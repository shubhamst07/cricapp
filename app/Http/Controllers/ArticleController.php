<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles, news & blogs.
     */
    public function index(Request $request): View
    {
        $this->ensureDatabaseReady();
        $this->ensureStarterArticles();

        $category = $request->input('category');
        $search = $request->input('q');

        $featuredArticle = null;
        $articles = new LengthAwarePaginator([], 0, 9);

        try {
            $query = Article::published()->latest('published_at');

            if (!empty($category) && strtolower($category) !== 'all') {
                $query->where('category', $category);
            }

            if (!empty($search)) {
                $s = trim($search);
                $query->where(function ($q) use ($s) {
                    $q->where('title', 'LIKE', "%{$s}%")
                      ->orWhere('excerpt', 'LIKE', "%{$s}%")
                      ->orWhere('content', 'LIKE', "%{$s}%")
                      ->orWhere('tags', 'LIKE', "%{$s}%");
                });
            }

            // Top featured article
            if (empty($category) && empty($search)) {
                $featuredArticle = Article::published()->where('is_featured', true)->latest('published_at')->first();
                if (!$featuredArticle) {
                    $featuredArticle = Article::published()->latest('published_at')->first();
                }
            }

            // Exclude featured from the paginated list if it exists
            if ($featuredArticle) {
                $articles = (clone $query)->where('id', '!=', $featuredArticle->id)->paginate(9)->withQueryString();
            } else {
                $articles = $query->paginate(9)->withQueryString();
            }
        } catch (\Throwable $e) {
            Log::error('ArticleController@index error: ' . $e->getMessage());
        }

        $categories = [
            'Match Report',
            'Tournament News',
            'Cricket Tips',
            'Local Stories',
            'Opinion & Analysis',
            'General',
        ];

        $isAdmin = Auth::check() && (Auth::user()->is_admin ?? false);

        return view('articles.index', compact('articles', 'featuredArticle', 'categories', 'category', 'search', 'isAdmin'));
    }

    /**
     * Display the specified article.
     */
    public function show(string $slug): View
    {
        $this->ensureDatabaseReady();
        $this->ensureStarterArticles();

        $article = Article::where('slug', $slug)->firstOrFail();

        // Increment views count safely
        try {
            $article->increment('views_count');
        } catch (\Throwable $e) {
            // ignore
        }

        // Related articles (same category or recent, excluding current)
        $relatedArticles = collect();
        try {
            $relatedArticles = Article::published()
                ->where('id', '!=', $article->id)
                ->where(function ($q) use ($article) {
                    $q->where('category', $article->category)
                      ->orWhere('is_featured', true);
                })
                ->latest('published_at')
                ->take(3)
                ->get();

            if ($relatedArticles->isEmpty()) {
                $relatedArticles = Article::published()
                    ->where('id', '!=', $article->id)
                    ->latest('published_at')
                    ->take(3)
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::warning('Related articles query error: ' . $e->getMessage());
        }

        $isAdmin = Auth::check() && (Auth::user()->is_admin ?? false);

        return view('articles.show', compact('article', 'relatedArticles', 'isAdmin'));
    }

    /**
     * Show form to create a new article (Admin only).
     */
    public function create(): View
    {
        $this->authorizeAdmin();
        $this->ensureDatabaseReady();

        $categories = [
            'Match Report',
            'Tournament News',
            'Cricket Tips',
            'Local Stories',
            'Opinion & Analysis',
            'General',
        ];

        return view('articles.create', compact('categories'));
    }

    /**
     * Store a newly created article (Admin only).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureDatabaseReady();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image_file' => 'nullable|image|max:5120',
            'featured_image_url' => 'nullable|url|max:500',
            'author_name' => 'nullable|string|max:100',
            'tags' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'is_featured' => 'nullable|boolean',
        ]);

        // Generate clean unique slug
        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Article::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        // Handle Image upload or URL
        $featuredImage = $validated['featured_image_url'] ?? null;
        if ($request->hasFile('featured_image_file')) {
            $path = $request->file('featured_image_file')->store('uploads/articles', 'public');
            $featuredImage = $path;
        }

        // Calculate reading time based on word count
        $wordCount = str_word_count(strip_tags($validated['content']));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $article = Article::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'category' => $validated['category'],
            'excerpt' => $validated['excerpt'] ?: Str::limit(strip_tags($validated['content']), 160),
            'content' => $validated['content'],
            'featured_image' => $featuredImage,
            'author_name' => $validated['author_name'] ?: (Auth::user()->name ?? 'LiveCricket Staff'),
            'tags' => $validated['tags'] ?? null,
            'status' => $validated['status'],
            'views_count' => 0,
            'reading_time' => $readingTime,
            'is_featured' => (bool) ($request->has('is_featured') && $request->input('is_featured')),
            'published_at' => $validated['status'] === 'published' ? Carbon::now() : null,
        ]);

        return redirect()->route('articles.show', $article->slug)
            ->with('success', '🏏 Article published successfully! It is now live for all cricket fans.');
    }

    /**
     * Show form to edit an article (Admin only).
     */
    public function edit(Article $article): View
    {
        $this->authorizeAdmin();

        $categories = [
            'Match Report',
            'Tournament News',
            'Cricket Tips',
            'Local Stories',
            'Opinion & Analysis',
            'General',
        ];

        return view('articles.edit', compact('article', 'categories'));
    }

    /**
     * Update the specified article (Admin only).
     */
    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image_file' => 'nullable|image|max:5120',
            'featured_image_url' => 'nullable|url|max:500',
            'author_name' => 'nullable|string|max:100',
            'tags' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'is_featured' => 'nullable|boolean',
        ]);

        // Keep or update image
        $featuredImage = $article->featured_image;
        if (!empty($validated['featured_image_url'])) {
            $featuredImage = $validated['featured_image_url'];
        }
        if ($request->hasFile('featured_image_file')) {
            $path = $request->file('featured_image_file')->store('uploads/articles', 'public');
            $featuredImage = $path;
        }

        $wordCount = str_word_count(strip_tags($validated['content']));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $article->update([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'excerpt' => $validated['excerpt'] ?: Str::limit(strip_tags($validated['content']), 160),
            'content' => $validated['content'],
            'featured_image' => $featuredImage,
            'author_name' => $validated['author_name'] ?: ($article->author_name ?: Auth::user()->name),
            'tags' => $validated['tags'] ?? null,
            'status' => $validated['status'],
            'reading_time' => $readingTime,
            'is_featured' => (bool) ($request->has('is_featured') && $request->input('is_featured')),
            'published_at' => $article->published_at ?: Carbon::now(),
        ]);

        return redirect()->route('articles.show', $article->slug)
            ->with('success', '✅ Article updated successfully.');
    }

    /**
     * Remove the specified article (Admin only).
     */
    public function destroy(Article $article): RedirectResponse
    {
        $this->authorizeAdmin();

        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', 'Article deleted successfully.');
    }

    /**
     * Authorize that the current user has admin privileges.
     */
    protected function authorizeAdmin(): void
    {
        if (!Auth::check() || !(Auth::user()->is_admin ?? false)) {
            abort(403, 'Unauthorized access. Only authorized administrators can publish articles.');
        }
    }

    /**
     * Ensure database migrations are run if articles table is missing.
     */
    protected function ensureDatabaseReady(): void
    {
        try {
            if (!Schema::hasTable('articles')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-migration in ArticleController: ' . $e->getMessage());
        }
    }

    /**
     * Ensure default starter articles exist so the blog page is never empty.
     */
    protected function ensureStarterArticles(): void
    {
        try {
            if (!Schema::hasTable('articles')) {
                return;
            }

            if (Article::count() > 0) {
                return;
            }

            $starters = [
                [
                    'title' => 'Top 5 Batting Drills to Master Power Hitting in T20 Cricket',
                    'slug' => 'top-5-batting-drills-power-hitting-t20',
                    'category' => 'Cricket Tips',
                    'excerpt' => 'Unlock explosive strokeplay and master high-speed bat swing with these 5 proven drills used by top franchise batters.',
                    'content' => "Power hitting in modern T20 cricket is no longer just brute muscle strength—it is pure science, balance, and bat-speed mechanics.\n\n### 1. The Medicine Ball Rotational Throw\nGreat batters generate power from ground reaction force through their hips. Stand sideways in your batting stance with a 3kg medicine ball and explode rotationally towards a wall. This replicates the hip-clearance needed for maximum six-hitting leverage.\n\n### 2. High-Tee Extended Follow-Through Drill\nPlace a cricket ball on a batting tee set at chest height. Practice striking cleanly over extra-cover and mid-wicket without collapsing your back leg. Keep the bottom hand relaxed until contact point to avoid slicing the ball.\n\n### 3. Drop-Ball Range Hitting\nHave a teammate drop soft balls from shoulder height from just outside the popping crease. Hit through the line with maximum extension. This builds fast neuromuscular decision-making against late dipping yorkers.\n\n### 4. Narrow Stance Base Stability\nTake a stance 20% narrower than usual during throwdowns. A narrower base forces your head into alignment and prevents lunging forward too early against slower cutters.\n\n### 5. Intentional Dot-Ball Recovery Mindset\nIn modern club and professional cricket, a dot ball is not a defeat—it is information. Elite batters reset their breathing pattern within 4 seconds and look for the next boundary opportunity.\n\nStart practicing these drills in your weekly net sessions to see immediate jumps in your strike-rate on CricApp!",
                    'featured_image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                    'author_name' => 'CricApp Coaching Desk',
                    'tags' => 'Batting, T20, Coaching, Drills, Fitness',
                    'status' => 'published',
                    'is_featured' => true,
                    'reading_time' => 4,
                    'published_at' => Carbon::now()->subDays(2),
                ],
                [
                    'title' => 'How Grassroots Cricket in India is Entering the Digital Scoring Era',
                    'slug' => 'grassroots-cricket-india-digital-scoring-era',
                    'category' => 'Local Stories',
                    'excerpt' => 'From paper scorebooks on dusty maidaans to live ball-by-ball webcasts on mobile screens, local cricket is having its biggest revolution.',
                    'content' => "For decades, village and corporate tournaments across India relied on rain-soaked paper scorebooks, disputes over wide balls, and manual run tallies written with broken pencils.\n\nToday, apps like LiveCricket (CricApp.in) are changing that reality overnight.\n\n### The Rise of the Verified Player Passport\nEvery young cricketer dreaming of district or state trials now has a permanent digital record: their batting average, strike rate, best bowling spell, and verified match history. Scouts and club captains no longer have to rely on word-of-mouth recommendations.\n\n### Match Meetups & Sunday Squads\nFinding 22 players with full kit for a Sunday match in Shimla, Delhi, or Pune used to take dozens of frantic WhatsApp phone calls. With our integrated Find Players and Meetups system, captains can post a match venue, specify tennis or leather ball, and fill empty player slots in minutes.\n\n### Live Video Streaming for Grassroots Heroes\nSeeing your family and friends watch you bowl a match-winning final over in high definition from 500 km away is pure magic. Digital live streaming is no longer exclusive to international stadiums—it belongs to every local cricketer on every maidaan.",
                    'featured_image' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
                    'author_name' => 'Shubham Thakur',
                    'tags' => 'Grassroots, Digital Scoring, India, CricApp',
                    'status' => 'published',
                    'is_featured' => false,
                    'reading_time' => 3,
                    'published_at' => Carbon::now()->subDays(4),
                ],
                [
                    'title' => 'Mastering the Death Overs Yorker: Pace, Seam & Mind Games',
                    'slug' => 'mastering-death-overs-yorker-pace-seam',
                    'category' => 'Cricket Tips',
                    'excerpt' => 'Why the toe-crushing yorker remains the ultimate weapon in T20 death overs, and how fast bowlers can bowl it with laser precision.',
                    'content' => "When 18 runs are needed off the final 6 balls, every bowler dreams of hitting the base of off-stump with perfection.\n\n### The Target is Not the Stumps\nElite death-overs bowlers like Jasprit Bumrah and Lasith Malinga repeatedly emphasize: do not aim at the stumps when bowling a yorker. Aim at the batter's front toe or the popping crease white line. Aiming 6 inches lower accounts for the natural dipping arc of the ball.\n\n### Wrist Position at Release\nLock your wrist completely upright until the instant of release. Snapping the wrist too early causes the ball to slip into a knee-high full toss—the most punished ball in limited-overs cricket.\n\n### Deceptive Run-Up Rhythm\nDo not change your running cadence when intending to bowl a yorker. Batters track your approach stride; keeping your final 3 strides consistent disguises your length until release point.\n\n### Practice with the Shoe Drill\nPlace an old shoe right on the popping crease in the nets. Bowl 24 balls daily aiming solely to hit the shoe. Once your consistency passes 70%, take it into live match scenarios!",
                    'featured_image' => 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
                    'author_name' => 'Coach Vikram',
                    'tags' => 'Bowling, Yorker, Death Overs, Pace',
                    'status' => 'published',
                    'is_featured' => false,
                    'reading_time' => 3,
                    'published_at' => Carbon::now()->subDays(6),
                ]
            ];

            foreach ($starters as $s) {
                Article::create($s);
            }
        } catch (\Throwable $e) {
            // Silently ignore if table not migrated yet
        }
    }
}
