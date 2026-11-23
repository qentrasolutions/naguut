<?php
// Öffentliche Seiten

function page_home(): void
{
    $user = current_user();
    view('pages/home', ['title' => null, 'user' => $user, 'pinError' => isset($_GET['pin_error'])], 'layout');
}

/** Spieler-Client: PIN, Nickname und das ganze Spiel auf einer Seite */
function page_play(): void
{
    $pin = preg_match('/^\d{6}$/', (string)($_GET['pin'] ?? '')) ? $_GET['pin'] : '';
    view('game/play', ['title' => t('Mitspielen'), 'pin' => $pin], 'layout_game');
}

function page_legal(string $slug): void
{
    view('pages/legal', [
        'title' => t(LEGAL_PAGES[$slug]['title']),
        'slug' => $slug,
        'html' => legal_render(legal_text($slug)),
        'updated' => legal_updated_at($slug),
    ]);
}

function page_imprint(): void { page_legal('impressum'); }
function page_privacy(): void { page_legal('datenschutz'); }
function page_terms(): void { page_legal('agb'); }
