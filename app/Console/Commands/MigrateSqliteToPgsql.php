<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSqliteToPgsql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:migrate-sqlite-to-pgsql {--force : Force migration even if target has data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely migrate existing local SQLite data into PostgreSQL cloud database on first run';

    /**
     * Tables in dependency order.
     */
    protected array $tables = [
        'users',
        'teams',
        'grounds',
        'ground_bookings',
        'umpire_profiles',
        'umpire_bookings',
        'matches',
        'scores',
        'match_players',
        'match_overs',
        'match_posts',
        'post_comments',
        'follows',
        'contact_inquiries',
        'cricket_circles',
        'circle_members',
        'match_meetups',
        'meetup_participants',
        'articles',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            DB::connection('pgsql')->getPdo();
        } catch (\Throwable $e) {
            $this->warn("PostgreSQL cloud connection not reachable: " . $e->getMessage());
            return Command::SUCCESS;
        }

        $sqlitePath = database_path('database.sqlite');
        if (!file_exists($sqlitePath) || filesize($sqlitePath) < 4096) {
            $this->warn("Local SQLite database not found or empty at {$sqlitePath}. Skipping migration.");
            return Command::SUCCESS;
        }

        // Check if pgsql already has existing data (e.g. articles or matches already migrated)
        $hasData = false;
        try {
            if (Schema::connection('pgsql')->hasTable('matches') && DB::connection('pgsql')->table('matches')->count() > 0) {
                $hasData = true;
            }
        } catch (\Throwable $e) {
            $this->warn("Could not check pgsql tables: " . $e->getMessage());
        }

        if ($hasData && !$this->option('force')) {
            $this->info("PostgreSQL database already contains data. Skipping initial SQLite migration.");
            return Command::SUCCESS;
        }

        $this->info("Starting data transfer from SQLite to PostgreSQL...");

        foreach ($this->tables as $table) {
            try {
                if (!Schema::connection('sqlite')->hasTable($table) || !Schema::connection('pgsql')->hasTable($table)) {
                    continue;
                }

                $rows = DB::connection('sqlite')->table($table)->get();
                if ($rows->isEmpty()) {
                    continue;
                }

                $this->line("Migrating table [{$table}] ({$rows->count()} rows)...");

                foreach ($rows->chunk(50) as $chunk) {
                    $insertData = [];
                    foreach ($chunk as $row) {
                        $insertData[] = (array) $row;
                    }
                    DB::connection('pgsql')->table($table)->insertOrIgnore($insertData);
                }

                // Reset PostgreSQL auto-increment sequence for ID column if exists
                try {
                    $maxId = DB::connection('pgsql')->table($table)->max('id');
                    if ($maxId) {
                        DB::connection('pgsql')->statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$maxId})");
                    }
                } catch (\Throwable $seqEx) {
                    // Ignore sequence errors for tables without standard id
                }

            } catch (\Throwable $tEx) {
                $this->warn("Error migrating table [{$table}]: " . $tEx->getMessage());
            }
        }

        $this->info("Data migration from SQLite to PostgreSQL completed successfully!");
        return Command::SUCCESS;
    }
}
