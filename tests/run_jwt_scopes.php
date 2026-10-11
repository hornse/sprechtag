<?php
require __DIR__ . '/../backend/auth/WebUntisRest.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "JWT-Auswertung (Aufbau aus dem Mitschnitt, Werte erfunden)\n";
// Aufbau wie im echten Token: scopes mg:r. Benutzer-ID und Benutzername sind
// ERFUNDEN, in derselben Länge wie im Mitschnitt (REIHENREGELN 9) – bis
// v0.9.86 standen hier die echten Werte eines Kontos.
$payload = base64_encode(json_encode(['scopes'=>'mg:r','per'=>['mg:r'],
    'user_id'=>900,'username'=>'probeXy']));
$jwt = 'kopf.' . strtr($payload, '+/', '-_') . '.signatur';
$r = new WebUntisRest('https://example.invalid', 'test');
$ref = new ReflectionClass($r);
$p = $ref->getProperty('jwt'); $p->setAccessible(true); $p->setValue($r, $jwt);
$d = $r->jwtDaten();
pruefe('Nutzlast dekodiert', is_array($d) && $d['user_id'] === 900);
pruefe('Benutzername gelesen', $d['username'] === 'probeXy');
$s = $r->jwtScopes();
pruefe('Scope mg:r erkannt', in_array('mg:r', $s, true));
// Nur, was im Token steht: Der Scope sagt nichts über das Senderecht (E1).
pruefe('Scope mg:rw nicht im Token', !in_array('mg:rw', $s, true));

// Gegenprobe mit dem Scope mg:rw
$payload2 = base64_encode(json_encode(['scopes'=>'mg:rw']));
$p->setValue($r, 'kopf.' . strtr($payload2, '+/', '-_') . '.sig');
pruefe('Scope mg:rw erkannt', in_array('mg:rw', $r->jwtScopes(), true));

// Kein Token
$p->setValue($r, null);
pruefe('ohne Token leer', $r->jwtScopes() === []);
pruefe('ohne Token null', $r->jwtDaten() === null);

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
