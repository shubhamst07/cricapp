<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ReelScriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ReelStudioController extends Controller
{
    private function authorizeAdmin(): void
    {
        if (!Auth::check() || !(Auth::user()->is_admin ?? false)) {
            abort(403, 'Unauthorized. Only Admins can access Reel Studio.');
        }
    }

    /**
     * Standalone CricApp Reel Studio page.
     */
    public function index(Request $request, ReelScriptService $scriptService)
    {
        $this->authorizeAdmin();

        $selectedArticle = null;
        if ($request->filled('article_id')) {
            $selectedArticle = Article::find($request->integer('article_id'));
        }

        $project = $selectedArticle
            ? $scriptService->buildFromArticle($selectedArticle)
            : $scriptService->buildDefaultBlank();

        $recentArticles = Article::latest('id')
            ->take(20)
            ->get(['id', 'title', 'slug', 'category', 'featured_image', 'status', 'published_at']);

        return view('reels.studio', [
            'project' => $project,
            'article' => $selectedArticle,
            'recentArticles' => $recentArticles,
            'curatedImages' => $scriptService->curatedImages(),
            'voiceOptions' => $scriptService->voiceOptions(),
        ]);
    }

    /**
     * 1-Click Article-to-Reel preloaded Studio page.
     */
    public function forArticle(Article $article, ReelScriptService $scriptService)
    {
        $this->authorizeAdmin();

        $recentArticles = Article::latest('id')
            ->take(20)
            ->get(['id', 'title', 'slug', 'category', 'featured_image', 'status', 'published_at']);

        return view('reels.studio', [
            'project' => $scriptService->buildFromArticle($article),
            'article' => $article,
            'recentArticles' => $recentArticles,
            'curatedImages' => $scriptService->curatedImages(),
            'voiceOptions' => $scriptService->voiceOptions(),
        ]);
    }

    /**
     * Generate free Hindi / Indian-English voiceover MP3 + WordBoundary timings via score-engine.
     */
    public function generateTts(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'text' => 'required|string|min:2|max:4000',
            'voice' => 'required|string|max:64',
            'rate' => 'nullable|string|max:16',
            'pitch' => 'nullable|string|max:16',
        ]);

        $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');

        try {
            $response = Http::timeout(25)->post($engineUrl . '/tts', [
                'text' => $validated['text'],
                'voice' => $validated['voice'],
                'rate' => $validated['rate'] ?? '+5%',
                'pitch' => $validated['pitch'] ?? '+0Hz',
            ]);

            $payload = $response->json();
            if ($response->failed() || !is_array($payload)) {
                return response()->json([
                    'status' => 'error',
                    'message' => is_array($payload) && isset($payload['message'])
                        ? $payload['message']
                        : 'Voice engine could not synthesize audio right now. You can record Mic or upload MP3 instead.',
                ], $response->status() >= 400 ? $response->status() : 502);
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI voice service is offline in this environment. Use Record Mic, Upload Voice, or Background Music.',
            ], 503);
        }
    }

    /**
     * Transcode recorded WebM/MP4 canvas stream to Instagram/YouTube compatible H.264/AAC MP4.
     */
    public function convertMp4(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'video' => 'required|file|max:61440',
            'max_duration' => 'nullable|integer|min:5|max:120',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $file = $request->file('video');
        $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to read uploaded video buffer.',
            ], 500);
        }

        try {
            $response = Http::timeout(90)
                ->attach('video', $stream, 'cricapp-reel.webm')
                ->post($engineUrl . '/convert-mp4', [
                    'max_duration' => (int) ($validated['max_duration'] ?? 90),
                ]);

            if ($response->failed()) {
                $err = $response->json();
                return response()->json([
                    'status' => 'error',
                    'message' => is_array($err) && isset($err['message'])
                        ? $err['message']
                        : 'MP4 converter unavailable; downloading direct browser recording instead.',
                ], $response->status() >= 400 ? $response->status() : 502);
            }

            return response($response->body(), 200, [
                'Content-Type' => 'video/mp4',
                'Content-Disposition' => 'attachment; filename="cricapp-reel-' . now()->format('Ymd-His') . '.mp4"',
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'MP4 converter is offline here; use direct browser download.',
            ], 503);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Same-origin SSRF-safe image proxy so external CDN images draw cleanly on <canvas> without tainting.
     */
    public function proxyImage(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $targetUrl = trim($validated['url']);

        for ($hop = 0; $hop < 2; $hop++) {
            if (!$this->isSafeExternalUrl($targetUrl)) {
                return response()->json(['message' => 'Image URL host is not allowed.'], 422);
            }

            try {
                $response = Http::withOptions(['allow_redirects' => false])
                    ->timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'CricApp-ReelStudio/1.0',
                        'Accept' => 'image/*',
                    ])
                    ->get($targetUrl);

                if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $location = $response->header('Location');
                    if (!$location) {
                        return response()->json(['message' => 'Invalid image redirect.'], 422);
                    }
                    if (Str::startsWith($location, '/')) {
                        $parts = parse_url($targetUrl);
                        $targetUrl = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $location;
                    } else {
                        $targetUrl = $location;
                    }
                    continue;
                }

                if ($response->failed()) {
                    return response()->json(['message' => 'Could not fetch remote image.'], 502);
                }

                $contentType = strtolower((string) $response->header('Content-Type'));
                $mime = trim(explode(';', $contentType)[0]);
                $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mime, $allowedMimes, true)) {
                    return response()->json(['message' => 'URL did not return a supported image format.'], 422);
                }

                $body = $response->body();
                if (strlen($body) > 10 * 1024 * 1024) {
                    return response()->json(['message' => 'Image exceeds 10 MB limit.'], 413);
                }

                return response($body, 200, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'public, max-age=3600',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Image fetch failed.'], 502);
            }
        }

        return response()->json(['message' => 'Too many redirects while loading image.'], 422);
    }

    private function isSafeExternalUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || Str::endsWith($host, ['.local', '.internal', '.localhost'])) {
            return false;
        }

        // If host is a literal IP, validate directly
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $ipv4s = @gethostbynamel($host);
        if (is_array($ipv4s) && !empty($ipv4s)) {
            foreach ($ipv4s as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return false;
                }
            }
            return true;
        }

        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa) && !empty($aaaa)) {
                foreach ($aaaa as $rec) {
                    $ip = $rec['ipv6'] ?? '';
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }
}
