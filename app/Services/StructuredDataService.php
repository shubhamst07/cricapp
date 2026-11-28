<?php

namespace App\Services;

use App\Models\CricketMatch;
use Carbon\Carbon;
use Illuminate\Support\Str;

class StructuredDataService
{
    /**
     * Build a Google-compliant SportsEvent JSON-LD node for an international/API live match page.
     */
    public function buildSportsEventForLiveMatch(array $match, string $title, string $description, string $url): array
    {
        $teams = $this->resolveTeams(
            is_array($match['teams'] ?? null) ? $match['teams'] : [],
            (string) ($match['team1_name'] ?? ''),
            (string) ($match['team2_name'] ?? ''),
            $title
        );

        return $this->buildSportsEvent([
            'name' => $title ?: 'Live Cricket Match',
            'description' => $description,
            'url' => $url,
            'start_raw' => $match['dateTimeGMT'] ?? $match['date'] ?? null,
            'fallback_date' => null,
            'match_type' => (string) ($match['matchType'] ?? $match['match_desc'] ?? ''),
            'status' => (string) ($match['status'] ?? ''),
            'status_text' => (string) ($match['status_text'] ?? $match['status'] ?? ''),
            'venue' => (string) ($match['venue'] ?? ''),
            'venue_fallback' => 'International Cricket Stadium',
            'locality_fallback' => 'International Cricket Venue',
            'team1' => $teams[0],
            'team2' => $teams[1],
            'images' => [url('/icons/icon-512x512.png')],
        ]);
    }

    /**
     * Build a Google-compliant SportsEvent JSON-LD node for a local CricketMatch page.
     */
    public function buildSportsEventForLocalMatch(CricketMatch $match, string $title, string $description, string $url): array
    {
        $ground = null;
        try {
            if (!empty($match->ground_id)) {
                $ground = $match->ground;
            }
        } catch (\Throwable $e) {
            $ground = null;
        }

        $venueParts = array_filter([
            trim((string) ($match->venue ?? '')),
            trim((string) ($ground?->name ?? '')),
            trim((string) ($ground?->city ?? '')),
        ]);
        $rawVenue = !empty($venueParts) ? implode(', ', array_unique($venueParts)) : '';

        $images = [];
        $groundImg = trim((string) ($ground?->image_url ?? ''));
        if ($groundImg !== '') {
            if (Str::startsWith($groundImg, 'http')) {
                $images[] = $groundImg;
            } elseif (Str::startsWith($groundImg, '/') && is_file(public_path(ltrim($groundImg, '/')))) {
                $images[] = url($groundImg);
            }
        }
        $images[] = url('/icons/icon-512x512.png');

        $teams = $this->resolveTeams(
            [],
            (string) ($match->team1_name ?? ''),
            (string) ($match->team2_name ?? ''),
            $title
        );

        return $this->buildSportsEvent([
            'name' => $title ?: 'Cricket Match',
            'description' => $description,
            'url' => $url,
            'start_raw' => $match->match_date,
            'fallback_date' => $match->created_at,
            'match_type' => (string) ($match->match_type ?? ''),
            'status' => (string) ($match->status ?? ''),
            'status_text' => (string) ($match->match_result ?? $match->status ?? ''),
            'venue' => $rawVenue,
            'venue_fallback' => 'Cricket Ground, India',
            'locality_fallback' => trim((string) ($ground?->city ?? '')) ?: 'India',
            'address_street' => trim((string) ($ground?->address ?? '')),
            'team1' => $teams[0],
            'team2' => $teams[1],
            'images' => array_values(array_unique($images)),
        ]);
    }

    /**
     * Build a BreadcrumbList schema node.
     */
    public function buildBreadcrumbList(array $items): array
    {
        $elements = [];
        foreach (array_values($items) as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) ($item['name'] ?? ''),
                'item' => (string) ($item['url'] ?? ''),
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    private function buildSportsEvent(array $input): array
    {
        $start = $this->parseEventDate($input['start_raw'] ?? null, $input['fallback_date'] ?? null);
        $end = $this->computeEndDate($start, (string) ($input['match_type'] ?? ''));
        $startIso = $start->toAtomString();
        $endIso = $end->toAtomString();

        $location = $this->buildLocation(
            (string) ($input['venue'] ?? ''),
            (string) ($input['venue_fallback'] ?? 'Cricket Stadium'),
            (string) ($input['locality_fallback'] ?? 'Cricket Venue'),
            (string) ($input['address_street'] ?? '')
        );

        $eventStatus = $this->resolveEventStatus(
            (string) ($input['status'] ?? ''),
            (string) ($input['status_text'] ?? '')
        );

        $team1 = trim((string) ($input['team1'] ?? '')) ?: 'Home Cricket Team';
        $team2 = trim((string) ($input['team2'] ?? ''));

        $performers = [
            ['@type' => 'SportsTeam', 'name' => $team1],
        ];
        if ($team2 !== '' && strcasecmp($team2, $team1) !== 0) {
            $performers[] = ['@type' => 'SportsTeam', 'name' => $team2];
        }

        $images = array_values(array_filter(
            (array) ($input['images'] ?? []),
            static fn ($img) => is_string($img) && trim($img) !== ''
        ));
        if (empty($images)) {
            $images = [url('/icons/icon-512x512.png')];
        }

        $url = (string) ($input['url'] ?? url('/'));

        $node = [
            '@type' => 'SportsEvent',
            'name' => (string) ($input['name'] ?? 'Cricket Match'),
            'sport' => 'Cricket',
            'description' => (string) ($input['description'] ?? ''),
            'url' => $url,
            'image' => $images,
            'startDate' => $startIso,
            'endDate' => $endIso,
            'eventStatus' => $eventStatus,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $location,
            'performer' => $performers,
            'homeTeam' => $performers[0],
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'price' => '0',
                'priceCurrency' => 'INR',
                'availability' => 'https://schema.org/InStock',
                'validFrom' => $startIso,
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'CricApp.in',
                'url' => url('/'),
            ],
        ];

        if (isset($performers[1])) {
            $node['awayTeam'] = $performers[1];
        }

        return $node;
    }

