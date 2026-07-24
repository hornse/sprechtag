<?php
require __DIR__ . '/../backend/auth/WebUntisRest.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "JWT-Auswertung (Beispiel aus dem Mitschnitt)\n";
// Nutzlast wie im echten Token: scopes mg:r
$payload = base64_encode(json_encode(['scopes'=>'mg:r','per'=>['mg:r'],
    'user_id'=>568,'username'=>'adminHo']));
$jwt = 'kopf.' . strtr($payload, '+/', '-_') . '.signatur';
$r = new WebUntisRest('https://example.invalid', 'test');
$ref = new ReflectionClass($r);
$p = $ref->getProperty('jwt'); $p->setAccessible(true); $p->setValue($r, $jwt);
$d = $r->jwtDaten();
pruefe('Nutzlast dekodiert', is_array($d) && $d['user_id'] === 568);
pruefe('Benutzername gelesen', $d['username'] === 'adminHo');
$s = $r->jwtScopes();
pruefe('Scope mg:r erkannt', in_array('mg:r', $s, true));
pruefe('kein Schreibrecht', !in_array('mg:rw', $s, true));

// Gegenprobe mit Schreibrecht
$payload2 = base64_encode(json_encode(['scopes'=>'mg:rw']));
$p->setValue($r, 'kopf.' . strtr($payload2, '+/', '-_') . '.sig');
pruefe('Schreibrecht erkannt', in_array('mg:rw', $r->jwtScopes(), true));

// Kein Token
$p->setValue($r, null);
pruefe('ohne Token leer', $r->jwtScopes() === []);
pruefe('ohne Token null', $r->jwtDaten() === null);

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
