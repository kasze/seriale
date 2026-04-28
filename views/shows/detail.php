<?php

declare(strict_types=1);

$showStatus = mb_strtolower(trim((string) ($show['status'] ?? '')));
$showStatusClass = $showStatus === 'ended' ? 'pill--ended' : 'pill--airing';

$externalRatings = array_values(array_filter([
    [
        'label' => 'IMDb',
        'value' => $show['imdb_rating'] ?? null,
        'source' => $show['imdb_rating_source'] ?? null,
    ],
    [
        'label' => 'Rotten Tomatoes',
        'value' => $show['rotten_tomatoes_rating'] ?? null,
        'source' => $show['rotten_tomatoes_source'] ?? null,
    ],
    [
        'label' => 'Metacritic',
        'value' => $show['metacritic_rating'] ?? null,
        'source' => $show['metacritic_rating_source'] ?? null,
    ],
], static fn (array $rating): bool => $rating['value'] !== null && $rating['value'] !== ''));
?>
<section class="show-hero">
    <div class="show-hero__poster">
        <?php if (!empty($show['poster_url'])): ?>
            <img src="<?= e((string) $show['poster_url']) ?>" alt="<?= e((string) $show['title']) ?>" decoding="async">
        <?php else: ?>
            <div class="show-card__placeholder"><?= e(mb_substr((string) $show['title'], 0, 1)) ?></div>
        <?php endif; ?>
    </div>
    <div class="show-hero__content">
        <p class="eyebrow"><?= e(translate_show_status((string) ($show['status'] ?? ''))) ?></p>
        <h1><?= e((string) $show['title']) ?></h1>
        <p class="lede"><?= e(truncate_text((string) ($show['summary'] ?? ''), 420)) ?></p>
        <div class="pill-row">
            <?php foreach (($show['genres'] ?? []) as $genre): ?>
                <span class="pill"><?= e((string) $genre) ?></span>
            <?php endforeach; ?>
            <span class="pill <?= e($showStatusClass) ?>"><?= e(translate_show_status((string) ($show['status'] ?? ''))) ?></span>
            <span class="pill pill--muted"><?= e((string) ($show['network_name'] ?: $show['web_channel_name'] ?: 'Brak platformy')) ?></span>
            <span class="pill pill--muted"><?= e((string) ($show['language'] ?: 'Brak języka')) ?></span>
        </div>
        <div class="show-hero__actions">
            <form method="post" action="<?= e(path_url('/shows/' . (string) $show['id'] . '/refresh')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="button button--primary">Odśwież dane</button>
            </form>
            <?php if ($isTracked): ?>
                <form method="post" action="<?= e(path_url('/shows/' . (string) $show['id'] . '/untrack')) ?>" class="inline-form" onsubmit="return confirm('Usunąć ten serial z obserwowanych?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--ghost" onclick="return confirm('Usunąć ten serial z obserwowanych?')">Usuń z obserwowanych</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="detail-grid">
    <article class="panel">
        <h2>Szybkie informacje</h2>
        <dl class="meta-list meta-list--wide">
            <div>
                <dt>Status</dt>
                <dd><?= e(translate_show_status((string) ($show['status'] ?? ''))) ?></dd>
            </div>
            <div>
                <dt>Sezony</dt>
                <dd><?= e((string) ($show['seasons_count'] ?? 0)) ?></dd>
            </div>
            <div>
                <dt>Ostatni odcinek</dt>
                <dd><?= e((string) (($show['last_episode_label'] ?? 'Brak') . (!empty($show['last_episode_air_at']) ? ' · ' . format_date((string) $show['last_episode_air_at'], false) : ''))) ?></dd>
            </div>
            <div>
                <dt>Następny odcinek</dt>
                <dd><?= e((string) (($show['next_episode_label'] ?? 'Brak zapowiedzi') . (!empty($show['next_episode_air_at']) ? ' · ' . format_date((string) $show['next_episode_air_at'], false) : ''))) ?></dd>
            </div>
            <div>
                <dt>Za ile</dt>
                <dd><span data-countdown data-at="<?= e((string) ($show['next_episode_air_at'] ?? '')) ?>"><?= e(countdown_label($show['next_episode_air_at'] ?? null)) ?></span></dd>
            </div>
        </dl>
    </article>

    <article class="panel">
        <h2>Oceny i linki</h2>
        <div class="rating-grid">
            <div class="rating-box">
                <span class="rating-box__label">TVmaze</span>
                <strong><?= e((string) ($show['tvmaze_rating'] ?? 'Brak')) ?></strong>
            </div>
            <?php foreach ($externalRatings as $rating): ?>
                <div class="rating-box">
                    <span class="rating-box__label"><?= e((string) $rating['label']) ?></span>
                    <strong><?= e((string) $rating['value']) ?></strong>
                    <?php if (!empty($rating['source'])): ?>
                        <small><?= e((string) $rating['source']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($externalRatings === []): ?>
            <p class="muted rating-note">Brak dodatkowych ocen z IMDb, Rotten Tomatoes lub Metacritic.</p>
        <?php endif; ?>
        <ul class="link-list">
            <?php if (!empty($show['imdb_url'])): ?><li><a href="<?= e((string) $show['imdb_url']) ?>" target="_blank" rel="noreferrer">IMDb</a></li><?php endif; ?>
            <?php if (!empty($show['tvmaze_url'])): ?><li><a href="<?= e((string) $show['tvmaze_url']) ?>" target="_blank" rel="noreferrer">TVmaze</a></li><?php endif; ?>
            <?php if (!empty($show['official_site'])): ?><li><a href="<?= e((string) $show['official_site']) ?>" target="_blank" rel="noreferrer">Oficjalna strona</a></li><?php endif; ?>
        </ul>
        <?php if (!empty($seasonProgress) && !empty($seasonProgress['markers'])): ?>
            <?php $selectedMarker = $seasonProgress['selected'] ?? $seasonProgress['markers'][0]; ?>
            <div class="season-progress" data-season-progress>
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
                    <span data-season-detail-date><?= e((string) ($selectedMarker['date'] ?? '')) ?></span>
                    <span class="pill <?= (($selectedMarker['status_key'] ?? 'upcoming') === 'aired') ? 'pill--aired' : 'pill--upcoming' ?>" data-season-detail-status><?= e((string) ($selectedMarker['status'] ?? '')) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php if ($similarShowsEnabled): ?>
    <?php
    $recommendedShows = $similarShows['recommended'] ?? [];
    $relatedShows = $similarShows['similar'] ?? [];
    $defaultTab = $recommendedShows !== [] ? 'recommended' : 'similar';
    ?>
    <section class="section">
        <div class="section__head">
            <h2>Podobne seriale</h2>
            <p>Propozycje oparte na podobieństwie, obsadzie, gatunkach i popularności.</p>
        </div>
        <?php if ($recommendedShows === [] && $relatedShows === []): ?>
            <div class="empty-state empty-state--soft">Nie znaleziono sensownych propozycji dla tego serialu.</div>
        <?php else: ?>
            <div class="tab-strip" data-tabs>
                <div class="tab-strip__nav" role="tablist" aria-label="Podobne seriale">
                    <button type="button" class="tab-strip__button<?= $defaultTab === 'recommended' ? ' is-active' : '' ?>" data-tab-button="recommended" role="tab" aria-selected="<?= $defaultTab === 'recommended' ? 'true' : 'false' ?>">Polecane</button>
                    <button type="button" class="tab-strip__button<?= $defaultTab === 'similar' ? ' is-active' : '' ?>" data-tab-button="similar" role="tab" aria-selected="<?= $defaultTab === 'similar' ? 'true' : 'false' ?>">Podobne</button>
                </div>
                <div class="tab-strip__panel<?= $defaultTab === 'recommended' ? ' is-active' : '' ?>" data-tab-panel="recommended" role="tabpanel" <?= $defaultTab === 'recommended' ? '' : 'hidden' ?>>
                    <?php if ($recommendedShows === []): ?>
                        <div class="empty-state empty-state--soft">Brak polecanych seriali dla tej pozycji.</div>
                    <?php else: ?>
                        <div class="related-grid">
                            <?php foreach ($recommendedShows as $item): ?>
                                <article class="related-card">
                                    <div class="related-card__poster">
                                        <?php if (!empty($item['poster_url'])): ?>
                                            <img src="<?= e((string) $item['poster_url']) ?>" alt="<?= e((string) $item['title']) ?>" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <div class="show-card__placeholder"><?= e(mb_substr((string) $item['title'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-card__body">
                                        <div class="related-card__head">
                                            <h3><?= e((string) $item['title']) ?></h3>
                                            <?php if (!empty($item['rating'])): ?>
                                                <span class="pill pill--muted">TMDb <?= e((string) $item['rating']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="related-card__meta">
                                            <?= e(implode(' · ', array_filter([(string) ($item['platform'] ?: ''), (string) ($item['year'] ?: '')]))) ?: 'Brak danych' ?>
                                        </p>
                                        <p class="related-card__summary">
                                            <?= e(truncate_text((string) ($item['summary'] ?: 'Brak opisu.'), 180)) ?>
                                        </p>
                                        <div class="related-card__actions">
                                            <a
                                                class="button button--ghost"
                                                href="<?= e((string) ($item['local_url'] ?? $item['search_url'])) ?>"
                                                data-track-link
                                                data-search-url="<?= e((string) $item['search_url']) ?>"
                                                data-local-url="<?= e((string) ($item['local_url'] ?? '')) ?>"
                                                data-search-label="Szukaj w aplikacji"
                                                data-local-label="Szczegóły"
                                            ><?= e(!empty($item['is_tracked']) ? 'Szczegóły' : 'Szukaj w aplikacji') ?></a>
                                            <div class="discovery-action" data-track-slot data-track-key="<?= e('tmdb:' . (string) ($item['tmdb_id'] ?? ($item['search_query'] ?? $item['title']))) ?>">
                                                <?php if (!empty($item['is_tracked'])): ?>
                                                    <span class="pill pill--accent discovery-status">Już obserwowany</span>
                                                <?php else: ?>
                                                    <form method="post" action="<?= e(path_url('/tracked/query')) ?>" class="inline-form" data-ajax-track>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="query" value="<?= e((string) ($item['search_query'] ?? $item['original_title'] ?: $item['title'])) ?>">
                                                        <input type="hidden" name="year" value="<?= e((string) ($item['year'] ?? '')) ?>">
                                                        <input type="hidden" name="tmdb_id" value="<?= e((string) ($item['tmdb_id'] ?? '')) ?>">
                                                        <input type="hidden" name="redirect_to" value="<?= e(path_url('/shows/' . (string) $show['id'])) ?>">
                                                        <button type="submit" class="button button--primary">Dodaj do obserwowanych</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tab-strip__panel<?= $defaultTab === 'similar' ? ' is-active' : '' ?>" data-tab-panel="similar" role="tabpanel" <?= $defaultTab === 'similar' ? '' : 'hidden' ?>>
                    <?php if ($relatedShows === []): ?>
                        <div class="empty-state empty-state--soft">Brak podobnych seriali dla tej pozycji.</div>
                    <?php else: ?>
                        <div class="related-grid">
                            <?php foreach ($relatedShows as $item): ?>
                                <article class="related-card">
                                    <div class="related-card__poster">
                                        <?php if (!empty($item['poster_url'])): ?>
                                            <img src="<?= e((string) $item['poster_url']) ?>" alt="<?= e((string) $item['title']) ?>" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <div class="show-card__placeholder"><?= e(mb_substr((string) $item['title'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-card__body">
                                        <div class="related-card__head">
                                            <h3><?= e((string) $item['title']) ?></h3>
                                            <?php if (!empty($item['rating'])): ?>
                                                <span class="pill pill--muted">TMDb <?= e((string) $item['rating']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="related-card__meta">
                                            <?= e(implode(' · ', array_filter([(string) ($item['platform'] ?: ''), (string) ($item['year'] ?: '')]))) ?: 'Brak danych' ?>
                                        </p>
                                        <p class="related-card__summary">
                                            <?= e(truncate_text((string) ($item['summary'] ?: 'Brak opisu.'), 180)) ?>
                                        </p>
                                        <div class="related-card__actions">
                                            <a
                                                class="button button--ghost"
                                                href="<?= e((string) ($item['local_url'] ?? $item['search_url'])) ?>"
                                                data-track-link
                                                data-search-url="<?= e((string) $item['search_url']) ?>"
                                                data-local-url="<?= e((string) ($item['local_url'] ?? '')) ?>"
                                                data-search-label="Szukaj w aplikacji"
                                                data-local-label="Szczegóły"
                                            ><?= e(!empty($item['is_tracked']) ? 'Szczegóły' : 'Szukaj w aplikacji') ?></a>
                                            <div class="discovery-action" data-track-slot data-track-key="<?= e('tmdb:' . (string) ($item['tmdb_id'] ?? ($item['search_query'] ?? $item['title']))) ?>">
                                                <?php if (!empty($item['is_tracked'])): ?>
                                                    <span class="pill pill--accent discovery-status">Już obserwowany</span>
                                                <?php else: ?>
                                                    <form method="post" action="<?= e(path_url('/tracked/query')) ?>" class="inline-form" data-ajax-track>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="query" value="<?= e((string) ($item['search_query'] ?? $item['original_title'] ?: $item['title'])) ?>">
                                                        <input type="hidden" name="year" value="<?= e((string) ($item['year'] ?? '')) ?>">
                                                        <input type="hidden" name="tmdb_id" value="<?= e((string) ($item['tmdb_id'] ?? '')) ?>">
                                                        <input type="hidden" name="redirect_to" value="<?= e(path_url('/shows/' . (string) $show['id'])) ?>">
                                                        <button type="submit" class="button button--primary">Dodaj do obserwowanych</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section__head">
        <h2>Sezony i odcinki</h2>
        <p>Najnowszy wyemitowany i najbliższy nadchodzący odcinek są wyróżnione.</p>
    </div>
    <?php if ($seasonGroups === []): ?>
        <div class="empty-state">Brak zsynchronizowanych odcinków.</div>
    <?php else: ?>
        <div class="season-stack">
            <?php foreach ($seasonGroups as $group): ?>
                <article class="season-card">
                    <header class="season-card__head">
                        <h3><?= e((string) ($group['season']['name'] ?? ('Sezon ' . ($group['season']['season_number'] ?? '?')))) ?></h3>
                        <span class="pill pill--muted"><?= e((string) count($group['episodes'])) ?> odc.</span>
                    </header>
                    <div class="episode-list">
                        <?php foreach ($group['episodes'] as $episode): ?>
                            <article class="episode-row <?= !empty($episode['is_latest']) ? 'episode-row--latest' : '' ?> <?= !empty($episode['is_next']) ? 'episode-row--next' : '' ?>">
                                <div class="episode-row__index"><?= e(sprintf('S%02dE%02d', (int) ($episode['season_number'] ?? 0), (int) ($episode['episode_number'] ?? 0))) ?></div>
                                <div class="episode-row__body">
                                    <div class="episode-row__title-row">
                                        <strong><?= e((string) ($episode['name'] ?? 'Bez tytułu')) ?></strong>
                                        <span class="pill <?= !empty($episode['is_next']) ? 'pill--upcoming' : 'pill--aired' ?>"><?= e((string) $episode['status_label']) ?></span>
                                    </div>
                                    <p><?= e((string) $episode['summary_text']) ?></p>
                                </div>
                                <div class="episode-row__meta">
                                    <span><?= e(format_airing_date($episode['airstamp'] ?? $episode['airdate'] ?? null, $episode['airtime'] ?? null)) ?></span>
                                    <span><?= e(relative_date($episode['airstamp'] ?? $episode['airdate'] ?? null)) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
