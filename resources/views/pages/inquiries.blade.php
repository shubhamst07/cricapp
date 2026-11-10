@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-1">
                📬 Admin Inbox
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">Contact Form Inquiries</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">All messages sent from the Contact Us page are safely logged here</p>
        </div>

        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-800 dark:text-white font-bold text-xs border border-slate-200 dark:border-white/10 transition">
            <span>View Public Contact Page ↗</span>
        </a>
    </div>
@endsection

@section('content')
<div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    @if($inquiries->isEmpty())
        <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-12 text-center max-w-lg mx-auto shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-3xl mx-auto mb-4">
                📬
            </div>
            <h3 class="font-black text-lg text-slate-900 dark:text-white">No Inquiries Yet</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">When users send messages from the Contact Us form, they will appear here instantly.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4">
            @foreach($inquiries as $item)
                <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-2xl p-5 shadow-xs transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-600 flex items-center justify-center text-white font-black text-sm uppercase shrink-0">
                                {{ strtoupper(substr($item->name, 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="font-black text-sm text-slate-900 dark:text-white">{{ $item->name }}</h4>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <a href="mailto:{{ $item->email }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">{{ $item->email }}</a>
                                    @if($item->phone)
                                        <span>•</span>
                                        <a href="tel:{{ $item->phone }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-bold">{{ $item->phone }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                                {{ $item->inquiry_type }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-mono">
                                {{ $item->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    <div class="pt-3">
                        <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-white/5 p-3.5 rounded-xl border border-slate-100 dark:border-white/5 whitespace-pre-line">
                            {{ $item->message }}
                        </div>

                        <div class="mt-3 flex items-center justify-end gap-2 text-xs font-bold">
                            <a href="mailto:{{ $item->email }}?subject={{ urlencode('Re: LiveCricket Inquiry - ' . $item->inquiry_type) }}" 
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">
                                <span>Reply via Email ✉️</span>
                            </a>
                            @if($item->phone)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $item->phone) }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition">
                                    <span>WhatsApp 💬</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $inquiries->links() }}
        </div>
    @endif
</div>
@endsection
