<section class="narrow card-plain">
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <?php if (!empty($link)): ?>
        <p><a class="btn btn-ink" href="<?= e(url($link[0])) ?>"><?= e($link[1]) ?></a></p>
    <?php else: ?>
        <p><a href="<?= e(url('/login')) ?>"><?= e(t('Zur Anmeldung')) ?></a></p>
    <?php endif; ?>
</section>
