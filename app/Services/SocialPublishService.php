<?php

namespace App\Services;

use App\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialPublishService
{
    public function __construct(protected ImageKitService $imageKit)
    {
    }

    protected function ipv4Options(): array
    {
        return defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')
            ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
            : [];
    }

    public function metaConfigured(): bool
    {
        return !empty(config('services.meta.app_id')) && !empty(config('services.meta.app_secret'));
    }

    public function youtubeConfigured(): bool
    {
        return !empty(config('services.youtube.client_id')) && !empty(config('services.youtube.client_secret'));
    }

    public function getStatusSummary(): array
    {
        $igConn = SocialConnection::activeFor('instagram');
        $ytConn = SocialConnection::activeFor('youtube');

        $envIgId = trim((string) config('services.meta.instagram_account_id'));
        $envIgTok = trim((string) config('services.meta.instagram_access_token'));
        $hasEnvIg = $envIgId !== '' && $envIgTok !== '';

        return [
            'instagram' => [
                'configured' => $this->metaConfigured() || $hasEnvIg,
                'oauth_ready' => $this->metaConfigured(),
                'connected' => ($igConn !== null) || $hasEnvIg,
                'account_id' => $igConn?->account_id ?: ($hasEnvIg ? $envIgId : null),
                'account_name' => $igConn?->account_name ?: ($hasEnvIg ? '@cricapp_official (ENV)' : null),
                'page_name' => $igConn?->page_name,
                'expires_at' => $igConn?->token_expires_at?->toDateTimeString(),
            ],
            'youtube' => [
                'configured' => $this->youtubeConfigured(),
                'oauth_ready' => $this->youtubeConfigured(),
                'connected' => $ytConn !== null,
                'account_id' => $ytConn?->account_id,
                'account_name' => $ytConn?->account_name,
                'expires_at' => $ytConn?->token_expires_at?->toDateTimeString(),
            ],
        ];
    }

    /**
     * Build Meta Facebook/Instagram Login URL for connecting @cricapp_official.
     */
    public function getInstagramAuthUrl(string $state, string $redirectUri): string
    {
        $appId = (string) config('services.meta.app_id');
        $version = (string) config('services.meta.graph_version', 'v22.0');
        if ($appId === '') {
            throw new \RuntimeException('META_APP_ID is not configured yet.');
        }

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement',
        ]);

        return "https://www.facebook.com/{$version}/dialog/oauth?{$query}";
    }

    /**
     * Exchange Meta OAuth code for long-lived token & auto-discover linked Instagram Professional account.
     */
    public function handleInstagramCallback(string $code, string $redirectUri, ?int $userId = null): SocialConnection
    {
        $appId = (string) config('services.meta.app_id');
        $appSecret = (string) config('services.meta.app_secret');
        $version = (string) config('services.meta.graph_version', 'v22.0');

        if ($appId === '' || $appSecret === '') {
            throw new \RuntimeException('META_APP_ID and META_APP_SECRET must be set.');
        }

        // 1. Exchange authorization code for short-lived token
        $tokenResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/oauth/access_token", [
                'client_id' => $appId,
                'redirect_uri' => $redirectUri,
                'client_secret' => $appSecret,
                'code' => $code,
            ]);

        $tokenData = $tokenResp->json() ?? [];
        if (!$tokenResp->successful() || empty($tokenData['access_token'])) {
            $errMsg = $tokenData['error']['message'] ?? $tokenResp->body();
            throw new \RuntimeException('Meta OAuth token exchange failed: ' . $errMsg);
        }

        // 2. Upgrade to long-lived 60-day token
        $longToken = (string) $tokenData['access_token'];
        $expiresIn = (int) ($tokenData['expires_in'] ?? 5184000);

        $llResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $longToken,
            ]);

        $llData = $llResp->json() ?? [];
        if ($llResp->successful() && !empty($llData['access_token'])) {
            $longToken = (string) $llData['access_token'];
            $expiresIn = (int) ($llData['expires_in'] ?? $expiresIn);
        }

        return $this->saveInstagramConnectionFromUserToken($longToken, $expiresIn, $userId);
    }

    /**
     * Save Instagram connection using either an OAuth long-lived token or a pasted Graph token.
     */
    public function saveInstagramConnectionFromUserToken(string $accessToken, int $expiresIn = 5184000, ?int $userId = null): SocialConnection
    {
        $version = (string) config('services.meta.graph_version', 'v22.0');

        $pagesResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/me/accounts", [
                'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url}',
                'access_token' => $accessToken,
            ]);

        $pagesJson = $pagesResp->json() ?? [];
        if (!$pagesResp->successful()) {
            $errMsg = $pagesJson['error']['message'] ?? $pagesResp->body();
            throw new \RuntimeException('Could not list Facebook Pages: ' . $errMsg);
        }

        $matchedPage = null;
        $matchedIg = null;
        foreach ($pagesJson['data'] ?? [] as $page) {
            if (!empty($page['instagram_business_account']['id'])) {
                $matchedPage = $page;
                $matchedIg = $page['instagram_business_account'];
                break;
            }
        }

        if (!$matchedIg) {
            throw new \RuntimeException(
                'No connected Instagram Business/Creator account was found on your selected Facebook Pages. '
                . 'During Facebook Login, allow your Cricappofficial Page and @cricapp_official Instagram account.'
            );
        }

        $username = !empty($matchedIg['username'])
            ? '@' . ltrim((string) $matchedIg['username'], '@')
            : ($matchedIg['name'] ?? 'Instagram Account');

        return SocialConnection::updateOrCreate(
            ['provider' => 'instagram', 'account_id' => (string) $matchedIg['id']],
            [
                'user_id' => $userId,
                'account_name' => $username,
                'page_id' => (string) ($matchedPage['id'] ?? ''),
                'page_name' => (string) ($matchedPage['name'] ?? ''),
                'access_token' => $accessToken,
                'token_expires_at' => now()->addSeconds(max(3600, $expiresIn)),
                'meta' => [
                    'page_access_token' => $matchedPage['access_token'] ?? null,
                    'profile_picture_url' => $matchedIg['profile_picture_url'] ?? null,
                ],
                'is_active' => true,
            ]
        );
    }

    /**
     * Build Google OAuth URL for connecting a YouTube Channel (with offline refresh token).
     */
    public function getYouTubeAuthUrl(string $state, string $redirectUri): string
    {
        $clientId = (string) config('services.youtube.client_id');
        if ($clientId === '') {
            throw new \RuntimeException('YOUTUBE_CLIENT_ID is not configured yet.');
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$query}";
    }

    /**
     * Exchange Google OAuth code for YouTube access + refresh tokens & fetch channel info.
     */
    public function handleYouTubeCallback(string $code, string $redirectUri, ?int $userId = null): SocialConnection
    {
        $clientId = (string) config('services.youtube.client_id');
        $clientSecret = (string) config('services.youtube.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET must be set.');
        }

        $tokenResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        $tokenData = $tokenResp->json() ?? [];
        if (!$tokenResp->successful() || empty($tokenData['access_token'])) {
            $err = $tokenData['error_description'] ?? ($tokenData['error'] ?? $tokenResp->body());
            throw new \RuntimeException('Google YouTube OAuth failed: ' . $err);
        }

        $accessToken = (string) $tokenData['access_token'];
        $refreshToken = (string) ($tokenData['refresh_token'] ?? '');
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        $chanResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->withToken($accessToken)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ]);

        $chanData = $chanResp->json() ?? [];
        $channel = $chanData['items'][0] ?? null;
        if (!$chanResp->successful() || !$channel) {
            throw new \RuntimeException(
                'No YouTube Channel found on this Google account. Create a YouTube channel first and try connecting again.'
            );
        }

        $channelId = (string) $channel['id'];
        $channelTitle = (string) ($channel['snippet']['title'] ?? 'YouTube Channel');

        $existing = SocialConnection::where('provider', 'youtube')
            ->where('account_id', $channelId)
            ->first();

        return SocialConnection::updateOrCreate(
            ['provider' => 'youtube', 'account_id' => $channelId],
            [
                'user_id' => $userId,
                'account_name' => $channelTitle,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken !== '' ? $refreshToken : ($existing?->refresh_token),
                'token_expires_at' => now()->addSeconds(max(300, $expiresIn)),
                'meta' => [
                    'thumbnail' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                ],
                'is_active' => true,
            ]
        );
    }

    /**
     * Refresh expired Google OAuth access token automatically using stored refresh_token.
     */
    protected function getFreshYouTubeToken(SocialConnection $conn): string
    {
        if ($conn->token_expires_at && now()->addMinutes(2)->lessThan($conn->token_expires_at)) {
            return (string) $conn->access_token;
        }

        if (empty($conn->refresh_token)) {
            return (string) $conn->access_token;
        }

        $clientId = (string) config('services.youtube.client_id');
        $clientSecret = (string) config('services.youtube.client_secret');

        $resp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => (string) $conn->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        $data = $resp->json() ?? [];
        if ($resp->successful() && !empty($data['access_token'])) {
            $conn->access_token = (string) $data['access_token'];
            $conn->token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
            $conn->save();
            return (string) $conn->access_token;
        }

        $err = $data['error_description'] ?? ($data['error'] ?? 'Token refresh failed');
        throw new \RuntimeException('YouTube token expired (' . $err . '). Please reconnect YouTube.');
    }

    /**
     * Publish an MP4 video directly to Instagram Reels via Meta Graph API (through ImageKit public MP4 URL).
     */
    public function publishInstagramReel(string $mp4Path, string $caption): array
    {
        $conn = SocialConnection::activeFor('instagram');
        $igUserId = $conn?->account_id ?: trim((string) config('services.meta.instagram_account_id'));
        $accessToken = $conn?->access_token ?: trim((string) config('services.meta.instagram_access_token'));

        if ($igUserId === '' || $accessToken === '') {
            throw new \RuntimeException('Instagram is not connected yet. Connect @cricapp_official first.');
        }

        $videoUrl = $this->imageKit->upload($mp4Path, '/cricapp/reels', 'cricapp_reel_' . time() . '.mp4');
        if (!$videoUrl) {
            throw new \RuntimeException('Failed to upload MP4 to ImageKit CDN for Instagram ingestion.');
        }

        $version = (string) config('services.meta.graph_version', 'v22.0');
        $cleanCaption = trim(Str::limit($caption, 2150, ''));

        // 1. Create Reels media container
        $createResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(30)
            ->asForm()
            ->post("https://graph.facebook.com/{$version}/{$igUserId}/media", [
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => $cleanCaption,
                'share_to_feed' => 'true',
                'access_token' => $accessToken,
            ]);

        $createJson = $createResp->json() ?? [];
        if (!$createResp->successful() || empty($createJson['id'])) {
            $err = $createJson['error']['message'] ?? $createResp->body();
            throw new \RuntimeException('Instagram media creation failed: ' . $err);
        }

        $creationId = (string) $createJson['id'];

        // 2. Poll container status until Instagram finishes fetching & encoding the MP4
        $statusCode = 'IN_PROGRESS';
        $lastStatusDetail = '';
        for ($attempt = 0; $attempt < 24; $attempt++) {
            sleep(3);
            $statusResp = Http::withOptions($this->ipv4Options())
                ->connectTimeout(5)
                ->timeout(15)
                ->get("https://graph.facebook.com/{$version}/{$creationId}", [
                    'fields' => 'status_code,status',
                    'access_token' => $accessToken,
                ]);

            $statusJson = $statusResp->json() ?? [];
            $statusCode = strtoupper((string) ($statusJson['status_code'] ?? ''));
            $lastStatusDetail = (string) ($statusJson['status'] ?? '');

            if ($statusCode === 'FINISHED' || $statusCode === 'PUBLISHED') {
                break;
            }
            if (in_array($statusCode, ['ERROR', 'EXPIRED'], true)) {
                throw new \RuntimeException('Instagram video processing failed: ' . ($lastStatusDetail ?: $statusCode));
            }
        }

        if ($statusCode !== 'FINISHED' && $statusCode !== 'PUBLISHED') {
            throw new \RuntimeException('Instagram is still processing the video (container ' . $creationId . '). Wait a minute or retry.');
        }

        // 3. Publish the Reels container
        $pubResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(30)
            ->asForm()
            ->post("https://graph.facebook.com/{$version}/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

        $pubJson = $pubResp->json() ?? [];
        if (!$pubResp->successful() || empty($pubJson['id'])) {
            $err = $pubJson['error']['message'] ?? $pubResp->body();
            throw new \RuntimeException('Instagram publish failed: ' . $err);
        }

        $mediaId = (string) $pubJson['id'];
        $permalink = 'https://www.instagram.com/' . ltrim((string) ($conn?->account_name ?: 'cricapp_official'), '@') . '/reels/';

        try {
            $linkResp = Http::withOptions($this->ipv4Options())
                ->connectTimeout(3)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$version}/{$mediaId}", [
                    'fields' => 'permalink',
                    'access_token' => $accessToken,
                ]);
            if ($linkResp->successful() && !empty($linkResp->json('permalink'))) {
                $permalink = (string) $linkResp->json('permalink');
            }
        } catch (\Throwable $e) {
            // Optional permalink lookup failure should not fail successful publish
        }

        return [
            'status' => 'published',
            'id' => $mediaId,
            'url' => $permalink,
            'video_cdn_url' => $videoUrl,
        ];
    }

    /**
     * Upload an MP4 video directly to YouTube Shorts via YouTube Data API v3 Resumable Upload.
     */
    public function publishYouTubeShort(
        string $mp4Path,
        string $title,
        string $description,
        array $tags = [],
        ?string $privacy = null
    ): array {
        $conn = SocialConnection::activeFor('youtube');
        if (!$conn) {
            throw new \RuntimeException('YouTube is not connected yet. Click Connect YouTube first.');
        }

        $accessToken = $this->getFreshYouTubeToken($conn);
        $fileSize = filesize($mp4Path);
        if (!$fileSize || $fileSize < 1024) {
            throw new \RuntimeException('Invalid MP4 file for YouTube upload.');
        }

        $cleanTitle = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? '');
        if ($cleanTitle === '') {
            $cleanTitle = 'CricApp Live Cricket Update';
        }
        if (!str_contains(strtolower($cleanTitle), '#shorts')) {
            $suffix = ' #Shorts';
            $cleanTitle = Str::limit($cleanTitle, 100 - strlen($suffix), '') . $suffix;
        } else {
            $cleanTitle = Str::limit($cleanTitle, 100, '');
        }

        $cleanDesc = trim($description);
        if (!str_contains(strtolower($cleanDesc), '#shorts')) {
            $cleanDesc .= ($cleanDesc !== '' ? "\n\n" : '') . '#Shorts #Cricket #CricApp';
        }

        $safeTags = array_values(array_unique(array_filter(array_map(
            static fn ($t) => trim(preg_replace('/[#<>]/', '', (string) $t)),
            array_merge($tags, ['Cricket', 'Shorts', 'CricApp', 'CricketNews', 'IndianCricket'])
        ))));
        $safeTags = array_slice($safeTags, 0, 12);

        $privacyStatus = in_array($privacy, ['public', 'unlisted', 'private'], true)
            ? $privacy
            : (string) config('services.youtube.default_privacy', 'public');

        // 1. Initiate YouTube Resumable Upload session
        $initResp = Http::withOptions($this->ipv4Options())
            ->connectTimeout(5)
            ->timeout(20)
            ->withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $fileSize,
                'X-Upload-Content-Type' => 'video/mp4',
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => [
                    'title' => $cleanTitle,
                    'description' => Str::limit($cleanDesc, 4900, ''),
                    'tags' => $safeTags,
                    'categoryId' => '17',
                ],
                'status' => [
                    'privacyStatus' => $privacyStatus,
                    'selfDeclaredMadeForKids' => false,
                ],
            ]);

        if (!$initResp->successful()) {
            $err = $initResp->json('error.message') ?? $initResp->body();
            throw new \RuntimeException('YouTube upload init failed: ' . $err);
        }

        $uploadUrl = (string) ($initResp->header('Location') ?: '');
        if ($uploadUrl === '') {
            throw new \RuntimeException('YouTube did not return a resumable upload Location URL.');
        }

        // 2. Stream MP4 file bytes to YouTube upload URL
        $stream = fopen($mp4Path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read MP4 file for YouTube upload.');
        }

        try {
            $uploadResp = Http::withOptions($this->ipv4Options())
                ->connectTimeout(5)
                ->timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'video/mp4',
                    'Content-Length' => (string) $fileSize,
                ])
                ->send('PUT', $uploadUrl, ['body' => $stream]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $uploadJson = $uploadResp->json() ?? [];
        if (!$uploadResp->successful() || empty($uploadJson['id'])) {
            $err = $uploadJson['error']['message'] ?? $uploadResp->body();
            Log::warning('YouTube upload failure', ['status' => $uploadResp->status(), 'body' => $uploadJson]);
            throw new \RuntimeException('YouTube video upload failed: ' . $err);
        }

        $videoId = (string) $uploadJson['id'];
        $actualPrivacy = (string) ($uploadJson['status']['privacyStatus'] ?? $privacyStatus);

        return [
            'status' => 'published',
            'id' => $videoId,
            'privacy' => $actualPrivacy,
            'url' => "https://www.youtube.com/shorts/{$videoId}",
        ];
    }
}
