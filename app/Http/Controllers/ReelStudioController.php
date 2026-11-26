<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ReelScriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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
     * Generate free Hindi / Indian-English voiceover MP3.
     * Fallback chain: score-engine HTTP -> in-container python tts_cli.py (edge-tts/gTTS) -> direct Google TTS HTTP.
     */
    public function generateTts(Request $request, ReelScriptService $scriptService)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'text' => 'required|string|min:2|max:4000',
            'voice' => 'required|string|max:64',
            'rate' => 'nullable|string|max:16',
            'pitch' => 'nullable|string|max:16',
            'auto_translate_hindi' => 'nullable|boolean',
        ]);

        $cleanText = preg_replace('/\s+/u', ' ', trim($validated['text']));
        $voice = trim($validated['voice']);
        $rate = trim((string) ($validated['rate'] ?? '+5%'));
        $pitch = trim((string) ($validated['pitch'] ?? '+0Hz'));
        $autoHindi = $request->has('auto_translate_hindi')
            ? $request->boolean('auto_translate_hindi')
            : true;

        $spokenText = $cleanText;
        $wasTranslated = false;

        // If user picked a Hindi voice (Madhur/Swara) with English text, auto-translate to Hindi script first
        if ($autoHindi && Str::startsWith($voice, 'hi-') && !$scriptService->isMostlyHindi($cleanText)) {
            $hindiText = $scriptService->translateToHindiFast($cleanText);
            if ($scriptService->isMostlyHindi($hindiText)) {
                $spokenText = $hindiText;
                $wasTranslated = true;
            }
        }

        // 1. Fast path: score-engine HTTP daemon if running on port 6020
        $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
        try {
            $response = Http::connectTimeout(2)->timeout(12)->post($engineUrl . '/tts', [
                'text' => $spokenText,
                'voice' => $voice,
                'rate' => $rate,
                'pitch' => $pitch,
            ]);
            $payload = $response->json();
            if ($response->ok() && is_array($payload) && !empty($payload['audio_base64'])) {
                $payload['spoken_text'] = $payload['spoken_text'] ?? $spokenText;
                $payload['translated'] = $wasTranslated || !empty($payload['translated']);
                return response()->json($payload);
            }
        } catch (\Throwable $e) {
            // Fall through silently to in-container CLI & direct HTTP TTS
        }

        // 2. Fallback A: Direct python3 tts_cli.py inside container (edge-tts + gTTS)
        $cliPayload = $this->runPythonTtsCli($spokenText, $voice, $rate, $pitch);
        if ($cliPayload !== null) {
            $cliPayload['spoken_text'] = $cliPayload['spoken_text'] ?? $spokenText;
            $cliPayload['translated'] = $wasTranslated || !empty($cliPayload['translated']);
            return response()->json($cliPayload);
        }

        // 3. Fallback B: Pure-PHP Google Translate TTS MP3 (works even if Python daemon/packages unavailable)
        $googlePayload = $this->fetchGoogleTranslateTts($spokenText, $voice);
        if ($googlePayload !== null) {
            $googlePayload['spoken_text'] = $spokenText;
            $googlePayload['translated'] = $wasTranslated;
            return response()->json($googlePayload);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Could not reach AI voice servers right now. Try Record Mic or Upload Voice MP3.',
        ], 503);
    }

    /**
     * Transcode recorded WebM/MP4 canvas stream to Instagram/YouTube compatible H.264/AAC MP4.
     * Falls back to direct in-container ffmpeg binary if score-engine port 6020 is down.
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
        $maxDuration = min(max((int) ($validated['max_duration'] ?? 90), 5), 120);
        $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');

        // 1. Try score-engine HTTP /convert-mp4 if reachable
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream !== false) {
            try {
                $response = Http::connectTimeout(2)
                    ->timeout(60)
                    ->attach('video', $stream, 'cricapp-reel.webm')
                    ->post($engineUrl . '/convert-mp4', ['max_duration' => $maxDuration]);

                if ($response->ok() && str_contains(strtolower((string) $response->header('Content-Type')), 'video/mp4')) {
                    return response($response->body(), 200, [
                        'Content-Type' => 'video/mp4',
                        'Content-Disposition' => 'attachment; filename="cricapp-reel-' . now()->format('Ymd-His') . '.mp4"',
                        'Cache-Control' => 'no-store',
                    ]);
                }
            } catch (\Throwable $e) {
                // Fall through to local ffmpeg binary
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        // 2. Fallback: Run local/container ffmpeg directly via Process
        $finder = new ExecutableFinder();
        $ffmpegBin = $finder->find('ffmpeg', null, ['/usr/bin', '/usr/local/bin']);
        if (!$ffmpegBin) {
            return response()->json([
                'status' => 'error',
                'message' => 'MP4 converter is unavailable in this environment; using native browser video instead.',
            ], 501);
        }

        $outputPath = sys_get_temp_dir() . '/cricapp-reel-' . uniqid('', true) . '.mp4';
        try {
            $process = new Process([
                $ffmpegBin,
                '-y',
                '-i', $file->getRealPath(),
                '-t', (string) $maxDuration,
                '-vf', 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=#050b16,setsar=1',
                '-r', '30',
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '23',
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ar', '44100',
                '-threads', '1',
                '-movflags', '+faststart',
                $outputPath,
            ]);
            $process->setTimeout(90);
            $process->run();

            if (!$process->isSuccessful() || !is_file($outputPath) || filesize($outputPath) === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'FFmpeg could not convert video: ' . Str::limit($process->getErrorOutput(), 160),
                ], 500);
            }

            $mp4Bytes = file_get_contents($outputPath);
            return response($mp4Bytes, 200, [
                'Content-Type' => 'video/mp4',
                'Content-Disposition' => 'attachment; filename="cricapp-reel-' . now()->format('Ymd-His') . '.mp4"',
                'Cache-Control' => 'no-store',
            ]);
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
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

    private function runPythonTtsCli(string $text, string $voice, string $rate, string $pitch): ?array
    {
        $scriptPath = base_path('score-engine/tts_cli.py');
        if (!is_file($scriptPath)) {
            return null;
        }

        $finder = new ExecutableFinder();
        $pythonBin = $finder->find('python3', null, ['/usr/bin', '/usr/local/bin']);
        if (!$pythonBin) {
            return null;
        }

        try {
            $process = new Process([$pythonBin, $scriptPath]);
            $process->setInput(json_encode([
                'text' => $text,
                'voice' => $voice,
                'rate' => $rate,
                'pitch' => $pitch,
            ]));
            $process->setTimeout(22);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $data = json_decode($process->getOutput(), true);
            if (is_array($data) && ($data['status'] ?? '') === 'success' && !empty($data['audio_base64'])) {
                return $data;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function fetchGoogleTranslateTts(string $text, string $voice): ?array
    {
        $isHindi = Str::startsWith($voice, 'hi-') || preg_match('/\p{Devanagari}/u', $text) === 1;
        $lang = $isHindi ? 'hi' : (Str::startsWith($voice, 'en-GB') ? 'en-GB' : 'en-IN');

        $chunks = $this->splitTextForTts($text, 185);
        $mp3Binary = '';

        try {
            foreach ($chunks as $chunk) {
                $resp = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                        'Referer' => 'https://translate.google.com/',
                    ])
                    ->get('https://translate.google.com/translate_tts', [
                        'ie' => 'UTF-8',
                        'client' => 'tw-ob',
                        'tl' => $lang,
                        'q' => $chunk,
                    ]);

                if ($resp->failed() || empty($resp->body())) {
                    return null;
                }
                $mp3Binary .= $resp->body();
            }

            if ($mp3Binary === '') {
                return null;
            }

            return [
                'status' => 'success',
                'engine' => 'google-tts',
                'voice' => $voice,
                'mime_type' => 'audio/mpeg',
                'audio_base64' => base64_encode($mp3Binary),
                'boundaries' => [],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function splitTextForTts(string $text, int $maxLen = 185): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $maxLen) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = mb_substr($word, 0, $maxLen);
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [mb_substr($text, 0, $maxLen)];
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
