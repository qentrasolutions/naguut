<section class="wide">
    <div class="page-head">
        <h1><?= e(t('Spielverlauf')) ?></h1>
    </div>
    <p class="muted"><?= e(t('Spiele werden nach {n} Tagen mit allen Nicknames und Antworten automatisch gelöscht.', ['n' => $retention])) ?></p>
    <?php if (!$games): ?>
        <div class="empty"><p><?= e(t('Noch keine Spiele. Starte eins unter „Quizze“.')) ?></p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th scope="col"><?= e(t('Datum')) ?></th>
                    <th scope="col"><?= e(t('Quiz')) ?></th>
                    <th scope="col" class="num"><?= e(t('Mitspielende')) ?></th>
                    <th scope="col"><?= e(t('Status')) ?></th>
                    <th scope="col"><span class="visually-hidden"><?= e(t('Aktionen')) ?></span></th>
                </tr></thead>
                <tbody>
                <?php foreach ($games as $g): ?>
                    <tr>
                        <td><?= e(fmt_date((int)$g['created_at'])) ?></td>
                        <td><a href="<?= e(url('/games/' . $g['id'])) ?>"><?= e($g['quiz_title']) ?></a></td>
                        <td class="num"><?= (int)$g['player_count'] ?></td>
                        <td><?php if ($g['status'] === 'finished'): ?><?= e(t('beendet')) ?><?php else: ?>
                            <a class="tag tag-live" href="<?= e(url('/host/' . $g['pin'])) ?>"><?= e(t('läuft, PIN {pin}', ['pin' => $g['pin']])) ?></a><?php endif; ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/games/' . $g['id'] . '/report.csv')) ?>"><?= e(t('CSV')) ?></a>
                            <form method="post" action="<?= e(url('/games/' . $g['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('Spiel mit allen Ergebnissen löschen?')) ?>">
                                <?= csrf_field() ?><button type="submit" class="linkbtn danger"><?= e(t('Löschen')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
