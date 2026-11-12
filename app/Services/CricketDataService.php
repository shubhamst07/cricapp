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

    public function cacheKey(string $key): string
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

    public function getCurrentMatches(bool $forceRefresh = false): array
    {
        $cacheKey = $this->cacheKey('local_current_matches');
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 12, function () {
            if ($this->provider === 'local') {
                try {
                    $response = Http::timeout(10)->get("{$this->localUrl}/matches");
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
        });
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
            $fresh = null;

            // 1. Try local service first
            if ($this->provider === 'local') {
                try {
                    $response = Http::timeout(8)->get("{$this->localUrl}/commentary/{$matchId}");
                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data) && ($data['status'] ?? '') === 'success' && !empty($data['data'])) {
                            $fresh = $data;
                        }
                    }
                } catch (\Throwable $e) {
                    // fallback to direct scraper
                }
            }

            // 2. Direct Cricbuzz scraper fallback in PHP
            if (!$fresh || empty($fresh['data'])) {
                $fresh = $this->scrapeCricbuzzCommentaryDirectly($matchId);
            }

            // 3. Accumulate over-wise data into TiDB / MySQL and persistent storage
            return $this->accumulateAndReturnAllOvers($matchId, $fresh);
        });
    }

    /**
     * Accumulate incoming commentary deliveries and save them over-wise in TiDB / MySQL & File Storage.
     * Then returns all accumulated overs so past overs are never lost.
     */
    public function accumulateAndReturnAllOvers(string $matchId, array $fresh): array
    {
        if (empty($fresh) || !is_array($fresh)) {
            $fresh = ['status' => 'success', 'matchId' => $matchId, 'data' => []];
        }

        $incoming = $fresh['data'] ?? [];

        // Group incoming items by Over Key
        $incomingGroups = [];
        $lastOverKey = null;

        // Pass 1: detect over keys for each incoming item
        foreach ($incoming as $item) {
            $overKey = null;
            if (isset($item['overNumber']) && is_numeric($item['overNumber'])) {
                $overKey = (string) (int) $item['overNumber'];
            } elseif (isset($item['over']) && is_numeric($item['over'])) {
                $overKey = (string) (int) floor((float) $item['over']);
            } elseif (isset($item['over']) && is_string($item['over']) && preg_match('/(\d+)(?:\.\d+)?/', $item['over'], $m)) {
                $overKey = (string) (int) $m[1];
            } elseif (isset($item['time']) && is_string($item['time']) && preg_match('/(\d+)(?:\.\d+)?/', $item['time'], $m)) {
                $overKey = (string) (int) $m[1];
            } elseif (isset($item['title']) && is_string($item['title']) && preg_match('/(\d+)\.\d+/', $item['title'], $m)) {
                $overKey = (string) (int) $m[1];
            }

            if ($overKey !== null) {
                $lastOverKey = $overKey;
            } elseif ($lastOverKey !== null) {
                $overKey = $lastOverKey;
            } else {
                $overKey = 'notes';
            }

            $incomingGroups[$overKey][] = $item;
        }

        // Save / Upsert each incoming over to TiDB / MySQL and local file storage
        $storedOvers = $this->loadStoredOvers($matchId);

        foreach ($incomingGroups as $overKey => $items) {
            $existingItems = $storedOvers[$overKey]['deliveries'] ?? [];
            $merged = $this->mergeDeliveries($existingItems, $items);

            $runs = 0;
            $wickets = 0;
            $bowler = null;
            $summary = null;

            foreach ($merged as $it) {
                if (($it['type'] ?? '') === 'over-separator') {
                    if (isset($it['overRuns'])) $runs = (int) $it['overRuns'];
                    if (!empty($it['overSummary'])) $summary = $it['overSummary'];
                    if (!empty($it['desc']) && preg_match('/Bowler:\s*([^(|]+)/i', $it['desc'], $bm)) {
                        $bowler = trim($bm[1]);
                    }
                } elseif (($it['type'] ?? '') === 'ball') {
                    $b = $it['badge'] ?? '';
                    if ($b === 'W' || ($it['badgeType'] ?? '') === 'wicket') {
                        $wickets++;
                    }
                    if (is_numeric($b)) {
                        $runs += (int) $b;
                    }
                    if (!$bowler && !empty($it['title']) && str_contains($it['title'], ' to ')) {
                        $bowler = trim(explode(' to ', $it['title'])[0]);
                    }
                }
            }

            $storedOvers[$overKey] = [
                'match_id' => $matchId,
                'over_key' => (string) $overKey,
                'over_number' => is_numeric($overKey) ? (int) $overKey : null,
                'runs' => $runs,
                'wickets' => $wickets,
                'bowler' => $bowler,
                'summary' => $summary,
                'deliveries' => $merged,
                'updated_at' => now()->toIso8601String(),
            ];

            // 1. Try Upserting into TiDB / MySQL Database
            try {
                \App\Models\MatchOver::updateOrCreate(
                    ['match_id' => $matchId, 'over_key' => (string) $overKey],
                    [
                        'over_number' => is_numeric($overKey) ? (int) $overKey : null,
                        'runs' => $runs,
                        'wickets' => $wickets,
                        'bowler' => $bowler,
                        'summary' => $summary,
                        'deliveries' => $merged,
                    ]
                );
            } catch (\Throwable $e) {
                // Silently continue if database is currently offline
            }
        }

        // 2. Persist to storage/app/overs/{matchId}.json as permanent file backup
        $this->saveStoredOversToFile($matchId, $storedOvers);

        // 3. Assemble all deliveries from all saved overs in descending order (highest over first)
        $allDeliveries = [];
        uksort($storedOvers, function ($a, $b) {
            if ($a === 'notes') return -1;
            if ($b === 'notes') return 1;
            return ((int) $b) <=> ((int) $a);
        });

        foreach ($storedOvers as $group) {
            foreach ($group['deliveries'] as $delivery) {
                $allDeliveries[] = $delivery;
            }
        }

        // Return combined data with full accumulated history
        $fresh['data'] = $allDeliveries;
        return $fresh;
    }

    private function loadStoredOvers(string $matchId): array
    {
        $overs = [];

        // 1. Try reading from Database (TiDB / MySQL)
        try {
            $dbOvers = \App\Models\MatchOver::where('match_id', $matchId)->get();
            if ($dbOvers->isNotEmpty()) {
                foreach ($dbOvers as $row) {
                    $overs[(string) $row->over_key] = [
                        'match_id' => $row->match_id,
                        'over_key' => (string) $row->over_key,
                        'over_number' => $row->over_number,
                        'runs' => (int) $row->runs,
                        'wickets' => (int) $row->wickets,
                        'bowler' => $row->bowler,
                        'summary' => $row->summary,
                        'deliveries' => is_array($row->deliveries) ? $row->deliveries : (json_decode($row->deliveries, true) ?: []),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // DB fallback
        }

        // 2. Also merge with file backup if exists
        $filePath = storage_path("app/overs/{$matchId}.json");
        if (file_exists($filePath)) {
            $fileData = json_decode(@file_get_contents($filePath), true);
            if (is_array($fileData)) {
                foreach ($fileData as $k => $v) {
                    if (!isset($overs[$k])) {
                        $overs[$k] = $v;
                    } else {
                        $overs[$k]['deliveries'] = $this->mergeDeliveries($overs[$k]['deliveries'] ?? [], $v['deliveries'] ?? []);
                    }
                }
            }
        }

        return $overs;
    }

    private function saveStoredOversToFile(string $matchId, array $storedOvers): void
    {
        try {
            $dir = storage_path('app/overs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents("{$dir}/{$matchId}.json", json_encode($storedOvers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function mergeDeliveries(array $existing, array $incoming): array
    {
        $map = [];
        foreach ($existing as $it) {
            $key = $it['id'] ?? (($it['time'] ?? '') . '-' . ($it['over'] ?? '') . '-' . ($it['title'] ?? ''));
            $map[$key] = $it;
        }
        foreach ($incoming as $it) {
            $key = $it['id'] ?? (($it['time'] ?? '') . '-' . ($it['over'] ?? '') . '-' . ($it['title'] ?? ''));
            $map[$key] = $it;
        }

        return array_values($map);
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

                $rawText = $item['commText'] ?? '';
                // Skip duplicate flash alerts
                if (str_contains($rawText, 'THATS OUT!!')) {
                    continue;
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
                } else {
                    $ball = $item['ballMetric'] ?? null;
                    $cleanText = trim(strip_tags($rawText));

                    // Verify if this is an actual ball delivery
                    $isBall = false;
                    $ballFloat = null;
                    if (!empty($ball) && $ball !== '$undefined' && is_numeric($ball)) {
                        $ballFloat = (float) $ball;
                        $isBall = true;
                    }

                    if ($isBall) {
                        $overStr = (string) $ball;
                        $badge = '0';
                        $badgeType = 'dot';
                        $tag = null;
                        $textLower = strtolower($cleanText);

                        if (in_array('four', $events) || str_contains(strtolower($rawText), '<b>four</b>') || str_contains($textLower, ', four') || str_ends_with($textLower, ', four')) {
                            $badge = '4';
                            $badgeType = 'four';
                            $tag = 'Boundary 4';
                        } elseif (in_array('six', $events) || str_contains(strtolower($rawText), '<b>six</b>') || str_contains($textLower, ', six') || str_ends_with($textLower, ', six')) {
                            $badge = '6';
                            $badgeType = 'six';
                            $tag = 'Maximum 6';
                        } elseif (in_array('wicket', $events) || str_contains(strtolower($rawText), '<b>out</b>') || str_contains($textLower, ', out ') || preg_match('/,\s*out\s+(bowled|caught|lbw|run out|stumped)/i', $textLower)) {
                            $badge = 'W';
                            $badgeType = 'wicket';
                            $tag = 'Wicket ☝️';
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
                            'over' => $overStr,
                            'ballMetric' => $ballFloat,
                            'badge' => $badge,
                            'badgeType' => $badgeType,
                            'title' => $title,
                            'desc' => $desc ?: $cleanText,
                            'rawText' => $cleanText,
                            'batsman' => $batsman,
                            'bowler' => $bowler,
                            'tag' => $tag,
                            'timestamp' => $timestamp,
                            'time' => "Ball {$overStr}",
                        ];
                    } else {
                        // Non-ball item: Post-match interview quote, match wrap-up, or tactical note
                        if (preg_match('/^([A-Za-z\s.\-]+):\s*(.+)$/s', $cleanText, $matchesQuote)) {
                            $speaker = trim($matchesQuote[1]);
                            $speech = trim($matchesQuote[2]);
                            $formatted[] = [
                                'id' => "speech-{$commId}",
                                'type' => 'interview',
                                'over' => 'QUOTE',
                                'badge' => '🎙️',
                                'badgeType' => 'expert',
                                'title' => "{$speaker} (Post-Match Take)",
                                'desc' => $speech,
                                'tag' => 'Player Quote 🎙️',
                                'timestamp' => $timestamp,
                                'time' => 'Presentation',
                            ];
                        } elseif (preg_match('/(clinch|win|local time|semis|champions|victory)/i', $cleanText)) {
                            $formatted[] = [
                                'id' => "wrap-{$commId}",
                                'type' => 'match_wrap',
                                'over' => 'FINAL',
                                'badge' => '🏆',
                                'badgeType' => 'expert',
                                'title' => 'Match Summary & Wrap-up',
                                'desc' => $cleanText,
                                'tag' => 'Match Result 🏆',
                                'timestamp' => $timestamp,
                                'time' => 'Wrap-up',
                            ];
                        } else {
                            $formatted[] = [
                                'id' => "note-{$commId}",
                                'type' => 'note',
                                'over' => 'NOTE',
                                'badge' => '📋',
                                'badgeType' => 'expert',
                                'title' => 'Match Note',
                                'desc' => $cleanText,
                                'tag' => 'Tactical Note',
                                'timestamp' => $timestamp,
                                'time' => 'Update',
                            ];
                        }
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

    public function normalizeScore(?array $rawScores, ?string $statusText = '', ?bool $matchEnded = false, ?string $matchType = ''): ?array
    {
        if (empty($rawScores) || !is_array($rawScores)) {
            return null;
        }

        $scores = [];
        $isCompletedMatch = (bool) $matchEnded || (
            !empty($statusText) && (
                str_contains(strtolower($statusText), 'won by') ||
                str_contains(strtolower($statusText), 'won the') ||
                str_contains(strtolower($statusText), ' won') ||
                str_contains(strtolower($statusText), 'beat') ||
                str_contains(strtolower($statusText), 'tied') ||
                str_contains(strtolower($statusText), 'drawn') ||
                str_contains(strtolower($statusText), 'conceded') ||
                str_contains(strtolower($statusText), 'complete') ||
                str_contains(strtolower($statusText), 'result')
            )
        );

        foreach ($rawScores as $idx => $s) {
            if (!is_array($s)) {
                continue;
            }

            $inningName = (string) ($s['inning'] ?? $s['batting_team'] ?? $s['team'] ?? '');
            $runs = isset($s['r']) ? (int) $s['r'] : (isset($s['runs']) ? (int) $s['runs'] : 0);
            $wickets = isset($s['w']) ? (int) $s['w'] : (isset($s['wickets']) ? (int) $s['wickets'] : 0);
            $rawOvers = $s['o'] ?? $s['overs'] ?? null;
            $overs = ($rawOvers !== null && $rawOvers !== '' && $rawOvers !== '0' && $rawOvers !== 0 && $rawOvers !== '0.0') ? (string) $rawOvers : null;

            // Intelligent correction for all-out teams:
            // When a team is all out in cricket (10 wickets), some APIs omit wickets or return w: 0.
            // 1. Chasing team (2nd innings) in a completed match won by runs:
            //    If won by runs (e.g. India won by 70 runs, Pak won by 101 runs), chasing team lost!
            //    They cannot have 0 wickets. If wickets == 0 or overs prematurely stopped, they were ALL OUT (10 wickets).
            if ($isCompletedMatch && $idx === 1 && $wickets === 0) {
                $isWonByRuns = str_contains(strtolower($statusText ?? ''), 'won by') && str_contains(strtolower($statusText ?? ''), 'run');
                $floatOvers = $overs ? (float) $overs : 0;
                $isLimitedOvers = str_contains(strtolower($matchType ?? ''), 'odi') || str_contains(strtolower($matchType ?? ''), 't20') || str_contains(strtolower($matchType ?? ''), 'match') || str_contains(strtolower($inningName), 'u19');
                if ($isWonByRuns || ($floatOvers > 0 && $floatOvers < 50 && $isLimitedOvers)) {
                    $wickets = 10;
                }
            }

            // 2. First innings: If overs stopped prematurely (e.g. 43.4 ov in 50 ov match) in a completed match:
            if ($idx === 0 && $wickets === 0 && $overs && $isCompletedMatch) {
                $floatOvers = (float) $overs;
                $isODI = str_contains(strtolower($matchType ?? ''), 'odi') || str_contains(strtolower($inningName), 'u19') || str_contains(strtolower($statusText ?? ''), 'u19');
                if ($isODI && $floatOvers > 0 && $floatOvers < 49.5) {
                    $wickets = 10;
                }
            }

            $displayScore = (string) ($s['display_score'] ?? '');
            if (empty($displayScore) || ($displayScore === "{$runs}/0" && $wickets > 0)) {
                $displayScore = "{$runs}/{$wickets}";
            }

            $cleanTeam = trim(preg_replace('/\s*innings\s*\d*/i', '', $inningName));

            $scores[] = [
                'inning' => $cleanTeam ?: $inningName,
                'batting_team' => $cleanTeam ?: $inningName,
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
            $matchType = (string) ($match['matchType'] ?? '');

            // Detect completed status from text keywords (must not match toss events or ongoing test events)
            $stLower = strtolower($statusText);
            $isToss = str_contains($stLower, 'toss') || str_contains($stLower, 'opt to') || str_contains($stLower, 'elected to') || str_contains($stLower, 'chose to');
            $isTestOngoing = (str_contains($stLower, 'stump') || str_contains($stLower, 'trail') || str_contains($stLower, 'lead') || preg_match('/day\s*[1-4]/i', $stLower))
                && !str_contains($stLower, 'won') && !str_contains($stLower, 'beat') && !str_contains($stLower, 'drawn');

            $isCompletedByText = !$isToss && !$isTestOngoing && (
                str_contains($stLower, 'won by') ||
                str_contains($stLower, 'won the match') ||
                str_contains($stLower, 'beat') ||
                str_contains($stLower, 'tied') ||
                str_contains($stLower, 'drawn') ||
                str_contains($stLower, 'conceded') ||
                str_contains($stLower, 'match ended')
            );

            if ($isCompletedByText) {
                $matchEnded = true;
            } elseif ($isTestOngoing || $isToss) {
                $matchEnded = false;
                $matchStarted = true;
            }

            $score = $this->normalizeScore($match['score'] ?? null, $statusText, $matchEnded, $matchType);

            // Check if both teams completed 50 overs (ODI) or 20 overs (T20) - NOT for test matches!
            $isTestMatch = str_contains(strtolower($matchType), 'test') || $isTestOngoing;
            if (!$isTestMatch && !empty($score) && count($score) >= 2) {
                $inn2Overs = (float) ($score[1]['overs'] ?? 0);
                if ($inn2Overs >= 50 || ($inn2Overs >= 20 && str_contains(strtolower($matchType), 't20'))) {
                    $matchEnded = true;
                }
            }

            // ONLY if match is NOT ended, should score imply in progress
            if (!empty($score)) {
                $matchStarted = true;
                if (!$matchEnded && in_array($stLower, ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                    if ($isTestMatch && count($score) >= 2) {
                        $r1 = (int) ($score[0]['runs'] ?? $score[0]['r'] ?? 0);
                        $r2 = (int) ($score[1]['runs'] ?? $score[1]['r'] ?? 0);
                        $rawT2 = $score[1]['batting_team'] ?: ($match['teams'][1] ?? 'Team 2');
                        $t2 = trim(preg_replace('/\s*innings\s*\d*/i', '', $rawT2));
                        if ($r1 > $r2) {
                            $statusText = "{$t2} trail by " . ($r1 - $r2) . " runs";
                        } elseif ($r2 > $r1) {
                            $statusText = "{$t2} lead by " . ($r2 - $r1) . " runs";
                        } else {
                            $statusText = "Scores Level";
                        }
                    } else {
                        $statusText = 'In Progress';
                    }
                }
            } elseif (empty($score) && in_array($stLower, ['', 'scheduled', 'match scheduled', 'upcoming'])) {
                // Definitely an upcoming scheduled match
                $matchStarted = false;
                $matchEnded = false;
            }

            // If match is ended, status_text must never be 'In Progress' or empty
            if ($matchEnded) {
                if (in_array(strtolower(trim($statusText)), ['', 'scheduled', 'match scheduled', 'upcoming', 'in progress'])) {
                    // Try to generate result from scores if possible
                    if (!empty($score) && count($score) >= 2) {
                        $r1 = (int) ($score[0]['runs'] ?? 0);
                        $r2 = (int) ($score[1]['runs'] ?? 0);
                        $t1 = $score[0]['batting_team'] ?: ($match['teams'][0] ?? 'Team 1');
                        $rawT2 = $score[1]['batting_team'] ?: ($match['teams'][1] ?? 'Team 2');
                        $t2 = trim(preg_replace('/\s*innings\s*\d*/i', '', $rawT2));
                        if ($r1 > $r2) {
                            $statusText = "{$t1} won by " . ($r1 - $r2) . " runs";
                        } elseif ($r2 > $r1) {
                            $statusText = "{$t2} won";
                        } else {
                            $statusText = "Match Tied";
                        }
                    } else {
                        $statusText = 'Match Completed';
                    }
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
                'matchType' => $matchType,
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
        $matchType = (string) ($data['matchType'] ?? '');

        // Detect completed status (must not match toss events)
        $stLower = strtolower($statusText);
        $isToss = str_contains($stLower, 'toss');
        $isCompletedByText = !$isToss && (
            str_contains($stLower, 'won by') ||
            str_contains($stLower, 'won the match') ||
            str_contains($stLower, 'beat') ||
            str_contains($stLower, 'tied') ||
            str_contains($stLower, 'drawn') ||
            str_contains($stLower, 'conceded') ||
            str_contains($stLower, 'match ended')
        );

        if ($isCompletedByText) {
            $matchEnded = true;
        }

        $score = $this->normalizeScore($data['score'] ?? null, $statusText, $matchEnded, $matchType);

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

    public function getCachedScorecard(string $matchId): ?array
    {
        return Cache::get($this->cacheKey("scorecard_{$matchId}"));
    }

    public function getCachedCommentary(string $matchId): ?array
    {
        return Cache::get($this->cacheKey("commentary_{$matchId}"));
    }

    public function clearMatchCache(?string $matchId = null): void
    {
        if ($matchId) {
            Cache::forget($this->cacheKey("match_{$matchId}"));
            Cache::forget($this->cacheKey("scorecard_{$matchId}"));
        } else {
            Cache::forget($this->cacheKey('local_current_matches'));
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
