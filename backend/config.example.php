<?php
/**
 * config.example.php – Vorlage. Nach config.php kopieren und befüllen.
 * config.php liegt NICHT in git (.gitignore). DB-Passwort steht auf dem
 * Server in ~/.my.cnf.
 */

return [

    'webuntis' => [
        'base_url' => 'https://SCHULSERVER.webuntis.com',   // ohne Slash am Ende
        'school'   => 'SCHULKENNUNG',                        // wie in der WebUntis-URL
        'client'   => 'SprechtagApp',
        // Befund Sondierung 07/2026:
        // 2 = Lehrkraft, 5 = Schüler, 12 = Erziehungsberechtigte(r)
        // (LEGAL_GUARDIAN), 16 = WebUntis-Admin (personId -1!)
        'allowed_person_types' => [2, 5, 12, 16],
        'admin_kuerzel' => ['XYZ'],
    ],

    'db' => [
        'dsn'      => 'mysql:host=localhost;dbname=DBNAME;charset=utf8mb4',
        'benutzer' => 'DBBENUTZER',
        'passwort' => 'AUS_~/.my.cnf_EINTRAGEN',
    ],

    'security' => [
        // Brute-Force-Bremse für das Sondierwerkzeug (und später den Login):
        // ab so vielen Fehlversuchen je Benutzername/IP innerhalb des
        // Fensters wird geblockt, BEVOR WebUntis gefragt wird.
        'max_failed_logins' => 5,
        'lockout_minutes'   => 15,
    ],

    // Schlüssel für die verschlüsselte Ablage der Dienstkonto-Zugangsdaten.
    // Mindestens 32 zufällige Zeichen, z. B. erzeugen mit:
    //   php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
    // Wird der Schlüssel geändert, müssen die Zugangsdaten neu eingetragen
    // werden. Ohne Schlüssel ist kein Dienstkonto speicherbar.
    'dienstkonto_schluessel' => 'HIER_32_ZUFAELLIGE_ZEICHEN_EINTRAGEN',

    // Das Sondierwerkzeug nach Abschluss der Sondierung abschalten!
    'sondierung_freigeschaltet' => true,

];
