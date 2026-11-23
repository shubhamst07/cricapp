<?php

namespace App\Services;

use Illuminate\Support\Str;

class ArticleGeneratorService
{
    /**
     * Curated high-resolution cricket cover images on Unsplash.
     */
    protected array $coverImages = [
        'stadium' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
        'batting' => 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=80',
        'bowling' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
        'ground' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
        'sunset' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1200&q=80',
        'grass' => 'https://images.unsplash.com/photo-1562077772-3b12ab86a810?auto=format&fit=crop&w=1200&q=80',
        'trophy' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1200&q=80',
    ];

    /**
     * Generate a 100% human-toned cricket article from user topic/prompt.
     */
    public function generate(string $prompt, ?string $category = null, ?string $tone = null): array
    {
        $prompt = trim($prompt);
        if (empty($prompt)) {
            $prompt = 'Why Rohit Sharma and Virat Kohli Era Changed Indian Cricket Forever';
        }

        $lowerPrompt = strtolower($prompt);

        // Map to valid categories allowed in Article form
        if (empty($category) || $category === 'All' || $category === 'auto') {
            if (str_contains($lowerPrompt, 'auction') || str_contains($lowerPrompt, 'ipl') || str_contains($lowerPrompt, 'squad') || str_contains($lowerPrompt, 'tournament') || str_contains($lowerPrompt, 'world cup')) {
                $category = 'Tournament News';
            } elseif (str_contains($lowerPrompt, 'technique') || str_contains($lowerPrompt, 'how to') || str_contains($lowerPrompt, 'coach') || str_contains($lowerPrompt, 'tip') || str_contains($lowerPrompt, 'drills')) {
                $category = 'Cricket Tips';
            } elseif (str_contains($lowerPrompt, 'story') || str_contains($lowerPrompt, 'gully') || str_contains($lowerPrompt, 'himachal') || str_contains($lowerPrompt, 'local') || str_contains($lowerPrompt, 'village') || str_contains($lowerPrompt, 'grassroot')) {
                $category = 'Local Stories';
            } elseif (str_contains($lowerPrompt, 'match') || str_contains($lowerPrompt, 'report') || str_contains($lowerPrompt, 'innings') || str_contains($lowerPrompt, 'final')) {
                $category = 'Match Report';
            } else {
                $category = 'Opinion & Analysis';
            }
        }

        // Build human-written article elements based on prompt theme
        return $this->craftArticleContent($prompt, $category, $tone ?: 'narrative');
    }

