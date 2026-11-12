@extends('layouts.app')

@section('content')
    <div id="live-scores-root"
         data-live='@json($live)'
         data-scheduled='@json($scheduled)'
         data-completed='@json($completed)'
         data-cache-ttl='{{ ($cacheTtl["current_matches"] ?? 900) / 60 }}'
         data-initial-tab='{{ $tab ?? "live" }}'
         data-initial-section='{{ $section ?? "international" }}'>
        {{-- Loading Skeleton while React mounts --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 animate-pulse">
            <div class="h-36 bg-[#111827]/80 border border-white/5 rounded-3xl mb-6"></div>
            <div class="h-16 bg-[#111827]/80 border border-white/5 rounded-2xl mb-6"></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="h-56 bg-[#131b2e]/80 border border-white/5 rounded-3xl"></div>
                <div class="h-56 bg-[#131b2e]/80 border border-white/5 rounded-3xl"></div>
                <div class="h-56 bg-[#131b2e]/80 border border-white/5 rounded-3xl"></div>
            </div>
        </div>
    </div>
@endsection
