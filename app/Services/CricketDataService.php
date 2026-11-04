<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CricketDataService
{
    protected string $provider;
    protected string $localUrl;
    protected string $baseUrl = 'https://api.cricapi.com/v1';
    protected string $apiKey;

    protected int $currentMatchesTtl;
    protected int $matchDetailTtl;
    protected int $scorecardTtl;

    public function __construct()
    {
        $this->provider = (string) config('services.cricket.provider', 'local');
        $this->localUrl = rtrim((string) config('services.cricket.local_url', 'http://127.0.0.1:6020'), '/');
        $this->apiKey = (string) config('services.cricket.api_key');

        // If local provider: short TTL (5-10s) because there is no API limit!
        // If CricAPI provider: long TTL to preserve 100 hits/day limit.
        $isLocal = ($this->provider === 'local');
        $this->currentMatchesTtl = (int) config('services.cricket.ttl.current_matches', $isLocal ? 10 : 900);
        $this->matchDetailTtl = (int) config('services.cricket.ttl.match_detail', $isLocal ? 10 : 1800);
        $this->scorecardTtl = (int) config('services.cricket.ttl.scorecard', $isLocal ? 10 : 1800);
    }

    protected function cacheKey(string $key): string
    {
        return 'cricket_' . $key;
    }

    protected function get(string $endpoint, array $params = [], int $ttl = 0, string $cacheKey = ''): mixed
    {
        $cKey = $cacheKey ?: $this->cacheKey(md5($endpoint . serialize($params)));

        if ($ttl > 0) {
            return Cache::remember($cKey, $ttl, fn() => $this->doRequest($endpoint, $params));
        }

        return $this->doRequest($endpoint, $params);
    }

    protected function doRequest(string $endpoint, array $params = []): mixed
    {
        // 1. Try local service first if provider is local
        if ($this->provider === 'local') {
            try {
                $url = "{$this->localUrl}/" . ltrim($endpoint, '/');
                $response = Http::timeout(20)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url, $params);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // Local request failed, fall through to fallback or return error
            }
        }

        // 2. CricAPI fallback if API key is set
        if (!empty($this->apiKey)) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get("{$this->baseUrl}/{$endpoint}", array_merge($params, ['apikey' => $this->apiKey]));

                if ($response->successful()) {
                    return $response->json();
                }

                if ($response->status() === 429) {
                    return ['error' => 'API rate limit exceeded. Please try again later.'];
                }

                return ['error' => "API error: {$response->status()} {$response->body()}"];
            } catch (\Throwable $e) {
                return ['error' => 'Failed to fetch data: ' . $e->getMessage()];
            }
        }

        return ['error' => 'Live Cricket Service unavailable'];
    }

    public function getCurrentMatches(): array
    {
        if ($this->provider === 'local') {
            try {
                $response = Http::timeout(25)->get("{$this->localUrl}/matches");
                if ($response->successful()) {
                    $raw = $response->json();
                    if ($raw && isset($raw['data']) && is_array($raw['data']) && !empty($raw['data'])) {
                        return $this->normalizeMatches($raw);
                    }
                }
            } catch (\Throwable $e) {
                // ignore and try fallback
            }
        }

        // CricAPI provider or fallback
        $raw = $this->get('currentMatches', ['offset' => 0], $this->currentMatchesTtl, $this->cacheKey('cricapi_current_matches'));
        return $this->normalizeMatches($raw);
    }

    public function getUpcomingMatches(): array
    {
        if ($this->provider === 'local') {
            $all = $this->getCurrentMatches();
            return collect($all)->where('status', 'scheduled')->values()->toArray();
        }

        $raw = $this->get('matches', ['offset' => 0], $this->currentMatchesTtl, $this->cacheKey('all_matches'));
        if (empty($raw) || isset($raw['error'])) {
            return [];
        }
        $matches = $this->normalizeMatches($raw);
        return collect($matches)->where('status', 'scheduled')->values()->toArray();
    }

    public function getCricketNews(): array
    {
        if ($this->provider === 'local') {
            $raw = Cache::remember($this->cacheKey('cricket_news'), 60, function () {
                try {
                    $response = Http::timeout(5)->get("{$this->localUrl}/news");
                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                return null;
            });

            if ($raw && isset($raw['data']) && is_array($raw['data'])) {
                return $raw['data'];
            }
        }
        return [];
    }

    public function getMatchDetails(string $matchId): array
    {
        if ($this->provider === 'local') {
            $raw = Cache::remember($this->cacheKey("match_{$matchId}"), $this->matchDetailTtl, function () use ($matchId) {
                try {
                    $response = Http::timeout(15)->get("{$this->localUrl}/match/{$matchId}");
                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                return null;
            });

            if ($raw && isset($raw['data'])) {
                return $this->normalizeMatchDetail($raw);
            }
        }

        $raw = $this->get('match_info', ['id' => $matchId], $this->matchDetailTtl, $this->cacheKey("match_{$matchId}"));
        return $this->normalizeMatchDetail($raw);
    }

    public function getMatchScorecard(string $matchId): array
    {
        if ($this->provider === 'local') {
            return Cache::remember($this->cacheKey("scorecard_{$matchId}"), $this->scorecardTtl, function () use ($matchId) {
                try {
                    $response = Http::timeout(15)->get("{$this->localUrl}/scorecard/{$matchId}");
                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                return ['status' => 'error', 'data' => ['batting' => [], 'bowling' => []]];
            });
        }

        $raw = $this->get('match_scorecard', ['id' => $matchId], $this->scorecardTtl, $this->cacheKey("scorecard_{$matchId}"));
        return $raw;
    }

    public function getPlayerProfile(string $playerId, string $name = ''): array
    {
        $cacheKey = $this->cacheKey("player_{$playerId}");
        return Cache::remember($cacheKey, 3600, function () use ($playerId, $name) {
            try {
                $response = Http::timeout(6)->get("{$this->localUrl}/player/{$playerId}", [
                    'name' => $name,
                ]);
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // ignore
            }
            return ['status' => 'error', 'message' => 'Player profile not found'];
        });
    }

    public function normalizeScore(?array $rawScores): ?array
    {
        if (empty($rawScores) || !is_array($rawScores)) {
            return null;
        }

        $scores = [];
        foreach ($rawScores as $s) {
            if (!is_array($s)) {
                continue;
            }

            $inningName = (string) ($s['inning'] ?? $s['batting_team'] ?? $s['team'] ?? '');
            $runs = isset($s['r']) ? (int) $s['r'] : (isset($s['runs']) ? (int) $s['runs'] : 0);
            $wickets = isset($s['w']) ? (int) $s['w'] : (isset($s['wickets']) ? (int) $s['wickets'] : 0);
            $rawOvers = $s['o'] ?? $s['overs'] ?? null;
            $overs = ($rawOvers !== null && $rawOvers !== '' && $rawOvers !== '0' && $rawOvers !== 0 && $rawOvers !== '0.0') ? (string) $rawOvers : null;
            $displayScore = (string) ($s['display_score'] ?? "{$runs}/{$wickets}");

            $scores[] = [
                'inning' => $inningName,
                'batting_team' => $inningName,
                'runs' => $runs,
                'r' => $runs,
                'wickets' => $wickets,
                'w' => $wickets,
                'overs' => $overs,
                'o' => $overs,
                'display_score' => $displayScore,
            ];
        }

        return !empty($scores) ? $scores : null;
    }

    protected function normalizeMatches(array|false|null $raw): array
    {
        if (!$raw || !isset($raw['data']) || !is_array($raw['data'])) {
            return [];
        }

        $matches = [];

        foreach ($raw['data'] as $match) {
            $statusText = (string) ($match['status_text'] ?? $match['status'] ?? '');
            $matchStarted = (bool) ($match['matchStarted'] ?? false);
            $matchEnded = (bool) ($match['matchEnded'] ?? false);

            $mappedStatus = $this->mapStatus($statusText, $matchStarted, $matchEnded);

            $matches[] = [
                'id' => (string) ($match['id'] ?? ''),
                'name' => (string) ($match['name'] ?? ''),
                'status' => $mappedStatus,
                'status_text' => $statusText,
                'series' => (string) ($match['series'] ?? $match['series_name'] ?? 'Other Matches'),
                'series_name' => (string) ($match['series_name'] ?? $match['series'] ?? 'Other Matches'),
                'venue' => (string) ($match['venue'] ?? ''),
                'matchType' => (string) ($match['matchType'] ?? ''),
                'teams' => $match['teams'] ?? [],
                'score' => $this->normalizeScore($match['score'] ?? null),
                'dateTimeGMT' => (string) ($match['dateTimeGMT'] ?? ''),
                'hasJadouT20League' => (int) ($match['hasJadouT20League'] ?? 0),
                'matchStarted' => $matchStarted || ($mappedStatus === 'live' || $mappedStatus === 'completed'),
                'matchEnded' => $matchEnded || ($mappedStatus === 'completed'),
            ];
        }

        return $matches;
    }

    protected function normalizeMatchDetail(array $raw): array
    {
        
        if (!$raw || isset($raw['error']) || !isset($raw['data'])) {
            return [];
        }

        $data = $raw['data'] ?? [];
        $statusText = (string) ($data['status_text'] ?? $data['status'] ?? '');
        $matchStarted = (bool) ($data['matchStarted'] ?? false);
        $matchEnded = (bool) ($data['matchEnded'] ?? false);
        $mappedStatus = $this->mapStatus($statusText, $matchStarted, $matchEnded);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'name' => (string) ($data['name'] ?? $data['title'] ?? ''),
            'status' => $mappedStatus,
            'status_text' => $statusText,
            'venue' => (string) ($data['venue'] ?? ''),
            'matchType' => (string) ($data['matchType'] ?? ''),
            'teams' => $data['teams'] ?? [],
            'score' => $this->normalizeScore($data['score'] ?? null),
            'dateTimeGMT' => (string) ($data['dateTimeGMT'] ?? ''),
            'matchStarted' => $matchStarted || ($mappedStatus === 'live' || $mappedStatus === 'completed'),
            'matchEnded' => $matchEnded || ($mappedStatus === 'completed'),
            'tossWinner' => (string) ($data['tossWinner'] ?? ''),
            'tossChoice' => (string) ($data['tossChoice'] ?? ''),
            'winnerTeam' => (string) ($data['winnerTeam'] ?? ''),
            'season' => (string) ($data['season'] ?? ''),
            'series' => $data['series'] ?? [],
            'current_batsmen' => $data['current_batsmen'] ?? [],
            'current_bowler' => $data['current_bowler'] ?? [],
            'player_of_the_match' => $data['player_of_the_match'] ?? null,
        ];
    }

    public function mapStatus(string $apiStatus, bool $matchStarted = false, bool $matchEnded = false): string
    {
        $status = strtolower(trim($apiStatus));

        // 1. Abandoned / Cancelled / No Result
        if (str_contains($status, 'abandon') || str_contains($status, 'cancel') || str_contains($status, 'no result')) {
            return 'abandoned';
        }

        // 2. Definitive match completed indicators
        $isCompletedText = str_contains($status, 'won by') ||
            str_contains($status, 'won the match') ||
            str_contains($status, ' won') ||
            str_starts_with($status, 'won') ||
            str_contains($status, 'beat') ||
            str_contains($status, 'tied') ||
            str_contains($status, 'drawn') ||
            str_contains($status, 'conceded');

        if ($isCompletedText || $matchEnded) {
            return 'completed';
        }

        // 3. Active in-game / live text indicators
        $isLiveText = str_contains($status, 'need ') ||
            str_contains($status, 'opt to') ||
            str_contains($status, 'elected to') ||
            str_contains($status, 'session') ||
            str_contains($status, 'day ') ||
            str_contains($status, 'break') ||
            str_contains($status, 'stump') ||
            str_contains($status, 'tea') ||
            str_contains($status, 'lunch') ||
            str_contains($status, 'delay') ||
            str_contains($status, 'trail') ||
            str_contains($status, 'lead') ||
            str_contains($status, 'target') ||
            str_contains($status, 'progress') ||
            str_contains($status, 'live') ||
            str_contains($status, 'innings break') ||
            str_contains($status, 'drinks');

        // 4. If match has started (or in-game text active), it is Live!
        if ($isLiveText || $matchStarted) {
            return 'live';
        }

        // 5. Otherwise it is Scheduled / Upcoming
        return 'scheduled';
    }

    public function findMatchById(array $matches, string $matchId): ?array
    {
        foreach ($matches as $match) {
            if ((string) $match['id'] === (string) $matchId) {
                return $match;
            }
        }
        return null;
    }

    public function validateApiKey(): array
    {
        if ($this->provider === 'local') {
            try {
                $response = Http::timeout(5)->get("{$this->localUrl}/matches");
                if ($response->successful()) {
                    return [
                        'valid' => true,
                        'message' => 'Live Cricket Local API is connected (No Rate Limit)'
                    ];
                }
            } catch (\Throwable $e) {
                return [
                    'valid' => false,
                    'message' => 'Cannot connect to local Cricket API: ' . $e->getMessage()
                ];
            }
        }

        $response = $this->get('currentMatches', ['offset' => 0], 0, $this->cacheKey('api_health_check'));

        if (isset($response['error'])) {
            return ['valid' => false, 'message' => $response['error']];
        }

        return ['valid' => true, 'message' => 'API key is valid'];
    }

    public function getCachedMatches(): array
    {
        return Cache::get($this->cacheKey('current_matches'), []);
    }

    public function clearMatchCache(?string $matchId = null): void
    {
        if ($matchId) {
            Cache::forget($this->cacheKey("match_{$matchId}"));
            Cache::forget($this->cacheKey("scorecard_{$matchId}"));
        } else {
            Cache::forget($this->cacheKey('current_matches'));
            Cache::forget($this->cacheKey('cricapi_current_matches'));
            Cache::forget($this->cacheKey('all_matches'));
        }
    }

    public function getCacheTtl(): array
    {
        return [
            'current_matches' => $this->currentMatchesTtl,
            'match_detail' => $this->matchDetailTtl,
            'scorecard' => $this->scorecardTtl,
        ];
    }
}