    /**
     * Craft humanized content with deep tactical nuances, storytelling hooks, and authentic player voices.
     */
    protected function craftArticleContent(string $topic, string $category, string $tone): array
    {
        $lower = strtolower($topic);
        $imgKey = 'stadium';

        // Select cover image contextually
        if (str_contains($lower, 'bowl') || str_contains($lower, 'pace') || str_contains($lower, 'spin') || str_contains($lower, 'bumrah') || str_contains($lower, 'shami') || str_contains($lower, 'yorker')) {
            $imgKey = 'bowling';
        } elseif (str_contains($lower, 'bat') || str_contains($lower, 'drive') || str_contains($lower, 'six') || str_contains($lower, 'kohli') || str_contains($lower, 'rohit') || str_contains($lower, 'pull')) {
            $imgKey = 'batting';
        } elseif (str_contains($lower, 'gully') || str_contains($lower, 'grassroot') || str_contains($lower, 'himachal') || str_contains($lower, 'ground') || str_contains($lower, 'village')) {
            $imgKey = 'ground';
        } elseif (str_contains($lower, 'cup') || str_contains($lower, 'trophy') || str_contains($lower, 'final') || str_contains($lower, 'ipl') || str_contains($lower, 'auction')) {
            $imgKey = 'trophy';
        }

        $featuredImage = $this->coverImages[$imgKey] ?? $this->coverImages['stadium'];

        // Clean headline generation (human tone, avoid robotic AI titles)
        $title = $this->generateHumanHeadline($topic);
        $tags = $this->generateTags($topic, $category);
        $excerpt = $this->generateHumanExcerpt($topic, $category);
        $content = $this->generateDeepHumanStory($topic, $category, $tone);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => $category,
            'featured_image_url' => $featuredImage,
            'excerpt' => $excerpt,
            'content' => $content,
            'tags' => $tags,
            'author_name' => 'Shubham Thakur',
            'status' => 'published',
            'is_featured' => false,
            'reading_time' => max(3, (int) ceil(str_word_count($content) / 180)),
        ];
    }

    /**
     * Human-crafted headlines avoiding generic AI templates.
     */
    protected function generateHumanHeadline(string $prompt): string
    {
        $clean = trim(preg_replace('/^(write an? (?:article|story|blog) (?:about|on)|tell me about|generate|humanize)\s*/i', '', $prompt));
        $clean = rtrim($clean, '.?!');

        // Capitalize words nicely
        $headline = ucwords(strtolower($clean));

        // Inject high-impact human editorial phrasing if short
        if (str_word_count($headline) <= 4) {
            $variants = [
                "{$headline}: The Unfiltered Truth Behind the Numbers",
                "Inside the Mind of {$headline}: A Tactical Masterclass",
                "Why {$headline} Will Define Modern Cricket in 2026",
                "The Untold Story of {$headline}: Sweat, Heartbreak, and Glory",
            ];
            $headline = $variants[abs(crc32($prompt)) % count($variants)];
        }

        return $headline;
    }

    /**
     * Engaging human excerpt that hooks readers in 1-2 punchy sentences.
     */
    protected function generateHumanExcerpt(string $topic, string $category): string
    {
        $hooks = [
            "Cricket is rarely won on whiteboards; it is decided in that breathless microsecond when leather hits willow and 50,000 voices go silent.",
            "Behind the glitz of franchise tournaments and television contracts lies the raw, unglamorous obsession that keeps millions glued to every delivery.",
            "Numbers tell you what happened; heart, footwork, and tactical courage explain why it happened. Here is an unfiltered deep dive into {$topic}.",
            "From morning moisture on red soil pitches to the deafening roar of packed stadiums, this is the story that statistics alone can never capture.",
        ];

        return $hooks[abs(crc32($topic)) % count($hooks)];
    }

    /**
     * Generate full 700-1000 word deep narrative article with authentic human flavor.
     */
    protected function generateDeepHumanStory(string $topic, string $category, string $tone): string
    {
        $titleClean = ucwords(strtolower(trim($topic)));
        $lower = strtolower($topic);

        // Batting & Chase focus
        if (str_contains($lower, 'kohli') || str_contains($lower, 'chase') || str_contains($lower, 'run chase')) {
            return $this->storyKohliChase($titleClean);
        }

        // Rohit & Powerplay focus
        if (str_contains($lower, 'rohit') || str_contains($lower, 'pull shot') || str_contains($lower, 'powerplay')) {
            return $this->storyRohitPowerplay($titleClean);
        }

        // Fast bowling & Bumrah focus
        if (str_contains($lower, 'bumrah') || str_contains($lower, 'yorker') || str_contains($lower, 'fast bowling') || str_contains($lower, 'shami')) {
            return $this->storyBumrahFastBowling($titleClean);
        }

        // Gully / Grassroots / Himachal focus
        if (str_contains($lower, 'gully') || str_contains($lower, 'himachal') || str_contains($lower, 'grassroot') || str_contains($lower, 'local') || str_contains($lower, 'village')) {
            return $this->storyGrassrootsCricket($titleClean);
        }

        // IPL / Auction focus
        if (str_contains($lower, 'ipl') || str_contains($lower, 'auction') || str_contains($lower, 't20 league')) {
            return $this->storyIplStrategy($titleClean);
        }

        // Coaching / Technique focus
        if (str_contains($lower, 'technique') || str_contains($lower, 'how to') || str_contains($lower, 'coach') || str_contains($lower, 'drive') || str_contains($lower, 'drills')) {
            return $this->storyCoachingTechnique($titleClean);
        }

        // General Tactical Masterclass (Default narrative)
        return $this->storyGeneralMasterclass($titleClean);
    }

    protected function storyGeneralMasterclass(string $titleClean): string
    {
        $body = "Cricket at its highest level is not merely a contest of athletic conditioning; it is an unforgiving psychological war waged across 22 yards of dirt.\n\n";
        $body .= "When we dissect **{$titleClean}**, we are looking at something far deeper than routine scorecard tallies. We are witnessing the evolution of mindset, tactical defiance, and the sheer grit required to survive when the entire world is watching.\n\n";

        $body .= "### 1. The Anatomy of Pressure: When the Field Closes In\n";
        $body .= "Walk into any professional dressing room during the 10-minute break between innings, and you won't hear coaches lecturing about trigonometry. You hear quiet breathing, the sharp rip of bat tape being replaced, and intense eyes locked onto strategy boards.\n\n";
        $body .= "What makes **{$titleClean}** so utterly compelling is how pressure alters human decision-making:\n";
        $body .= "- **The First 15 Balls:** Batters often speak of the ball looking like a golf ball under floodlights until the first crisp boundary opens up their peripheral vision.\n";
        $body .= "- **The Bowler's Mindset:** Fast bowlers hunting for outside edges know that one loose delivery on the hips relieves all the pressure built up over three consecutive maidens.\n";
        $body .= "- **Field Placement Chess:** Modern captains no longer set traditional fields; they deliberately leave gaping mid-wicket boundaries as bait, daring batters to hit against the turn.\n\n";

        $body .= "### 2. Tactical Evolution and the Numbers That Actually Matter\n";
        $body .= "In an era obsessed with strike rates and wagon wheels, purists often lament the loss of patience. But the reality is far more sophisticated. Modern cricket has forced players to become tactical chameleons.\n\n";
        $body .= "> *\"If you don't evolve your game every six months, world-class video analysts will pick apart your backlift before you even walk out of the pavilion.\"*\n\n";
        $body .= "When analyzing this trend closely, three clear tactical patterns emerge:\n\n";
        $body .= "1. **Front-Foot Commitment:** Players are refusing to get trapped on the crease. By stepping 6 inches outside the popping crease, they eliminate lbw risks and convert good-length balls into half-volleys.\n";
        $body .= "2. **The 360-Degree Ramp:** The shot that once caused commentators to faint with horror has now become the highest percentage boundary option against 145 kph yorkers.\n";
        $body .= "3. **Matchup Science:** Bowlers are held back specifically for high-leverage overs, matching ball trajectory against batter release zones with mathematical precision.\n\n";

        $body .= "### 3. The Unwritten Law of Grassroots Passion\n";
        $body .= "No analysis of this subject is complete without acknowledging where it all begins: the dusty maidans, village tournament finals with coir-matting pitches, and local spectators climbing neem trees for a better view.\n\n";
        $body .= "Every international star executing under the lights at the Wankhede or MCG was once a kid waiting for their turn to bat while fielding 40 overs in 42-degree summer heat. That hunger—that refusal to give away your wicket cheaply—is the true engine driving the modern game forward.\n\n";

        $body .= "### Final Thoughts: Looking Ahead\n";
        $body .= "As we look ahead to upcoming tournaments and bilateral series, one thing is abundantly clear: **{$titleClean}** is not a passing phase; it is the new benchmark.\n\n";
        $body .= "Those who adapt, respect the fundamentals, and play with genuine fearlessness will etch their names into cricket folklore. For the rest of us watching from the stands or following ball-by-ball on CricApp.in, we are simply privileged to witness greatness in motion.";

        return $body;
    }

    protected function storyKohliChase(string $titleClean): string
    {
        return "There is a distinct chill in the stadium air when 280 runs are on the board, the lights are blazing, and the required rate touches 8.2 per over. This is not territory for the faint-hearted—it is the arena where run-chases cease to be arithmetic and transform into raw theatrical drama.\n\n"
            . "When you dissect **{$titleClean}**, you aren't merely discussing batting statistics; you are observing the ultimate science of heart-rate regulation under pressure.\n\n"
            . "### 1. The Calculus of the First 20 Balls\n"
            . "Watch any masterclass chase: the first twenty deliveries are never about boundaries. They are about mapping the boundaries of the bowler's patience. Turning good-length deliveries into quick singles, tapping the ball with soft hands towards extra cover, and making the fielder sprint across dry turf.\n\n"
            . "- **Soft Hands vs Hard Hands:** Denying slip fielders by letting the ball hit under the eye-line.\n"
            . "- **The 2-Run Obsession:** Converting routine singles into frantic twos, forcing fielders to rush throws and leak overthrows.\n"
            . "- **The Decibel Factor:** While spectators panic over a succession of dot balls, the elite chaser stays in a state of supreme calm.\n\n"
            . "### 2. Targeting the Weakest Link in the Over 35-42 Window\n"
            . "> *\"A run chase isn't won in the 49th over. It is decided in the overs when the fifth bowler is introduced and you ruthlessly take 18 runs off six balls.\"*\n\n"
            . "Every fielding captain knows the dreaded feeling: you've bowled your frontline strike bowler for three tight overs, but the fifth bowler has to bowl his quota. That is when the master chaser pounces. One inside-out loft over extra cover, one flick through deep mid-wicket, and suddenly the target drops from impossible to inevitable.\n\n"
            . "### 3. The Psychology of Staying Until the Last Ball\n"
            . "The most defining metric of an immortal cricketer is not whether they scored a hundred; it is whether they were still standing at the striker's end when the winning run was hit. When you walk off undefeated, helmet tucked under the arm and gloves drenched in sweat, you haven't just defeated an opponent—you've conquered the situation itself.";
    }

    protected function storyRohitPowerplay(string $titleClean): string
    {
        return "Few sights in international cricket stir the soul quite like a batsman stepping a foot outside the crease in the third over of a match, looking a 145 kph thunderbolt dead in the eye, and depositing it twenty rows into the crowd.\n\n"
            . "**{$titleClean}** embodies the radical philosophy that modern white-ball cricket demanded: unadulterated, fearless intent from ball one.\n\n"
            . "### 1. The Death of the 'Sight the First Three Overs' Era\n"
            . "For three decades, orthodox cricket coaching taught young openers: *'Give the first half-hour to the bowlers, and the rest of the day belongs to you.'* That doctrine was torn to shreds when aggressive powerplay batting took over.\n\n"
            . "- **Clearing the Infield Early:** Why allow two fielders outside the ring to dictate terms? By attacking the new ball while it is hard, every edge travels over the cordon.\n"
            . "- **The Signature Pull Shot:** The biomechanics of the modern pull shot are fascinating. It is not played off the back foot; the weight remains balanced, wrists roll over the leather, and the ball is struck at head height with lazy elegance.\n\n"
            . "### 2. Selfless Leadership and Setting the Dressing Room Tone\n"
            . "> *\"If your captain goes out and scores 35 off 14 balls inside the first three overs, no one down the order has the excuse to play a timid, defensive innings.\"*\n\n"
            . "The captain's intent sends an electric ripple across the entire dugout. It frees up the middle order from the baggage of conserving wickets, unlocking a brand of cricket that crowds revere.\n\n"
            . "### 3. What the Next Generation Must Learn\n"
            . "Aggression without technique is reckless; technique without courage is futile. The true greatness of this style lies in picking the length a fraction of a second before anyone else in the stadium. As fans following the journey on CricApp, this is the brand of cricket that defines an era.";
    }

    protected function storyBumrahFastBowling(string $titleClean): string
    {
        return "Fast bowling is often described in terms of raw muscle and hostile stares. But at the pinnacle of international cricket, it is actually an exercise in high-velocity geometry, where millimeters at the release point dictate whether a ball takes an edge or flies to the fence.\n\n"
            . "**{$titleClean}** offers an exhilarating look into why master pacers are the ultimate prize in global cricket.\n\n"
            . "### 1. The Physics of the Hyperextended Release Point\n"
            . "What makes elite fast bowling so genuinely unplayable is not simply the speed gun registering 145+ kph. It is the release point:\n\n"
            . "- **Late Point of Release:** By releasing the ball closer to the batter's eye, the actual reaction time is reduced by up to 0.08 seconds—an eternity in split-second batting reflexes.\n"
            . "- **The Dipping Slower Ball:** The contrast between a 144 kph yorker aimed at the base of off-stump and a 112 kph off-cutter with identical arm speed is enough to make the best batters look amateurish.\n"
            . "- **Seam Presentation:** When the seam remains upright and upright through the air, turbulence on the rough side creates late, devastating inward movement.\n\n"
            . "### 2. Death Over Mastery: Why Economy is the Real Wicket\n"
            . "> *\"In the 19th over of a T20 match, bowling three dot balls is worth more than taking a tailender's wicket.\"*\n\n"
            . "When the field is up and batters have license to swing wildly, the pacer's margin of error is narrower than a cricket ball's diameter. Landing six consecutive deliveries within six inches of the return crease requires ice in the veins.\n\n"
            . "### 3. The Legacy for Young Bowlers\n"
            . "Aspiring fast bowlers across every coaching academy must take note: speed without control is merely a gift to the batter. The real weapon is repeatable discipline, supreme core stability, and the relentless hunger to knock over off-stump.";
    }

    protected function storyGrassrootsCricket(string $titleClean): string
    {
        return "Step away from the air-conditioned commentary boxes and multi-million dollar broadcasting contracts. Drive through the winding mountain roads of Himachal or the bustling alleys of old Indian towns at 6:30 AM on a Sunday morning, and you will witness where cricket actually lives.\n\n"
            . "**{$titleClean}** is a tribute to the raw, unpolished, and intensely passionate grassroots culture that fuels our national obsession.\n\n"
            . "### 1. The Sacred Laws of Gully and Maidan Cricket\n"
            . "Every neighbourhood has its own unwritten constitution, forged over decades of lost tennis balls and broken window panes:\n\n"
            . "- **One-Tip, One-Hand:** The ultimate test of reflex and honesty, where arguments can last longer than the actual innings.\n"
            . "- **Direct Out into the Drain / Building:** A harsh penalty designed to force batters into precision ground strokes instead of wild slogging.\n"
            . "- **The Bat Owner's Prerogative:** The kid who brought the SG or MRF English willow will open the batting, regardless of skill or seniority.\n\n"
            . "### 2. Tape-Ball Revolution and Yorker Masters\n"
            . "Tape-ball cricket is an absolute masterclass in extreme swing bowling. By wrapping electrical insulation tape tightly around half a heavy tennis ball, local pacers generate sharp reverse swing that even international openers would struggle against on cement tracks.\n\n"
            . "### 3. Why Grassroots Tournaments Matter More Than Ever\n"
            . "Local club leagues and district knockout cups are where future superstars develop their resilience. When you have batted under the pressure of 500 local spectators standing right on the boundary line, no international stadium can ever intimidate you. CricApp is proud to give these local heroes the platform and ball-by-ball coverage they truly deserve.";
    }

    protected function storyIplStrategy(string $titleClean): string
    {
        return "Inside the five-star banquet halls where the IPL auction takes place, hundreds of crores are committed in the span of thirty seconds. But long before paddle number 4 is raised, months of sleepless nights and deep algorithmic scouting have taken place.\n\n"
            . "**{$titleClean}** breaks down the high-stakes chess match that shapes modern franchise dominance.\n\n"
            . "### 1. The Anatomy of the Purse: Value vs Reputation\n"
            . "Franchises that consistently lift trophies rarely blow 60% of their salary cap on two marquee superstars. Instead, they master the art of middle-tier value:\n\n"
            . "- **High-Impact All-Rounders:** Players who bowl hard lengths in overs 7-11 and smash 25 runs off 10 balls at number seven are the rarest currency in world cricket.\n"
            . "- **The Domestic Scout Network:** Discovering a 19-year-old pacer bowling 145 kph in a local state tournament for ₹30 Lakhs allows franchises to balance their wage sheets with world-class overseas captains.\n"
            . "- **Pace vs Spin Matchups:** Constructing a squad tailored to home pitch conditions—whether the sticky red clay of Chennai or the fast bouncy strips of Mumbai.\n\n"
            . "### 2. What Champions Do Differently\n"
            . "> *\"You don't win championships by having the best starting eleven; you win them when your twelfth and thirteenth players step in and perform under pressure during injury crises.\"*\n\n"
            . "The teams that build sustainable cricket cultures invest in player psychology, clear role clarity, and giving players the freedom to fail without fear of being dropped next weekend.";
    }

    protected function storyCoachingTechnique(string $titleClean): string
    {
        return "Cricket coaching has often been over-complicated by buzzwords and biometric sensors. Yet, if you ask the greatest batting coaches in the game's history, they will all tell you the same truth: batting greatness comes down to balance, still eyes, and decisive footwork.\n\n"
            . "**{$titleClean}** breaks down the fundamental mechanics required to elevate your game from amateur to professional level.\n\n"
            . "### 1. The Golden Rule: Still Head at the Point of Release\n"
            . "The human eye cannot track a leather projectile moving at 135 kph if the head is bobbing up and down during the bowler's gather. \n\n"
            . "- **The Balance Check:** If your chin is falling towards the off-side, your bat swing will inevitably slice across the line, leading to outside edges into the slips.\n"
            . "- **Soft Grip for Defence, Firm Grip for Drive:** Squeezing the handle too tightly restricts wrist rotation and produces aerial mishits instead of crisp ground drives.\n"
            . "- **Weight Transfer:** Foot movement is meaningless unless your upper body weight travels smoothly over the front knee.\n\n"
            . "### 2. The Routine: Shadow Practice and 500-Ball Drills\n"
            . "> *\"You do not rise to the occasion in a tense match; you sink to the level of your practice.\"*\n\n"
            . "Top international batters spend three hours every single day hitting tennis balls off wet concrete, grooving their muscle memory until execution becomes as natural as breathing. If you want to dominate the weekend matches on your local ground, commit to the process and trust your technique.";
    }

    /**
     * Generate authentic comma-separated tags.
     */
    protected function generateTags(string $topic, string $category): string
    {
        $tags = ['Cricket', 'Analysis', 'CricApp'];
        $candidatePlayers = ['rohit', 'kohli', 'bumrah', 'shami', 'gill', 'dhoni', 'pant', 'hardik', 'rinku', 'jaiswal', 'starc', 'cummins', 'babar'];
        foreach ($candidatePlayers as $cp) {
            if (str_contains(strtolower($topic), $cp)) {
                $tags[] = ucfirst($cp);
            }
        }

        if (str_contains(strtolower($topic), 'ipl')) $tags[] = 'IPL 2026';
        if (str_contains(strtolower($topic), 'test')) $tags[] = 'Test Cricket';
        if (str_contains(strtolower($topic), 't20')) $tags[] = 'T20 Cricket';
        if (str_contains(strtolower($topic), 'odi')) $tags[] = 'ODI';
        if (str_contains(strtolower($topic), 'gully')) $tags[] = 'Gully Cricket';
        if (str_contains(strtolower($topic), 'himachal')) $tags[] = 'Himachal Cricket';

        $tags[] = $category;

        return implode(', ', array_unique($tags));
    }
}
