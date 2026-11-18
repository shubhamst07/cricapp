<?php

namespace App\Http\Controllers;

use App\Models\CricketCircle;
use App\Models\Ground;
use App\Models\MatchMeetup;
use App\Models\MatchPlayer;
use App\Models\MeetupParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MeetupController extends Controller
{
    /**
     * Display a listing of upcoming cricket match meetups & circles.
     */
    public function index(Request $request): View
    {
        $city = $request->input('city');
        $when = $request->input('when', 'all');
        $ballType = $request->input('ball_type');
        $search = $request->input('q');

        $query = MatchMeetup::with(['user', 'ground', 'circle', 'activeParticipants.user'])
            ->where('match_date', '>=', Carbon::today())
            ->orderBy('match_date', 'asc')
            ->orderBy('match_time', 'asc');

        // Filter by City
        if (!empty($city) && strtolower($city) !== 'all') {
            $query->where('city', 'LIKE', '%' . trim($city) . '%');
        }

        // Filter by Timing
        if ($when === 'today') {
            $query->whereDate('match_date', Carbon::today());
        } elseif ($when === 'tomorrow') {
            $query->whereDate('match_date', Carbon::tomorrow());
        } elseif ($when === 'weekend') {
            $saturday = Carbon::now()->next(Carbon::SATURDAY);
            $sunday = Carbon::now()->next(Carbon::SUNDAY);
            if (Carbon::now()->isWeekend()) {
                $query->whereBetween('match_date', [Carbon::today(), Carbon::now()->endOfWeek()]);
            } else {
                $query->whereBetween('match_date', [$saturday, $sunday]);
            }
        }

        // Filter by Ball Type
        if (!empty($ballType) && $ballType !== 'all') {
            $query->where('ball_type', $ballType);
        }

        // Search query
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('location_name', 'LIKE', "%{$search}%");
            });
        }

        try {
            $meetups = $query->paginate(12)->withQueryString();
        } catch (\Throwable $e) {
            $meetups = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12);
        }

        // Popular / Active Cities for Quick Filter Pills
        try {
            $popularCities = MatchMeetup::where('match_date', '>=', Carbon::today())
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->filter()
                ->values();
        } catch (\Throwable $e) {
            $popularCities = collect([]);
        }

        // Default cities if empty
        if ($popularCities->isEmpty()) {
            $popularCities = collect(['Shimla', 'Theog', 'Solan', 'Chandigarh', 'Delhi']);
        }

        // City Circles for local community
        try {
            $circlesQuery = CricketCircle::withCount('members')
                ->where('is_active', true);
            if (!empty($city) && strtolower($city) !== 'all') {
                $circlesQuery->where('city', 'LIKE', '%' . trim($city) . '%');
            }
            $circles = $circlesQuery->take(6)->get();
        } catch (\Throwable $e) {
            $circles = collect([]);
        }

        return view('meetups.index', compact('meetups', 'popularCities', 'circles', 'city', 'when', 'ballType', 'search'));
    }

    /**
     * Show form to create a new match meetup / player request.
     */
    public function create(): View
    {
        try {
            $grounds = Ground::where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'inactive');
            })->orderBy('name')->get();
            if ($grounds->isEmpty()) {
                $grounds = Ground::orderBy('name')->get();
            }
        } catch (\Throwable $e) {
            $grounds = collect([]);
        }

        try {
            $circles = CricketCircle::where('is_active', true)->orderBy('name')->get();
        } catch (\Throwable $e) {
            $circles = collect([]);
        }

        // Pre-fill upcoming Sunday date as convenient default
        $defaultDate = Carbon::now()->isSunday() ? Carbon::today() : Carbon::now()->next(Carbon::SUNDAY);

        return view('meetups.create', compact('grounds', 'circles', 'defaultDate'));
    }

    /**
     * Store a newly created match meetup in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'city' => 'required|string|max:100',
            'location_name' => 'required|string|max:150',
            'location_address' => 'nullable|string|max:255',
            'google_maps_url' => 'nullable|url|max:500',
            'match_date' => 'required|date|after_or_equal:today',
            'match_time' => 'required|string|max:30',
            'ball_type' => 'required|string|in:tennis,leather,tape,box_turf',
            'match_format' => 'required|string|in:friendly,tournament_practice,box_cricket,open_net',
            'skill_level' => 'required|string|in:all,beginner,intermediate,advanced',
            'total_players' => 'required|integer|min:2|max:30',
            'slots_needed' => 'required|integer|min:1|max:25',
            'cost_per_player' => 'nullable|string|max:50',
            'contact_phone' => 'nullable|string|max:20',
            'contact_whatsapp' => 'nullable|string|max:20',
            'whatsapp_group_link' => 'nullable|url|max:255',
            'ground_id' => 'nullable|exists:grounds,id',
            'cricket_circle_id' => 'nullable|exists:cricket_circles,id',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'open';

        $meetup = MatchMeetup::create($validated);

        // Auto-add the creator/organizer as the first confirmed participant
        MeetupParticipant::create([
            'match_meetup_id' => $meetup->id,
            'user_id' => Auth::id(),
            'player_name' => Auth::user()->name,
            'player_role' => Auth::user()->player_role ?? 'Captain / Organizer',
            'contact_phone' => $meetup->contact_phone ?: (Auth::user()->phone ?? null),
            'status' => 'confirmed',
            'notes' => 'Match Organizer',
        ]);

        return redirect()->route('meetups.show', $meetup)
            ->with('success', '🏏 Match Meetup published successfully! Other nearby players can now join your squad.');
    }

    /**
     * Display the specified match meetup with full squad list & WhatsApp connect.
     */
    public function show(MatchMeetup $meetup): View
    {
        $meetup->load(['user', 'ground', 'circle', 'activeParticipants.user']);

        $user = Auth::user();
        $isJoined = $user ? $meetup->isUserJoined($user->id) : false;
        $isOrganizer = $user && ($user->id === $meetup->user_id || ($user->is_admin ?? false));
        $slotsLeft = $meetup->slotsLeft();

        return view('meetups.show', compact('meetup', 'isJoined', 'isOrganizer', 'slotsLeft'));
    }

    /**
     * Join a match meetup (RSVP / I'm In).
     */
    public function join(Request $request, MatchMeetup $meetup): RedirectResponse
    {
        $user = Auth::user();

        if ($meetup->isUserJoined($user->id)) {
            return back()->with('info', 'You are already in this squad!');
        }

        if ($meetup->isFull()) {
            return back()->with('error', '⚠️ Sorry, all vacant player slots for this match are already filled!');
        }

        $validated = $request->validate([
            'player_role' => 'nullable|string|in:Batsman,Bowler,All-Rounder,Wicket-Keeper',
            'contact_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:200',
        ]);

        MeetupParticipant::create([
            'match_meetup_id' => $meetup->id,
            'user_id' => $user->id,
            'player_name' => $user->name,
            'player_role' => $validated['player_role'] ?? ($user->player_role ?? 'All-Rounder'),
            'contact_phone' => $validated['contact_phone'] ?? ($user->phone ?? null),
            'status' => 'confirmed',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Auto update status to full if all slots filled
        if ($meetup->slotsLeft() <= 0) {
            $meetup->update(['status' => 'full']);
        }

        return back()->with('success', '🎉 Welcome to the squad! You are registered to play in this match.');
    }

    /**
     * Leave a match meetup (withdraw slot).
     */
    public function leave(MatchMeetup $meetup): RedirectResponse
    {
        $user = Auth::user();

        if ($user->id === $meetup->user_id) {
            return back()->with('error', 'As match organizer, you cannot leave the squad. You can edit or cancel the meetup instead.');
        }

        $participant = MeetupParticipant::where('match_meetup_id', $meetup->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->delete();

            // Re-open if was previously full
            if ($meetup->status === 'full') {
                $meetup->update(['status' => 'open']);
            }
        }

        return back()->with('success', 'You have withdrawn from this match squad. Your slot is now open for other players.');
    }

    /**
     * Organizer removes a participant from squad.
     */
    public function removeParticipant(MatchMeetup $meetup, MeetupParticipant $participant): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $meetup->user_id && !($user->is_admin ?? false)) {
            abort(403);
        }

        if ($participant->user_id === $meetup->user_id) {
            return back()->with('error', 'Cannot remove organizer.');
        }

        $participant->delete();

        if ($meetup->status === 'full') {
            $meetup->update(['status' => 'open']);
        }

        return back()->with('success', 'Player removed from squad.');
    }

    /**
     * Delete / Cancel a match meetup.
     */
    public function destroy(MatchMeetup $meetup): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $meetup->user_id && !($user->is_admin ?? false)) {
            abort(403);
        }

        $meetup->delete();

        return redirect()->route('meetups.index')
            ->with('success', 'Match meetup cancelled and deleted.');
    }

    /**
     * Create a permanent City / Area Cricket Circle.
     */
    public function storeCircle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'whatsapp_link' => 'nullable|url|max:255',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['is_active'] = true;

        $circle = CricketCircle::create($validated);

        // Add creator as admin member
        $circle->members()->attach(Auth::id(), ['role' => 'admin']);

        return back()->with('success', "🎉 Cricket Circle '{$circle->name}' created in {$circle->city}! Players can now join your community.");
    }

    /**
     * Join a City Cricket Circle.
     */
    public function joinCircle(CricketCircle $circle): RedirectResponse
    {
        $user = Auth::user();
        if (!$circle->isUserMember($user->id)) {
            $circle->members()->attach($user->id, ['role' => 'member']);
        }

        return back()->with('success', "You have joined the '{$circle->name}' Cricket Circle! 🏏");
    }

    /**
     * Leave a City Cricket Circle.
     */
    public function leaveCircle(CricketCircle $circle): RedirectResponse
    {
        $user = Auth::user();
        $circle->members()->detach($user->id);

        return back()->with('success', "You have left '{$circle->name}'.");
    }

    /**
     * Get player career statistics for a meetup participant.
     */
    public function participantCareer(MatchMeetup $meetup, MeetupParticipant $participant): JsonResponse
    {
        $user = $participant->user;
        if (!$user && $participant->user_id) {
            $user = User::find($participant->user_id);
        }
        if (!$user && !empty($participant->contact_phone)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $participant->contact_phone);
            if (strlen($cleanPhone) >= 4) {
                $user = User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$cleanPhone}%"])->first();
            }
        }
        if (!$user) {
            $user = User::whereRaw('LOWER(name) = ?', [strtolower(trim($participant->player_name))])->first();
        }

        return response()->json($this->buildCareerData(
            playerName: $participant->player_name,
            playerRole: $participant->player_role,
            playerPhone: $participant->contact_phone,
            teamName: $meetup->title,
            user: $user,
            isCaptain: $participant->user_id === $meetup->user_id
        ));
    }

    /**
     * Get player career statistics for a user.
     */
    public function userCareer(User $user): JsonResponse
    {
        return response()->json($this->buildCareerData(
            playerName: $user->name,
            playerRole: $user->player_role ?? 'All-Rounder',
            playerPhone: $user->phone,
            teamName: 'LiveCricket Platform',
            user: $user,
            isCaptain: false
        ));
    }

    /**
     * Helper to compute unified CricHeroes career statistics.
     */
    protected function buildCareerData(string $playerName, ?string $playerRole, ?string $playerPhone, string $teamName, ?User $user, bool $isCaptain = false): array
    {
        $cleanName = strtolower(trim($playerName));
        $perfQuery = MatchPlayer::query();

        if ($user) {
            $perfQuery->where(function ($q) use ($user, $cleanName, $playerPhone) {
                $q->where('user_id', $user->id)
                  ->orWhereRaw('LOWER(player_name) = ?', [$cleanName]);
                if (!empty($playerPhone)) {
                    $q->orWhere('player_phone', $playerPhone);
                }
            });
        } else {
            $perfQuery->where(function ($q) use ($cleanName, $playerPhone) {
                $q->whereRaw('LOWER(player_name) = ?', [$cleanName]);
                if (!empty($playerPhone)) {
                    $q->orWhere('player_phone', $playerPhone);
                }
            });
        }

        $performances = $perfQuery->with('match')->get();

        if ($performances->isNotEmpty()) {
            $matchesCount = $performances->pluck('match_id')->unique()->count();
            $inningsBatted = $performances->filter(function ($p) {
                return $p->balls_faced > 0 || $p->runs_scored > 0 || ($p->dismissal && !in_array(strtolower(trim($p->dismissal)), ['yet to bat', 'dnb', '']));
            })->count();
            $totalRuns = (int) $performances->sum('runs_scored');
            $totalBalls = (int) $performances->sum('balls_faced');
            $totalFours = (int) $performances->sum('fours');
            $totalSixes = (int) $performances->sum('sixes');
            $fifties = $performances->filter(fn($p) => $p->runs_scored >= 50 && $p->runs_scored < 100)->count();
            $hundreds = $performances->filter(fn($p) => $p->runs_scored >= 100)->count();
            $highestScore = (int) ($performances->max('runs_scored') ?? 0);
            $highestPerf = $performances->where('runs_scored', $highestScore)->first();
            $highestStr = (string) $highestScore;
            if ($highestPerf && ($highestPerf->dismissal === 'not out' || empty($highestPerf->dismissal)) && $highestScore > 0) {
                $highestStr .= '*';
            }
            $timesOut = $performances->filter(function ($p) {
                return $p->balls_faced > 0 && !in_array(strtolower(trim($p->dismissal ?? '')), ['not out', 'dnb', 'yet to bat', '']);
            })->count();
            $battingAvg = $timesOut > 0 ? round($totalRuns / $timesOut, 2) : ($inningsBatted > 0 ? $totalRuns : 0.0);
            $strikeRate = $totalBalls > 0 ? round(($totalRuns / $totalBalls) * 100, 2) : 0.0;
            $notOuts = $performances->filter(function ($p) {
                return ($p->balls_faced > 0 || $p->runs_scored > 0) && ($p->dismissal === 'not out' || empty($p->dismissal));
            })->count();

            $inningsBowled = $performances->filter(function ($p) {
                return !empty($p->overs_bowled) && $p->overs_bowled !== '0.0' && $p->overs_bowled !== '0';
            })->count();
            $totalWickets = (int) $performances->sum('wickets_taken');
            $totalRunsConceded = (int) $performances->sum('runs_conceded');
            $totalMaidens = (int) $performances->sum('maidens');
            $totalLegalBalls = 0;
            foreach ($performances as $perf) {
                if (!empty($perf->overs_bowled)) {
                    $parts = explode('.', (string) $perf->overs_bowled);
                    $ov = (int) ($parts[0] ?? 0);
                    $bl = (int) ($parts[1] ?? 0);
                    $totalLegalBalls += ($ov * 6) + $bl;
                }
            }
            $totalOversFormatted = floor($totalLegalBalls / 6) . '.' . ($totalLegalBalls % 6);
            $economyRate = $totalLegalBalls > 0 ? round(($totalRunsConceded / $totalLegalBalls) * 6, 2) : 0.0;
            $bowlingAvg = $totalWickets > 0 ? round($totalRunsConceded / $totalWickets, 2) : 0.0;
            $bestBowlingPerf = $performances->sortByDesc('wickets_taken')->sortBy('runs_conceded')->first();
            $bestBowling = ($bestBowlingPerf && $bestBowlingPerf->wickets_taken > 0)
                ? "{$bestBowlingPerf->wickets_taken}/{$bestBowlingPerf->runs_conceded}"
                : '0/0';

            $recentMatches = $performances->sortByDesc('created_at')->take(5)->map(function ($p) {
                $m = $p->match;
                return [
                    'match_id' => $p->match_id,
                    'title' => $m ? ($m->team1_name . ' vs ' . $m->team2_name) : 'Match #' . $p->match_id,
                    'team' => $p->team_name,
                    'runs' => $p->runs_scored,
                    'balls' => $p->balls_faced,
                    'dismissal' => $p->dismissal ?: 'not out',
                    'overs' => $p->formatted_overs,
                    'wickets' => $p->wickets_taken,
                    'runs_conceded' => $p->runs_conceded,
                    'date' => $m && $m->match_date ? \Carbon\Carbon::parse($m->match_date)->format('d M Y') : ($p->created_at ? $p->created_at->format('d M Y') : 'Recent'),
                ];
            })->values();
        } else {
            // Fallback from User model passport or defaults
            $matchesCount = $user ? (int) $user->matches_played : 0;
            $totalRuns = $user ? (int) $user->total_runs : 0;
            $highestStr = $user && $user->highest_score ? (string) $user->highest_score : '0';
            $battingAvg = $user && $user->batting_avg ? (float) $user->batting_avg : ($matchesCount > 0 ? round($totalRuns / $matchesCount, 2) : 0.0);
            $strikeRate = $user && $user->strike_rate ? (float) $user->strike_rate : 120.0;
            $fifties = $user ? (int) $user->fifties : 0;
            $hundreds = $user ? (int) $user->hundreds : 0;
            $notOuts = 0;
            $inningsBatted = $matchesCount;
            $totalBalls = $totalRuns > 0 ? round(($totalRuns / ($strikeRate ?: 120)) * 100) : 0;
            $totalFours = round($totalRuns * 0.10);
            $totalSixes = round($totalRuns * 0.04);

            $inningsBowled = $matchesCount;
            $totalWickets = $user ? (int) $user->wickets_taken : 0;
            $bestBowling = $user && $user->best_bowling ? (string) $user->best_bowling : '0/0';
            $economyRate = $user && $user->economy_rate ? (float) $user->economy_rate : 7.2;
            $totalOversFormatted = ($matchesCount * 2) . '.0';
            $totalRunsConceded = round($totalWickets * 16);
            $totalMaidens = 0;
            $bowlingAvg = $totalWickets > 0 ? round($totalRunsConceded / $totalWickets, 2) : 0.0;

            $recentMatches = collect([]);
        }

        $initials = urlencode($playerName ?: 'Cricket Player');
        $defaultAvatar = "https://ui-avatars.com/api/?name={$initials}&background=0284c7&color=ffffff&bold=true&size=200";

        return [
            'status' => 'success',
            'player' => [
                'id' => $user?->id ?? 0,
                'name' => $playerName,
                'role' => $playerRole ?: ($user?->player_role ?? 'All-Rounder'),
                'team' => $teamName,
                'phone' => $playerPhone ?? $user?->phone,
                'is_captain' => (bool) $isCaptain,
                'is_wicketkeeper' => str_contains(strtolower($playerRole ?? ''), 'keeper') || str_contains(strtolower($playerRole ?? ''), 'wk'),
                'user_id' => $user?->id,
                'profile_url' => $user ? route('profile.user', $user->id) : null,
                'avatar_url' => $user ? $user->avatar_url : $defaultAvatar,
                'city' => $user?->city ?? 'Local Cricket Arena',
                'bio' => $user?->bio ?? 'Cricket Player on LiveCricket',
            ],
            'batting' => [
                'matches' => max($matchesCount, 1),
                'innings' => $inningsBatted,
                'runs' => $totalRuns,
                'balls' => $totalBalls,
                'highest' => $highestStr,
                'average' => $battingAvg,
                'strike_rate' => $strikeRate,
                'fours' => $totalFours,
                'sixes' => $totalSixes,
                'fifties' => $fifties,
                'hundreds' => $hundreds,
                'not_outs' => $notOuts,
            ],
            'bowling' => [
                'innings' => $inningsBowled,
                'overs' => $totalOversFormatted,
                'wickets' => $totalWickets,
                'runs_conceded' => $totalRunsConceded,
                'maidens' => $totalMaidens,
                'economy' => $economyRate,
                'average' => $bowlingAvg,
                'best' => $bestBowling,
            ],
            'recent_matches' => $recentMatches,
        ];
    }
}
