<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchPost;
use App\Services\CricketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchPostController extends Controller
{
    /**
     * Get social posts for a match.
     */
    public function index(string $matchId, CricketDataService $cricket): JsonResponse
    {
        $posts = MatchPost::where('match_id', $matchId)
            ->latest()
            ->take(50)
            ->get();

        // If no posts exist yet, auto-seed customized posts for THIS specific match
        if ($posts->isEmpty()) {
            $this->seedStarterPosts($matchId, $cricket);
            $posts = MatchPost::where('match_id', $matchId)->latest()->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $posts,
        ]);
    }

    /**
     * Create a fan post for a match.
     */
    public function store(string $matchId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'user_name' => 'nullable|string|max:60',
            'media_url' => 'nullable|string|max:500',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'insta_url' => 'nullable|string|max:500',
            'team_tag' => 'nullable|string|max:50',
            'post_type' => 'nullable|string|in:text,meme,instagram,highlight,commentary',
        ]);

        $user = Auth::user();
        $authorName = $user ? $user->name : (trim($validated['user_name'] ?? '') ?: 'Cricket Fan');
        
        $instaUrl = $validated['insta_url'] ?? null;
        $content = $validated['content'];
        $postType = $validated['post_type'] ?? 'text';
        $mediaUrl = $validated['media_url'] ?? null;

        // Handle uploaded photo / meme file
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $uploadDir = public_path('uploads/match-memes');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $mediaUrl = '/uploads/match-memes/' . $filename;
            $postType = 'meme';
        } elseif ($mediaUrl) {
            $postType = 'meme';
        }

        // Auto-detect Instagram link from content or insta_url field
        if (!$instaUrl && preg_match('/https?:\/\/(www\.)?instagram\.com\/(p|reel|tv)\/([a-zA-Z0-9_\-]+)/', $content, $matches)) {
            $instaUrl = $matches[0];
            $postType = 'instagram';
        } elseif ($instaUrl) {
            $postType = 'instagram';
        }

        // Random avatar badge colors
        $colors = ['indigo', 'emerald', 'rose', 'amber', 'purple', 'sky', 'cyan'];
        $avatarColor = $colors[array_rand($colors)];

        $post = MatchPost::create([
            'match_id' => $matchId,
            'user_id' => $user ? $user->id : null,
            'user_name' => $authorName,
            'avatar_color' => $avatarColor,
            'content' => $content,
            'post_type' => $postType,
            'media_url' => $mediaUrl,
            'insta_url' => $instaUrl,
            'team_tag' => $validated['team_tag'] ?? null,
            'likes_count' => 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Post published successfully!',
            'data' => $post,
        ], 201);
    }

    /**
     * Like a post.
     */
    public function like(int $id): JsonResponse
    {
        $post = MatchPost::find($id);
        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Post not found'], 404);
        }

        $post->increment('likes_count');

        return response()->json([
            'status' => 'success',
            'likes_count' => $post->likes_count,
        ]);
    }

    /**
     * Get comments for a post.
     */
    public function getComments(int $id): JsonResponse
    {
        $post = MatchPost::find($id);
        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Post not found'], 404);
        }

        $comments = $post->comments()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $comments,
        ]);
    }

    /**
     * Add a comment to a post.
     */
    public function addComment(int $id, Request $request): JsonResponse
    {
        $post = MatchPost::find($id);
        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Post not found'], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:500',
            'user_name' => 'nullable|string|max:60',
        ]);

        $user = Auth::user();
        $authorName = $user ? $user->name : (trim($validated['user_name'] ?? '') ?: 'Cricket Fan');

        $colors = ['indigo', 'emerald', 'rose', 'amber', 'purple', 'sky', 'cyan'];
        $avatarColor = $colors[array_rand($colors)];

        $comment = $post->comments()->create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $authorName,
            'avatar_color' => $avatarColor,
            'comment' => $validated['comment'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Comment added successfully!',
            'data' => $comment,
            'comments_count' => $post->comments()->count(),
        ], 201);
    }

    /**
     * Seed customized starter posts for THIS specific match using a smart team & format algorithm.
     */
    protected function seedStarterPosts(string $matchId, CricketDataService $cricket): void
    {
        $match = $cricket->findMatchById($cricket->getCurrentMatches(), $matchId);
        if (empty($match)) {
            $match = $cricket->getMatchDetails($matchId);
        }

        $teams = $match['teams'] ?? [];
        $team1 = $teams[0] ?? 'Team 1';
        $team2 = $teams[1] ?? 'Team 2';
        $matchName = $match['name'] ?? ($match['title'] ?? "{$team1} vs {$team2}");
        $matchType = strtoupper($match['matchType'] ?? 'MATCH');
        $venue = $match['venue'] ?? '';

        // Extract batters & bowlers from scorecard
        $scorecard = $cricket->getMatchScorecard($matchId);
        $scData = $scorecard['data'] ?? [];

        $batters = [];
        $bowlers = [];

        if (!empty($scData['innings'])) {
            foreach ($scData['innings'] as $inn) {
                foreach ($inn['batting'] ?? [] as $b) {
                    if (!empty($b['name']) && !in_array($b['name'], $batters)) {
                        $batters[] = $b['name'];
                    }
                }
                foreach ($inn['bowling'] ?? [] as $bw) {
                    $bName = $bw['name'] ?? ($bw['bowler'] ?? null);
                    if ($bName && !in_array($bName, $bowlers)) {
                        $bowlers[] = $bName;
                    }
                }
            }
        }

        if (empty($batters) && !empty($scData['batting'])) {
            $batters = collect($scData['batting'])->pluck('name')->filter()->values()->toArray();
        }
        if (empty($bowlers) && !empty($scData['bowling'])) {
            $bowlers = collect($scData['bowling'])->pluck('name')->filter()->values()->toArray();
        }

        $batter1 = $batters[0] ?? "{$team1} Batsman";
        $batter2 = $batters[1] ?? ($batters[0] ?? "{$team1} Top Order");
        $bowler1 = $bowlers[0] ?? "{$team2} Pacer";
        $bowler2 = $bowlers[1] ?? ($bowlers[0] ?? "{$team2} Bowler");

        // Team-Specific Database: Profiles, Handles, Slangs, and Unique Instagram Reels
        $teamMeta = [
            'South Africa' => [
                'handle' => '@proteasmencsa',
                'slang' => 'Proteas Fire 🔥',
                'reels' => [
                    'https://www.instagram.com/reel/C8q1Xy-p-oM/',
                    'https://www.instagram.com/reel/C72xP8gIaK4/',
                ],
                'fanclub' => 'Proteas South Fanclub 🇿🇦',
                'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
            ],
            'Australia' => [
                'handle' => '@cricketaustralia',
                'slang' => 'Baggy Green Era 🇦🇺',
                'reels' => [
                    'https://www.instagram.com/reel/C6yN12kL8pq/',
                    'https://www.instagram.com/reel/C8q1Xy-p-oM/',
                ],
                'fanclub' => 'Aussie Cricket Corner 🦘',
                'image' => 'https://images.unsplash.com/photo-1531415074868-036b107e7752?w=800&auto=format&fit=crop&q=80',
            ],
            'India' => [
                'handle' => '@indiancricketteam',
                'slang' => 'Bleed Blue 🇮🇳',
                'reels' => [
                    'https://www.instagram.com/reel/C8rP5kLgT8a/',
                    'https://www.instagram.com/reel/C79mKl0P9xQ/',
                ],
                'fanclub' => 'Bharat Army Official 🇮🇳',
                'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
            ],
            'New Zealand' => [
                'handle' => '@blackcapsnz',
                'slang' => 'BlackCaps Power 🇳🇿',
                'reels' => [
                    'https://www.instagram.com/reel/C5sW8eFv0vY/',
                    'https://www.instagram.com/reel/C8q1Xy-p-oM/',
                ],
                'fanclub' => 'Kiwi Cricket Brigade 🥝',
                'image' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800&auto=format&fit=crop&q=80',
            ],
            'Pakistan' => [
                'handle' => '@therealpcb',
                'slang' => 'Jazba Junoon 🇵🇰',
                'reels' => [
                    'https://www.instagram.com/reel/C8q1Xy-p-oM/',
                ],
                'fanclub' => 'Pak Cricket Fans Club 🇵🇰',
                'image' => 'https://images.unsplash.com/photo-1531415074868-036b107e7752?w=800&auto=format&fit=crop&q=80',
            ],
            'Sri Lanka' => [
                'handle' => '@officialslc',
                'slang' => 'Lions Pride 🇱🇰',
                'reels' => [
                    'https://www.instagram.com/reel/C8q1Xy-p-oM/',
                ],
                'fanclub' => 'Sri Lanka Papare Band 🇱🇰',
                'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        $t1Meta = $teamMeta[$team1] ?? [
            'handle' => '@icc',
            'slang' => "Cheering for {$team1} 🏏",
            'reels' => ['https://www.instagram.com/reel/C8q1Xy-p-oM/'],
            'fanclub' => "{$team1} Fans United",
            'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
        ];

        $t2Meta = $teamMeta[$team2] ?? [
            'handle' => '@icc',
            'slang' => "{$team2} Army 🏏",
            'reels' => ['https://www.instagram.com/reel/C8q1Xy-p-oM/'],
            'fanclub' => "{$team2} Fan Club",
            'image' => 'https://images.unsplash.com/photo-1531415074868-036b107e7752?w=800&auto=format&fit=crop&q=80',
        ];

        $seeds = [];

        // 1. SPECIFIC MATCH TYPE LOGIC
        if (str_contains($matchType, 'TEST') || str_contains(strtoupper($matchName), 'TEST')) {
            // TEST MATCH SOCIAL VIBES (Sessions, Patience, Pitch, Bowling spells)
            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => $t1Meta['fanclub'],
                'avatar_color' => 'indigo',
                'content' => "Test cricket at its finest at {$venue}! {$batter1} showing grit, solid forward defense and playing the moving ball so late. This is how you respect the red cherry! 🔴👏 #TestCricket #{$team1}Cricket",
                'post_type' => 'text',
                'media_url' => null,
                'insta_url' => null,
                'team_tag' => $team1,
                'likes_count' => rand(85, 190),
                'created_at' => now()->subMinutes(24),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => "{$t1Meta['handle']} Moments 🎬",
                'avatar_color' => 'rose',
                'content' => "REEL OF THE DAY: {$batter1} driving through the covers with unbelievable balance! Pure poetry in motion. 🎥 Sound on for the sweet middle sound! 🔊 #CricketReels #CoverDrive",
                'post_type' => 'instagram',
                'insta_url' => $t1Meta['reels'][0],
                'media_url' => $t1Meta['image'],
                'team_tag' => $team1,
                'likes_count' => rand(150, 420),
                'created_at' => now()->subMinutes(17),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => 'Red Ball Memes Hub 😂',
                'avatar_color' => 'amber',
                'content' => "{$team2} bowlers checking the pitch after bowling 30 tight overs in scorching heat: 'Is this a pitch or a highway?' 🥵 Starc and Cummins waiting for the second new ball already! 😂",
                'post_type' => 'meme',
                'media_url' => 'https://images.unsplash.com/photo-1531415074868-036b107e7752?w=800&auto=format&fit=crop&q=80',
                'insta_url' => null,
                'team_tag' => $team2,
                'likes_count' => rand(90, 210),
                'created_at' => now()->subMinutes(11),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => 'Pitch & Tactics Desk 📊',
                'avatar_color' => 'emerald',
                'content' => "Tactical breakdown: {$team2}'s plan to bowl short into the ribs hasn't worked so far because {$batter1} and {$batter2} are swaying out smoothly. If {$team1} bats through to Tea, 380+ is on the cards. Critical session ahead!",
                'post_type' => 'highlight',
                'media_url' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800&auto=format&fit=crop&q=80',
                'insta_url' => null,
                'team_tag' => 'Tactics',
                'likes_count' => rand(40, 95),
                'created_at' => now()->subMinutes(4),
            ];

        } elseif (str_contains($matchType, 'T20') || str_contains(strtoupper($matchName), 'T20')) {
            // T20 MATCH SOCIAL VIBES (Powerplay, Sixes, Death Overs, Strike rates)
            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => "{$t1Meta['handle']} Official Reels 🎬",
                'avatar_color' => 'rose',
                'content' => "HIGH VOLTAGE T20 ACTION! ⚡ That 95-meter rocket six by {$batter1} clean out of the stadium! Watch the viral Instagram replay now! 🚀 #T20Blast #SixHit #{$team1}",
                'post_type' => 'instagram',
                'insta_url' => $t1Meta['reels'][0],
                'media_url' => $t1Meta['image'],
                'team_tag' => $team1,
                'likes_count' => rand(220, 580),
                'created_at' => now()->subMinutes(20),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => 'T20 Trolls & Memes 😂',
                'avatar_color' => 'amber',
                'content' => "Bowlers when they hear 'Death Overs start ho gaye': 'Main toh khud bhag jaata hoon' 💀 {$batter1} batting with a 170+ strike rate is illegal in T20s!",
                'post_type' => 'meme',
                'media_url' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
                'insta_url' => null,
                'team_tag' => $team2,
                'likes_count' => rand(110, 310),
                'created_at' => now()->subMinutes(14),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => $t2Meta['fanclub'],
                'avatar_color' => 'indigo',
                'content' => "{$team2} need a couple of quick wickets right now! {$bowler1} has to nail the wide yorkers to stem the bleeding. A chase of 180+ will be tricky under lights!",
                'post_type' => 'text',
                'media_url' => null,
                'insta_url' => null,
                'team_tag' => $team2,
                'likes_count' => rand(65, 140),
                'created_at' => now()->subMinutes(8),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => 'Powerplay Analytics 📊',
                'avatar_color' => 'cyan',
                'content' => "FAST & FURIOUS: Run rate hovering at 9.4 RPO! Short boundaries and lightning-fast outfield making life hell for spinners. Expecting a thrilling 200+ finish!",
                'post_type' => 'highlight',
                'media_url' => 'https://images.unsplash.com/photo-1517649763962-0c623266ddc0?w=800&auto=format&fit=crop&q=80',
                'insta_url' => null,
                'team_tag' => 'MatchStats',
                'likes_count' => rand(45, 115),
                'created_at' => now()->subMinutes(3),
            ];

        } else {
            // ODI / GENERAL MATCH SOCIAL VIBES (Middle overs, Chase dynamics, Century hunts)
            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => "{$t1Meta['fanclub']}",
                'avatar_color' => 'indigo',
                'content' => "{$team1} building a sensational foundation in {$matchName}! The acceleration after the 30th over is going to be explosive with {$batter1} well set.",
                'post_type' => 'text',
                'media_url' => null,
                'insta_url' => null,
                'team_tag' => $team1,
                'likes_count' => rand(55, 130),
                'created_at' => now()->subMinutes(22),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => "Viral Cricket Reels 🎬",
                'avatar_color' => 'rose',
                'content' => "WHAT A GRAB! Dive, roll and catch inches off the turf! Outstanding fielding effort that turned this match on its head! 🔥 #CricketReels #Flyer",
                'post_type' => 'instagram',
                'insta_url' => $t1Meta['reels'][0],
                'media_url' => $t1Meta['image'],
                'team_tag' => $team2,
                'likes_count' => rand(130, 360),
                'created_at' => now()->subMinutes(15),
            ];

            $seeds[] = [
                'match_id' => $matchId,
                'user_name' => 'Cricket Memes Hub 😂',
                'avatar_color' => 'amber',
                'content' => "Watching {$team1} vs {$team2} on a Sunday afternoon with chai-samosa hits completely different! Cricket fans living their best life today. ☕️🏏",
                'post_type' => 'meme',
                'media_url' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80',
                'insta_url' => null,
                'team_tag' => $team1,
                'likes_count' => rand(80, 220),
                'created_at' => now()->subMinutes(7),
            ];
        }

        foreach ($seeds as $seed) {
            MatchPost::create($seed);
        }
    }
}
