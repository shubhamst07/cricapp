<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ArticleGeneratorService;
use App\Services\ImageKitService;
use App\Services\IndexNowService;
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
        $indexColumns = ['id', 'title', 'slug', 'category', 'excerpt', 'featured_image', 'author_name', 'views_count', 'reading_time', 'published_at', 'is_featured'];
        $isDefaultBrowse = (empty($category) || strtolower((string) $category) === 'all') && empty($search);
        $page = max(1, (int) $request->input('page', 1));

        try {
            if ($isDefaultBrowse) {
                $bundle = Cache::remember("articles_index_page_{$page}", 120, function () use ($indexColumns, $request) {
                    $feat = Article::published()
                        ->select($indexColumns)
                        ->where('is_featured', true)
                        ->latest('published_at')
                        ->first();
                    if (!$feat) {
                        $feat = Article::published()
                            ->select($indexColumns)
                            ->latest('published_at')
                            ->first();
                    }

                    $listQuery = Article::published()->select($indexColumns)->latest('published_at');
                    if ($feat) {
                        $listQuery->where('id', '!=', $feat->id);
                    }
                    $list = $listQuery->paginate(9)->withQueryString();
                    $list->withPath(route('articles.index'));

                    return [
                        'featuredArticle' => $feat,
                        'articles' => $list,
                    ];
                });

                $featuredArticle = $bundle['featuredArticle'] ?? null;
                $articles = $bundle['articles'] ?? $articles;
            } else {
                $query = Article::published()->select($indexColumns)->latest('published_at');

                if (!empty($category) && strtolower($category) !== 'all') {
                    $query->where('category', $category);
                }

                if (!empty($search)) {
                    $s = trim($search);
                    $query->where(function ($q) use ($s) {
                        $q->where('title', 'LIKE', "%{$s}%")
                          ->orWhere('excerpt', 'LIKE', "%{$s}%")
                          ->orWhere('tags', 'LIKE', "%{$s}%");
                    });
                }

                $articles = $query->paginate(9)->withQueryString();
                $articles->withPath(route('articles.index'));
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
        $article = Cache::remember("article_detail_{$slug}", 300, function () use ($slug) {
            return Article::where('slug', $slug)->firstOrFail();
        });

        // Skip view increments for automated crawlers / bots to avoid unnecessary DB writes
        $userAgent = (string) request()->userAgent();
        $isCrawler = $userAgent !== '' && preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|lighthouse|headless/i', $userAgent);
        if (!$isCrawler) {
            try {
                Article::where('id', $article->id)->increment('views_count');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Related articles (narrow projection excluding heavy multi-language content columns, cached 5m)
        $relatedArticles = collect();
        try {
            $relatedColumns = ['id', 'title', 'slug', 'category', 'excerpt', 'featured_image', 'author_name', 'reading_time', 'published_at', 'is_featured'];
            $relatedArticles = Cache::remember("article_related_{$article->id}", 300, function () use ($article, $relatedColumns) {
                $items = Article::published()
                    ->select($relatedColumns)
                    ->where('id', '!=', $article->id)
                    ->where(function ($q) use ($article) {
                        $q->where('category', $article->category)
                          ->orWhere('is_featured', true);
                    })
                    ->latest('published_at')
                    ->take(3)
                    ->get();

                if ($items->isEmpty()) {
                    $items = Article::published()
                        ->select($relatedColumns)
                        ->where('id', '!=', $article->id)
                        ->latest('published_at')
                        ->take(3)
                        ->get();
                }

                return $items;
            });
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

        $linkableArticles = $this->getLinkableArticles();

        return view('articles.create', compact('categories', 'linkableArticles'));
    }

    private function getLinkableArticles(?int $excludeId = null)
    {
        try {
            $q = Article::published()->select(['id', 'title', 'slug'])->latest('published_at');
            if ($excludeId !== null) {
                $q->where('id', '!=', $excludeId);
            }
            return $q->take(25)->get();
        } catch (\Throwable $e) {
            return collect();
        }
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
    public function store(Request $request, ImageKitService $imageKit, IndexNowService $indexNow): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureDatabaseReady();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image_file' => 'nullable|mimes:jpg,jpeg,png,webp,gif,avif|max:10240',
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

        // Upload uploaded file or third-party URL to permanent ImageKit Cloud CDN
        $featuredImage = $this->resolveFeaturedImage($request, $imageKit, $validated['featured_image_url'] ?? null);

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

        $this->clearArticleCache($article->slug, $article->id);
        if ($article->status === 'published') {
            $indexNow->pingUrls([
                route('articles.show', $article->slug),
                route('articles.index'),
                url('/'),
            ]);
        }

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

        $linkableArticles = $this->getLinkableArticles($article->id);

        return view('articles.edit', compact('article', 'categories', 'linkableArticles'));
    }

    /**
     * Update the specified article (Admin only).
     */
    public function update(Request $request, Article $article, ImageKitService $imageKit, IndexNowService $indexNow): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image_file' => 'nullable|mimes:jpg,jpeg,png,webp,gif,avif|max:10240',
            'featured_image_url' => 'nullable|url|max:500',
            'author_name' => 'nullable|string|max:100',
            'tags' => 'nullable|string|max:255',
            'status' => 'required|in:published,draft',
            'is_featured' => 'nullable|boolean',
        ]);

        // Keep existing or upload new file/URL to permanent ImageKit Cloud CDN
        $featuredImage = $this->resolveFeaturedImage(
            $request,
            $imageKit,
            $validated['featured_image_url'] ?? null,
            $article->featured_image
        );

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

        $this->clearArticleCache($article->slug, $article->id);
        if ($article->status === 'published') {
            $indexNow->pingUrls([
                route('articles.show', $article->slug),
                route('articles.index'),
            ]);
        }

        return redirect()->route('articles.show', $article->slug)
            ->with('success', '✅ Article updated successfully.');
    }

    /**
     * Save client-side or background translations.
     */
    public function saveTranslations(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'title_hi' => 'nullable|string',
            'excerpt_hi' => 'nullable|string',
            'content_hi' => 'nullable|string',
            'title_hinglish' => 'nullable|string',
            'excerpt_hinglish' => 'nullable|string',
            'content_hinglish' => 'nullable|string',
        ]);

        $updateData = [];
        if (!empty($validated['title_hi']) && preg_match('/[\x{0900}-\x{097F}]/u', $validated['title_hi'])) {
            $updateData['title_hi'] = $validated['title_hi'];
        }
        if (!empty($validated['excerpt_hi']) && preg_match('/[\x{0900}-\x{097F}]/u', $validated['excerpt_hi'])) {
            $updateData['excerpt_hi'] = $validated['excerpt_hi'];
        }
        if (!empty($validated['content_hi']) && preg_match('/[\x{0900}-\x{097F}]/u', $validated['content_hi'])) {
            $updateData['content_hi'] = $validated['content_hi'];
        }
        if (!empty($validated['title_hinglish'])) {
            $updateData['title_hinglish'] = $validated['title_hinglish'];
        }
        if (!empty($validated['excerpt_hinglish'])) {
            $updateData['excerpt_hinglish'] = $validated['excerpt_hinglish'];
        }
        if (!empty($validated['content_hinglish'])) {
            $updateData['content_hinglish'] = $validated['content_hinglish'];
        }

        if (!empty($updateData)) {
            $article->update($updateData);
            $this->clearArticleCache($article->slug, $article->id);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified article (Admin only).
     */
    public function destroy(Article $article): RedirectResponse
    {
        $this->authorizeAdmin();

        $article->delete();
        $this->clearArticleCache($article->slug, $article->id);

        return redirect()->route('articles.index')
            ->with('success', 'Article deleted successfully.');
    }

    /**
     * Resolve or upload the article featured image to permanent ImageKit Cloud CDN.
     */
    protected function resolveFeaturedImage(
        Request $request,
        ImageKitService $imageKit,
        ?string $featuredImageUrl = null,
        ?string $existingImage = null
    ): ?string {
        if ($request->hasFile('featured_image_file')) {
            $file = $request->file('featured_image_file');
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'])) {
                    $ext = 'jpg';
                }
                $filename = 'art_' . time() . '_' . Str::random(8) . '.' . $ext;
                $cloudUrl = $imageKit->upload($file, '/cricapp/articles', $filename);
                if ($cloudUrl) {
                    return $cloudUrl;
                }

                Log::warning('ImageKit article upload failed, falling back to local storage.');
                $destDir = public_path('uploads/articles');
                if (!file_exists($destDir)) {
                    @mkdir($destDir, 0777, true);
                }
                $file->move($destDir, $filename);
                return '/uploads/articles/' . $filename;
            }
        }

        if (!empty($featuredImageUrl)) {
            $cleanUrl = trim($featuredImageUrl);
            if (
                $cleanUrl !== $existingImage &&
                !str_contains($cleanUrl, 'ik.imagekit.io') &&
                !str_contains($cleanUrl, 'images.unsplash.com')
            ) {
                $mirrored = $imageKit->upload($cleanUrl, '/cricapp/articles', 'art_url_' . time() . '_' . Str::random(6));
                if ($mirrored) {
                    return $mirrored;
                }
            }
            return $cleanUrl;
        }

        return $existingImage;
    }

    /**
     * Invalidate cached article lists and homepage showcase.
     */
    protected function clearArticleCache(?string $slug = null, ?int $articleId = null): void
    {
        Cache::forget('home_top_articles');
        Cache::forget('sitemap_xml_v1');
        Cache::forget('sitemap_xml_v2');
        Cache::forget('news_sitemap_xml_v1');
        Cache::forget('rss_feed_xml_v1');
        if ($slug) {
            Cache::forget("article_detail_{$slug}");
        }
        if ($articleId) {
            Cache::forget("article_related_{$articleId}");
        }
        for ($p = 1; $p <= 5; $p++) {
            Cache::forget("articles_index_page_{$p}");
        }
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
            Cache::remember('articles_starter_seeded_v2', 86400, function () {
                if (!Schema::hasTable('articles')) {
                    return false;
                }

                $starters = \Database\Seeders\TrendingArticlesSeeder::getTrendingArticles();

                if (Article::count() >= count($starters)) {
                    return true;
                }

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
