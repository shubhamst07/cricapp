<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CricketMatch;
use App\Models\Ground;
use App\Models\UmpireProfile;
use App\Services\CricketDataService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', 'https://www.cricapp.in'), '/');
        return Str::startsWith($baseUrl, 'http') ? $baseUrl : 'https://www.cricapp.in';
    }

    private function absoluteImageUrl(?string $raw, string $baseUrl): string
    {
        $url = trim((string) $raw);
        if ($url === '') {
            return $baseUrl . '/icons/icon-512x512.png';
        }
        if (Str::startsWith($url, '//')) {
            return 'https:' . $url;
        }
        if (Str::startsWith($url, '/')) {
            return $baseUrl . $url;
        }
        return Str::startsWith($url, 'http') ? $url : $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Enhanced XML Sitemap including Image tags and active Live/Upcoming International Match pages.
     */
    public function sitemap(CricketDataService $cricket): Response
    {
        $xml = Cache::remember('sitemap_xml_v2', 180, function () use ($cricket) {
            $baseUrl = $this->baseUrl();

            $urls = [
                ['loc' => "{$baseUrl}/", 'changefreq' => 'always', 'priority' => '1.0'],
                ['loc' => "{$baseUrl}/live-scores?section=international", 'changefreq' => 'always', 'priority' => '0.9'],
                ['loc' => "{$baseUrl}/live-scores?section=local", 'changefreq' => 'always', 'priority' => '0.9'],
                ['loc' => "{$baseUrl}/articles", 'changefreq' => 'daily', 'priority' => '0.9'],
                ['loc' => "{$baseUrl}/matches", 'changefreq' => 'hourly', 'priority' => '0.9'],
                ['loc' => "{$baseUrl}/feed", 'changefreq' => 'hourly', 'priority' => '0.8'],
                ['loc' => "{$baseUrl}/grounds", 'changefreq' => 'weekly', 'priority' => '0.8'],
                ['loc' => "{$baseUrl}/umpires", 'changefreq' => 'weekly', 'priority' => '0.8'],
                ['loc' => "{$baseUrl}/meetups", 'changefreq' => 'daily', 'priority' => '0.8'],
                ['loc' => "{$baseUrl}/about", 'changefreq' => 'monthly', 'priority' => '0.6'],
                ['loc' => "{$baseUrl}/contact", 'changefreq' => 'monthly', 'priority' => '0.6'],
                ['loc' => "{$baseUrl}/privacy-policy", 'changefreq' => 'monthly', 'priority' => '0.5'],
                ['loc' => "{$baseUrl}/terms-of-service", 'changefreq' => 'monthly', 'priority' => '0.5'],
            ];

            // Include active live / upcoming / recent international scorecards so Google indexes match URLs
            try {
                $intlMatches = Cache::get('cricket_local_current_matches_stale')
                    ?? Cache::get('cricket_local_current_matches')
                    ?? [];
                if (empty($intlMatches)) {
                    $intlMatches = $cricket->getCurrentMatches();
                }

                $seenIds = [];
                foreach (array_slice((array) $intlMatches, 0, 60) as $m) {
                    $mid = trim((string) ($m['id'] ?? ''));
                    if ($mid === '' || Str::startsWith($mid, 'local_') || isset($seenIds[$mid])) {
                        continue;
                    }
                    $seenIds[$mid] = true;
                    $urls[] = [
                        'loc' => "{$baseUrl}/live-scores/" . rawurlencode($mid),
                        'lastmod' => now()->toAtomString(),
                        'changefreq' => (($m['status'] ?? '') === 'live') ? 'always' : 'hourly',
                        'priority' => (($m['status'] ?? '') === 'live') ? '0.9' : '0.8',
                    ];
                }
            } catch (\Throwable $e) {
                // Keep sitemap healthy even if external match feed times out
            }

            try {
                $articles = Article::published()
                    ->select(['id', 'title', 'slug', 'featured_image', 'category', 'updated_at', 'created_at', 'published_at'])
                    ->latest('published_at')
                    ->take(500)
                    ->get();

                foreach ($articles as $art) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/articles/{$art->slug}",
                        'lastmod' => ($art->updated_at ?? $art->published_at ?? $art->created_at)?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                        'image_loc' => $this->absoluteImageUrl($art->cover_image_url, $baseUrl),
                        'image_title' => $art->title,
                    ];
                }

                $matches = CricketMatch::select(['id', 'updated_at', 'created_at'])
                    ->latest('id')
                    ->take(100)
                    ->get();
                foreach ($matches as $m) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/matches/{$m->id}",
                        'lastmod' => ($m->updated_at ?? $m->created_at)?->toAtomString(),
                        'changefreq' => 'daily',
                        'priority' => '0.7',
                    ];
                }

                $grounds = Ground::select(['id', 'updated_at', 'created_at'])
                    ->latest('id')
                    ->take(100)
                    ->get();
                foreach ($grounds as $g) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/grounds/{$g->id}",
                        'lastmod' => ($g->updated_at ?? $g->created_at)?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ];
                }

                $umpires = UmpireProfile::select(['id', 'updated_at', 'created_at'])
                    ->latest('id')
                    ->take(100)
                    ->get();
                foreach ($umpires as $u) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/umpires/{$u->id}",
                        'lastmod' => ($u->updated_at ?? $u->created_at)?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore partial DB failures during sitemap generation
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
            foreach ($urls as $u) {
                $out .= "  <url>\n";
                $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
                if (!empty($u['lastmod'])) {
                    $out .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
                }
                $out .= '    <changefreq>' . ($u['changefreq'] ?? 'weekly') . "</changefreq>\n";
                $out .= '    <priority>' . ($u['priority'] ?? '0.8') . "</priority>\n";
                if (!empty($u['image_loc'])) {
                    $out .= "    <image:image>\n";
                    $out .= '      <image:loc>' . htmlspecialchars($u['image_loc'], ENT_XML1, 'UTF-8') . "</image:loc>\n";
                    if (!empty($u['image_title'])) {
                        $out .= '      <image:title>' . htmlspecialchars(Str::limit($u['image_title'], 120), ENT_XML1, 'UTF-8') . "</image:title>\n";
                    }
                    $out .= "    </image:image>\n";
                }
                $out .= "  </url>\n";
            }
            $out .= '</urlset>';

            return $out;
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Google News Sitemap (strictly articles published within the last 48 hours).
     */
    public function newsSitemap(): Response
    {
        $xml = Cache::remember('news_sitemap_xml_v1', 180, function () {
            $baseUrl = $this->baseUrl();
            $articles = collect();

            try {
                $cutoff = now()->subHours(48);
                $articles = Article::published()
                    ->select(['id', 'title', 'slug', 'tags', 'featured_image', 'category', 'published_at', 'created_at'])
                    ->where(function ($q) use ($cutoff) {
                        $q->where('published_at', '>=', $cutoff)
                          ->orWhere(function ($q2) use ($cutoff) {
                              $q2->whereNull('published_at')->where('created_at', '>=', $cutoff);
                          });
                    })
                    ->latest('published_at')
                    ->take(100)
                    ->get();
            } catch (\Throwable $e) {
                $articles = collect();
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

            foreach ($articles as $art) {
                $pubDate = ($art->published_at ?? $art->created_at)?->toAtomString() ?? now()->toAtomString();
                $imgLoc = $this->absoluteImageUrl($art->cover_image_url, $baseUrl);
                $out .= "  <url>\n";
                $out .= '    <loc>' . htmlspecialchars("{$baseUrl}/articles/{$art->slug}", ENT_XML1, 'UTF-8') . "</loc>\n";
                $out .= "    <news:news>\n";
                $out .= "      <news:publication>\n";
                $out .= "        <news:name>CricApp.in</news:name>\n";
                $out .= "        <news:language>en</news:language>\n";
                $out .= "      </news:publication>\n";
                $out .= '      <news:publication_date>' . htmlspecialchars($pubDate, ENT_XML1, 'UTF-8') . "</news:publication_date>\n";
                $out .= '      <news:title>' . htmlspecialchars($art->title, ENT_XML1, 'UTF-8') . "</news:title>\n";
                if (!empty($art->tags)) {
                    $out .= '      <news:keywords>' . htmlspecialchars(Str::limit($art->tags, 200, ''), ENT_XML1, 'UTF-8') . "</news:keywords>\n";
                }
                $out .= "    </news:news>\n";
                $out .= "    <image:image>\n";
                $out .= '      <image:loc>' . htmlspecialchars($imgLoc, ENT_XML1, 'UTF-8') . "</image:loc>\n";
                $out .= "    </image:image>\n";
                $out .= "  </url>\n";
            }

            $out .= '</urlset>';
            return $out;
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * RSS 2.0 Feed for Google Discover, News Aggregators, and Readers.
     */
    public function rssFeed(): Response
    {
        $xml = Cache::remember('rss_feed_xml_v1', 300, function () {
            $baseUrl = $this->baseUrl();
            $articles = collect();

            try {
                $articles = Article::published()
                    ->select(['id', 'title', 'slug', 'category', 'excerpt', 'content', 'featured_image', 'author_name', 'published_at', 'created_at'])
                    ->latest('published_at')
                    ->take(30)
                    ->get();
            } catch (\Throwable $e) {
                $articles = collect();
            }

            $lastBuild = $articles->first()
                ? (($articles->first()->published_at ?? $articles->first()->created_at)?->toRssString() ?? now()->toRssString())
                : now()->toRssString();

            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
            $out .= "  <channel>\n";
            $out .= "    <title>CricApp.in - Cricket News, Match Reports &amp; Live Scores</title>\n";
            $out .= '    <link>' . htmlspecialchars("{$baseUrl}/articles", ENT_XML1, 'UTF-8') . "</link>\n";
            $out .= '    <atom:link href="' . htmlspecialchars("{$baseUrl}/rss.xml", ENT_XML1, 'UTF-8') . '" rel="self" type="application/rss+xml" />' . "\n";
            $out .= "    <description>Latest cricket news, tactical breakdowns, pitch reports, domestic tournament coverage, and live scores on CricApp.in.</description>\n";
            $out .= "    <language>en-in</language>\n";
            $out .= '    <lastBuildDate>' . htmlspecialchars($lastBuild, ENT_XML1, 'UTF-8') . "</lastBuildDate>\n";

            foreach ($articles as $art) {
                $link = "{$baseUrl}/articles/{$art->slug}";
                $pubRss = ($art->published_at ?? $art->created_at)?->toRssString() ?? $lastBuild;
                $summary = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($art->excerpt ?: $art->content)))), 260);
                $imgLoc = $this->absoluteImageUrl($art->cover_image_url, $baseUrl);

                $out .= "    <item>\n";
                $out .= '      <title>' . htmlspecialchars($art->title, ENT_XML1, 'UTF-8') . "</title>\n";
                $out .= '      <link>' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</link>\n";
                $out .= '      <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</guid>\n";
                $out .= '      <pubDate>' . htmlspecialchars($pubRss, ENT_XML1, 'UTF-8') . "</pubDate>\n";
                if (!empty($art->category)) {
                    $out .= '      <category>' . htmlspecialchars($art->category, ENT_XML1, 'UTF-8') . "</category>\n";
                }
                $out .= '      <description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $summary) . "]]></description>\n";
                $out .= '      <media:content url="' . htmlspecialchars($imgLoc, ENT_XML1, 'UTF-8') . '" medium="image" />' . "\n";
                $out .= "    </item>\n";
            }

            $out .= "  </channel>\n";
            $out .= '</rss>';

            return $out;
        });

        return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    /**
     * Serve IndexNow verification key file automatically at /{key}.txt.
     */
    public function indexNowKey(string $key): Response
    {
        $configured = trim((string) config('services.indexnow.key', ''));
        if ($configured === '' || !hash_equals($configured, $key)) {
            abort(404);
        }

        return response($configured, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
