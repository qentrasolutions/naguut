<?php /** @var array $q  @var array $f */ ?>
<?php $qtags = tags_load($q['tags'] ?? ''); ?>
<?php if (!empty($q['category_name']) || $qtags): ?>
    <p class="quiz-tax">
        <?php if (!empty($q['category_name'])): ?>
            <a class="cat-badge" href="<?= e(quiz_filter_url($f, ['cat' => (string)$q['category_id']])) ?>"><?= e($q['category_name']) ?></a>
        <?php endif; ?>
        <?php foreach ($qtags as $tg): ?>
            <a class="tag-link<?= $tg === $f['tag'] ? ' is-active' : '' ?>" href="<?= e(quiz_filter_url($f, ['tag' => $tg])) ?>">#<?= e($tg) ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
