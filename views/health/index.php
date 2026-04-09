<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="section__head">
        <h1>Stan systemu</h1>
        <p>Krótki test aplikacji, bazy danych i włączonych integracji.</p>
    </div>
    <div class="mini-grid">
        <article class="mini-card">
            <strong>Aplikacja</strong>
            <span><?= e((string) $health['app']) ?></span>
        </article>
        <article class="mini-card">
            <strong>Tryb</strong>
            <span><?= e(translate_app_env((string) $health['env'])) ?></span>
        </article>
        <article class="mini-card">
            <strong>Strefa czasowa</strong>
            <span><?= e((string) $health['timezone']) ?></span>
        </article>
        <article class="mini-card">
            <strong>Baza danych</strong>
            <span><?= e(translate_health_status((string) $health['db'])) ?></span>
        </article>
    </div>
    <article class="panel">
        <h2>Integracje</h2>
        <ul class="link-list">
            <li>TVmaze: <?= $health['providers']['tvmaze'] ? 'włączony' : 'wyłączony' ?></li>
            <li>TMDb: <?= $health['providers']['tmdb'] ? 'włączony' : 'wyłączony' ?></li>
            <li>OMDb: <?= $health['providers']['omdb'] ? 'włączony' : 'wyłączony' ?></li>
        </ul>
    </article>
</section>
