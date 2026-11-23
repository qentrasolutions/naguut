<?php $scripts = ['js/richtext.js']; ?>
<section class="wide legal-admin">
    <div class="page-head">
        <div>
            <p class="crumb"><a href="<?= e(url('/admin')) ?>"><?= e(t('Verwaltung')) ?></a></p>
            <h1><?= e(t('Rechtstexte')) ?></h1>
        </div>
    </div>
    <p class="lead"><?= e(t('Hier änderst du Impressum, Datenschutzerklärung und AGB. Die Texte erscheinen sofort auf der Website; die Konfigurationsdatei bleibt unberührt.')) ?></p>

    <div class="legal-layout">
        <nav class="legal-toc" aria-label="<?= e(t('Rechtstexte')) ?>">
            <?php foreach ($pages as $slug => $p): ?>
                <a href="#<?= e($slug) ?>"><?= e(t($p['title'])) ?>
                    <small><?= e($p['custom'] ? t('angepasst, {date}', ['date' => fmt_date($p['updated'], false)]) : t('Vorlage')) ?></small></a>
            <?php endforeach; ?>
            <div class="legal-help">
                <h2><?= e(t('Erlaubtes HTML')) ?></h2>
                <p class="legal-tags"><?php foreach (['p', 'br', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'sup', 'sub', 'small', 'ul', 'ol', 'li', 'a href', 'blockquote', 'hr', 'code'] as $tg): ?><code>&lt;<?= e($tg) ?>&gt;</code> <?php endforeach; ?></p>
                <p class="muted"><?= e(t('Andere Elemente, Attribute, Stile und Skripte werden nicht übernommen, sondern als Text angezeigt.')) ?></p>
                <h2><?= e(t('Kurzschreibweise')) ?></h2>
                <dl>
                    <dt><code>## Titel</code></dt><dd><?= e(t('Überschrift')) ?></dd>
                    <dt><code>- Punkt</code></dt><dd><?= e(t('Aufzählung')) ?></dd>
                    <dt><code>**Text**</code></dt><dd><?= e(t('fett')) ?></dd>
                    <dt><code>[Text](https://…)</code></dt><dd><?= e(t('Link')) ?></dd>
                </dl>
                <p class="muted"><?= e(t('Leerzeile beginnt einen neuen Absatz.')) ?></p>
            </div>
        </nav>

        <div class="legal-forms">
            <?php foreach ($pages as $slug => $p): ?>
                <form method="post" action="<?= e(url('/admin/rechtstexte')) ?>" class="panel legal-form" id="<?= e($slug) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="page" value="<?= e($slug) ?>">
                    <div class="legal-form-head">
                        <h2><?= e(t($p['title'])) ?></h2>
                        <a href="<?= e(url($p['path'])) ?>" target="_blank"><?= e(t('Seite ansehen')) ?></a>
                    </div>
                    <?php if (!$p['custom']): ?>
                        <p class="note"><?= e(t('Das ist die Vorlage. Ersetze die Angaben in eckigen Klammern und speichere.')) ?></p>
                    <?php endif; ?>
                    <div class="fmtbar fmtbar-wide" role="toolbar" aria-label="<?= e(t('Formatierung')) ?>" data-rt-toolbar="text-<?= e($slug) ?>"
                         data-heading="<?= e(t('Überschrift')) ?>" data-text="<?= e(t('Text')) ?>" data-item="<?= e(t('Punkt')) ?>"
                         data-link-prompt="<?= e(t('Adresse des Links (https://… oder mailto:…)')) ?>" data-link-text="<?= e(t('Linktext')) ?>">
                        <button type="button" class="fmtbtn" data-rt="h2" title="<?= e(t('Überschrift')) ?>">H2</button>
                        <button type="button" class="fmtbtn" data-rt="h3" title="<?= e(t('kleinere Überschrift')) ?>">H3</button>
                        <button type="button" class="fmtbtn" data-rt="p" title="<?= e(t('Absatz')) ?>">¶</button>
                        <span class="fmt-sep"></span>
                        <button type="button" class="fmtbtn" data-rt="strong" title="<?= e(t('Fett')) ?>"><b>B</b></button>
                        <button type="button" class="fmtbtn" data-rt="em" title="<?= e(t('Kursiv')) ?>"><i>I</i></button>
                        <button type="button" class="fmtbtn" data-rt="u" title="<?= e(t('Unterstrichen')) ?>"><u>U</u></button>
                        <button type="button" class="fmtbtn" data-rt="small" title="<?= e(t('Klein')) ?>"><small>klein</small></button>
                        <span class="fmt-sep"></span>
                        <button type="button" class="fmtbtn" data-rt="ul" title="<?= e(t('Aufzählung')) ?>">• <?= e(t('Liste')) ?></button>
                        <button type="button" class="fmtbtn" data-rt="ol" title="<?= e(t('Nummerierte Liste')) ?>">1. <?= e(t('Liste')) ?></button>
                        <button type="button" class="fmtbtn" data-rt="link" title="<?= e(t('Link')) ?>"><?= e(t('Link')) ?></button>
                        <button type="button" class="fmtbtn" data-rt="br" title="<?= e(t('Zeilenumbruch')) ?>">↵</button>
                        <button type="button" class="fmtbtn" data-rt="hr" title="<?= e(t('Trennlinie')) ?>">—</button>
                    </div>
                    <label class="visually-hidden" for="text-<?= e($slug) ?>"><?= e(t($p['title'])) ?></label>
                    <textarea id="text-<?= e($slug) ?>" name="text" rows="18" maxlength="<?= LEGAL_MAX_CHARS ?>" spellcheck="true"><?= e($p['text']) ?></textarea>
                    <div class="btn-row">
                        <button type="submit" name="action" value="save" class="btn btn-ink"><?= e(t('{title} speichern', ['title' => t($p['title'])])) ?></button>
                        <?php if ($p['custom']): ?>
                            <button type="submit" name="action" value="reset" class="btn btn-ghost"
                                    data-confirm-click="<?= e(t('Eigenen Text verwerfen und die Vorlage wiederherstellen?')) ?>"><?= e(t('Vorlage wiederherstellen')) ?></button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>
