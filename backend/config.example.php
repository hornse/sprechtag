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
        // Wird um den Eltern-personType erweitert, sobald die
        // Sondierung den Wert geliefert hat (vermutlich 15).
        // 2 = Lehrkraft, 16 = WebUntis-Admin, 5 = Schüler
        'allowed_person_types' => [2, 16, 5],
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

    // Das Sondierwerkzeug nach Abschluss der Sondierung abschalten!
    'sondierung_freigeschaltet' => true,

];