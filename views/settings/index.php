<?php

declare(strict_types=1);

$groupedDefinitions = [];

foreach (array_keys($groups) as $groupKey) {
    $groupedDefinitions[$groupKey] = [];
}

foreach ($definitions as $definition) {
    $groupedDefinitions[$definition['group']][] = $definition;
}

$groupedDefinitions = array_filter(
    $groupedDefinitions,
    static fn (array $items): bool => $items !== []
);
?>
<section class="section">
    <div class="section__head">
        <h1>Ustawienia</h1>
        <p>Najważniejsze opcje aplikacji, konta, synchronizacji i integracji.</p>
    </div>
    <div class="settings-layout">
        <form method="post" action="<?= e(path_url('/settings')) ?>" class="settings-form">
            <?= csrf_field() ?>
            <?php foreach ($groupedDefinitions as $groupKey => $items): ?>
                <?php $group = $groups[$groupKey] ?? ['label' => ucfirst($groupKey), 'description' => '']; ?>
                <article class="panel settings-section">
                    <div class="settings-section__head">
                        <div>
                            <h2><?= e($group['label']) ?></h2>
                            <?php if (($group['description'] ?? '') !== ''): ?>
                                <p><?= e((string) $group['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="settings-grid">
                        <?php foreach ($items as $definition): ?>
                            <?php
                            $value = $settings[$definition['key']] ?? null;
                            $inputType = $definition['input'] ?? ($definition['type'] === 'int' ? 'number' : 'text');
                            $help = (string) ($definition['help'] ?? '');
                            ?>
                            <label class="field settings-field <?= $definition['type'] === 'bool' ? 'field--checkbox settings-field--checkbox' : '' ?>">
                                <span class="settings-field__top">
                                    <span class="field-label"><?= e($definition['label']) ?></span>
                                    <?php if ($help !== ''): ?>
                                        <span class="settings-help" tabindex="0" aria-label="<?= e($help) ?>" data-tooltip="<?= e($help) ?>">?</span>
                                    <?php endif; ?>
                                </span>

                                <?php if ($inputType === 'select'): ?>
                                    <select class="input" name="<?= e($definition['key']) ?>">
                                        <?php foreach (($definition['options'] ?? []) as $option): ?>
                                            <option value="<?= e((string) $option['value']) ?>" <?= (string) $value === (string) $option['value'] ? 'selected' : '' ?>>
                                                <?= e((string) $option['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($definition['type'] === 'bool'): ?>
                                    <input type="checkbox" name="<?= e($definition['key']) ?>" value="1" <?= (bool) $value ? 'checked' : '' ?>>
                                <?php else: ?>
                                    <input
                                        class="input"
                                        type="<?= e($inputType) ?>"
                                        name="<?= e($definition['key']) ?>"
                                        value="<?= e((string) $value) ?>"
                                        <?= isset($definition['placeholder']) ? 'placeholder="' . e((string) $definition['placeholder']) . '"' : '' ?>
                                        <?= isset($definition['min']) ? 'min="' . e((string) $definition['min']) . '"' : '' ?>
                                    >
                                <?php endif; ?>

                                <?php if ($help !== ''): ?>
                                    <span class="settings-field__help"><?= e($help) ?></span>
                                <?php endif; ?>

                                <?php if (isset($definition['suffix']) && $definition['type'] !== 'bool'): ?>
                                    <span class="settings-field__suffix"><?= e((string) $definition['suffix']) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="section__actions">
                <button type="submit" class="button button--primary">Zapisz ustawienia</button>
            </div>
        </form>

        <aside class="settings-side">
            <article class="panel panel--subtle settings-note">
                <h2>Szybkie akcje</h2>
                <p>Ręczne odświeżenie pobiera najnowsze odcinki dla całej listy obserwowanych. Wylogowanie kończy tylko bieżącą sesję w tej przeglądarce.</p>
                <form method="post" action="<?= e(path_url('/settings')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="refresh_tracked">
                    <button type="submit" class="button button--ghost">Odśwież obserwowane seriale</button>
                </form>
                <form method="post" action="<?= e(path_url('/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--ghost">Wyloguj</button>
                </form>
            </article>
        </aside>
    </div>
</section>
