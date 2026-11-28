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
                'label' => 'Pitch & Bat Action',
                'url' => 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=80',
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
            [
                'label' => 'Championship Arena',
                'url' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1200&q=80',
            ],
        ];
    }

    /**
     * Resolve a live, loadable cover URL without falling back to stale local files or dead stock URLs.
     */
    private function resolveSafeCoverUrl(Article $article, array $curated): string
    {
        $coverUrl = trim((string) $article->cover_image_url);
        $deadStockUrls = [
            'photo-1531415074868-036b1c57e3ce',
        ];
        foreach ($deadStockUrls as $dead) {
            if ($coverUrl !== '' && str_contains($coverUrl, $dead)) {
                return $curated[1] ?? $curated[0] ?? $coverUrl;
            }
        }
        if (Str::startsWith($coverUrl, '/') && !is_file(public_path(ltrim($coverUrl, '/')))) {
            return $curated[0] ?? $coverUrl;
        }
        return $coverUrl !== '' ? $coverUrl : ($curated[0] ?? '');
    }

    /**
     * Build non-repeating background URLs across slides that rotate cleanly on every refresh.
     */
    private function pickRotatedBackgrounds(int $count, int $variant, string $primaryImage, array $excludeImages = []): array
    {
        $curated = array_column($this->curatedImages(), 'url');
        $pool = array_values(array_unique(array_filter(array_merge([$primaryImage], $curated))));
        if (empty($pool)) {
            return array_fill(0, $count, '');
        }

        if ($variant === 0) {
            $chosen = [];
            for ($i = 0; $i < $count; $i++) {
                if ($i === 0) {
                    $chosen[] = $primaryImage ?: $pool[0];
                } elseif ($i === $count - 1) {
                    $chosen[] = ($curated[0] ?? $pool[0]) !== ($primaryImage ?: '')
                        ? ($curated[0] ?? $pool[0])
                        : ($curated[1] ?? $pool[0]);
                } else {
                    $chosen[] = $curated[($i + 1) % max(1, count($curated))] ?? $pool[0];
                }
            }
            return $chosen;
        }

        $poolCount = count($pool);
        $offset = ($variant * 2 + 1) % $poolCount;
        $rotated = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));

        $chosen = [];
        $prevUsed = '';
        for ($i = 0; $i < $count; $i++) {
            $excluded = trim((string) ($excludeImages[$i] ?? ''));
            $pick = '';
            foreach ($rotated as $candidate) {
                if ($candidate !== $excluded && $candidate !== $prevUsed && !in_array($candidate, $chosen, true)) {
                    $pick = $candidate;
                    break;
                }
            }
            if ($pick === '') {
                foreach ($rotated as $candidate) {
                    if ($candidate !== $excluded && $candidate !== $prevUsed) {
                        $pick = $candidate;
                        break;
                    }
                }
            }
            if ($pick === '') {
                $pick = $rotated[$i % $poolCount];
            }
            $chosen[] = $pick;
            $prevUsed = $pick;
        }

        return $chosen;
    }

    /**
     * Build aligned English / Hindi / Hinglish narration fields for a single slide.
     */
    private function buildSlideNarrationPair(
        string $enRaw,
        string $hiHint = '',
        bool $wantsEnglish = false,
        string $hinglishHint = ''
    ): array {
        $enSpeech = $this->toSpeechSentence($enRaw);
        $hiSpeech = ($hiHint !== '' && $this->isMostlyHindi($hiHint))
            ? $this->toSpeechSentence($hiHint)
            : $this->translateToHindiFast($enSpeech);
        $hasHi = $this->isMostlyHindi($hiSpeech);

        $cleanHinglishHint = $hinglishHint !== '' ? $this->toSpeechSentence($hinglishHint) : '';
        if ($cleanHinglishHint !== '' && !$this->isDevanagari($cleanHinglishHint)) {
            $hinglish = $cleanHinglishHint;
        } else {
            $hinglish = $hasHi ? $this->hindiToHinglish($hiSpeech, $enSpeech) : '';
        }

        return [
            'narration' => $wantsEnglish
                ? $enSpeech
                : ($hinglish !== '' ? $hinglish : $enSpeech),
            'narration_hinglish' => $hinglish,
            'narration_hi' => $hasHi ? $hiSpeech : '',
            'narration_en' => $enSpeech,
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
     * Passing $variant > 0 generates fresh narrative hooks, rotated points & new backgrounds.
     */
    public function buildFromArticle(
        Article $article,
        int $variant = 0,
        string $voice = 'hi-IN-MadhurNeural',
        array $excludeImages = []
    ): array {
        $variant = max(0, $variant);
        $angle = $variant % 4;
        $wantsEnglish = Str::startsWith($voice, 'en-');

        $title = $this->cleanText($article->title ?? 'Latest Cricket Update');
        $excerpt = $this->cleanText($article->excerpt ?? '');
        $category = strtoupper($this->cleanText($article->category ?: 'CRICKET UPDATE'));
        $curated = array_column($this->curatedImages(), 'url');
        $coverUrl = $this->resolveSafeCoverUrl($article, $curated);

        $allSections = $this->extractSections((string) ($article->content ?? ''));
        $sectionsHi = $this->isMostlyHindi((string) ($article->content_hi ?? ''))
            ? $this->extractSections((string) $article->content_hi)
            : [];
        $rawHinglishContent = trim((string) ($article->content_hinglish ?? ''));
        $sectionsHinglish = ($rawHinglishContent !== '' && !$this->isDevanagari($rawHinglishContent))
            ? $this->extractSections($rawHinglishContent)
            : [];
        $hasAlignedHinglish = !empty($sectionsHinglish) && count($sectionsHinglish) === count($allSections);

        // Select middle sections differently per angle so repeated refreshes surface fresh talking points
        if (empty($allSections)) {
            $fallbackText = $excerpt !== '' ? $excerpt : $title;
            $fallbackSpeech = $this->toSpeechSentence($fallbackText, 240);
            $allSections = [[
                'badge' => 'THE BIG STORY',
                'headline' => $this->smartTrimText($fallbackText, 76, ''),
                'subtext' => 'Tap the link in bio or search CricApp.in for ball-by-ball details.',
                'narration' => $fallbackSpeech,
                'alt_headline' => $this->smartTrimText($title, 76, ''),
                'alt_subtext' => 'Complete match breakdown and tactical details inside.',
                'alt_narration' => $fallbackSpeech,
                'sentences' => [$fallbackText],
            ]];
        }

        $totalSecs = count($allSections);
        if ($angle === 0 || $totalSecs <= 1) {
            $middleIndices = range(0, min(2, $totalSecs - 1));
        } elseif ($angle === 1) {
            $shifted = array_map(static fn (int $i) => ($i + 1) % $totalSecs, range(0, $totalSecs - 1));
            $middleIndices = array_slice($shifted, 0, min(3, $totalSecs));
        } elseif ($angle === 2) {
            $oddFirst = array_values(array_merge(
                array_filter(range(0, $totalSecs - 1), static fn ($i) => $i % 2 === 1),
                array_filter(range(0, $totalSecs - 1), static fn ($i) => $i % 2 === 0)
            ));
            $middleIndices = array_slice($oddFirst, 0, min(3, $totalSecs));
        } else {
            $middleIndices = array_slice(array_reverse(range(0, $totalSecs - 1)), 0, min(3, $totalSecs));
        }

        $hookSub = $excerpt !== ''
            ? $this->smartTrimText($excerpt, 115)
            : ($allSections[0]['headline'] ?? 'Full breakdown inside.');

        $titleHi = $this->isMostlyHindi((string) ($article->title_hi ?? ''))
            ? $this->cleanText((string) $article->title_hi)
            : '';
        $excerptHi = $this->isMostlyHindi((string) ($article->excerpt_hi ?? ''))
            ? $this->cleanText((string) $article->excerpt_hi)
            : '';
        $titleHinglish = $this->cleanText((string) ($article->title_hinglish ?? ''));
        $excerptHinglish = $this->cleanText((string) ($article->excerpt_hinglish ?? ''));

        $slide1HinglishHint = '';
        if ($angle === 0) {
            $slide1Badge = $this->smartTrimText($category, 26, '');
            $slide1Headline = $this->smartTrimText($title, 82);
            $slide1Sub = $hookSub;
            $titleEnd = preg_match('/[.!?।]$/u', $title) ? ' ' : '. ';
            $slide1EnRaw = $title . ($excerpt !== '' ? $titleEnd . $excerpt : '');
            $titleHiEnd = preg_match('/[.!?।]$/u', $titleHi) ? ' ' : '। ';
            $slide1HiHint = $titleHi !== '' ? ($titleHi . ($excerptHi !== '' ? $titleHiEnd . $excerptHi : '')) : '';
            if ($titleHinglish !== '' && !$this->isDevanagari($titleHinglish)) {
                $titleHnEnd = preg_match('/[.!?।]$/u', $titleHinglish) ? ' ' : '. ';
                $slide1HinglishHint = $titleHinglish . ($excerptHinglish !== '' ? $titleHnEnd . $excerptHinglish : '');
            }
        } elseif ($angle === 1) {
            $leadSec = $allSections[$middleIndices[0]] ?? $allSections[0];
            $slide1Badge = 'WHY THIS MATTERS';
            $slide1Headline = $this->smartTrimText('Wait Till You Know This: ' . ($leadSec['headline'] ?? $title), 82);
            $slide1Sub = $this->smartTrimText($hookSub, 115);
            $slide1EnRaw = 'Ever wondered why fans talk about ' . $this->smartTrimText($title, 70, '') . '? ' . ($leadSec['subtext'] ?? $hookSub);
            $slide1HiHint = '';
        } elseif ($angle === 2) {
            $slide1Badge = 'CRICKET INSIGHT';
            $slide1Headline = $this->smartTrimText('Quick Breakdown: ' . $title, 82);
            $slide1Sub = $this->smartTrimText(($allSections[$middleIndices[0]]['subtext'] ?? $hookSub), 115);
            $slide1EnRaw = 'Here is the rapid 30 second cricket breakdown. ' . ($excerpt !== '' ? $excerpt : $title);
            $slide1HiHint = '';
        } else {
            $lastSec = $allSections[$totalSecs - 1];
            $slide1Badge = 'MUST WATCH 🔥';
            $slide1Headline = $this->smartTrimText($lastSec['headline'] ?? $title, 82);
            $slide1Sub = $this->smartTrimText('Real reason behind ' . $this->smartTrimText($title, 70, ''), 115);
            $slide1EnRaw = 'The biggest turning point in ' . $this->smartTrimText($title, 70, '') . '. ' . ($lastSec['subtext'] ?? $hookSub);
            $slide1HiHint = '';
        }

        $slide1Voice = $this->buildSlideNarrationPair($slide1EnRaw, $slide1HiHint, $wantsEnglish, $slide1HinglishHint);
        $slides = [];
        $slides[] = array_merge([
            'badge' => $slide1Badge,
            'headline' => $slide1Headline,
            'subtext' => $slide1Sub,
            'image' => $coverUrl,
            'duration' => 5.5,
        ], $slide1Voice);

        foreach ($middleIndices as $pos => $secIdx) {
            $sec = $allSections[$secIdx];
            $sentences = $sec['sentences'] ?? [];
            $hinglishHint = '';

            if ($angle === 0) {
                $rawBadge = trim((string) ($sec['badge'] ?? ''));
                $badgeLabel = ($rawBadge !== '' && mb_strlen($rawBadge, 'UTF-8') <= 24)
                    ? $rawBadge
                    : 'KEY POINT ' . ($pos + 1);
                $headText = $sec['headline'];
                $subText = $sec['subtext'];
                $enRaw = $sec['narration'];
                $hiHint = $sectionsHi[$secIdx]['narration'] ?? '';
                if ($hasAlignedHinglish) {
                    $hinglishHint = $sectionsHinglish[$secIdx]['narration'] ?? '';
                }
            } elseif ($angle === 1) {
                $badgeLabel = 'DETAIL ' . ($pos + 1);
                $detailLine = $sentences[1] ?? $sentences[0] ?? $sec['subtext'];
                $headText = $sec['alt_headline'] ?? $sec['headline'];
                $subText = $detailLine;
                $enRaw = 'Point ' . ($pos + 1) . ': ' . ($sec['alt_narration'] ?? $detailLine);
                $hiHint = '';
            } elseif ($angle === 2) {
                $badgeLabel = 'TAKEAWAY #' . ($pos + 1);
                $punchLine = $sentences[0] ?? $sec['subtext'];
                $secondLine = $sentences[1] ?? '';
                $headText = $sec['headline'];
                $subText = $secondLine !== '' ? $secondLine : $punchLine;
                $enRaw = trim($punchLine . ' ' . $secondLine);
                $hiHint = '';
            } else {
                $badgeLabel = $pos === 0 ? 'THE SECRET' : 'KEY PROOF ' . ($pos + 1);
                $headText = $sec['headline'];
                $subText = $sentences[count($sentences) - 1] ?? $sec['subtext'];
                $enRaw = ($sentences[count($sentences) - 1] ?? $sec['narration']) . ' ' . ($sentences[0] ?? '');
                $hiHint = '';
            }

            $voiceFields = $this->buildSlideNarrationPair($enRaw, $hiHint, $wantsEnglish, $hinglishHint);
            $slides[] = array_merge([
                'badge' => $this->smartTrimText(strtoupper($badgeLabel), 24, ''),
                'headline' => $this->smartTrimText($headText, 78),
                'subtext' => $this->smartTrimText($subText, 115),
                'image' => $coverUrl,
                'duration' => 5.5,
            ], $voiceFields);
        }

        $closingCtas = [
            0 => [
                'badge' => 'FOLLOW @CRICAPP.IN',
                'headline' => 'Full Story & Live Scores',
                'subtext' => 'Search CricApp.in on Google for ball-by-ball commentary & squad analysis.',
                'en' => 'Read the full story and ball by ball live updates now on CricApp dot in.',
                'hi' => 'पूरी स्टोरी और बॉल-बाय-बॉल लाइव अपडेट्स अभी पढ़ें, CricApp dot in पर।',
            ],
            1 => [
                'badge' => 'DEEP ANALYSIS',
                'headline' => 'Read Complete Guide Now',
                'subtext' => 'Get match reports, pitch breakdowns & fastest live scores on CricApp.in.',
                'en' => 'Get the full tactical breakdown and live match scores on CricApp dot in.',
                'hi' => 'पूरा टैक्टिकल विश्लेषण और लाइव मैच स्कोर अभी देखें, CricApp dot in पर।',
            ],
            2 => [
                'badge' => 'CRICKET FANS',
                'headline' => 'Catch Every Ball Live',
                'subtext' => 'Follow live scorecards, cricket tips & breaking updates at CricApp.in.',
                'en' => 'Catch every ball live with fast scorecards and cricket tips on CricApp dot in.',
                'hi' => 'हर बॉल की लाइव अपडेट और तेज़ स्कोरकार्ड के लिए अभी जुड़ें, CricApp dot in पर।',
            ],
            3 => [
                'badge' => 'YOUR VERDICT?',
                'headline' => 'Agree Or Disagree?',
                'subtext' => 'Comment your opinion below & visit CricApp.in for live match scorecards.',
                'en' => 'Share your opinion in comments and visit CricApp dot in for live scorecards.',
                'hi' => 'कमेंट्स में अपनी राय बताएं और लाइव स्कोरकार्ड के लिए CricApp dot in पर जाएं।',
            ],
        ];
        $cta = $closingCtas[$angle];
        $ctaVoice = $this->buildSlideNarrationPair($cta['en'], $cta['hi'], $wantsEnglish);
        $slides[] = array_merge([
            'badge' => $cta['badge'],
            'headline' => $cta['headline'],
            'subtext' => $cta['subtext'],
            'image' => $coverUrl,
            'duration' => 4.5,
        ], $ctaVoice);

        $bgUrls = $this->pickRotatedBackgrounds(count($slides), $variant, $coverUrl, $excludeImages);
        foreach ($slides as $sIdx => &$sItem) {
            $sItem['image'] = $bgUrls[$sIdx] ?? $coverUrl;
        }
        unset($sItem);

        $themes = ['stadium', 'crimson', 'pitch'];

        return [
            'source_article_id' => $article->id,
            'variant' => $variant,
            'title' => $title,
            'theme' => $themes[$variant % count($themes)],
            'voice' => $voice,
            'slides' => $slides,
            'social_caption' => $this->buildSocialCaption($article, $title, $excerpt),
            'article_url' => route('articles.show', $article->slug),
        ];
    }

    /**
     * Build a blank starter project when admin opens Reel Studio directly or clicks Refresh.
     */
    public function buildDefaultBlank(
        int $variant = 0,
        string $voice = 'hi-IN-MadhurNeural',
        array $excludeImages = []
    ): array {
        $variant = max(0, $variant);
        $wantsEnglish = Str::startsWith($voice, 'en-');
        $curated = array_column($this->curatedImages(), 'url');

        $templates = [
            [
                'title' => 'Breaking Cricket Reel',
                'caption' => "Latest Cricket Update! Live scores & full breakdown on CricApp.in\n\n#Cricket #CricApp #CricketReels #IPL #TeamIndia #CricketNews",
                'slides' => [
                    [
                        'badge' => 'BREAKING CRICKET',
                        'headline' => 'Massive Turning Point In Today\'s Thriller!',
                        'subtext' => 'Watch the top 3 moments that changed the match completely.',
                        'hinglish' => 'Bada cricket update! Janiye aaj ke thrilling match ke un 3 moments ke baare mein jinhone poora khel palat diya.',
                        'hi' => 'बड़ा क्रिकेट अपडेट! जानिए आज के रोमांचक मैच के उन तीन पलों के बारे में जिन्होंने पूरा खेल पलट दिया।',
                        'en' => 'Big cricket update! Here are the 3 moments that completely turned today\'s thriller around.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'MOMENT 1',
                        'headline' => 'Powerplay Assault Stuns The Opposition',
                        'subtext' => 'Clean hitting right from over one put instant scoreboard pressure.',
                        'hinglish' => 'Sabse pehle powerplay mein toofani batting ne vipakshi team par turant bada dabav bana diya.',
                        'hi' => 'सबसे पहले पावरप्ले में तूफानी बल्लेबाजी ने विपक्षी टीम पर तुरंत बड़ा दबाव बना दिया।',
                        'en' => 'First, an explosive powerplay start put the opposition under immediate scoreboard pressure.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'MOMENT 2',
                        'headline' => 'Death-Overs Spell Seals The Contest',
                        'subtext' => 'Pinpoint yorkers and clever slower balls defended the target.',
                        'hinglish' => 'Iske baad death overs ki sateek yorker bowling ne aakhiri palon mein jeet pakki kar di.',
                        'hi' => 'इसके बाद डेथ ओवर्स की सटीक यॉर्कर गेंदबाजी ने आखिरी पलों में जीत पक्की कर दी।',
                        'en' => 'Then came pinpoint death-over yorkers that sealed the contest in the final overs.',
                        'duration' => 5.0,
                    ],
                ],
            ],
            [
                'title' => 'Fast Bowling Tactical Reel',
                'caption' => "Why Hard-Length Bowling Beats Modern Batters!\n\n#HardLength #FastBowling #CricketTips #CricApp #CricketReels",
                'slides' => [
                    [
                        'badge' => 'BOWLING SECRET',
                        'headline' => 'Why Hard Length Beats Modern Power Hitters',
                        'subtext' => 'The 6m to 8m pitch zone that freezes every batter\'s footwork.',
                        'hinglish' => 'Kya aap jaante hain modern batters ko sabse zyada hard length ball kyun pareshan karti hai?',
                        'hi' => 'क्या आप जानते हैं आधुनिक बल्लेबाजों को सबसे ज्यादा हार्ड लेंथ गेंद क्यों परेशान करती है?',
                        'en' => 'Do you know why hard-length deliveries trouble modern power hitters more than bouncers?',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'NO-MAN\'S LAND',
                        'headline' => 'Neither Full To Drive Nor Short To Pull',
                        'subtext' => 'Extra steep bounce cramps hands near the bat splice.',
                        'hinglish' => '6 se 8 meter ki yeh length na drive karne deti hai aur na hi pull shot ke liye room chhodti hai.',
                        'hi' => 'छह से आठ मीटर की यह लेंथ न ड्राइव करने देती है और न ही पुल शॉट के लिए जगह छोड़ती है।',
                        'en' => 'Pitching 6 to 8 meters out, it prevents easy front-foot drives and cramps pull shots.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'HEAVY BALL',
                        'headline' => 'High Seam Release Extracts Sharp Kick',
                        'subtext' => 'Hit the deck upright to beat outside edges and build dot-ball pressure.',
                        'hinglish' => 'Tez seam hit se ball tezi se bounce hoti hai aur लगातार dot balls match palat deti hain.',
                        'hi' => 'तेज सीम हिट से गेंद तेजी से उछलती है और लगातार डॉट बॉल मैच पलट देती हैं।',
                        'en' => 'Hitting the deck hard extracts sharp bounce that builds dot-ball pressure and forced mistakes.',
                        'duration' => 5.0,
                    ],
                ],
            ],
            [
                'title' => 'Death Overs Survival Guide',
                'caption' => "3 Mistakes That Decide Tight Death Overs!\n\n#DeathOvers #T20Cricket #MatchAnalysis #CricApp",
                'slides' => [
                    [
                        'badge' => 'CRICKET IQ',
                        'headline' => '3 Mistakes That Cost Tight Final Overs',
                        'subtext' => 'Why small tactical errors turn winning positions into heartbreak.',
                        'hinglish' => 'Death overs mein yeh 3 chhoti galtiyan aksar jeete hue match ko haar mein badal deti hain.',
                        'hi' => 'डेथ ओवर्स में ये तीन छोटी गलतियां अक्सर जीते हुए मैच को हार में बदल देती हैं।',
                        'en' => 'These 3 small tactical mistakes in death overs often turn winning games into heartbreak.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'MISTAKE #1',
                        'headline' => 'Bowling Wide Without Field Protection',
                        'subtext' => 'Giving width with fine leg up gifts easy boundaries.',
                        'hinglish' => 'Pehli galti: wrong field ke saath predictable line par bowling karna jahan batter wait kar raha ho.',
                        'hi' => 'पहली गलती: गलत फील्ड के साथ अनुमानित लाइन पर गेंदबाजी करना जहाँ बल्लेबाज इंतजार कर रहा हो।',
                        'en' => 'Mistake one: bowling predictable lines into a batter\'s hitting arc without boundary protection.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'MISTAKE #2',
                        'headline' => 'Panicking After One Boundary',
                        'subtext' => 'Great finishers target unsettled bowlers who abandon slower balls.',
                        'hinglish' => 'Doosri galti: ek six padne ke baad plan chhodkar panic length daalna.',
                        'hi' => 'दूसरी गलती: एक छक्का लगने के बाद प्लान छोड़कर पैनिक लेंथ डालना।',
                        'en' => 'Mistake two: abandoning clever pace variations right after conceding one big hit.',
                        'duration' => 5.0,
                    ],
                ],
            ],
            [
                'title' => 'Captaincy Chess Masterclass',
                'caption' => "How Smart Captains Defend Under-Par Totals!\n\n#CricketCaptaincy #Tactics #CricApp #CricketReels",
                'slides' => [
                    [
                        'badge' => 'TACTICAL MASTERCLASS',
                        'headline' => 'How Captains Defend Low Scores Calmly',
                        'subtext' => 'Field traps and matchup bowling squeeze chases dry.',
                        'hinglish' => 'Kam total defend karne ke liye smart captains kaise bowling matchups aur field traps set karte hain?',
                        'hi' => 'कम स्कोर बचाने के लिए स्मार्ट कप्तान कैसे बॉलिंग मैचअप और फील्ड ट्रैप सेट करते हैं?',
                        'en' => 'How do sharp captains turn under-par totals into nail-biting victories?',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'STRIKE CHOKE',
                        'headline' => 'Target Single Options, Force Big Risks',
                        'subtext' => 'Taking away easy singles forces batters out of rhythm.',
                        'hinglish' => 'Pehle easy singles band kiye jaate hain taaki set batter bada risk lene par majboor ho.',
                        'hi' => 'पहले आसान सिंगल बंद किए जाते हैं ताकि सेट बल्लेबाज बड़ा रिस्क लेने पर मजबूर हो।',
                        'en' => 'First, cutting off easy singles forces set batters to take high-risk aerial shots.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'PACE OFF TRAP',
                        'headline' => 'Hold Back Your Best Over For Momentum Shift',
                        'subtext' => 'One surprise spinner or strike bowler swing breaks partnerships.',
                        'hinglish' => 'Phir surprise bowler matchup laakar partnership todte hi chase par poora control aa jata hai.',
                        'hi' => 'फिर सरप्राइज बॉलर मैचअप लाकर साझेदारी तोड़ते ही रन चेज पर पूरा कंट्रोल आ जाता है।',
                        'en' => 'Then a timely matchup change breaks the stand and swings momentum back completely.',
                        'duration' => 5.0,
                    ],
                ],
            ],
            [
                'title' => 'Run Chase Psychology',
                'caption' => "Inside The Mind Of A Master Run Chaser!\n\n#RunChase #CricketMindset #ViratKohli #CricApp",
                'slides' => [
                    [
                        'badge' => 'RUN CHASE MINDSET',
                        'headline' => 'What Separates Legends In Pressure Chases?',
                        'subtext' => 'Breaking huge targets into calm 3-over mini goals.',
                        'hinglish' => 'Mushkil run chase mein bade players pressure ke bawajood hamesha shant kaise rehte hain?',
                        'hi' => 'मुश्किल रन चेज में बड़े खिलाड़ी दबाव के बावजूद हमेशा शांत कैसे रहते हैं?',
                        'en' => 'How do the greatest finishers stay calm when the required run rate climbs fast?',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'STEP BY STEP',
                        'headline' => 'Never Chase 60 Runs In One Over',
                        'subtext' => 'Focus on targeting one weaker matchup per two overs.',
                        'hinglish' => 'Woh poora target ek saath nahi balki har 2 over ke chhote targets mein baat kar khelte hain.',
                        'hi' => 'वे पूरा लक्ष्य एक साथ नहीं बल्कि हर दो ओवर के छोटे लक्ष्यों में बांटकर खेलते हैं।',
                        'en' => 'They break daunting targets into small chunks and target one specific matchup every two overs.',
                        'duration' => 5.0,
                    ],
                    [
                        'badge' => 'RUNNING GOLD',
                        'headline' => 'Turn Ones Into Twos Before Death Overs',
                        'subtext' => 'Fast running keeps dot-ball panic completely away.',
                        'hinglish' => 'Tez running se 1s ko 2s mein badalna scoreboard ko hamesha control mein rakhta hai.',
                        'hi' => 'तेज रनिंग से एक रन को दो रन में बदलना स्कोरबोर्ड को हमेशा कंट्रोल में रखता है।',
                        'en' => 'Hustling ones into twos keeps dot-ball percentage low before the final assault.',
                        'duration' => 5.0,
                    ],
                ],
            ],
        ];

        $chosenTpl = $templates[$variant % count($templates)];
        $slidesRaw = $chosenTpl['slides'];
        $slidesRaw[] = [
            'badge' => 'LIVE ON CRICAPP.IN',
            'headline' => 'Ball-by-Ball Scores & Reports',
            'subtext' => 'Search CricApp.in on Google for full scorecards & match news.',
            'hinglish' => 'Sabse tez live cricket scores aur match reports ke liye abhi judein, CricApp.in par!',
            'hi' => 'सबसे तेज़ लाइव क्रिकेट स्कोर और मैच रिपोर्ट्स के लिए अभी जुड़ें, CricApp dot in पर।',
            'en' => 'Follow CricApp dot in for fastest live cricket scores and daily match stories.',
            'duration' => 4.5,
        ];

        $bgUrls = $this->pickRotatedBackgrounds(count($slidesRaw), $variant, $curated[0] ?? '', $excludeImages);
        $slides = [];
        foreach ($slidesRaw as $idx => $s) {
            $slides[] = [
                'badge' => $s['badge'],
                'headline' => $s['headline'],
                'subtext' => $s['subtext'],
                'narration' => $wantsEnglish ? $s['en'] : $s['hinglish'],
                'narration_hinglish' => $s['hinglish'],
                'narration_hi' => $s['hi'],
                'narration_en' => $s['en'],
                'image' => $bgUrls[$idx] ?? ($curated[$idx % max(1, count($curated))] ?? ''),
                'duration' => $s['duration'],
            ];
        }

        $themes = ['stadium', 'crimson', 'pitch'];

        return [
            'source_article_id' => null,
            'variant' => $variant,
            'title' => $chosenTpl['title'],
            'theme' => $themes[$variant % count($themes)],
            'voice' => $voice,
            'article_url' => url('/articles'),
            'social_caption' => $chosenTpl['caption'],
            'slides' => $slides,
        ];
    }

    /**
     * Parse markdown headings and paragraphs into concise vertical reel sections.
     */
    private function extractSections(string $markdown): array
    {
        $sections = [];
        $lines = preg_split('/\R+/u', $markdown) ?: [];

        $currentHeading = '';
        $currentBody = [];

        $flush = function () use (&$currentHeading, &$currentBody, &$sections) {
            $bodyText = $this->cleanText(implode("\n", $currentBody));
            $headText = $this->cleanText($currentHeading);

            if ($headText === '' && $bodyText === '') {
                return;
            }

            $sentences = $this->splitCleanSentences($bodyText);
            $firstSentence = $sentences[0] ?? $headText;
            $secondSentence = $sentences[1] ?? '';

            $headline = $headText !== '' ? $headText : $this->smartTrimText($firstSentence, 72);
            $subtext = $headText !== '' ? $firstSentence : ($secondSentence !== '' ? $secondSentence : $bodyText);
            $headNorm = mb_strtolower(rtrim($headText, '.!?:। '), 'UTF-8');
            $firstNorm = mb_strtolower(rtrim($firstSentence, '.!?:। '), 'UTF-8');
            if ($headNorm !== '' && ($headNorm === $firstNorm || str_starts_with($firstNorm, $headNorm))) {
                $narration = trim($firstSentence . ' ' . $secondSentence);
            } else {
                $headingSep = ($headText !== '' && preg_match('/[?!.।]$/u', $headText)) ? ' ' : '. ';
                $narration = trim(($headText !== '' ? $headText . $headingSep : '') . $firstSentence . ' ' . $secondSentence);
            }

            $altSub = $secondSentence !== '' ? $secondSentence : $subtext;
            $altNarration = trim(($secondSentence !== '' ? $secondSentence . ' ' : '') . ($sentences[2] ?? $firstSentence));

            if (mb_strlen($headline, 'UTF-8') >= 6) {
                $sections[] = [
                    'badge' => $headText !== '' ? $headText : 'MATCH INSIGHT',
                    'headline' => $headline,
                    'subtext' => $subtext !== '' ? $subtext : $headline,
                    'narration' => $narration !== '' ? $narration : $headline,
                    'alt_headline' => $this->smartTrimText($firstSentence !== '' ? $firstSentence : $headline, 72),
                    'alt_subtext' => $altSub !== '' ? $altSub : $headline,
                    'alt_narration' => $altNarration !== '' ? $altNarration : $narration,
                    'sentences' => $sentences,
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

            if (preg_match('/^#{1,4}\s+(.+)$/u', $line, $m)) {
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

    private function splitCleanSentences(string $text): array
    {
        $clean = trim($text);
        if ($clean === '') {
            return [];
        }
        // Protect numbered prefixes like "1. Shattering..." from splitting on the period
        $protected = preg_replace('/(^|\s)(\d{1,2})\.\s+/u', '$1$2__DOTNUM__ ', $clean) ?? $clean;
        $parts = preg_split('/(?<!\d)(?<=[.!?।])\s+/u', $protected, -1, PREG_SPLIT_NO_EMPTY) ?: [$protected];
        return array_map(static fn (string $s) => str_replace('__DOTNUM__', '.', $s), $parts);
    }

    /**
     * Trim text on whole-word boundaries (never cutting a word mid-letter) and strip dangling connectors.
     */
    private function smartTrimText(string $text, int $maxChars, string $suffix = '...'): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($clean === '' || mb_strlen($clean, 'UTF-8') <= $maxChars) {
            return $clean;
        }

        $suffixLen = mb_strlen($suffix, 'UTF-8');
        $budget = max(12, $maxChars - $suffixLen);
        $slice = mb_substr($clean, 0, $budget, 'UTF-8');
        $nextChar = mb_substr($clean, $budget, 1, 'UTF-8');

        // Roll back to the previous word boundary if we stopped inside a word
        if ($nextChar !== '' && !preg_match('/^[\s.,!?;:\-–—।)]/u', $nextChar)) {
            $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');
            if ($lastSpace !== false && $lastSpace >= (int) floor($budget * 0.45)) {
                $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
            }
        }

        // Remove dangling connectors/punctuation left at the tail after trimming
        $slice = rtrim($slice, " \t\n\r\0\x0B,;:-–—/&(");
        // Strip a dangling bullet/list lead-in cut right after a colon (e.g. "considerations: Searching")
        $slice = preg_replace('/:\s+\S{1,20}$/u', '', $slice) ?? $slice;
        $slice = preg_replace('/\s+(?:and|or|the|of|with|for|to|in|on|at|a|an|his|her|their|its|as|ke|ka|ki|aur|ya)$/iu', '', $slice) ?? $slice;
        $slice = rtrim($slice, " \t\n\r\0\x0B,;:-–—/&(");

        if ($slice === '') {
            $slice = trim(mb_substr($clean, 0, $budget, 'UTF-8'));
        }

        return $suffix !== '' ? ($slice . $suffix) : $slice;
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        // Strip markdown images & links keeping label text
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text) ?? $text;
        // Strip markdown emphasis/code markers
        $text = str_replace(['**', '__', '`', '###', '##', '#', '>', '•'], ' ', $text);
        $text = preg_replace('/^\s*[-*+]\s+/mu', '', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([,.;!?।])/u', '$1', $text) ?? $text;
        return trim((string) $text);
    }

    private function toSpeechSentence(string $text, int $maxChars = 285): string
    {
        $clean = $this->cleanText($text);
        $clean = preg_replace('/CricApp\.in/iu', 'CricApp dot in', $clean) ?? $clean;
        // Remove emojis / pictographs so neural TTS doesn't read symbol names awkwardly
        $clean = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', (string) $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/u', ' ', (string) $clean) ?? '');
        $clean = preg_replace('/([!?।])\.+/u', '$1', $clean) ?? $clean;

        if ($clean === '') {
            return '';
        }

        // Collect complete sentences within the speaking window so words never get cut mid-word (e.g. "tr." / "n.")
        $sentences = $this->splitCleanSentences($clean);
        $picked = '';
        foreach ($sentences as $idx => $sentence) {
            $candidate = $picked === '' ? $sentence : ($picked . ' ' . $sentence);
            if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
                $picked = $candidate;
                continue;
            }
            if ($picked === '') {
                $picked = $this->trimOverlongSpeech($sentence, $maxChars);
            } elseif ($idx === 1 && mb_strlen($picked, 'UTF-8') <= 85) {
                // First sentence was only a heading — include the main point trimmed on a complete clause or word boundary
                $picked = $this->trimOverlongSpeech($candidate, $maxChars);
            }
            break;
        }

        $out = trim($picked !== '' ? $picked : $this->trimOverlongSpeech($clean, $maxChars));
        $out = preg_replace('/([!?।])\.+$/u', '$1', $out) ?? $out;
        if ($out !== '' && !preg_match('/[.!?।]$/u', $out)) {
            $out .= $this->isMostlyHindi($out) ? '।' : '.';
        }

        return $out;
    }

    /**
     * Prefer trimming an overlong sentence at a natural clause boundary (: ; — ,) before falling back to word boundary.
     */
    private function trimOverlongSpeech(string $text, int $maxChars): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($clean === '' || mb_strlen($clean, 'UTF-8') <= $maxChars) {
            return $clean;
        }

        $window = mb_substr($clean, 0, $maxChars, 'UTF-8');
        $minThreshold = (int) floor($maxChars * 0.45);
        foreach ([': ', '; ', '—', ', '] as $delim) {
            $pos = mb_strrpos($window, $delim, 0, 'UTF-8');
            if ($pos !== false && $pos >= $minThreshold) {
                $candidate = rtrim(mb_substr($window, 0, $pos, 'UTF-8'), " \t\n\r\0\x0B,;:-–—");
                if (mb_strlen($candidate, 'UTF-8') >= 40) {
                    return $candidate;
                }
            }
        }

        return $this->smartTrimText($clean, $maxChars, '');
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
            'निलामी' => 'auction',
            'नीलामी' => 'auction',
            'ऑक्शन' => 'auction',
            'ट्रेड' => 'trade',
            'नवंबर' => 'November',
            'दिसंबर' => 'December',
            'जनवरी' => 'January',
            'अक्टूबर' => 'October',
            'अर्शदीप सिंह' => 'Arshdeep Singh',
            'अर्शदीप' => 'Arshdeep',
            'लिस्ट-ए' => 'List A',
            'लिस्ट ए' => 'List A',
            'स्विंग' => 'swing',
            'बाउंस' => 'bounce',
            'हार्ड लेंथ' => 'hard length',
            'प्लेइंग' => 'playing',
            'टॉस' => 'toss',
            'ऑलआउट' => 'all out',
            'ऑल-आउट' => 'all out',
            'डालने' => 'daalne',
            'कोच' => 'coach',
            'पिच' => 'pitch',
            'लीग' => 'league',
            'सीजन' => 'season',
            'फैंस' => 'fans',
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
                    if ($lower[0] !== $engLower[0] && !($lower[0] === 'p' && $engLower[0] === 'f')) {
                        continue;
                    }
                    $engClean = rtrim($engLower, 's');
                    $engSkel = $this->consonantSkeleton($engLower);
                    $engCleanSkel = $this->consonantSkeleton($engClean);
                    similar_text($lower, $engLower, $pct);
                    if (
                        (strlen($romanSkel) >= 5 && ($romanSkel === $engSkel || $romanSkel === $engCleanSkel)) ||
                        ($pct >= 80.0 && abs(strlen($engLower) - strlen($lower)) <= 2)
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
        $summaryLine = $excerpt !== '' ? $this->smartTrimText($excerpt, 140) : 'Full analysis & live updates inside.';

        return "{$title}\n\n{$summaryLine}\n\nRead full breakdown on CricApp.in:\n" .
            route('articles.show', $article->slug) .
            "\n\n" . implode(' ', $hashtags);
    }
}
