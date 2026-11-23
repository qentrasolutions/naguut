<?php
// Routen: [Methode, Muster, Funktion]
// {id} = Zahl, {pin} = 6-stellige PIN

require APP_DIR . '/controllers/pages.php';
require APP_DIR . '/controllers/auth.php';
require APP_DIR . '/controllers/quiz.php';
require APP_DIR . '/controllers/game.php';
require APP_DIR . '/controllers/api.php';
require APP_DIR . '/controllers/admin.php';

function routes(): array
{
    return [
        ['GET',  '/',                         'page_home'],
        ['GET',  '/play',                     'page_play'],
        ['GET',  '/impressum',                'page_imprint'],
        ['GET',  '/datenschutz',              'page_privacy'],
        ['GET',  '/agb',                      'page_terms'],

        ['GET',  '/login',                    'auth_login_form'],
        ['POST', '/login',                    'auth_login'],
        ['POST', '/logout',                   'auth_logout'],
        ['GET',  '/register',                 'auth_register_form'],
        ['POST', '/register',                 'auth_register'],
        ['GET',  '/verify',                   'auth_verify'],
        ['POST', '/verify/resend',            'auth_verify_resend'],
        ['GET',  '/forgot',                   'auth_forgot_form'],
        ['POST', '/forgot',                   'auth_forgot'],
        ['GET',  '/reset',                    'auth_reset_form'],
        ['POST', '/reset',                    'auth_reset'],
        ['GET',  '/profile',                  'auth_profile'],
        ['POST', '/profile',                  'auth_profile_save'],
        ['POST', '/profile/password',         'auth_password_save'],
        ['POST', '/profile/delete',           'auth_account_delete'],

        ['GET',  '/dashboard',                'quiz_dashboard'],
        ['POST', '/quiz/new',                 'quiz_new'],
        ['GET',  '/quiz/{id}/edit',           'quiz_edit'],
        ['POST', '/quiz/{id}/duplicate',      'quiz_duplicate_action'],
        ['POST', '/quiz/{id}/delete',         'quiz_delete_action'],
        ['POST', '/quiz/{id}/play',           'game_start_action'],
        ['POST', '/quiz/{id}/visibility',     'quiz_visibility_action'],
        ['GET',  '/quiz/{id}/export.json',    'quiz_export_json'],
        ['GET',  '/quiz/{id}/export.csv',     'quiz_export_csv'],
        ['GET',  '/quiz/export-all.json',     'quiz_export_all'],
        ['GET',  '/quiz/import-vorlage.csv',  'quiz_import_template'],

        ['GET',  '/host/{pin}',               'game_host_page'],
        ['GET',  '/games',                    'game_history'],
        ['GET',  '/games/{id}',               'game_report'],
        ['GET',  '/games/{id}/report.csv',    'game_report_csv'],
        ['POST', '/games/{id}/delete',        'game_delete_action'],

        ['GET',  '/admin',                    'admin_index'],
        ['POST', '/admin/users/{id}/lock',    'admin_user_lock'],
        ['POST', '/admin/users/{id}/unlock',  'admin_user_unlock'],
        ['POST', '/admin/users/{id}/role',    'admin_user_role'],
        ['POST', '/admin/users/{id}/delete',  'admin_user_delete'],
        ['POST', '/admin/users/{id}/approve', 'admin_user_approve'],
        ['POST', '/admin/users/{id}/reject',  'admin_user_reject'],
        ['POST', '/admin/users/{id}/expiry',  'admin_user_expiry'],
        ['POST', '/admin/accounts',           'admin_accounts_save'],
        ['GET',  '/admin/rechtstexte',        'admin_legal'],
        ['POST', '/admin/rechtstexte',        'admin_legal_save'],
        ['POST', '/admin/map',                'admin_map_save'],
        ['POST', '/admin/categories',              'admin_category_add'],
        ['POST', '/admin/categories/{id}/rename',  'admin_category_rename'],
        ['POST', '/admin/categories/{id}/move',    'admin_category_move'],
        ['POST', '/admin/categories/{id}/delete',  'admin_category_delete'],
        ['POST', '/admin/music',              'admin_music_upload'],
        ['POST', '/admin/music/delete',       'admin_music_delete'],
        ['POST', '/admin/quizzes/{id}/hide',  'admin_quiz_hide'],
        ['POST', '/admin/quizzes/{id}/delete','admin_quiz_delete'],

        // JSON-API
        ['POST',   '/api/auth/login',                 'api_login'],
        ['GET',    '/api/quizzes',                    'api_quiz_list'],
        ['POST',   '/api/quizzes',                    'api_quiz_create'],
        ['GET',    '/api/quizzes/{id}',               'api_quiz_get'],
        ['PUT',    '/api/quizzes/{id}',               'api_quiz_update'],
        ['DELETE', '/api/quizzes/{id}',               'api_quiz_delete'],
        ['POST',   '/api/quizzes/{id}/images',        'api_quiz_image'],
        ['POST',   '/api/quizzes/{id}/sound',         'api_quiz_sound'],
        ['POST',   '/api/quizzes/{id}/import',        'api_quiz_import'],
        ['POST',   '/api/games',                      'api_game_create'],
        ['GET',    '/api/games/{pin}/check',          'api_game_check'],
        ['POST',   '/api/games/{pin}/next',           'api_game_next'],
        ['POST',   '/api/games/{pin}/skip',           'api_game_skip'],
        ['POST',   '/api/games/{pin}/devices',        'api_game_devices'],
        ['POST',   '/api/games/{pin}/end',            'api_game_end'],
        ['DELETE', '/api/games/{pin}/players/{id}',   'api_game_kick'],
        ['POST',   '/api/games/{pin}/join',           'api_game_join'],
        ['POST',   '/api/games/{pin}/answer',         'api_game_answer'],
        ['POST',   '/api/games/{pin}/leave',          'api_game_leave'],
        ['GET',    '/api/games/{pin}/state',          'api_game_state'],
        ['GET',    '/api/games/{id}/report.csv',      'game_report_csv'],
    ];
}

function dispatch(string $method, string $path): void
{
    $allowed = false;
    foreach (routes() as [$m, $pattern, $handler]) {
        $regex = '#^' . strtr($pattern, ['{id}' => '(\d{1,10})', '{pin}' => '(\d{6})']) . '$#';
        if (!preg_match($regex, $path, $matches)) {
            continue;
        }
        if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
            $allowed = true;
            continue;
        }
        array_shift($matches);
        preg_match_all('/\{(id|pin)\}/', $pattern, $names);
        $args = [];
        foreach ($names[1] as $i => $name) {
            $args[] = $name === 'id' ? (int)$matches[$i] : $matches[$i];
        }
        $handler(...$args);
        return;
    }
    abort($allowed ? 405 : 404);
}
