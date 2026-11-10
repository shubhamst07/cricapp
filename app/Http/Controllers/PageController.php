<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        return back()->with('success', 'Thank you ' . e($validated['name']) . '! Your message has been received by Shubham Thakur and the LiveCricket team. We will get in touch with you shortly.');
    }
}
