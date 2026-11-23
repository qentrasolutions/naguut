<?php $scripts = ['js/map.js', 'js/admin-map.js']; ?>
<section class="wide">
    <div class="page-head">
        <h1><?= e(t('Verwaltung')) ?></h1>
        <?php if (is_superadmin($admin)): ?>
            <a class="btn btn-outline" href="<?= e(url('/admin/rechtstexte')) ?>"><?= e(t('Rechtstexte bearbeiten')) ?></a>
        <?php endif; ?>
    </div>

    <div class="figures">
        <p><strong><?= count($users) ?></strong> <?= e(t('Konten')) ?></p>
        <p><strong><?= (int)$stats['games_running'] ?></strong> <?= e(t('Spiele laufen gerade')) ?></p>
        <p><strong><?= (int)$stats['games_total'] ?></strong> <?= e(t('Spiele gespeichert')) ?></p>
    </div>

    <?php if ($pending): ?>
        <h2 class="section-title" id="freischaltung"><?= e(t('Wartet auf Freischaltung')) ?> <span class="count-badge"><?= count($pending) ?></span></h2>
        <div class="panel pending-panel">
            <?php if (!is_superadmin($admin)): ?>
                <p class="muted"><?= e(t('Neue Konten schalten nur Super-Admins frei.')) ?></p>
            <?php endif; ?>
            <ul class="pending-list">
                <?php foreach ($pending as $u): ?>
                    <li class="pending-row">
                        <div class="pending-who">
                            <strong><?= e($u['display_name']) ?></strong>
                            <span><?= e($u['email']) ?></span>
                            <small class="muted"><?= e(t('registriert am {date}', ['date' => fmt_date((int)$u['created_at'])])) ?></small>
                        </div>
                        <?php if (is_superadmin($admin)): ?>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/approve')) ?>" class="pending-approve">
                                <?= csrf_field() ?>
                                <label class="field">
                                    <span><?= e(t('Zugang bis')) ?></span>
                                    <input type="date" name="expires_at" value="<?= e(ymd($defaultExpiry)) ?>" min="<?= e(ymd(time())) ?>">
                                    <small><?= e(t('Leer lassen für unbefristet')) ?></small>
                                </label>
                                <button type="submit" class="btn btn-ink"><?= e(t('Freischalten')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/reject')) ?>"
                                  data-confirm="<?= e(t('Konto {email} ablehnen und löschen? Die Person bekommt eine kurze E-Mail.', ['email' => $u['email']])) ?>">
                                <?= csrf_field() ?><button type="submit" class="linkbtn danger"><?= e(t('Ablehnen')) ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (is_superadmin($admin)): ?>
        <h2 class="section-title" id="neue-konten"><?= e(t('Neue Konten')) ?></h2>
        <form method="post" action="<?= e(url('/admin/accounts')) ?>" class="panel accounts-settings">
            <?= csrf_field() ?>
            <label class="check">
                <input type="checkbox" name="approval_required" value="1" <?= $approvalRequired ? 'checked' : '' ?>>
                <span><?= e(t('Neue Konten müssen freigeschaltet werden')) ?><small><?= e(t('Nach der Registrierung und der Bestätigung der E-Mail-Adresse kann sich niemand anmelden, bis ein Super-Admin das Konto freischaltet. Super-Admins bekommen dazu eine E-Mail.')) ?></small></span>
            </label>
            <label class="field trial-field">
                <span><?= e(t('Testzeitraum für neue Konten')) ?></span>
                <span class="input-unit"><input type="number" name="trial_days" min="0" max="<?= TRIAL_DAYS_MAX ?>" step="1" value="<?= (int)$trialDays ?>" inputmode="numeric"> <?= e(t('Tage')) ?></span>
                <small><?= e(t('0 = unbefristet. Das Ablaufdatum wird bei der Freischaltung vorgeschlagen und lässt sich pro Konto ändern. Es gilt nur für Quizersteller, nicht für Admins.')) ?></small>
            </label>
            <button type="submit" class="btn btn-ink"><?= e(t('Speichern')) ?></button>
        </form>
    <?php endif; ?>

    <h2 class="section-title" id="musik"><?= e(t('Standard-Musik für alle Spiele')) ?></h2>
    <div class="panel music-admin">
        <p><?= e(t('Läuft am Beamer in jedem Spiel, dessen Quiz keine eigene Musik hat. Registrierte Quizersteller können sie dort jederzeit stumm schalten.')) ?></p>
        <?php if ($defaultMusic): ?>
            <audio controls preload="none" src="<?= e(upload_url($defaultMusic)) ?>"></audio>
        <?php else: ?>
            <p class="muted"><?= e(t('Derzeit ist keine Standard-Musik festgelegt.')) ?></p>
        <?php endif; ?>
        <div class="btn-row">
            <form method="post" action="<?= e(url('/admin/music')) ?>" enctype="multipart/form-data" class="upload-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span><?= e($defaultMusic ? t('Andere Datei hochladen') : t('Datei hochladen')) ?></span>
                    <input type="file" name="sound" accept="audio/mpeg,audio/ogg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,.mp3,.ogg,.oga,.m4a,.wav" required>
                    <small><?= e(t('MP3, OGG, M4A oder WAV, höchstens {n} MB.', ['n' => (int)cfg('max_sound_mb', 10)])) ?></small>
                </label>
                <button type="submit" class="btn btn-ink"><?= e(t('Hochladen')) ?></button>
            </form>
            <?php if ($defaultMusic): ?>
                <form method="post" action="<?= e(url('/admin/music/delete')) ?>" data-confirm="<?= e(t('Standard-Musik entfernen?')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline"><?= e(t('Standard-Musik entfernen')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title" id="kategorien"><?= e(t('Kategorien für Quizze')) ?></h2>
    <div class="panel cat-admin">
        <p class="muted"><?= e(t('Quizersteller ordnen ihre Quizze im Editor einer dieser Kategorien zu. In der Quizliste kann man danach filtern.')) ?></p>
        <?php if (!$categories): ?>
            <p><?= e(t('Noch keine Kategorien. Lege unten die erste an, z. B. „Geografie“ oder „Mathematik“.')) ?></p>
        <?php else: ?>
            <ul class="cat-list">
                <?php foreach ($categories as $ci => $c): ?>
                    <li class="cat-row">
                        <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/rename')) ?>" class="cat-rename">
                            <?= csrf_field() ?>
                            <label class="visually-hidden" for="cat-<?= $c['id'] ?>"><?= e(t('Name der Kategorie')) ?></label>
                            <input id="cat-<?= $c['id'] ?>" name="name" value="<?= e($c['name']) ?>" maxlength="<?= CATEGORY_MAX_LEN ?>" required>
                            <button type="submit" class="btn btn-small btn-outline"><?= e(t('Umbenennen')) ?></button>
                        </form>
                        <span class="muted cat-count"><?= e(plural($c['quiz_count'], t('Quiz'), t('Quizze'))) ?></span>
                        <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/move')) ?>" class="cat-move">
                            <?= csrf_field() ?>
                            <button type="submit" name="dir" value="up" class="iconbtn" <?= $ci === 0 ? 'disabled' : '' ?> aria-label="<?= e(t('Nach oben')) ?>" title="<?= e(t('Nach oben')) ?>">↑</button>
                            <button type="submit" name="dir" value="down" class="iconbtn" <?= $ci === count($categories) - 1 ? 'disabled' : '' ?> aria-label="<?= e(t('Nach unten')) ?>" title="<?= e(t('Nach unten')) ?>">↓</button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/delete')) ?>" data-confirm="<?= e(t('Kategorie „{name}“ löschen? Die Quizze bleiben erhalten, nur ohne Kategorie.', ['name' => $c['name']])) ?>">
                            <?= csrf_field() ?><button type="submit" class="linkbtn danger"><?= e(t('Löschen')) ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/categories')) ?>" class="cat-add">
            <?= csrf_field() ?>
            <label class="field"><span><?= e(t('Neue Kategorie')) ?></span><input name="name" maxlength="<?= CATEGORY_MAX_LEN ?>" required placeholder="<?= e(t('z. B. Geografie')) ?>"></label>
            <button type="submit" class="btn btn-ink"><?= e(t('Kategorie anlegen')) ?></button>
        </form>
    </div>

    <h2 class="section-title" id="karte"><?= e(t('Kartenausschnitt für Kartenfragen')) ?></h2>
    <div class="panel map-admin">
        <p><?= e(t('Mit diesem Ausschnitt starten alle Kartenfragen, außer eine Frage hat einen eigenen. Verschiebe und zoome die Karte und speichere dann.')) ?></p>
        <div class="map-admin-box" id="admin-map" data-view="<?= e(json_encode(map_default_view())) ?>" data-tiles="<?= e(json_encode(map_tiles())) ?>"></div>
        <form method="post" action="<?= e(url('/admin/map')) ?>" class="btn-row" id="admin-map-form">
            <?= csrf_field() ?>
            <input type="hidden" name="lat"><input type="hidden" name="lng"><input type="hidden" name="zoom">
            <button type="submit" name="action" value="save" class="btn btn-ink"><?= e(t('Aktuellen Ausschnitt speichern')) ?></button>
            <?php if ($mapCustom): ?>
                <button type="submit" name="action" value="reset" class="btn btn-ghost"><?= e(t('Zurück auf Österreich')) ?></button>
            <?php endif; ?>
        </form>
    </div>

    <h2 class="section-title" id="konten"><?= e(t('Konten')) ?></h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th scope="col"><?= e(t('Name')) ?></th>
                <th scope="col"><?= e(t('E-Mail')) ?></th>
                <th scope="col"><?= e(t('Rolle')) ?></th>
                <th scope="col"><?= e(t('Status')) ?></th>
                <th scope="col"><?= e(t('Zugang bis')) ?></th>
                <th scope="col" class="num"><?= e(t('Quizze')) ?></th>
                <th scope="col"><?= e(t('Angelegt')) ?></th>
                <th scope="col"><span class="visually-hidden"><?= e(t('Aktionen')) ?></span></th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$admin['id']; ?>
                <tr>
                    <td><?= e($u['display_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?php if ($u['role'] === 'superadmin'): ?><span class="tag tag-live"><?= e(t('Super-Admin')) ?></span><?php else: ?><?= e(t(ROLES[$u['role']] ?? $u['role'])) ?><?php endif; ?></td>
                    <td>
                        <?php switch (account_status($u)):
                            case 'locked': ?><span class="tag tag-bad"><?= e(t('gesperrt')) ?></span><?php break;
                            case 'unverified': ?><span class="tag"><?= e(t('unbestätigt')) ?></span><?php break;
                            case 'pending': ?><a class="tag tag-wait" href="#freischaltung"><?= e(t('wartet auf Freischaltung')) ?></a><?php break;
                            case 'expired': ?><span class="tag tag-bad"><?= e(t('abgelaufen')) ?></span><?php break;
                            default: ?><?= e(t('aktiv')) ?><?php endswitch; ?>
                    </td>
                    <td class="expiry-cell">
                        <?php if (!account_can_expire($u)): ?>
                            <span class="muted"><?= e(t('unbefristet')) ?></span>
                        <?php elseif (is_superadmin($admin) && !$self && $u['approved_at']): ?>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/expiry')) ?>" class="expiry-form">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="exp-<?= (int)$u['id'] ?>"><?= e(t('Zugang bis')) ?></label>
                                <input type="date" id="exp-<?= (int)$u['id'] ?>" name="expires_at" value="<?= e(ymd($u['expires_at'] ? (int)$u['expires_at'] : null)) ?>" min="<?= e(ymd(time())) ?>">
                                <button class="linkbtn" type="submit"><?= e(t('Speichern')) ?></button>
                                <?php if ($u['expires_at']): ?>
                                    <button class="linkbtn" type="submit" name="unlimited" value="1" formnovalidate><?= e(t('Unbefristet')) ?></button>
                                <?php endif; ?>
                            </form>
                        <?php elseif ($u['expires_at']): ?>
                            <?= e(fmt_date((int)$u['expires_at'], false)) ?>
                        <?php else: ?>
                            <span class="muted"><?= e(t('unbefristet')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= (int)$u['quiz_count'] ?></td>
                    <td><?= e(fmt_date((int)$u['created_at'], false)) ?></td>
                    <td class="actions">
                        <?php if ($self): ?>
                            <span class="muted"><?= e(t('Du')) ?></span>
                        <?php elseif ($u['role'] === 'superadmin' && !is_superadmin($admin)): ?>
                            <span class="muted"><?= e(t('geschützt')) ?></span>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . ($u['is_locked'] ? '/unlock' : '/lock'))) ?>" class="inline">
                                <?= csrf_field() ?><button class="linkbtn" type="submit"><?= e($u['is_locked'] ? t('Entsperren') : t('Sperren')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>" class="inline role-form">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="role-<?= (int)$u['id'] ?>"><?= e(t('Rolle')) ?></label>
                                <select name="role" id="role-<?= (int)$u['id'] ?>">
                                    <?php foreach (ROLES as $rk => $rl): if ($rk === 'superadmin' && !is_superadmin($admin)) continue; ?>
                                        <option value="<?= e($rk) ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>><?= e(t($rl)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="linkbtn" type="submit"><?= e(t('Rolle ändern')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" class="inline"
                                  data-confirm="<?= e(t('Konto {email} mit allen Quizzen und Spielen löschen?', ['email' => $u['email']])) ?>">
                                <?= csrf_field() ?><button class="linkbtn danger" type="submit"><?= e(t('Löschen')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="section-title"><?= e(t('Für alle freigegebene Quizze')) ?></h2>
    <?php if (!$public): ?>
        <p class="muted"><?= e(t('Derzeit ist kein Quiz für alle freigegeben.')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th scope="col"><?= e(t('Titel')) ?></th>
                <th scope="col"><?= e(t('Von')) ?></th>
                <th scope="col"><?= e(t('Geändert')) ?></th>
                <th scope="col"><span class="visually-hidden"><?= e(t('Aktionen')) ?></span></th>
            </tr></thead>
            <tbody>
            <?php foreach ($public as $q): ?>
                <tr>
                    <td><?= e($q['title']) ?></td>
                    <td><?= e($q['author']) ?></td>
                    <td><?= e(fmt_date((int)$q['updated_at'], false)) ?></td>
                    <td class="actions">
                        <form method="post" action="<?= e(url('/admin/quizzes/' . $q['id'] . '/hide')) ?>" class="inline">
                            <?= csrf_field() ?><button class="linkbtn" type="submit"><?= e(t('Freigabe aufheben')) ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/quizzes/' . $q['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('Quiz „{title}“ löschen?', ['title' => $q['title']])) ?>">
                            <?= csrf_field() ?><button class="linkbtn danger" type="submit"><?= e(t('Löschen')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
