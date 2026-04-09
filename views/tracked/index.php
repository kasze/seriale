<?php

declare(strict_types=1);
?>
<section class="section">
    <div class="section__head section__head--split">
        <div>
            <h1>Obserwowane seriale</h1>
            <p>Pelna lista z sortowaniem po dacie kolejnego odcinka, alfabetycznie albo dacie dodania.</p>
        </div>
        <form method="get" action="<?= e(path_url('/tracked')) ?>" class="inline-form">
            <label class="field field--inline">
                <span class="field-label">Sortuj</span>
                <select class="input" name="sort">
                    <option value="next" <?= $sort === 'next' ? 'selected' : '' ?>>Najblizszy odcinek</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Alfabetycznie</option>
                    <option value="added" <?= $sort === 'added' ? 'selected' : '' ?>>Data dodania</option>
                </select>
            </label>
            <button type="submit" class="button button--ghost">Zmien</button>
        </form>
    </div>
    <?php if ($items === []): ?>
        <div class="empty-state">Lista obserwowanych jest pusta.</div>
    <?php else: ?>
        <div class="show-grid">
            <?php foreach ($items as $item): ?>
                <?php require base_path('views/partials/show_card.php'); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
