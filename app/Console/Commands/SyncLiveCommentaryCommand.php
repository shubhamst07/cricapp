<?php

namespace App\Console\Commands;

use App\Models\CricketMatch;
use App\Services\CricketDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLiveCommentaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cricket:sync-live {--daemon : Run continuously as a background daemon} {--interval=30 : Polling interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Polls active live cricket matches and persists scorecards, commentary, and ball-by-ball overs into the database.';

    /**
     * Execute the console command.
     */
    public function handle(CricketDataService $cricket): int
    {
        $isDaemon = $this->option('daemon');
        $interval = max(10, (int) $this->option('interval'));

        $this->info("Starting Cricket Live Sync" . ($isDaemon ? " (Daemon mode every {$interval}s)..." : "..."));

        // Setup graceful shutdown for daemon mode
        $keepRunning = true;
        if ($isDaemon && extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$keepRunning) {
                $this->warn("\nTermination signal received. Exiting gracefully...");
                $keepRunning = false;
            });
            pcntl_signal(SIGINT, function () use (&$keepRunning) {
                $this->warn("\nInterrupt signal received. Exiting gracefully...");
                $keepRunning = false;
            });
        }

        do {
            $startTime = microtime(true);
            $syncedCount = $this->syncActiveMatches($cricket);
            $duration = round(microtime(true) - $startTime, 2);

            $this->line(sprintf("[%s] Sync cycle finished in %ss. Active matches synced: %d", now()->toDateTimeString(), $duration, $syncedCount));

            if (!$isDaemon) {
                break;
            }

            // Sleep interval with short ticks to respond quickly to signals
            for ($s = 0; $s < $interval && $keepRunning; $s++) {
                sleep(1);
            }
        } while ($keepRunning);

        $this->info("Cricket Live Sync stopped.");
        return 0;
    }

    /**
     * Synchronize all currently live matches (international and local).
     */
    public function syncActiveMatches(CricketDataService $cricket): int
    {
        $synced = 0;
        $liveMatchIds = [];

        try {
            // 1. Fetch current matches from Cricbuzz / API
            $currentMatches = $cricket->getCurrentMatches(true);

            foreach ($currentMatches as $match) {
                $status = strtolower($match['status'] ?? '');
                $stText = strtolower($match['status_text'] ?? '');
                $matchId = (string) ($match['id'] ?? '');

                if (!$matchId) {
                    continue;
                }

                // Detect if match is live or currently in progress
                $isCompleted = ($match['matchEnded'] ?? false) ||
                    $status === 'completed' ||
                    $status === 'abandoned' ||
                    str_contains($stText, 'won by') ||
                    str_contains($stText, 'won the match') ||
                    str_contains($stText, 'beat ') ||
                    str_contains($stText, 'tied');

                $isInningsBreak = str_contains($stText, 'innings break') ||
                    str_contains($stText, 'inn break') ||
                    str_contains($stText, 'break') ||
                    str_contains($stText, 'lunch') ||
                    str_contains($stText, 'tea') ||
                    str_contains($stText, 'dinner') ||
                    str_contains($stText, 'strategic timeout') ||
                    str_contains($stText, 'drinks');

                $isLive = !$isCompleted && (
                    $status === 'live' ||
                    $status === 'in progress' ||
                    $isInningsBreak ||
                    str_contains($stText, 'need ') ||
                    str_contains($stText, 'require ') ||
                    str_contains($stText, 'target ') ||
                    str_contains($stText, 'opt to') ||
                    str_contains($stText, 'elected to') ||
                    str_contains($stText, 'chose to') ||
                    str_contains($stText, 'trail') ||
                    str_contains($stText, 'lead') ||
                    (!empty($match['score']) && !$isCompleted)
                );

                if ($isLive) {
                    $liveMatchIds[$matchId] = $match['name'] ?? "Match #{$matchId}";
                }
            }
        } catch (\Throwable $e) {
            Log::warning("CricketLiveSync: Failed to fetch current matches: " . $e->getMessage());
        }

        // 2. Fetch local matches from database that are in 'live' status
        try {
            $localMatches = CricketMatch::where('status', 'live')->get();
            foreach ($localMatches as $lm) {
                $liveMatchIds["local_{$lm->id}"] = "{$lm->team1_name} vs {$lm->team2_name}";
            }
        } catch (\Throwable $e) {
            // DB offline fallback
        }

        // 3. Process each live match: update scorecard and accumulate overs/commentary
        foreach ($liveMatchIds as $matchId => $title) {
            try {
                // If it's an external international match
                if (!str_starts_with($matchId, 'local_')) {
                    // Update scorecard cache & DB
                    $cricket->getMatchScorecard($matchId);

                    // Fetch commentary (this triggers accumulateAndReturnAllOvers and backfillMissingOversFromHighlights)
                    $comm = $cricket->getMatchCommentary($matchId);

                    $deliveriesCount = is_array($comm['data'] ?? null) ? count($comm['data']) : 0;
                    $this->info(" -> Synced [{$matchId}] {$title} | Deliveries recorded: {$deliveriesCount}");
                }

                $synced++;
            } catch (\Throwable $e) {
                Log::warning("CricketLiveSync: Error syncing match {$matchId}: " . $e->getMessage());
            }
        }

        return $synced;
    }
}
