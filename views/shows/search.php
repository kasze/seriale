<?php

declare(strict_types=1);

$widgetId = 'search-page-widget';
$label = 'Szukaj w TVmaze';
$placeholder = 'Wpisz tytuł, np. Severance, The Last of Us...';
$compact = false;
?>
<section class="section">
    <div class="section__head">
        <h1>Dodawanie seriali</h1>
        <p>Wpisz tytuł i wybierz serial z listy wyników. Kliknięcie wyniku od razu dodaje serial do obserwowanych.</p>
    </div>
    <?php require base_path('views/partials/search_widget.php'); ?>
</section>

<section class="section">
    <div class="section__head">
        <h2>Wyniki wyszukiwania</h2>
        <p><?= $query !== '' ? 'Zapytanie: ' . e($query) : 'Wpisz co najmniej 2 znaki, aby zacząć.' ?></p>
    </div>

    <?php if ($query !== '' && $results === []): ?>
        <div class="empty-state">Brak wyników dla tego zapytania.</div>
    <?php elseif ($results !== []): ?>
        <div class="search-page-results">
            <?php foreach ($results as $result): ?>
                <article class="search-result-card">
                    <div class="search-result-card__poster">
                        <?php if (!empty($result['poster_url'])): ?>
                            <img src="<?= e((string) $result['poster_url']) ?>" alt="<?= e((string) $result['title']) ?>">
                        <?php else: ?>
                            <div class="show-card__placeholder"><?= e(mb_substr((string) $result['title'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="search-result-card__body">
                        <h3><?= e((string) $result['title']) ?></h3>
                        <p class="muted"><?= e(trim(($result['year'] ? $result['year'] . ' · ' : '') . ($result['country'] ?? $result['network'] ?? 'Brak danych o kraju/platformie'))) ?></p>
                        <p><?= e(truncate_text((string) ($result['summary'] ?? ''), 220)) ?></p>
                        <form method="post" action="<?= e(path_url('/tracked')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="provider" value="<?= e((string) $result['provider']) ?>">
                            <input type="hidden" name="source_id" value="<?= e((string) $result['source_id']) ?>">
                            <button type="submit" class="button button--primary">Dodaj do obserwowanych</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
