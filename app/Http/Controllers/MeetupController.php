<?php

namespace App\Http\Controllers;

use App\Models\CricketCircle;
use App\Models\Ground;
use App\Models\MatchMeetup;
use App\Models\MeetupParticipant;
use Carbon\Carbon;
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

        $meetups = $query->paginate(12)->withQueryString();

        // Popular / Active Cities for Quick Filter Pills
        $popularCities = MatchMeetup::where('match_date', '>=', Carbon::today())
            ->select('city')
            ->distinct()
            ->pluck('city')
            ->filter()
            ->values();

        // Default cities if empty
        if ($popularCities->isEmpty()) {
            $popularCities = collect(['Shimla', 'Theog', 'Solan', 'Chandigarh', 'Delhi']);
        }

        // City Circles for local community
        $circlesQuery = CricketCircle::withCount('members')
            ->where('is_active', true);
        if (!empty($city) && strtolower($city) !== 'all') {
            $circlesQuery->where('city', 'LIKE', '%' . trim($city) . '%');
        }
        $circles = $circlesQuery->take(6)->get();

        return view('meetups.index', compact('meetups', 'popularCities', 'circles', 'city', 'when', 'ballType', 'search'));
    }

    /**
     * Show form to create a new match meetup / player request.
     */
    public function create(): View
    {
        $grounds = Ground::where('is_active', true)->get();
        $circles = CricketCircle::where('is_active', true)->get();

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
}
