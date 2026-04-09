<?php

declare(strict_types=1);

$renderEpisodeFeed = static function (array $episodes, string $mode): void {
    foreach ($episodes as $episode): ?>
        <article class="episode-feed__item">
            <div class="episode-feed__time">
                <?php if ($mode === 'past'): ?>
                    <span class="pill pill--aired">Wyemitowany</span>
                    <strong><?= e(relative_date((string) $episode['airstamp'])) ?></strong>
                <?php else: ?>
                    <span class="pill pill--upcoming">Nadchodzacy</span>
                    <strong><?= e(relative_date((string) $episode['airstamp'])) ?></strong>
                <?php endif; ?>
            </div>
            <div class="episode-feed__body">
                <strong>
                    <a class="episode-feed__title" href="<?= e(path_url('/shows/' . (string) $episode['show_id_local'])) ?>">
                        <?= e((string) $episode['title']) ?>
                    </a>
                </strong>
                <p><?= e(sprintf('S%02dE%02d · %s · %s', (int) ($episode['season_number'] ?? 0), (int) ($episode['episode_number'] ?? 0), (string) ($episode['name'] ?? 'Bez tytulu'), format_airing_date((string) $episode['airstamp'], $episode['airtime'] ?? null))) ?></p>
            </div>
            <?php if ($mode === 'past'): ?>
                <div class="episode-feed__actions">
                    <a class="button button--ghost" href="<?= e(tpb_episode_search_url((string) $episode['title'], isset($episode['season_number']) ? (int) $episode['season_number'] : null, isset($episode['episode_number']) ? (int) $episode['episode_number'] : null)) ?>" target="_blank" rel="noreferrer">TPB</a>
                    <a class="button button--ghost" href="<?= e(btdig_episode_search_url((string) $episode['title'], isset($episode['season_number']) ? (int) $episode['season_number'] : null, isset($episode['episode_number']) ? (int) $episode['episode_number'] : null)) ?>" target="_blank" rel="noreferrer">BTDig</a>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach;
};
?>
<section class="section">
    <?php if ($dashboard['recently_aired'] === [] && $dashboard['upcoming'] === []): ?>
        <div class="empty-state">Brak odcinkow w oknie ostatnich i najblizszych 7 dni.</div>
    <?php else: ?>
        <?php if ($dashboard['recently_aired'] !== []): ?>
            <div class="section__subhead">
                <h3>Ostatni tydzien</h3>
            </div>
            <div class="episode-feed">
                <?php $renderEpisodeFeed($dashboard['recently_aired'], 'past'); ?>
            </div>
        <?php endif; ?>

        <?php if ($dashboard['upcoming'] !== []): ?>
            <div class="section__subhead">
                <h3>Nadchodzacy tydzien</h3>
            </div>
            <div class="episode-feed">
                <?php $renderEpisodeFeed($dashboard['upcoming'], 'future'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="stack stack--spaced">
        <details class="disclosure">
            <summary class="disclosure__summary">
                <span>Wyemitowane dawniej niz tydzien temu</span>
                <span class="pill pill--muted"><?= e((string) count($dashboard['older_aired'])) ?></span>
            </summary>
            <?php if ($dashboard['older_aired'] === []): ?>
                <div class="empty-state empty-state--soft">Brak starszych wyemitowanych odcinkow.</div>
            <?php else: ?>
                <div class="episode-feed">
                    <?php $renderEpisodeFeed($dashboard['older_aired'], 'past'); ?>
                </div>
            <?php endif; ?>
        </details>

        <details class="disclosure">
            <summary class="disclosure__summary">
                <span>Nadchodzace w przyszlosci</span>
                <span class="pill pill--muted"><?= e((string) count($dashboard['future_later'])) ?></span>
            </summary>
            <?php if ($dashboard['future_later'] === []): ?>
                <div class="empty-state empty-state--soft">Brak dalszych zapowiedzi.</div>
            <?php else: ?>
                <div class="episode-feed">
                    <?php $renderEpisodeFeed($dashboard['future_later'], 'future'); ?>
                </div>
            <?php endif; ?>
        </details>
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
                    <option value="next" <?= $sort === 'next' ? 'selected' : '' ?>>Najblizszy odcinek</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Alfabetycznie</option>
                    <option value="added" <?= $sort === 'added' ? 'selected' : '' ?>>Data dodania</option>
                </select>
            </label>
            <button type="submit" class="button button--ghost">Zmien</button>
        </form>
    </div>
    <?php if ($dashboard['tracked'] === []): ?>
        <div class="empty-state">Nie obserwujesz jeszcze zadnych seriali. Zacznij od wyszukania tytulu.</div>
    <?php else: ?>
        <div class="show-grid">
            <?php foreach ($dashboard['tracked'] as $item): ?>
                <?php require base_path('views/partials/show_card.php'); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
