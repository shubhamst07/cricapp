<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ArticleGeneratorService;
use App\Services\ArticleTranslationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            $articles->withPath(route('articles.index'));
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
     * Display the specified article with multi-language support.
     */
    public function show(string $slug, ArticleTranslationService $translationService): View
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        // Increment views count safely
        try {
            $article->increment('views_count');
        } catch (\Throwable $e) {
            // ignore
        }

        // Get 3-language translations (English, Hindi, Hinglish)
        $translations = [];
        try {
            $translations = $translationService->getTranslations($article);
        } catch (\Throwable $e) {
            Log::warning('Translation error in ArticleController@show: ' . $e->getMessage());
            $translations = [
                'en' => [
                    'title' => $article->title,
                    'category' => $article->category,
                    'excerpt' => $article->excerpt,
                    'content' => $article->content,
                ],
                'hi' => [
                    'title' => $article->title_hi ?: $article->title,
                    'category' => $article->category_hi ?: $article->category,
                    'excerpt' => $article->excerpt_hi ?: $article->excerpt,
                    'content' => $article->content_hi ?: $article->content,
                ],
                'hinglish' => [
                    'title' => $article->title_hinglish ?: $article->title,
                    'category' => $article->category_hinglish ?: $article->category,
                    'excerpt' => $article->excerpt_hinglish ?: $article->excerpt,
                    'content' => $article->content_hinglish ?: $article->content,
                ],
            ];
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

        return view('articles.show', compact('article', 'relatedArticles', 'isAdmin', 'translations'));
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
     * Generate humanized cricket article using AI bot (Admin only).
     */
    public function generateArticle(Request $request, ArticleGeneratorService $generator): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'prompt' => 'required|string|min:3|max:500',
            'category' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:50',
        ]);

        try {
            $article = $generator->generate(
                $validated['prompt'],
                $validated['category'] ?? null,
                $validated['tone'] ?? 'narrative'
            );

            return response()->json([
                'success' => true,
                'data' => $article,
                'message' => 'Humanized cricket article generated successfully!',
            ]);
        } catch (\Throwable $e) {
            Log::error('ArticleController@generateArticle error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate article: ' . $e->getMessage(),
            ], 500);
        }
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
            $file = $request->file('featured_image_file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $ext = 'jpg';
            }
            $filename = 'art_' . time() . '_' . Str::random(8) . '.' . $ext;
            $destDir = public_path('uploads/articles');
            if (!file_exists($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            $file->move($destDir, $filename);
            $featuredImage = '/uploads/articles/' . $filename;
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
            $file = $request->file('featured_image_file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $ext = 'jpg';
            }
            $filename = 'art_' . time() . '_' . Str::random(8) . '.' . $ext;
            $destDir = public_path('uploads/articles');
            if (!file_exists($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            $file->move($destDir, $filename);
            $featuredImage = '/uploads/articles/' . $filename;
        }

        $wordCount = str_word_count(strip_tags($validated['content']));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        // Invalidate old translations if title or content changed
        $translationsChanged = ($article->title !== $validated['title'] || $article->content !== $validated['content']);

        $article->update([
            'title' => $validated['title'],
            'title_hi' => $translationsChanged ? null : $article->title_hi,
            'title_hinglish' => $translationsChanged ? null : $article->title_hinglish,
            'category' => $validated['category'],
            'category_hi' => $translationsChanged ? null : $article->category_hi,
            'category_hinglish' => $translationsChanged ? null : $article->category_hinglish,
            'excerpt' => $validated['excerpt'] ?: Str::limit(strip_tags($validated['content']), 160),
            'excerpt_hi' => $translationsChanged ? null : $article->excerpt_hi,
            'excerpt_hinglish' => $translationsChanged ? null : $article->excerpt_hinglish,
            'content' => $validated['content'],
            'content_hi' => $translationsChanged ? null : $article->content_hi,
            'content_hinglish' => $translationsChanged ? null : $article->content_hinglish,
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
            Cache::remember('articles_db_ready', 86400, function () {
                if (!Schema::hasTable('articles')) {
                    Artisan::call('migrate', ['--force' => true]);
                }
                return true;
            });
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
            Cache::remember('articles_starter_seeded', 86400, function () {
                if (!Schema::hasTable('articles')) {
                    return false;
                }

                if (Article::count() >= 20) {
                    return true;
                }

                $starters = \Database\Seeders\TrendingArticlesSeeder::getTrendingArticles();

                foreach ($starters as $s) {
                    Article::firstOrCreate(
                        ['slug' => $s['slug']],
                        $s
                    );
                }
                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('ensureStarterArticles error: ' . $e->getMessage());
        }
    }
}
