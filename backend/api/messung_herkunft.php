<?php
// ============================================================
// messung_herkunft.php – MESSUNG (v0.9.106), kein Feature.
// WIRD MIT DER NÄCHSTEN VERSION ENTFERNT, samt Route, Suite und Mutationen.
//
// Frage (Review koordination 14.10.2026, O2, für Befund 5): Welche Adresse
// sieht die Anwendung hinter dem Proxy von Uberspace – die des Geräts oder
// die des Proxys, und kommt X-Forwarded-For an? Gemessen vom Betreiber:
// 91 Einträge in login_log tragen eine einzige IPv4-Adresse, nicht intern,
// nicht die des Servers, nicht die heutige des Rechners – Proxy oder
// gewechselte Adresse, das trennt diese Messung nicht.
//
// Die Antwort enthält KEINE Adresse: nur Wahrheitswerte, Zahlen, die NAMEN
// der Weiterleitungs-Kopfzeilen und Prüfsummen mit einem Salz, das nur für
// diese eine Antwort gilt und nicht herausgegeben wird – sonst ließe sich
// eine IPv4-Adresse aus ihrer Prüfsumme zurückrechnen (2^32 Versuche).
// ============================================================

declare(strict_types=1);

/** Vergleich der Adressen dieser Anfrage mit $ich (vom Aufrufer) und dem login_log-Eintrag. */
function mh_vergleich(array $server, string $ich, ?string $logIp): array
{
    $ra = (string)($server['REMOTE_ADDR'] ?? '');
    $xff = array_values(array_filter(array_map('trim',
        explode(',', (string)($server['HTTP_X_FORWARDED_FOR'] ?? ''))), fn($t) => $t !== ''));
    $salz = random_bytes(16);
    $ps = fn(string $w): string => $w === '' ? '' : substr(hash_hmac('sha256', $w, $salz), 0, 12);
    $namen = array_values(array_filter(array_keys($server),
        fn($k) => preg_match('/^HTTP_(X_FORWARDED|X_REAL_IP|FORWARDED|X_CLIENT|CLIENT_IP|TRUE_CLIENT)/', (string)$k) === 1));
    sort($namen);
    return [
        'remote_addr_gleich_ich'       => $ra === $ich,
        'remote_addr_intern'           => $ra !== '' && filter_var($ra, FILTER_VALIDATE_IP,
                                              FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false,
        'xff_vorhanden'                => isset($server['HTTP_X_FORWARDED_FOR']),
        'xff_eintraege'                => count($xff),
        'xff_erster_gleich_ich'        => ($xff[0] ?? null) === $ich,
        'xff_letzter_gleich_ich'       => $xff !== [] && $xff[count($xff) - 1] === $ich,
        'x_real_ip_gleich_ich'         => ($server['HTTP_X_REAL_IP'] ?? null) === $ich,
        'login_log_gleich_ich'         => $logIp === null ? null : $logIp === $ich,
        'login_log_gleich_remote_addr' => $logIp === null ? null : $logIp === $ra,
        'kopfzeilen'                   => $namen,
        'pruefsummen'                  => ['remote_addr' => $ps($ra), 'ich' => $ps($ich),
                                           'login_log' => $ps((string)$logIp),
                                           'xff_letzter' => $ps((string)($xff[count($xff) - 1] ?? ''))],
    ];
}
