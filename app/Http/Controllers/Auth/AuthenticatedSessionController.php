<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $this->ensureDatabaseReady();

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureDatabaseReady();

        try {
            $request->authenticate();

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Authentication error on /login: ' . $e->getMessage(), [
                'exception' => $e,
                'email' => $request->input('email'),
            ]);

            return redirect()->back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Server error while logging in: ' . $e->getMessage() . '. Please try again.'
                ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            Auth::guard('web')->logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            Log::warning('Logout error: ' . $e->getMessage());
        }

        return redirect('/');
    }

    /**
     * Ensure database tables exist before authentication operations.
     */
    protected function ensureDatabaseReady(): void
    {
        try {
            $dbPath = database_path('database.sqlite');
            if (config('database.default') === 'sqlite') {
                if (!file_exists($dbPath)) {
                    @mkdir(dirname($dbPath), 0777, true);
                    @touch($dbPath);
                    @chmod($dbPath, 0777);
                }
            }

            if (!Schema::hasTable('users') || !Schema::hasTable('sessions') || !Schema::hasTable('articles')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-migration in AuthenticatedSessionController: ' . $e->getMessage());
        }
    }
}
