<?php
$symbols = ['▲', '◆', '●', '■'];
$playerCount = count($data['players']);
?>
<section class="wide report">
    <div class="page-head">
        <div>
            <p class="crumb"><a href="<?= e(url('/games')) ?>"><?= e(t('Spielverlauf')) ?></a></p>
            <h1><?= e($game['quiz_title']) ?></h1>
            <p class="meta"><?= e(fmt_date((int)$game['created_at'])) ?>, PIN <?= e($game['pin']) ?>
                <?php if ($game['status'] !== 'finished'): ?><a class="tag tag-live" href="<?= e(url('/host/' . $game['pin'])) ?>"><?= e(t('läuft noch')) ?></a><?php endif; ?></p>
        </div>
        <a class="btn btn-ink" href="<?= e(url('/games/' . $game['id'] . '/report.csv')) ?>"><?= e(t('Als CSV herunterladen')) ?></a>
    </div>

    <div class="figures">
        <p><strong><?= $playerCount ?></strong> <?= e($playerCount === 1 ? t('Person hat mitgespielt') : t('Personen haben mitgespielt')) ?></p>
        <p><strong><?= (int)$data['played'] ?></strong> <?= e(t('von {n} Fragen gespielt', ['n' => count($data['questions'])])) ?><?php if ($data['skipped']): ?>, <?= e(plural(count($data['skipped']), t('übersprungen'), t('übersprungen'))) ?><?php endif; ?></p>
        <p><strong><?= (int)$data['avg_rate'] ?> %</strong> <?= e(t('der Antworten waren richtig')) ?></p>
    </div>

    <h2 class="section-title"><?= e(t('Fragen')) ?></h2>
    <p class="muted"><?= e(t('Sortiert nach Reihenfolge. Rot markiert sind Fragen, die weniger als die Hälfte richtig hatte.')) ?></p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th scope="col" class="num">#</th>
                <th scope="col"><?= e(t('Frage')) ?></th>
                <th scope="col"><?= e(t('Richtig')) ?></th>
                <th scope="col"><?= e(t('Verteilung')) ?></th>
                <th scope="col" class="num"><?= e(t('Ø Zeit')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($data['question_rows'] as $qr): $q = $qr['question']; ?>
                <tr class="<?= $qr['played'] ? ($qr['rate'] < 50 ? 'weak' : '') : 'unplayed' ?>">
                    <td class="num"><?= (int)$qr['no'] ?></td>
                    <td><?= rich_inline($q['text']) ?>
                        <?php if ($qr['skipped']): ?><span class="tag"><?= e(t('übersprungen')) ?></span><?php elseif (!$qr['played']): ?><span class="tag"><?= e(t('nicht gespielt')) ?></span><?php endif; ?></td>
                    <td>
                        <div class="rate"><span class="rate-bar" style="width: <?= (int)$qr['rate'] ?>%"></span></div>
                        <span class="rate-num"><?= (int)$qr['rate'] ?> %</span>
                    </td>
                    <td class="dist">
                        <?php if ($q['type'] === 'txt'): ?>
                            <span class="dist-est"><?= e(t('richtig: {v}', ['v' => implode(' / ', array_column($q['answers'], 'text'))])) ?></span>
                        <?php elseif ($q['type'] === 'ord'): ?>
                            <span class="dist-est"><?= implode(' → ', array_map(fn($a) => rich_inline($a['text']), $q['answers'])) ?></span>
                        <?php elseif ($q['type'] === 'map' && !empty($q['map'])): ?>
                            <span class="dist-est"><?= e(t('Ziel: {v}', ['v' => ($q['map']['label'] ?? '') !== '' ? $q['map']['label'] : $q['map']['lat'] . ', ' . $q['map']['lng']])) ?><?php if ($qr['avg_value'] !== null): ?>, <?= e(t('Ø {v}', ['v' => map_format_km($qr['avg_value'])])) ?><?php endif; ?></span>
                        <?php endif; ?>
                        <?php if ($q['type'] === 'est' && !empty($q['estimate'])): ?>
                            <span class="dist-est"><?= e(t('richtig: {v}', ['v' => est_format($q['estimate']['correct'], $q['estimate'])])) ?><?php if ($qr['avg_value'] !== null): ?>, <?= e(t('Ø {v}', ['v' => est_format($qr['avg_value'], $q['estimate'])])) ?><?php endif; ?></span>
                        <?php endif; ?>
                        <?php foreach (in_array($q['type'], ['mc', 'tf'], true) ? $q['answers'] : [] as $i => $a): ?>
                            <span class="dist-item a<?= $i ?><?= $a['is_correct'] ? ' is-correct' : '' ?>" title="<?= e(rich_plain($a['text'])) ?>">
                                <span aria-hidden="true"><?= $symbols[$i] ?></span> <?= (int)($qr['dist'][$i] ?? 0) ?><?php if ($a['is_correct']): ?><span class="visually-hidden"> (<?= e(t('richtig')) ?>)</span><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td class="num"><?= $qr['avg_time'] !== null ? e(number_format($qr['avg_time'], 1, ',', '')) . ' s' : '–' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="section-title"><?= e(t('Mitspielende')) ?></h2>
    <?php if (!$data['players']): ?>
        <p class="muted"><?= e(t('Niemand hat mitgespielt.')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-grid">
            <thead><tr>
                <th scope="col" class="num"><?= e(t('Platz')) ?></th>
                <th scope="col"><?= e(t('Nickname')) ?></th>
                <th scope="col" class="num"><?= e(t('Punkte')) ?></th>
                <th scope="col" class="num"><?= e(t('Richtig')) ?></th>
                <?php foreach ($data['questions'] as $i => $q): ?>
                    <th scope="col" class="num q-col" title="<?= e(rich_plain($q['text'])) ?>"><?= $i + 1 ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($data['players'] as $row): ?>
                <tr>
                    <td class="num"><?= (int)$row['rank'] ?></td>
                    <td><?= e($row['player']['nickname']) ?></td>
                    <td class="num"><?= number_format((int)$row['player']['score'], 0, ',', '.') ?></td>
                    <td class="num"><?= (int)$row['correct'] ?></td>
                    <?php foreach ($data['questions'] as $i => $q): $r = $row['responses'][$i] ?? null; ?>
                        <?php if (isset($data['skipped'][$i])): ?><td class="num mark mark-none"><span aria-hidden="true">·</span><span class="visually-hidden"><?= e(t('übersprungen')) ?></span></td><?php continue; endif; ?>
                        <td class="num mark <?= $r ? ($r['is_correct'] ? 'mark-ok' : 'mark-bad') : 'mark-none' ?>">
                            <?php if ($r): ?><span aria-hidden="true"><?= $r['is_correct'] ? '✓' : '✗' ?></span><span class="visually-hidden"><?= e($r['is_correct'] ? t('richtig') : t('falsch')) ?></span><?php else: ?><span aria-hidden="true">–</span><span class="visually-hidden"><?= e(t('keine Antwort')) ?></span><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
