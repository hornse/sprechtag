<?php
// ============================================================
// tests/run_admin_konto.php – das echte WebUntis-Admin-Konto wird erkannt
// (v0.9.80, E23-Nachtrag: Verwaltung mit Warnung)
// Aufruf: php tests/run_admin_konto.php   → Exit-Code 0 = alles grün
//
// Dem echten Admin-Konto (personType 16) fehlt die Empfängerart PARENTS
// (Fund 2, docs/BEFUND-2026-10-10-verwaltung-parents.md). Es behält die
// Verwaltung als Notzugang, die Oberfläche warnt. Dafür muss die Sitzung
// wissen, ob es dieses Konto ist – die Rolle „admin“ allein sagt es nicht,
// denn ein Lehrerkonto mit admin_kuerzel hat dieselbe Rolle (E22).
// Ausgeführt: auth_login_speichern() und auth_user().
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

require __DIR__ . '/../backend/api/auth.php';
// auth_login_speichern() erneuert die Sitzungskennung; das geht nur, solange
// keine Ausgabe gesendet ist. Darum gepuffert, ausgegeben am Ende.
ob_start();
session_save_path(sys_get_temp_dir());
session_start();

$anmelden = function (string $rolle, ?int $personType): ?array {
    $d = ['rolle' => $rolle, 'name' => 'N', 'kuerzel' => null, 'lehrer_id' => null,
          'user_id' => null, 'person_id' => 1, 'kinder' => []];
    if ($personType !== null) $d['personType'] = $personType;
    auth_login_speichern($d);
    return auth_user();
};

echo "Erkennung des echten Admin-Kontos\n";
pruefe('personType 16: admin_konto wahr', ($anmelden('admin', 16)['admin_konto'] ?? null) === true);
pruefe('Lehrerkonto mit admin_kuerzel (Rolle admin, personType 2): admin_konto falsch',
    ($anmelden('admin', 2)['admin_konto'] ?? null) === false);
pruefe('Eltern und Lehrkraft: falsch', ($anmelden('eltern', 12)['admin_konto'] ?? null) === false
    && ($anmelden('lehrkraft', 2)['admin_konto'] ?? null) === false);
pruefe('ohne personType in den Anmeldedaten: falsch, kein Fehler',
    ($anmelden('admin', null)['admin_konto'] ?? null) === false);
$anmelden('admin', 16);
auth_login_speichern(['rolle' => 'lehrkraft', 'name' => 'N', 'kuerzel' => null, 'lehrer_id' => null,
    'user_id' => null, 'person_id' => 1, 'kinder' => [], 'personType' => 2]);
pruefe('erneute Anmeldung mit anderem Konto: die Markierung wird ersetzt', auth_user()['admin_konto'] === false);
unset($_SESSION['wu_admin_konto']);
pruefe('Sitzung von vor v0.9.80 (Schlüssel fehlt): falsch, kein Fehler', auth_user()['admin_konto'] === false);

$ad = (string)file_get_contents(__DIR__ . '/../backend/api/webuntis_adapter.php');
pruefe('wu_login liefert personType in den Anmeldedaten (Quelle der Markierung)',
    str_contains($ad, "'personType' => \$personType,"));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
ob_end_flush();
exit($fehler === 0 ? 0 : 1);
