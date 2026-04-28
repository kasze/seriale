<?php

declare(strict_types=1);

$timezone = app('timezone');
$today = new DateTimeImmutable('now', $timezone);
$timelineStart = $today->setTime(0, 0)->modify('-4 days');
$timelineEnd = $today->setTime(23, 59, 59)->modify('+4 days');
$timelineWeekdays = [1 => 'Pon', 2 => 'Wt', 3 => 'Śr', 4 => 'Czw', 5 => 'Pt', 6 => 'Sob', 7 => 'Niedz'];
$timelineDays = [];
$timelineAll = [];

for ($offset = -4; $offset <= 4; $offset++) {
    $date = $today->setTime(0, 0)->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
    $key = $date->format('Y-m-d');
    $timelineDays[$key] = [
        'key' => $key,
        'offset' => $offset,
        'label' => $timelineWeekdays[(int) $date->format('N')] ?? '',
        'day_number' => $date->format('j'),
        'is_today' => $offset === 0,
        'episodes' => [],
    ];
}

foreach (array_merge($dashboard['recently_aired'], $dashboard['upcoming']) as $episode) {
    $stamp = isset($episode['airstamp']) ? new DateTimeImmutable((string) $episode['airstamp']) : null;

    if ($stamp === null) {
        continue;
    }

    $localStamp = $stamp->setTimezone($timezone);

    if ($localStamp < $timelineStart || $localStamp > $timelineEnd) {
        continue;
    }

    $dayKey = $localStamp->format('Y-m-d');

    if (!isset($timelineDays[$dayKey])) {
        continue;
    }

    $isAired = $stamp <= $today;
    $episodeCode = sprintf(
        'S%02dE%02d',
        (int) ($episode['season_number'] ?? 0),
        (int) ($episode['episode_number'] ?? 0)
    );
    $entry = [
        'id' => 'timeline-' . (string) ($episode['id'] ?? md5((string) ($episode['show_id_local'] ?? '') . $dayKey . $episodeCode)),
        'title' => (string) ($episode['title'] ?? ''),
        'short_title' => truncate_text((string) ($episode['title'] ?? ''), 18),
        'show_url' => path_url('/shows/' . (string) ($episode['show_id_local'] ?? '')),
        'episode_code' => $episodeCode,
        'episode_name' => (string) ($episode['name'] ?? 'Bez tytułu'),
        'when' => format_airing_date((string) $episode['airstamp'], $episode['airtime'] ?? null),
        'relative' => relative_date((string) $episode['airstamp']),
        'status' => $isAired ? 'Wyemitowany' : 'Nadchodzący',
        'status_key' => $isAired ? 'aired' : 'upcoming',
        'poster_url' => (string) ($episode['poster_url'] ?? ''),
        'tpb_url' => $isAired ? tpb_episode_search_url((string) ($episode['title'] ?? ''), isset($episode['season_number']) ? (int) $episode['season_number'] : null, isset($episode['episode_number']) ? (int) $episode['episode_number'] : null) : '',
        'btdig_url' => $isAired ? btdig_episode_search_url((string) ($episode['title'] ?? ''), isset($episode['season_number']) ? (int) $episode['season_number'] : null, isset($episode['episode_number']) ? (int) $episode['episode_number'] : null) : '',
        'timestamp' => $stamp->getTimestamp(),
    ];

    $timelineDays[$dayKey]['episodes'][] = $entry;
    $timelineAll[] = $entry;
}

foreach ($timelineDays as $key => $day) {
    usort(
        $day['episodes'],
        static fn (array $left, array $right): int => ($left['timestamp'] ?? 0) <=> ($right['timestamp'] ?? 0)
    );
    $timelineDays[$key] = $day;
}

if ($timelineAll !== []) {
    usort(
        $timelineAll,
        static fn (array $left, array $right): int => abs(($left['timestamp'] ?? 0) - $today->getTimestamp()) <=> abs(($right['timestamp'] ?? 0) - $today->getTimestamp())
    );
}

$timelineSelected = $timelineAll[0] ?? null;

