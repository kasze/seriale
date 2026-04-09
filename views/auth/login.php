<?php

declare(strict_types=1);

$authPage = true;
?>
<section class="auth-card">
    <div class="auth-card__logo" aria-hidden="true">
        <?php require base_path('views/partials/app_logo.php'); ?>
    </div>
    <div class="auth-card__body">
        <h1>Logowanie</h1>
        <form method="post" action="<?= e(path_url('/login')) ?>" class="stack" autocomplete="on">
            <?= csrf_field() ?>
            <label class="field">
                <span class="field-label">Login</span>
                <input class="input" type="email" name="identity" value="<?= e((string) old('identity', $identity)) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false">
            </label>
            <label class="field">
                <span class="field-label">Haslo</span>
                <input class="input" type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="checkbox-field auth-card__remember">
                <input type="checkbox" name="remember" value="1">
                <span>Zapamiętaj mnie na tym urządzeniu</span>
            </label>
            <button type="submit" class="button button--primary">Wejdz</button>
        </form>

        <form method="post" action="<?= e(path_url('/password/forgot')) ?>" class="inline-form auth-card__footer">
            <?= csrf_field() ?>
            <input type="hidden" name="identity" value="<?= e($identity) ?>">
            <button type="submit" class="button button--ghost">Resetuj haslo</button>
        </form>

        <?php if (is_array($devResetLink ?? null)): ?>
            <div class="code-block auth-card__hint">
                <strong>Link resetu:</strong>
                <a href="<?= e((string) $devResetLink['link']) ?>"><?= e((string) $devResetLink['link']) ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>
