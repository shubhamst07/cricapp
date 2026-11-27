<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IndexNowService
{
    /**
     * Ping IndexNow (Bing, DuckDuckGo, Yandex, ChatGPT Search) when content is published/updated.
     */
    public function pingUrls(array $urls): bool
    {
        $key = trim((string) config('services.indexnow.key', ''));
        $baseUrl = rtrim((string) config('app.url', 'https://www.cricapp.in'), '/');
        if ($key === '' || !Str::startsWith($baseUrl, 'http') || empty($urls)) {
            return false;
        }

        // Only ping production/public URLs (skip localhost dev testing)
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        $cleanUrls = array_values(array_unique(array_filter(array_map('trim', $urls))));
        if (empty($cleanUrls)) {
            return false;
        }

        $ipv4Curl = defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')
            ? ['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]
            : [];

        try {
            $resp = Http::withOptions($ipv4Curl)
                ->connectTimeout(1)
                ->timeout(2)
                ->post('https://api.indexnow.org/indexnow', [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => "{$baseUrl}/{$key}.txt",
                    'urlList' => $cleanUrls,
                ]);

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::debug('IndexNow ping skipped: ' . $e->getMessage());
            return false;
        }
    }
}