?>
<section class="section">
    <?php if ($timelineSelected !== null): ?>
        <div class="timeline-card" data-episode-timeline>
            <div class="timeline-card__head">
                <div>
                    <h2>Oś odcinków</h2>
                    <p>4 dni wstecz i 4 dni do przodu. Kliknij odcinek, żeby podejrzeć szczegóły.</p>
                </div>
            </div>

            <div class="timeline-strip">
                <?php foreach ($timelineDays as $day): ?>
                    <section class="timeline-day <?= $day['is_today'] ? 'timeline-day--today' : '' ?> <?= $day['episodes'] === [] ? 'timeline-day--empty' : '' ?>">
                        <header class="timeline-day__head">
                            <span class="timeline-day__label"><?= e($day['label']) ?></span>
                            <strong><?= e($day['day_number']) ?></strong>
                        </header>
                        <div class="timeline-day__rail"></div>
                        <div class="timeline-day__events">
                            <?php if ($day['episodes'] === []): ?>
                                <span class="timeline-day__empty">Brak</span>
                            <?php else: ?>
                                <?php foreach ($day['episodes'] as $entry): ?>
                                    <button
                                        type="button"
                                        class="timeline-event <?= $timelineSelected['id'] === $entry['id'] ? 'is-active' : '' ?> timeline-event--<?= e($entry['status_key']) ?>"
                                        data-timeline-event
                                        data-id="<?= e($entry['id']) ?>"
                                        data-title="<?= e($entry['title']) ?>"
                                        data-show-url="<?= e($entry['show_url']) ?>"
                                        data-episode-code="<?= e($entry['episode_code']) ?>"
                                        data-episode-name="<?= e($entry['episode_name']) ?>"
                                        data-when="<?= e($entry['when']) ?>"
                                        data-relative="<?= e($entry['relative']) ?>"
                                        data-status="<?= e($entry['status']) ?>"
                                        data-status-key="<?= e($entry['status_key']) ?>"
                                        data-poster-url="<?= e($entry['poster_url']) ?>"
                                        data-tpb-url="<?= e($entry['tpb_url']) ?>"
                                        data-btdig-url="<?= e($entry['btdig_url']) ?>"
                                        title="<?= e($entry['title']) ?>"
                                        aria-pressed="<?= $timelineSelected['id'] === $entry['id'] ? 'true' : 'false' ?>"
                                    >
                                        <span class="timeline-event__dot"></span>
                                        <span class="timeline-event__label"><?= e($entry['short_title']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <article class="timeline-preview" data-timeline-preview>
                <div class="timeline-preview__poster" data-timeline-poster>
                    <?php if ($timelineSelected['poster_url'] !== ''): ?>
                        <img src="<?= e($timelineSelected['poster_url']) ?>" alt="<?= e($timelineSelected['title']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="show-card__placeholder" aria-hidden="true"><?= e(mb_substr((string) $timelineSelected['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="timeline-preview__body">
                    <div class="timeline-preview__meta">
                        <span class="pill <?= $timelineSelected['status_key'] === 'aired' ? 'pill--aired' : 'pill--upcoming' ?>" data-timeline-status><?= e($timelineSelected['status']) ?></span>
                        <span data-timeline-when><?= e($timelineSelected['when']) ?></span>
                        <strong data-timeline-relative><?= e($timelineSelected['relative']) ?></strong>
                    </div>
                    <h3>
                        <a href="<?= e($timelineSelected['show_url']) ?>" data-timeline-title><?= e($timelineSelected['title']) ?></a>
                    </h3>
                    <p data-timeline-episode><?= e($timelineSelected['episode_code'] . ' · ' . $timelineSelected['episode_name']) ?></p>
                    <div class="timeline-preview__actions">
                        <a class="button button--primary" href="<?= e($timelineSelected['show_url']) ?>" data-timeline-show>Przejdź do serialu</a>
                        <a class="button button--ghost" href="<?= e($timelineSelected['tpb_url']) ?>" data-timeline-tpb <?= $timelineSelected['tpb_url'] === '' ? 'hidden' : '' ?> target="_blank" rel="noreferrer">TPB</a>
                        <a class="button button--ghost" href="<?= e($timelineSelected['btdig_url']) ?>" data-timeline-btdig <?= $timelineSelected['btdig_url'] === '' ? 'hidden' : '' ?> target="_blank" rel="noreferrer">BTDig</a>
                    </div>
                </div>
            </article>
        </div>
    <?php else: ?>
        <div class="empty-state">Brak odcinków z ostatniego tygodnia i zapowiedzi na najbliższy tydzień.</div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__head section__head--split">
        <div>
            <h2>Obserwowane seriale</h2>
        </div>
        <form method="get" action="<?= e(path_url('/dashboard')) ?>" class="inline-form">
            <label class="field field--inline">
                <span class="field-label">Sortuj</span>
                <select class="input" name="sort">
                    <option value="next" <?= $sort === 'next' ? 'selected' : '' ?>>Najbliższy odcinek</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Alfabetycznie</option>
                    <option value="added" <?= $sort === 'added' ? 'selected' : '' ?>>Data dodania</option>
                </select>
            </label>
            <button type="submit" class="button button--ghost">Zmień</button>
        </form>
    </div>
    <?php if ($dashboard['tracked'] === []): ?>
        <div class="empty-state">Nie obserwujesz jeszcze żadnych seriali. Zacznij od wyszukania tytułu.</div>
    <?php else: ?>
        <div class="show-grid">
            <?php foreach ($dashboard['tracked'] as $item): ?>
                <?php require base_path('views/partials/show_card.php'); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
