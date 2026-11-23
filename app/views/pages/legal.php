<section class="narrow prose legal">
    <h1><?= e($title) ?></h1>
    <?= $html /* sicher: legal_render() escaped jeden Text */ ?>
    <?php if ($slug === 'impressum'): ?>
        <h2><?= e(t('Software')) ?></h2>
        <p><?= e(t('Naguut {v}, entwickelt von {vendor}.', ['v' => APP_VERSION, 'vendor' => APP_VENDOR])) ?></p>
    <?php endif; ?>
    <?php if ($updated): ?>
        <p class="muted legal-date"><?= e(t('Stand: {date}', ['date' => fmt_date($updated, false)])) ?></p>
    <?php endif; ?>
    <?php if (is_superadmin(current_user())): ?>
        <p><a class="btn btn-small btn-outline" href="<?= e(url('/admin/rechtstexte#' . $slug)) ?>"><?= e(t('Text bearbeiten')) ?></a></p>
    <?php endif; ?>
</section>
