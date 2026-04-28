<?php

declare(strict_types=1);

if (!function_exists('render_season_progress')) {
    function render_season_progress(?array $seasonProgress): void
    {
        if (empty($seasonProgress) || empty($seasonProgress['markers'])) {
            return;
        }

        $selectedMarker = $seasonProgress['selected'] ?? $seasonProgress['markers'][0];
        ?>
        <div class="season-progress season-progress--compact" data-season-progress>
            <div class="season-progress__head">
                <div>
                    <h3><?= e((string) ($seasonProgress['season_name'] ?? 'Sezon')) ?></h3>
                    <p><?= e(sprintf('%d z %d odcinków wyemitowanych', (int) ($seasonProgress['aired_count'] ?? 0), (int) ($seasonProgress['total_count'] ?? 0))) ?></p>
                </div>
                <span class="pill pill--muted"><?= e((string) ($seasonProgress['total_count'] ?? 0)) ?> odc.</span>
            </div>
            <div class="season-progress__track" role="list" aria-label="Przebieg sezonu">
                <?php foreach ($seasonProgress['markers'] as $marker): ?>
                    <button
                        type="button"
                        class="season-progress__marker season-progress__marker--<?= e((string) ($marker['status_key'] ?? 'upcoming')) ?> <?= (($selectedMarker['id'] ?? null) === ($marker['id'] ?? null)) ? 'is-active' : '' ?> <?= !empty($marker['is_latest']) ? 'is-latest' : '' ?> <?= !empty($marker['is_next']) ? 'is-next' : '' ?>"
                        data-season-marker
                        data-id="<?= e((string) ($marker['id'] ?? '')) ?>"
                        data-code="<?= e((string) ($marker['full_code'] ?? '')) ?>"
                        data-title="<?= e((string) ($marker['title'] ?? '')) ?>"
                        data-date="<?= e((string) ($marker['date'] ?? '')) ?>"
                        data-relative="<?= e((string) ($marker['relative'] ?? '')) ?>"
                        data-status="<?= e((string) ($marker['status'] ?? '')) ?>"
                        aria-pressed="<?= (($selectedMarker['id'] ?? null) === ($marker['id'] ?? null)) ? 'true' : 'false' ?>"
                    >
                        <span><?= e((string) ($marker['code'] ?? '')) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="season-progress__detail" data-season-detail>
                <strong data-season-detail-code><?= e((string) ($selectedMarker['full_code'] ?? '')) ?></strong>
                <span data-season-detail-title><?= e((string) ($selectedMarker['title'] ?? '')) ?></span>
                <span data-season-detail-date><?= e((string) (($selectedMarker['date'] ?? '') . (!empty($selectedMarker['relative']) ? ' · ' . $selectedMarker['relative'] : ''))) ?></span>
                <span class="pill <?= (($selectedMarker['status_key'] ?? 'upcoming') === 'aired') ? 'pill--aired' : 'pill--upcoming' ?>" data-season-detail-status><?= e((string) ($selectedMarker['status'] ?? '')) ?></span>
            </div>
        </div>
        <?php
    }
}

