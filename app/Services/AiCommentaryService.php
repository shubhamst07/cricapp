<?php

namespace App\Services;

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
            // If already processed by AI, retain it
            if (!empty($item['is_ai']) && !empty($item['ai_hindi'])) {
                $transformed[] = $item;
                continue;
            }

            $type = $item['type'] ?? 'ball';

            if ($type === 'ball') {
                $item = $this->transformBall($item);
            } elseif ($type === 'over-separator') {
                $item = $this->transformOverSeparator($item);
            } else {
                // General note or announcement
                $item['is_ai'] = true;
                $item['ai_flair'] = 'Live Match Desk';
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

        // Replace raw description and rawText with AI output
        $ball['desc'] = $aiEng;
        $ball['rawText'] = "{$bowler} to {$batsman}, " . ($badge === 'W' ? 'OUT' : ($badge . ' runs')) . " - {$aiEng}";
        $ball['ai_hindi'] = $aiHin;
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

        $sep['desc'] = "End of Over {$overNum}: {$runs} runs conceded ({$summary}). " .
            ($runs > 10 ? "An expensive over for the bowling side!" : ($runs <= 3 ? "A very tight and disciplined over." : "Steady passage of play in the middle."));

        $sep['ai_hindi'] = "Over {$overNum} samapt: {$runs} run aaye ({$summary}). " .
            ($runs > 10 ? "Batting team ke liye faydemand over!" : "Bowling team ne tight line banaye rakhi.");

        $sep['is_ai'] = true;
        $sep['ai_flair'] = 'AI Over Recap';

        return $sep;
    }
}
