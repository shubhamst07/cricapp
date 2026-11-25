<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleTranslationService
{
    /**
     * Map of common categories to Hindi and Hinglish.
     */
    protected array $categoryMap = [
        'Match Report' => [
            'hi' => 'मैच रिपोर्ट',
            'hinglish' => 'Match Report',
        ],
        'Tournament News' => [
            'hi' => 'टूर्नामेंट समाचार',
            'hinglish' => 'Tournament News',
        ],
        'Cricket Tips' => [
            'hi' => 'क्रिकेट टिप्स व तकनीक',
            'hinglish' => 'Cricket Tips & Drills',
        ],
        'Local Stories' => [
            'hi' => 'स्थानीय क्रिकेट कहानियाँ',
            'hinglish' => 'Grassroots & Local Stories',
        ],
        'Opinion & Analysis' => [
            'hi' => 'विशेषज्ञ राय व विश्लेषण',
            'hinglish' => 'Opinion & Tactical Analysis',
        ],
        'General' => [
            'hi' => 'सामान्य क्रिकेट ख़बरें',
            'hinglish' => 'General Cricket News',
        ],
    ];

    /**
     * Common cricket terms and proper nouns mapped from Devanagari to authentic cricket Hinglish.
     */
    protected array $cricketTermsHinglish = [
        'दक्षिण अफ्रीका' => 'South Africa',
        'ऑस्ट्रेलिया' => 'Australia',
        'न्यूजीलैंड' => 'New Zealand',
        'इंग्लैंड' => 'England',
        'वेस्टइंडीज' => 'West Indies',
        'पाकिस्तान' => 'Pakistan',
        'बांग्लादेश' => 'Bangladesh',
        'श्रीलंका' => 'Sri Lanka',
        'अफगानिस्तान' => 'Afghanistan',
        'भारत' => 'India',
        'केप टाउन' => 'Cape Town',
        'न्यूलैंड्स' => 'Newlands',
        'मेलबर्न' => 'Melbourne',
        'सिडनी' => 'Sydney',
        'लॉर्ड्स' => 'Lords',
        'ओवल' => 'Oval',
        'वानखेड़े' => 'Wankhede',
        'ईडन गार्डन्स' => 'Eden Gardens',
        'धर्मशाला' => 'Dharamshala',
        'अहमदाबाद' => 'Ahmedabad',
        'टेस्ट मैच' => 'Test match',
        'टेस्ट' => 'Test',
        'क्रिकेट' => 'cricket',
        'सैंडपेपर' => 'sandpaper',
        'घोटाला' => 'scandal',
        'धोखाधड़ी' => 'cheating scandal',
        'कप्तान' => 'captain',
        'उपकप्तान' => 'vice-captain',
        'विकेट' => 'wicket',
        'गेंदबाज़' => 'bowler',
        'गेंदबाज' => 'bowler',
        'बल्लेबाज़' => 'batsman',
        'बल्लेबाज' => 'batsman',
        'गेंदबाजी' => 'bowling',
        'बल्लेबाजी' => 'batting',
        'फील्डिंग' => 'fielding',
        'अंपायर' => 'umpire',
        'अंपायरों' => 'umpires',
        'पिच' => 'pitch',
        'ओवर' => 'over',
        'ओवरों' => 'overs',
        'रन' => 'run',
        'रनों' => 'runs',
        'चौका' => 'four',
        'चौके' => 'fours',
        'छक्का' => 'six',
        'छक्के' => 'sixes',
        'मैच' => 'match',
        'टीम' => 'team',
        'सीरीज' => 'series',
        'शृंखला' => 'series',
        'वर्ल्ड कप' => 'World Cup',
        'विश्व कप' => 'World Cup',
        'आईपीएल' => 'IPL',
        'पवेलियन' => 'pavilion',
        'ड्रेसिंग रूम' => 'dressing room',
        'कैमरा' => 'camera',
        'कैमरों' => 'cameras',
        'रिवर्स स्विंग' => 'reverse swing',
        'स्विंग' => 'swing',
        'बाउंड्री' => 'boundary',
        'प्रेस कॉन्फ्रेंस' => 'press conference',
        'प्रतिबंध' => 'ban',
        'सस्पेंड' => 'suspend',
        'बॉल टेंपरिंग' => 'ball tampering',
    ];

    /**
     * Phonetic map for Devanagari transliteration into Roman script.
     */
    protected array $devanagariPhonetic = [
        'अ' => 'a', 'आ' => 'aa', 'इ' => 'i', 'ई' => 'i', 'उ' => 'u', 'ऊ' => 'oo',
        'ए' => 'e', 'ऐ' => 'ai', 'ओ' => 'o', 'औ' => 'au', 'ऋ' => 'ri',
        'क' => 'k', 'ख' => 'kh', 'ग' => 'g', 'घ' => 'gh', 'ङ' => 'ng',
        'च' => 'ch', 'छ' => 'chh', 'ज' => 'j', 'झ' => 'jh', 'ञ' => 'ny',
        'ट' => 't', 'ठ' => 'th', 'ड' => 'd', 'ढ' => 'dh', 'ण' => 'n',
        'त' => 't', 'थ' => 'th', 'द' => 'd', 'ध' => 'dh', 'न' => 'n',
        'प' => 'p', 'फ' => 'ph', 'ब' => 'b', 'भ' => 'bh', 'म' => 'm',
        'य' => 'y', 'र' => 'r', 'ल' => 'l', 'व' => 'v', 'श' => 'sh',
        'ष' => 'sh', 'स' => 's', 'ह' => 'h',
        'क़' => 'q', 'ख़' => 'kh', 'ग़' => 'gh', 'ज़' => 'z', 'ड़' => 'r', 'ढ़' => 'rh', 'फ़' => 'f',
        'ा' => 'a', 'ि' => 'i', 'ी' => 'i', 'ु' => 'u', 'ू' => 'oo',
        'े' => 'e', 'ै' => 'ai', 'ो' => 'o', 'ौ' => 'au', 'ृ' => 'ri',
        'ं' => 'n', 'ँ' => 'n', 'ः' => 'h', '्' => '', '़' => '',
        '।' => '.', '॥' => '.'
    ];

    /**
     * Check if a string contains authentic Hindi Devanagari characters.
     */
    public function isDevanagari(?string $text): bool
    {
        return !empty($text) && (bool) preg_match('/[\x{0900}-\x{097F}]/u', (string) $text);
    }

    /**
     * Get or build full 3-language translations for an article.
     * Guaranteed to return complete sets for 'en', 'hi', and 'hinglish'.
     */
    public function getTranslations(Article $article): array
    {
        // 1. English (Original)
        $en = [
            'title' => $article->title,
            'category' => $article->category,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
        ];

        // Check if Hindi & Hinglish are already cached or in the database with actual Devanagari
        $hasHi = $this->isDevanagari($article->title_hi) && $this->isDevanagari($article->content_hi);
        $hasHinglish = !empty($article->title_hinglish) 
            && !empty($article->content_hinglish)
            && ($article->title_hinglish !== $article->title);

        if ($hasHi && $hasHinglish) {
            return [
                'en' => $en,
                'hi' => [
                    'title' => $article->title_hi,
                    'category' => $article->category_hi ?: ($this->categoryMap[$article->category]['hi'] ?? $article->category),
                    'excerpt' => $article->excerpt_hi ?: $article->excerpt,
                    'content' => $article->content_hi,
                ],
                'hinglish' => [
                    'title' => $article->title_hinglish,
                    'category' => $article->category_hinglish ?: ($this->categoryMap[$article->category]['hinglish'] ?? $article->category),
                    'excerpt' => $article->excerpt_hinglish ?: $article->excerpt,
                    'content' => $article->content_hinglish,
                ],
            ];
        }

        // Generate missing translations
        $categoryHi = $this->categoryMap[$article->category]['hi'] ?? $article->category;
        $categoryHinglish = $this->categoryMap[$article->category]['hinglish'] ?? $article->category;

        // Title translations (Ensure real Devanagari, never fall back to English)
        $titleHi = $this->isDevanagari($article->title_hi) 
            ? $article->title_hi 
            : $this->translateToHindi($article->title);

        $titleHinglish = (!empty($article->title_hinglish) && $article->title_hinglish !== $article->title)
            ? $article->title_hinglish 
            : $this->devanagariToHinglish($titleHi);

        // Excerpt translations
        $excerptHi = $this->isDevanagari($article->excerpt_hi) 
            ? $article->excerpt_hi 
            : (!empty($article->excerpt) ? $this->translateToHindi($article->excerpt) : '');

        $excerptHinglish = (!empty($article->excerpt_hinglish) && $article->excerpt_hinglish !== $article->excerpt)
            ? $article->excerpt_hinglish 
            : $this->devanagariToHinglish($excerptHi);

        // Content translations (Batched markdown translation)
        $contentHi = $this->isDevanagari($article->content_hi) 
            ? $article->content_hi 
            : $this->translateMarkdownContent($article->content, 'hi');

        $contentHinglish = (!empty($article->content_hinglish) && $article->content_hinglish !== $article->content)
            ? $article->content_hinglish 
            : $this->devanagariToHinglishContent($contentHi);

        // Only persist to database if we have authentic Devanagari Hindi
        if ($this->isDevanagari($titleHi) || $this->isDevanagari($contentHi)) {
            try {
                $article->update([
                    'title_hi' => $titleHi,
                    'title_hinglish' => $titleHinglish,
                    'category_hi' => $categoryHi,
                    'category_hinglish' => $categoryHinglish,
                    'excerpt_hi' => $excerptHi,
                    'excerpt_hinglish' => $excerptHinglish,
                    'content_hi' => $contentHi,
                    'content_hinglish' => $contentHinglish,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to persist article translations: ' . $e->getMessage());
            }
        }

        return [
            'en' => $en,
            'hi' => [
                'title' => $titleHi,
                'category' => $categoryHi,
                'excerpt' => $excerptHi ?: $article->excerpt,
                'content' => $contentHi,
            ],
            'hinglish' => [
                'title' => $titleHinglish,
                'category' => $categoryHinglish,
                'excerpt' => $excerptHinglish ?: $article->excerpt,
                'content' => $contentHinglish,
            ],
        ];
    }

    /**
     * Translate markdown formatted content using batching to avoid API bottlenecks.
     */
    public function translateMarkdownContent(string $content, string $target = 'hi'): string
    {
        $paragraphs = explode("\n\n", $content);
        $translatedParagraphs = [];
        $batch = [];
        $batchLen = 0;

        foreach ($paragraphs as $para) {
            $trimmed = trim($para);
            if (empty($trimmed)) {
                continue;
            }

            if ($batchLen + mb_strlen($trimmed) > 400 && !empty($batch)) {
                $joined = implode("\n\n", $batch);
                $translatedParagraphs[] = $this->translateToHindi($joined);
                $batch = [$trimmed];
                $batchLen = mb_strlen($trimmed);
            } else {
                $batch[] = $trimmed;
                $batchLen += mb_strlen($trimmed);
            }
        }

        if (!empty($batch)) {
            $joined = implode("\n\n", $batch);
            $translatedParagraphs[] = $this->translateToHindi($joined);
        }

        return implode("\n\n", $translatedParagraphs);
    }

    /**
     * Convert full Hindi markdown content into authentic cricket Hinglish.
     */
    public function devanagariToHinglishContent(string $contentHi): string
    {
        $lines = explode("\n", $contentHi);
        $hinglishLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $hinglishLines[] = '';
                continue;
            }

            if (str_starts_with($trimmed, '### ')) {
                $hinglishLines[] = '### ' . $this->devanagariToHinglish(substr($trimmed, 4));
                continue;
            }
            if (str_starts_with($trimmed, '## ')) {
                $hinglishLines[] = '## ' . $this->devanagariToHinglish(substr($trimmed, 3));
                continue;
            }
            if (str_starts_with($trimmed, '# ')) {
                $hinglishLines[] = '# ' . $this->devanagariToHinglish(substr($trimmed, 2));
                continue;
            }
            if (str_starts_with($trimmed, '- ')) {
                $hinglishLines[] = '- ' . $this->devanagariToHinglish(substr($trimmed, 2));
                continue;
            }

            $hinglishLines[] = $this->devanagariToHinglish($trimmed);
        }

        return implode("\n", $hinglishLines);
    }

    /**
     * Translate text from English to Hindi using MyMemory & Google Translate API with caching.
     */
    public function translateToHindi(string $text): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }

        // If already Devanagari Hindi, return as is
        if ($this->isDevanagari($text)) {
            return $text;
        }

        $cacheKey = 'art_trans_hi_v5_' . md5($text);

        try {
            $cached = Cache::get($cacheKey);
            if (!empty($cached) && $this->isDevanagari($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {}

        $result = '';
        if (mb_strlen($text) > 300) {
            $sentences = preg_split('/(?<=[.!?\n])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $translatedSentences = [];
            foreach ($sentences as $sentence) {
                $translatedSentences[] = $this->translateChunkToHindi($sentence);
            }
            $result = implode(' ', $translatedSentences);
        } else {
            $result = $this->translateChunkToHindi($text);
        }

        // ONLY CACHE IF IT ACTUALLY CONTAINS HINDI DEVANAGARI!
        if ($this->isDevanagari($result)) {
            try {
                Cache::put($cacheKey, $result, 86400 * 30);
            } catch (\Throwable $e) {}
        }

        return $result;
    }

    /**
     * Translate a short sentence/chunk into Hindi using MyMemory with fallbacks.
     */
    protected function translateChunkToHindi(string $text): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return $text;
        }

        // 1. Try MyMemory API (Fast, reliable from servers)
        try {
            $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=en|hi';
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'CricAppNews/2.0 (Cricket Portal)',
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $json = $response->json();
                $res = $json['responseData']['translatedText'] ?? null;
                if (!empty($res)) {
                    $clean = html_entity_decode(trim($res), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (preg_match('/[\x{0900}-\x{097F}]/u', $clean)) {
                        return $clean;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MyMemory translation error: ' . $e->getMessage());
        }

        // 2. Fallback to Google Translate if available
        try {
            $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=hi&dt=t&q=' . urlencode($text);
            $response = Http::timeout(3)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => '*/*',
                ])
                ->get($url);

            if ($response->successful()) {
                $arr = $response->json();
                if (is_array($arr) && isset($arr[0]) && is_array($arr[0])) {
                    $translated = '';
                    foreach ($arr[0] as $part) {
                        $translated .= $part[0] ?? '';
                    }
                    $translated = trim($translated);
                    if (!empty($translated) && preg_match('/[\x{0900}-\x{097F}]/u', $translated)) {
                        return $translated;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $text;
    }

    /**
     * Transliterate Devanagari Hindi into fluent Cricket Hinglish (Roman Hindi).
     */
    public function devanagariToHinglish(string $text): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }

        // 1. Substitute high-frequency cricket terms and countries
        foreach ($this->cricketTermsHinglish as $hi => $hn) {
            $text = str_replace($hi, $hn, $text);
        }

        $words = explode(' ', $text);
        $outputWords = [];

        $consonants = ['क','ख','ग','घ','ङ','च','छ','ज','झ','ञ','ट','ठ','ड','ढ','ण','त','थ','द','ध','न','प','फ','ब','भ','म','य','र','ल','व','श','ष','स','ह','क़','ख़','ग़','ज़','ड़','ढ़','फ़'];
        $matras = ['ा','ि','ी','ु','ू','े','ै','ो','ौ','ृ','्'];

        foreach ($words as $word) {
            // If already Roman or symbols, keep intact
            if (preg_match('/^[a-zA-Z0-9_\-\.\,\:\!\?\"\'\(\)\/\#]+$/', $word)) {
                $outputWords[] = $word;
                continue;
            }

            $chars = mb_str_split($word);
            $len = count($chars);
            $res = '';

            for ($i = 0; $i < $len; $i++) {
                $c = $chars[$i];
                $next = ($i + 1 < $len) ? $chars[$i + 1] : '';

                $isConsonant = in_array($c, $consonants, true);
                $nextIsMatra = in_array($next, $matras, true);

                if (isset($this->devanagariPhonetic[$c])) {
                    $res .= $this->devanagariPhonetic[$c];
                    // Schwa addition: consonant not followed by matra/halant and not at the end of word
                    if ($isConsonant && !$nextIsMatra && $i < $len - 1 && !in_array($next, ['ं', 'ँ', '.', ',', '!', '?'], true)) {
                        $res .= 'a';
                    }
                } else {
                    $res .= $c;
                }
            }

            $res = preg_replace('/aa$/', 'a', $res);
            $outputWords[] = $res;
        }

        $out = implode(' ', $outputWords);

        // 2. Hinglish natural spelling corrections
        $cleanups = [
            'men' => 'mein',
            'aur' => 'aur',
            'se' => 'se',
            'ko' => 'ko',
            'ka' => 'ka',
            'ki' => 'ki',
            'ke' => 'ke',
            'kisi' => 'kisi',
            'ek' => 'ek',
            'gaya' => 'gaya',
            'gaye' => 'gaye',
            'gayi' => 'gayi',
            'raha' => 'raha',
            'rahe' => 'rahe',
            'rahi' => 'rahi',
            'hai' => 'hai',
            'hain' => 'hain',
            'tha' => 'tha',
            'the' => 'the',
            'thi' => 'thi',
            'hota' => 'hota',
            'hote' => 'hote',
            'hoti' => 'hoti',
            'baad' => 'baad',
            'pehle' => 'pehle',
            'shuru' => 'shuru',
            'sabase' => 'sabse',
            'tukadae' => 'tukde',
            'chhote' => 'chhote',
            'dauraan' => 'dauran',
        ];

        foreach ($cleanups as $k => $v) {
            $out = preg_replace('/\b' . preg_quote($k, '/') . '\b/i', $v, $out);
        }

        return trim(preg_replace('/\s+/', ' ', $out));
    }
}
