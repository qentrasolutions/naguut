<?php
// Naguut – Konfiguration
// Der Web-Installer legt diese Datei als app/config.php an.
// Alternativ: Datei kopieren, umbenennen in config.php und Werte eintragen.

return [
    // Datenbank (im Hosting-Panel angelegt)
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=naguut;charset=utf8mb4',
        'user' => 'naguut',
        'pass' => '',
    ],

    // Öffentliche Adresse ohne Schrägstrich am Ende, z. B. https://quiz.schule.at
    'base_url' => 'https://quiz.example.at',

    // Absender für Bestätigungs- und Passwort-E-Mails
    'mail_from'      => 'noreply@example.at',
    'mail_from_name' => 'Naguut',
    // 'mail' = PHP mail() des Servers, 'log' = nur in app/storage/mail.log schreiben (Test)
    'mail_mode'      => 'mail',

    // Dürfen sich neue Quizersteller selbst registrieren?
    'registration_open' => true,

    // Maximale Größe von Musikdateien in MB (zusätzlich gilt upload_max_filesize von PHP)
    'max_sound_mb' => 10,

    // Karten-Server für Kartenfragen (Standard: OpenStreetMap). Bei vielen Spielen lieber einen
    // eigenen oder kommerziellen Kachel-Server eintragen (Nutzungsbedingungen von OSM beachten).
    'map' => [
        'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '© OpenStreetMap-Mitwirkende',
        'attribution_url' => 'https://www.openstreetmap.org/copyright',
        'max_zoom' => 18,
    ],

    // Spieldaten werden nach so vielen Tagen gelöscht (F-53)
    'retention_days' => 90,

    // Anbieter-Angaben für Impressum und Datenschutzerklärung
    'operator' => [
        'name'    => 'Name der Schule oder Organisation',
        'address' => 'Straße 1, 3100 St. Pölten, Österreich',
        'email'   => 'office@example.at',
    ],

    // Zufälliger geheimer Schlüssel (vom Installer erzeugt)
    'secret' => 'BITTE-AENDERN',

    // Fehlermeldungen anzeigen (nur zur Entwicklung auf true setzen)
    'debug' => false,
];
