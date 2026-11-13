@php
    $isAdmin = auth()->check() && (
        str_contains(strtolower(auth()->user()->email), 'shubham') ||
        in_array(auth()->user()->email, ['shubhamthakurst0007@gmail.com', 'shubhamthakurst007@gmail.com', 'shubhamthakurst07@gmail.com', env('ADMIN_EMAIL')]) ||
        str_starts_with(auth()->user()->email, 'admin@') ||
        (auth()->user()->is_admin ?? false)
    );
@endphp

@extends('layouts.app')

@section('content')
    <div id="match-detail-root"
         data-match='@json($match)'
         data-scorecard='@json($scorecard)'
         data-commentary='@json($commentary ?? [])'
         data-match-id='{{ $match["id"] ?? "" }}'
         data-is-admin='{{ $isAdmin ? "true" : "false" }}'>
        {{-- Loading Skeleton while React mounts --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-pulse space-y-6">
            <div class="h-8 w-40 bg-gray-200 rounded-lg"></div>
            <div class="h-44 bg-gray-200 rounded-2xl"></div>
            <div class="h-32 bg-gray-200 rounded-2xl"></div>
            <div class="h-64 bg-gray-200 rounded-2xl"></div>
        </div>
    </div>
@endsection
