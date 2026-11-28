<?php

namespace App\Http\Controllers;

use App\Models\SocialConnection;
use App\Services\SocialPublishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    private function authorizeAdmin(): void
    {
        if (!Auth::check() || !(Auth::user()->is_admin ?? false)) {
            abort(403, 'Unauthorized. Only Admins can connect social channels.');
        }
    }

    private function rememberReturnUrl(Request $request): void
    {
        $returnTo = trim((string) $request->query('return_to', ''));
        if ($returnTo !== '' && (Str::startsWith($returnTo, url('/')) || (Str::startsWith($returnTo, '/') && !Str::startsWith($returnTo, '//')))) {
            session(['social_oauth_return_to' => $returnTo]);
        }
    }

    private function redirectToStudio(?string $statusType = null, ?string $message = null): RedirectResponse
    {
        $target = session()->pull('social_oauth_return_to', route('reels.index'));
        $redirect = redirect()->to($target);
        if ($statusType && $message) {
            $redirect->with($statusType, $message);
        }
        return $redirect;
    }

    public function connectInstagram(Request $request, SocialPublishService $social): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->rememberReturnUrl($request);

        if (!$social->metaConfigured()) {
            return $this->redirectToStudio('social_error', 'META_APP_ID and META_APP_SECRET are not set in Render Environment yet.');
        }

        $state = Str::random(40);
        session(['meta_oauth_state' => $state]);

        $redirectUri = config('services.meta.redirect_uri') ?: route('social.instagram.callback');
        return redirect()->away($social->getInstagramAuthUrl($state, $redirectUri));
    }

    public function callbackInstagram(Request $request, SocialPublishService $social): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($request->filled('error')) {
            $msg = (string) ($request->query('error_description') ?: $request->query('error_reason') ?: 'Instagram connection cancelled.');
            return $this->redirectToStudio('social_error', $msg);
        }

        $expectedState = (string) session()->pull('meta_oauth_state', '');
        $actualState = (string) $request->query('state', '');
        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            return $this->redirectToStudio('social_error', 'Invalid Meta OAuth state. Please click Connect Instagram again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->redirectToStudio('social_error', 'Meta did not return an authorization code.');
        }

        try {
            $redirectUri = config('services.meta.redirect_uri') ?: route('social.instagram.callback');
            $conn = $social->handleInstagramCallback($code, $redirectUri, Auth::id());
            return $this->redirectToStudio(
                'social_success',
                "Instagram connected: {$conn->account_name}" . ($conn->page_name ? " (via {$conn->page_name})" : '') . '!'
            );
        } catch (\Throwable $e) {
            return $this->redirectToStudio('social_error', $e->getMessage());
        }
    }

    public function saveInstagramManualToken(Request $request, SocialPublishService $social): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->rememberReturnUrl($request);

        $validated = $request->validate([
            'access_token' => 'required|string|min:20|max:2000',
        ]);

        try {
            $conn = $social->saveInstagramConnectionFromUserToken(trim($validated['access_token']), 5184000, Auth::id());
            return $this->redirectToStudio(
                'social_success',
                "Instagram connected via token: {$conn->account_name}" . ($conn->page_name ? " ({$conn->page_name})" : '') . '!'
            );
        } catch (\Throwable $e) {
            return $this->redirectToStudio('social_error', $e->getMessage());
        }
    }

    public function disconnectInstagram(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->rememberReturnUrl($request);

        SocialConnection::where('provider', 'instagram')->delete();
        return $this->redirectToStudio('social_success', 'Instagram account disconnected.');
    }

    public function connectYouTube(Request $request, SocialPublishService $social): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->rememberReturnUrl($request);

        if (!$social->youtubeConfigured()) {
            return $this->redirectToStudio('social_error', 'YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET are not set in Render Environment yet.');
        }

        $state = Str::random(40);
        session(['youtube_oauth_state' => $state]);

        $redirectUri = config('services.youtube.redirect_uri') ?: route('social.youtube.callback');
        return redirect()->away($social->getYouTubeAuthUrl($state, $redirectUri));
    }

    public function callbackYouTube(Request $request, SocialPublishService $social): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($request->filled('error')) {
            return $this->redirectToStudio('social_error', 'YouTube connection cancelled: ' . (string) $request->query('error'));
        }

        $expectedState = (string) session()->pull('youtube_oauth_state', '');
        $actualState = (string) $request->query('state', '');
        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            return $this->redirectToStudio('social_error', 'Invalid Google OAuth state. Please click Connect YouTube again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->redirectToStudio('social_error', 'Google did not return an authorization code.');
        }

        try {
            $redirectUri = config('services.youtube.redirect_uri') ?: route('social.youtube.callback');
            $conn = $social->handleYouTubeCallback($code, $redirectUri, Auth::id());
            return $this->redirectToStudio('social_success', "YouTube Channel connected: {$conn->account_name}!");
        } catch (\Throwable $e) {
            return $this->redirectToStudio('social_error', $e->getMessage());
        }
    }

    public function disconnectYouTube(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->rememberReturnUrl($request);

        SocialConnection::where('provider', 'youtube')->delete();
        return $this->redirectToStudio('social_success', 'YouTube channel disconnected.');
    }
}
