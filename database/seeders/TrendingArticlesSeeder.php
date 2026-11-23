<?php

namespace Database\Seeders;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TrendingArticlesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('articles')) {
            return;
        }

        $articles = self::getTrendingArticles();

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }

    /**
     * Curated, authentic human-written cricket articles written from a player & coach perspective.
     */
    public static function getTrendingArticles(): array
    {
        return [
            // 1. T20 Powerplay Philosophy
            [
                'title' => "The Rohit Sharma Powerplay Blueprint: Why Reckless Intent is Actually Smart Cricket",
                'slug' => 'rohit-sharma-t20-powerplay-intent-blueprint',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'For years, Indian white-ball cricket worshipped 45/0 after 6 overs. Here is how Rohit Sharma smashed that template and built a fearless new generation of T20 power hitters.',
                'content' => "Anyone who watched Indian white-ball cricket between 2015 and 2021 remembers the familiar script: bat cautiously through the first six overs, build a 'safe base' of 42 for no loss, and hope someone goes ballistic at the death. It looked tidy on paper, but in knockout cricket against fearless teams like England and Australia, it kept leaving India 25 runs short.\n\nThen came Rohit Sharma's radical ideological shift.\n\n### The Math Behind 65/2 vs 45/0\nModern T20 analytics revealed an uncomfortable truth that traditional coaches hated: having 10 wickets in hand at over 15 is vastly overrated if your run rate is crawling at 7.2. In 120-ball cricket, unused wickets are wasted resource capital.\n\nRohit understood that taking calculated boundary risks in the powerplay—where only two fielders are outside the 30-yard circle—is 300% more statistically efficient than trying to hit sixes against deep defensive rings in the 17th over. Even if India lose two wickets inside 6 overs, being 68/2 sets a launchpad that completely paralyzes the opposition captain's bowling changes.\n\n### The Short-Arm Jab & Head Stillness\nWatch Rohit's trigger movement closely against 145 km/h seam bowling. He no longer takes that deep forward stride that used to pin his front pad in England. He stands upright, keeps his weight balanced on the balls of his feet, and watches the seam out of the bowler's hand. When the ball is even two inches short of a length, he executes the short-arm pull before the bowler has completed their follow-through.\n\n### The Lesson for Club & Tournament Batters\nNext time you open the batting in your local Sunday league on CricApp, stop thinking about preserving your wicket for personal averages. Attack the field restrictions early. If you strike at 180 in the first 4 overs, you force the opposition bowler to abandon their line—and that single mental victory wins matches.",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'Rohit Sharma, T20 Cricket, Powerplay, Batting Intent, Team India',
                'status' => 'published',
                'is_featured' => true,
                'reading_time' => 5,
                'published_at' => Carbon::now()->subHours(6),
            ],

            // 2. Fast Bowling & Reverse Swing
            [
                'title' => "The Lost Art of Reverse Swing: What 35-Over SG Balls on Dusty Indian Maidaans Teach You",
                'slug' => 'lost-art-reverse-swing-sg-ball-indian-pitches',
                'category' => 'Cricket Tips',
                'excerpt' => 'Conventional swing dies after 8 overs on abrasive subcontinental soil. Here is the exact physics, sweat maintenance, and wrist release needed to make an old ball hoop back in.',
                'content' => "There is no more terrifying sight for a top-order batter in domestic cricket than a paceman walking back to their mark with a scuffed, asymmetrical red ball in the 38th over, shining only one side against their trousers with obsessive focus.\n\nConventional outswing belongs to morning moisture and green grass. But reverse swing is born out of dust, heat, abrasive square turnarounds, and pure aerodynamic science.\n\n### The Rough vs Smooth Equation\nTo achieve true reverse swing, one hemisphere of the ball must remain completely dry and rough, while the opposite side is polished until it reflects sunlight like glass. \n\n*The golden rule of club reverse swing:* Never use saliva or water on the rough side. If the rough side absorbs sweat, it gains weight, destroying the boundary-layer pressure differential. Keep the rough side dry, and use only pure forehead sweat to buff the smooth side.\n\n### Adjusting the Release Point\nMost young seamers fail at reverse swing because they try to bowl it with their normal outswing wrist. To get reverse swing:\n1. **Bowl 5 km/h quicker:** Reverse swing requires higher airspeed (typically 125+ km/h) to trip the boundary layer into turbulent airflow earlier on the rough side.\n2. **Slightly wider crease angle:** Running in with a slight angle into the stumps gives the ball natural trajectory before the late inward snap kicks in.\n3. **Fuller length by half a yard:** Because reverse swing happens much later in flight (closer to the batter), a good-length ball will just behave like a straight ball. You must pitch it within 4 to 5 meters of the stumps to give the ball time to dip into the toe.",
                'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Coach Vikram',
                'tags' => 'Fast Bowling, Reverse Swing, Pace, Seam Position, SG Ball',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(1),
            ],

            // 3. Grassroots Mountain Cricket
            [
                'title' => "Cricket at 7,000 Feet: The High-Altitude Magic & Heartaches of Mountain Maidaans in Himachal",
                'slug' => 'cricket-at-7000-feet-himachal-mountain-maidaans',
                'category' => 'Local Stories',
                'excerpt' => 'Thin mountain air carrying sixes 100 meters, icy morning breezes swinging the new ball sideways, and concrete coir-mat pitches: welcome to grassroots cricket in the hills.',
                'content' => "If you have only ever played cricket in the flat plains of Mumbai or the red soil tracks of Bangalore, your first match in the hills of Himachal Pradesh will blow your mind.\n\nPlaying at 2,000 meters above sea level completely bends the fundamental laws of cricket physics.\n\n### The Altitude Effect on Ball Flight\nDue to lower atmospheric density at high altitude, air resistance is roughly 15% lower than at sea level. That means a mis-hit that would comfortably settle into the hands of long-off at the Wankhede Stadium sails 10 rows into the pine trees at Annadale or Ridge Maidan in Shimla.\n\nBowlers who rely on flight and loop find their deliveries drifting flatter and faster, while fast bowlers enjoy an extra yard of perceived carry off the deck.\n\n### The Morning Swing Mirage\nHowever, the first 45 minutes of a 9 AM match in November tell a completely opposite story. Heavy morning mountain mist combined with chilling valley winds creates exaggerated conventional swing that moves the leather ball 2 to 3 feet in the air. Batters who survive the first 5 overs feel like warriors; those who push forward with hard hands find their outside edges flying to third slip before they can blink.\n\n### The Unbreakable Passion of Village Tournaments\nDespite limited flat turf and unforgiving rocky boundaries, the enthusiasm across Solan, Shimla, Mandi, and Dharamshala is unmatched. Local captains organize 32-team knockouts with players walking 5 kilometers uphill carrying heavy kitbags. It is grassroots cricket in its purest, most romantic form—and now with digital scoring on CricApp.in, these mountain heroes finally have their achievements recorded for the world to see.",
                'featured_image' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'Himachal Cricket, Shimla, Grassroots, High Altitude, Ridge Ground',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(2),
            ],

            // 4. Batting Mindset & Strike Rotation
            [
                'title' => "Why Virat Kohli's Middle-Over Strike Rotation is More Lethal Than Boundary Hitting",
                'slug' => 'virat-kohli-middle-overs-running-strike-rotation-art',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'Turning 1s into 2s is not just cardiovascular stamina—it is psychological warfare that suffocates opposition spinners and shatters the fielding captain’s field settings.',
                'content' => "In an era where teenage batters enter the nets obsessing exclusively over no-look sixes and ramp scoops, Virat Kohli's white-ball mastery remains the textbook definition of batting IQ.\n\nWatch any legendary Kohli run chase. He rarely hits more than 3 boundaries between overs 11 and 35. Yet, his strike rate stays unflinchingly above 95 without taking a single aerial risk. How does he do it?\n\n### Soft Hands & Gap Placement\nInstead of smashing spinners with hard forearms, Kohli relaxes his top hand grip upon impact. He nudges good-length balls gently into the vacant space between extra cover and deep point. By dampening the pace, the ball never carries quickly to the boundary rider, giving him and his batting partner an automatic two.\n\n### The Psychological Impact on the Bowler\nWhen a bowler gets hit for a boundary off a bad ball, they reset easily: *'That was a poor ball, I will correct my length.'* \n\nBut when a bowler delivers 6 consecutive immaculate good-length deliveries and still concedes 8 runs because the batters stole two doubles and four quick singles, panic sets in. The bowler starts searching for magic deliveries, bowls short, and eventually gifts the boundary ball on a silver platter.\n\n### Golden Takeaway for Local Cricketers\nIf your team is struggling in the middle overs of 20-over matches on CricApp, stop trying to clear 70-meter ropes on slow pitches. Focus on pushing the ball into the outfield and running hard. A team that scores 45 singles and ten 2s in middle overs will beat a team that hits five sixes and plays 40 dot balls nine times out of ten.",
                'featured_image' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'CricApp Editorial',
                'tags' => 'Virat Kohli, Strike Rotation, Batting Masterclass, Running Between Wickets, ODI Cricket',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(3),
            ],

            // 5. Spin Bowling Masterclass
            [
                'title' => "Drift, Dip, and Revolutions: Why Modern Finger Spinners Must Master Over-Spin on Flat Tracks",
                'slug' => 'modern-finger-spin-drift-dip-revolutions-guide',
                'category' => 'Cricket Tips',
                'excerpt' => 'Side-spin gets you swept or smashed over mid-wicket on hard turf. Here is how releasing the ball with forward revolutions makes it dip violently on the batter.',
                'content' => "Walk into any club cricket ground on a dry weekend afternoon, and you will see countless off-spinners bowling flat darts at 88 km/h. They get frustrated when batters line them up and pull them into the parking lot. \n\nThey complain that the pitch has no turn. But true spin bowling is not about what happens after the pitch—it is about what happens in the air.\n\n### The Magnus Effect: Turning Flight into a Trap\nWhen you impart pure forward over-spin (the seam rotating vertically towards the batter like a bicycle wheel), aerodynamic lift pulls the ball downwards faster than gravity alone. This is known as the Magnus effect.\n\nTo the batter looking down the pitch, the ball appears to be pitching on a driving length. They commit forward onto their front toe with an attacking bat. Suddenly, the ball violently dips 8 inches earlier than expected, forcing them into a clumsy defensive push that lobs straight to silly point or produces an outside edge.\n\n### Generating True Drift\nDrift is the horizontal counterpart of dip. It occurs when a spinner bowls with seam tilted roughly 30 to 45 degrees towards fine leg into the breeze. The rough seam catches the air current, creating a side-force that pulls the ball away from the right-hander before snapping back off the surface.\n\n### Practical Grip Adjustment\n- Place your index and middle fingers wide across the seam, with the knuckle of the third finger acting as the pivot shelf.\n- Keep your thumb completely relaxed; a tight thumb restricts wrist snap.\n- At release point, imagine flicking the seam towards the sky to maximize revolutions per minute (RPM). Aim for 2,200+ RPM for visible dip.",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Anil K. (Former Ranji Spinner)',
                'tags' => 'Spin Bowling, Off Spin, Drift and Dip, Bowling Tips, Cricket Coaching',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(4),
            ],

            // 6. Tennis to Leather Transition
            [
                'title' => "From Tennis Ball Gali Cricket to Leather Ball Tournaments: 5 Habits You Must Unlearn Immediately",
                'slug' => 'tennis-ball-to-leather-ball-cricket-transition-guide',
                'category' => 'Cricket Tips',
                'excerpt' => 'Playing with bottom-hand swings, blind baseball slogs, and lazy footwork will get you injured or out for a duck in leather ball cricket. Here is how to fix your technique.',
                'content' => "Over 90% of cricketers in India begin their journey with a heavy tennis or tape ball in narrow city alleys, colony parks, or school corridors. Tennis ball cricket is fantastic for fast reflexes, flick shots, and boundary hitting. \n\nHowever, when a tennis ball player steps into a formal leather ball club tournament, the transition can be brutal. A 156-gram cork-and-leather ball does not bounce like rubber—it seams, kicks off the crack, and can break fingers if your technique is flawed.\n\n### 1. The Dominant Bottom-Hand Syndrome\nIn tennis ball, batters grip the bat tightly with their bottom hand to generate maximum wrist whip over mid-wicket. In leather ball, an overpowering bottom hand closes the bat face too early, leading to leading edges and spooned catches to cover. \n\n*The Fix:* Grip firmly with your top hand (left hand for right-handers) and let the bottom hand act purely as a stabilizing guide.\n\n### 2. Standing Deep in the Crease\nTennis ball players love camping on their back foot deep inside the popping crease to access pull shots. In leather ball against good swing bowling, staying pinned inside your crease makes you a sitting duck for LBW and bowled dismissals.\n\n### 3. Blind Slogging Across the Line\nIn tennis cricket, you can swipe across the line against a delivery pitching outside off and still clear mid-wicket. If you attempt that against a 125 km/h red leather ball that is holding its line, you will hear the death rattle of your off-stump.\n\n### 4. Head Falling Away to the Off-Side\nWatch yourself in net recordings. If your head is tilting towards the off-side during a pull shot, your body balance is compromised. Keep your eyes level and nose over the ball.\n\n### 5. Respecting the First 10 Deliveries\nA leather ball requires 10 balls of sight adjustment. Watch the seam, gauge the pitch bounce on CricApp live scorecard, and give yourself a chance to build an innings.",
                'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Coach Vikram',
                'tags' => 'Tennis Cricket, Leather Ball, Batting Technique, Cricket Fundamentals, Coaching',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 5,
                'published_at' => Carbon::now()->subDays(5),
            ],

            // 7. Pitch Reading & Toss Tactics
            [
                'title' => "How to Read a Cricket Pitch Before the Toss: Soil Color, Moisture, and the 10-Minute Morning Window",
                'slug' => 'how-to-read-cricket-pitch-before-toss-guide',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'Before you flip the coin on Sunday morning, check these 4 subtle physical signs on the pitch that 95% of club captains completely overlook.',
                'content' => "We have all witnessed it: a club captain wins the toss on a bright, sunny Sunday morning, immediately shouts *'We will bat first!'*, and 45 minutes later their team is reeling at 28 for 5 with the opening bowlers unplayable.\n\nWinning the toss is only an advantage if you actually understand what the 22 yards of turf beneath your spikes is going to do over the next four hours.\n\n### 1. The Thumbnail Moisture Test\nDon't just look at the pitch—get down on one knee and press your thumbnail firmly into the good-length area (about 6 to 8 feet from the stumps).\n- If your nail sinks in easily and leaves a soft, damp indent, subsurface moisture is trapped underneath. The pitch will offer lateral seam movement and uneven sponge bounce for the first 8-10 overs. **Bowl first.**\n- If the surface is rock-solid like sun-baked clay, it has been rolled properly and will play true. **Bat first.**\n\n### 2. Red Soil vs Black Clay Soil\n- **Black soil (Mumbai, Pune, Vidarbha):** Holds moisture longer initially, provides true bounce, but crumbles quickly in afternoon heat, bringing spinners into play by over 15.\n- **Red soil (Chennai, Bangalore):** Dries rapidly, offers explosive bounce for tall fast bowlers, and rewards batters who play on the up.\n\n### 3. Live Grass vs Dead Grass\nGreen, live grass binds the topsoil and prevents crumbling. It gives consistent seam movement with true carry. Dead, white grass (straw-like) is purely cosmetic; it does not hold the ball and usually creates a batting paradise with zero lateral deviation.\n\n### 4. Footwear & Dew Inspection\nIf your sneakers pick up wet dew from the outfield at 8:30 AM, remember that the ball will become damp and slippery by the second innings, making grip almost impossible for spinners. Factor this into your chase strategy before calling heads or tails.",
                'featured_image' => 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'Pitch Report, Toss Tactics, Cricket Captaincy, Match Strategy, Soil Analysis',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(6),
            ],

            // 8. Death Overs Captaincy
            [
                'title' => "Defending 12 Runs in the Final Over: The Field Placements That Actually Work in Club Cricket",
                'slug' => 'defending-12-runs-final-over-club-cricket-captaincy',
                'category' => 'Tournament News',
                'excerpt' => 'Fine leg inside the ring or deep? Protecting the straight boundary vs baiting the mishit: a battle-tested playbook for high-pressure finishes.',
                'content' => "Twelve runs to defend off the final six balls. The opposition dugout is cheering, the batter is tapping their bat with aggressive body language, and your seamer looks like they might pass out from tension.\n\nAs a captain, your field setting in the next 30 seconds will decide whether your team lifts the trophy or goes home empty-handed.\n\n### Step 1: Align Your Field With Your Bowler's Strongest Ball\nThe single biggest mistake captains make in the 20th over is setting a 'generic boundary field' without asking their bowler what ball they intend to execute.\n- If your bowler is aiming for wide outside-off yorkers, do NOT leave deep extra-cover vacant.\n- If your bowler prefers hard into-the-pitch back-of-a-length deliveries into the batter's ribs, bring mid-off inside the ring and push deep mid-wicket and deep square leg back to the fence.\n\n### Step 2: The Golden Rule of Protecting the 'V'\nOver 65% of last-over boundary runs in club cricket come down the ground between long-off and long-on. Batters know that swinging straight offers the lowest chance of an edge. Always have both long-on and long-off straight, roughly 10 yards in from the rope so they can cut off flat sixes and save doubles.\n\n### Step 3: Keep the Pressure on the Non-Striker\nTell your mid-on and mid-off fielders to stand directly on the edge of the circle and look for run-out direct hits. A panic single on ball 1 that results in a run-out changes the entire momentum of the over.\n\n### Step 4: Body Language and One-Word Clarity\nDon't crowd the bowler with 5 different players giving 5 different pieces of advice. Walk over alone, give them a high-five, say: *'Commit 100% to your yorker, I have set the field for you,'* and walk back to your position. Confidence defends totals.",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'CricApp Coaching Desk',
                'tags' => 'Captaincy, Death Overs, Field Setting, Pressure Situations, T20 Strategy',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(7),
            ],

            // 9. Wicketkeeping Standing Up
            [
                'title' => "The Unsung Weapon: Why Standing Up to 130 km/h Medium Pacers is the Ultimate Modern Wicketkeeping Craft",
                'slug' => 'wicketkeeping-standing-up-to-medium-pacers-masterclass',
                'category' => 'Cricket Tips',
                'excerpt' => 'Eliminating charge-down boundary attempts and suffocating modern batters—how MS Dhoni revolutionized the keeper-bowler tactical relationship.',
                'content' => "Watch old international cricket footage from the 1990s: against any bowler above 120 km/h, the wicketkeeper stood 18 yards back with the slips. Batters had free rein to dance down the pitch, convert good-length deliveries into half-volleys, and smash them over the bowler's head.\n\nThen came MS Dhoni, standing right up to the stumps against Joginder Sharma, Dwayne Bravo, and Chris Morris.\n\n### The Psychological Cage for the Batter\nWhen a wicketkeeper stands up to the stumps, the batter's crease becomes a prison. They can no longer:\n1. Charge down the wicket to hit over long-on (instant stumping risk).\n2. Shuffle 2 feet outside off-stump to access the leg side.\n3. Take a head-start single on a mis-field.\n\n### The Glove Technique: Soft Hands vs Reaching\nStanding up to 130 km/h seam is dangerous. The ball wobbles late, cuts off the seam, and travels to your gloves in less than 0.4 seconds.\n- **Do not snatch at the ball:** Keep your elbows bent and let the ball come to your chest. Your hands must give with the ball like catching an egg.\n- **Weight on inside balls of your feet:** If your heels are flat, you cannot react laterally to late leg-cutters.\n- **Head level with the bails:** Your eye level should align with the top of the off-stump as the bowler enters their delivery stride.\n\nIf your local club team on CricApp is giving away too many runs in the middle overs, ask your keeper to put on their shinpads and step up to the stumps. It immediately strangles the opposition's scoring rate.",
                'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Rahul Rawat (Club Wicketkeeper)',
                'tags' => 'Wicketkeeping, MS Dhoni, Gloves Craft, Cricket Skills, Fielding',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(8),
            ],

            // 10. Fitness for Working Professionals
            [
                'title' => "Cricket Fitness for Working Professionals: Build Match Stamina Without a Costly Gym Membership",
                'slug' => 'cricket-fitness-working-professionals-stamina-routine',
                'category' => 'Cricket Tips',
                'excerpt' => 'Balancing a 9-to-6 desk job with competitive Sunday cricket? Here is an effective 20-minute daily routine to bulletproof your hamstrings, shoulders, and core.',
                'content' => "It is a story every club cricketer in India knows by heart: you spend Monday to Friday sitting in an office chair for 9 hours staring at a computer screen. On Sunday morning, you sprint full-tilt for a boundary in the 3rd over, and immediately pull your hamstring or twist your shoulder.\n\nA desk job tightens your hip flexors, deactivates your glutes, and weakens your rotator cuffs. You don't need a ₹30,000 yearly gym membership to fix this—you just need 20 focused minutes every morning.\n\n### The 20-Minute Daily Club Cricketer Circuit\n\n#### 1. Nordic Hamstring Drops & Glute Bridges (5 Minutes)\nHamstring tears are the #1 injury in amateur cricket. Doing 3 sets of 15 single-leg glute bridges and slow eccentric hamstring lowers builds the deceleration strength required to stop when sprinting for a catch.\n\n#### 2. Resistance Band Rotator Cuff External Rotations (5 Minutes)\nBuy a ₹300 resistance band. Anchor it to a door handle. Perform 20 slow external rotations daily with your elbow pinned to your ribcage. This stabilizes the glenohumeral joint and prevents the dreaded bowler's shoulder impingement.\n\n#### 3. Lateral Bound & Hold (4 Minutes)\nCricket is played laterally, not just forward. Jump laterally off your right foot, land softly on your left foot, and hold for 2 seconds. This builds the ankle stabilizer muscles needed when bowling off a hard crease.\n\n#### 4. Interval Sprints (6 Minutes, 2x a week)\nDon't run long 5 km slow jogs—cricket is a game of explosive 20-meter sprints followed by 30 seconds of rest. Find an empty corridor or colony road: sprint hard for 22 yards (pitch length), walk back, and repeat 10 times.\n\nStart this routine today, and by your next Sunday match on CricApp, you will feel 5 years younger in the field.",
                'featured_image' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Dr. Aman Sharma (Sports Physio)',
                'tags' => 'Cricket Fitness, Injury Prevention, Hamstring Strength, Stamina, Health',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(9),
            ],
        ];
    }
}
