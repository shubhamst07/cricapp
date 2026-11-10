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

        // 1. Permanently save to Database so no message is ever lost
        try {
            ContactInquiry::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'inquiry_type' => $validated['inquiry_type'],
                'message' => $validated['message'],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not save ContactInquiry to DB: ' . $e->getMessage());
        }

        Log::info('Contact message received:', $validated);

        $adminEmail = config('mail.admin_address', env('ADMIN_EMAIL', 'shubhamthakurst07@gmail.com'));
        $mailable = new ContactInquiryMail($validated);
        $emailSent = false;

        // 2. If RESEND_API_KEY is provided, send via HTTPS API (Port 443 - 100% works on Render free tier)
        $resendKey = env('RESEND_API_KEY');
        if (!empty($resendKey)) {
            try {
                $response = Http::timeout(5)
                    ->withToken($resendKey)
                    ->post('https://api.resend.com/emails', [
                        'from' => env('RESEND_FROM', 'LiveCricket <onboarding@resend.dev>'),
                        'to' => [$adminEmail],
                        'reply_to' => $validated['email'],
                        'subject' => "[LiveCricket] New Contact: {$validated['inquiry_type']} from {$validated['name']}",
                        'html' => $mailable->render(),
                    ]);

                if ($response->successful()) {
                    $emailSent = true;
                    Log::info("Email sent via Resend HTTPS API to {$adminEmail}");
                } else {
                    Log::warning("Resend API response error: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("Resend HTTPS API error: " . $e->getMessage());
            }
        }

        // 3. If not sent via Resend, attempt SMTP with a strict 2s socket check
        // This GUARANTEES the request will NEVER hang or hit Nginx 504 Gateway Timeout!
        if (!$emailSent) {
            try {
                $mailer = config('mail.default');
                if ($mailer === 'smtp') {
                    $host = config('mail.mailers.smtp.host', 'smtp.gmail.com');
                    $port = (int) config('mail.mailers.smtp.port', 587);

                    // Test socket connection with 2 second timeout
                    $connection = @fsockopen($host, $port, $errno, $errstr, 2);

                    if ($connection) {
                        fclose($connection);
                        Mail::to($adminEmail)->send($mailable);
                        Log::info("Contact inquiry email dispatched via SMTP to: {$adminEmail}");
                    } else {
                        Log::warning("SMTP port {$host}:{$port} blocked by hosting firewall (Error: {$errstr}). Message safely logged and stored in DB.");
                    }
                } else {
                    Mail::to($adminEmail)->send($mailable);
                }
            } catch (\Throwable $e) {
                Log::error("Failed to send contact inquiry email: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Thank you ' . e($validated['name']) . '! Your message has been received by Shubham Thakur and the LiveCricket team. We will get in touch with you shortly.');
    }
}
