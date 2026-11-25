@php
    $isAdmin = auth()->check() && (
        str_contains(strtolower(auth()->user()->email), 'shubham') ||
        in_array(auth()->user()->email, ['shubhamthakurst0007@gmail.com', 'shubhamthakurst007@gmail.com', 'shubhamthakurst07@gmail.com', env('ADMIN_EMAIL')]) ||
        str_starts_with(auth()->user()->email, 'admin@') ||
        (auth()->user()->is_admin ?? false)
    );

    $scoreSnippet = '';
    $scoreParts = [];
    if (!empty($match['score']) && is_array($match['score'])) {
        foreach ($match['score'] as $s) {
            $t = $s['batting_team'] ?? $s['inning'] ?? '';
            $shortT = !empty($t) ? (strlen($t) > 5 ? preg_replace('/(\b\w)\w*\s*/', '$1', $t) : $t) : '';
            $rawSc = $s['display_score'] ?? (($s['r'] ?? $s['runs'] ?? 0) . '/' . ($s['w'] ?? $s['wickets'] ?? 0));
            $sc = \App\Services\CricketDataService::normalizeScoreString($rawSc);
            if ($sc && $sc !== '0/0') {
                $rawOv = \App\Services\CricketDataService::normalizeCricketOvers($s['overs'] ?? $s['o'] ?? null);
                $ov = !empty($rawOv) ? ' (' . $rawOv . ' ov)' : '';
                $scoreParts[] = trim(($shortT ? "$shortT " : "") . $sc . $ov);
            }
        }
    }

    if (empty($scoreParts) && !empty($scorecard['data']['innings'])) {
        foreach ($scorecard['data']['innings'] as $inn) {
            $t = $inn['short_name'] ?? $inn['team'] ?? '';
            $sc = \App\Services\CricketDataService::normalizeScoreString($inn['score'] ?? '');
            if ($sc && $sc !== '0/0') {
                $rawOv = \App\Services\CricketDataService::normalizeCricketOvers($inn['overs'] ?? null);
                $ov = !empty($rawOv) ? ' (' . $rawOv . ' ov)' : '';
                $scoreParts[] = trim(($t ? "$t " : "") . $sc . $ov);
            }
        }
    }

    // Place current / latest innings score FIRST so it is immediately visible in the browser tab
    if (count($scoreParts) > 1) {
        $currentInning = array_pop($scoreParts);
        array_unshift($scoreParts, $currentInning);
    }

    if (!empty($scoreParts)) {
        $scoreSnippet = implode(' & ', $scoreParts);
    }

    $teamsName = $match['name'] ?? trim(($match['team1_name'] ?? '') . ' vs ' . ($match['team2_name'] ?? ''));
    if ($scoreSnippet) {
        $pageTitle = "{$scoreSnippet} - {$teamsName} | CricApp";
    } elseif ($teamsName) {
        $pageTitle = "{$teamsName} Live Score | CricApp";
    } else {
        $pageTitle = "Live Cricket Score | CricApp";
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
        {{-- Animated Bat & Ball Loader while React mounts --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <x-cricket-loader inline="true" label="Loading live match scorecard & ball-by-ball commentary..." />
        </div>
    </div>
@endsection
