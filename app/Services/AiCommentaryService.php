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

        // Synthesize dynamic AI commentary across 3 distinct languages:
        // 1. English
        // 2. Pure Devanagari Hindi
        // 3. Hinglish (Roman Hindi)
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
                "आउट! बड़ा झटका! {$bowler} की सटीक गेंद पर {$batsman} चकमा खा गए और पवेलियन लौटना पड़ा।",
                "विकेट! मैच का बड़ा मोड़! {$bowler} ने अपनी चतुराई से {$batsman} को फंसाया और विकेट चटकाया।",
                "आउट! शानदार गेंदबाज़ी! {$bowler} ने टीम को दिलाई बड़ी सफलता, {$batsman} आउट होकर वापस जाते हुए।"
            ];
            $wicketHn = [
                "OUT! Bada jhatka! {$bowler} ne shandar bowling karte hue {$batsman} ko pavilion bhej diya hai.",
                "WICKET! High drama in the middle! {$batsman} ne bada shot khelne ki koshish ki aur wicket gawa baithe.",
                "OUT! Zabardast bowling! {$bowler} ne match me team ki mazboot wapsi karwayi, {$batsman} out!"
            ];
            $choiceIdx = abs(crc32($over . $title)) % count($wicketEn);
            $aiEng = $wicketEn[$choiceIdx];
            $aiHin = $wicketHi[$choiceIdx];
            $aiHinglish = $wicketHn[$choiceIdx];
        } elseif ($isSix) {
            $sixEn = [
                "SIX! That is colossal! {$batsman} picks the length early off {$bowler} and launches it high, handsome and into the crowd!",
                "BOOM! Massive maximum! Clean connection from {$batsman}, towering over the boundary ropes!",
                "SIX! Into the stands! {$batsman} dances down the track against {$bowler} and smokes it all the way!"
            ];
            $sixHi = [
                "छक्का! गगनचुंबी शॉट! {$batsman} ने गेंद की लेंथ को जल्दी भांपा और सीधे दर्शकों के बीच भेज दिया!",
                "शानदार छक्का! गेंद बल्ले के बीचों-बीच लगी और सीमा रेखा के काफी दूर जाकर गिरी!",
                "विशाल छक्का! {$batsman} ने आगे बढ़कर {$bowler} की गेंद को स्टैंड्स में पहुंचाया!"
            ];
            $sixHn = [
                "SIX! Gagan-chumbi chhakka! {$batsman} ne {$bowler} ki delivery par poora bharosa dikhaya aur stadium me bhej diya!",
                "BOOM! Shandar 6 run! Clean connection from {$batsman}, boundary ke bohot door!",
                "SIX! Zabardast hitting! {$batsman} ne aage badhkar seedha stand me mara!"
            ];
            $choiceIdx = abs(crc32($over . $title)) % count($sixEn);
            $aiEng = $sixEn[$choiceIdx];
            $aiHin = $sixHi[$choiceIdx];
            $aiHinglish = $sixHn[$choiceIdx];
        } elseif ($isFour) {
            $fourEn = [
                "FOUR! Cracking stroke! {$batsman} finds the gap with supreme timing off {$bowler}, racing away to the ropes.",
                "FOUR! Pure elegance! Leans into the drive and threads the infield with exquisite perfection.",
                "FOUR! Beautiful boundary! {$batsman} uses the pace of {$bowler} smartly to beat the chasing fielder."
            ];
            $fourHi = [
                "चौका! बेहतरीन टाइमिंग! {$batsman} ने खूबसूरती से गैप ढूंढा और गेंद बिजली की तेजी से सीमा रेखा पार।",
                "लाजवाब चौका! कलाइयों का शानदार इस्तेमाल, फील्डर के पास रोकने का कोई मौका नहीं।",
                "खूबसूरत बाउंड्री! {$batsman} ने गेंद की गति का पूरा फायदा उठाया और 4 रन बटोरे।"
            ];
            $fourHn = [
                "FOUR! Khubsoorat chauka! {$batsman} ne gap dhundha aur gend bijli ki tezi se boundary paar!",
                "FOUR! Lajawab shot! Timing ke sath shot lagaya aur fielder ke paas koi mauka nahi tha.",
                "FOUR! Shandar boundary! {$batsman} ne bowler ki speed ka faayda uthaya aur 4 run bhatore."
            ];
            $choiceIdx = abs(crc32($over . $title)) % count($fourEn);
            $aiEng = $fourEn[$choiceIdx];
            $aiHin = $fourHi[$choiceIdx];
            $aiHinglish = $fourHn[$choiceIdx];
        } elseif ($isWide) {
            $aiEng = "Wide called. {$bowler} strays down leg, and the umpire stretches both arms out for an extra.";
            $aiHin = "वाइड गेंद! {$bowler} लेग स्टंप से काफी बाहर भटके, अंपायर ने दोनों हाथ फैलाकर अतिरिक्त रन का इशारा किया।";
            $aiHinglish = "Wide ball! {$bowler} ne line miss ki, umpire ka ishara extra run ke liye.";
        } elseif ($isNoBall) {
            $aiEng = "No ball signaled! Overstepping from {$bowler}. Extra run conceded and a Free Hit coming up!";
            $aiHin = "नो बॉल! गेंदबाज का पैर क्रीज से बाहर, बल्लेबाजी टीम को मिला 1 अतिरिक्त रन और फ्री हिट का मौका!";
            $aiHinglish = "No ball! {$bowler} ka pair line se bahar, batting team ko mila extra run aur Free Hit!";
        } elseif ($badge === '1') {
            $singleEn = [
                "{$bowler} bowls on a good length, {$batsman} taps it gently towards mid-on for a brisk single.",
                "Pitched up on middle and leg, pushed through the vacant gap to rotate the strike comfortably.",
                "Controlled nudge into the off-side gap, scampering through for an easy single."
            ];
            $singleHi = [
                "{$bowler} की अच्छी लेंथ गेंद, {$batsman} ने हल्के हाथों से मिड-ऑन की तरफ धकेलकर 1 रन लिया।",
                "पैड पर आती हुई गेंद, फ्लिक करके खाली जगह में खेला और आसानी से स्ट्राइक रोटेट की।",
                "कंट्रोल्ड शॉट, ऑफ साइड में खेलकर दौड़ते हुए 1 रन पूरा किया।"
            ];
            $singleHn = [
                "{$bowler} ki acchi length delivery, {$batsman} ne single lekar strike rotate ki.",
                "Middle stump par delivery, {$batsman} ne aaram se 1 run poora kiya.",
                "Controlled nudge into the off-side gap, scampering through for an easy single."
            ];
            $choiceIdx = abs(crc32($over . $title)) % count($singleEn);
            $aiEng = $singleEn[$choiceIdx];
            $aiHin = $singleHi[$choiceIdx];
            $aiHinglish = $singleHn[$choiceIdx];
        } elseif ($badge === '2' || $badge === '3') {
            $aiEng = "Good running between the wickets! {$batsman} pushes it wide of the fielder and hustles back hard for {$badge} runs.";
            $aiHin = "विकेटों के बीच बेहतरीन दौड़! {$batsman} ने गेंद को गैप में खेला और फुर्ती से दौड़कर {$badge} रन पूरे किए।";
            $aiHinglish = "Kamaal ki running between the wickets! {$batsman} ne tej daud lagakar {$badge} run poore kiye.";
        } else {
            // Dot Ball
            $dotEn = [
                "Good length delivery around off-stump, {$batsman} plays it with soft hands straight to the fielder.",
                "Beaten! Lovely seam movement from {$bowler}, beating the defensive push.",
                "Right in the blockhole! {$batsman} jams the bat down in time to dig it out safely.",
                "Tidy line and length from {$bowler}, keeping {$batsman} quiet on the backfoot."
            ];
            $dotHi = [
                "ऑफ स्टंप पर अच्छी लेंथ की गेंद, {$batsman} ने रक्षात्मक तरीके से खेला, कोई रन नहीं।",
                "शानदार स्विंग! गेंद टप्पा खाकर बल्लेबाज के बल्ले को छकाती हुई निकली, डॉट गेंद।",
                "सटीक यॉर्कर! {$batsman} ने सही समय पर बल्ला नीचे लाकर गेंद को रोका, कोई रन नहीं।",
                "अनुशासित लाइन और लेंथ, बल्लेबाज को बैकफुट पर रहकर खेलने पर मजबूर किया, डॉट गेंद।"
            ];
            $dotHn = [
                "Off-stump par badhiya delivery, {$batsman} ne defend kiya, koi run nahi.",
                "Beaten! Shandar swinging delivery, batsman beat hue, dot ball.",
                "Blockhole me delivery! {$batsman} ne samay par bat lagaya, safe rahe, koi run nahi.",
                "Tidy line aur length, batsman ko quiet rakha, dot ball."
            ];
            $choiceIdx = abs(crc32($over . $title)) % count($dotEn);
            $aiEng = $dotEn[$choiceIdx];
            $aiHin = $dotHi[$choiceIdx];
            $aiHinglish = $dotHn[$choiceIdx];
        }

        $actionEn = 'no run';
        if ($isWicket) {
            $actionEn = 'OUT';
            $titleHi = "{$bowler} vs {$batsman}, आउट (WICKET)";
            $titleHn = "{$bowler} to {$batsman}, OUT (Wicket)";
            $tagHi = 'विकेट ☝️';
            $tagHn = 'Wicket ☝️';
        } elseif ($isSix) {
            $actionEn = 'SIX';
            $titleHi = "{$bowler} vs {$batsman}, छक्का (SIX)";
            $titleHn = "{$bowler} to {$batsman}, 6 Runs (Six)";
            $tagHi = 'छक्का 6';
            $tagHn = 'Six 6';
        } elseif ($isFour) {
            $actionEn = 'FOUR';
            $titleHi = "{$bowler} vs {$batsman}, चौका (FOUR)";
            $titleHn = "{$bowler} to {$batsman}, 4 Runs (Four)";
            $tagHi = 'चौका 4';
            $tagHn = 'Four 4';
        } elseif ($isWide) {
            $actionEn = 'wide';
            $titleHi = "{$bowler} vs {$batsman}, वाइड (Wide)";
            $titleHn = "{$bowler} to {$batsman}, Wide Ball";
            $tagHi = 'अतिरिक्त रन';
            $tagHn = 'Wide Ball';
        } elseif ($isNoBall) {
            $actionEn = 'no ball';
            $titleHi = "{$bowler} vs {$batsman}, नो बॉल (No Ball)";
            $titleHn = "{$bowler} to {$batsman}, No Ball";
            $tagHi = 'नो बॉल';
            $tagHn = 'No Ball';
        } elseif ($badge === '1') {
            $actionEn = '1 run';
            $titleHi = "{$bowler} vs {$batsman}, 1 रन";
            $titleHn = "{$bowler} to {$batsman}, 1 Run";
            $tagHi = '1 रन';
            $tagHn = '1 Run';
        } elseif ($badge === '2' || $badge === '3') {
            $actionEn = "{$badge} runs";
            $titleHi = "{$bowler} vs {$batsman}, {$badge} रन";
            $titleHn = "{$bowler} to {$batsman}, {$badge} Runs";
            $tagHi = "{$badge} रन";
            $tagHn = "{$badge} Runs";
        } else {
            $actionEn = 'no run';
            $titleHi = "{$bowler} vs {$batsman}, कोई रन नहीं";
            $titleHn = "{$bowler} to {$batsman}, No Run";
            $tagHi = 'डॉट गेंद';
            $tagHn = 'Dot Ball';
        }

        // Replace raw description, title, and rawText with clean AI output
        $ball['title'] = "{$bowler} to {$batsman}, {$actionEn}";
        $ball['desc'] = $aiEng;
        $ball['rawText'] = $aiEng;
        $ball['ai_hindi'] = $aiHin;
        $ball['ai_hinglish'] = $aiHinglish;
        $ball['title_hi'] = $titleHi;
        $ball['title_hinglish'] = $titleHn;
        $ball['tag_hi'] = $tagHi;
        $ball['tag_hinglish'] = $tagHn;
        $ball['is_ai'] = true;
        $ball['ai_flair'] = 'LiveCricket AI Engine';

        return $ball;
    }

    /**
     * Transform an over separator into an AI Over Summary across all 3 languages.
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

        $sep['ai_hindi'] = "ओवर {$overNum} समाप्त: {$runs} रन बने ({$summary})। " .
            ($runs > 10 ? "बल्लेबाजी टीम के लिए काफी फायदेमंद ओवर!" : ($runs <= 3 ? "गेंदबाजी टीम का बेहद कसा हुआ और अनुशासित ओवर।" : "मैदान पर सामान्य गति से खेल जारी।"));

        $sep['ai_hinglish'] = "Over {$overNum} samapt: {$runs} run aaye ({$summary}). " .
            ($runs > 10 ? "Batting team ke liye faydemand over!" : ($runs <= 3 ? "Bowling team ka kafi tight over." : "Match me steady khel chal raha hai."));

        $sep['title'] = "End of Over {$overNum}" . ($batTeam && $score ? " • {$batTeam} {$score}" : "");
        $sep['title_hi'] = "ओवर {$overNum} समाप्त" . ($batTeam && $score ? " • {$batTeam} {$score}" : "");
        $sep['title_hinglish'] = "Over {$overNum} Samapt" . ($batTeam && $score ? " • {$batTeam} {$score}" : "");

        $sep['tag'] = "Over {$overNum}";
        $sep['tag_hi'] = "ओवर {$overNum}";
        $sep['tag_hinglish'] = "Over {$overNum}";
        $sep['is_ai'] = true;
        $sep['ai_flair'] = 'AI Over Recap';

        return $sep;
    }

    /**
     * Transform a player quote or interview take into AI commentary across all 3 languages.
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

        // 1. English
        $paraSpeech = $this->paraphraseEnglish($speech);
        $aiEng = "\"{$paraSpeech}\"";

        // 2. Pure Devanagari Hindi
        $hiSpeech = $this->translateToHindi($paraSpeech ?: $speech);
        $aiHin = "{$speaker} ने मैच के बाद अपनी राय साझा करते हुए कहा: \"{$hiSpeech}\"";

        // 3. Hinglish
        $aiHn = "{$speaker} ne match ke baad reaction dete hue kaha: \"{$paraSpeech}\"";

        $item['desc'] = $aiEng;
        $item['rawText'] = $aiEng;
        $item['ai_hindi'] = $aiHin;
        $item['ai_hinglish'] = $aiHn;
        $item['title'] = "{$speaker} (Post-Match Take)";
        $item['title_hi'] = "{$speaker} (मैच के बाद बयान)";
        $item['title_hinglish'] = "{$speaker} (Post-Match Reaction)";
        $item['tag'] = 'Player Quote 🎙️';
        $item['tag_hi'] = 'खिलाड़ी का बयान 🎙️';
        $item['tag_hinglish'] = 'Player Quote 🎙️';
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Post-Match Presentation';

        return $item;
    }

    /**
     * Transform a match wrap-up announcement into AI commentary across all 3 languages.
     */
    protected function transformMatchWrap(array $item): array
    {
        $text = (string) ($item['desc'] ?? $item['rawText'] ?? '');
        $paraText = $this->paraphraseEnglish($text);
        $hiText = $this->translateToHindi($paraText ?: $text);

        $item['desc'] = "Match Wrap: " . $paraText;
        $item['rawText'] = "Match Wrap: " . $paraText;
        $item['ai_hindi'] = "मैच परिणाम सारांश: " . $hiText;
        $item['ai_hinglish'] = "Match Result Summary: " . $paraText;
        $item['title'] = 'Match Summary & Wrap-up';
        $item['title_hi'] = 'मैच सारांश व परिणाम';
        $item['title_hinglish'] = 'Match Result Summary';
        $item['tag'] = 'Match Result 🏆';
        $item['tag_hi'] = 'मैच परिणाम 🏆';
        $item['tag_hinglish'] = 'Match Result 🏆';
        $item['is_ai'] = true;
        $item['ai_flair'] = 'Match Review';

        return $item;
    }

    /**
     * Transform a tactical match note into AI commentary across all 3 languages.
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
            $aiHn = "DRS Review: Fielding team ne review liya hai, decision third umpire ke paas hai.";
            $tagEn = 'DRS Review 📺';
            $tagHi = 'डीआरएस रिव्यू 📺';
            $tagHn = 'DRS Review 📺';
        }
        // Pattern 5: General analysis / observation note
        else {
            $para = $this->paraphraseEnglish($rawTrimmed);
            $aiEng = "Match Desk Analysis: " . $para;
            $hi = $this->translateToHindi($para ?: $rawTrimmed);
            $aiHin = "मैच विश्लेषण: " . $hi;
            $aiHn = "Match Analysis: " . $para;
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
