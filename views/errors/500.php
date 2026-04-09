<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="panel">
        <p class="eyebrow">500</p>
        <h1>Wystapil blad aplikacji</h1>
        <p><?= e((string) ($message ?? 'Sprobuj ponownie za chwile.')) ?></p>
        <a class="button button--primary" href="<?= e(path_url('/dashboard')) ?>">Wroc do dashboardu</a>
    </div>
</section>
