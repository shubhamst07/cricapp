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
        } catch (\Throwable $e) {
            Log::warning('Could not save contact message to DB: ' . $e->getMessage());
        }

        // 2. Send email notification to Shubham Thakur
        $adminEmail = config('mail.admin_address', env('ADMIN_EMAIL', 'shubhamthakurst07@gmail.com'));
        $mailSent = false;

        // A. Resend HTTPS API (Port 443 - Never blocked on Render Free Tier)
        if (env('RESEND_API_KEY')) {
            try {
                $response = Http::timeout(5)->withToken(env('RESEND_API_KEY'))->post('https://api.resend.com/emails', [
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
                    Log::warning("Resend API response failed: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("Resend API delivery error: " . $e->getMessage());
            }
        }

        // B. Brevo HTTPS API (Port 443 - Never blocked on Render Free Tier)
        if (!$mailSent && env('BREVO_API_KEY')) {
            try {
                $response = Http::timeout(5)->withHeaders([
                    'api-key' => env('BREVO_API_KEY'),
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

        // C. Standard SMTP (with strict 4s timeout guard to prevent 504 Gateway Timeouts)
        if (!$mailSent) {
            try {
                config(['mail.mailers.smtp.timeout' => 4]);
                Mail::to($adminEmail)->send(new ContactInquiryMail($validated));
                Log::info("Contact inquiry email dispatched via SMTP to: {$adminEmail}");
            } catch (\Throwable $e) {
                Log::warning("SMTP delivery failed (expected on Render Free tier due to port 587/465 restriction): " . $e->getMessage());
            }
        }

        return back()->with('success', 'Thank you ' . e($validated['name']) . '! Your message has been received by Shubham Thakur and the LiveCricket team. We will get in touch with you shortly.');
    }
}
