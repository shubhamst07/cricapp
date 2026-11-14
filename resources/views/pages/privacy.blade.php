@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-1">
                🔒 Trust & Transparency
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">Privacy Policy</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Last updated: {{ date('F d, Y') }} • Effective globally</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-md shadow-indigo-600/30 hover:from-indigo-500 transition">
                <span>📬 Contact Founder</span>
            </a>
            <a href="{{ route('terms') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-200 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/15 text-slate-800 dark:text-white font-bold text-xs transition">
                <span>📜 Terms of Service</span>
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="py-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
    <!-- Introduction Card -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900/40 via-slate-900 to-[#0b1120] border border-white/10 p-6 sm:p-10 shadow-2xl text-white">
        <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                🛡️ LiveCricket Privacy Commitment
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-white">Your Privacy Matters to Us</h1>
            <p class="text-slate-300 text-sm leading-relaxed">
                Welcome to <strong>LiveCricket</strong> (<a href="{{ url('/') }}" class="text-indigo-400 underline">{{ url('/') }}</a>), founded by <strong>Shubham Thakur</strong>. We are committed to protecting your personal information and your right to privacy. If you have any questions or concerns about this policy or our practices, please contact us at <a href="mailto:shubhamthakurst007@gmail.com" class="text-indigo-400 underline">shubhamthakurst007@gmail.com</a>.
            </p>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-10 shadow-xl space-y-8 text-slate-700 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-200">
        
        <!-- Section 1 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">1.</span> Information We Collect
            </h3>
            <p>
                When you visit or interact with LiveCricket, we may collect information about you in a few ways:
            </p>
            <ul class="list-disc pl-5 space-y-2 text-xs sm:text-sm">
                <li><strong>Personal Information You Provide:</strong> Name, email address, phone number, and inquiry details provided voluntarily via our Contact or Booking forms.</li>
                <li><strong>Account Data:</strong> If you register or authenticate, we securely store your email, username, encrypted password, and cricket profile preferences.</li>
                <li><strong>Log & Usage Data:</strong> Like most web servers, we automatically log your IP address, browser type, operating system, referring URLs, pages viewed, and timestamps to diagnose technical issues and ensure service reliability.</li>
            </ul>
        </section>

        <!-- Section 2: Google AdSense & Cookies (Crucial for AdSense approval) -->
        <section class="space-y-3 p-5 rounded-2xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/20">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-amber-500">2.</span> Google AdSense, Advertising & Cookies Policy
            </h3>
            <p class="text-xs sm:text-sm">
                Third-party vendors, including <strong>Google</strong>, use cookies to serve ads based on a user's prior visits to our website or other websites on the internet.
            </p>
            <ul class="list-disc pl-5 space-y-2 text-xs sm:text-sm">
                <li>
                    <strong>Google's Use of Advertising Cookies:</strong> Google's use of advertising cookies enables it and its partners to serve ads to our users based on their visit to our sites and/or other sites on the Internet.
                </li>
                <li>
                    <strong>Opting Out of Personalized Advertising:</strong> Users may opt out of personalized advertising by visiting <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 font-bold underline">Google Ads Settings</a>. Alternatively, you can opt out of third-party vendor cookies for personalized advertising by visiting <a href="https://www.aboutads.info" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 font-bold underline">aboutads.info</a> or the Network Advertising Initiative at <a href="https://optout.networkadvertising.org/" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 font-bold underline">optout.networkadvertising.org</a>.
                </li>
                <li>
                    <strong>Cookies & Web Beacons:</strong> LiveCricket uses first-party session cookies to keep you logged in and preserve dark/light theme preferences. Third-party ad networks may place and read cookies on your browser or use web beacons to collect information as a result of ad serving.
                </li>
            </ul>
        </section>

        <!-- Section 3 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">3.</span> How We Use Your Information
            </h3>
            <p>We use the collected information for purposes that include:</p>
            <ul class="list-disc pl-5 space-y-1.5 text-xs sm:text-sm">
                <li>Delivering real-time international and grassroots cricket scorecards.</li>
                <li>Responding promptly to support, tournament inquiry, and sponsorship requests.</li>
                <li>Managing turf ground venue bookings and certified cricket umpire appointments.</li>
                <li>Monitoring website performance, preventing abuse, spam, and DDoS threats.</li>
                <li>Displaying relevant non-intrusive sponsorships and contextual advertisements.</li>
            </ul>
        </section>

        <!-- Section 4 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">4.</span> Cricket Data & Third-Party Content
            </h3>
            <p class="text-xs sm:text-sm">
                LiveCricket aggregates public cricket scores, fixtures, player statistics, and commentary feeds for informational and sporting enthusiasm purposes. Live match data is synchronized through publicly accessible live sports engines and verified community scorekeepers. All trademarks, player names, team crests, and tournament titles belong to their respective statutory cricket boards and rights holders.
            </p>
        </section>

        <!-- Section 5 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">5.</span> Data Security & Retention
            </h3>
            <p class="text-xs sm:text-sm">
                We implement industry-standard SSL/TLS encryption, secure database configurations, and strict access controls. Passwords are hash-encrypted using bcrypt. We never sell, rent, or trade your personal information with external data brokers.
            </p>
        </section>

        <!-- Section 6 -->
        <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">6.</span> Your Data Protection Rights
            </h3>
            <p class="text-xs sm:text-sm">
                Depending on your location (including under the Indian Digital Personal Data Protection Act, GDPR, or CCPA), you may have the right to access, rectify, or request deletion of personal information stored about you. To exercise these rights, please reach out directly to our grievance officer.
            </p>
        </section>

        <!-- Section 7 -->
        <section class="space-y-3 pt-4 border-t border-slate-200 dark:border-white/10">
            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-indigo-600 dark:text-indigo-400">7.</span> Grievance Officer & Contact Details
            </h3>
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 text-xs sm:text-sm space-y-1">
                <p><strong>Platform:</strong> LiveCricket App</p>
                <p><strong>Data Officer & Founder:</strong> Shubham Thakur</p>
                <p><strong>Email:</strong> <a href="mailto:shubhamthakurst007@gmail.com" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">shubhamthakurst007@gmail.com</a></p>
                <p><strong>Phone:</strong> <a href="tel:+918219795479" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">+91 82197 95479</a></p>
                <p><strong>Headquarters:</strong> Delhi NCR / India</p>
            </div>
        </section>

    </div>
</div>
@endsection
