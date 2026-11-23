<?php if (!empty($list)): ?>
<div class="form-errors" role="alert">
    <?php if (count($list) === 1): ?>
        <p><?= e($list[0]) ?></p>
    <?php else: ?>
        <ul><?php foreach ($list as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
</div>
<?php endif; ?>
