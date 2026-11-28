<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ReelScriptService;
use App\Services\SocialPublishService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
    public function index(Request $request, ReelScriptService $scriptService, SocialPublishService $social)
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
            'socialStatus' => $social->getStatusSummary(),
        ]);
    }

    /**
     * 1-Click Article-to-Reel preloaded Studio page.
     */
    public function forArticle(Article $article, ReelScriptService $scriptService, SocialPublishService $social)
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
            'socialStatus' => $social->getStatusSummary(),
        ]);
    }

    /**
     * Regenerate an alternative Reel script variant & fresh background images.
     */
    public function refreshScript(Request $request, ReelScriptService $scriptService)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'article_id' => 'nullable|integer',
            'variant' => 'required|integer|min:0|max:200',
            'voice' => 'nullable|string|max:64',
            'exclude_images' => 'nullable|array|max:20',
            'exclude_images.*' => 'nullable|string|max:2048',
        ]);

        $variant = (int) $validated['variant'];
        $voice = trim((string) ($validated['voice'] ?? 'hi-IN-MadhurNeural'));
        $excludeImages = array_values(array_map(
            static fn ($u) => trim((string) $u),
            (array) ($validated['exclude_images'] ?? [])
        ));

        $article = !empty($validated['article_id'])
            ? Article::find((int) $validated['article_id'])
            : null;

        $project = $article
            ? $scriptService->buildFromArticle($article, $variant, $voice, $excludeImages)
            : $scriptService->buildDefaultBlank($variant, $voice, $excludeImages);

        return response()->json([
            'status' => 'success',
            'variant' => $variant,
            'project' => $project,
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

        $finalizePayload = static function (array $payload) use ($scriptService, $voice, $cleanText, $spokenText, $wasTranslated): array {
            $actualSpoken = (string) ($payload['spoken_text'] ?? $spokenText);
            $actualTranslated = $wasTranslated || !empty($payload['translated']);
            $payload['spoken_text'] = $actualSpoken;
            $payload['translated'] = $actualTranslated;
            if ($actualTranslated && Str::startsWith($voice, 'hi-') && $scriptService->isMostlyHindi($actualSpoken)) {
                $payload['hinglish_text'] = $scriptService->hindiToHinglish($actualSpoken, $cleanText);
            }
            return $payload;
        };

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
                return response()->json($finalizePayload($payload));
            }
        } catch (\Throwable $e) {
            // Fall through silently to in-container CLI & direct HTTP TTS
        }

        // 2. Fallback A: Direct python3 tts_cli.py inside container (edge-tts + gTTS)
        $cliPayload = $this->runPythonTtsCli($spokenText, $voice, $rate, $pitch);
        if ($cliPayload !== null) {
            return response()->json($finalizePayload($cliPayload));
        }

        // 3. Fallback B: Pure-PHP Google Translate TTS MP3 (works even if Python daemon/packages unavailable)
        $googlePayload = $this->fetchGoogleTranslateTts($spokenText, $voice);
        if ($googlePayload !== null) {
            return response()->json($finalizePayload($googlePayload));
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Could not reach AI voice servers right now. Try Record Mic or Upload Voice MP3.',
        ], 503);
    }

    /**
     * Switch slide Spoken Voiceover & Caption scripts dynamically between Hinglish (for Hindi voices) and English.
     */
    public function translateScript(Request $request, ReelScriptService $scriptService)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'target' => 'required|in:hinglish,en',
            'slides' => 'required|array|min:1|max:20',
            'slides.*.text' => 'nullable|string|max:1000',
            'slides.*.narration_en' => 'nullable|string|max:1000',
            'slides.*.narration_hi' => 'nullable|string|max:1000',
            'slides.*.narration_hinglish' => 'nullable|string|max:1000',
        ]);

        $target = $validated['target'];
        $results = [];
        $translatedCount = 0;

        foreach ($validated['slides'] as $slide) {
            $rawText = trim((string) ($slide['text'] ?? ''));
            $enRef = trim((string) ($slide['narration_en'] ?? ''));
            $hiStored = trim((string) ($slide['narration_hi'] ?? ''));
            $hinglishStored = trim((string) ($slide['narration_hinglish'] ?? ''));

            if ($target === 'hinglish') {
                $sourceForHi = $enRef !== '' ? $enRef : $rawText;
                if ($scriptService->isMostlyHindi($hiStored)) {
                    $hiText = $hiStored;
                } elseif ($scriptService->isMostlyHindi($rawText)) {
                    $hiText = $rawText;
                } else {
                    $hiText = $scriptService->translateToHindiFast($sourceForHi);
                }

                $hasGenuineHi = $scriptService->isMostlyHindi($hiText);
                $hasDistinctHinglish = $hinglishStored !== ''
                    && $hinglishStored !== $enRef
                    && !$scriptService->isDevanagari($hinglishStored);

                if ($hasGenuineHi && $hasDistinctHinglish && $scriptService->isMostlyHindi($hiStored)) {
                    $hinglishText = $hinglishStored;
                } elseif ($hasGenuineHi) {
                    $hinglishText = $scriptService->hindiToHinglish($hiText, $sourceForHi);
                } else {
                    $hinglishText = $rawText !== '' ? $rawText : $enRef;
                }

                if ($hasGenuineHi) {
                    $translatedCount++;
                }

                $results[] = [
                    'narration' => $hinglishText,
                    'narration_hinglish' => $hasGenuineHi ? $hinglishText : '',
                    'narration_hi' => $hasGenuineHi ? $hiText : '',
                    'narration_en' => $enRef !== '' ? $enRef : (!$scriptService->isDevanagari($rawText) ? $rawText : ''),
                ];
            } else {
                if ($enRef !== '' && !$scriptService->isDevanagari($enRef)) {
                    $enText = $enRef;
                } elseif ($scriptService->isDevanagari($rawText)) {
                    $enText = $scriptService->translateToEnglishFast($rawText);
                } elseif ($scriptService->isMostlyHindi($hiStored)) {
                    $enText = $scriptService->translateToEnglishFast($hiStored);
                } else {
                    $enText = $rawText;
                }

                if (!$scriptService->isDevanagari($enText) && $enText !== '') {
                    $translatedCount++;
                }

                $results[] = [
                    'narration' => $enText,
                    'narration_en' => $enText,
                    'narration_hi' => $hiStored,
                    'narration_hinglish' => $hinglishStored,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'target' => $target,
            'translated_count' => $translatedCount,
            'slides' => $results,
        ]);
    }

    /**
     * Transcode recorded WebM/MP4 canvas stream to Instagram/YouTube compatible H.264/AAC MP4.
     * Uses fast stream remux when input is already MP4 and falls back safely to local ffmpeg or score-engine.
     */
    public function convertMp4(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'video' => 'required|file|max:61440',
            'max_duration' => 'nullable|integer|min:5|max:120',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(150);
        }

        $maxDuration = min(max((int) ($validated['max_duration'] ?? 90), 5), 120);
        $mp4 = $this->ensureMp4Path($request->file('video'), $maxDuration);

        try {
            if (!empty($mp4['error'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => $mp4['error'],
                ], $mp4['status'] ?? 500);
            }

            $mp4Bytes = file_get_contents($mp4['path']);
            return response($mp4Bytes, 200, [
                'Content-Type' => 'video/mp4',
                'Content-Disposition' => 'attachment; filename="cricapp-reel-' . now()->format('Ymd-His') . '.mp4"',
                'Cache-Control' => 'no-store',
            ]);
        } finally {
            if (!empty($mp4['cleanup']) && !empty($mp4['path']) && is_file($mp4['path'])) {
                @unlink($mp4['path']);
            }
        }
    }

    /**
     * 1-Click Publish rendered Reel MP4 to connected Instagram Reels & YouTube Shorts accounts.
     */
    public function publishSocial(Request $request, SocialPublishService $social)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'video' => 'required|file|max:61440',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:instagram,youtube',
            'title' => 'nullable|string|max:160',
            'caption' => 'nullable|string|max:2200',
            'privacy' => 'nullable|in:public,unlisted,private',
            'max_duration' => 'nullable|integer|min:5|max:120',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $maxDuration = min(max((int) ($validated['max_duration'] ?? 90), 5), 120);
        $mp4 = $this->ensureMp4Path($request->file('video'), $maxDuration);

        try {
            if (!empty($mp4['error'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => $mp4['error'],
                    'results' => [],
                ], $mp4['status'] ?? 500);
            }

            $caption = trim((string) ($validated['caption'] ?? ''));
            $title = trim((string) ($validated['title'] ?? ''));
            if ($title === '' && $caption !== '') {
                $title = Str::limit(strtok($caption, "\n") ?: $caption, 92, '');
            }
            $privacy = (string) ($validated['privacy'] ?? config('services.youtube.default_privacy', 'public'));

            preg_match_all('/#([A-Za-z0-9_]+)/', $caption, $tagMatches);
            $tags = $tagMatches[1] ?? [];

            $platforms = array_values(array_unique((array) $validated['platforms']));
            $results = [];
            $successCount = 0;

            foreach ($platforms as $platform) {
                try {
                    if ($platform === 'instagram') {
                        $res = $social->publishInstagramReel($mp4['path'], $caption !== '' ? $caption : $title);
                        $results['instagram'] = array_merge(['ok' => true], $res);
                        $successCount++;
                    } elseif ($platform === 'youtube') {
                        $res = $social->publishYouTubeShort(
                            $mp4['path'],
                            $title,
                            $caption !== '' ? $caption : $title,
                            $tags,
                            $privacy
                        );
                        $results['youtube'] = array_merge(['ok' => true], $res);
                        $successCount++;
                    }
                } catch (\Throwable $e) {
                    $results[$platform] = [
                        'ok' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => $successCount > 0 ? 'success' : 'error',
                'results' => $results,
            ], $successCount > 0 ? 200 : 422);
        } finally {
            if (!empty($mp4['cleanup']) && !empty($mp4['path']) && is_file($mp4['path'])) {
                @unlink($mp4['path']);
            }
        }
    }

    /**
     * Guarantee an H.264/AAC faststart MP4 file path from uploaded browser WebM or MP4.
     */
    protected function ensureMp4Path(UploadedFile $file, int $maxDuration = 90): array
    {
        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            return ['path' => '', 'cleanup' => false, 'error' => 'Uploaded video could not be read.', 'status' => 422];
        }

        $origExt = strtolower((string) ($file->getClientOriginalExtension() ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION)));
        $finder = new ExecutableFinder();
        $ffmpegBin = $finder->find('ffmpeg', null, ['/usr/bin', '/usr/local/bin']);

        if ($ffmpegBin) {
            $outputPath = sys_get_temp_dir() . '/cricapp-reel-' . uniqid('', true) . '.mp4';

            // Fast path: browser already recorded native MP4 -> copy H.264 video stream & ensure AAC + faststart in <1s
            if ($origExt === 'mp4') {
                $remux = new Process([
                    $ffmpegBin,
                    '-y',
                    '-i', $realPath,
                    '-t', (string) $maxDuration,
                    '-c:v', 'copy',
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-ar', '44100',
                    '-movflags', '+faststart',
                    $outputPath,
                ]);
                $remux->setTimeout(30);
                $remux->run();

                if ($remux->isSuccessful() && is_file($outputPath) && filesize($outputPath) > 4096) {
                    return ['path' => $outputPath, 'cleanup' => true, 'error' => null, 'status' => 200];
                }
                @unlink($outputPath);
            }

            // Full transcode path (WebM or fallback re-encode)
            $process = new Process([
                $ffmpegBin,
                '-y',
                '-i', $realPath,
                '-t', (string) $maxDuration,
                '-vf', 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=#050b16,setsar=1',
                '-r', '30',
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '24',
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ar', '44100',
                '-threads', '2',
                '-movflags', '+faststart',
                $outputPath,
            ]);
            $process->setTimeout(110);
            $process->run();

            if ($process->isSuccessful() && is_file($outputPath) && filesize($outputPath) > 4096) {
                return ['path' => $outputPath, 'cleanup' => true, 'error' => null, 'status' => 200];
            }
            @unlink($outputPath);

            return [
                'path' => '',
                'cleanup' => false,
                'error' => 'FFmpeg could not convert video: ' . Str::limit($process->getErrorOutput(), 160),
                'status' => 500,
            ];
        }

        // Secondary fallback: score-engine HTTP /convert-mp4 if ffmpeg binary isn't local
        $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
        $stream = fopen($realPath, 'rb');
        if ($stream !== false) {
            try {
                $response = Http::connectTimeout(2)
                    ->timeout(90)
                    ->attach('video', $stream, 'cricapp-reel.' . ($origExt ?: 'webm'))
                    ->post($engineUrl . '/convert-mp4', ['max_duration' => $maxDuration]);

                if ($response->ok() && str_contains(strtolower((string) $response->header('Content-Type')), 'video/mp4')) {
                    $outputPath = sys_get_temp_dir() . '/cricapp-reel-' . uniqid('', true) . '.mp4';
                    file_put_contents($outputPath, $response->body());
                    return ['path' => $outputPath, 'cleanup' => true, 'error' => null, 'status' => 200];
                }
            } catch (\Throwable $e) {
                // Fall through to unavailable response
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        if ($origExt === 'mp4') {
            return ['path' => $realPath, 'cleanup' => false, 'error' => null, 'status' => 200];
        }

        return [
            'path' => '',
            'cleanup' => false,
            'error' => 'MP4 converter is unavailable in this environment; using native browser video instead.',
            'status' => 501,
        ];
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

        $ipv4Curl = defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')
            ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
            : [];

        try {
            foreach ($chunks as $chunk) {
                $resp = Http::withOptions($ipv4Curl)
                    ->connectTimeout(2)
                    ->timeout(10)
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
