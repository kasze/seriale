<?php

declare(strict_types=1);

$widgetId = $widgetId ?? uniqid('search-widget-', false);
$label = $label ?? '';
$placeholder = $placeholder ?? 'Wpisz tytuł';
$compact = (bool) ($compact ?? true);
?>
<section class="search-widget <?= $compact ? 'search-widget--compact' : '' ?>" data-search-widget data-endpoint="<?= e(path_url('/shows/search')) ?>">
    <?php if ($label !== ''): ?>
        <label class="field-label" for="<?= e($widgetId) ?>"><?= e($label) ?></label>
    <?php endif; ?>
    <div class="search-widget__bar">
        <input
            id="<?= e($widgetId) ?>"
            name="q"
            type="search"
            class="input"
            placeholder="<?= e($placeholder) ?>"
            autocomplete="off"
            spellcheck="false"
            data-search-input
        >
        <a href="<?= e(path_url('/shows/search')) ?>" class="button button--ghost search-widget__link">Szukaj</a>
    </div>
    <div class="search-results" data-search-results hidden></div>
    <form method="post" action="<?= e(path_url('/tracked')) ?>" data-track-form hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="provider" value="tvmaze" data-track-provider>
        <input type="hidden" name="source_id" value="" data-track-source-id>
    </form>
</section>
