<section class="wide">
    <div class="page-head">
        <h1><?= e(t('Meine Quizze')) ?></h1>
        <div class="btn-row">
            <?php if ($mine || $f['active']): ?>
                <a class="btn btn-outline" href="<?= e(url('/quiz/export-all.json')) ?>" download title="<?= e(t('Alle deine Quizze in einer JSON-Datei, mit Bildern')) ?>"><?= e(t('Alle exportieren')) ?></a>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/quiz/new')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ink"><?= e(t('Neues Quiz')) ?></button>
            </form>
        </div>
    </div>

    <?php if ($daysLeft !== null): ?>
        <p class="trial-note<?= $daysLeft <= 7 ? ' soon' : '' ?>">
            <?= e($daysLeft === 0
                ? t('Dein Testzugang läuft heute ab.')
                : t('Dein Testzugang läuft bis {date} (noch {days}).', ['date' => fmt_date((int)$user['expires_at'], false), 'days' => plural($daysLeft, t('Tag'), t('Tage'))])) ?>
            <?= e($daysLeft <= 7 ? t('Wende dich an die Verwaltung, wenn du ihn verlängern möchtest.') : '') ?>
        </p>
    <?php endif; ?>

    <?php if ($running): ?>
        <div class="running">
            <?php foreach ($running as $g): ?>
                <p><?= e(t('Läuft gerade: {title}, PIN {pin}', ['title' => $g['quiz_title'], 'pin' => $g['pin']])) ?>
                    <a class="btn btn-small btn-sun" href="<?= e(url('/host/' . $g['pin'])) ?>"><?= e(t('Zum Spiel')) ?></a></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/dashboard')) ?>" class="quiz-filter" role="search">
        <label class="visually-hidden" for="f-q"><?= e(t('Quizze durchsuchen')) ?></label>
        <input type="search" id="f-q" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('Suchen: Titel, Fragen, Stichwörter …')) ?>" maxlength="100">
        <label class="visually-hidden" for="f-cat"><?= e(t('Kategorie')) ?></label>
        <select id="f-cat" name="cat" data-autosubmit>
            <option value=""><?= e(t('Alle Kategorien')) ?></option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $f['cat'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
            <option value="none" <?= $f['cat'] === 'none' ? 'selected' : '' ?>><?= e(t('Ohne Kategorie')) ?></option>
        </select>
        <?php if ($f['tag'] !== ''): ?><input type="hidden" name="tag" value="<?= e($f['tag']) ?>"><?php endif; ?>
        <button type="submit" class="btn btn-ink"><?= e(t('Suchen')) ?></button>
        <?php if ($f['active']): ?>
            <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>"><?= e(t('Filter zurücksetzen')) ?></a>
        <?php endif; ?>
    </form>
    <?php if ($tagCloud): ?>
        <p class="tag-cloud" aria-label="<?= e(t('Stichwörter')) ?>">
            <?php foreach ($tagCloud as $tg => $n): ?>
                <?php if ($tg === $f['tag']): ?>
                    <a class="tag-link is-active" href="<?= e(quiz_filter_url($f, ['tag' => ''])) ?>" title="<?= e(t('Stichwort-Filter entfernen')) ?>">#<?= e($tg) ?> ×</a>
                <?php else: ?>
                    <a class="tag-link" href="<?= e(quiz_filter_url($f, ['tag' => $tg])) ?>">#<?= e($tg) ?> <small><?= (int)$n ?></small></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <?php if ($f['active']): ?>
        <p class="filter-result" role="status"><?= e(t('{n} Treffer', ['n' => count($mine) + count($public)])) ?></p>
    <?php endif; ?>

    <?php if (!$mine && $f['active']): ?>
        <p class="muted"><?= e(t('Keines deiner Quizze passt zur Suche.')) ?></p>
    <?php elseif (!$mine): ?>
        <div class="empty">
            <p><?= e(t('Du hast noch kein Quiz. Lege eins an oder übernimm unten ein freigegebenes Quiz als Kopie.')) ?></p>
        </div>
    <?php else: ?>
        <ul class="quiz-list">
            <?php foreach ($mine as $q): ?>
                <li class="quiz-row">
                    <div class="quiz-thumb"<?php if ($q['cover_image']): ?> style="background-image:url('<?= e(upload_url($q['cover_image'])) ?>')"<?php endif; ?> aria-hidden="true"></div>
                    <div class="quiz-info">
                        <a class="quiz-title" href="<?= e(url('/quiz/' . $q['id'] . '/edit')) ?>"><?= e($q['title']) ?></a>
                        <p class="meta">
                            <?= e(plural((int)$q['question_count'], t('Frage'), t('Fragen'))) ?>,
                            <?= e(t('geändert {date}', ['date' => fmt_date((int)$q['updated_at'], false)])) ?>
                            <?php if ($q['is_public']): ?><span class="tag tag-share"><?= e(t('für alle')) ?></span><?php else: ?><span class="tag tag-private"><?= e(t('nur ich')) ?></span><?php endif; ?>
                        </p>
                        <?php require APP_DIR . '/views/partials/quiz_tax.php'; ?>
                    </div>
                    <div class="quiz-actions">
                        <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/play')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sun" <?= $q['question_count'] ? '' : 'disabled title="' . e(t('Erst Fragen anlegen')) . '"' ?>><?= e(t('Spiel starten')) ?></button>
                        </form>
                        <a class="btn btn-outline" href="<?= e(url('/quiz/' . $q['id'] . '/edit')) ?>"><?= e(t('Bearbeiten')) ?></a>
                        <details class="more">
                            <summary class="btn btn-ghost" aria-label="<?= e(t('Weitere Aktionen')) ?>">…</summary>
                            <div class="more-menu">
                                <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/visibility')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="visibility" value="<?= $q['is_public'] ? 'private' : 'public' ?>">
                                    <button type="submit" class="linkbtn"><?= e($q['is_public'] ? t('Nur für mich') : t('Für alle freigeben')) ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/duplicate')) ?>">
                                    <?= csrf_field() ?><button type="submit" class="linkbtn"><?= e(t('Duplizieren')) ?></button>
                                </form>
                                <a class="linkbtn" href="<?= e(url('/quiz/' . $q['id'] . '/export.json')) ?>" download><?= e(t('Exportieren (JSON)')) ?></a>
                                <a class="linkbtn" href="<?= e(url('/quiz/' . $q['id'] . '/export.csv')) ?>" download><?= e(t('Exportieren (CSV für Excel)')) ?></a>
                                <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/delete')) ?>" data-confirm="<?= e(t('„{title}“ wirklich löschen?', ['title' => $q['title']])) ?>">
                                    <?= csrf_field() ?><button type="submit" class="linkbtn danger"><?= e(t('Löschen')) ?></button>
                                </form>
                            </div>
                        </details>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 class="section-title"><?= e(t('Von anderen Quizerstellern freigegeben')) ?></h2>
    <?php if (!$public && $f['active']): ?>
        <p class="muted"><?= e(t('Kein freigegebenes Quiz passt zur Suche.')) ?></p>
    <?php elseif (!$public): ?>
        <p class="muted"><?= e(t('Noch niemand hat ein Quiz freigegeben. Deine eigenen gibst du im Editor oder über „…“ für alle frei.')) ?></p>
    <?php else: ?>
        <ul class="quiz-list">
            <?php foreach ($public as $q): ?>
                <li class="quiz-row">
                    <div class="quiz-thumb"<?php if ($q['cover_image']): ?> style="background-image:url('<?= e(upload_url($q['cover_image'])) ?>')"<?php endif; ?> aria-hidden="true"></div>
                    <div class="quiz-info">
                        <span class="quiz-title"><?= e($q['title']) ?></span>
                        <p class="meta"><?= e(plural((int)$q['question_count'], t('Frage'), t('Fragen'))) ?>, <?= e(t('von {name}', ['name' => $q['author']])) ?></p>
                        <?php require APP_DIR . '/views/partials/quiz_tax.php'; ?>
                    </div>
                    <div class="quiz-actions">
                        <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/play')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sun" <?= $q['question_count'] ? '' : 'disabled' ?>><?= e(t('Spiel starten')) ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/quiz/' . $q['id'] . '/duplicate')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline"><?= e(t('Als Kopie übernehmen')) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
