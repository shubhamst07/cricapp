<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiCommentaryService
{
    /**
     * Transform incoming raw deliveries into unique, original AI-generated cricket commentary.
     * Replaces verbatim third-party commentary with creative narrative commentary in both English and Hindi.
     */
    public function transformDeliveries(array $deliveries): array
    {
        $transformed = [];

        foreach ($deliveries as $item) {
            // If already processed by AI and has Hindi commentary and title_hi, retain it
            if (!empty($item['is_ai']) && !empty($item['ai_hindi']) && !empty($item['title_hi'])) {
                $transformed[] = $item;
                continue;
            }

            $type = $item['type'] ?? 'ball';

            if ($type === 'ball') {
                $item = $this->transformBall($item);
            } elseif ($type === 'over-separator') {
                $item = $this->transformOverSeparator($item);
            } elseif ($type === 'interview' || ($item['over'] ?? '') === 'QUOTE') {
                $item = $this->transformInterview($item);
            } elseif ($type === 'match_wrap' || ($item['over'] ?? '') === 'FINAL') {
                $item = $this->transformMatchWrap($item);
            } else {
                // Tactical notes, updates, and announcements
                $item = $this->transformNote($item);
            }

            $transformed[] = $item;
        }

        return $transformed;
    }

    /**
     * Transform an individual ball delivery.
     */
    protected function transformBall(array $ball): array
    {
        $title = (string) ($ball['title'] ?? '');
        $rawDesc = (string) ($ball['desc'] ?? $ball['rawText'] ?? '');
        $badge = (string) ($ball['badge'] ?? '0');
        $badgeType = (string) ($ball['badgeType'] ?? 'dot');
        $over = (string) ($ball['over'] ?? '');

        // Extract Bowler and Batsman names
        $bowler = $ball['bowler'] ?? null;
        $batsman = $ball['batsman'] ?? null;

        if ((!$bowler || !$batsman) && str_contains($title, ' to ')) {
            $parts = explode(' to ', $title, 2);
            if (!$bowler) $bowler = trim($parts[0]);
            if (!$batsman && isset($parts[1])) {
                $sub = explode(',', $parts[1])[0];
                $batsman = trim($sub);
            }
        }

        $bowler = $bowler ?: 'The bowler';
        $batsman = $batsman ?: 'The batsman';

        // Detect action category with high precision
        $badgeStr = strtoupper(trim($badge));
        $isFour = ($badgeStr === '4' || $badgeType === 'four' || preg_match('/\b(four|boundary)\b/i', $title));
        $isSix = ($badgeStr === '6' || $badgeType === 'six' || preg_match('/\b(six|maximum)\b/i', $title));
        $isWicket = ($badgeStr === 'W' || $badgeType === 'wicket' || (preg_match('/\b(is out|clean bowled|caught out|run out|stumped|lbw)\b/i', $title) && !$isFour && !$isSix));
        $isWide = (stripos($rawDesc, 'wide') !== false || $badgeStr === 'WD');
        $isNoBall = (stripos($rawDesc, 'no ball') !== false || $badgeStr === 'NB');

        // Synthesize dynamic AI commentary (English & Hindi)
        $aiEng = '';
        $aiHin = '';

        if ($isWicket) {
            $wicketVariations = [
                ["OUT! What a massive breakthrough! {$bowler} strikes with absolute precision, sending {$batsman} back to the pavilion.", "OUT! Bada jhatka! {$bowler} ne shandar bowling karte hue {$batsman} ko pavilion bhej diya hai."],
                ["WICKET! Gone! High drama in the middle as {$batsman} perishes trying to force the pace against {$bowler}.", "WICKET! Bada wicket gira! {$batsman} ne {$bowler} par bada shot khelne ki koshish ki aur apna wicket gawa baithe."],
                ["OUT! Silence in the batting camp! {$bowler} produces a piece of magic to dismiss {$batsman}.", "OUT! Match ka naya mod! {$bowler} ne apni chalaki se {$batsman} ko trap kiya aur wicket jhatak li."],
                ["WICKET! Big blow! Outstanding execution by {$bowler}, and {$batsman} has to take the long walk back.", "WICKET! Zabardast bowling! {$bowler} ne match me team ki mazboot wapsi karwayi, {$batsman} out!"]
            ];
            $choice = $wicketVariations[abs(crc32($over . $title)) % count($wicketVariations)];
            $aiEng = $choice[0];
            $aiHin = $choice[1];
        } elseif ($isSix) {
            $sixVariations = [
                ["SIX! That is colossal! {$batsman} picks the length early off {$bowler} and launches it high, handsome and into the crowd!", "SIX! Gagan-chumbi chhakka! {$batsman} ne {$bowler} ki delivery par poora bharosa dikhaya aur gend ko stadium me bhej diya!"],
                ["BOOM! Massive maximum! Clean connection from {$batsman}, towering over the boundary ropes!", "BOOM! Shandar 6 run! {$batsman} ke balle se nikla behtareen shot, boundary ke bohot door!"],
                ["SIX! Into the stands! {$batsman} dances down the track against {$bowler} and smokes it all the way!", "SIX! Zabardast hitting! {$batsman} ne aage badhkar {$bowler} ko seedha stand me mara!"]
            ];
            $choice = $sixVariations[abs(crc32($over . $title)) % count($sixVariations)];
            $aiEng = $choice[0];
            $aiHin = $choice[1];
        } elseif ($isFour) {
            $fourVariations = [
                ["FOUR! Cracking stroke! {$batsman} finds the gap with supreme timing off {$bowler}, racing away to the ropes.", "FOUR! Khubsoorat chauka! {$batsman} ne gap dhundha aur gend bijli ki tezi se boundary paar!"],
                ["FOUR! Pure elegance! Leans into the drive and threads the infield with exquisite perfection.", "FOUR! Lajawab shot! {$batsman} ne timing ke sath shot lagaya aur fielder ke paas koi mauka nahi tha."],
                ["FOUR! Beautiful boundary! {$batsman} uses the pace of {$bowler} smartly to beat the chasing fielder.", "FOUR! Shandar boundary! {$batsman} ne {$bowler} ki speed ka faayda uthaya aur 4 run bhatore."]
            ];
            $choice = $fourVariations[abs(crc32($over . $title)) % count($fourVariations)];
            $aiEng = $choice[0];
            $aiHin = $choice[1];
        } elseif ($isWide) {
            $aiEng = "Wide called. {$bowler} strays down leg, and the umpire stretches both arms out for an extra.";
            $aiHin = "Wide ball! {$bowler} ne line miss ki, umpire ka ishara extra run ke liye.";
        } elseif ($isNoBall) {
            $aiEng = "No ball signaled! Overstepping from {$bowler}. Extra run conceded and a Free Hit coming up!";
            $aiHin = "No ball! {$bowler} ka pair line se bahar, batting team ko mila extra run aur Free Hit!";
        } elseif ($badge === '1') {
            $singleVariations = [
                ["{$bowler} bowls on a good length, {$batsman} taps it gently towards mid-on for a brisk single.", "{$bowler} ki acchi length delivery, {$batsman} ne single lekar strike rotate ki."],
                ["Pitched up on middle and leg, pushed through the vacant gap to rotate the strike comfortably.", "Middle stump par delivery, {$batsman} ne aaram se 1 run poora kiya."],
                ["Controlled nudge into the off-side gap, scampering through for an easy single.", "Halka push off-side me aur aasani se 1 run chura liya."]
            ];
            $choice = $singleVariations[abs(crc32($over . $title)) % count($singleVariations)];
            $aiEng = $choice[0];
            $aiHin = $choice[1];
        } elseif ($badge === '2' || $badge === '3') {
            $aiEng = "Good running between the wickets! {$batsman} pushes it wide of the fielder and hustles back hard for {$badge} runs.";
            $aiHin = "Kamaal ki running between the wickets! {$batsman} ne tej daud lagakar {$badge} run poore kiye.";
        } else {
            // Dot Ball
            $dotVariations = [
                ["Good length delivery around off-stump, {$batsman} plays it with soft hands straight to the fielder.", "Off-stump par badhiya delivery, {$batsman} ne defend kiya, koi run nahi."],
                ["Beaten! Lovely seam movement from {$bowler}, beating the defensive push.", "Beaten! {$bowler} ki shandar swinging delivery, batsman beat hue, dot ball."],
                ["Right in the blockhole! {$batsman} jams the bat down in time to dig it out safely.", "Blockhole me delivery! {$batsman} ne samay par bat lagaya, safe rahe, koi run nahi."],
                ["Tidy line and length from {$bowler}, keeping {$batsman} quiet on the backfoot.", "{$bowler} ki disciplined line aur length, batsman ko bandh ke rakha, dot ball."]
            ];
            $choice = $dotVariations[abs(crc32($over . $title)) % count($dotVariations)];
            $aiEng = $choice[0];
            $aiHin = $choice[1];
        }

        // Action text for title and tag in Hindi
        $titleHi = '';
        $tagHi = '';
        if ($isWicket) {
            $titleHi = "{$bowler} to {$batsman}, आउट (WICKET)";
            $tagHi = 'विकेट ☝️';
        } elseif ($isSix) {
            $titleHi = "{$bowler} to {$batsman}, छक्का (SIX)";
            $tagHi = 'मैक्सिमम 6';
        } elseif ($isFour) {
            $titleHi = "{$bowler} to {$batsman}, चौका (FOUR)";
            $tagHi = 'बाउंड्री 4';
        } elseif ($isWide) {
            $titleHi = "{$bowler} to {$batsman}, वाइड (Wide)";
            $tagHi = 'अतिरिक्त रन';
        } elseif ($isNoBall) {
            $titleHi = "{$bowler} to {$batsman}, नो बॉल (No Ball)";
            $tagHi = 'नो बॉल';
        } elseif ($badge === '1') {
            $titleHi = "{$bowler} to {$batsman}, 1 रन";
            $tagHi = '1 रन';
        } elseif ($badge === '2' || $badge === '3') {
            $titleHi = "{$bowler} to {$batsman}, {$badge} रन";
            $tagHi = "{$badge} रन";
        } else {
            $titleHi = "{$bowler} to {$batsman}, कोई रन नहीं";
            $tagHi = 'डॉट बॉल';
        }

        // Replace raw description and rawText with AI output
        $ball['desc'] = $aiEng;
        $ball['rawText'] = "{$bowler} to {$batsman}, " . ($badge === 'W' ? 'OUT' : ($badge . ' runs')) . " - {$aiEng}";
        $ball['ai_hindi'] = $aiHin;
        $ball['title_hi'] = $titleHi;
        $ball['tag_hi'] = $tagHi;
        $ball['is_ai'] = true;
        $ball['ai_flair'] = 'LiveCricket AI Engine';

        return $ball;
    }

    /**
     * Transform an over separator into an AI Over Summary.
     */
    protected function transformOverSeparator(array $sep): array
    {
        $overNum = $sep['overNumber'] ?? $sep['over'] ?? null;
        $runs = $sep['overRuns'] ?? 0;
        $summary = $sep['overSummary'] ?? '';
        $score = $sep['teamScore'] ?? '';
        $batTeam = $sep['batTeam'] ?? '';

        $sep['desc'] = "End of Over {$overNum}: {$runs} runs conceded ({$summary}). " .
            ($runs > 10 ? "An expensive over for the bowling side!" : ($runs <= 3 ? "A very tight and disciplined over." : "Steady passage of play in the middle."));

        $sep['ai_hindi'] = "Over {$overNum} samapt: {$runs} run aaye ({$summary}). " .
            ($runs > 10 ? "Batting team ke liye faydemand over!" : "Bowling team ne tight line banaye rakhi.");

        $sep['title_hi'] = "ओवर {$overNum} समाप्त" . ($batTeam && $score ? " • {$batTeam} {$score}" : "");
        $sep['tag_hi'] = "ओवर {$overNum}";
        $sep['is_ai'] = true;
        $sep['ai_flair'] = 'AI Over Recap';

        return $sep;
    }

    /**
     * Transform a player quote or interview take into AI commentary with rephrased wording and Hindi translation.
     */
    protected function transformInterview(array $item): array
    {
        $speech = (string) ($item['desc'] ?? $item['rawText'] ?? '');
        $title = (string) ($item['title'] ?? '');
        $speaker = '';
        if (preg_match('/^([^(]+)/', $title, $m)) {
            $speaker = trim($m[1]);
        }
        $speaker = $speaker ?: 'Player';

        // Rephrase English wording with AI paraphrase
        $paraSpeech = $this->paraphraseEnglish($speech);
        $aiEng = "\"{$paraSpeech}\"";

        // Translate rephrased quote to natural Hindi
        $hiSpeech = $this->translateToHindi($paraSpeech ?: $speech);
        $aiHin = "{$speaker} ने मैच के बाद अपनी राय साझा करते हुए कहा: \"{$hiSpeech}\"";

        $item['desc'] = $aiEng;
        $item['ai_hindi'] = $aiHin;
        $item['title'] = "{$speaker} (Post-Match Take)";
        $item['title_hi'] = "{$speaker} (मैच के बाद बयान)";
        $item['tag'] = 'Player Quote 🎙️';
        $item['tag_hi'] = 'खिलाड़ी का बयान 🎙️';
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Post-Match Presentation';

        return $item;
    }

    /**
     * Transform a match wrap-up announcement into AI commentary with rephrased wording and Hindi translation.
     */
    protected function transformMatchWrap(array $item): array
    {
        $text = (string) ($item['desc'] ?? $item['rawText'] ?? '');
        $paraText = $this->paraphraseEnglish($text);
        $hiText = $this->translateToHindi($paraText ?: $text);

        $item['desc'] = "Match Wrap: " . $paraText;
        $item['ai_hindi'] = "मैच परिणाम सारांश: " . $hiText;
        $item['title'] = 'Match Summary & Wrap-up';
        $item['title_hi'] = 'मैच सारांश व परिणाम';
        $item['tag'] = 'Match Result 🏆';
        $item['tag_hi'] = 'मैच परिणाम 🏆';
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Match Review';

        return $item;
    }

    /**
     * Transform a tactical match note into AI commentary with completely rewritten wording and Hindi translation.
     */
    protected function transformNote(array $item): array
    {
        $raw = (string) ($item['desc'] ?? $item['rawText'] ?? '');
        $rawTrimmed = trim(strip_tags($raw));

        // Pattern 1: Bowling change e.g. "Amir Hamza [2.0-0-13-0] is back into the attack"
        if (preg_match('/^([A-Za-z\s.\-]+?)\s*(?:\[[^\]]*\])?\s*(?:is\s+back\s+into\s+the\s+attack|comes\s+into\s+the\s+attack|begins\s+a\s+new\s+spell)/i', $rawTrimmed, $m)) {
            $bowler = trim($m[1]);
            $aiEng = "Bowling Change: Captain brings {$bowler} into the attack to break the rhythm and apply pressure.";
            $aiHin = "गेंदबाजी में बदलाव: कप्तान ने साझेदारी को तोड़ने और रन रोकने के लिए {$bowler} को गेंदबाजी पर लगाया है।";
            $tagEn = 'Bowling Change';
            $tagHi = 'गेंदबाजी बदलाव 🎳';
        }
        // Pattern 2: New batter arrives e.g. "Michael Bracewell, left handed bat, comes to the crease"
        elseif (preg_match('/^([A-Za-z\s.\-]+?),\s*(?:left|right)[\s\-]+handed\s+bat,\s*comes\s+to\s+the\s+crease/i', $rawTrimmed, $m)) {
            $batter = trim($m[1]);
            $aiEng = "Batter In: {$batter} walks out to the pitch, tasked with anchoring the innings and building momentum.";
            $aiHin = "नए बल्लेबाज क्रीज पर: {$batter} बल्लेबाजी के लिए मैदान में उतरे हैं, टीम को इनसे एक मजबूत पारी की उम्मीद होगी।";
            $tagEn = 'New Batter';
            $tagHi = 'नया बल्लेबाज 🏏';
        }
        // Pattern 3: Drinks break
        elseif (preg_match('/(?:drink|drinks|hydration\s+break)/i', $rawTrimmed)) {
            $aiEng = "Drinks Interval: Players take a hydration pause as team management and captains review on-field tactics.";
            $aiHin = "ड्रिंक्स ब्रेक: मैदान पर खिलाड़ियों के लिए ड्रिंक्स ब्रेक, दोनों टीमें पानी पीकर मैच की अगली रणनीति पर चर्चा कर रही हैं।";
            $tagEn = 'Drinks Break';
            $tagHi = 'ड्रिंक्स ब्रेक 🥤';
        }
        // Pattern 4: General analysis / observation note
        else {
            $para = $this->paraphraseEnglish($rawTrimmed);
            $aiEng = "Match Desk Analysis: " . $para;
            $hi = $this->translateToHindi($para ?: $rawTrimmed);
            $aiHin = "मैच विश्लेषण: " . $hi;
            $tagEn = 'Tactical Note';
            $tagHi = 'मैच अपडेट 📋';
        }

        $item['desc'] = $aiEng;
        $item['ai_hindi'] = $aiHin;
        $item['title'] = 'Match Note';
        $item['title_hi'] = 'मैच नोट (Match Note)';
        $item['tag'] = $tagEn;
        $item['tag_hi'] = $tagHi;
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Live Match Desk';

        return $item;
    }

    /**
     * Paraphrase English text to ensure original, non-verbatim wording.
     */
    public function paraphraseEnglish(string $text): string
    {
        $text = trim(strip_tags($text));
        if (empty($text) || strlen($text) < 15) {
            return $text;
        }

        $cacheKey = 'para_en_' . md5($text);
        try {
            return Cache::remember($cacheKey, 86400 * 7, function () use ($text) {
                try {
                    // English -> German -> English back-translation produces clean, idiomatic rephrasing
                    $urlDe = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=de&dt=t&q=' . urlencode($text);
                    $resDe = Http::timeout(3)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
                        ->get($urlDe);

                    if ($resDe->successful()) {
                        $arrDe = $resDe->json();
                        $deText = '';
                        if (is_array($arrDe) && isset($arrDe[0]) && is_array($arrDe[0])) {
                            foreach ($arrDe[0] as $p) {
                                $deText .= $p[0] ?? '';
                            }
                        }

                        if (!empty(trim($deText))) {
                            $urlEn = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=de&tl=en&dt=t&q=' . urlencode($deText);
                            $resEn = Http::timeout(3)
                                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
                                ->get($urlEn);

                            if ($resEn->successful()) {
                                $arrEn = $resEn->json();
                                $enText = '';
                                if (is_array($arrEn) && isset($arrEn[0]) && is_array($arrEn[0])) {
                                    foreach ($arrEn[0] as $p) {
                                        $enText .= $p[0] ?? '';
                                    }
                                }
                                if (!empty(trim($enText))) {
                                    return trim($enText);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fall back
                }

                return $text;
            });
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /**
     * Translate English cricket prose into Hindi with caching.
     */
    public function translateToHindi(string $text): string
    {
        $text = trim(strip_tags($text));
        if (empty($text)) {
            return '';
        }

        $cacheKey = 'trans_hi_' . md5($text);
        try {
            return Cache::remember($cacheKey, 86400 * 7, function () use ($text) {
                try {
                    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=hi&dt=t&q=' . urlencode($text);
                    $response = Http::timeout(4)
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
                            if (!empty($translated)) {
                                return $translated;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fall back to original text
                }

                return $text;
            });
        } catch (\Throwable $e) {
            return $text;
        }
    }
}
