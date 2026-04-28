<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ShowRepository;
use App\Repositories\TrackedShowRepository;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardService
{
    public function __construct(
        private TrackedShowRepository $tracked,
        private ShowRepository $shows
    ) {
    }

    public function build(int $userId, ?string $lastSeenAt, string $sort = 'next'): array
    {
        return [
            'timeline' => $this->timelineWindow($userId),
            'today' => $this->tracked->listUpcomingEpisodes($userId, 1, true),
            'upcoming' => $this->tracked->listUpcomingEpisodes($userId, 7, false),
            'recently_aired' => $this->tracked->listRecentlyAiredEpisodes($userId, 7),
            'older_aired' => $this->tracked->listOlderAiredEpisodes($userId, 7),
            'future_later' => $this->tracked->listFutureShowForecastsAfter($userId, 7),
            'tracked' => $this->tracked->listTrackedWithStats($userId, $sort),
            'new_since_visit' => $lastSeenAt ? $this->tracked->countNewSinceVisit($userId, $lastSeenAt) : 0,
        ];
    }

    public function timelineWindow(int $userId, int $startOffset = -4, int $days = 9): array
    {
        $days = max(1, $days);
        $timezone = app('timezone');
        \assert($timezone instanceof DateTimeZone);

        $today = new DateTimeImmutable('now', $timezone);
        $windowStart = $today->setTime(0, 0)->modify(($startOffset >= 0 ? '+' : '') . $startOffset . ' days');
        $windowEnd = $windowStart->modify('+' . ($days - 1) . ' days')->setTime(23, 59, 59);
        $weekdays = [1 => 'Pon', 2 => 'Wt', 3 => 'Śr', 4 => 'Czw', 5 => 'Pt', 6 => 'Sob', 7 => 'Niedz'];
        $dayBuckets = [];

        for ($index = 0; $index < $days; $index++) {
            $date = $windowStart->modify('+' . $index . ' days');
            $key = $date->format('Y-m-d');
            $dayBuckets[$key] = [
                'key' => $key,
                'offset' => $startOffset + $index,
                'label' => $weekdays[(int) $date->format('N')] ?? '',
                'day_number' => $date->format('j'),
                'is_today' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                'episodes' => [],
            ];
        }

        $episodes = $this->tracked->listEpisodesBetween(
            $userId,
            $windowStart->format(DATE_ATOM),
            $windowEnd->format(DATE_ATOM)
        );
        $timelineAll = [];

        foreach ($episodes as $episode) {
            $stampValue = trim((string) ($episode['airstamp'] ?? ''));

            if ($stampValue === '') {
                continue;
            }

            $stamp = new DateTimeImmutable($stampValue);
            $localStamp = $stamp->setTimezone($timezone);
            $dayKey = $localStamp->format('Y-m-d');

            if (!isset($dayBuckets[$dayKey])) {
                continue;
            }

            $isAired = $stamp <= $today;
            $seasonNumber = isset($episode['season_number']) ? (int) $episode['season_number'] : null;
            $episodeNumber = isset($episode['episode_number']) ? (int) $episode['episode_number'] : null;
            $episodeCode = sprintf('S%02dE%02d', (int) ($seasonNumber ?? 0), (int) ($episodeNumber ?? 0));
            $title = (string) ($episode['title'] ?? '');
            $entry = [
                'id' => 'timeline-' . (string) ($episode['id'] ?? md5((string) ($episode['show_id_local'] ?? '') . $dayKey . $episodeCode)),
                'title' => $title,
                'short_title' => $this->shortTitle($title, 18),
                'show_url' => path_url('/shows/' . (string) ($episode['show_id_local'] ?? '')),
                'episode_code' => $episodeCode,
                'episode_name' => (string) ($episode['name'] ?? 'Bez tytułu'),
                'when' => format_airing_date($stampValue, $episode['airtime'] ?? null),
                'relative' => relative_date($stampValue),
                'status' => $isAired ? 'Wyemitowany' : 'Nadchodzący',
                'status_key' => $isAired ? 'aired' : 'upcoming',
                'poster_url' => (string) ($episode['poster_url'] ?? ''),
                'tpb_url' => $isAired ? tpb_episode_search_url($title, $seasonNumber, $episodeNumber) : '',
                'btdig_url' => $isAired ? btdig_episode_search_url($title, $seasonNumber, $episodeNumber) : '',
                'timestamp' => $stamp->getTimestamp(),
            ];

            $dayBuckets[$dayKey]['episodes'][] = $entry;
            $timelineAll[] = $entry;
        }

        foreach ($dayBuckets as $key => $day) {
            usort(
                $day['episodes'],
                static fn (array $left, array $right): int => ($left['timestamp'] ?? 0) <=> ($right['timestamp'] ?? 0)
            );
            $dayBuckets[$key] = $day;
        }

        if ($timelineAll !== []) {
            usort(
                $timelineAll,
                static fn (array $left, array $right): int => abs(($left['timestamp'] ?? 0) - $today->getTimestamp()) <=> abs(($right['timestamp'] ?? 0) - $today->getTimestamp())
            );
        }

        return [
            'start_offset' => $startOffset,
            'days' => array_values($dayBuckets),
            'selected' => $timelineAll[0] ?? null,
            'has_episodes' => $timelineAll !== [],
            'previous_offset' => $startOffset - $days,
            'next_offset' => $startOffset + $days,
            'window_label' => sprintf(
                '%s - %s',
                format_date($windowStart->format(DATE_ATOM)),
                format_date($windowEnd->format(DATE_ATOM))
            ),
        ];
    }

    private function shortTitle(string $title, int $limit = 18): string
    {
        $title = trim($title);

        if ($title === '') {
            return 'Bez tytułu';
        }

        if (mb_strlen($title) <= $limit) {
            return $title;
        }

        return rtrim(mb_substr($title, 0, $limit));
    }
}
