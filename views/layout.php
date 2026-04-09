<?php

declare(strict_types=1);

$flashSuccess = flash('success');
$flashError = flash('error');
$user = current_user();
$isAuthPage = (bool) ($authPage ?? false);
$settingsService = app(App\Services\AppSettingsService::class);
$appName = e($settingsService->get('app_name', 'Seriale'));
$theme = $settingsService->theme();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' https: data:; style-src 'self'; script-src 'self'; connect-src 'self' https://api.tvmaze.com https://api.themoviedb.org https://www.omdbapi.com; base-uri 'self'; form-action 'self'">
    <title><?= e(($pageTitle ?? 'Seriale') . ' · ' . html_entity_decode($appName, ENT_QUOTES, 'UTF-8')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="<?= $isAuthPage ? 'auth-body' : 'app-body' ?> theme--<?= e($theme) ?>">
<?php if (!$isAuthPage): ?>
    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="<?= e(path_url('/dashboard')) ?>">
                <span class="brand__mark" aria-hidden="true">
                    <?php require base_path('views/partials/app_logo.php'); ?>
                </span>
                <span class="brand__text"><?= $appName ?></span>
            </a>
            <nav class="nav">
                <a href="<?= e(path_url('/top')) ?>" class="nav__link">Topki</a>
                <a href="<?= e(path_url('/tracked')) ?>" class="nav__link">Obserwowane</a>
                <a href="<?= e(path_url('/settings')) ?>" class="nav__link">Ustawienia</a>
            </nav>
            <div class="topbar__actions">
                <?php require base_path('views/partials/search_widget.php'); ?>
            </div>
        </div>
    </header>
<?php endif; ?>

<main class="<?= $isAuthPage ? 'auth-shell' : 'page-shell' ?>">
    <?php if ($flashSuccess !== null): ?>
        <div class="flash flash--success"><?= e((string) $flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError !== null): ?>
        <div class="flash flash--error"><?= e((string) $flashError) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>

<?php if (!$isAuthPage): ?>
    <footer class="footer">
        <div class="footer__inner">
            <p>&copy; 2026 Kamil Szewczyk</p>
            <?php if ($user !== null): ?>
                <p>Zalogowano jako <?= e((string) ($user['identity'] ?? 'single-user')) ?></p>
            <?php endif; ?>
        </div>
    </footer>
<?php endif; ?>
</body>
</html>
