<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display a user's Instagram-style profile.
     */
    public function show(Request $request, string|int|null $id = null): View|RedirectResponse
    {
        $viewer = Auth::user();

        if ($id === null) {
            if (!$viewer) {
                return redirect()->guest(route('login'));
            }
            $user = $viewer;
        } else {
            $user = User::where('id', $id)
                ->orWhere('username', $id)
                ->orWhere('username', ltrim($id, '@'))
                ->firstOrFail();
        }

        // Load stats
        $user->loadCount(['posts', 'followers', 'following']);

        // Load recent posts with comments
        $posts = $user->posts()
            ->with(['comments' => function ($q) {
                $q->latest()->take(5);
            }])
            ->latest()
            ->get();

        $isOwner = $viewer && ($viewer->id === $user->id);
        $isFollowing = $viewer && !$isOwner ? $viewer->isFollowing($user->id) : false;

        // Suggested cricket creators/fans to follow
        $suggestedUsers = User::where('id', '!=', $user->id)
            ->when($viewer, fn($q) => $q->where('id', '!=', $viewer->id))
            ->withCount(['followers', 'posts'])
            ->take(5)
            ->get();

        $matchPerformances = $user->matchPerformances()
            ->with(['match'])
            ->latest()
            ->take(10)
            ->get();

        return view('profile.show', [
            'user' => $user,
            'posts' => $posts,
            'matchPerformances' => $matchPerformances,
            'isOwner' => $isOwner,
            'isFollowing' => $isFollowing,
            'suggestedUsers' => $suggestedUsers,
        ]);
    }

    /**
     * Display the user's profile edit form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user() ?: Auth::user();
        if (!$user) {
            abort(401);
        }

        $validated = $request->validated();

        if ($request->filled('cropped_avatar')) {
            $validated['avatar'] = $request->input('cropped_avatar');
        } elseif ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $uploadDir = public_path('uploads/avatars');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $validated['avatar'] = 'uploads/avatars/' . $filename;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully!',
                'user' => $user,
            ]);
        }

        return Redirect::route('profile.show', ['id' => $user->id])->with('status', 'profile-updated');
    }

    /**
     * Update cropped avatar directly.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user() ?: Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $avatarData = $request->input('avatar') ?: $request->input('cropped_avatar');

        if ($avatarData && str_starts_with($avatarData, 'data:image/')) {
            $user->avatar = $avatarData;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile picture updated successfully! 📸',
                'avatar_url' => $user->avatar_url,
            ]);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $uploadDir = public_path('uploads/avatars');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $user->avatar = 'uploads/avatars/' . $filename;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile picture updated successfully! 📸',
                'avatar_url' => $user->avatar_url,
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'No image data received.'], 422);
    }

    /**
     * Toggle follow / unfollow a user.
     */
    public function toggleFollow(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please login to follow creators.'
            ], 401);
        }

        if ($currentUser->id === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot follow yourself.'
            ], 422);
        }

        $alreadyFollowing = $currentUser->isFollowing($user->id);

        if ($alreadyFollowing) {
            $currentUser->following()->detach($user->id);
            $isFollowing = false;
            $msg = "Unfollowed {$user->name}";
        } else {
            $currentUser->following()->attach($user->id);
            $isFollowing = true;
            $msg = "Now following {$user->name} 🎉";
        }

        $followersCount = $user->followers()->count();

        return response()->json([
            'status' => 'success',
            'following' => $isFollowing,
            'followers_count' => $followersCount,
            'message' => $msg,
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
