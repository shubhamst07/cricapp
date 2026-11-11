@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-1">
                ⚖️ Legal Terms
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">Terms of Service</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Last updated: {{ date('F d, Y') }} • Applicable to all visitors & users</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('privacy') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-200 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/15 text-slate-800 dark:text-white font-bold text-xs transition">
                <span>🔒 Privacy Policy</span>
            </a>
            <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-md shadow-indigo-600/30 hover:from-indigo-500 transition">
                <span>📬 Contact Founder</span>
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="py-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
    <!-- Header Hero Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900/40 via-slate-900 to-[#0b1120] border border-white/10 p-6 sm:p-10 shadow-2xl text-white">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                📜 Agreement to Terms
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-white">Platform Usage & Service Agreement</h1>
            <p class="text-slate-300 text-sm leading-relaxed">
                By accessing or using <strong>LiveCricket</strong> (<a href="{{ url('/') }}" class="text-indigo-400 underline">{{ url('/') }}</a>), you agree to be bound by these Terms of Service. If you do not agree to all conditions outlined herein, please discontinue using the platform immediately.
            </p>
        </div>
    </div>

    <!-- Main Terms Container -->
    <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-10 shadow-xl space-y-8 text-slate-700 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-200">
        
        <!-- Section 1 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">1.</span> About the Service
            </h3>
            <p>
                LiveCricket is a comprehensive sports technology platform providing live ball-by-ball cricket scoring, grassroots tournament administration, venue and cricket turf ground booking, and verified umpire discovery across India and worldwide.
            </p>
        </section>

        <!-- Section 2 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">2.</span> User Conduct & Fair Play
            </h3>
            <p>
                When utilizing LiveCricket's community features, scoring consoles, or booking systems, you agree not to:
            </p>
            <ul class="list-disc pl-5 space-y-1.5 text-xs sm:text-sm">
                <li>Submit false, defamatory, fraudulent, or malicious match statistics or player identities.</li>
                <li>Attempt to bypass security features, reverse-engineer proprietary scoring engines, or conduct automated web scraping.</li>
                <li>Post abusive, discriminatory, obscene, or threatening language in commentary or social feeds.</li>
                <li>Misuse tournament management systems to manipulate grassroots cricket standings.</li>
            </ul>
        </section>

        <!-- Section 3 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">3.</span> Advertisements & Sponsorships
            </h3>
            <p class="text-xs sm:text-sm">
                LiveCricket displays non-obtrusive programmatic advertisements (including via Google AdSense) and direct sponsorship integrations for cricket equipment, local turf grounds, and sports gear. We do not endorse any third-party product or service promoted through advertising banners unless explicitly stated. Users clicking on third-party advertisements do so at their own discretion.
            </p>
        </section>

        <!-- Section 4 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">4.</span> Ground Bookings & Umpire Reservations
            </h3>
            <p class="text-xs sm:text-sm">
                LiveCricket facilitates communication and booking inquiries between players, cricket grounds, and verified umpires. Venue availability, pitch conditions, equipment safety, and fair adjudication remain the direct responsibility of the respective venue managers and independent umpires.
            </p>
        </section>

        <!-- Section 5 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">5.</span> Intellectual Property & Fair Use
            </h3>
            <p class="text-xs sm:text-sm">
                The LiveCricket branding, UI design, custom graphics, and software code are proprietary to Shubham Thakur. Cricket team names, logos, tournament insignias, and live scores are used under fair-use principles for sporting commentary, reporting, and fan appreciation.
            </p>
        </section>

        <!-- Section 6 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">6.</span> Disclaimer of Warranties & Limitation of Liability
            </h3>
            <p class="text-xs sm:text-sm">
                LiveCricket is provided on an "AS IS" and "AS AVAILABLE" basis. While we strive for 100% accuracy and millisecond-level live score synchronization, we do not warrant that score feeds will be uninterrupted or error-free during severe network disruptions. LiveCricket shall not be held liable for any indirect or consequential damages arising from service interruption.
            </p>
        </section>

        <!-- Section 7 -->
        <section class="space-y-3 pt-4 border-t border-slate-200 dark:border-white/10">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">7.</span> Governing Law & Inquiries
            </h3>
            <p class="text-xs sm:text-sm">
                These terms shall be governed by and interpreted according to the laws of India. For any disputes or clarifications:
            </p>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 text-xs sm:text-sm space-y-1">
                <p><strong>Founder:</strong> Shubham Thakur</p>
                <p><strong>Email:</strong> <a href="mailto:shubhamthakurst007@gmail.com" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">shubhamthakurst007@gmail.com</a></p>
                <p><strong>Phone:</strong> <a href="tel:+919876543210" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">+91 98765 43210</a></p>
            </div>
        </section>

    </div>
</div>
@endsection
