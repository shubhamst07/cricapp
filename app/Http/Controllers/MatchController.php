<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Models\MatchPlayer;
use App\Models\MatchPost;
use App\Models\PostComment;
use App\Models\Score;
use App\Models\Team;
use App\Services\CricketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchController extends Controller
{
    /**
     * CricHeroes-style Matches Center & Community Arena.
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?: Auth::user();
        $userId = $user?->id;

        // 1. Identify User's Associated & Network Matches (Creator, Squad Player, Followed Creators)
        $userOwnedIds = [];
        $userPlayingIds = [];
        $followingMatchIds = [];
        $networkMatchIds = [];

        if ($user) {
            $userOwnedIds = CricketMatch::where('user_id', $userId)->pluck('id')->toArray();

            $userPlayingIds = MatchPlayer::where('user_id', $userId)
                ->when($user->phone, fn($q) => $q->orWhere('player_phone', $user->phone))
                ->orWhere('player_name', $user->name)
                ->pluck('match_id')
                ->unique()
                ->toArray();

            $followingIds = $user->following()->pluck('users.id')->toArray();
            if (!empty($followingIds)) {
                $followingMatchIds = CricketMatch::whereIn('user_id', $followingIds)->pluck('id')->toArray();
            }

            $networkMatchIds = array_values(array_unique(array_merge($userOwnedIds, $userPlayingIds, $followingMatchIds)));
        }

        // 2. Query Builder with Eager Loading
        $query = CricketMatch::with(['scores', 'user', 'players']);

        // Scope filter: 'my' (only user network) vs 'all' (all matches across community)
        $scope = $request->get('scope', 'all');
        if ($scope === 'my' && $user) {
            $query->whereIn('id', !empty($networkMatchIds) ? $networkMatchIds : [0]);
        }

        // 3. Search Filter: Tournament, Player Name, Ground/Venue, or Teams
        $search = trim((string) $request->get('search', ''));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('tournament_name', 'LIKE', "%{$search}%")
                    ->orWhere('venue', 'LIKE', "%{$search}%")
                    ->orWhere('team1_name', 'LIKE', "%{$search}%")
                    ->orWhere('team2_name', 'LIKE', "%{$search}%")
                    ->orWhereHas('players', function ($pq) use ($search) {
                        $pq->where('player_name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 4. Status tab filter
        $tab = $request->get('tab', 'all');
        if ($tab === 'live') {
            $query->where('status', 'live');
        } elseif ($tab === 'upcoming' || $tab === 'scheduled') {
            $query->where('status', 'scheduled');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        }

        // 5. Smart Hierarchy Ordering:
        // Priority 1: User's owned matches
        // Priority 2: User's squad matches & network matches
        // Priority 3: Other community matches
        // Within each tier: recent match_date and created_at
        if ($user && !empty($networkMatchIds)) {
            $ownedList = !empty($userOwnedIds) ? implode(',', $userOwnedIds) : '0';
            $networkList = implode(',', $networkMatchIds);
            $query->orderByRaw("CASE 
                WHEN id IN ({$ownedList}) THEN 1 
                WHEN id IN ({$networkList}) THEN 2 
                ELSE 3 
            END ASC");
        }

        $matches = $query->orderByDesc('match_date')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // 6. Overall stats snapshot
        $baseStatsQuery = CricketMatch::query();
        if ($scope === 'my' && $user) {
            $baseStatsQuery->whereIn('id', !empty($networkMatchIds) ? $networkMatchIds : [0]);
        }

        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'live' => (clone $baseStatsQuery)->where('status', 'live')->count(),
            'upcoming' => (clone $baseStatsQuery)->where('status', 'scheduled')->count(),
            'completed' => (clone $baseStatsQuery)->where('status', 'completed')->count(),
            'my_network_count' => count($networkMatchIds),
        ];

        return view('matches.index', compact(
            'matches',
            'stats',
            'tab',
            'scope',
            'search',
            'userOwnedIds',
            'userPlayingIds',
            'followingMatchIds',
            'networkMatchIds'
        ));
    }

    /**
     * Show the form for creating a new match.
     */
    public function create(): View
    {
        $teams = Team::orderBy('name')->get();
        return view('matches.create', compact('teams'));
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'team1_name' => 'required|string|max:255',
            'team2_name' => 'required|string|max:255',
            'tournament_name' => 'nullable|string|max:255',
            'match_type' => 'nullable|string|max:50',
            'total_overs' => 'nullable|integer|min:1|max:100',
            'ball_type' => 'nullable|string|max:50',
            'venue' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'status' => 'required|in:scheduled,live,completed,abandoned',
            'toss_decision' => 'nullable|string|max:255',
            'team1_runs' => 'nullable|integer|min:0',
            'team1_wickets' => 'nullable|integer|min:0|max:10',
            'team1_overs' => 'nullable|string|max:10',
            'team2_runs' => 'nullable|integer|min:0',
            'team2_wickets' => 'nullable|integer|min:0|max:10',
            'team2_overs' => 'nullable|string|max:10',
            'match_result' => 'nullable|string|max:255',
            'player_of_match' => 'nullable|string|max:255',
            'stream_url' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->id ?? Auth::id();
        if (!$userId) {
            abort(401);
        }

        $match = CricketMatch::create([
            'user_id' => $userId,
            'team1_name' => $validated['team1_name'],
            'team2_name' => $validated['team2_name'],
            'tournament_name' => $validated['tournament_name'] ?? 'Friendly Match',
            'match_type' => $validated['match_type'] ?? 'T20',
            'total_overs' => $validated['total_overs'] ?? 20,
            'ball_type' => $validated['ball_type'] ?? 'Tennis Ball',
            'venue' => $validated['venue'] ?? 'Local Ground',
            'match_date' => $validated['match_date'],
            'status' => $validated['status'],
            'toss_decision' => $validated['toss_decision'] ?? null,
            'match_result' => $validated['match_result'] ?? null,
            'player_of_match' => $validated['player_of_match'] ?? null,
            'stream_url' => $validated['stream_url'] ?? null,
        ]);

        // Create initial scores if provided or if match is live/completed
        if ($request->filled('team1_runs') || $validated['status'] === 'live' || $validated['status'] === 'completed') {
            Score::create([
                'match_id' => $match->id,
                'inning_number' => 1,
                'batting_team' => $validated['team1_name'],
                'runs' => $request->input('team1_runs', 0),
                'wickets' => $request->input('team1_wickets', 0),
                'overs' => $request->input('team1_overs', '0.0'),
                'status' => $validated['status'] === 'completed' ? 'completed' : 'live',
            ]);
        }

        if ($request->filled('team2_runs') || $validated['status'] === 'completed') {
            Score::create([
                'match_id' => $match->id,
                'inning_number' => 2,
                'batting_team' => $validated['team2_name'],
                'runs' => $request->input('team2_runs', 0),
                'wickets' => $request->input('team2_wickets', 0),
                'overs' => $request->input('team2_overs', '0.0'),
                'status' => $validated['status'] === 'completed' ? 'completed' : 'live',
            ]);
        }

        // Store Team 1 Players
        if ($request->filled('team1_players')) {
            $t1Players = is_array($request->input('team1_players')) 
                ? $request->input('team1_players') 
                : json_decode($request->input('team1_players'), true);
            if (is_array($t1Players)) {
                foreach ($t1Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 1,
                            'team_name' => $validated['team1_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'Batter',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        // Store Team 2 Players
        if ($request->filled('team2_players')) {
            $t2Players = is_array($request->input('team2_players')) 
                ? $request->input('team2_players') 
                : json_decode($request->input('team2_players'), true);
            if (is_array($t2Players)) {
                foreach ($t2Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 2,
                            'team_name' => $validated['team2_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'Batter',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Match created successfully!',
                'match' => $match,
            ]);
        }

        return redirect()->route('matches.index')->with('status', 'Match created successfully!');
    }

    /**
     * Display the specified match with CricHeroes scoreboard.
     */
    public function show(CricketMatch $match): View
    {
        $match->load(['scores', 'players.user']);
        $teams = Team::orderBy('name')->get();

        $state = $match->live_state ?: [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];

        $this->ensureActiveMatchStateValid($state, $match);

        if ($state !== $match->live_state) {
            $match->update(['live_state' => $state]);
        }

        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $team1BattingSquad = $this->getSortedBattingSquad($match, 1, $state);
        $team2BattingSquad = $this->getSortedBattingSquad($match, 2, $state);

        return view('matches.show', compact('match', 'teams', 'state', 'striker', 'nonStriker', 'bowler', 'team1BattingSquad', 'team2BattingSquad'));
    }

    /**
     * In-App Live Camera Broadcast with TV Scoreboard Overlay (CricHeroes Signature Live).
     */
    public function broadcast(CricketMatch $match): View
    {
        $match->load(['scores', 'players.user']);
        $isOwner = auth()->check() && (auth()->id() === $match->user_id);

        $state = $match->live_state ?: [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];
        $this->ensureActiveMatchStateValid($state, $match);

        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $activeInningNum = $state['batting_team_number'] ?? 1;
        $score1 = $match->getScoreForTeam($match->team1_name);
        $score2 = $match->getScoreForTeam($match->team2_name);
        $currentScoreObj = $activeInningNum == 1 ? $score1 : $score2;

        return view('matches.broadcast', compact('match', 'state', 'striker', 'nonStriker', 'bowler', 'score1', 'score2', 'currentScoreObj', 'activeInningNum', 'isOwner'));
    }

    /**
     * Real-time Broadcast State API for dynamic overlay sync.
     */
    public function broadcastState(CricketMatch $match): JsonResponse
    {
        $match->load(['scores', 'players.user']);
        $state = $match->live_state ?: [];
        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $activeInningNum = $state['batting_team_number'] ?? 1;
        $score1 = $match->getScoreForTeam($match->team1_name);
        $score2 = $match->getScoreForTeam($match->team2_name);
        $currentScore = $activeInningNum == 1 ? $score1 : $score2;

        return response()->json([
            'status' => 'success',
            'match_status' => $match->status,
            'match_result' => $match->match_result,
            'active_inning' => $activeInningNum,
            'team1_name' => $match->team1_name,
            'team2_name' => $match->team2_name,
            'total_overs' => $match->total_overs,
            'score1' => $score1,
            'score2' => $score2,
            'current_score' => $currentScore,
            'striker' => $striker,
            'non_striker' => $nonStriker,
            'bowler' => $bowler,
            'this_over' => $state['this_over'] ?? [],
            'commentary' => array_slice($state['commentary'] ?? [], 0, 30),
        ]);
    }

    /**
     * Show the form for editing the specified match.
     */
    public function edit(CricketMatch $match): View
    {
        $this->authorizeMatch($match);
        $match->load(['players.user']);
        $teams = Team::orderBy('name')->get();
        return view('matches.edit', compact('match', 'teams'));
    }

    /**
     * Update the specified match.
     */
    public function update(Request $request, CricketMatch $match): RedirectResponse|JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'team1_name' => 'required|string|max:255',
            'team2_name' => 'required|string|max:255',
            'tournament_name' => 'nullable|string|max:255',
            'match_type' => 'nullable|string|max:50',
            'total_overs' => 'nullable|integer|min:1|max:100',
            'ball_type' => 'nullable|string|max:50',
            'venue' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'status' => 'required|in:scheduled,live,completed,abandoned',
            'toss_decision' => 'nullable|string|max:255',
            'match_result' => 'nullable|string|max:255',
            'player_of_match' => 'nullable|string|max:255',
            'stream_url' => 'nullable|string|max:500',
        ]);

        $match->update($validated);

        // Sync Team 1 Players if sent
        if ($request->filled('team1_players')) {
            $t1Players = is_array($request->input('team1_players'))
                ? $request->input('team1_players')
                : json_decode($request->input('team1_players'), true);
            if (is_array($t1Players)) {
                \App\Models\MatchPlayer::where('match_id', $match->id)->where('team_number', 1)->delete();
                foreach ($t1Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 1,
                            'team_name' => $validated['team1_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'All-Rounder',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        // Sync Team 2 Players if sent
        if ($request->filled('team2_players')) {
            $t2Players = is_array($request->input('team2_players'))
                ? $request->input('team2_players')
                : json_decode($request->input('team2_players'), true);
            if (is_array($t2Players)) {
                \App\Models\MatchPlayer::where('match_id', $match->id)->where('team_number', 2)->delete();
                foreach ($t2Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 2,
                            'team_name' => $validated['team2_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'All-Rounder',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Match updated successfully!',
                'match' => $match,
            ]);
        }

        return redirect()->route('matches.index')->with('status', 'Match updated successfully.');
    }

    /**
     * Quick Score Update (CricHeroes live scorer action).
     */
    public function quickScore(Request $request, CricketMatch $match): JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'inning_number' => 'required|integer|in:1,2',
            'batting_team' => 'required|string|max:255',
            'runs' => 'required|integer|min:0',
            'wickets' => 'required|integer|min:0|max:10',
            'overs' => 'required|string|max:10',
            'status' => 'nullable|string|in:scheduled,live,completed,abandoned',
            'match_result' => 'nullable|string|max:255',
        ]);

        $score = Score::updateOrCreate(
            [
                'match_id' => $match->id,
                'inning_number' => $validated['inning_number'],
            ],
            [
                'batting_team' => $validated['batting_team'],
                'runs' => $validated['runs'],
                'wickets' => $validated['wickets'],
                'overs' => $validated['overs'],
                'status' => ($validated['status'] ?? $match->status) === 'completed' ? 'completed' : 'live',
            ]
        );

        if (!empty($validated['status'])) {
            $match->status = $validated['status'];
        }
        if (!empty($validated['match_result'])) {
            $match->match_result = $validated['match_result'];
        }
        $match->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Live Score updated! ⚡',
            'score' => $score,
            'match' => $match->load('scores'),
        ]);
    }

    /**
     * CricHeroes update individual player runs, wickets, overs & dismissal stats.
     */
    public function updatePlayerStats(Request $request, CricketMatch $match, \App\Models\MatchPlayer $player): JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'runs_scored' => 'required|integer|min:0',
            'balls_faced' => 'required|integer|min:0',
            'fours' => 'nullable|integer|min:0',
            'sixes' => 'nullable|integer|min:0',
            'dismissal' => 'nullable|string|max:100',
            'overs_bowled' => 'nullable|string|max:10',
            'maidens' => 'nullable|integer|min:0',
            'runs_conceded' => 'nullable|integer|min:0',
            'wickets_taken' => 'nullable|integer|min:0|max:10',
            'sync_team_score' => 'nullable|boolean',
        ]);

        $player->update([
            'runs_scored' => $validated['runs_scored'],
            'balls_faced' => $validated['balls_faced'],
            'fours' => $validated['fours'] ?? 0,
            'sixes' => $validated['sixes'] ?? 0,
            'dismissal' => $validated['dismissal'] ?: 'not out',
            'overs_bowled' => $validated['overs_bowled'] ?: '0.0',
            'maidens' => $validated['maidens'] ?? 0,
            'runs_conceded' => $validated['runs_conceded'] ?? 0,
            'wickets_taken' => $validated['wickets_taken'] ?? 0,
        ]);

        // Auto-link registered user if phone matches
        $user = $player->user;
        if (!$user && $player->player_phone) {
            $clean = preg_replace('/[^0-9]/', '', $player->player_phone);
            $user = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
            if ($user) {
                $player->update(['user_id' => $user->id]);
            }
        }

        // Recalculate player's career passport stats
        if ($user) {
            $user->recalculateCricketStats();
        }

        // Optional: auto-sync team totals
        if (!empty($validated['sync_team_score'])) {
            $teamPlayers = \App\Models\MatchPlayer::where('match_id', $match->id)
                ->where('team_number', $player->team_number)
                ->get();
            $teamTotalRuns = $teamPlayers->sum('runs_scored');
            $teamTotalWickets = $teamPlayers->filter(fn($p) => $p->dismissal !== 'not out' && $p->dismissal !== 'dnb')->count();

            // Calculate overs from opponent bowlers
            $opponentTeamNum = $player->team_number == 1 ? 2 : 1;
            $opponentBowlers = \App\Models\MatchPlayer::where('match_id', $match->id)
                ->where('team_number', $opponentTeamNum)
                ->get();
            $totalBalls = 0;
            foreach ($opponentBowlers as $ob) {
                if ($ob->overs_bowled && $ob->overs_bowled !== '0.0') {
                    $parts = explode('.', (string) $ob->overs_bowled);
                    $totalBalls += ((int) ($parts[0] ?? 0) * 6) + (int) ($parts[1] ?? 0);
                }
            }
            $calculatedOvers = floor($totalBalls / 6) . '.' . ($totalBalls % 6);
            $existingOvers = $match->getScoreForTeam($player->team_name)?->overs;
            $finalOvers = ($totalBalls > 0) ? $calculatedOvers : ($existingOvers ?: '0.0');

            $score = $match->scores()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'inning_number' => $player->team_number,
                ],
                [
                    'batting_team' => $player->team_name,
                    'runs' => $teamTotalRuns,
                    'wickets' => $teamTotalWickets,
                    'overs' => $finalOvers,
                    'status' => $match->status === 'completed' ? 'completed' : 'live',
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => "Stats updated for {$player->player_name}! 🏏",
            'player' => $player->fresh()->load('user'),
        ]);
    }

    /**
     * CricHeroes Authentic Ball-By-Ball Scoring Action.
     */
    public function recordBall(Request $request, CricketMatch $match): JsonResponse
    {
        $this->authorizeMatch($match);

        $action = $request->input('action', 'run');
        $state = $match->live_state ?: [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];

        $battingTeamNumber = $state['batting_team_number'] ?? 1;
        $battingTeamName = $battingTeamNumber == 1 ? $match->team1_name : $match->team2_name;
        $score = $match->scores()->firstOrCreate(
            [
                'match_id' => $match->id,
                'inning_number' => $battingTeamNumber,
            ],
            [
                'batting_team' => $battingTeamName,
                'runs' => 0,
                'wickets' => 0,
                'overs' => '0.0',
                'status' => 'live',
            ]
        );

        // Save previous snapshot for 1-click Undo
        if ($action !== 'undo') {
            $history = $state['history'] ?? [];
            $history[] = [
                'state' => [
                    'striker_id' => $state['striker_id'] ?? null,
                    'non_striker_id' => $state['non_striker_id'] ?? null,
                    'bowler_id' => $state['bowler_id'] ?? null,
                    'this_over' => $state['this_over'] ?? [],
                    'commentary' => $state['commentary'] ?? [],
                ],
                'score' => [
                    'runs' => $score->runs,
                    'wickets' => $score->wickets,
                    'overs' => $score->overs,
                ],
                'striker_stats' => ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id'])?->only(['runs_scored', 'balls_faced', 'fours', 'sixes', 'dismissal']) : null,
                'bowler_stats' => ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id'])?->only(['overs_bowled', 'maidens', 'runs_conceded', 'wickets_taken']) : null,
            ];
            if (count($history) > 20) array_shift($history);
            $state['history'] = $history;
        }

        // Action: Select Openers & Bowler
        if ($action === 'select_openers') {
            $strikerId = $request->input('striker_id') ? (int) $request->input('striker_id') : null;
            $nonStrikerId = $request->input('non_striker_id') ? (int) $request->input('non_striker_id') : null;
            $state['striker_id'] = $strikerId;
            $state['non_striker_id'] = $nonStrikerId;
            $state['bowler_id'] = $request->input('bowler_id') ? (int) $request->input('bowler_id') : null;
            $state['this_over'] = [];

            $teamNum = $state['batting_team_number'] ?? 1;
            $state["batting_order_{$teamNum}"] = array_values(array_filter([$strikerId, $nonStrikerId]));

            $match->update(['live_state' => $state, 'status' => 'live']);

            return $this->cricHeroesResponse($match, 'Openers & Opening Bowler ready! Game ON 🏏');
        }

        // Action: Select / Change Bowler
        if ($action === 'select_bowler') {
            $bowlerId = (int) $request->input('bowler_id');
            $bowlingTeamNumber = ($state['batting_team_number'] ?? 1) == 1 ? 2 : 1;
            $validBowler = $match->players()->where('team_number', $bowlingTeamNumber)->find($bowlerId);
            if (!$validBowler) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid bowler selected! Must be from the fielding team.'
                ], 422);
            }

            $state['bowler_id'] = $bowlerId;
            if (!empty($state['needs_new_bowler'])) {
                $state['this_over'] = [];
                $state['needs_new_bowler'] = false;
            }
            $match->update(['live_state' => $state]);

            return $this->cricHeroesResponse($match, "Bowler ready: {$validBowler->player_name}! 🎯");
        }

        // Action: Swap Strike (Batter Strike Rotation)
        if ($action === 'swap_strike') {
            $tmp = $state['striker_id'];
            $state['striker_id'] = $state['non_striker_id'];
            $state['non_striker_id'] = $tmp;
            $match->update(['live_state' => $state]);

            return $this->cricHeroesResponse($match, 'Strike rotated! 🔄');
        }

        // Action: Undo Last Ball
        if ($action === 'undo') {
            $history = $state['history'] ?? [];
            if (!empty($history)) {
                $last = array_pop($history);
                $state['striker_id'] = $last['state']['striker_id'];
                $state['non_striker_id'] = $last['state']['non_striker_id'];
                $state['bowler_id'] = $last['state']['bowler_id'];
                $state['this_over'] = $last['state']['this_over'];
                if (isset($last['state']['commentary'])) {
                    $state['commentary'] = $last['state']['commentary'];
                }
                $state['history'] = $history;
                $state['needs_new_bowler'] = false;

                $score->update($last['score']);

                if (!empty($last['striker_stats']) && $state['striker_id']) {
                    \App\Models\MatchPlayer::where('id', $state['striker_id'])->update($last['striker_stats']);
                }
                if (!empty($last['bowler_stats']) && $state['bowler_id']) {
                    \App\Models\MatchPlayer::where('id', $state['bowler_id'])->update($last['bowler_stats']);
                }

                $match->update(['live_state' => $state]);
                return $this->cricHeroesResponse($match, 'Last delivery undone! ↩️');
            }
            return $this->cricHeroesResponse($match, 'Nothing to undo.');
        }

        // Action: Switch Innings (Move to 2nd Innings)
        if ($action === 'switch_innings') {
            $currentInning = $state['batting_team_number'] ?? 1;
            $newInning = $currentInning == 1 ? 2 : 1;
            $state['inning'] = $newInning;
            $state['batting_team_number'] = $newInning;
            $state['bowling_team_number'] = $newInning == 1 ? 2 : 1;
            $state['striker_id'] = null;
            $state['non_striker_id'] = null;
            $state['bowler_id'] = null;
            $state['this_over'] = [];
            $state['needs_new_bowler'] = false;

            $this->ensureActiveMatchStateValid($state, $match);

            $match->update(['live_state' => $state]);

            $battingTeamName = $newInning == 1 ? $match->team1_name : $match->team2_name;
            $bowlingTeamName = $newInning == 1 ? $match->team2_name : $match->team1_name;

            // Ensure current inning score completed
            $prevScore = $match->scores()->where('inning_number', $currentInning)->first();
            if ($prevScore && $prevScore->status !== 'completed') {
                $prevScore->update(['status' => 'completed']);
            }

            // Ensure new inning score exists
            $newScore = $match->scores()->firstOrCreate(
                ['match_id' => $match->id, 'inning_number' => $newInning],
                [
                    'batting_team' => $battingTeamName,
                    'runs' => 0,
                    'wickets' => 0,
                    'overs' => '0.0',
                    'status' => 'live',
                ]
            );
            if ($newScore->status !== 'live' && $match->status !== 'completed') {
                $newScore->update(['status' => 'live']);
            }

            return $this->cricHeroesResponse($match, "Innings {$newInning} started! {$battingTeamName} batting 🏏");
        }

        // Match Overs & Completion checks
        $totalMatchOvers = (int) ($match->total_overs ?: 20);
        $maxBalls = $totalMatchOvers * 6;
        $currentBalls = $this->getOversInBalls($score->overs);

        // Check if match is already completed
        if ($match->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Match is already completed! 🏆',
                'match_completed' => true,
            ], 422);
        }

        // Check if current inning has already finished its total overs
        if (!in_array($action, ['select_bowler', 'select_openers', 'switch_innings', 'undo'])) {
            if ($currentBalls >= $maxBalls) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Innings {$score->inning_number} complete! Max overs limit ({$totalMatchOvers}.0 ov) reached.",
                    'inning_completed' => true,
                ], 422);
            }
        }

        // Validate state before scoring
        $this->ensureActiveMatchStateValid($state, $match);

        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        // CRITICAL SCORING VALIDATION: Reject scoring if bowler or batsmen are not selected
        if (!in_array($action, ['select_bowler', 'select_openers', 'switch_innings', 'undo'])) {
            if (!$bowler || $bowler->match_id !== $match->id || $bowler->team_number == $battingTeamNumber) {
                return response()->json([
                    'status' => 'error',
                    'message' => '⚠️ Please select a Bowler from the bowling team first!',
                    'needs_new_bowler' => true,
                ], 422);
            }

            if (!empty($state['needs_new_bowler'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => '⚠️ Over completed! Please select a Bowler for the next over.',
                    'needs_new_bowler' => true,
                ], 422);
            }

            if (!$striker || !$nonStriker || $striker->match_id !== $match->id || $nonStriker->match_id !== $match->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => '⚠️ Please select Striker and Non-Striker first!',
                    'needs_openers' => true,
                ], 422);
            }
        }

        $isLegalDelivery = false;
        $overBallText = '0';
        $runsScored = 0;
        $isWicket = false;
        $overCompleted = false;

        // Normal Run (0, 1, 2, 3, 4, 6)
        if ($action === 'run') {
            $runsScored = (int) $request->input('runs', 0);
            $isLegalDelivery = true;
            $overBallText = (string) $runsScored;

            if ($striker) {
                $striker->runs_scored += $runsScored;
                $striker->balls_faced += 1;
                if ($runsScored === 4) $striker->fours += 1;
                if ($runsScored === 6) $striker->sixes += 1;
                $striker->save();
                $striker->user?->recalculateCricketStats();
            }

            if ($bowler) {
                $bowler->runs_conceded += $runsScored;
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            }

            $score->runs += $runsScored;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            // Odd runs rotate strike!
            if (in_array($runsScored, [1, 3, 5])) {
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
                $this->ensureActiveMatchStateValid($state, $match);
            }
        } 
        // Wide or No Ball (Extras)
        elseif ($action === 'wide' || $action === 'no_ball') {
            $extraRuns = (int) $request->input('runs', 1);
            $overBallText = ($action === 'wide') ? ($extraRuns > 1 ? "{$extraRuns}WD" : 'WD') : ($extraRuns > 1 ? "{$extraRuns}NB" : 'NB');
            $isLegalDelivery = false;

            if ($bowler) {
                $bowler->runs_conceded += $extraRuns;
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            }

            $score->runs += $extraRuns;
            $score->save();
        } 
        // Byes / Leg Byes
        elseif ($action === 'bye' || $action === 'leg_bye') {
            $runs = (int) $request->input('runs', 1);
            $isLegalDelivery = true;
            $overBallText = ($action === 'bye') ? "{$runs}B" : "{$runs}LB";

            if ($striker) {
                $striker->balls_faced += 1;
                $striker->save();
            }
            if ($bowler) {
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
            }

            $score->runs += $runs;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            if (in_array($runs, [1, 3])) {
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
            }
        } 
        // Wicket
        elseif ($action === 'wicket') {
            $isLegalDelivery = true;
            $isWicket = true;
            $overBallText = 'W';
            $howOut = $request->input('how_out', 'bowled');
            $dismissalText = $howOut;
            if ($bowler && !in_array($howOut, ['run out', 'timed out'])) {
                $dismissalText .= " b {$bowler->player_name}";
            }

            if ($striker) {
                $striker->balls_faced += 1;
                $striker->dismissal = $dismissalText;
                $striker->save();
                $striker->user?->recalculateCricketStats();
            }

            if ($bowler && $howOut !== 'run out') {
                $bowler->wickets_taken += 1;
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            } elseif ($bowler) {
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
            }

            $score->wickets += 1;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            // Next batsman: if not specified, automatically find next available batsman in order
            $nextId = $request->input('next_batsman_id');
            if ($nextId) {
                $state['striker_id'] = (int) $nextId;
            } else {
                $state['striker_id'] = null;
            }
            $this->ensureActiveMatchStateValid($state, $match);
        }

        // Add to this_over delivery chips
        $thisOver = $state['this_over'] ?? [];
        $thisOver[] = $overBallText;
        $state['this_over'] = $thisOver;

        // Generate Dynamic CricHeroes/Cricbuzz Ball-by-Ball Commentary
        $shot = $request->input('shot');
        $customText = $request->input('commentary');
        $deliveryBallNumber = $this->formatDeliveryBallNumber((string) $score->overs, $isLegalDelivery);
        $runsForCommentary = ($action === 'run') ? $runsScored : (($action === 'wide' || $action === 'no_ball') ? ($extraRuns ?? 1) : (($action === 'bye' || $action === 'leg_bye') ? ($runs ?? 1) : 0));

        $commentaryItem = $this->generateBallCommentary(
            action: $action,
            runs: $runsForCommentary,
            shot: $shot,
            howOut: $isWicket ? ($howOut ?? 'bowled') : null,
            striker: $striker,
            bowler: $bowler,
            customText: $customText,
            deliveryBall: $deliveryBallNumber,
            badge: $overBallText,
            teamScore: "{$score->runs}/{$score->wickets}"
        );

        $commentaryList = $state['commentary'] ?? [];
        array_unshift($commentaryList, $commentaryItem);
        if (count($commentaryList) > 150) {
            $commentaryList = array_slice($commentaryList, 0, 150);
        }
        $state['commentary'] = $commentaryList;

        // Check if over completed (legal ball % 6 == 0)
        if ($isLegalDelivery) {
            $parts = explode('.', (string) $score->overs);
            $ballsInOver = (int) ($parts[1] ?? 0);
            if ($ballsInOver === 0) {
                $overCompleted = true;
                // Strike automatically rotates for new over!
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
                $state['needs_new_bowler'] = true;
                $this->ensureActiveMatchStateValid($state, $match);
            }
        }

        // Check for Innings Completion / Match Result
        $newBalls = $this->getOversInBalls($score->overs);
        $inningCompleted = false;
        $matchCompleted = false;
        $matchResultText = '';

        $score1 = $match->scores()->where('inning_number', 1)->first();

        if ($score->inning_number == 1) {
            $isAllOut = $score->wickets >= 10 || !empty($state['all_out']);
            if ($newBalls >= $maxBalls || $isAllOut) {
                $inningCompleted = true;
                $score->status = 'completed';
                $score->save();
            }
        } elseif ($score->inning_number == 2) {
            $target = ($score1 ? $score1->runs : 0) + 1;
            $isAllOut = $score->wickets >= 10 || !empty($state['all_out']);

            // Case A: Target Chased
            if ($score->runs >= $target) {
                $matchCompleted = true;
                $wicketsLeft = max(1, 10 - $score->wickets);
                $matchResultText = "{$match->team2_name} won by {$wicketsLeft} wickets";
            }
            // Case B: Overs Finished or All Out
            elseif ($newBalls >= $maxBalls || $isAllOut) {
                $matchCompleted = true;
                if ($score->runs < ($score1 ? $score1->runs : 0)) {
                    $margin = ($score1->runs) - $score->runs;
                    $matchResultText = "{$match->team1_name} won by {$margin} runs";
                } elseif ($score->runs == ($score1 ? $score1->runs : 0)) {
                    $matchResultText = "Match Tied!";
                }
            }

            if ($matchCompleted) {
                $score->status = 'completed';
                $score->save();
                $match->status = 'completed';
                $match->match_result = $matchResultText;
                $match->save();
            }
        }

        $match->update(['live_state' => $state]);

        $customMsg = $isWicket ? 'WICKET! ☝️' : ($runsScored == 6 ? 'SIX! 💥' : ($runsScored == 4 ? 'FOUR! 🏏' : 'Ball recorded! ⚡'));
        if ($matchCompleted) {
            $customMsg = "🏆 MATCH OVER! {$matchResultText}";
        } elseif ($inningCompleted) {
            $customMsg = "🏏 Innings 1 Complete! Target: " . (($score1 ? $score1->runs : 0) + 1);
        }

        return $this->cricHeroesResponse(
            $match,
            $customMsg,
            [
                'over_completed' => $overCompleted,
                'wicket' => $isWicket,
                'inning_completed' => $inningCompleted,
                'match_completed' => $matchCompleted,
                'match_result' => $matchResultText,
            ]
        );
    }

    public function getOversInBalls(string|float|null $overs): int
    {
        $parts = explode('.', (string) ($overs ?: '0.0'));
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0);
        return ($ov * 6) + $bl;
    }

    protected function ensureActiveMatchStateValid(array &$state, CricketMatch $match): void
    {
        $battingTeamNumber = $state['batting_team_number'] ?? 1;
        $bowlingTeamNumber = $battingTeamNumber == 1 ? 2 : 1;

        $battingPlayers = $match->players()->where('team_number', $battingTeamNumber)->get();
        $bowlingPlayers = $match->players()->where('team_number', $bowlingTeamNumber)->get();

        // 1. Validate Batsmen
        $isPlayerOut = function(?int $playerId) use ($battingPlayers) {
            if (!$playerId) return true;
            $p = $battingPlayers->firstWhere('id', $playerId);
            if (!$p) return true;
            $d = strtolower(trim((string)$p->dismissal));
            return !empty($d) && $d !== 'not out';
        };

        $availableNotOut = function(array $excludeIds = []) use ($battingPlayers) {
            return $battingPlayers->filter(function($p) use ($excludeIds) {
                if (in_array($p->id, $excludeIds)) return false;
                $d = strtolower(trim((string)$p->dismissal));
                return empty($d) || $d === 'not out';
            });
        };

        // If striker is missing, invalid or already OUT, replace with next available not-out batsman
        if ($isPlayerOut($state['striker_id'] ?? null)) {
            $exclude = array_filter([$state['non_striker_id'] ?? null]);
            $next = $availableNotOut($exclude)->first();
            $state['striker_id'] = $next?->id ?? null;
        }

        // If non-striker is missing, invalid, already OUT, or duplicate of striker, replace
        if ($isPlayerOut($state['non_striker_id'] ?? null) || (!empty($state['non_striker_id']) && $state['non_striker_id'] === ($state['striker_id'] ?? null))) {
            $exclude = array_filter([$state['striker_id'] ?? null]);
            $next = $availableNotOut($exclude)->first();
            $state['non_striker_id'] = $next?->id ?? null;
        }

        // Check if both are empty (team is all out)
        if (empty($state['striker_id']) && empty($state['non_striker_id'])) {
            $state['all_out'] = true;
        }

        // 2. Validate Bowler: MUST belong to this match and bowling team
        $currentBowlerId = $state['bowler_id'] ?? null;
        $isValidBowler = false;
        if ($currentBowlerId) {
            $isValidBowler = $bowlingPlayers->contains('id', $currentBowlerId);
        }

        if (!$isValidBowler && $bowlingPlayers->isNotEmpty()) {
            // Find a preferred bowler (roles with Bowler or All-Rounder) or first available
            $preferred = $bowlingPlayers->first(function($p) {
                $role = strtolower((string)$p->player_role);
                return str_contains($role, 'bowler') || str_contains($role, 'all-rounder');
            }) ?? $bowlingPlayers->first();

            $state['bowler_id'] = $preferred?->id ?? null;
        } elseif (!$isValidBowler) {
            $state['bowler_id'] = null;
        }

        // 3. Batting order tracking: Track chronological order of batsmen coming to the crease
        $orderKey = "batting_order_{$battingTeamNumber}";
        $currentOrder = $state[$orderKey] ?? [];
        if (!empty($state['striker_id']) && !in_array((int)$state['striker_id'], $currentOrder)) {
            $currentOrder[] = (int) $state['striker_id'];
        }
        if (!empty($state['non_striker_id']) && !in_array((int)$state['non_striker_id'], $currentOrder)) {
            $currentOrder[] = (int) $state['non_striker_id'];
        }
        $state[$orderKey] = array_values(array_unique(array_filter($currentOrder)));
    }

    public function getSortedBattingSquad(CricketMatch $match, int $teamNumber, array $state): \Illuminate\Support\Collection
    {
        $players = $teamNumber == 1 ? $match->team1Players : $match->team2Players;
        $orderKey = "batting_order_{$teamNumber}";
        $battingOrder = $state[$orderKey] ?? [];

        $currentStrikerId = $state['striker_id'] ?? null;
        $currentNonStrikerId = $state['non_striker_id'] ?? null;

        if (empty($battingOrder) && ($state['batting_team_number'] ?? 1) == $teamNumber) {
            $battingOrder = array_values(array_filter([$currentStrikerId, $currentNonStrikerId]));
        }

        return $players->sort(function ($a, $b) use ($battingOrder, $currentStrikerId, $currentNonStrikerId) {
            $aInOrder = array_search($a->id, $battingOrder);
            $bInOrder = array_search($b->id, $battingOrder);

            $aIsOut = $a->dismissal && !in_array(strtolower(trim($a->dismissal)), ['not out', 'yet to bat', 'did not bat', 'dnb', '']);
            $bIsOut = $b->dismissal && !in_array(strtolower(trim($b->dismissal)), ['not out', 'yet to bat', 'did not bat', 'dnb', '']);

            $aIsAtCrease = in_array($a->id, array_filter([$currentStrikerId, $currentNonStrikerId]));
            $bIsAtCrease = in_array($b->id, array_filter([$currentStrikerId, $currentNonStrikerId]));

            $aHasBatted = ($aInOrder !== false) || $a->balls_faced > 0 || $a->runs_scored > 0 || $aIsOut || $aIsAtCrease;
            $bHasBatted = ($bInOrder !== false) || $b->balls_faced > 0 || $b->runs_scored > 0 || $bIsOut || $bIsAtCrease;

            // Batsmen who have batted come before those who have not
            if ($aHasBatted && !$bHasBatted) return -1;
            if (!$aHasBatted && $bHasBatted) return 1;

            // Both batted: sort by batting order of appearance (index in batting_order)
            if ($aHasBatted && $bHasBatted) {
                if ($aInOrder !== false && $bInOrder !== false) {
                    return $aInOrder <=> $bInOrder;
                }
                if ($aInOrder !== false) return -1;
                if ($bInOrder !== false) return 1;
                return $a->id <=> $b->id;
            }

            // Neither batted: sort by squad order / id
            return $a->id <=> $b->id;
        })->values();
    }

    protected function incrementOversString(?string $overs): string
    {
        $parts = explode('.', (string) ($overs ?: '0.0'));
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0) + 1;
        if ($bl >= 6) {
            $ov += 1;
            $bl = 0;
        }
        return "{$ov}.{$bl}";
    }

    protected function incrementBowlerBall(\App\Models\MatchPlayer $bowler): \App\Models\MatchPlayer
    {
        $parts = explode('.', (string) ($bowler->overs_bowled ?: '0.0'));
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0) + 1;
        if ($bl >= 6) {
            $ov += 1;
            $bl = 0;
        }
        $bowler->overs_bowled = "{$ov}.{$bl}";
        return $bowler;
    }

    /**
     * Compute delivery ball label (e.g. 0.1, 0.6, 1.2, etc.).
     */
    protected function formatDeliveryBallNumber(string $currentOvers, bool $isLegalDelivery): string
    {
        $parts = explode('.', $currentOvers ?: '0.0');
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0);
        if ($isLegalDelivery && $bl === 0 && $ov > 0) {
            return ($ov - 1) . '.6';
        }
        return "{$ov}.{$bl}";
    }

    /**
     * Generate dynamic CricHeroes / Cricbuzz style ball-by-ball commentary.
     */
    protected function generateBallCommentary(
        string $action,
        int $runs,
        ?string $shot,
        ?string $howOut,
        ?\App\Models\MatchPlayer $striker,
        ?\App\Models\MatchPlayer $bowler,
        ?string $customText,
        string $deliveryBall,
        string $badge,
        string $teamScore
    ): array {
        $strikerName = $striker ? $striker->player_name : 'Batter';
        $bowlerName = $bowler ? $bowler->player_name : 'Bowler';
        $shot = strtolower(trim((string) $shot));

        $badgeType = 'dot';
        if ($action === 'wicket') {
            $badgeType = 'wicket';
        } elseif ($action === 'wide' || $action === 'no_ball') {
            $badgeType = 'extra';
        } elseif ($action === 'bye' || $action === 'leg_bye') {
            $badgeType = 'bye';
        } elseif ($runs === 4) {
            $badgeType = 'four';
        } elseif ($runs === 6) {
            $badgeType = 'six';
        } elseif ($runs > 0) {
            $badgeType = 'run';
        }

        // Custom commentary entered by scorer takes top priority
        if (!empty($customText)) {
            $desc = trim($customText);
            $heading = "{$bowlerName} to {$strikerName}";
            if ($badgeType === 'four') $heading .= ', FOUR!';
            elseif ($badgeType === 'six') $heading .= ', SIX!';
            elseif ($badgeType === 'wicket') $heading .= ', OUT!';
            return [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'ball' => $deliveryBall,
                'badge' => $badge,
                'badge_type' => $badgeType,
                'bowler' => $bowlerName,
                'striker' => $strikerName,
                'heading' => $heading,
                'text' => $desc,
                'shot' => $shot ?: null,
                'team_score' => $teamScore,
                'time' => now()->format('h:i A'),
            ];
        }

        // Automatic smart commentary generation
        $heading = "{$bowlerName} to {$strikerName}";
        $desc = '';

        if ($action === 'wicket') {
            $howOutNorm = strtolower(trim((string) $howOut));
            $heading .= ', OUT!';
            if ($howOutNorm === 'bowled') {
                $lines = [
                    "Clean bowled! Stumps shattered as {$strikerName} plays all over a good length ball. Absolute timber!",
                    "Bowled 'em! What a peach of a delivery, breaches the defence and knocks back middle stump!",
                    "Knocked him over! Right through the gate, {$strikerName} has to walk back disappointed!"
                ];
                $desc = $lines[array_rand($lines)];
            } elseif ($howOutNorm === 'caught') {
                $lines = [
                    "In the air... and TAKEN! Mistimed lofted stroke, the fielder under it makes no mistake!",
                    "Edged and taken! Thick edge carried safely to the fielder, huge breakthrough for {$bowlerName}!",
                    "Caught! Tried to clear the infield but didn't get enough elevation, safe hands in the outfield!"
                ];
                $desc = $lines[array_rand($lines)];
            } elseif ($howOutNorm === 'lbw') {
                $lines = [
                    "Loud shout for LBW and the finger goes UP! Plumb in front of the middle stump!",
                    "Trapped on the crease! Full and skidding through, beat the inside edge, umpire raises the finger!"
                ];
                $desc = $lines[array_rand($lines)];
            } elseif ($howOutNorm === 'run out') {
                $lines = [
                    "RUN OUT! Direct hit! Total hesitation between the batters and {$strikerName} is caught well short of the crease!",
                    "Run out! Superb athletic pick-up and throw, bails removed in a flash! What a piece of fielding!"
                ];
                $desc = $lines[array_rand($lines)];
            } elseif ($howOutNorm === 'stumped') {
                $desc = "Stumped! Danced down the wicket, beaten by the flight and turn, keeper whips the bails off in a flash!";
            } else {
                $desc = "Wicket falls! Significant breakthrough as {$strikerName} departs ({$howOutNorm}).";
            }
        } elseif ($runs === 6) {
            $heading .= ', SIX!';
            if (str_contains($shot, 'cover')) {
                $desc = "Inside out over extra cover! Stood tall and lofted with immense style, clears the boundary ropes!";
            } elseif (str_contains($shot, 'straight') || str_contains($shot, 'long_on')) {
                $desc = "Straight as an arrow! Lofted cleanly right back over the bowler's head into the sightscreen for a massive six!";
            } elseif (str_contains($shot, 'pull') || str_contains($shot, 'midwicket') || str_contains($shot, 'hook')) {
                $desc = "Dispatched with immense authority! Short ball hammered high and deep over mid-wicket into the stands!";
            } elseif (str_contains($shot, 'point') || str_contains($shot, 'cut')) {
                $desc = "Carved over backward point! Tremendous bat speed, sliced high and clears the rope for half a dozen!";
            } elseif (str_contains($shot, 'sweep')) {
                $desc = "Smoked over deep square leg! Got down on one knee and slog-swept high into the crowd!";
            } else {
                $lines = [
                    "Massive hit! Got right under the delivery and launched it high into the night sky for a colossal maximum!",
                    "Smoked into the stands! Sweet sound off the bat as {$strikerName} clears the boundary with ease!",
                    "Dispatched! In the slot and punished handsomely over the ropes for a 6!"
                ];
                $desc = $lines[array_rand($lines)];
            }
        } elseif ($runs === 4) {
            $heading .= ', FOUR!';
            if (str_contains($shot, 'cover')) {
                $desc = "Glorious cover drive! Leans into the drive with high elbow and creams it through extra cover to the fence!";
            } elseif (str_contains($shot, 'cut') || str_contains($shot, 'point')) {
                $desc = "Square cut! Short and wide outside off, {$strikerName} pounces on it and slashes it past backward point!";
            } elseif (str_contains($shot, 'pull') || str_contains($shot, 'hook')) {
                $desc = "Pulled away with disdain! Rocked onto the backfoot and hammered firmly through mid-wicket for four!";
            } elseif (str_contains($shot, 'straight') || str_contains($shot, 'drive')) {
                $desc = "Pure textbook straight drive! Driven on the up past mid-on and mid-off, races to the boundary!";
            } elseif (str_contains($shot, 'midwicket') || str_contains($shot, 'flick')) {
                $desc = "Whipped off the pads! Sublime wristwork, finds the gap through mid-wicket for a boundary!";
            } elseif (str_contains($shot, 'sweep')) {
                $desc = "Swept cleanly! Down on one knee, rolls the wrists and beats the fielder in the deep!";
            } elseif (str_contains($shot, 'edge')) {
                $desc = "Edged and away! Thick outside edge flies safely past the slip cordon down to the third man fence!";
            } else {
                $lines = [
                    "Crunched through the gap! Superb timing and placement, beating the diving fielders to the ropes!",
                    "Cracking boundary! Found the gap to perfection and races across the outfield!",
                    "Class written all over that stroke! Pierces the infield and races away for four!"
                ];
                $desc = $lines[array_rand($lines)];
            }
        } elseif ($action === 'wide') {
            $heading .= ', WIDE';
            $desc = "Slipped down the leg side, well past the tramline. Umpire signals wide delivery, extra run.";
        } elseif ($action === 'no_ball') {
            $heading .= ', NO BALL';
            $desc = "Overstepped the bowling crease! Umpire calls No Ball, extra run awarded and Free Hit coming up!";
        } elseif ($action === 'bye') {
            $heading .= ', Bye';
            $desc = "Beats the bat and keeper fails to collect cleanly, batters steal a bye.";
        } elseif ($action === 'leg_bye') {
            $heading .= ', Leg Bye';
            $desc = "Struck on the pad, deflected away on the on-side, batters hustle across for a leg bye.";
        } elseif ($runs === 1) {
            $heading .= ', 1 run';
            if (str_contains($shot, 'cover')) {
                $desc = "Pushed gently into the covers with soft hands for a quick single.";
            } elseif (str_contains($shot, 'point') || str_contains($shot, 'cut')) {
                $desc = "Guided towards backward point, rotates the strike nicely.";
            } elseif (str_contains($shot, 'straight')) {
                $desc = "Punched down the ground to long-on for an easy single.";
            } elseif (str_contains($shot, 'midwicket') || str_contains($shot, 'flick')) {
                $desc = "Tucked away off the hips towards mid-wicket to rotate strike.";
            } else {
                $lines = [
                    "Worked into the gap on the leg side for a sensible single.",
                    "Tapped softly into the off-side and scampered through for a brisk single.",
                    "Pushed into the gap, easy single taken to rotate the strike."
                ];
                $desc = $lines[array_rand($lines)];
            }
        } elseif ($runs === 2) {
            $heading .= ', 2 runs';
            $desc = "Driven into the deep pocket, excellent hustle between the wickets to turn one into two.";
        } elseif ($runs === 3) {
            $heading .= ', 3 runs';
            $desc = "Superb running between the wickets! Placed nicely into the outfield, batters push hard for three.";
        } else {
            // Dot ball (0 runs)
            $heading .= ', no run';
            $lines = [
                "Defended solidly off the front foot back towards the bowler.",
                "Beaten outside off! Pitched on a good length and zipped past the outside edge.",
                "Punched towards cover, fielder cuts it off cleanly, no run.",
                "Good length on middle, defended patiently into the pitch."
            ];
            $desc = $lines[array_rand($lines)];
        }

        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'ball' => $deliveryBall,
            'badge' => $badge,
            'badge_type' => $badgeType,
            'bowler' => $bowlerName,
            'striker' => $strikerName,
            'heading' => $heading,
            'text' => $desc,
            'shot' => $shot ?: null,
            'team_score' => $teamScore,
            'time' => now()->format('h:i A'),
        ];
    }

    protected function cricHeroesResponse(CricketMatch $match, string $msg, array $extra = []): JsonResponse
    {
        $match->load(['scores', 'players.user']);
        $state = $match->live_state ?: [];
        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $score = $match->scores->where('inning_number', $state['batting_team_number'] ?? 1)->first();

        return response()->json(array_merge([
            'status' => 'success',
            'message' => $msg,
            'state' => $state,
            'striker' => $striker,
            'non_striker' => $nonStriker,
            'bowler' => $bowler,
            'score' => $score,
            'match' => $match,
        ], $extra));
    }

    /**
     * Create a fresh Rematch (copying teams, match settings, and both squads without stats).
     */
    public function rematch(Request $request, CricketMatch $match): RedirectResponse|JsonResponse
    {
        $this->authorizeMatch($match);
        $match->load(['players']);

        // 1. Create cloned match
        $newMatch = CricketMatch::create([
            'user_id' => Auth::id(),
            'team1_name' => $match->team1_name,
            'team2_name' => $match->team2_name,
            'tournament_name' => $match->tournament_name ?? 'Friendly Match',
            'match_type' => $match->match_type ?? 'T20',
            'total_overs' => $match->total_overs ?? 20,
            'ball_type' => $match->ball_type ?? 'Tennis Ball',
            'venue' => $match->venue ?? 'Local Ground',
            'match_date' => now()->toDateString(),
            'status' => 'live',
            'toss_decision' => null,
            'match_result' => null,
            'player_of_match' => null,
            'stream_url' => $match->stream_url ?? null,
        ]);

        // 2. Create blank scores for Inning 1 and Inning 2
        Score::create([
            'match_id' => $newMatch->id,
            'inning_number' => 1,
            'batting_team' => $newMatch->team1_name,
            'runs' => 0,
            'wickets' => 0,
            'overs' => '0.0',
            'status' => 'live',
        ]);

        Score::create([
            'match_id' => $newMatch->id,
            'inning_number' => 2,
            'batting_team' => $newMatch->team2_name,
            'runs' => 0,
            'wickets' => 0,
            'overs' => '0.0',
            'status' => 'scheduled',
        ]);

        // 3. Clone all squad players with reset match stats
        foreach ($match->players as $p) {
            MatchPlayer::create([
                'match_id' => $newMatch->id,
                'user_id' => $p->user_id,
                'team_number' => $p->team_number,
                'team_name' => $p->team_name,
                'player_name' => $p->player_name,
                'player_phone' => $p->player_phone,
                'player_role' => $p->player_role,
                'is_captain' => $p->is_captain,
                'is_wicketkeeper' => $p->is_wicketkeeper,
                'runs_scored' => 0,
                'balls_faced' => 0,
                'fours' => 0,
                'sixes' => 0,
                'dismissal' => 'not out',
                'overs_bowled' => '0.0',
                'maidens' => 0,
                'runs_conceded' => 0,
                'wickets_taken' => 0,
            ]);
        }

        // 4. Initialize valid live state with openers & bowler for new match
        $newMatch->load(['players']);
        $state = [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];
        $this->ensureActiveMatchStateValid($state, $newMatch);
        $newMatch->update(['live_state' => $state]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Rematch created! Squads copied successfully 🏏',
                'match' => $newMatch,
                'redirect_url' => route('matches.show', $newMatch),
            ]);
        }

        return redirect()->route('matches.show', $newMatch)->with('status', 'Rematch created! Both squads copied successfully 🏏');
    }

    /**
     * Remove the specified match from storage.
     */
    public function destroy(CricketMatch $match): RedirectResponse
    {
        $this->authorizeMatch($match);
        $match->delete();

        return redirect()->route('matches.index')->with('status', 'Match deleted successfully.');
    }

    /**
     * Custom match authorization check.
     */
    protected function authorizeMatch(CricketMatch $match): void
    {
        $userId = Auth::id();
        if (!$userId || $match->user_id !== $userId) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Dashboard view.
     */
    public function dashboard(Request $request, CricketDataService $cricket): View
    {
        $userId = $request->user()?->id ?? Auth::id();

        $userMatches = $userId
            ? CricketMatch::with(['scores'])
                ->where('user_id', $userId)
                ->orderByDesc('match_date')
                ->get()
            : collect([]);

        $totalMatches = $userMatches->count();
        $liveMatches = $userMatches->where('status', 'live')->count();

        // 1. Fetch real-time Cricbuzz editorial news
        try {
            $newsArticles = $cricket->getCricketNews();
            if (!empty($newsArticles)) {
                foreach (array_slice($newsArticles, 0, 10) as $news) {
                    $newsTitle = $news['title'] ?? '';
                    $newsImage = $news['image'] ?? null;
                    $newsStory = $news['story'] ?? '';
                    $newsContext = $news['context'] ?? 'CRICKET NEWS';

                    if (!empty($newsTitle)) {
                        $fullContent = "📰 **" . $newsTitle . "**\n\n" . $newsStory;
                        $existingPost = MatchPost::where('content', 'LIKE', '%' . substr($newsTitle, 0, 30) . '%')->first();

                        if (!$existingPost) {
                            MatchPost::create([
                                'match_id' => '147942',
                                'user_id' => null,
                                'user_name' => 'Cricbuzz Editorial',
                                'avatar_color' => 'emerald',
                                'content' => $fullContent,
                                'post_type' => 'news',
                                'media_url' => $newsImage,
                                'insta_url' => null,
                                'team_tag' => strtoupper($newsContext),
                                'likes_count' => rand(15, 85),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // News sync optional
        }

        // 2. Fetch all real live matches from Cricbuzz
        $cricbuzzMatches = collect([]);
        try {
            $fetched = $cricket->getCurrentMatches();
            $cricbuzzMatches = collect($fetched)->map(function ($item) {
                return [
                    'id' => (string) ($item['id'] ?? 'cric_' . rand(1000, 9999)),
                    'title' => $item['title'] ?? 'Cricket Match',
                    'name' => $item['title'] ?? ($item['name'] ?? 'Cricket Match'),
                    'team1' => $item['team1'] ?? 'Team A',
                    'team2' => $item['team2'] ?? 'Team B',
                    'teams' => [$item['team1'] ?? 'Team A', $item['team2'] ?? 'Team B'],
                    'score1' => $item['score1'] ?? '',
                    'score2' => $item['score2'] ?? '',
                    'status' => $item['status'] ?? 'Live',
                    'series' => $item['series'] ?? 'Live Series',
                ];
            });
        } catch (\Throwable $e) {
            $cricbuzzMatches = collect([]);
        }

        $allPosts = MatchPost::with('comments')
            ->latest()
            ->take(60)
            ->get();

        $allPosts->transform(function ($post) use ($cricbuzzMatches) {
            $matched = $cricbuzzMatches->firstWhere('id', (string) $post->match_id);
            $post->match_name = $matched ? $matched['title'] : ($post->team_tag ? $post->team_tag . ' Match' : 'Live Cricket Match');
            $post->series_name = $matched ? $matched['series'] : 'Cricket Series';
            $post->match_status = $matched ? $matched['status'] : 'Live';
            return $post;
        });

        return view('dashboard', [
            'totalMatches' => $totalMatches,
            'liveMatches' => $liveMatches,
            'cricbuzzMatches' => $cricbuzzMatches,
            'allPosts' => $allPosts,
        ]);
    }
}
