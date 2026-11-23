<section class="narrow card-plain">
    <p class="error-code"><?= (int)$code ?></p>
    <h1><?= e($message) ?></h1>
    <p><a class="btn btn-ink" href="<?= e(url('/')) ?>"><?= e(t('Zur Startseite')) ?></a></p>
</section>
