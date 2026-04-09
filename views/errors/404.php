<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="panel">
        <p class="eyebrow">404</p>
        <h1>Nie znaleziono strony</h1>
        <p><?= e((string) ($message ?? 'Podany adres nie istnieje.')) ?></p>
        <a class="button button--primary" href="<?= e(path_url('/dashboard')) ?>">Wroc do dashboardu</a>
    </div>
</section>
