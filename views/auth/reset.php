<?php

declare(strict_types=1);

$authPage = true;
?>
<section class="auth-card">
    <div class="auth-card__logo" aria-hidden="true">
        <?php require base_path('views/partials/app_logo.php'); ?>
    </div>
    <div class="auth-card__body">
        <h1>Ustaw nowe haslo</h1>
        <p class="muted"><?= e($identity) ?></p>
        <form method="post" action="<?= e(path_url('/password/reset')) ?>" class="stack" autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="identity" value="<?= e($identity) ?>" autocomplete="username">
            <label class="field">
                <span class="field-label">Nowe haslo</span>
                <input class="input" type="password" name="password" required autocomplete="new-password">
            </label>
            <label class="field">
                <span class="field-label">Powtorz haslo</span>
                <input class="input" type="password" name="password_confirm" required autocomplete="new-password">
            </label>
            <button type="submit" class="button button--primary">Zapisz haslo</button>
        </form>
    </div>
</section>
