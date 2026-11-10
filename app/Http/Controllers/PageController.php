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

        // 1. Permanently store in database so no message is ever lost
        try {
            ContactInquiry::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'inquiry_type' => $validated['inquiry_type'],
                'message' => $validated['message'],
                'ip_address' => $request->ip(),
                'is_read' => false,
            ]);
            Log::info('Contact inquiry saved to database successfully.');
        } catch (\Throwable $e) {
            Log::warning('Could not save contact inquiry to DB: ' . $e->getMessage());
        }

        // 2. Deliver email notification to Shubham Thakur
        $adminEmail = config('mail.admin_address', env('ADMIN_EMAIL', 'shubhamthakurst07@gmail.com'));
        $mailSent = false;

        // A. Resend HTTPS API (Port 443 - Never blocked on Render Free Tier)
        $resendKey = env('RESEND_API_KEY');
        if ($resendKey) {
            try {
                $response = Http::timeout(4)->withToken($resendKey)->post('https://api.resend.com/emails', [
                    'from' => env('MAIL_FROM_NAME', 'LiveCricket') . ' <' . (env('RESEND_FROM_EMAIL', 'onboarding@resend.dev')) . '>',
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

        // B. Brevo HTTPS API (Port 443 - Never blocked on Render Free Tier)
        $brevoKey = env('BREVO_API_KEY');
        if (!$mailSent && $brevoKey) {
            try {
                $response = Http::timeout(4)->withHeaders([
                    'api-key' => $brevoKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => env('MAIL_FROM_NAME', 'LiveCricket'),
                        'email' => env('MAIL_FROM_ADDRESS', 'shubhamthakurst07@gmail.com'),
                    ],
                    'to' => [
                        ['email' => $adminEmail, 'name' => 'Shubham Thakur'],
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
                    Log::info("Contact message delivered via Brevo HTTPS API to {$adminEmail}");
                }
            } catch (\Throwable $e) {
                Log::warning("Brevo API delivery error: " . $e->getMessage());
            }
        }

        // C. Standard SMTP (with 1.5s pre-flight socket test to PREVENT 504 TIMEOUTS)
        if (!$mailSent && config('mail.default') === 'smtp') {
            $host = config('mail.mailers.smtp.host', env('MAIL_HOST', 'smtp.gmail.com'));
            $port = (int) config('mail.mailers.smtp.port', env('MAIL_PORT', 587));

            // Pre-flight check: Can this host actually open TCP connection to the SMTP port in 1.5 seconds?
            $canConnect = false;
            if ($host && $port) {
                $fp = @fsockopen($host, $port, $errno, $errstr, 1.5);
                if (is_resource($fp)) {
                    fclose($fp);
                    $canConnect = true;
                }
            }

            if ($canConnect) {
                try {
                    config(['mail.mailers.smtp.timeout' => 4]);
                    Mail::to($adminEmail)->send(new ContactInquiryMail($validated));
                    Log::info("Contact inquiry email dispatched via SMTP to: {$adminEmail}");
                } catch (\Throwable $e) {
                    Log::warning("SMTP delivery error: " . $e->getMessage());
                }
            } else {
                Log::warning("SMTP port {$port} on {$host} is blocked by cloud provider (Render Free Tier blocks outbound SMTP ports 25, 465, and 587). Skipping synchronous SMTP to prevent 504 Gateway Timeout. Stored in DB instead.");
            }
        }

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
}
