@extends('layouts.app')

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-1">
                📬 24/7 Match & Booking Support
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">Contact Us</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Reach out to Shubham Thakur and the LiveCricket team for any assistance or inquiries</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="https://wa.me/919876543210?text=Hi%20Shubham,%20I%20have%20an%20inquiry%20about%20LiveCricket" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition">
                <span>💬 Quick WhatsApp</span>
            </a>
            <a href="tel:+919876543210" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-800 dark:text-white font-bold text-xs border border-slate-200 dark:border-white/10 transition">
                <span>📞 Call Now</span>
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

    @if(session('success'))
        <div class="p-4 sm:p-5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 font-bold text-sm flex items-center gap-3 shadow-lg">
            <span class="text-xl">✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contact Information Cards -->
        <div class="lg:col-span-1 space-y-5">
            <!-- Founder Quick Contact -->
            <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-sm transition-colors duration-200">
                <span class="text-[11px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">FOUNDER & LEAD</span>
                <h3 class="text-xl font-black text-slate-900 dark:text-white mt-1">Shubham Thakur</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Direct contact for platform support, tournament setups, and partnerships.</p>

                <div class="mt-5 space-y-3 pt-4 border-t border-slate-100 dark:border-white/5 text-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            📞
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px] uppercase font-bold">Phone Number</span>
                            <a href="tel:+919876543210" class="font-extrabold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                +91 98765 43210
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            💬
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px] uppercase font-bold">WhatsApp Direct</span>
                            <a href="https://wa.me/919876543210?text=Hi%20Shubham,%20I%20have%20an%20inquiry%20regarding%20LiveCricket" target="_blank" rel="noopener noreferrer" class="font-extrabold text-emerald-600 dark:text-emerald-400 hover:underline">
                                Chat on WhatsApp ⚡
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            ✉️
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px] uppercase font-bold">Official Email</span>
                            <a href="mailto:shubhamthakurst007@gmail.com" class="font-extrabold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 break-all">
                                shubhamthakurst007@gmail.com
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            📍
                        </div>
                        <div>
                            <span class="block text-slate-400 text-[10px] uppercase font-bold">Location</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">
                                Shimla, Himachal Pradesh & Mumbai, India
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Hours Card -->
            <div class="bg-gradient-to-br from-indigo-900/40 via-slate-900 to-[#0b1120] border border-white/10 rounded-3xl p-6 text-white shadow-md">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                    <h4 class="font-black text-sm text-white">Live Match Assistance</h4>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed">
                    Need help setting up live scoring for your weekend tournament or ground booking confirmation? We are available all 7 days during match hours.
                </p>
            </div>
        </div>

        <!-- Contact Query Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-10 shadow-xl transition-colors duration-200">
                <div class="mb-6">
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">Send Us A Message</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Fill out the form below and we will get back to you promptly.</p>
                </div>

                <form method="POST" action="{{ route('contact.submit') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Your Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   required
                                   placeholder="e.g. Shubham Sharma"
                                   value="{{ old('name', auth()->user()?->name ?? '') }}"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                            @error('name')
                                <span class="text-rose-500 text-[11px] font-bold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Phone Number
                            </label>
                            <input type="text"
                                   name="phone"
                                   placeholder="e.g. +91 98765 43210"
                                   value="{{ old('phone') }}"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                            @error('phone')
                                <span class="text-rose-500 text-[11px] font-bold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Email Address <span class="text-rose-500">*</span>
                            </label>
                            <input type="email"
                                   name="email"
                                   required
                                   placeholder="e.g. you@example.com"
                                   value="{{ old('email', auth()->user()?->email ?? '') }}"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                            @error('email')
                                <span class="text-rose-500 text-[11px] font-bold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                Inquiry Topic <span class="text-rose-500">*</span>
                            </label>
                            <select name="inquiry_type"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-[#131b2e] text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                                <option value="Tournament Live Scoring">Tournament Live Scoring 🏏</option>
                                <option value="Ground & Turf Booking">Ground & Turf Booking 🏟️</option>
                                <option value="Certified Umpire Registration/Hiring">Certified Umpire Registration/Hiring ⚖️</option>
                                <option value="Partnership & Sponsorship">Partnership & Sponsorship 🤝</option>
                                <option value="Technical Support or Bug">Technical Support or Bug ⚙️</option>
                                <option value="General Feedback">General Feedback 💡</option>
                            </select>
                            @error('inquiry_type')
                                <span class="text-rose-500 text-[11px] font-bold mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Your Message <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="message"
                                  rows="5"
                                  required
                                  placeholder="Describe how we can help you with matches, venues, or live scoring..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none transition leading-relaxed">{{ old('message') }}</textarea>
                        @error('message')
                            <span class="text-rose-500 text-[11px] font-bold mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-black text-xs shadow-lg shadow-indigo-600/30 transition transform active:scale-95 cursor-pointer">
                            <span>Send Message 🚀</span>
                        </button>
                        <span class="text-[11px] text-slate-400">Response typically within a few hours</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
