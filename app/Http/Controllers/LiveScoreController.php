<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Services\CricketDataService;
use Illuminate\Http\Request;

class LiveScoreController extends Controller
{
    public function index(CricketDataService $cricket, Request $request)
    {
        $forceRefresh = $request->get('refresh') === 'true';
        if ($forceRefresh) {
            $cricket->clearMatchCache();
        }

        $currentUserId = auth()->id();
        $currentUserPhone = auth()->user()->phone ?? null;

        $indexCacheKey = 'live_index_matches_' . ($currentUserId ?: 'guest');
        if ($forceRefresh) {
            \Illuminate\Support\Facades\Cache::forget($indexCacheKey);
        }

        $bundle = \Illuminate\Support\Facades\Cache::remember($indexCacheKey, 10, function () use ($cricket, $currentUserId, $currentUserPhone, $forceRefresh) {
            // 1. Fetch all local platform matches (with network matches sorted top)
            $localMatches = CricketMatch::getAllFormattedForLiveCenter($currentUserId, $currentUserPhone);

            // 2. Fetch external cric matches (with graceful fallback)
            $externalMatches = [];
            try {
                $externalMatches = $cricket->getCurrentMatches($forceRefresh);
            } catch (\Throwable $e) {
                $externalMatches = [];
            }

            // 3. Merge: Local community & network matches lead the list, followed by external matches
            $matches = array_merge($localMatches, $externalMatches);

            // Ensure any match with active score data is marked correctly as completed or live
            $matches = array_map(function ($m) {
                // Local platform matches are already strictly categorized by the match admin/database
                if (!empty($m['is_local']) || str_starts_with((string)($m['id'] ?? ''), 'local_')) {
                    return $m;
                }

                $statusLower = strtolower(trim($m['status_text'] ?? ''));

                // 1. Abandoned / Cancelled / No Result matches must NEVER be live or upcoming!
                $isAbandoned = str_contains($statusLower, 'abandon') || str_contains($statusLower, 'cancel') || str_contains($statusLower, 'no result') || ($m['status'] ?? '') === 'abandoned';
                if ($isAbandoned) {
                    $m['status'] = 'abandoned';
                    $m['matchEnded'] = true;
                    $m['matchStarted'] = true;
                    return $m;
                }

                // 2. Completed text detection
                $isCompletedText = str_contains($statusLower, 'won by') ||
                    str_contains($statusLower, 'won the match') ||
                    str_contains($statusLower, 'beat') ||
                    str_contains($statusLower, 'tied') ||
                    str_contains($statusLower, 'drawn') ||
                    str_contains($statusLower, 'conceded') ||
                    str_contains($statusLower, 'match ended');

                $isEnded = ($m['matchEnded'] ?? false) || ($m['status'] ?? '') === 'completed' || $isCompletedText;

                if ($isEnded) {
                    $m['status'] = 'completed';
                    $m['matchEnded'] = true;
                    $m['matchStarted'] = true;
                    return $m;
                }

                // 3. Ongoing toss or test match day in progress
                $isToss = (str_contains($statusLower, 'opt to') || str_contains($statusLower, 'elected to') || str_contains($statusLower, 'chose to') || (str_contains($statusLower, 'toss') && !str_contains($statusLower, 'abandon')));
                $isTestOngoing = (str_contains($statusLower, 'stump') || str_contains($statusLower, 'trail') || str_contains($statusLower, 'lead') || preg_match('/day\s*[1-4]/i', $statusLower))
                    && !str_contains($statusLower, 'won') && !str_contains($statusLower, 'beat') && !str_contains($statusLower, 'drawn');

                if ($isTestOngoing || $isToss) {
                    $m['status'] = 'live';
                    $m['matchEnded'] = false;
                    $m['matchStarted'] = true;
                    return $m;
                }

                // Scheduled matches with no scores are definitely upcoming
                if (($m['status'] ?? '') === 'scheduled' && empty($m['score'])) {
                    return $m;
                }

                if (($m['status'] ?? '') === 'scheduled' && !empty($m['score'])) {
                    $m['status'] = 'live';
                    $m['matchStarted'] = true;
                    if (in_array($statusLower, ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                        $m['status_text'] = 'In Progress';
                    }
                }
                return $m;
            }, $matches);

            $live = collect($matches)->where('status', 'live')->values();
            $scheduled = collect($matches)->where('status', 'scheduled')->values();
            $completed = collect($matches)->filter(fn($m) => in_array($m['status'] ?? '', ['completed', 'abandoned']))->values();

            // If no scheduled matches found in currentMatches, try to get upcoming matches from all matches
            if ($scheduled->isEmpty()) {
                try {
                    $upcomingFromAll = $cricket->getUpcomingMatches();
                    if (!empty($upcomingFromAll)) {
                        $scheduled = collect($upcomingFromAll)->values();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            return compact('live', 'scheduled', 'completed', 'matches');
        });

        $live = $bundle['live'];
        $scheduled = $bundle['scheduled'];
        $completed = $bundle['completed'];
        $matches = $bundle['matches'];

        $cacheTtl = $cricket->getCacheTtl();

        $section = $request->get('section', 'international');
        if (!in_array($section, ['international', 'local'])) {
            $section = 'international';
        }

        $tab = $request->get('tab');
        if (!in_array($tab, ['live', 'upcoming', 'recent', 'archives'])) {
            $tab = $live->count() > 0 ? 'live' : ($completed->count() > 0 ? 'recent' : 'upcoming');
        }

        return view('live.index', compact('live', 'scheduled', 'completed', 'matches', 'cacheTtl', 'tab', 'section'));
    }

    public function show(string $matchId, CricketDataService $cricket, Request $request)
    {
        // Redirect local platform matches directly to matches.show
        if (str_starts_with($matchId, 'local_')) {
            $localId = str_replace('local_', '', $matchId);
            return redirect()->route('matches.show', $localId);
        }
        if (is_numeric($matchId) && CricketMatch::where('id', $matchId)->exists()) {
            return redirect()->route('matches.show', $matchId);
        }

        $forceRefresh = $request->get('refresh') === 'true';

        if ($forceRefresh) {
            $cricket->clearMatchCache($matchId);
        }

        $pageCacheKey = "page_show_match_{$matchId}";
        if ($forceRefresh) {
            \Illuminate\Support\Facades\Cache::forget($pageCacheKey);
        }

        $bundle = \Illuminate\Support\Facades\Cache::remember($pageCacheKey, 8, function () use ($cricket, $matchId, $forceRefresh) {
            $match = $cricket->findMatchById($cricket->getCurrentMatches($forceRefresh), $matchId);

            // Only fetch details if match was not found in current matches list
            if (!$match) {
                $details = $cricket->getMatchDetails($matchId);
                $match = $details;
                if (empty($match)) {
                    abort(404, 'Match not found.');
                }
            }

            $scorecard = $cricket->getMatchScorecard($matchId);
            $commentary = $cricket->getMatchCommentary($matchId);

            if (empty($match['player_of_the_match']) && !empty($scorecard['data']['player_of_the_match'])) {
                $match['player_of_the_match'] = $scorecard['data']['player_of_the_match'];
            }

            // Auto-correct status if scorecard has live innings or batting data
            $scInnings = $scorecard['data']['innings'] ?? [];
            $scBatting = $scorecard['data']['batting'] ?? [];
            $hasActiveScorecard = !empty($scInnings) || !empty($scBatting);

            if ($hasActiveScorecard && in_array($match['status'] ?? '', ['scheduled', 'upcoming', ''])) {
                $match['status'] = 'live';
                $match['matchStarted'] = true;
                if (in_array(strtolower(trim($match['status_text'] ?? '')), ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                    $firstInn = $scInnings[0] ?? null;
                    $match['status_text'] = $firstInn && !empty($firstInn['team']) ? "{$firstInn['team']} batting ({$firstInn['score']})" : 'Match in progress';
                }
            }

            return compact('match', 'scorecard', 'commentary');
        });

        $match = $bundle['match'];
        $scorecard = $bundle['scorecard'];
        $commentary = $bundle['commentary'];

        return view('live.show', compact('match', 'scorecard', 'commentary', 'forceRefresh'));
    }
}
