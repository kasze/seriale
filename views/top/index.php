<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="section__head">
        <div>
            <h1>Topki seriali</h1>
            <p>Popularne, trendujące i wysoko oceniane seriale. Obserwowane tytuły są ukryte.</p>
        </div>
    </div>

    <?php if (!$topListsEnabled): ?>
        <div class="empty-state">
            Włącz TMDb i wpisz klucz API w ustawieniach, żeby zobaczyć topki seriali.
        </div>
    <?php elseif ($lists === []): ?>
        <div class="empty-state empty-state--soft">
            Nie udało się teraz pobrać list rankingowych. Spróbuj ponownie później.
        </div>
    <?php else: ?>
        <div class="tab-strip" data-tabs>
            <div class="tab-strip__nav" role="tablist" aria-label="Topki seriali">
                <?php foreach ($lists as $list): ?>
                    <button
                        type="button"
                        class="tab-strip__button<?= $defaultTopTab === $list['key'] ? ' is-active' : '' ?>"
                        data-tab-button="<?= e((string) $list['key']) ?>"
                        role="tab"
                        aria-selected="<?= $defaultTopTab === $list['key'] ? 'true' : 'false' ?>"
                    >
                        <?= e((string) $list['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($lists as $list): ?>
                <div
                    class="tab-strip__panel<?= $defaultTopTab === $list['key'] ? ' is-active' : '' ?>"
                    data-tab-panel="<?= e((string) $list['key']) ?>"
                    role="tabpanel"
                    <?= $defaultTopTab === $list['key'] ? '' : 'hidden' ?>
                >
                    <div class="panel panel--subtle top-list-head">
                        <h2><?= e((string) $list['label']) ?></h2>
                        <p><?= e((string) ($list['description'] ?? '')) ?></p>
                    </div>

                    <div class="top-list">
                        <?php foreach (($list['items'] ?? []) as $item): ?>
                            <article class="top-row">
                                <div class="top-card__rank"><?= e((string) $item['rank']) ?></div>
                                <div class="top-row__poster">
                                    <?php if (!empty($item['poster_url'])): ?>
                                        <img src="<?= e((string) $item['poster_url']) ?>" alt="<?= e((string) $item['title']) ?>">
                                    <?php else: ?>
                                        <div class="show-card__placeholder"><?= e(mb_substr((string) $item['title'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="top-row__body">
                                    <div class="top-row__head">
                                        <h3><?= e((string) $item['title']) ?></h3>
                                        <?php if (!empty($item['rating'])): ?>
                                            <span class="pill pill--muted">TMDb <?= e((string) $item['rating']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['original_title']) && $item['original_title'] !== $item['title']): ?>
                                        <p class="top-row__original"><?= e((string) $item['original_title']) ?></p>
                                    <?php endif; ?>
                                    <p class="top-row__meta"><?= e((string) ($item['meta'] ?: 'Brak danych')) ?></p>
                                    <p class="top-row__summary"><?= e(truncate_text((string) ($item['summary'] ?: 'Brak opisu.'), 180)) ?></p>
                                </div>
                                <div class="top-row__side">
                                    <div class="top-row__actions">
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
                                                    <input type="hidden" name="redirect_to" value="<?= e(path_url('/top')) ?>">
                                                    <button type="submit" class="button button--primary">Dodaj do obserwowanych</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (($list['items'] ?? []) === []): ?>
                            <div class="empty-state empty-state--soft">W tym rankingu nie ma teraz nowych tytułów poza tymi, które już obserwujesz.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
