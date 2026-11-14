<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Services\CricketDataService;
use Illuminate\Http\Request;

class LiveScoreController extends Controller
{
    public function index(CricketDataService $cricket, Request $request)
    {
        $section = $request->get('section', 'international');
        if (!in_array($section, ['international', 'local'])) {
            $section = 'international';
        }

        try {
            $forceRefresh = $request->get('refresh') === 'true';
            if ($forceRefresh) {
                $cricket->clearMatchCache();
            }

            $currentUserId = auth()->id();
            $currentUserPhone = auth()->user()->phone ?? null;

            $indexCacheKey = 'live_index_matches_' . ($currentUserId ?: 'guest');
            $staleCacheKey = $indexCacheKey . '_stale';
            if ($forceRefresh) {
                \Illuminate\Support\Facades\Cache::forget($indexCacheKey);
                \Illuminate\Support\Facades\Cache::forget($staleCacheKey);
            }

            $bundle = \Illuminate\Support\Facades\Cache::get($indexCacheKey);

            if (!$bundle) {
                $staleBundle = \Illuminate\Support\Facades\Cache::get($staleCacheKey);
                if ($staleBundle && !$forceRefresh) {
                    // Immediate sub-second response using warm stale cache
                    $bundle = $staleBundle;
                } else {
                    $bundle = $this->buildLiveIndexBundle($cricket, $currentUserId, $currentUserPhone, $forceRefresh);
                    \Illuminate\Support\Facades\Cache::put($indexCacheKey, $bundle, 45);
                    \Illuminate\Support\Facades\Cache::put($staleCacheKey, $bundle, 600);
                }
            }

            $live = collect($bundle['live'] ?? []);
            $scheduled = collect($bundle['scheduled'] ?? []);
            $completed = collect($bundle['completed'] ?? []);
            $matches = is_array($bundle['matches'] ?? null) ? $bundle['matches'] : (isset($bundle['all']) && is_array($bundle['all']) ? $bundle['all'] : []);

            $cacheTtl = $cricket->getCacheTtl();

            $tab = $request->get('tab');
            if (!in_array($tab, ['live', 'upcoming', 'recent', 'archives'])) {
                $tab = $live->count() > 0 ? 'live' : ($completed->count() > 0 ? 'recent' : 'upcoming');
            }

            return view('live.index', compact('live', 'scheduled', 'completed', 'matches', 'cacheTtl', 'tab', 'section'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LiveScoreController@index error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return view('live.index', [
                'live' => collect([]),
                'scheduled' => collect([]),
                'completed' => collect([]),
                'matches' => [],
                'cacheTtl' => ['current_matches' => 30],
                'tab' => 'live',
                'section' => $section,
            ]);
        }
    }

    protected function buildLiveIndexBundle(CricketDataService $cricket, ?int $currentUserId, ?string $currentUserPhone, bool $forceRefresh): array
    {
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

            // 2. Completed text detection (won by, beat, tied, drawn, etc.)
            $isCompletedText = str_contains($statusLower, 'won by') ||
                str_contains($statusLower, 'won the match') ||
                str_contains($statusLower, 'won the series') ||
                str_contains($statusLower, 'beat ') ||
                str_contains($statusLower, 'tied') ||
                str_contains($statusLower, 'drawn') ||
                str_contains($statusLower, 'conceded') ||
                str_contains($statusLower, 'match ended');

            // 3. INNINGS BREAK / Ongoing Live Sessions / Chasing / Toss / Weather Delays: ALWAYS LIVE, NEVER COMPLETED!
            $isInningsBreak = str_contains($statusLower, 'innings break') ||
                str_contains($statusLower, 'inn break') ||
                str_contains($statusLower, 'break') ||
                str_contains($statusLower, 'lunch') ||
                str_contains($statusLower, 'tea') ||
                str_contains($statusLower, 'dinner') ||
                str_contains($statusLower, 'strategic timeout') ||
                str_contains($statusLower, 'drinks');
            $isChasing = str_contains($statusLower, 'need ') || str_contains($statusLower, 'require ') || str_contains($statusLower, 'target ');
            $isToss = (str_contains($statusLower, 'opt to') || str_contains($statusLower, 'elected to') || str_contains($statusLower, 'chose to') || (str_contains($statusLower, 'toss') && !str_contains($statusLower, 'abandon')));
            $isTestOngoing = (str_contains($statusLower, 'stump') || str_contains($statusLower, 'trail') || str_contains($statusLower, 'lead') || preg_match('/day\s*[1-4]/i', $statusLower))
                && !$isCompletedText;
            $isInProgress = str_contains($statusLower, 'in progress') || str_contains($statusLower, 'rain') || str_contains($statusLower, 'delay') || $isChasing || $isToss || $isTestOngoing || $isInningsBreak;

            // An Innings Break or ongoing live session takes 100% priority over any premature matchEnded flag!
            if ($isInProgress && !$isCompletedText) {
                $m['status'] = 'live';
                $m['matchEnded'] = false;
                $m['matchStarted'] = true;
                return $m;
            }

            if ($isCompletedText) {
                $m['status'] = 'completed';
                $m['matchEnded'] = true;
                $m['matchStarted'] = true;
                return $m;
            }

            $isEnded = ($m['matchEnded'] ?? false) || ($m['status'] ?? '') === 'completed';

            if ($isEnded) {
                $m['status'] = 'completed';
                $m['matchEnded'] = true;
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
    }

    public function show(string $matchId, CricketDataService $cricket, Request $request)
    {
        // Redirect local platform matches directly to matches.show
        if (str_starts_with($matchId, 'local_')) {
            $localId = str_replace('local_', '', $matchId);
            return redirect()->route('matches.show', $localId);
        }
        try {
            if (is_numeric($matchId) && CricketMatch::where('id', $matchId)->exists()) {
                return redirect()->route('matches.show', $matchId);
            }
        } catch (\Throwable $e) {
            // Ignore DB check if offline or unreachable
        }

        $forceRefresh = $request->get('refresh') === 'true';

        if ($forceRefresh) {
            $cricket->clearMatchCache($matchId);
        }

        // Fast cached match lookup (never blocks for 10s on Cricbuzz round trips)
        $match = null;
        if (!$forceRefresh) {
            $cachedList = $cricket->getCachedMatches();
            if (!empty($cachedList)) {
                $match = $cricket->findMatchById($cachedList, $matchId);
            }
        }

        if (!$match) {
            $match = $cricket->findMatchById($cricket->getCurrentMatches($forceRefresh), $matchId);
        }

        // Fallback: If match was not found in current matches list, fetch details
        if (!$match) {
            $details = $cricket->getMatchDetails($matchId);
            $match = $details;
            if (empty($match)) {
                abort(404, 'Match not found.');
            }
        }

        // Fast RAM/disk cache lookup for scorecard and commentary - progressive asynchronous loading
        $scorecard = $cricket->getCachedScorecard($matchId) ?? ['status' => 'success', 'data' => []];
        $commentary = $cricket->getCachedCommentary($matchId);
        if (empty($commentary) || empty($commentary['data'])) {
            try {
                $commentary = $cricket->getMatchCommentary($matchId);
            } catch (\Throwable $e) {
                $commentary = ['status' => 'success', 'data' => []];
            }
        }

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

        return view('live.show', compact('match', 'scorecard', 'commentary', 'forceRefresh'));
    }

    public function pointsTable(Request $request, CricketDataService $cricket)
    {
        $seriesId = $request->query('series_id');
        $seriesName = $request->query('series_name');
        $data = $cricket->getPointsTable($seriesId, $seriesName);
        return response()->json($data);
    }
}
