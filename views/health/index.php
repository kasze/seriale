<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="section__head">
        <h1>Stan systemu</h1>
        <p>Prosty ekran kontrolny do shared hostingu.</p>
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
        <h2>Providery</h2>
        <ul class="link-list">
            <li>TVmaze: <?= $health['providers']['tvmaze'] ? 'wlaczony' : 'wylaczony' ?></li>
            <li>TMDb: <?= $health['providers']['tmdb'] ? 'wlaczony' : 'wylaczony' ?></li>
            <li>OMDb: <?= $health['providers']['omdb'] ? 'wlaczony' : 'wylaczony' ?></li>
        </ul>
    </article>
</section>
