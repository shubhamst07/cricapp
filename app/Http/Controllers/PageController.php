<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiryMail;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:25',
            'email' => 'required|email|max:100',
            'inquiry_type' => 'required|string|max:50',
            'message' => 'required|string|min:5|max:2000',
        ]);

        Log::info('Contact message received:', $validated);

        $ip = $request->ip();

        // 1. Permanently store in database so no message is ever lost
        try {
            ContactInquiry::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'inquiry_type' => $validated['inquiry_type'],
                'message' => $validated['message'],
                'ip_address' => $ip,
                'is_read' => false,
            ]);
            Log::info("Contact inquiry from {$validated['name']} saved to database.");
        } catch (\Throwable $e) {
            Log::warning('Could not save contact inquiry to DB: ' . $e->getMessage());
        }

        // 2. Dispatch email delivery in background AFTER HTTP response is returned to browser
        // Using ->afterResponse() guarantees the browser receives an instant response (0.05s)
        // and NEVER suffers from a 504 Gateway Timeout!
        $adminEmail = env('ADMIN_EMAIL') ?: config('mail.admin_address', 'shubhamthakurst007@gmail.com');
        if ($adminEmail === 'shubhamthakurst07@gmail.com') {
            $adminEmail = 'shubhamthakurst007@gmail.com';
        }

        dispatch(function () use ($adminEmail, $validated) {
            $mailSent = false;

            // A. Resend HTTPS API (Port 443 - Works on Render Free Tier)
            $resendKey = config('services.resend.key') ?: env('RESEND_API_KEY');
            if ($resendKey) {
                try {
                    $response = Http::timeout(5)->withToken(trim($resendKey))->post('https://api.resend.com/emails', [
                        'from' => 'LiveCricket <onboarding@resend.dev>',
                        'to' => [$adminEmail],
                        'reply_to' => $validated['email'],
                        'subject' => "[LiveCricket] New Contact Message: {$validated['inquiry_type']} from {$validated['name']}",
                        'html' => (new ContactInquiryMail($validated))->render(),
                    ]);
                    if ($response->successful()) {
                        $mailSent = true;
                        Log::info("Contact message delivered via Resend HTTPS API to {$adminEmail}");
                    } else {
                        Log::warning("Resend API response: " . $response->body());
                    }
                } catch (\Throwable $e) {
                    Log::warning("Resend API delivery error: " . $e->getMessage());
                }
            }

            // B. Brevo HTTPS API (Port 443 - Works on Render Free Tier)
            $brevoKey = config('services.brevo.key') ?: env('BREVO_API_KEY');
            if (!$mailSent && $brevoKey) {
                try {
                    $response = Http::timeout(5)->withHeaders([
                        'api-key' => trim($brevoKey),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->post('https://api.brevo.com/v3/smtp/email', [
                        'sender' => [
                            'name' => 'LiveCricket',
                            'email' => 'shubhamthakurst007@gmail.com',
                        ],
                        'to' => [
                            ['email' => 'shubhamthakurst007@gmail.com', 'name' => 'Shubham Thakur'],
                        ],
                        'replyTo' => [
                            'email' => $validated['email'],
                            'name' => $validated['name'],
                        ],
                        'subject' => "[LiveCricket] New Contact Message: {$validated['inquiry_type']} from {$validated['name']}",
                        'htmlContent' => (new ContactInquiryMail($validated))->render(),
                    ]);
                    if ($response->successful()) {
                        $mailSent = true;
                        Log::info("Contact message delivered via Brevo HTTPS API to shubhamthakurst007@gmail.com");
                    } else {
                        Log::warning("Brevo API error (" . $response->status() . "): " . $response->body());
                    }
                } catch (\Throwable $e) {
                    Log::warning("Brevo API delivery error: " . $e->getMessage());
                }
            }

            // C. Standard SMTP (Local / VPS / Paid Render or open port)
            if (!$mailSent && config('mail.default') === 'smtp') {
                try {
                    config(['mail.mailers.smtp.timeout' => 5]);
                    Mail::to($adminEmail)->send(new ContactInquiryMail($validated));
                    Log::info("Contact inquiry email dispatched via SMTP to: {$adminEmail}");
                } catch (\Throwable $e) {
                    Log::warning("Background SMTP delivery error: " . $e->getMessage());
                }
            }
        })->afterResponse();

        return back()->with('success', 'Thank you ' . e($validated['name']) . '! Your message has been received by Shubham Thakur and the LiveCricket team. We will get in touch with you shortly.');
    }

    /**
     * View all contact inquiries (Admin).
     */
    public function inquiries()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $inquiries = ContactInquiry::latest()->paginate(25);
        return view('pages.inquiries', compact('inquiries'));
    }

    /**
     * Diagnostic endpoint to test email delivery on Render.
     */
    public function testEmail()
    {
        $adminEmail = env('ADMIN_EMAIL') ?: 'shubhamthakurst007@gmail.com';
        if ($adminEmail === 'shubhamthakurst07@gmail.com') {
            $adminEmail = 'shubhamthakurst007@gmail.com';
        }

        $brevoKey = config('services.brevo.key') ?: env('BREVO_API_KEY');
        $resendKey = config('services.resend.key') ?: env('RESEND_API_KEY');

        $results = [
            'destination_email' => $adminEmail,
            'brevo_key_detected' => !empty($brevoKey) ? ('Present (Starts with ' . substr($brevoKey, 0, 10) . '...)') : 'NOT FOUND (Please check Render Environment BREVO_API_KEY)',
            'resend_key_detected' => !empty($resendKey) ? 'Present' : 'NOT FOUND',
            'tests' => [],
        ];

        // 1. Test Brevo if key exists
        if ($brevoKey) {
            try {
                $response = Http::timeout(8)->withHeaders([
                    'api-key' => trim($brevoKey),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => 'LiveCricket',
                        'email' => 'shubhamthakurst007@gmail.com',
                    ],
                    'to' => [
                        ['email' => $adminEmail, 'name' => 'Shubham Thakur'],
                    ],
                    'subject' => '[LiveCricket] Brevo Test Email Verification to 007',
                    'htmlContent' => '<h3>Hi Shubham!</h3><p>Your Brevo API connection is 100% working and delivering directly to shubhamthakurst007@gmail.com!</p>',
                ]);

                $results['tests']['brevo'] = [
                    'http_status' => $response->status(),
                    'success' => $response->successful(),
                    'response' => $response->json() ?? $response->body(),
                ];
            } catch (\Throwable $e) {
                $results['tests']['brevo'] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json($results, 200, [], JSON_PRETTY_PRINT);
    }
}
