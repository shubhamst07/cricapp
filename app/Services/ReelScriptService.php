<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ReelScriptService
{
    /**
     * Curated high-contrast cricket photos safe for 9:16 vertical crop.
     */
    public function curatedImages(): array
    {
        return [
            [
                'label' => 'Stadium Floodlights',
                'url' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'label' => 'Match Action Pitch',
                'url' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'label' => 'Batting Crease Focus',
                'url' => 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'label' => 'Green Outfield',
                'url' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'label' => 'Arena Atmosphere',
                'url' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
            ],
        ];
    }

    /**
     * Free Microsoft Neural voices available via Edge TTS.
     */
    public function voiceOptions(): array
    {
        return [
            ['id' => 'hi-IN-MadhurNeural', 'label' => 'Madhur — Hindi (Male, Commentary) [Default]'],
            ['id' => 'hi-IN-SwaraNeural', 'label' => 'Swara — Hindi (Female, Clear)'],
            ['id' => 'en-IN-PrabhatNeural', 'label' => 'Prabhat — Indian English (Male, Energetic)'],
            ['id' => 'en-IN-NeerjaNeural', 'label' => 'Neerja — Indian English (Female, Crisp)'],
            ['id' => 'en-GB-RyanNeural', 'label' => 'Ryan — UK Commentary (Male)'],
        ];
    }

    /**
     * Build a structured 5-slide 9:16 Reel storyboard & spoken narration from an Article.
     */
    public function buildFromArticle(Article $article): array
    {
        $title = $this->cleanText($article->title ?? 'Latest Cricket Update');
        $excerpt = $this->cleanText($article->excerpt ?? '');
        $category = strtoupper($this->cleanText($article->category ?: 'CRICKET UPDATE'));
        $coverUrl = $article->cover_image_url;
        $curated = array_column($this->curatedImages(), 'url');
        // Ephemeral local uploads from older deploys may not exist on container rebuilds — fall back to crisp cricket photo
        if (Str::startsWith($coverUrl, '/') && !is_file(public_path(ltrim($coverUrl, '/')))) {
            $coverUrl = $curated[0] ?? $coverUrl;
        }

        $sections = $this->extractSections((string) ($article->content ?? ''));
        $middleSlides = array_slice($sections, 0, 3);
        $sectionsHi = $this->isMostlyHindi((string) ($article->content_hi ?? ''))
            ? $this->extractSections((string) $article->content_hi)
            : [];

        // Ensure at least 2 content slides even for very short articles
        if (empty($middleSlides)) {
            $fallbackText = $excerpt !== '' ? $excerpt : $title;
            $middleSlides = [[
                'badge' => 'THE BIG STORY',
                'headline' => Str::limit($fallbackText, 76, ''),
                'subtext' => 'Tap the link in bio or search CricApp.in for ball-by-ball details.',
                'narration' => Str::limit($fallbackText, 180, '.'),
            ]];
        }

        $hookSub = $excerpt !== ''
            ? Str::limit($excerpt, 105)
            : ($middleSlides[0]['headline'] ?? 'Full breakdown inside.');

        $titleHi = $this->isMostlyHindi((string) ($article->title_hi ?? ''))
            ? $this->cleanText((string) $article->title_hi)
            : '';
        $excerptHi = $this->isMostlyHindi((string) ($article->excerpt_hi ?? ''))
            ? $this->cleanText((string) $article->excerpt_hi)
            : '';

        $slide1En = $this->toSpeechSentence($title . '. ' . $hookSub);
        $slide1Hi = $titleHi !== ''
            ? $this->toSpeechSentence($titleHi . ($excerptHi !== '' ? '। ' . $excerptHi : ''))
            : $this->translateToHindiFast($slide1En);
        $slide1HasHi = $this->isMostlyHindi($slide1Hi);
        $slide1Hinglish = $slide1HasHi
            ? $this->hindiToHinglish($slide1Hi, $slide1En)
            : '';

        $slides = [];
        $slides[] = [
            'badge' => Str::limit($category, 26, ''),
            'headline' => Str::limit($title, 82),
            'subtext' => $hookSub,
            'narration' => $slide1Hinglish !== '' ? $slide1Hinglish : $slide1En,
            'narration_hinglish' => $slide1Hinglish,
            'narration_hi' => $slide1HasHi ? $slide1Hi : '',
            'narration_en' => $slide1En,
            'image' => $coverUrl,
            'duration' => 5.5,
        ];

        foreach ($middleSlides as $idx => $sec) {
            $bgImage = ($idx % 2 === 1 && isset($curated[$idx])) ? $curated[$idx] : $coverUrl;
            $enSpeech = $this->toSpeechSentence($sec['narration']);
            $hiSpeech = isset($sectionsHi[$idx]['narration']) && $this->isMostlyHindi($sectionsHi[$idx]['narration'])
                ? $this->toSpeechSentence($sectionsHi[$idx]['narration'])
                : $this->translateToHindiFast($enSpeech);
            $hasHi = $this->isMostlyHindi($hiSpeech);
            $hinglishSpeech = $hasHi
                ? $this->hindiToHinglish($hiSpeech, $enSpeech)
                : '';

            $slides[] = [
                'badge' => Str::limit(strtoupper($sec['badge'] ?: 'KEY POINT ' . ($idx + 1)), 26, ''),
                'headline' => Str::limit($sec['headline'], 78),
                'subtext' => Str::limit($sec['subtext'], 110),
                'narration' => $hinglishSpeech !== '' ? $hinglishSpeech : $enSpeech,
                'narration_hinglish' => $hinglishSpeech,
                'narration_hi' => $hasHi ? $hiSpeech : '',
                'narration_en' => $enSpeech,
                'image' => $bgImage,
                'duration' => 5.5,
            ];
        }

        $slides[] = [
            'badge' => 'FOLLOW @CRICAPP.IN',
            'headline' => 'Full Story & Live Scores',
            'subtext' => 'Search CricApp.in on Google for ball-by-ball commentary & squad analysis.',
            'narration' => 'Poori story aur ball-by-ball live updates abhi padhein, CricApp.in par!',
            'narration_hinglish' => 'Poori story aur ball-by-ball live updates abhi padhein, CricApp.in par!',
            'narration_hi' => 'पूरी स्टोरी और बॉल-बाय-बॉल लाइव अपडेट्स अभी पढ़ें, CricApp dot in पर।',
            'narration_en' => 'Read the full story and ball by ball live updates now on CricApp dot in.',
            'image' => $curated[0] ?? $coverUrl,
            'duration' => 4.5,
        ];

        return [
            'source_article_id' => $article->id,
            'title' => $title,
            'theme' => 'stadium',
            'voice' => 'hi-IN-MadhurNeural',
            'slides' => $slides,
            'social_caption' => $this->buildSocialCaption($article, $title, $excerpt),
            'article_url' => route('articles.show', $article->slug),
        ];
    }

    /**
     * Build a blank starter project when admin opens Reel Studio directly.
     */
    public function buildDefaultBlank(): array
    {
        $curated = array_column($this->curatedImages(), 'url');

        return [
            'source_article_id' => null,
            'title' => 'Breaking Cricket Reel',
            'theme' => 'stadium',
            'voice' => 'hi-IN-MadhurNeural',
            'article_url' => url('/articles'),
            'social_caption' => "Latest Cricket Update! Live scores & full breakdown on CricApp.in\n\n#Cricket #CricApp #CricketReels #IPL #TeamIndia #CricketNews",
            'slides' => [
                [
                    'badge' => 'BREAKING CRICKET',
                    'headline' => 'Massive Turning Point In Today\'s Thriller!',
                    'subtext' => 'Watch the top 3 moments that changed the match completely.',
                    'narration' => 'Bada cricket update! Janiye aaj ke thrilling match ke un 3 moments ke baare mein jinhone poora khel palat diya.',
                    'narration_hinglish' => 'Bada cricket update! Janiye aaj ke thrilling match ke un 3 moments ke baare mein jinhone poora khel palat diya.',
                    'narration_hi' => 'बड़ा क्रिकेट अपडेट! जानिए आज के रोमांचक मैच के उन तीन पलों के बारे में जिन्होंने पूरा खेल पलट दिया।',
                    'narration_en' => 'Big cricket update! Here are the 3 moments that completely turned today\'s thriller around.',
                    'image' => $curated[0],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'MOMENT 1',
                    'headline' => 'Powerplay Assault Stuns The Opposition',
                    'subtext' => 'Clean hitting right from over one put instant scoreboard pressure.',
                    'narration' => 'Sabse pehle powerplay mein toofani batting ne vipakshi team par turant bada dabav bana diya.',
                    'narration_hinglish' => 'Sabse pehle powerplay mein toofani batting ne vipakshi team par turant bada dabav bana diya.',
                    'narration_hi' => 'सबसे पहले पावरप्ले में तूफानी बल्लेबाजी ने विपक्षी टीम पर तुरंत बड़ा दबाव बना दिया।',
                    'narration_en' => 'First, an explosive powerplay start put the opposition under immediate scoreboard pressure.',
                    'image' => $curated[1],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'MOMENT 2',
                    'headline' => 'Death-Overs Spell Seals The Contest',
                    'subtext' => 'Pinpoint yorkers and clever slower balls defended the target.',
                    'narration' => 'Iske baad death overs ki sateek yorker bowling ne aakhiri palon mein jeet pakki kar di.',
                    'narration_hinglish' => 'Iske baad death overs ki sateek yorker bowling ne aakhiri palon mein jeet pakki kar di.',
                    'narration_hi' => 'इसके बाद डेथ ओवर्स की सटीक यॉर्कर गेंदबाजी ने आखिरी पलों में जीत पक्की कर दी।',
                    'narration_en' => 'Then came pinpoint death-over yorkers that sealed the contest in the final overs.',
                    'image' => $curated[2],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'LIVE ON CRICAPP.IN',
                    'headline' => 'Ball-by-Ball Scores & Reports',
                    'subtext' => 'Search CricApp.in on Google for full scorecards & match news.',
                    'narration' => 'Sabse tez live cricket scores aur match reports ke liye abhi judein, CricApp.in par!',
                    'narration_hinglish' => 'Sabse tez live cricket scores aur match reports ke liye abhi judein, CricApp.in par!',
                    'narration_hi' => 'सबसे तेज़ लाइव क्रिकेट स्कोर और मैच रिपोर्ट्स के लिए अभी जुड़ें, CricApp dot in पर।',
                    'narration_en' => 'Follow CricApp dot in for fastest live cricket scores and daily match stories.',
                    'image' => $curated[3],
                    'duration' => 4.5,
                ],
            ],
        ];
    }

    /**
     * Parse markdown headings and paragraphs into concise vertical reel sections.
     */
    private function extractSections(string $markdown): array
    {
        $sections = [];
        $lines = preg_split('/\R+/', $markdown) ?: [];

        $currentHeading = '';
        $currentBody = [];

        $flush = function () use (&$currentHeading, &$currentBody, &$sections) {
            $bodyText = $this->cleanText(implode(' ', $currentBody));
            $headText = $this->cleanText($currentHeading);

            if ($headText === '' && $bodyText === '') {
                return;
            }

            $sentences = preg_split('/(?<=[.!?])\s+/', $bodyText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $firstSentence = $sentences[0] ?? $headText;
            $secondSentence = $sentences[1] ?? '';

            $headline = $headText !== '' ? $headText : Str::limit($firstSentence, 72);
            $subtext = $headText !== '' ? $firstSentence : ($secondSentence !== '' ? $secondSentence : $bodyText);
            $headNorm = mb_strtolower(rtrim($headText, '.!?: '), 'UTF-8');
            $firstNorm = mb_strtolower(rtrim($firstSentence, '.!?: '), 'UTF-8');
            if ($headNorm !== '' && ($headNorm === $firstNorm || str_starts_with($firstNorm, $headNorm))) {
                $narration = trim($firstSentence . ' ' . $secondSentence);
            } else {
                $narration = trim(($headText !== '' ? $headText . '. ' : '') . $firstSentence . ' ' . $secondSentence);
            }

            if (mb_strlen($headline) >= 6) {
                $sections[] = [
                    'badge' => $headText !== '' ? $headText : 'MATCH INSIGHT',
                    'headline' => $headline,
                    'subtext' => $subtext !== '' ? $subtext : $headline,
                    'narration' => $narration !== '' ? $narration : $headline,
                ];
            }

            $currentHeading = '';
            $currentBody = [];
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^#{1,4}\s+(.+)$/', $line, $m)) {
                $flush();
                $currentHeading = $m[1];
                continue;
            }

            $currentBody[] = $line;

            // Keep paragraphs compact when articles lack headings
            if ($currentHeading === '' && count($currentBody) >= 2) {
                $flush();
            }
        }
        $flush();

        return $sections;
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        // Strip markdown images & links keeping label text
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        // Strip markdown emphasis/code markers
        $text = str_replace(['**', '__', '`', '###', '##', '#', '>', '•'], ' ', $text);
        $text = preg_replace('/^\s*[-*+]\s+/m', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    private function toSpeechSentence(string $text): string
    {
        $clean = $this->cleanText($text);
        $clean = preg_replace('/CricApp\.in/i', 'CricApp dot in', $clean);
        // Remove emojis / pictographs so neural TTS doesn't read symbol names awkwardly
        $clean = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', (string) $clean);
        $clean = preg_replace('/\s+/', ' ', (string) $clean);
        return Str::limit(trim((string) $clean), 210, '.');
    }

    public function isDevanagari(string $text): bool
    {
        return preg_match('/[\x{0900}-\x{097F}]/u', $text) === 1;
    }

    /**
     * Check that the majority of script letters are Devanagari Hindi (not 1 symbol in English text).
     */
    public function isMostlyHindi(string $text): bool
    {
        $devCount = preg_match_all('/[\x{0900}-\x{0963}\x{0966}-\x{097F}]/u', $text) ?: 0;
        $latinCount = preg_match_all('/[A-Za-z]/', $text) ?: 0;
        return $devCount >= 6 && $devCount >= $latinCount;
    }

    /**
     * Fast cached English -> Hindi translation with multi-provider fallback (Render-safe).
     */
    public function translateToHindiFast(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($clean === '' || $this->isMostlyHindi($clean)) {
            return $clean;
        }

        // Protect brand pronunciation during translation
        $prepared = preg_replace('/CricApp\.in/i', 'CricApp dot in', $clean) ?? $clean;
        $cacheKey = 'reel_hi_script_v2_' . md5($prepared);

        try {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $this->isMostlyHindi($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Ignore cache read error
        }

        $translated = $this->translateChunkViaProviders($prepared);

        if ($translated !== '' && $this->isMostlyHindi($translated)) {
            try {
                Cache::put($cacheKey, $translated, now()->addDays(14));
            } catch (\Throwable $e) {
                // Ignore cache write error
            }
            return $translated;
        }

        return $clean;
    }

    private function translateChunkViaProviders(string $text): string
    {
        $ipv4Curl = defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')
            ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
            : [];

        // 0. Score-engine local Python /translate endpoint (deep-translator, fast when daemon is running)
        try {
            $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
            $resp = Http::connectTimeout(1)->timeout(5)->post($engineUrl . '/translate', [
                'text' => $text,
                'target' => 'hi',
            ]);
            if ($resp->ok()) {
                $candidate = trim((string) ($resp->json('translated_text') ?? ''));
                if ($candidate !== '' && $this->isMostlyHindi($candidate)) {
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to external HTTP translators
        }

        // 1. Google clients5 endpoint (IPv4 forced to avoid 60s IPv6 hang)
        try {
            $resp = Http::withOptions($ipv4Curl)
                ->connectTimeout(2)
                ->timeout(3)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                ])
                ->get('https://clients5.google.com/translate_a/t', [
                    'client' => 'dict-chrome-ex',
                    'sl' => 'auto',
                    'tl' => 'hi',
                    'q' => $text,
                ]);
            if ($resp->successful()) {
                $arr = $resp->json();
                $candidate = '';
                if (is_array($arr) && isset($arr[0])) {
                    $candidate = is_string($arr[0]) ? $arr[0] : (string) ($arr[0][0] ?? '');
                }
                $candidate = trim($candidate);
                if ($candidate !== '' && $this->isMostlyHindi($candidate)) {
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            // Try next provider
        }

        // 2. Google Translate GTX endpoint (IPv4 forced)
        try {
            $resp = Http::withOptions($ipv4Curl)
                ->connectTimeout(2)
                ->timeout(3)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                ])
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => 'hi',
                    'dt' => 't',
                    'q' => $text,
                ]);
            if ($resp->successful()) {
                $arr = $resp->json();
                if (is_array($arr) && isset($arr[0]) && is_array($arr[0])) {
                    $candidate = '';
                    foreach ($arr[0] as $part) {
                        $candidate .= $part[0] ?? '';
                    }
                    $candidate = trim($candidate);
                    if ($candidate !== '' && $this->isMostlyHindi($candidate)) {
                        return $candidate;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Try next provider
        }

        // 3. MyMemory API fallback (IPv4 forced)
        try {
            $snippet = mb_substr($text, 0, 480);
            $resp = Http::withOptions($ipv4Curl)
                ->connectTimeout(2)
                ->timeout(4)
                ->withHeaders([
                    'User-Agent' => 'CricAppNews/2.0 (Cricket Reel Studio)',
                    'Accept' => 'application/json',
                ])
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $snippet,
                    'langpair' => 'en|hi',
                ]);
            if ($resp->successful()) {
                $json = $resp->json();
                $res = trim(html_entity_decode((string) ($json['responseData']['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($res !== '' && !str_contains(strtoupper($res), 'MYMEMORY WARNING') && $this->isMostlyHindi($res)) {
                    return $res;
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return '';
    }

    /**
     * Translate Hindi script back to clean English when admin switches to an English narrator voice.
     */
    public function translateToEnglishFast(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($clean === '' || !$this->isDevanagari($clean)) {
            return $clean;
        }

        $ipv4Curl = defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')
            ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
            : [];

        try {
            $engineUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
            $resp = Http::connectTimeout(1)->timeout(5)->post($engineUrl . '/translate', [
                'text' => $clean,
                'target' => 'en',
            ]);
            if ($resp->ok()) {
                $candidate = trim((string) ($resp->json('translated_text') ?? ''));
                if ($candidate !== '' && !$this->isDevanagari($candidate)) {
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        try {
            $resp = Http::withOptions($ipv4Curl)
                ->connectTimeout(2)
                ->timeout(3)
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => 'auto',
                    'tl' => 'en',
                    'dt' => 't',
                    'q' => $clean,
                ]);
            if ($resp->successful()) {
                $arr = $resp->json();
                if (is_array($arr) && isset($arr[0]) && is_array($arr[0])) {
                    $out = '';
                    foreach ($arr[0] as $part) {
                        $out .= $part[0] ?? '';
                    }
                    $out = trim($out);
                    if ($out !== '' && !$this->isDevanagari($out)) {
                        return $out;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return $clean;
    }

    /**
     * Convert Devanagari Hindi narration into crisp, natural Indian Cricket Hinglish.
     */
    public function hindiToHinglish(string $hindi, string $englishRef = ''): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $hindi) ?? '');
        if ($text === '') {
            return '';
        }

        // Normalize brand & Hindi danda punctuation cleanly
        $text = preg_replace('/CricApp\s*(?:dot|\.)\s*in/iu', 'CricApp.in', $text) ?? $text;
        $text = str_replace(['।', '॥'], '.', $text);

        // 1. Common cricket, English-loanword & Hindi phrasing dictionary (ordered longest-first cleanly)
        $phraseMap = [
            'बॉल-बाय-बॉल' => 'ball-by-ball',
            'व्हाइट-बॉल' => 'white-ball',
            'रेड-बॉल' => 'red-ball',
            'लाइव अपडेट्स' => 'live updates',
            'लाइव स्कोर' => 'live score',
            'मिनी-नीलामी' => 'mini-auction',
            'मिनी नीलामी' => 'mini-auction',
            'मिनी-ऑक्शन' => 'mini-auction',
            'मिनी ऑक्शन' => 'mini-auction',
            'स्ट्राइक रेट' => 'strike rate',
            'डेथ ओवर्स' => 'death overs',
            'सुपर ओवर' => 'super over',
            'रन चेज़' => 'run chase',
            'रन-चेज़' => 'run chase',
            'वर्ल्ड कप' => 'World Cup',
            'विश्व कप' => 'World Cup',
            'टी20' => 'T20',
            'टी-20' => 'T20',
            'वनडे' => 'ODI',
            'टेस्ट मैच' => 'Test match',
            'टेस्ट क्रिकेट' => 'Test cricket',
            'पावरप्ले' => 'powerplay',
            'ब्लूप्रिंट' => 'blueprint',
            'बीसीसीआई' => 'BCCI',
            'आईपीएल' => 'IPL',
            'आईसीसी' => 'ICC',
            'रोहित शर्मा' => 'Rohit Sharma',
            'विराट कोहली' => 'Virat Kohli',
            'बेन स्टोक्स' => 'Ben Stokes',
            'जसप्रीत बुमराह' => 'Jasprit Bumrah',
            'हार्दिक पंड्या' => 'Hardik Pandya',
            'शुभमन गिल' => 'Shubman Gill',
            'सूर्यकुमार यादव' => 'Suryakumar Yadav',
            'महेंद्र सिंह धोनी' => 'MS Dhoni',
            'रिषभ पंत' => 'Rishabh Pant',
            'केएल राहुल' => 'KL Rahul',
            'नीलामी' => 'auction',
            'ऑक्शन' => 'auction',
            'फ्रेंचाइजियों' => 'franchises',
            'फ्रेंचाइजी' => 'franchise',
            'रोमांचक' => 'thrilling',
            'आक्रामक' => 'aggressive',
            'वास्तव में' => 'vastav mein',
            'बल्लेबाजी' => 'batting',
            'गेंदबाजी' => 'bowling',
            'बल्लेबाजों' => 'batsmen',
            'बल्लेबाज़' => 'batsman',
            'बल्लेबाज' => 'batsman',
            'गेंदबाजों' => 'bowlers',
            'गेंदबाज़' => 'bowler',
            'गेंदबाज' => 'bowler',
            'क्षेत्ररक्षण' => 'fielding',
            'फील्डिंग' => 'fielding',
            'व्हाइटबोर्ड' => 'whiteboard',
            'माइक्रोसेकंड' => 'microsecond',
            'बुक' => 'book',
            'विलो' => 'willow',
            'बच्चे' => 'bachche',
            'होता' => 'hota',
            'शायद' => 'shayad',
            'जीता' => 'jeeta',
            'लिखा' => 'likha',
            'बनाया' => 'banaya',
            'खिलाड़ियों' => 'players',
            'खिलाड़ी' => 'player',
            'कप्तानी' => 'captaincy',
            'कप्तान' => 'captain',
            'विकेटों' => 'wickets',
            'विकेट' => 'wicket',
            'ओवरों' => 'overs',
            'ओवर्स' => 'overs',
            'ओवर' => 'over',
            'यॉर्कर' => 'yorker',
            'बाउंड्री' => 'boundary',
            'स्टेडियम' => 'stadium',
            'टूर्नामेंट' => 'tournament',
            'मुकाबले' => 'match',
            'मुकाबला' => 'match',
            'प्रदर्शन' => 'performance',
            'रणनीति' => 'strategy',
            'विश्लेषण' => 'analysis',
            'रिपोर्ट्स' => 'reports',
            'रिपोर्ट' => 'report',
            'अपडेट्स' => 'updates',
            'अपडेट' => 'update',
            'कहानी' => 'kahani',
            'स्टोरी' => 'story',
            'क्रिकेट' => 'cricket',
            'भारतीय' => 'Indian',
            'भारत' => 'India',
            'इंग्लैंड' => 'England',
            'ऑस्ट्रेलिया' => 'Australia',
            'न्यूजीलैंड' => 'New Zealand',
            'दक्षिण अफ्रीका' => 'South Africa',
            'पाकिस्तान' => 'Pakistan',
            'श्रीलंका' => 'Sri Lanka',
            'बांग्लादेश' => 'Bangladesh',
            'अफगानिस्तान' => 'Afghanistan',
            'वेस्टइंडीज' => 'West Indies',
            'गोवा' => 'Goa',
            'मुंबई' => 'Mumbai',
            'चेन्नई' => 'Chennai',
            'बेंगलुरु' => 'Bengaluru',
            'कोलकाता' => 'Kolkata',
            'दिल्ली' => 'Delhi',
            'स्मार्ट' => 'smart',
            'सुरक्षित' => 'safe',
            'मजबूत' => 'strong',
            'शानदार' => 'shandar',
            'तूफानी' => 'toofani',
            'सटीक' => 'sateek',
            'लाइव' => 'live',
            'स्कोर' => 'score',
            'टारगेट' => 'target',
            'रिकॉर्ड' => 'record',
            'नियम' => 'rule',
            'नियमों' => 'rules',
            'रूल' => 'rule',
            'बैन' => 'ban',
            'एंट्री' => 'entry',
            'वापसी' => 'wapsi',
            'टीमों' => 'teams',
            'टीम' => 'team',
            'मैच' => 'match',
            'बॉल' => 'ball',
            'रनों' => 'runs',
            'रन' => 'runs',
            'छह' => '6',
            'तीन' => '3',
            'सालों' => 'saalon',
            'पहले' => 'pehle',
            'बावजूद' => 'baavjood',
            'क्योंकि' => 'kyunki',
            'इसलिए' => 'isliye',
            'लेकिन' => 'lekin',
            'जबकि' => 'jabki',
            'जानिए' => 'janiye',
            'समझिए' => 'samjhiye',
            'देखिए' => 'dekhiye',
            'पढ़ें' => 'padhein',
            'जुड़ें' => 'judein',
            'अभी' => 'abhi',
            'पूरी' => 'poori',
            'पूरा' => 'poora',
            'बहुत' => 'bahut',
            'सबसे' => 'sabse',
            'अधिक' => 'zyada',
            'बड़ा' => 'bada',
            'बड़ी' => 'badi',
            'क्यों' => 'kyun',
            'कैसे' => 'kaise',
            'क्या' => 'kya',
            'कौन' => 'kaun',
            'कब' => 'kab',
            'कहाँ' => 'kahan',
            'जहाँ' => 'jahan',
            'यहाँ' => 'yahan',
            'वहाँ' => 'wahan',
            'उनकी' => 'unki',
            'उनके' => 'unke',
            'उनका' => 'unka',
            'उसकी' => 'uski',
            'उसके' => 'uske',
            'उसका' => 'uska',
            'इसकी' => 'iski',
            'इसके' => 'iske',
            'इसका' => 'iska',
            'अपने' => 'apne',
            'अपनी' => 'apni',
            'अपना' => 'apna',
            'सभी' => 'sabhi',
            'आखिरी' => 'aakhiri',
            'पहली' => 'pehli',
            'पहले' => 'pehle',
            'दूसरे' => 'doosre',
            'तीसरे' => 'teesre',
            'खिलाफ' => 'khilaf',
            'दौरान' => 'dauran',
            'तुरंत' => 'turant',
            'हमेशा' => 'hamesha',
            'आज' => 'aaj',
            'कल' => 'kal',
            'सिर्फ' => 'sirf',
            'बल्कि' => 'balki',
            'साथ' => 'saath',
            'बाद' => 'baad',
            'तक' => 'tak',
            'लिए' => 'liye',
            'और' => 'aur',
            'या' => 'ya',
            'में' => 'mein',
            'पर' => 'par',
            'से' => 'se',
            'को' => 'ko',
            'का' => 'ka',
            'की' => 'ki',
            'के' => 'ke',
            'ने' => 'ne',
            'है' => 'hai',
            'हैं' => 'hain',
            'था' => 'tha',
            'थे' => 'the',
            'थी' => 'thi',
            'हो' => 'ho',
            'ही' => 'hi',
            'भी' => 'bhi',
            'नहीं' => 'nahi',
            'ना' => 'na',
            'तो' => 'toh',
            'इस' => 'is',
            'उस' => 'us',
            'ये' => 'ye',
            'यह' => 'yeh',
            'वे' => 've',
            'वह' => 'woh',
            'एक' => 'ek',
            'दो' => 'do',
            'जो' => 'jo',
            'जब' => 'jab',
            'तब' => 'tab',
            'अब' => 'ab',
        ];

        // Longest keys first so multi-word phrases replace cleanly before single tokens
        uksort($phraseMap, static fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($phraseMap as $hi => $hn) {
            $text = preg_replace('/(?<![\p{Devanagari}])' . preg_quote($hi, '/') . '(?![\p{Devanagari}])/u', $hn, $text) ?? $text;
        }

        // Collect ALL English reference tokens >= 3 chars so transliterated loanwords & proper nouns stay crisp
        $englishProper = [];
        $englishLoanwords = [];
        if ($englishRef !== '') {
            preg_match_all('/\b[A-Z][A-Za-z0-9\-\/]{1,24}\b/', $englishRef, $matches);
            foreach ($matches[0] ?? [] as $tok) {
                $englishProper[strtolower($tok)] = $tok;
            }
            preg_match_all('/\b[A-Za-z][A-Za-z0-9\-]{3,24}\b/', $englishRef, $allMatches);
            foreach ($allMatches[0] ?? [] as $tok) {
                $englishLoanwords[strtolower($tok)] = $tok;
            }
        }

        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $outTokens = [];

        foreach ($tokens as $tok) {
            // Separate punctuation cleanly (include \p{M} so trailing Devanagari matras stay inside $core)
            if (!preg_match('/^([^\p{L}\p{M}\p{N}]*)(.*?)([^\p{L}\p{M}\p{N}]*)$/u', $tok, $m)) {
                $outTokens[] = $tok;
                continue;
            }
            $lead = $m[1];
            $core = $m[2];
            $trail = $m[3];

            if ($core === '') {
                $outTokens[] = $tok;
                continue;
            }

            $wasDevanagari = $this->isDevanagari($core);
            if ($wasDevanagari) {
                $roman = preg_replace_callback(
                    '/[\x{0900}-\x{097F}]+/u',
                    fn ($dm) => $this->transliterateDevanagariCore($dm[0]),
                    $core
                ) ?? $core;
            } else {
                $roman = $core;
            }

            // Restore exact proper noun & loanword spelling from English reference via strict phonetic matching
            $lower = strtolower($roman);
            if (isset($englishProper[$lower])) {
                $roman = $englishProper[$lower];
            } elseif ($wasDevanagari && strlen($lower) >= 4 && !empty($englishLoanwords)) {
                $romanSkel = $this->consonantSkeleton($lower);
                foreach ($englishLoanwords as $engLower => $engOrig) {
                    $engClean = rtrim($engLower, 's');
                    $engSkel = $this->consonantSkeleton($engLower);
                    $engCleanSkel = $this->consonantSkeleton($engClean);
                    similar_text($lower, $engLower, $pct);
                    if (
                        (strlen($romanSkel) >= 5 && ($romanSkel === $engSkel || $romanSkel === $engCleanSkel)) ||
                        ($pct >= 72.0 && abs(strlen($engLower) - strlen($lower)) <= 2)
                    ) {
                        $roman = isset($englishProper[$engLower]) ? $engOrig : strtolower($engOrig);
                        break;
                    }
                }
            }

            $outTokens[] = $lead . $roman . $trail;
        }

        $result = trim(preg_replace('/\s+/u', ' ', implode(' ', $outTokens)) ?? '');
        // Clean repeated spaces before punctuation & capitalize sentence starts
        $result = preg_replace('/\s+([,\.\!\?\:])/', '$1', $result) ?? $result;
        $result = preg_replace_callback('/(?:^|[.!?]\s+)([a-z])/', static fn ($m) => strtoupper($m[0]), $result) ?? $result;

        return trim($result);
    }

    private function consonantSkeleton(string $word): string
    {
        $w = strtolower(preg_replace('/[^a-z]/', '', $word) ?? '');
        $w = str_replace(['chh', 'ch', 'sh', 'ph', 'ck', 'qu', 'oo', 'ee'], ['X', 'X', 'S', 'f', 'k', 'k', 'u', 'i'], $w);
        $w = strtr($w, ['v' => 'w', 'c' => 'k', 'q' => 'k', 'z' => 'j']);
        if ($w === '') {
            return '';
        }
        $first = $w[0];
        $rest = preg_replace('/[aeiouyh]/', '', substr($w, 1)) ?? '';
        return preg_replace('/(.)\1+/', '$1', $first . $rest) ?? ($first . $rest);
    }

    private function transliterateDevanagariCore(string $word): string
    {
        $word = str_replace(['क्ष', 'त्र', 'ज्ञ', 'श्र'], ['ksh', 'tr', 'gy', 'shr'], $word);

        $map = [
            'अ' => 'a', 'आ' => 'aa', 'इ' => 'i', 'ई' => 'i', 'उ' => 'u', 'ऊ' => 'oo',
            'ए' => 'e', 'ऐ' => 'ai', 'ओ' => 'o', 'औ' => 'au', 'ऋ' => 'ri', 'ऑ' => 'o', 'ऍ' => 'e',
            'क' => 'k', 'ख' => 'kh', 'ग' => 'g', 'घ' => 'gh', 'ङ' => 'ng',
            'च' => 'ch', 'छ' => 'chh', 'ज' => 'j', 'झ' => 'jh', 'ञ' => 'ny',
            'ट' => 't', 'ठ' => 'th', 'ड' => 'd', 'ढ' => 'dh', 'ण' => 'n',
            'त' => 't', 'थ' => 'th', 'द' => 'd', 'ध' => 'dh', 'न' => 'n',
            'प' => 'p', 'फ' => 'ph', 'ब' => 'b', 'भ' => 'bh', 'म' => 'm',
            'य' => 'y', 'र' => 'r', 'ल' => 'l', 'व' => 'v', 'श' => 'sh',
            'ष' => 'sh', 'स' => 's', 'ह' => 'h',
            'क़' => 'q', 'ख़' => 'kh', 'ग़' => 'gh', 'ज़' => 'z', 'ड़' => 'd', 'ढ़' => 'dh', 'फ़' => 'f',
            'ा' => 'a', 'ि' => 'i', 'ी' => 'i', 'ु' => 'u', 'ू' => 'oo',
            'े' => 'e', 'ै' => 'ai', 'ो' => 'o', 'ौ' => 'au', 'ृ' => 'ri', 'ॉ' => 'o', 'ॅ' => 'e',
            'ं' => 'n', 'ँ' => 'n', 'ः' => 'h', '्' => '', '़' => '',
        ];

        $consonants = ['क','ख','ग','घ','ङ','च','छ','ज','झ','ञ','ट','ठ','ड','ढ','ण','त','थ','द','ध','न','प','फ','ब','भ','म','य','र','ल','व','श','ष','स','ह','क़','ख़','ग़','ज़','ड़','ढ़','फ़'];
        $vowelsOrMatras = ['ा','ि','ी','ु','ू','े','ै','ो','ौ','ृ','ॉ','ॅ','्','़'];

        $chars = mb_str_split($word);
        $len = count($chars);
        $res = '';

        for ($i = 0; $i < $len; $i++) {
            $c = $chars[$i];
            $next = ($i + 1 < $len) ? $chars[$i + 1] : '';
            $isCons = in_array($c, $consonants, true);
            $nextIsVowelMark = in_array($next, $vowelsOrMatras, true);

            if (isset($map[$c])) {
                $res .= $map[$c];
                // Add inherent 'a' inside word before next consonant or anusvara (e.g. santulan, mana)
                if ($isCons && !$nextIsVowelMark && $i < $len - 1) {
                    $res .= 'a';
                }
            } else {
                $res .= $c;
            }
        }

        return $res;
    }

    private function buildSocialCaption(Article $article, string $title, string $excerpt): string
    {
        $tags = array_filter(array_map('trim', explode(',', (string) ($article->tags ?? ''))));
        $hashtags = ['#Cricket', '#CricApp', '#CricketReels', '#CricketNews'];

        foreach (array_slice($tags, 0, 5) as $tag) {
            $slugTag = preg_replace('/[^A-Za-z0-9]/', '', ucwords($tag));
            if ($slugTag !== '' && strlen($slugTag) <= 28) {
                $hashtags[] = '#' . $slugTag;
            }
        }

        $hashtags = array_values(array_unique($hashtags));
        $summaryLine = $excerpt !== '' ? Str::limit($excerpt, 140) : 'Full analysis & live updates inside.';

        return "{$title}\n\n{$summaryLine}\n\nRead full breakdown on CricApp.in:\n" .
            route('articles.show', $article->slug) .
            "\n\n" . implode(' ', $hashtags);
    }
}