$timeline = $dashboard['timeline'] ?? [
    'start_offset' => -2,
    'days' => [],
    'selected' => null,
    'has_episodes' => false,
    'previous_offset' => -9,
    'next_offset' => 5,
    'window_label' => '',
];
?>
<section class="section">
    <div
        class="timeline-card"
        data-episode-timeline
        data-endpoint="<?= e(path_url('/dashboard/timeline')) ?>"
        data-offset="<?= e((string) ($timeline['start_offset'] ?? -2)) ?>"
    >
        <div class="timeline-card__head">
            <div>
                <h2>Odcinki</h2>
            </div>
            <div class="timeline-card__nav">
                <button type="button" class="button button--ghost timeline-nav-button" data-timeline-nav="prev" data-offset="<?= e((string) ($timeline['previous_offset'] ?? -9)) ?>" aria-label="Poprzedni zakres">&#8592;</button>
                <strong class="timeline-card__range" data-timeline-range><?= e((string) ($timeline['window_label'] ?? '')) ?></strong>
                <button type="button" class="timeline-today-link" data-timeline-today data-offset="-2">Dziś</button>
                <button type="button" class="button button--ghost timeline-nav-button" data-timeline-nav="next" data-offset="<?= e((string) ($timeline['next_offset'] ?? 5)) ?>" aria-label="Następny zakres">&#8594;</button>
            </div>
        </div>

        <div class="timeline-strip" data-timeline-strip>
            <?php foreach (($timeline['days'] ?? []) as $day): ?>
                <section class="timeline-day <?= !empty($day['is_today']) ? 'timeline-day--today' : '' ?> <?= ($day['episodes'] ?? []) === [] ? 'timeline-day--empty' : '' ?>">
                    <header class="timeline-day__head">
                        <span class="timeline-day__label"><?= e((string) ($day['label'] ?? '')) ?></span>
                        <strong><?= e((string) ($day['day_number'] ?? '')) ?></strong>
                    </header>
                    <div class="timeline-day__rail"></div>
                    <div class="timeline-day__events">
                        <?php if (($day['episodes'] ?? []) === []): ?>
                            <span class="timeline-day__empty">Brak</span>
                        <?php else: ?>
                            <?php foreach ($day['episodes'] as $entry): ?>
                                <button
                                    type="button"
                                    class="timeline-event <?= (($timeline['selected']['id'] ?? null) === ($entry['id'] ?? null)) ? 'is-active' : '' ?> timeline-event--<?= e((string) ($entry['status_key'] ?? 'upcoming')) ?>"
                                    data-timeline-event
                                    data-id="<?= e((string) ($entry['id'] ?? '')) ?>"
                                    data-title="<?= e((string) ($entry['title'] ?? '')) ?>"
                                    data-show-url="<?= e((string) ($entry['show_url'] ?? '')) ?>"
                                    data-episode-code="<?= e((string) ($entry['episode_code'] ?? '')) ?>"
                                    data-episode-name="<?= e((string) ($entry['episode_name'] ?? '')) ?>"
                                    data-when="<?= e((string) ($entry['when'] ?? '')) ?>"
                                    data-relative="<?= e((string) ($entry['relative'] ?? '')) ?>"
                                    data-status="<?= e((string) ($entry['status'] ?? '')) ?>"
                                    data-status-key="<?= e((string) ($entry['status_key'] ?? 'upcoming')) ?>"
                                    data-poster-url="<?= e((string) ($entry['poster_url'] ?? '')) ?>"
                                    data-tpb-url="<?= e((string) ($entry['tpb_url'] ?? '')) ?>"
                                    data-btdig-url="<?= e((string) ($entry['btdig_url'] ?? '')) ?>"
                                    data-season-progress="<?= e(json_encode($entry['season_progress'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null') ?>"
                                    title="<?= e((string) ($entry['title'] ?? '')) ?>"
                                    aria-pressed="<?= (($timeline['selected']['id'] ?? null) === ($entry['id'] ?? null)) ? 'true' : 'false' ?>"
                                >
                                    <span class="timeline-event__label"><?= e((string) ($entry['short_title'] ?? 'Bez tytułu')) ?></span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <article class="timeline-preview <?= empty($timeline['selected']) ? 'is-empty' : '' ?>" data-timeline-preview>
            <?php if (!empty($timeline['selected'])): ?>
                <?php $selected = $timeline['selected']; ?>
                <div class="timeline-preview__poster" data-timeline-poster>
                    <?php if (($selected['poster_url'] ?? '') !== ''): ?>
                        <img src="<?= e((string) $selected['poster_url']) ?>" alt="<?= e((string) $selected['title']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="show-card__placeholder" aria-hidden="true"><?= e(mb_substr((string) $selected['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="timeline-preview__body">
                    <div class="timeline-preview__meta">
                        <span class="pill <?= ($selected['status_key'] ?? 'upcoming') === 'aired' ? 'pill--aired' : 'pill--upcoming' ?>" data-timeline-status><?= e((string) ($selected['status'] ?? '')) ?></span>
                        <span data-timeline-when><?= e((string) ($selected['when'] ?? '')) ?></span>
                        <strong data-timeline-relative><?= e((string) ($selected['relative'] ?? '')) ?></strong>
                    </div>
                    <h3>
                        <a href="<?= e((string) ($selected['show_url'] ?? '#')) ?>" data-timeline-title><?= e((string) ($selected['title'] ?? '')) ?></a>
                    </h3>
                    <p data-timeline-episode><?= e((string) (($selected['episode_code'] ?? '') . ' · ' . ($selected['episode_name'] ?? ''))) ?></p>
                    <div class="timeline-preview__actions">
                        <a class="button button--primary" href="<?= e((string) ($selected['show_url'] ?? '#')) ?>" data-timeline-show>Przejdź do serialu</a>
                        <?php if (($selected['status_key'] ?? 'upcoming') === 'aired' && ($selected['tpb_url'] ?? '') !== ''): ?>
                            <a class="button button--ghost" href="<?= e((string) $selected['tpb_url']) ?>" data-timeline-tpb data-open-external target="_blank" rel="noreferrer noopener">TPB</a>
                        <?php endif; ?>
                        <?php if (($selected['status_key'] ?? 'upcoming') === 'aired' && ($selected['btdig_url'] ?? '') !== ''): ?>
                            <a class="button button--ghost" href="<?= e((string) $selected['btdig_url']) ?>" data-timeline-btdig data-open-external target="_blank" rel="noreferrer noopener">BTDig</a>
                        <?php endif; ?>
                    </div>
                    <?php render_season_progress($selected['season_progress'] ?? null); ?>
                </div>
            <?php else: ?>
                <div class="empty-state empty-state--soft timeline-preview__empty" data-timeline-empty>Brak odcinków w tym zakresie. Zmień zakres przyciskami powyżej.</div>
            <?php endif; ?>
        </article>
    </div>
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
