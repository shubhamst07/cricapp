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

            if (Article::count() >= 20) {
                return;
            }

            $starters = \Database\Seeders\TrendingArticlesSeeder::getTrendingArticles();

            foreach ($starters as $s) {
                Article::firstOrCreate(
                    ['slug' => $s['slug']],
                    $s
                );
            }
        } catch (\Throwable $e) {
            Log::warning('ensureStarterArticles error: ' . $e->getMessage());
        }
    }
}
