<?php
// Naguut – nächtlicher Aufräum-Job
//
// Als Cronjob im Hosting-Panel einrichten, z. B.:
//   Befehl:  php /pfad/zur/website/app/cron/cleanup.php
//   Zeit:    täglich, z. B. 3:17 Uhr

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

if (!NAGUUT_INSTALLED) {
    fwrite(STDERR, "Naguut ist noch nicht installiert.\n");
    exit(1);
}

db_migrate(db());
$report = cleanup_run();
foreach ($report as $k => $v) {
    echo str_pad($k, 22) . $v . PHP_EOL;
}
