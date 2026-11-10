<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiryMail;
use Illuminate\Http\Request;
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

        // Send email notification to Shubham Thakur
        $adminEmail = config('mail.admin_address', env('ADMIN_EMAIL', 'shubhamthakurst07@gmail.com'));
        try {
            Mail::to($adminEmail)->send(new ContactInquiryMail($validated));
            Log::info("Contact inquiry email dispatched to: {$adminEmail}");
        } catch (\Throwable $e) {
            Log::error("Failed to send contact inquiry email to {$adminEmail}: " . $e->getMessage());
        }

        return back()->with('success', 'Thank you ' . e($validated['name']) . '! Your message has been received by Shubham Thakur and the LiveCricket team. We will get in touch with you shortly.');
    }
}
