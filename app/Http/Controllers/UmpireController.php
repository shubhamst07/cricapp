<?php

namespace App\Http\Controllers;

use App\Models\UmpireBooking;
use App\Models\UmpireProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UmpireController extends Controller
{
    /**
     * Display a listing of cricket umpires.
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?: Auth::user();

        $query = UmpireProfile::with(['user', 'bookings']);

        // Search Keyword (Name, Phone, WhatsApp, City, Qualification, Bio)
        $search = trim((string) $request->get('search', ''));
        if (!empty($search)) {
            $cleanDigits = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanDigits) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('whatsapp', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('certification_level', 'LIKE', "%{$search}%")
                    ->orWhere('bio', 'LIKE', "%{$search}%");

                if (!empty($cleanDigits) && strlen($cleanDigits) >= 3) {
                    $q->orWhere('phone', 'LIKE', "%{$cleanDigits}%")
                      ->orWhere('whatsapp', 'LIKE', "%{$cleanDigits}%");
                }
            });
        }

        // City Filter
        $city = trim((string) $request->get('city', ''));
        if (!empty($city) && $city !== 'all') {
            $query->where('city', $city);
        }

        // Certification Level Filter
        $level = trim((string) $request->get('level', ''));
        if (!empty($level) && $level !== 'all') {
            $query->where('certification_level', $level);
        }

        // Ball Type Filter
        $ballType = trim((string) $request->get('ball_type', ''));
        if (!empty($ballType) && $ballType !== 'all') {
            $query->whereJsonContains('match_types', $ballType);
        }

        // Max Fee Filter
        $maxFee = $request->get('max_fee');
        if (!empty($maxFee) && is_numeric($maxFee)) {
            $query->where('fee_per_match', '<=', (float) $maxFee);
        }

        $isDefaultGuest = empty($search) && (empty($city) || $city === 'all') && (empty($level) || $level === 'all') && (empty($ballType) || $ballType === 'all') && empty($maxFee) && !$user;
        $page = (int) $request->get('page', 1);
        $defaultPaginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 9, $page, ['path' => $request->url()]);
        $defaultCities = collect();

        try {
            if ($isDefaultGuest) {
                $bundle = \Illuminate\Support\Facades\Cache::remember("umpires_index_page_{$page}", 60, function () use ($query) {
                    return [
                        'umpires' => $query->orderByDesc('rating')->orderByDesc('matches_umpired')->paginate(9),
                        'cities' => UmpireProfile::select('city')->distinct()->pluck('city')->filter()->values(),
                    ];
                });
                $umpires = $bundle['umpires'] ?? $defaultPaginator;
                $cities = $bundle['cities'] ?? $defaultCities;
            } else {
                $umpires = $query->orderByDesc('rating')->orderByDesc('matches_umpired')->paginate(9)->withQueryString();
                $cities = \Illuminate\Support\Facades\Cache::remember('umpires_cities_list', 300, function () {
                    return UmpireProfile::select('city')->distinct()->pluck('city')->filter()->values();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DB error in UmpireController index: ' . $e->getMessage());
            $umpires = $defaultPaginator;
            $cities = $defaultCities;
        }

        // User's own umpire profile if exists
        $myUmpireProfile = $user ? UmpireProfile::where('user_id', $user->id)->first() : null;

        // User's bookings (as organizer) & incoming requests (as umpire)
        $myBookings = collect();
        $incomingRequests = collect();
        if ($user) {
            $myBookings = UmpireBooking::with('umpireProfile')
                ->where('user_id', $user->id)
                ->orderByDesc('match_date')
                ->get();

            if ($myUmpireProfile) {
                $incomingRequests = UmpireBooking::with(['user'])
                    ->where('umpire_profile_id', $myUmpireProfile->id)
                    ->orderByDesc('created_at')
                    ->get();
            }
        }

        $tab = $request->get('tab', 'all');

        return view('umpires.index', compact(
            'umpires',
            'cities',
            'search',
            'city',
            'level',
            'ballType',
            'maxFee',
            'tab',
            'myUmpireProfile',
            'myBookings',
            'incomingRequests'
        ));
    }

    /**
     * Show form to register/edit umpire profile.
     */
    public function register(): View
    {
        $profile = UmpireProfile::where('user_id', Auth::id())->first();
        return view('umpires.register', compact('profile'));
    }

    /**
     * Store or update umpire profile.
     */
    public function storeProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'experience_years' => 'required|integer|min:0',
            'matches_umpired' => 'required|integer|min:0',
            'certification_level' => 'required|string|max:150',
            'match_types' => 'nullable|array',
            'fee_per_match' => 'required|numeric|min:0',
            'phone' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:2000',
            'photo' => 'nullable|file|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'photo_url' => 'nullable|string|max:500',
        ]);

        // Process uploaded profile photo
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $uploadDir = public_path('uploads/umpires');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $file = $request->file('photo');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'umpire_' . Auth::id() . '_' . time() . '.' . $ext;
            $file->move($uploadDir, $filename);
            $validated['photo_url'] = asset('uploads/umpires/' . $filename);
        }

        unset($validated['photo']);

        $validated['match_types'] = $validated['match_types'] ?? ['Tennis Ball', 'Leather Ball'];
        $validated['whatsapp'] = $validated['whatsapp'] ?: $validated['phone'];

        $profile = UmpireProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        return redirect()->route('umpires.show', $profile)
            ->with('status', 'Your Umpire Official Profile has been registered! ⚖️ Match organizers can now book you.');
    }

    /**
     * Show umpire profile details and booking form.
     */
    public function show(UmpireProfile $umpire): View
    {
        $umpire->load([
            'user',
            'bookings' => fn($q) => $q->where('match_date', '>=', now()->toDateString()),
            'matches' => fn($q) => $q->with('scores')->latest('match_date')->take(10),
        ]);
        $officiatedMatches = $umpire->matches;
        $isSelf = Auth::check() && Auth::id() === $umpire->user_id;

        return view('umpires.show', compact('umpire', 'isSelf', 'officiatedMatches'));
    }

    /**
     * Book an umpire for a match.
     */
    public function book(Request $request, UmpireProfile $umpire): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_name' => 'nullable|string|max:255',
            'match_date' => 'required|date|after_or_equal:today',
            'match_time' => 'required|string|max:50',
            'venue' => 'required|string|max:255',
            'teams' => 'required|string|max:255',
            'ball_type' => 'required|string|max:50',
            'contact_phone' => 'required|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Don't allow booking yourself
        if (Auth::id() === $umpire->user_id) {
            return back()->with('error', 'You cannot book your own umpire profile!');
        }

        $booking = UmpireBooking::create([
            'umpire_profile_id' => $umpire->id,
            'user_id' => Auth::id(),
            'tournament_name' => $validated['tournament_name'] ?? 'Friendly Match',
            'match_date' => $validated['match_date'],
            'match_time' => $validated['match_time'],
            'venue' => $validated['venue'],
            'teams' => $validated['teams'],
            'ball_type' => $validated['ball_type'],
            'total_fee' => $umpire->fee_per_match,
            'contact_phone' => $validated['contact_phone'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('umpires.index', ['tab' => 'my_bookings'])
            ->with('status', "Umpire booking request sent to {$umpire->name}! They will confirm shortly. ⚖️");
    }

    /**
     * Update booking status (umpire confirms or organizer cancels).
     */
    public function updateBookingStatus(Request $request, UmpireBooking $booking): RedirectResponse
    {
        $userId = Auth::id();
        $isUmpire = ($booking->umpireProfile->user_id === $userId);
        $isOrganizer = ($booking->user_id === $userId);

        if (!$isUmpire && !$isOrganizer) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
        ]);

        // Organizer can only cancel
        if ($isOrganizer && !$isUmpire && $validated['status'] !== 'cancelled') {
            abort(403, 'Unauthorized');
        }

        $booking->update(['status' => $validated['status']]);

        if ($validated['status'] === 'completed') {
            $booking->umpireProfile?->recalculateStats();
        }

        return back()->with('status', "Booking status updated to {$validated['status']}!");
    }
}
