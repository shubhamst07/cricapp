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
            $rawCheck = trim(strip_tags((string)($item['desc'] ?? $item['rawText'] ?? '')));
            if ($rawCheck === '$2b' || str_contains($rawCheck, '$2b') || str_starts_with($rawCheck, '$')) {
                continue;
            }
            $cleanContent = trim(preg_replace('/^(?:Match Desk Analysis|Match Note|Match Wrap):\s*/i', '', $rawCheck));
            if ($cleanContent === '$2b' || str_contains($cleanContent, '$2b') || str_starts_with($cleanContent, '$') || (isset($item['type']) && $item['type'] === 'note' && strlen($cleanContent) < 4)) {
                continue;
            }

            // If already processed by AI and has all three languages, retain it
            if (!empty($item['is_ai']) && !empty($item['ai_hindi']) && !empty($item['ai_hinglish'])) {
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
     * Transform an individual ball delivery into English, Pure Devanagari Hindi, and Hinglish.
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

        $aiEng = '';
        $aiHin = '';
        $aiHinglish = '';

        if ($isWicket) {
            $wicketEn = [
                "OUT! What a massive breakthrough! {$bowler} strikes with absolute precision, sending {$batsman} back to the pavilion.",
                "WICKET! Gone! High drama in the middle as {$batsman} perishes trying to force the pace against {$bowler}.",
                "OUT! Silence in the batting camp! {$bowler} produces a piece of magic to dismiss {$batsman}."
            ];
            $wicketHi = [
                "आउट! बड़ा झटका! {$bowler} की सटीक गेंद पर {$batsman} पवेलियन लौटते हुए।",
                "विकेट! मैच में जबरदस्त मोड़! {$bowler} ने अपनी चतुराई भरी गेंद से {$batsman} का कीमती विकेट चटकाया।",
                "क्लीन बोल्ड/आउट! गेंदबाज का शानदार प्रदर्शन! {$bowler} ने {$batsman} को चलता किया।"
            ];
            $wicketHn = [
                "OUT! Zabardast breakthrough! {$bowler} ne {$batsman} ko out karke match me apni team ki wapsi karwayi.",
                "WICKET! Bada wicket gir gaya! {$batsman} lamba shot lagane ke chakkar me {$bowler} ka shikar bane.",
                "OUT! Pavilion ki taraf lautate hue {$batsman}! {$bowler} ki behtareen bowling ne kamaal kar diya."
            ];
            $idx = abs(crc32($over . $title . 'w')) % count($wicketEn);
            $aiEng = $wicketEn[$idx];
            $aiHin = $wicketHi[$idx];
            $aiHinglish = $wicketHn[$idx];
        } elseif ($isSix) {
            $sixEn = [
                "SIX! Into the stands! {$batsman} dances down the track against {$bowler} and smokes it all the way!",
                "MAXIMUM! That is pure timing and elegance! {$batsman} hammers {$bowler} high over the ropes!",
                "SIX! Out of the ground! A colossal hit by {$batsman} off {$bowler} that clears deep midwicket with ease!"
            ];
            $sixHi = [
                "विशाल छक्का! {$batsman} ने आगे बढ़कर {$bowler} की गेंद को स्टैंड्स में पहुंचाया!",
                "सिक्स! क्या गजब का टाइमिंग और पावर! गेंद सीधे दर्शकों के बीच जाकर गिरी!",
                "शानदार छक्का! {$batsman} का बल्ले पर आया जबर्दस्त प्रहार, गेंद हवा में लहराते हुए बाउंड्री पार!"
            ];
            $sixHn = [
                "SIX! Zabardast hitting! {$batsman} ne aage badhkar seedha stand me mara!",
                "MAXIMUM! Kya kamaal ki timing! {$batsman} ne {$bowler} ki ball ko boundary ke bahar bhej diya!",
                "CHHAKKA! Ball hawa me aur seedha 6 runs ke liye boundary paar!"
            ];
            $idx = abs(crc32($over . $title . '6')) % count($sixEn);
            $aiEng = $sixEn[$idx];
            $aiHin = $sixHi[$idx];
            $aiHinglish = $sixHn[$idx];
        } elseif ($isFour) {
            $fourEn = [
                "FOUR! Exquisite placement! {$batsman} pierces the gap between point and cover with surgical precision against {$bowler}.",
                "BOUNDARY! Crisp timing off the willow! {$batsman} drives {$bowler} handsomely to the fence.",
                "FOUR! Beautifully executed shot! {$batsman} waits for the length and carves {$bowler} away for four valuable runs."
            ];
            $fourHi = [
                "शानदार चौका! {$batsman} ने गैप तलाशते हुए गेंद को बाउंड्री लाइन के पार भेजा।",
                "चौका! बल्ले का खूबसूरत कनेक्शन, फील्डर के पास कोई मौका नहीं और गेंद सीमा रेखा के पार।",
                "चार रन! नजाकत भरा शॉट, टाइमिंग इतनी सटीक कि गेंद गोली की रफ्तार से बाउंड्री तक पहुंची।"
            ];
            $fourHn = [
                "FOUR! Khubsurat shot! {$batsman} ne gap nikal kar ball ko boundary line ke paar bheja.",
                "CHAUKA! Behtareen timing! Fielders sirf ball ko boundary ki taraf jate hue dekhte reh gaye.",
                "FOUR! Khoobsurat cover drive/cut shot, 4 runs milenge batting team ko!"
            ];
            $idx = abs(crc32($over . $title . '4')) % count($fourEn);
            $aiEng = $fourEn[$idx];
            $aiHin = $fourHi[$idx];
            $aiHinglish = $fourHn[$idx];
        } elseif ($isWide || $isNoBall) {
            $extraType = $isWide ? 'Wide ball' : 'No ball';
            $aiEng = "Extra: {$extraType}! {$bowler} strays off radar and gifts an extra delivery and run to {$batsman}.";
            $aiHin = "अतिरिक्त रन: {$extraType}! {$bowler} अपनी लाइन से भटके, अंपायर का इशारा अतिरिक्त रन के लिए।";
            $aiHn = "Extra run! {$bowler} ne line miss ki, {$extraType} ka ishara umpire ki taraf se.";
        } else {
            // Dot balls / 1s / 2s
            $runs = is_numeric($badgeStr) ? (int)$badgeStr : 0;
            if ($runs === 1) {
                $singlesEn = [
                    "Single taken. {$batsman} nudges {$bowler} into the vacant pocket and rotates the strike briskly.",
                    "Quick single! Good communication between the runners as {$batsman} taps and dashes across.",
                    "One run added. {$bowler} keeps it tight on good length, tucked away neatly for a single."
                ];
                $singlesHi = [
                    "सिंगल! {$batsman} ने हल्के हाथों से खेलकर स्ट्राइक रोटेट की।",
                    "एक रन! दोनों बल्लेबाजों के बीच अच्छी समझ, तेजी से दौड़कर रन पूरा किया।",
                    "स्ट्राइक बदली! गेंदबाज की कसी हुई गेंद को सम्मान देकर 1 रन बटोरा।"
                ];
                $singlesHn = [
                    "Single mil gaya! {$batsman} ne Halke hatho se khelkar strike rotate kar li.",
                    "Ek run! Dono batsmen ne achhi running dikhayi aur aaram se 1 run le liya.",
                    "Strike rotation! {$batsman} ne ball ko gap me push kiya aur single le liya."
                ];
                $idx = abs(crc32($over . $title . '1')) % count($singlesEn);
                $aiEng = $singlesEn[$idx];
                $aiHin = $singlesHi[$idx];
                $aiHinglish = $singlesHn[$idx];
            } elseif ($runs === 2 || $runs === 3) {
                $aiEng = "Brilliant running between the wickets! {$batsman} pushes {$bowler} into the deep and hustles back for {$runs} runs.";
                $aiHin = "शानदार रनिंग! बल्लेबाजों ने तेजी दिखाते हुए दौड़कर {$runs} रन चुरा लिए।";
                $aiHn = "Zabardast running! Dono batsmen ne doudkar {$runs} runs poore kiye.";
            } else {
                // Dot ball
                $dotsEn = [
                    "Dot ball. Solid forward defense by {$batsman} against a probing delivery from {$bowler}.",
                    "No run. {$bowler} hits the hard length, and {$batsman} can only push it back to the bowler.",
                    "Dot ball! Good discipline outside off stump, left alone cautiously by {$batsman}."
                ];
                $dotsHi = [
                    "डॉट गेंद! {$bowler} की सटीक लाइन, बल्लेबाज ने संभलकर गेंद को डिफेंड किया।",
                    "कोई रन नहीं! गेंदबाज का बेहतरीन नियंत्रण, बल्लेबाज को कोई मौका नहीं मिला।",
                    "डॉट डिलीवरी! कसी हुई गेंदबाजी, बल्लेबाज रन बनाने में असमर्थ।"
                ];
                $dotsHn = [
                    "Dot ball! {$bowler} ki achhi bowling, batsman ne aage aakar defend kiya.",
                    "Koi run nahi! Bowler ne tight line rakhi, batsman ne koi risk nahi liya.",
                    "Dot ball! Zabardast control bowler ka, batsman dot ball khelne par majboor."
                ];
                $idx = abs(crc32($over . $title . '0')) % count($dotsEn);
                $aiEng = $dotsEn[$idx];
                $aiHin = $dotsHi[$idx];
                $aiHinglish = $dotsHn[$idx];
            }
        }

        $ball['desc'] = $aiEng;
        $ball['rawText'] = $aiEng;
        $ball['ai_hindi'] = $aiHin;
        $ball['ai_hinglish'] = $aiHinglish;
        $ball['is_ai'] = true;

        return $ball;
    }

    /**
     * Transform an over summary into original commentary.
     */
    protected function transformOverSeparator(array $item): array
    {
        $overRuns = (int) ($item['overRuns'] ?? $item['runs'] ?? 0);
        $overNum = (int) ($item['overNumber'] ?? $item['over'] ?? 0);
        $bowler = (string) ($item['bowler'] ?? 'The bowler');

        if ($overRuns >= 15) {
            $eng = "End of Over {$overNum}: Big over for the batting team! {$overRuns} runs plundered off {$bowler}'s spell.";
            $hin = "ओवर {$overNum} समाप्त: बल्लेबाजी टीम के लिए बड़ा ओवर! इस ओवर में {$overRuns} रन बटोरे।";
            $hn = "Over {$overNum} khatam: Batting team ke liye bada over! {$overRuns} runs aaye is over me.";
        } elseif ($overRuns === 0) {
            $eng = "End of Over {$overNum}: MAIDEN OVER! Top-class discipline by {$bowler}, conceding zero runs.";
            $hin = "ओवर {$overNum} समाप्त: मेडन ओवर! गेंदबाज ने कसी हुई गेंदबाजी से कोई रन नहीं दिया।";
            $hn = "Over {$overNum} khatam: MAIDEN OVER! Bowler ne ek bhi run nahi diya, shandaar bowling!";
        } else {
            $eng = "End of Over {$overNum}: {$overRuns} runs conceded. {$bowler} finishes a disciplined set of deliveries.";
            $hin = "ओवर {$overNum} समाप्त: इस ओवर से आए {$overRuns} रन। गेंदबाजी टीम का नियंत्रित प्रदर्शन।";
            $hn = "Over {$overNum} khatam: Is over se {$overRuns} runs aaye. Match kafi exciting chal raha hai.";
        }

        $item['desc'] = $eng;
        $item['rawText'] = $eng;
        $item['ai_hindi'] = $hin;
        $item['ai_hinglish'] = $hn;
        $item['is_ai'] = true;

        return $item;
    }

    /**
     * Transform a match note into non-verbatim, freshly phrased commentary across English, Hindi, and Hinglish.
     */
    protected function transformNote(array $item): array
    {
        $raw = (string) ($item['desc'] ?? $item['rawText'] ?? '');
        $rawTrimmed = trim(strip_tags($raw));

        // Pattern 1: Bowling change
        if (preg_match('/^([A-Za-z\s.\-]+?)\s*(?:\[[^\]]*\])?\s*(?:is\s+back\s+into\s+the\s+attack|comes\s+into\s+the\s+attack|begins\s+a\s+new\s+spell)/i', $rawTrimmed, $m)) {
            $bowler = trim($m[1]);
            $aiEng = "Bowling Change: Captain brings {$bowler} into the attack to break the partnership and restrict runs.";
            $aiHin = "गेंदबाजी में बदलाव: कप्तान ने साझेदारी को तोड़ने और रनों पर रोक लगाने के लिए {$bowler} को गेंदबाजी सौंपी है।";
            $aiHn = "Bowling Change: Captain ne partnership todne aur runs rokne ke liye {$bowler} ko bowling par lagaya hai.";
            $tagEn = 'Bowling Change';
            $tagHi = 'गेंदबाजी बदलाव 🎳';
            $tagHn = 'Bowling Change 🎳';
        }
        // Pattern 2: New batter arrives
        elseif (preg_match('/^([A-Za-z\s.\-]+?),\s*(?:left|right)[\s\-]+handed\s+bat,\s*comes\s+to\s+the\s+crease/i', $rawTrimmed, $m)) {
            $batter = trim($m[1]);
            $aiEng = "Batter In: {$batter} walks out to the pitch, tasked with anchoring the innings and building momentum.";
            $aiHin = "नए बल्लेबाज क्रीज पर: {$batter} बल्लेबाजी के लिए मैदान में उतरे हैं, टीम को इनसे एक मजबूत पारी की उम्मीद होगी।";
            $aiHn = "Naye batsman crease par: {$batter} batting ke liye utre hain, team ko inke experience aur achhi partnership ki zaroorat hai.";
            $tagEn = 'New Batter';
            $tagHi = 'नया बल्लेबाज 🏏';
            $tagHn = 'New Batter 🏏';
        }
        // Pattern 3: Drinks break
        elseif (preg_match('/(?:drink|drinks|hydration\s+break)/i', $rawTrimmed)) {
            $aiEng = "Drinks Interval: Players take a hydration pause as team management and captains review on-field tactics.";
            $aiHin = "ड्रिंक्स ब्रेक: मैदान पर खिलाड़ियों के लिए ड्रिंक्स ब्रेक, दोनों टीमें पानी पीकर मैच की अगली रणनीति पर चर्चा कर रही हैं।";
            $aiHn = "Drinks Break: Maidan par drinks break, dono teams paani peekar aage ki strategy bana rahi hain.";
            $tagEn = 'Drinks Break';
            $tagHi = 'ड्रिंक्स ब्रेक 🥤';
            $tagHn = 'Drinks Break 🥤';
        }
        // Pattern 4: Review / DRS / LBW
        elseif (preg_match('/(?:review|drs|lbw|snicko|ultra\s*edge)/i', $rawTrimmed)) {
            $para = $this->paraphraseEnglish($rawTrimmed);
            $aiEng = "DRS & Review: " . $para;
            $hi = $this->translateToHindi($para ?: $rawTrimmed);
            $aiHin = "डीआरएस व रिव्यू: " . $hi;
            $aiHn = "DRS Review: Decision third umpire ke paas refer kiya gaya hai, screen par ball tracking check ho rahi hai.";
            $tagEn = 'DRS Review 📺';
            $tagHi = 'डीआरएस रिव्यू 📺';
            $tagHn = 'DRS Review 📺';
        }
        // Pattern 5: General analysis / observation note - ALWAYS fully rewritten
        else {
            $para = $this->paraphraseEnglish($rawTrimmed);
            $aiEng = $para;
            $hi = $this->translateToHindi($para);
            $aiHin = "मैच विश्लेषण: " . $hi;
            $aiHn = $this->craftHinglishNote($rawTrimmed);
            $tagEn = 'Tactical Note';
            $tagHi = 'मैच अपडेट 📋';
            $tagHn = 'Match Note 📋';
        }

        $item['desc'] = $aiEng;
        $item['rawText'] = $aiEng;
        $item['ai_hindi'] = $aiHin;
        $item['ai_hinglish'] = $aiHn;
        $item['title'] = 'Match Note';
        $item['title_hi'] = 'मैच नोट';
        $item['title_hinglish'] = 'Match Note';
        $item['tag'] = $tagEn;
        $item['tag_hi'] = $tagHi;
        $item['tag_hinglish'] = $tagHn;
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Live Match Desk';

        return $item;
    }

    /**
     * Paraphrase English text to ensure original, non-verbatim wording.
     * Guaranteed to change sentence vocabulary and structure even when external APIs are unavailable.
     */
    public function paraphraseEnglish(string $text): string
    {
        $text = trim(strip_tags($text));
        if (empty($text) || strlen($text) < 10) {
            return $text;
        }

        // Apply robust lexical and phrase transformation
        $rewritten = $this->rewriteCricketText($text);

        // Try external network paraphrasing as an additional layer if available
        $cacheKey = 'para_en_' . md5($text);
        try {
            return Cache::remember($cacheKey, 86400 * 7, function () use ($text, $rewritten) {
                try {
                    $urlDe = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=de&dt=t&q=' . urlencode($text);
                    $resDe = Http::timeout(2)
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
                            $resEn = Http::timeout(2)
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
                                $enText = trim($enText);
                                if (!empty($enText) && strtolower($enText) !== strtolower($text)) {
                                    return "Tactical Desk Update: " . $enText;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}

                return $rewritten;
            });
        } catch (\Throwable $e) {
            return $rewritten;
        }
    }

    /**
     * Rule-based intelligent cricket text rewriter to replace verbatim wording with original prose.
     */
    protected function rewriteCricketText(string $text): string
    {
        $phraseMap = [
            'will be disappointed with' => 'will be deeply frustrated by',
            'will be kicking themselves' => 'are heavily ruing',
            'batting display' => 'showing with the bat',
            'batting performance' => 'top-order effort',
            'morning session' => 'opening session of the day',
            'afternoon session' => 'post-lunch session',
            'evening session' => 'final session before stumps',
            'uneven bounce' => 'variable bounce off the surface',
            'good length' => 'disciplined channel outside off',
            'comes into the attack' => 'is drafted into the bowling crease',
            'is back into the attack' => 'returns for a fresh spell of bowling',
            'comes to the crease' => 'takes guard at the striker\'s end',
            'taking a drinks break' => 'pausing for tactical on-field hydration',
            'taking a review' => 'challenging the on-field decision via DRS',
            'under pressure' => 'feeling the squeeze under intense fielding',
            'in trouble' => 'in serious bother on the scoreboard',
            'collapse' => 'dramatic tumble of wickets',
            'great shot' => 'glorious strokeplay through the gap',
            'pitch report' => 'surface conditions assessment',
        ];

        $synonymMap = [
            '/\bdisappointed\b/i' => 'aggrieved',
            '/\bfrustrated\b/i' => 'tested',
            '/\blosing\b/i' => 'surrendering',
            '/\buneven\b/i' => 'unpredictable',
            '/\bpitch\b/i' => 'surface',
            '/\bcaptain\b/i' => 'skipper',
            '/\bwicket\b/i' => 'breakthrough',
            '/\bwickets\b/i' => 'scalps',
            '/\bquick\b/i' => 'rapid',
            '/\bpressure\b/i' => 'heat',
            '/\bbowling\b/i' => 'attack',
            '/\bbatters\b/i' => 'batsmen',
        ];

        $transformed = $text;
        foreach ($phraseMap as $from => $to) {
            $transformed = str_ireplace($from, $to, $transformed);
        }

        foreach ($synonymMap as $regex => $to) {
            $transformed = preg_replace($regex, $to, $transformed, 2);
        }

        $prefixes = [
            'Tactical Desk: ',
            'From the Match Desk: ',
            'On-Field Analysis: ',
            'Game Flow Note: ',
        ];
        $prefix = $prefixes[abs(crc32($text)) % count($prefixes)];

        return $prefix . ucfirst($transformed);
    }

    /**
     * Craft conversational cricket Hinglish for tactical notes.
     */
    protected function craftHinglishNote(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'wicket') && str_contains($lower, 'run')) {
            return "Match Desk Note: Batting side ne lagatar wickets gawaye hain, pitch par bounce aur movement ke chalte pressure kafi badh chuka hai.";
        } elseif (str_contains($lower, 'rain') || str_contains($lower, 'weather')) {
            return "Mausam Update: Match me mausam ke chalte rukawat aayi hai, ground staff covers ke sath tayyar hai.";
        } elseif (str_contains($lower, 'injury') || str_contains($lower, 'physio')) {
            return "Injury Update: Maidan par physio bula liya gaya hai, player ki fitness check ho rahi hai.";
        }

        return "Match Analysis Note: Match me kafi tight contest chal raha hai, dono teams apni strategy par focus kar rahi hain.";
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
                    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=hi&dt=t&q=' . urlencode($text);
                    $response = Http::timeout(2)
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
                } catch (\Throwable $e) {}

                return $text;
            });
        } catch (\Throwable $e) {
            return $text;
        }
    }
}
