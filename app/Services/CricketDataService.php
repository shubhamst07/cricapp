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

    public function getMatchCommentary(string $matchId): array
    {
        $cacheKey = $this->cacheKey("commentary_{$matchId}");
        return Cache::remember($cacheKey, 6, function () use ($matchId) {
            // 1. Try local service first
            if ($this->provider === 'local') {
                try {
                    $response = Http::timeout(8)->get("{$this->localUrl}/commentary/{$matchId}");
                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data) && ($data['status'] ?? '') === 'success' && !empty($data['data'])) {
                            return $data;
                        }
                    }
                } catch (\Throwable $e) {
                    // fallback to direct scraper
                }
            }

            // 2. Direct Cricbuzz scraper fallback in PHP
            return $this->scrapeCricbuzzCommentaryDirectly($matchId);
        });
    }

    public function scrapeCricbuzzCommentaryDirectly(string $matchId): array
    {
        try {
            $url = "https://www.cricbuzz.com/live-cricket-scores/{$matchId}?_=" . round(microtime(true) * 1000);
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://www.cricbuzz.com/',
                ])
                ->get($url);

            if (!$response->successful()) {
                return ['status' => 'error', 'message' => 'Unable to fetch Cricbuzz stream', 'data' => []];
            }

            $html = $response->body();
            $marker = 'commentaryPageData';
            $idx = strpos($html, $marker);
            if ($idx === false) {
                return ['status' => 'success', 'matchId' => $matchId, 'miniscore' => null, 'data' => []];
            }

            $braceStart = strpos($html, '{', $idx);
            if ($braceStart === false) {
                return ['status' => 'success', 'matchId' => $matchId, 'miniscore' => null, 'data' => []];
            }

            $depth = 0;
            $braceEnd = -1;
            $len = strlen($html);
            for ($i = $braceStart; $i < $len; $i++) {
                if ($html[$i] === '{') {
                    $depth++;
                } elseif ($html[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $braceEnd = $i;
                        break;
                    }
                }
            }

            if ($braceEnd === -1) {
                return ['status' => 'success', 'matchId' => $matchId, 'miniscore' => null, 'data' => []];
            }

            $rawJson = substr($html, $braceStart, $braceEnd - $braceStart + 1);
            $cleaned = str_replace(["\\\\\\\"", "\\\"", "\\\\\\\\"], ["\"", "\"", "\\"], $rawJson);
            $parsed = json_decode($cleaned, true);

            if (!$parsed || !isset($parsed['matchCommentary'])) {
                return ['status' => 'success', 'matchId' => $matchId, 'miniscore' => null, 'data' => []];
            }

            $matchComm = $parsed['matchCommentary'];
            $miniscore = $parsed['miniscore'] ?? null;
            $formatted = [];

            foreach ($matchComm as $commId => $item) {
                $ctype = $item['commType'] ?? 'commentary';
                $timestamp = $item['timestamp'] ?? 0;
                $events = $item['event'] ?? [];
                if (is_string($events)) {
                    $events = [$events];
                }

                if ($ctype === 'over-separator') {
                    $overSep = $item['overSeparator'] ?? [];
                    $overNum = $overSep['overNumber'] ?? null;
                    $overRuns = $overSep['overRuns'] ?? 0;
                    $summary = trim($overSep['overSummary'] ?? '');
                    $batTeam = $overSep['batTeamObj']['teamName'] ?? '';
                    $batScore = $overSep['batTeamObj']['teamScore'] ?? '';
                    $striker = $overSep['batStrikerObj'] ?? [];
                    $bowler = $overSep['bowlerObj'] ?? [];

                    $subTitle = "End of Over {$overNum}";
                    if ($batTeam && $batScore) {
                        $subTitle .= " • {$batTeam} {$batScore}";
                    }

                    $descParts = [];
                    if ($summary) {
                        $descParts[] = "Over summary: {$summary} ({$overRuns} runs)";
                    } else {
                        $descParts[] = "{$overRuns} runs in over {$overNum}";
                    }
                    if (!empty($bowler['playerName'])) {
                        $descParts[] = "Bowler: {$bowler['playerName']} (" . ($bowler['playerScore'] ?? '') . ")";
                    }
                    if (!empty($striker['playerName'])) {
                        $descParts[] = "Batting: {$striker['playerName']} " . ($striker['playerScore'] ?? '');
                    }

                    $formatted[] = [
                        'id' => "over-{$overNum}-{$commId}",
                        'type' => 'over-separator',
                        'over' => "Over {$overNum}",
                        'overNumber' => $overNum,
                        'badge' => 'OV',
                        'badgeType' => 'expert',
                        'title' => $subTitle,
                        'desc' => implode(' | ', $descParts),
                        'overSummary' => $summary,
                        'overRuns' => $overRuns,
                        'timestamp' => $timestamp,
                        'time' => 'Over Break',
                        'tag' => "Over {$overNum}",
                    ];
                } elseif ($ctype === 'commentary') {
                    $ball = $item['ballMetric'] ?? null;
                    $overStr = $ball !== null ? (string) $ball : '';
                    $rawText = $item['commText'] ?? '';
                    $cleanText = trim(strip_tags($rawText));

                    $badge = '0';
                    $badgeType = 'dot';
                    $tag = null;
                    $textLower = strtolower($rawText);

                    if (in_array('four', $events) || str_contains($textLower, 'four') || str_contains($textLower, ', four')) {
                        $badge = '4';
                        $badgeType = 'four';
                        $tag = 'Boundary 4';
                    } elseif (in_array('six', $events) || str_contains($textLower, 'six') || str_contains($textLower, ', six')) {
                        $badge = '6';
                        $badgeType = 'six';
                        $tag = 'Maximum 6';
                    } elseif (
                        array_intersect(['wicket', 'out', 'wk'], $events) ||
                        preg_match('/(out,|caught|bowled|lbw|run out)/i', $textLower)
                    ) {
                        $badge = 'W';
                        $badgeType = 'wicket';
                        $tag = 'Wicket 🎯';
                    } elseif (in_array('wide', $events) || str_contains($textLower, 'wide') || str_contains($textLower, 'wd')) {
                        $badge = 'Wd';
                        $badgeType = 'extra';
                        $tag = 'Wide';
                    } elseif (in_array('no-ball', $events) || str_contains($textLower, 'no ball') || str_contains($textLower, 'nb')) {
                        $badge = 'Nb';
                        $badgeType = 'extra';
                        $tag = 'No Ball';
                    } elseif (str_contains($textLower, '1 run') || in_array('one', $events)) {
                        $badge = '1';
                        $badgeType = 'run';
                    } elseif (str_contains($textLower, '2 runs') || in_array('two', $events)) {
                        $badge = '2';
                        $badgeType = 'run';
                    } elseif (str_contains($textLower, '3 runs') || in_array('three', $events)) {
                        $badge = '3';
                        $badgeType = 'run';
                    } elseif (str_contains($textLower, 'leg bye') || str_contains($textLower, 'bye')) {
                        $badge = 'B';
                        $badgeType = 'run';
                    }

                    $batsman = $item['batsmanDetails']['playerName'] ?? null;
                    $bowler = $item['bowlerDetails']['playerName'] ?? null;

                    $parts = explode(',', $cleanText, 3);
                    if (count($parts) >= 2 && (str_contains($parts[0], 'to') || in_array($badgeType, ['four', 'six', 'wicket']))) {
                        $title = trim($parts[0]) . ', ' . trim($parts[1]);
                        $desc = isset($parts[2]) ? trim($parts[2]) : '';
                    } else {
                        $title = $cleanText;
                        $desc = '';
                    }

                    $formatted[] = [
                        'id' => "ball-{$commId}",
                        'type' => 'ball',
                        'over' => $overStr ?: 'LIVE',
                        'ballMetric' => $ball,
                        'badge' => $badge,
                        'badgeType' => $badgeType,
                        'title' => $title,
                        'desc' => $desc ?: $cleanText,
                        'rawText' => $cleanText,
                        'batsman' => $batsman,
                        'bowler' => $bowler,
                        'tag' => $tag,
                        'timestamp' => $timestamp,
                        'time' => $overStr ? "Ball {$overStr}" : 'Live',
                    ];
                } else {
                    $rawText = $item['commText'] ?? '';
                    $cleanText = trim(strip_tags($rawText));
                    if ($cleanText) {
                        $formatted[] = [
                            'id' => "note-{$commId}",
                            'type' => 'note',
                            'over' => 'NOTE',
                            'badge' => '💡',
                            'badgeType' => 'expert',
                            'title' => $cleanText,
                            'desc' => $cleanText,
                            'tag' => 'Match Note',
                            'timestamp' => $timestamp,
                            'time' => 'Update',
                        ];
                    }
                }
            }

            usort($formatted, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

            return [
                'status' => 'success',
                'matchId' => $matchId,
                'miniscore' => $miniscore,
                'data' => $formatted,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
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
            $score = $this->normalizeScore($match['score'] ?? null);

            // If score has any inning/runs/overs, match is actively underway
            if (!empty($score)) {
                $matchStarted = true;
                if (in_array(strtolower(trim($statusText)), ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                    $statusText = 'In Progress';
                }
            }

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
                'score' => $score,
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
        $score = $this->normalizeScore($data['score'] ?? null);

        // Check if there are active batsmen, live score display, or score data
        $hasActiveBatsmen = !empty($data['current_batsmen']) && collect($data['current_batsmen'])->contains(fn($b) => !empty($b['name']) && $b['name'] !== 'score not found');
        $hasLiveScoreDisplay = !empty($data['live_score_display']) && $data['live_score_display'] !== 'score not found';
        $hasScoreData = !empty($score) || $hasActiveBatsmen || $hasLiveScoreDisplay;

        if ($hasScoreData && !$matchEnded) {
            $matchStarted = true;
            if (in_array(strtolower(trim($statusText)), ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                $statusText = $hasLiveScoreDisplay ? "Live: {$data['live_score_display']}" : 'In Progress';
            }
        }

        $mappedStatus = $this->mapStatus($statusText, $matchStarted, $matchEnded);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'name' => (string) ($data['name'] ?? $data['title'] ?? ''),
            'status' => $mappedStatus,
            'status_text' => $statusText,
            'venue' => (string) ($data['venue'] ?? ''),
            'matchType' => (string) ($data['matchType'] ?? ''),
            'teams' => $data['teams'] ?? [],
            'score' => $score,
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
            'live_score_display' => $data['live_score_display'] ?? null,
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
            str_contains($status, 'chose to') ||
            str_contains($status, 'choose to') ||
            str_contains($status, 'batting') ||
            str_contains($status, 'bowling') ||
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
            str_contains($status, 'rain') ||
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
