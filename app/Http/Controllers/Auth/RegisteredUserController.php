<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $this->ensureDatabaseReady();

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureDatabaseReady();

        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'phone' => ['nullable', 'string', 'max:25'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            event(new Registered($user));

            Auth::login($user);

            return redirect(route('dashboard', absolute: false));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Registration error: ' . $e->getMessage(), ['exception' => $e]);

            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Unable to create account right now: ' . $e->getMessage()]);
        }
    }

    /**
     * Ensure database tables exist before registration operations.
     */
    protected function ensureDatabaseReady(): void
    {
        try {
            $conn = config('database.default', 'sqlite');
            $dbPath = database_path('database.sqlite');
            if ($conn === 'sqlite') {
                if (!file_exists($dbPath)) {
                    @mkdir(dirname($dbPath), 0777, true);
                    @touch($dbPath);
                    @chmod($dbPath, 0777);
                }
            }

            if (!Schema::hasTable('users') || !Schema::hasTable('sessions')) {
                Artisan::call('migrate', ['--force' => true, '--database' => $conn ?: 'sqlite']);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-migration in RegisteredUserController: ' . $e->getMessage());
        }
    }
}
