@extends('layouts.app')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full bg-[#131b2e] border border-white/10 rounded-3xl p-8 text-center shadow-2xl space-y-6">
        <div class="w-20 h-20 rounded-2xl bg-amber-500/10 text-amber-400 mx-auto flex items-center justify-center text-4xl shadow-inner animate-pulse">
            📡
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                <span>NO INTERNET CONNECTION</span>
            </span>
            <h2 class="text-2xl font-black text-white tracking-tight">You're Offline</h2>
            <p class="text-xs text-slate-400 leading-relaxed">
                Live cricket scores and updates require an active internet connection. Please check your Wi-Fi or mobile data.
            </p>
        </div>

        <div class="pt-2">
            <button onclick="window.location.reload()"
                    class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 active:scale-95 text-white font-black text-sm uppercase tracking-wider shadow-lg shadow-red-600/30 transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin-reverse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Retry Connection</span>
            </button>
        </div>

        <p class="text-[11px] text-slate-500">
            LiveCricket PWA will automatically reconnect once your signal returns.
        </p>
    </div>
</div>
@endsection
