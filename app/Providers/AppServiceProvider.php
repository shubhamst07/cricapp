<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production' || !empty(request()->server('HTTP_X_FORWARDED_PROTO'))) {
            URL::forceScheme('https');
        }

        // 1. Ensure SQLite database directory & file exist
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

            // 2. Auto-run migrations if essential tables are missing
            if (!Schema::hasTable('users') || !Schema::hasTable('sessions') || !Schema::hasTable('articles')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('AppServiceProvider boot DB initialization: ' . $e->getMessage());
        }

        // 3. Resilient session fallback: If sessions table is missing or DB unreachable, fall back to file driver
        try {
            if (config('session.driver') === 'database' && !Schema::hasTable('sessions')) {
                config(['session.driver' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['session.driver' => 'file']);
        }

        // 4. Resilient cache fallback: If cache table is missing or DB unreachable, fall back to file driver
        try {
            if (config('cache.default') === 'database' && !Schema::hasTable('cache')) {
                config(['cache.default' => 'file']);
            }
        } catch (\Throwable $e) {
            config(['cache.default' => 'file']);
        }
    }
}
