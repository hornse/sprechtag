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
        // NOTZUGANG (E29): Kürzel der Lehrkräfte, die IMMER Administrator
        // sind, damit sich niemand aussperren kann – ein oder zwei genügen.
        // Ändern lässt es sich nur hier auf dem Server. Alle weiteren
        // Administratoren werden in der App unter „Administratoren“
        // eingetragen, nicht hier. Ein ARRAY, je Kürzel ein Eintrag:
        //     'admin_kuerzel' => ['Hq', 'Mq', 'Sq'],
        // NICHT ['Hq, Mq'] – das ist EIN Eintrag mit Komma. Die App teilt
        // ihn zur Sicherheit auf (seit v0.9.78), gemeint ist aber die
        // Array-Form. Groß- und Kleinschreibung spielt keine Rolle: 'hq',
        // 'Hq' und 'HQ' treffen dasselbe Kürzel. Das erste Kürzel gilt auch
        // für den WebUntis-Admin (personType 16), der selbst keines hat.
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

    // Sondierwerkzeug (Diagnose für die Verwaltung). Abgeschaltet
    // voreingestellt (v0.9.93): Wer sondieren will, schaltet bewusst ein und
    // danach wieder aus. Auch eingeschaltet erreicht es nur die Verwaltung.
    'sondierung_freigeschaltet' => false,

];
