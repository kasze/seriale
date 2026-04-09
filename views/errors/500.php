<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="panel">
        <p class="eyebrow">500</p>
        <h1>Wystąpił błąd aplikacji</h1>
        <p><?= e((string) ($message ?? 'Spróbuj ponownie za chwilę.')) ?></p>
        <a class="button button--primary" href="<?= e(path_url('/dashboard')) ?>">Wróć do pulpitu</a>
    </div>
</section>
