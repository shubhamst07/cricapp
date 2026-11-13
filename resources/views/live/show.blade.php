@php
    $isAdmin = auth()->check() && (
        str_contains(strtolower(auth()->user()->email), 'shubham') ||
        in_array(auth()->user()->email, ['shubhamthakurst0007@gmail.com', 'shubhamthakurst007@gmail.com', 'shubhamthakurst07@gmail.com', env('ADMIN_EMAIL')]) ||
        str_starts_with(auth()->user()->email, 'admin@') ||
        (auth()->user()->is_admin ?? false)
    );

    $scoreSnippet = '';
    if (!empty($match['score']) && is_array($match['score'])) {
        $parts = [];
        foreach ($match['score'] as $s) {
            $t = $s['batting_team'] ?? $s['inning'] ?? '';
            $shortT = !empty($t) ? (strlen($t) > 5 ? preg_replace('/(\b\w)\w*\s*/', '$1', $t) : $t) : '';
            $sc = $s['display_score'] ?? (($s['r'] ?? $s['runs'] ?? 0) . '/' . ($s['w'] ?? $s['wickets'] ?? 0));
            if ($sc && $sc !== '0/0') {
                $ov = !empty($s['overs'] ?? $s['o']) ? ' (' . ($s['overs'] ?? $s['o']) . ' ov)' : '';
                $parts[] = trim(($shortT ? "$shortT " : "") . $sc . $ov);
            }
        }
        if (!empty($parts)) {
            $scoreSnippet = implode(' & ', $parts);
        }
    }

    if (empty($scoreSnippet) && !empty($scorecard['data']['innings'])) {
        $parts = [];
        foreach ($scorecard['data']['innings'] as $inn) {
            $t = $inn['short_name'] ?? $inn['team'] ?? '';
            $sc = $inn['score'] ?? '';
            if ($sc && $sc !== '0/0') {
                $ov = !empty($inn['overs']) ? ' (' . $inn['overs'] . ' ov)' : '';
                $parts[] = trim(($t ? "$t " : "") . $sc . $ov);
            }
        }
        if (!empty($parts)) {
            $scoreSnippet = implode(' & ', $parts);
        }
    }

    $teamsName = $match['name'] ?? trim(($match['team1_name'] ?? '') . ' vs ' . ($match['team2_name'] ?? ''));
    if ($scoreSnippet) {
        $pageTitle = "{$scoreSnippet} - {$teamsName} | LiveCricket";
    } elseif ($teamsName) {
        $pageTitle = "{$teamsName} Live Score | LiveCricket";
    } else {
        $pageTitle = "Live Cricket Score | LiveCricket";
    }
@endphp

@extends('layouts.app')

@section('title', $pageTitle)

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
