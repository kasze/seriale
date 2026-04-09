<?php

declare(strict_types=1);

$bucket = upcoming_bucket($item['next_episode_air_at'] ?? null);
$seasonNumber = isset($item['display_season_number']) && $item['display_season_number'] !== null ? (int) $item['display_season_number'] : null;
$seasonFutureCount = (int) ($item['display_season_future_count'] ?? 0);
$seasonAiredCount = (int) ($item['display_season_aired_count'] ?? 0);
$seasonStatusLabel = null;

if ($seasonNumber !== null && $seasonNumber > 0) {
    if ($seasonFutureCount > 0 && $seasonAiredCount > 0) {
        $seasonStatusLabel = sprintf('Sezon %d w emisji', $seasonNumber);
    } elseif ($seasonFutureCount > 0) {
        $seasonStatusLabel = sprintf('Sezon %d nadchodzi', $seasonNumber);
    } else {
        $seasonStatusLabel = sprintf('Sezon %d zakończony', $seasonNumber);
    }
}

$isEnded = mb_strtolower(trim((string) ($item['status'] ?? ''))) === 'ended';
$showStatusClass = $isEnded ? 'pill--ended' : 'pill--airing';

if ($seasonStatusLabel !== null) {
    if (str_contains($seasonStatusLabel, 'w emisji')) {
        $seasonStatusClass = 'pill--airing';
    } elseif (str_contains($seasonStatusLabel, 'nadchodzi')) {
        $seasonStatusClass = 'pill--upcoming';
    } else {
        $seasonStatusClass = 'pill--ended';
    }
}
?>
<article class="show-card show-card--<?= e($bucket) ?> <?= $isEnded ? 'show-card--ended' : '' ?>">
    <div class="show-card__poster">
        <?php if (!empty($item['poster_url'])): ?>
            <img src="<?= e((string) $item['poster_url']) ?>" alt="<?= e((string) $item['title']) ?>">
        <?php else: ?>
            <div class="show-card__placeholder"><?= e(mb_substr((string) $item['title'], 0, 1)) ?></div>
        <?php endif; ?>
    </div>
    <div class="show-card__body">
        <div class="show-card__head">
            <div>
                <h3 class="show-card__title">
                    <a href="<?= e(path_url('/shows/' . (string) $item['id'])) ?>"><?= e((string) $item['title']) ?></a>
                </h3>
                <p class="show-card__meta">
                    <span class="pill <?= e($showStatusClass) ?>"><?= e(translate_show_status((string) ($item['status'] ?? ''))) ?></span>
                    <?php if ($seasonStatusLabel !== null): ?>
                        <span class="pill <?= e($seasonStatusClass) ?>"><?= e($seasonStatusLabel) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <dl class="meta-list">
            <div>
                <dt>Ostatni</dt>
                <dd><?= e((string) ($item['last_episode_label'] ?? 'Brak')) ?></dd>
            </div>
            <div>
                <dt>Następny</dt>
                <dd><?= e((string) ($item['next_episode_label'] ?? 'Brak zapowiedzi')) ?></dd>
            </div>
            <div>
                <dt>Emisja</dt>
                <dd><?= e(format_date($item['next_episode_air_at'] ?? null, false)) ?></dd>
            </div>
            <div>
                <dt>Odliczanie</dt>
                <dd>
                    <span data-countdown data-at="<?= e((string) ($item['next_episode_air_at'] ?? '')) ?>">
                        <?= e(countdown_label($item['next_episode_air_at'] ?? null)) ?>
                    </span>
                </dd>
            </div>
        </dl>
        <div class="show-card__actions">
            <a class="button" href="<?= e(path_url('/shows/' . (string) $item['id'])) ?>">Szczegóły</a>
            <form method="post" action="<?= e(path_url('/shows/' . (string) $item['id'] . '/untrack')) ?>" class="inline-form" onsubmit="return confirm('Usunąć ten serial z obserwowanych?')">
                <?= csrf_field() ?>
                <button type="submit" class="button button--ghost" onclick="return confirm('Usunąć ten serial z obserwowanych?')">Usuń</button>
            </form>
        </div>
    </div>
</article>
