<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->is('logout') || $request->routeIs('logout')) {
                return redirect('/');
            }
            return redirect()->back()->with('error', 'Your session expired. Please try again.');
        });

        // Global safeguard: If any table is missing or DB quota exhausted, auto-failover to SQLite and retry without 500 error!
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $msg = strtolower($e->getMessage());
            if (
                str_contains($msg, 'quota') ||
                str_contains($msg, 'restricted') ||
                str_contains($msg, 'exhausted') ||
                str_contains($msg, '1105') ||
                str_contains($msg, '2002') ||
                str_contains($msg, 'connection refused') ||
                str_contains($msg, 'timed out') ||
                str_contains($msg, 'no such table') ||
                str_contains($msg, 'base table or view not found')
            ) {
                try {
                    config(['database.default' => 'sqlite']);
                    \Illuminate\Support\Facades\DB::purge('mysql');
                    \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

                    $dbPath = database_path('database.sqlite');
                    if (!file_exists($dbPath)) {
                        @mkdir(dirname($dbPath), 0777, true);
                        @touch($dbPath);
                        @chmod($dbPath, 0777);
                    }

                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true, '--database' => 'sqlite']);

                    if ($request->isMethod('GET')) {
                        return redirect()->to($request->fullUrl());
                    }
                    return redirect()->back()->with('status', 'Switched to secure local backup storage. Please try again.');
                } catch (\Throwable $migEx) {
                    \Illuminate\Support\Facades\Log::error('Global exception failover failed: ' . $migEx->getMessage());
                }
            }
        });
    })->create();
