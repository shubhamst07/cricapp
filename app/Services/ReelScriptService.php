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

        $slides = [];
        $slides[] = [
            'badge' => Str::limit($category, 26, ''),
            'headline' => Str::limit($title, 82),
            'subtext' => $hookSub,
            'narration' => $slide1Hi !== '' ? $slide1Hi : $slide1En,
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

            $slides[] = [
                'badge' => Str::limit(strtoupper($sec['badge'] ?: 'KEY POINT ' . ($idx + 1)), 26, ''),
                'headline' => Str::limit($sec['headline'], 78),
                'subtext' => Str::limit($sec['subtext'], 110),
                'narration' => $hiSpeech !== '' ? $hiSpeech : $enSpeech,
                'narration_en' => $enSpeech,
                'image' => $bgImage,
                'duration' => 5.5,
            ];
        }

        $slides[] = [
            'badge' => 'FOLLOW @CRICAPP.IN',
            'headline' => 'Full Story & Live Scores',
            'subtext' => 'Search CricApp.in on Google for ball-by-ball commentary & squad analysis.',
            'narration' => 'पूरी स्टोरी और बॉल-बाय-बॉल लाइव अपडेट्स अभी पढ़ें, CricApp dot in पर।',
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
                    'narration' => 'बड़ा क्रिकेट अपडेट! जानिए आज के रोमांचक मैच के उन तीन पलों के बारे में जिन्होंने पूरा खेल पलट दिया।',
                    'image' => $curated[0],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'MOMENT 1',
                    'headline' => 'Powerplay Assault Stuns The Opposition',
                    'subtext' => 'Clean hitting right from over one put instant scoreboard pressure.',
                    'narration' => 'सबसे पहले पावरप्ले में तूफानी शुरुआत ने विपक्षी टीम पर तुरंत बड़ा दबाव बना दिया।',
                    'image' => $curated[1],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'MOMENT 2',
                    'headline' => 'Death-Overs Spell Seals The Contest',
                    'subtext' => 'Pinpoint yorkers and clever slower balls defended the target.',
                    'narration' => 'इसके बाद डेथ ओवर्स की सटीक यॉर्कर गेंदबाजी ने आखिरी पलों में जीत पक्की कर दी।',
                    'image' => $curated[2],
                    'duration' => 5.0,
                ],
                [
                    'badge' => 'LIVE ON CRICAPP.IN',
                    'headline' => 'Ball-by-Ball Scores & Reports',
                    'subtext' => 'Search CricApp.in on Google for full scorecards & match news.',
                    'narration' => 'सबसे तेज़ लाइव क्रिकेट स्कोर और मैच रिपोर्ट्स के लिए अभी जुड़ें, CricApp dot in पर।',
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
            $narration = trim(($headText !== '' ? $headText . '. ' : '') . $firstSentence . ' ' . $secondSentence);

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
        // 1. Google clients5 endpoint (fast compact JSON)
        try {
            $resp = Http::timeout(3)
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

        // 2. MyMemory API (reliable from cloud/Render AWS server IPs)
        try {
            $snippet = mb_substr($text, 0, 480);
            $resp = Http::timeout(4)
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
            // Try next provider
        }

        // 3. Google Translate GTX endpoint
        try {
            $resp = Http::timeout(3)
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
            // Ignore
        }

        return '';
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
