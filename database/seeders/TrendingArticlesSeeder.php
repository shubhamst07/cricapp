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

            // 11. IPL Auction & Raw Scouting
            [
                'title' => "The ₹10 Crore Uncapped Phenomenon: How IPL Franchises Now Scout Indian Raw Talent in State Leagues",
                'slug' => 'ipl-mega-auction-uncapped-indian-players-scouting-blueprint',
                'category' => 'Tournament News',
                'excerpt' => 'Gone are the days when international names guaranteed auction billions. How Tamil Nadu Premier League, Syed Mushtaq Ali, and local maidaans became the real breeding ground for IPL stardom.',
                'content' => "There was a time when IPL auction rooms were dominated by frantic bidding wars over faded West Indian power-hitters or English all-rounders who would play half the season and fly back home. \n\nToday, the auction tables tell a vastly different story. Multi-million-dollar paychecks are being handed to 21-year-olds from small towns in Vidarbha, Punjab, and Tamil Nadu who have never played an international game.\n\n### The Data Revolution in Domestic Scouting\nFranchises like Mumbai Indians, Chennai Super Kings, and Kolkata Knight Riders no longer rely on newspaper box scores. They employ full-time data analysts and former Ranji veterans who travel with high-speed 240fps cameras to local Syed Mushtaq Ali Trophy matches, TNPL, and UP T20 leagues.\n\nThey aren't just looking at how many runs a batter scored; they are tracking bat-speed metrics, release-point consistency against 140 km/h throwdowns, and ball exit velocity off short balls. If an uncapped batter can consistently clear mid-wicket against hard 8-meter length deliveries, their market value skyrockets instantly.\n\n### The Impact Player Rule Has Accelerated the Shift\nWith the Impact Player rule cementing 8 to 9 specialist batters in every playing XI, utility all-rounders who bowl gentle 118 km/h medium pace are no longer in demand. Franchises want extremes: either an express pacer who clocks 145+ km/h or a pure 200-strike-rate pinch hitter who doesn't mind getting out on 22 off 9 balls.\n\n### What Local Grassroots Players Should Focus On\nIf you are a young player in India tracking your matches on CricApp, remember: consistency alone doesn't get you noticed by scouts—impact does. A match-winning 35 off 14 balls under lights in a tense club final carries ten times more scouting weight than a slow, selfish 60 off 52 balls. Play with intent, play for your team.",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'IPL Auction, Uncapped Players, Scouting, Domestic Cricket, T20',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 5,
                'published_at' => Carbon::now()->subHours(12),
            ],

            // 12. Jasprit Bumrah Action Breakdown
            [
                'title' => "Deconstructing Jasprit Bumrah’s Hyperextension & Short Run-Up: Why You Shouldn't Copy It at Home",
                'slug' => 'jasprit-bumrah-bowling-action-biomechanics-breakdown',
                'category' => 'Cricket Tips',
                'excerpt' => 'Bumrah’s 8-pace run-up and hyper-extended elbow produce lethal 145 km/h thunderbolts. Here is the anatomical science behind why it works for him—and why junior pacers need caution.',
                'content' => "Visit any cricket coaching camp in Delhi, Mumbai, or Himachal, and within 10 minutes you will spot at least two 14-year-olds attempting a stuttering 7-step run-up, locking their right arm rigid, and snapping their wrist like Jasprit Bumrah.\n\nIt looks cool. But from a sports biomechanics perspective, trying to replicate Bumrah’s unique action without his exact physiological blueprint is one of the fastest ways to suffer a lumbar spine stress fracture.\n\n### The Physics of the Short Stutter Run-Up\nMost traditional fast bowlers—from Dennis Lillee to Brett Lee—generate 70% of their bowling speed from linear kinetic momentum built up during a long, rhythmic 25-meter approach. Bumrah generates virtually zero linear momentum from his jog.\n\nInstead, he relies entirely on rotational torque and elastic recoil. His torso rotates aggressively away from the batter before violently snapping square during the front-foot contact. It is the human equivalent of a drawn bow and arrow.\n\n### The Hyperextended Elbow Lever\nBumrah was born with natural congenital hyperextension in his right elbow joint (roughly 15 to 20 degrees of backward flexion). When he releases the ball, his forearm behaves like a longer lever than a normal arm, releasing the ball closer to the batter (around 2.1 meters in front of the popping crease).\n\nTo a batter, a 142 km/h delivery from Bumrah feels like 150 km/h because it arrives 0.05 seconds quicker than a ball bowled by a bowler releasing from behind the crease.\n\n### The Advice for Aspiring Club Bowlers\nDo not try to force an unnatural locked-arm action. Focus instead on Bumrah's repeatable fundamentals: an upright wrist behind the seam, a bracing front knee at delivery stride, and laser-like eye focus on the off-stump bail. That is what creates world-class bowling.",
                'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Dr. Aman Sharma (Sports Physio)',
                'tags' => 'Jasprit Bumrah, Fast Bowling, Biomechanics, Bowling Action, Sports Science',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 5,
                'published_at' => Carbon::now()->subDays(1)->subHours(3),
            ],

            // 13. Border-Gavaskar Fast Bowling Conditions
            [
                'title' => "Surviving the Perth Cracks & Gabba Bounce: What Fast Bowling in Australia Demands From Indian Batters",
                'slug' => 'border-gavaskar-trophy-australia-fast-bowling-conditions',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'It is not the raw speed that gets subcontinental batters in Australia—it is the steep bounce above the roll of the bat. A tactical look at chest-high defense and leaving on length.',
                'content' => "Ask any veteran Indian batter about their first tour to Australia, and they will tell you the same thing: the first time you play a forward defensive stroke at the WACA or Optus Stadium in Perth, the ball hits the manufacturer's sticker at the top of your bat handle, sends a stinging vibration through your thumbs, and loops gently to gully.\n\nIn India, a good-length ball hits the top of off-stump at waist height. In Australia, that exact same length bounces at armpit height. That 18-inch vertical difference is where careers are made or broken.\n\n### The Art of Leaving on Height, Not Just Line\nIn Indian domestic cricket, batters leave balls primarily on line (outside off-stump). In Australia, you must learn to leave balls on height.\n\nWhen Pat Cummins or Mitchell Starc pound the hard deck 7 meters from your stumps, the ball naturally sails over the top of the bails. The secret that helped Cheteshwar Pujara and Rishabh Pant conquer Australia is keeping your hands pulled back close to your chest, resisting the temptation to push out at deliveries you cannot comfortably dominate on the front foot.\n\n### Batting Out of the Crease to Counter Swung Kookaburra\nThe Australian Kookaburra ball swings sharply for only 12 to 15 overs before going soft. By standing 6 to 10 inches outside the popping crease, batters intercept the ball before its maximum arc of swing and cut down the bounce by half a foot.\n\n### Mental Toughness & Body Blows\nYou cannot play Test cricket in Australia without getting hit on the ribs, shoulders, and helmet grille. In the modern game where CricApp tracks every dismissal, understanding that survival against elite pace is a game of patience and courage is the ultimate masterclass.",
                'featured_image' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'CricApp Editorial',
                'tags' => 'Border Gavaskar Trophy, Australia, Test Cricket, Pace and Bounce, Batting Tactics',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(2)->subHours(4),
            ],

            // 14. Hard Length in T20
            [
                'title' => "What Exactly is 'The Hard Length' and Why Every T20 Franchise Wants 6-Foot-2 Bowlers Who Can Hit It",
                'slug' => 'what-is-the-hard-length-in-t20-cricket-explained',
                'category' => 'Cricket Tips',
                'excerpt' => 'Between 6 to 8 meters from the stumps, where the ball is neither full enough to drive nor short enough to pull. How hitting the deck hard took over modern cricket.',
                'content' => "If you have listened to commentary during any recent IPL or international T20 match, you have heard the term 'hard length' repeated fifty times an evening. But what actually separates a 'hard length' from an ordinary good length?\n\n### The 'No-Man’s Land' of Cricket Lengths\nA standard good length is around 5 to 6 meters from the stumps—the zone designed to entice forward drives in Test cricket. However, in modern T20 cricket with 400g bats and 65-meter ropes, a standard good length gets hit straight back over the bowler's head for six.\n\nA short pitch delivery (9+ meters) is easy to spot early and gets pulled over square leg.\n\nThe 'Hard Length' sits exactly in between: **6.8 to 8.2 meters from the stumps**.\n\n### Why It Paralyzes Modern Batters\nWhen a bowler releases the ball into this zone at 135+ km/h:\n1. The batter cannot step forward to drive, because the ball hits the deck too far back and rears up toward their ribs.\n2. The batter cannot rock back to pull, because the ball arrives too quickly and isn't short enough to get under.\n3. The result is a rushed, cramped stroke that hits the splice of the bat and loops to mid-on or cover.\n\n### Height is the Ultimate Multiplier\nThis is why tall fast bowlers like Josh Hazlewood, Prasidh Krishna, and Marco Jansen are so heavily valued. Their steep release height (releasing from 7.5 feet above the ground) makes the hard length ball bounce even more viciously off the pitch. For club bowlers on CricApp, mastering this length is the guaranteed ticket to sub-7 economy rates.",
                'featured_image' => 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Coach Vikram',
                'tags' => 'Hard Length, T20 Bowling, Fast Bowling, Economy Rate, Bowling Tactics',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(3)->subHours(2),
            ],

            // 15. Rishabh Pant Counter Attack
            [
                'title' => "The Method Behind Rishabh Pant’s Madness: Why Chaos Against 90mph Pace is Masterful Counter-Attack",
                'slug' => 'rishabh-pant-test-cricket-counter-attack-genius',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'Charging Jimmy Anderson with the second new ball isn’t reckless ego—it breaks the fielding captain’s ring and forces the slips into defensive positions.',
                'content' => "When Rishabh Pant reversed-swept Jimmy Anderson over the slips for four with an 82-over-old red ball at Ahmedabad, cricket purists nearly dropped their cups of tea. Pundits called it reckless, uncalculated, and disrespectful to Test match traditions.\n\nThey were completely wrong. It was one of the most calculated psychological moves in modern Test history.\n\n### The Strategic Value of Counter-Attack\nIn red-ball cricket, opening bowlers thrive on rhythm and predictable lines. They set four slips, a gully, and bowl repeat deliveries on a fourth-stump corridor. If you defend normally, you are playing entirely on their terms.\n\nPant refuses to let the bowler dictate the terms. By dancing 2 steps down the pitch or backing away to carve through point, he forces the bowler to change their line. The captain is forced to take out third slip and place a deep third man. Suddenly, the bowler has lost their catching cushion, their run-up rhythm is disrupted, and the pressure instantly flips back to the fielding side.\n\n### Low Center of Gravity & Torso Flexibility\nWhat makes Pant's batting work is not blind power—it is extraordinary balance. Even when he falls to his knees while hitting a one-handed six over square leg, his head stays perfectly still over the contact point until long after the ball leaves the bat.\n\n### The Takeaway for Young Attackers\nNever confuse counter-attack with reckless slogging. Pant respects genuine unplayable deliveries. But when the opportunity comes to shift momentum in a tense club match on CricApp, having the bravery to disrupt the bowler's plans can win you matches from completely lost positions.",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Rahul Rawat',
                'tags' => 'Rishabh Pant, Counter Attack, Test Cricket, Batting Aggression, Mindset',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(4)->subHours(5),
            ],

            // 16. Dew Factor in Night Matches
            [
                'title' => "The 8 PM Dew Nightmare: How Wet Outfields Turn World-Class Spinners Into Club Bowlers",
                'slug' => 'dew-factor-indian-night-cricket-tactics-handling',
                'category' => 'Opinion & Analysis',
                'excerpt' => 'Gripping a wet leather ball like a bar of soap. The bowling strategies, towel drills, and cross-seam tricks teams use to survive second-innings defending.',
                'content' => "Every cricketer who has played under floodlights in North India between October and March knows the dreaded sensation: you bend down to field a ball in the outfield at 8:30 PM, and your trousers and hands are instantly soaked with ice-cold dew.\n\nWithin 3 overs, the dry leather cricket ball transforms into a slippery, heavy sponge.\n\n### Why Dew Completely Destroys Spin Bowling\nSpin bowling relies entirely on tactile friction between the finger skin and the raised seam of the ball. When moisture covers the ball, that friction drops to near zero.\n- Finger spinners cannot impart revolutions; the ball slips prematurely out of the hand, resulting in knee-high full tosses.\n- Wrist spinners lose grip on the seam, making googlies and leg-breaks completely flat and easy to pick off the deck.\n\n### The Seamer’s Survival Playbook\nIf your team is defending a total under heavy dew on CricApp:\n1. **Bowl Cross-Seam Exclusively:** A wet upright seam slips unpredictably. Releasing the ball across the seam allows the leather surface to grip the grass and hit the deck with skid and uneven bounce.\n2. **The Dry Towel Relay:** Designate two fielders inside the 30-yard circle whose only job is keeping clean, dry microfiber towels inside their waistbands. Wipe the ball thoroughly after every single delivery.\n3. **Sawdust Around the Delivery Stride:** Wet bowler landing areas cause ankle slips and groin injuries. Always keep a bucket of dry sawdust near the umpire to sprinkle on the bowling crease.\n\nDew is an undeniable part of subcontinental cricket—teams that prepare for it tactically are the ones that defend totals when everyone else panics.",
                'featured_image' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Anil K. (Former Ranji Spinner)',
                'tags' => 'Dew Factor, Night Cricket, Spin Bowling, Match Tactics, Indian Cricket',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(5)->subHours(3),
            ],

            // 17. Bat Knocking Secrets
            [
                'title' => "How to Knock In and Oil an English Willow Cricket Bat Properly: Avoid Snapping Your ₹15,000 Blade",
                'slug' => 'how-to-knock-in-english-willow-cricket-bat-properly',
                'category' => 'Cricket Tips',
                'excerpt' => 'Raw linseed oil, wooden mallet drills, edge feathering, and progressive net throwdowns: the definitive 3-week guide to making your bat ping like dynamite.',
                'content' => "There is no heartbreak in amateur cricket quite like spending your hard-earned ₹12,000 on a pristine Grade-1 English willow bat, walking into the nets the very next evening, facing a bowler with a brand-new leather ball, and hearing the sickening crack of your toe or edge snapping off on ball four.\n\nModern bats are pressed for lightweight balance, not indestructible armor. Without proper knocking-in, even a ₹50,000 professional bat will break within weeks.\n\n### Step 1: The Raw Linseed Oil Coat (Not Boiled Oil!)\nRaw linseed oil keeps the willow fibers flexible and prevents dry brittle cracking. Pour a single coin-sized drop onto the face of the bat and spread it evenly with an old cloth across the face, edges, and toe. \n*Crucial warning:* Never oil the splice (where the handle joins the blade) or the back stickers. Leave the bat horizontal on its back for 24 hours to let the oil soak into the timber.\n\n### Step 2: The Wooden Mallet Knocking Phase\nYou cannot rush this. You need at least 6 to 8 hours of knocking over a 10-day period.\n- Start gently tapping the center of the blade with a specialized wooden cricket mallet.\n- Progressively increase striking force until you are hitting with 80% power.\n- **Glance the edges at a 45-degree angle:** Never hit the sharp side of an edge straight on; strike at an angle to round off the sharp grain and compress the willow fibers.\n\n### Step 3: Progressive Net Testing\nAfter 8 hours of mallet work, test the bat in the nets using old, soft leather balls for 3 sessions. Check the face for seam indentations. If the face leaves deep dents, knock for another 2 hours. Once it withstands full-speed throwdowns without marks, your bat is ready to score centuries on CricApp!",
                'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Coach Vikram',
                'tags' => 'English Willow, Cricket Bat, Bat Knocking, Equipment Care, Cricket Gear',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 5,
                'published_at' => Carbon::now()->subDays(6)->subHours(4),
            ],

            // 18. Box Cricket Tactics
            [
                'title' => "The Tactical Playbook of Box Cricket: How to Win 6-a-Side Floodlight Turf Tournaments",
                'slug' => 'box-cricket-turf-tournament-winning-tactics',
                'category' => 'Local Stories',
                'excerpt' => 'Net deflections, underarm precision, dot-ball bowling, and wall rebounds: how box cricket evolved into India’s most popular evening sport.',
                'content' => "Over the last five years, rooftop floodlit turf arenas have revolutionized recreational sports across India. From Bengaluru and Pune to Delhi and Chandigarh, box cricket has replaced open field matches for millions of working professionals and weekend warriors.\n\nHowever, treating box cricket like normal cricket is a recipe for quick elimination. Box cricket is its own sport with its own tactical handbook.\n\n### 1. Bowling the 'Stump-to-Stump' Yorker Line\nIn an enclosed turf arena where boundaries are just 25 meters away, giving width outside off-stump is suicide—batters use the rebound nets for easy twos and fours. The best box bowlers bowl underarm or short-run flat deliveries directly aimed at the base of middle and leg stump. Keep the ball at knee height; batters cannot get elevation without hitting the top net.\n\n### 2. The Art of the 'Net Tap' Single\nPower hitting often works against you in box tournaments because hitting the side net with force often brings 'out' rules or restricts runs. Elite box batters develop the soft-wrist push: tap the ball into a blind corner between two fielders, sprint the 16-yard pitch, and keep the scoreboard ticking.\n\n### 3. Running Between Wickets is 70% of the Game\nIn a 6-over match, you cannot afford more than 6 dot balls across the entire innings. Stealing quick singles on byes, leg-byes, and misfields puts immense pressure on amateur fielders, leading to overthrows and panic run-outs. Track your turf league squads on CricApp to see your win percentage skyrocket!",
                'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'Box Cricket, Turf Arena, 6 a Side, Weekend League, Local Cricket',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(7)->subHours(2),
            ],

            // 19. Picking Slower Balls
            [
                'title' => "Knuckle Ball, Back-of-the-Hand, and Split-Finger: How Batters Can Pick Slower Deliveries Early",
                'slug' => 'how-to-pick-slower-ball-variations-batting-guide',
                'category' => 'Cricket Tips',
                'excerpt' => 'Do not wait for the ball to pitch. Watch the bowler’s wrist angle, shoulder deceleration, and finger release before the delivery stride to dominate death overs.',
                'content' => "Every batter has lived through that embarrassing moment: you charge down the track ready to launch a fast bowler into the next zip code, you commit your bat swing at 140 km/h speed, and the ball arrives a full second later at 112 km/h, looping harmlessly off your toe-end to cover.\n\nYou didn't mis-hit the ball—the bowler completely fooled you in the air.\n\n### 1. The Knuckle Ball (Bhuvneshwar Kumar / Andrew Tye)\nWatch the bowler's fingers as they enter the delivery stride. In a knuckle ball, the bowler tucks the tips of their index and middle fingers directly against the leather seam rather than resting on the pads. \n*The giveaway:* The ball will float towards you with **zero rotational spin on the seam**—it wobbles like a beach ball in the breeze.\n\n### 2. The Back-of-the-Hand Slower Delivery (Harshal Patel / Bravo)\nThis variation is released with the back of the bowler's hand facing the batter at release point. Look at the bowler’s wrist in their final rotation. If their wrist is pronated outward, the ball will come out with scrambled leg-spin rotation and dip sharply upon pitching.\n\n### 3. The Shoulder Deceleration Tell\nEven the best bowlers in the world have subtle involuntary physical tells when bowling slower deliveries. Their final approach stride is often 5% slower, and their non-bowling arm drops slightly earlier to slow their rotational torque. If you train your eyes to watch the bowler's chest and shoulder rather than just the pitch, you will pick slower balls 15 meters earlier.",
                'featured_image' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Coach Vikram',
                'tags' => 'Slower Ball, Knuckle Ball, Batting IQ, T20 Cricket, Fast Bowling Variations',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(8)->subHours(1),
            ],

            // 20. Grassroots Umpiring Respect
            [
                'title' => "The Toughest Job on the Maidaan: Why Grassroots Cricket Needs Verified, Respected Umpires",
                'slug' => 'grassroots-cricket-umpiring-challenges-conflict-resolution',
                'category' => 'Local Stories',
                'excerpt' => 'Standing 7 hours under the scorching sun for ₹800 without DRS or ultra-edge. Why fair adjudication is the true beating heart of local tournament cricket.',
                'content' => "Next time you feel like screaming at an umpire in your local Sunday league match over a tight LBW decision, take a moment to stand in their worn-out canvas shoes.\n\nAn amateur cricket umpire stands motionless on hard turf or dusty concrete for 40 to 80 overs in 38-degree heat. They don’t have ball-tracking cameras, UltraEdge sound graphs, or third umpires in air-conditioned booths. They have only their naked human eyes, their ears, and a split second to decide.\n\n### The Pressure of Village Rivalries\nIn local tournaments with cash prizes and local prestige on the line, every appeal is deafening. Ten fielders are charging at the umpire shouting at the top of their lungs, while the batting team's dugout is watching with hostile intensity.\n\nYet, without that certified official standing at the bowler's end, the match dissolves into arguments over waist-high no-balls and disputed run-outs within four overs.\n\n### Why CricApp is Empowering Local Umpires\nThis is why we built our verified Umpire Profiles and booking module on CricApp.in. Local umpires can showcase their years of experience, officiating badges, tournament history, and player reviews. When players know an umpire is certified and respected, disputes drop by 80%.\n\nCricket is a gentleman’s game not because the rules are simple, but because both teams respect the official's verdict. Treat your local umpires with dignity—they make the game possible.",
                'featured_image' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
                'author_name' => 'Shubham Thakur',
                'tags' => 'Cricket Umpiring, Grassroots, Fair Play, Spirit of Cricket, Local Match',
                'status' => 'published',
                'is_featured' => false,
                'reading_time' => 4,
                'published_at' => Carbon::now()->subDays(9)->subHours(3),
            ],
        ];
    }
}