    private function parseEventDate(mixed $primary, mixed $fallback): Carbon
    {
        foreach ([$primary, $fallback] as $candidate) {
            if ($candidate instanceof \DateTimeInterface) {
                return Carbon::instance($candidate);
            }
            if (is_string($candidate) && trim($candidate) !== '') {
                try {
                    return Carbon::parse(trim($candidate));
                } catch (\Throwable $e) {
                    // Try next fallback
                }
            }
        }

        return now('Asia/Kolkata')->startOfDay();
    }

    private function computeEndDate(Carbon $start, string $matchType): Carbon
    {
        $typeLower = strtolower($matchType);
        if (preg_match('/test|first[\s\-]*class|\bfc\b|ranji|4[\s\-]*day|5[\s\-]*day/', $typeLower)) {
            return $start->copy()->addDays(4);
        }
        if (preg_match('/odi|one[\s\-]*day|list[\s\-]*a|50/', $typeLower)) {
            return $start->copy()->addHours(9);
        }

        return $start->copy()->addHours(4);
    }

    private function buildLocation(string $rawVenue, string $fallbackName, string $localityFallback, string $streetOverride = ''): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $rawVenue) ?? '');
        $invalid = ['', 'tba', 'tbd', 'unknown', 'n/a', '-', 'na'];
        if (in_array(strtolower($clean), $invalid, true)) {
            $clean = $fallbackName;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $clean))));
        $placeName = $parts[0] ?? $fallbackName;
        $locality = count($parts) >= 2 ? $parts[count($parts) - 1] : ($localityFallback ?: $placeName);
        $street = $streetOverride !== '' ? $streetOverride : $clean;

        return [
            '@type' => 'Place',
            'name' => $placeName,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $street,
                'addressLocality' => $locality,
            ],
        ];
    }

    private function resolveEventStatus(string $status, string $statusText): string
    {
        $combined = strtolower(trim($status . ' ' . $statusText));
        if (str_contains($combined, 'abandon') || str_contains($combined, 'cancel') || str_contains($combined, 'no result')) {
            return 'https://schema.org/EventCancelled';
        }
        if (str_contains($combined, 'postpone')) {
            return 'https://schema.org/EventPostponed';
        }
        if (str_contains($combined, 'reschedul')) {
            return 'https://schema.org/EventRescheduled';
        }

        return 'https://schema.org/EventScheduled';
    }

    private function resolveTeams(array $teamsList, string $team1, string $team2, string $matchTitle): array
    {
        $t1 = trim($team1);
        $t2 = trim($team2);

        if (($t1 === '' || $t2 === '') && !empty($teamsList)) {
            $first = $teamsList[0] ?? null;
            $second = $teamsList[1] ?? null;
            if ($t1 === '') {
                $t1 = is_array($first) ? (string) ($first['name'] ?? $first['shortName'] ?? '') : (string) $first;
            }
            if ($t2 === '') {
                $t2 = is_array($second) ? (string) ($second['name'] ?? $second['shortName'] ?? '') : (string) $second;
            }
        }

        if (($t1 === '' || $t2 === '') && $matchTitle !== '') {
            $parts = preg_split('/\s+(?:vs?\.?)\s+/i', $matchTitle, 2);
            if (is_array($parts) && count($parts) === 2) {
                if ($t1 === '') {
                    $t1 = trim($parts[0]);
                }
                if ($t2 === '') {
                    $t2 = trim(preg_replace('/(?:,|\||\s+-)\s*.*$/', '', $parts[1]) ?? $parts[1]);
                }
            }
        }

        return [$t1 ?: ($matchTitle ?: 'Cricket Team A'), $t2];
    }
}
