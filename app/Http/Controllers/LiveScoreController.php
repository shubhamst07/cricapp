<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Services\CricketDataService;
use Illuminate\Http\Request;

class LiveScoreController extends Controller
{
    public function index(CricketDataService $cricket, Request $request)
    {
        if ($request->get('refresh') === 'true') {
            $cricket->clearMatchCache();
        }

        $currentUserId = auth()->id();
        $currentUserPhone = auth()->user()->phone ?? null;

        // 1. Fetch all local platform matches (with network matches sorted top)
        $localMatches = CricketMatch::getAllFormattedForLiveCenter($currentUserId, $currentUserPhone);

        // 2. Fetch external cric matches (with graceful fallback)
        $externalMatches = [];
        try {
            $externalMatches = $cricket->getCurrentMatches();
        } catch (\Throwable $e) {
            $externalMatches = [];
        }

        // 3. Merge: Local community & network matches lead the list, followed by external matches
        $matches = array_merge($localMatches, $externalMatches);

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

        $cacheTtl = $cricket->getCacheTtl();

        $tab = $request->get('tab');
        if (!in_array($tab, ['live', 'upcoming', 'recent'])) {
            $tab = $live->count() > 0 ? 'live' : ($completed->count() > 0 ? 'recent' : 'upcoming');
        }

        return view('live.index', compact('live', 'scheduled', 'completed', 'matches', 'cacheTtl', 'tab'));
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

        $match = $cricket->findMatchById($cricket->getCurrentMatches(), $matchId);

        if (!$match) {
            $match = $cricket->getMatchDetails($matchId);
            if (empty($match)) {
                abort(404, 'Match not found.');
            }
        }

        $scorecard = $cricket->getMatchScorecard($matchId);

        return view('live.show', compact('match', 'scorecard', 'forceRefresh'));
    }
}
