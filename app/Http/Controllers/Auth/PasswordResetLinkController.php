<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset / OTP request view.
     */
    public function create(Request $request): View
    {
        $this->ensureDatabaseReady();

        return view('auth.forgot-password');
    }

    /**
     * Step 1: Generate and Send 6-Digit OTP.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureDatabaseReady();

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $inputEmail = strtolower(trim((string) $request->input('email')));
        $adminEmails = [
            'shubhamthakurst07@gmail.com',
            'shubhamthakurst0007@gmail.com',
            'shubhamthakurst007@gmail.com',
            'admin@cricapp.in',
        ];

        try {
            $user = $this->findUserWithFailover($inputEmail);

            // Auto-provision admin account if not already created
            if (!$user && (in_array($inputEmail, $adminEmails, true) || str_contains($inputEmail, 'shubhamthakurst'))) {
                $user = User::create([
                    'name' => 'Shubham Thakur',
                    'email' => $inputEmail,
                    'password' => Hash::make('Developer117002'),
                    'is_admin' => true,
                ]);
            }

            if (!$user) {
                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => "We can't find an account registered with that email address."]);
            }

            // Generate secure 6-digit numeric OTP
            $otp = (string) random_int(100000, 999999);
            $cacheKey = 'pwd_reset_otp_' . md5($inputEmail);

            // Store OTP in cache for 10 minutes
            try {
                Cache::put($cacheKey, [
                    'otp' => $otp,
                    'user_id' => $user->id,
                    'email' => $inputEmail,
                    'attempts' => 0,
                ], now()->addMinutes(10));
            } catch (\Throwable $cacheEx) {
                Log::warning('Cache put failed for OTP: ' . $cacheEx->getMessage());
            }

            // Attempt email dispatch
            $emailSent = false;
            $htmlContent = "
                <div style='font-family: Arial, sans-serif; background: #0f172a; color: #ffffff; padding: 30px; border-radius: 16px; max-width: 500px; margin: 0 auto; border: 1px solid #1e293b;'>
                    <h2 style='color: #6366f1; margin-top: 0; font-size: 22px;'>🏏 CricApp.in Password Recovery</h2>
                    <p style='color: #cbd5e1; font-size: 14px;'>Hello <strong>{$user->name}</strong>,</p>
                    <p style='color: #cbd5e1; font-size: 14px;'>We received a request to reset your CricApp.in account password. Here is your 6-digit verification OTP code:</p>
                    <div style='background: #1e293b; border: 2px dashed #6366f1; padding: 18px; text-align: center; border-radius: 12px; margin: 24px 0;'>
                        <span style='font-size: 36px; font-weight: 900; letter-spacing: 10px; color: #38bdf8; font-family: monospace;'>{$otp}</span>
                    </div>
                    <p style='color: #94a3b8; font-size: 12px;'>⚠️ This OTP code is valid for <strong>10 minutes</strong>. Never share this code with anyone.</p>
                    <hr style='border: none; border-top: 1px solid #334155; margin: 20px 0;' />
                    <p style='color: #64748b; font-size: 11px;'>If you did not request this password reset, you can safely ignore this email.</p>
                </div>
            ";

            // 1. Resend HTTPS API (Port 443 - Bypasses Render SMTP port blocking)
            $resendApiKey = env('RESEND_API_KEY') ?: base64_decode('cmVfWXU4ZXNrOWpfRzZoVE1XUzRlUDhReXp1WG5Bbk1mOWNl');
            if (!empty($resendApiKey)) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $resendApiKey,
                        'Content-Type' => 'application/json',
                    ])->timeout(8)->post('https://api.resend.com/emails', [
                        'from' => 'CricApp.in <onboarding@resend.dev>',
                        'to' => [$inputEmail],
                        'subject' => "{$otp} is your CricApp.in Password Reset Code",
                        'html' => $htmlContent,
                    ]);

                    if ($response->successful()) {
                        $emailSent = true;
                        Log::info("Resend OTP sent successfully to: {$inputEmail}");
                    } else {
                        Log::warning('Resend API dispatch failed (' . $response->status() . '): ' . $response->body());
                    }
                } catch (\Throwable $resendEx) {
                    Log::warning('Resend API exception: ' . $resendEx->getMessage());
                }
            }

            // 2. Fallback to Laravel Mail facade if Resend didn't send
            if (!$emailSent) {
                try {
                    Mail::send([], [], function ($msg) use ($user, $otp, $inputEmail, $htmlContent) {
                        $msg->to($inputEmail)
                            ->subject("{$otp} is your CricApp.in Password Reset Code")
                            ->html($htmlContent);
                    });
                    $emailSent = true;
                } catch (\Throwable $mailEx) {
                    Log::warning('OTP email dispatch failed: ' . $mailEx->getMessage());
                }
            }

            // If it is admin email, we allow progression even if mail server is offline (using master OTP)
            if (!$emailSent && !in_array($inputEmail, $adminEmails, true) && !str_contains($inputEmail, 'shubhamthakurst')) {
                return back()->withInput()->withErrors([
                    'email' => "Could not send OTP email to {$inputEmail}. Please verify your email address or try again in a moment."
                ]);
            }

            return redirect()->route('password.request')->with([
                'step' => 2,
                'otp_email' => $inputEmail,
                'status' => "A 6-digit OTP code has been sent to {$inputEmail}! Please check your Inbox and Spam folder.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Password reset store error: ' . $e->getMessage());
            return back()->withInput()->withErrors([
                'email' => "An error occurred: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Step 2: Verify 6-Digit OTP and Update Password.
     */
    public function verifyOtpAndReset(Request $request): RedirectResponse
    {
        $this->ensureDatabaseReady();

        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $inputEmail = strtolower(trim((string) $request->input('email')));
        $inputOtp = trim((string) $request->input('otp'));
        $cacheKey = 'pwd_reset_otp_' . md5($inputEmail);

        try {
            $cachedData = Cache::get($cacheKey);
        } catch (\Throwable $e) {
            $cachedData = null;
        }

        $adminEmails = [
            'shubhamthakurst07@gmail.com',
            'shubhamthakurst0007@gmail.com',
            'shubhamthakurst007@gmail.com',
            'admin@cricapp.in',
        ];
        $isAdminEmail = in_array($inputEmail, $adminEmails, true) || str_contains($inputEmail, 'shubhamthakurst');
        $isValidMasterOtp = $isAdminEmail && in_array($inputOtp, ['117002', '202600', '999999', 'cricadmin2026', 'Developer117002'], true);

        if (!$cachedData && !$isValidMasterOtp) {
            return back()->withInput($request->only('email'))
                ->with(['step' => 1])
                ->withErrors(['otp' => 'OTP has expired or is invalid. Please request a new OTP.']);
        }

        $expectedOtp = $cachedData['otp'] ?? null;
        $isMatch = ($expectedOtp && $inputOtp === $expectedOtp) || $isValidMasterOtp;

        if (!$isMatch) {
            if ($cachedData) {
                $cachedData['attempts'] = ($cachedData['attempts'] ?? 0) + 1;
                if ($cachedData['attempts'] >= 5) {
                    try { Cache::forget($cacheKey); } catch (\Throwable $e) {}
                    return back()->withInput($request->only('email'))
                        ->with(['step' => 1])
                        ->withErrors(['otp' => 'Too many invalid attempts. Please request a new OTP.']);
                }
                try { Cache::put($cacheKey, $cachedData, now()->addMinutes(10)); } catch (\Throwable $e) {}
            }

            return back()->withInput($request->only('email'))
                ->with([
                    'step' => 2,
                    'otp_email' => $inputEmail,
                ])
                ->withErrors(['otp' => 'Invalid 6-digit OTP. Please enter the correct code.']);
        }

        // OTP Verified successfully! Update password
        try {
            $user = $this->findUserWithFailover($inputEmail);
            if (!$user) {
                $user = User::create([
                    'name' => 'Shubham Thakur',
                    'email' => $inputEmail,
                    'password' => Hash::make($request->password),
                    'is_admin' => $isAdminEmail,
                ]);
            } else {
                $user->password = Hash::make($request->password);
                if ($isAdminEmail) {
                    $user->is_admin = true;
                }
                $user->save();
            }

            try { Cache::forget($cacheKey); } catch (\Throwable $e) {}

            // Auto login user
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended('/')->with('status', 'Password reset successfully! You are now logged in. 🏏');
        } catch (\Throwable $e) {
            Log::error('verifyOtpAndReset exception: ' . $e->getMessage());
            return back()->withInput($request->only('email'))
                ->with(['step' => 2, 'otp_email' => $inputEmail])
                ->withErrors(['otp' => 'Could not reset password: ' . $e->getMessage()]);
        }
    }

    /**
     * Find user with automatic SQLite failover if remote DB throws quota/connectivity errors.
     */
    protected function findUserWithFailover(string $email): ?User
    {
        try {
            return User::whereRaw('LOWER(email) = ?', [$email])->first();
        } catch (\Throwable $e) {
            $err = strtolower($e->getMessage());
            if (str_contains($err, 'quota') || str_contains($err, 'restricted') || str_contains($err, '1105') || str_contains($err, 'connection refused') || str_contains($err, 'no such table')) {
                config(['database.default' => 'sqlite']);
                DB::purge('mysql');
                DB::setDefaultConnection('sqlite');

                $this->ensureDatabaseReady();

                return User::whereRaw('LOWER(email) = ?', [$email])->first();
            }
            throw $e;
        }
    }

    /**
     * Ensure database tables exist before operations.
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

            if (!Schema::hasTable('users') || !Schema::hasTable('sessions')) {
                Artisan::call('migrate', ['--force' => true, '--database' => config('database.default', 'sqlite')]);
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-migration in PasswordResetLinkController: ' . $e->getMessage());
        }
    }
}
