<?php

namespace App\Http\Controllers;

use App\Models\Ground;
use App\Models\GroundBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GroundController extends Controller
{
    /**
     * Display a listing of cricket grounds.
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?: Auth::user();

        $query = Ground::with(['user', 'bookings']);

        // Search Keyword
        $search = trim((string) $request->get('search', ''));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // City Filter
        $city = trim((string) $request->get('city', ''));
        if (!empty($city) && $city !== 'all') {
            $query->where('city', $city);
        }

        // Pitch Type Filter
        $pitchType = trim((string) $request->get('pitch_type', ''));
        if (!empty($pitchType) && $pitchType !== 'all') {
            $query->where('pitch_type', $pitchType);
        }

        // Price Filter
        $maxPrice = $request->get('max_price');
        if (!empty($maxPrice) && is_numeric($maxPrice)) {
            $query->where('price_per_slot', '<=', (float) $maxPrice);
        }

        // Amenities Filter
        $amenity = trim((string) $request->get('amenity', ''));
        if (!empty($amenity) && $amenity !== 'all') {
            $query->whereJsonContains('amenities', $amenity);
        }

        // View Tab Filter ('all', 'my_grounds', 'my_bookings')
        $tab = $request->get('tab', 'all');
        if ($tab === 'my_grounds' && $user) {
            $query->where('user_id', $user->id);
        }

        $grounds = $query->orderByDesc('created_at')->paginate(9)->withQueryString();

        // Unique cities for filter dropdown
        $cities = Ground::select('city')->distinct()->pluck('city')->filter()->values();

        // My Bookings (if logged in)
        $myBookings = collect();
        $incomingRequests = collect();
        if ($user) {
            $myBookings = GroundBooking::with('ground')
                ->where('user_id', $user->id)
                ->orderByDesc('booking_date')
                ->get();

            $myGroundIds = Ground::where('user_id', $user->id)->pluck('id');
            $incomingRequests = GroundBooking::with(['ground', 'user'])
                ->whereIn('ground_id', $myGroundIds)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('grounds.index', compact(
            'grounds',
            'cities',
            'search',
            'city',
            'pitchType',
            'maxPrice',
            'amenity',
            'tab',
            'myBookings',
            'incomingRequests'
        ));
    }

    /**
     * Show form to register a new ground.
     */
    public function create(): View
    {
        return view('grounds.create');
    }

    /**
     * Store newly registered ground.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'address' => 'required|string|max:500',
            'pitch_type' => 'required|string|max:100',
            'price_per_slot' => 'required|numeric|min:0',
            'slot_duration' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'amenities' => 'nullable|array',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'nullable|file|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'photos_base64' => 'nullable|array|max:5',
            'image_url' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:2000',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['amenities'] = $validated['amenities'] ?? [];
        $validated['whatsapp'] = $validated['whatsapp'] ?: $validated['phone'];

        $images = [];

        // 1. Process multiple uploaded files (up to 5)
        if ($request->hasFile('photos')) {
            $uploadDir = public_path('uploads/grounds');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            foreach ($request->file('photos') as $idx => $photoFile) {
                if ($idx >= 5) break;
                if ($photoFile && $photoFile->isValid()) {
                    $ext = $photoFile->getClientOriginalExtension() ?: 'jpg';
                    $filename = 'ground_' . Auth::id() . '_' . time() . '_' . $idx . '.' . $ext;
                    $photoFile->move($uploadDir, $filename);
                    $images[] = asset('uploads/grounds/' . $filename);
                }
            }
        }

        // 2. Process base64 photos (e.g. from drag & drop or client preview)
        if (!empty($request->input('photos_base64')) && is_array($request->input('photos_base64'))) {
            foreach ($request->input('photos_base64') as $b64) {
                if (count($images) >= 5) break;
                if (!empty($b64) && str_starts_with($b64, 'data:image/')) {
                    $images[] = $b64;
                }
            }
        }

        // 3. Fallback to image_url if provided
        if (!empty($validated['image_url']) && count($images) < 5) {
            if (!in_array($validated['image_url'], $images)) {
                $images[] = $validated['image_url'];
            }
        }

        $validated['images'] = $images;
        $validated['image_url'] = $images[0] ?? ($validated['image_url'] ?? null);

        $ground = Ground::create($validated);

        return redirect()->route('grounds.show', $ground)->with('status', 'Cricket Ground registered successfully! 🏟️ Teams can now book slots.');
    }

    /**
     * Show ground details and booking form.
     */
    public function show(Ground $ground): View
    {
        $ground->load(['user', 'bookings' => fn($q) => $q->where('booking_date', '>=', now()->toDateString())]);
        $isOwner = Auth::check() && Auth::id() === $ground->user_id;

        return view('grounds.show', compact('ground', 'isOwner'));
    }

    /**
     * Book a ground slot.
     */
    public function book(Request $request, Ground $ground): RedirectResponse
    {
        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'booking_date' => 'required|date|after_or_equal:today',
            'time_slot' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if slot already confirmed for this ground on this date
        $exists = GroundBooking::where('ground_id', $ground->id)
            ->where('booking_date', $validated['booking_date'])
            ->where('time_slot', $validated['time_slot'])
            ->where('status', 'confirmed')
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'This time slot is already booked! Please select another slot.');
        }

        $booking = GroundBooking::create([
            'ground_id' => $ground->id,
            'user_id' => Auth::id(),
            'team_name' => $validated['team_name'],
            'booking_date' => $validated['booking_date'],
            'time_slot' => $validated['time_slot'],
            'total_price' => $ground->price_per_slot,
            'contact_phone' => $validated['contact_phone'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('grounds.index', ['tab' => 'my_bookings'])
            ->with('status', "Booking request submitted for {$ground->name}! Ground owner will confirm shortly. 🏏");
    }

    /**
     * Update booking status (owner confirms/rejects).
     */
    public function updateBookingStatus(Request $request, GroundBooking $booking): RedirectResponse
    {
        $userId = Auth::id();
        $isOwner = ($booking->ground->user_id === $userId);
        $isBooker = ($booking->user_id === $userId);

        if (!$isOwner && !$isBooker) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        // Booker can only cancel
        if ($isBooker && !$isOwner && $validated['status'] !== 'cancelled') {
            abort(403, 'Unauthorized');
        }

        $booking->update(['status' => $validated['status']]);

        return back()->with('status', "Booking status updated to {$validated['status']}!");
    }
}
