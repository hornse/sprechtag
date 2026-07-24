<?php
// ============================================================
// WebUntisRest.php – Client für die INTERNE WebUntis-REST-API
// VENDORED aus hornse/webuntis-client-php – dort ändern, hierher kopieren!
// (Ergänzung für sprechtag: setzeTimeout() für kurze Sondierproben –
//  bei Übernahme ins Modul-Repo mitnehmen.)
//
// ⚠️ UNDOKUMENTIERTE API: kann sich mit jedem WebUntis-Update
// ändern. Für Produktivbetrieb den offiziellen JSON-RPC-Weg
// (WebUntisAuth) bevorzugen und diesen Client als Zusatz nutzen.
//
// Ablauf:
//  1. Session per JSON-RPC authenticate -> JSESSIONID-Cookie
//  2. GET /WebUntis/api/token/new  (Cookie) -> JWT als Klartext
//  3. REST-Aufrufe mit "Authorization: Bearer <JWT>" und
//     optional "tenant-id" (aus /api/rest/view/v1/app/data)
// ============================================================

declare(strict_types=1);

class WebUntisRest
{
    private string $baseUrl;
    private string $school;
    private ?string $cookie   = null;   // "JSESSIONID=...; schoolname=_..."
    private ?string $jwt      = null;
    private ?string $tenantId = null;
    private int $timeout      = 25;

    public function __construct(string $baseUrl, string $school)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->school  = $school;
    }

    /** Timeout je Aufruf in Sekunden (Sondierung: kurz halten wegen Proxy-Limit). */
    public function setzeTimeout(int $sekunden): void
    {
        $this->timeout = max(1, $sekunden);
    }

    /** Übernimmt den JSESSIONID-Cookie einer bestehenden JSON-RPC-Session. */
    public function mitSessionCookie(string $jsessionCookie): void
    {
        // schoolname-Cookie wird von manchen Instanzen zusätzlich verlangt
        $this->cookie = $jsessionCookie
            . '; schoolname=_' . base64_encode($this->school);
    }

    /** Holt das JWT (Schritt 2). Liefert true bei Erfolg. */
    public function tokenHolen(): bool
    {
        $r = $this->rohGet('/WebUntis/api/token/new');
        if ($r['status'] === 200 && $r['text'] !== '' && substr_count($r['text'], '.') === 2) {
            $this->jwt = trim($r['text']);
            return true;
        }
        return false;
    }

    /**
     * Liest die Nutzlast des JWT aus (ohne Signaturprüfung – rein
     * informativ). Nützlich für die Diagnose: Das Feld 'scopes' bzw.
     * 'per' zeigt, welche Rechte das Token trägt. Beispiel: "mg:r"
     * bedeutet nur LESEN von Mitteilungen, "mg:rw" auch Schreiben.
     * Ohne Schreibrecht scheitert der Mitteilungsversand mit 403.
     */
    public function jwtDaten(): ?array
    {
        if ($this->jwt === null) return null;
        $teile = explode('.', $this->jwt);
        if (count($teile) !== 3) return null;
        $roh = base64_decode(strtr($teile[1], '-_', '+/'), false);
        if ($roh === false) return null;
        $daten = json_decode($roh, true);
        return is_array($daten) ? $daten : null;
    }

    /** Kurzform für die Diagnose: welche Rechte trägt das Token? */
    public function jwtScopes(): array
    {
        $d = $this->jwtDaten();
        if ($d === null) return [];
        $scopes = [];
        if (isset($d['scopes'])) {
            if (is_array($d['scopes'])) {
                $scopes = $d['scopes'];
            } else {
                $scopes = preg_split('/[\s,]+/', (string)$d['scopes']) ?: [];
            }
        }
        if (isset($d['per']) && is_array($d['per'])) {
            $scopes = array_merge($scopes, $d['per']);
        }
        return array_values(array_unique(array_filter($scopes)));
    }

    /** Versucht, die tenant-id aus app/data zu ermitteln (optional). */
    public function tenantErmitteln(): void
    {
        $r = $this->get('/WebUntis/api/rest/view/v1/app/data');
        $j = $r['json'];
        if (!is_array($j)) return;
        foreach ([['tenant', 'id'], ['tenantId'], ['user', 'tenant', 'id']] as $pfad) {
            $wert = $j;
            foreach ($pfad as $k) { $wert = is_array($wert) ? ($wert[$k] ?? null) : null; }
            if ($wert !== null && $wert !== '') { $this->tenantId = (string)$wert; return; }
        }
    }

    /** GET mit Bearer-Auth. Liefert ['status','contentType','text','json']. */
    public function get(string $pfad, array $query = []): array
    {
        $extra = [];
        if ($this->jwt !== null)      $extra[] = 'Authorization: Bearer ' . $this->jwt;
        if ($this->tenantId !== null) $extra[] = 'tenant-id: ' . $this->tenantId;
        return $this->rohGet($pfad . ($query ? '?' . http_build_query($query) : ''), $extra);
    }

    /**
     * POST mit Bearer-Auth und JSON-Body (Ergänzung v1.3.0).
     * ACHTUNG: schreibender Zugriff – nur mit ausdrücklicher Absicht nutzen.
     * Liefert ['status','contentType','text','json'].
     */
    public function post(string $pfad, array $daten): array
    {
        $headers = ['Accept: application/json, text/plain',
                    'Content-Type: application/json'];
        if ($this->jwt !== null)      $headers[] = 'Authorization: Bearer ' . $this->jwt;
        if ($this->tenantId !== null) $headers[] = 'tenant-id: ' . $this->tenantId;
        if ($this->cookie !== null)   $headers[] = 'Cookie: ' . $this->cookie;

        $ch = curl_init($this->baseUrl . $pfad);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($daten, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $text = curl_exec($ch);
        if ($text === false) {
            $fehler = curl_error($ch);
            curl_close($ch);
            return ['status' => 0, 'contentType' => '', 'text' => 'cURL: ' . $fehler, 'json' => null];
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct     = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $json = json_decode($text, true);
        return ['status' => $status, 'contentType' => $ct,
                'text' => $text, 'json' => is_array($json) ? $json : null];
    }

    /**
     * POST mit Bearer-Auth und multipart/form-data-Body.
     *
     * Manche WebUntis-Endpunkte erwarten den JSON-Block NICHT als
     * Request-Body, sondern als Datei-Teil einer Multipart-Nachricht –
     * so verschickt die Weboberfläche z. B. Mitteilungen:
     *
     *   Content-Disposition: form-data; name="request"; filename="blob"
     *   Content-Type: application/json
     *   {"subject":"…","content":"…","recipientUserIds":[123]}
     *
     * @param string $pfad     z. B. '/WebUntis/api/rest/view/v2/messages/users'
     * @param array  $daten    wird als JSON in den Teil geschrieben
     * @param string $feldname Name des Teils (Standard 'request')
     * @return array{status:int, contentType:string, text:string, json:?array}
     */
    public function postMultipart(string $pfad, array $daten,
                                  string $feldname = 'request'): array
    {
        $grenze = '----WebUntisBoundary' . bin2hex(random_bytes(8));
        $json = json_encode($daten, JSON_UNESCAPED_UNICODE);

        $koerper = "--$grenze\r\n"
            . 'Content-Disposition: form-data; name="' . $feldname
            . '"; filename="blob"' . "\r\n"
            . "Content-Type: application/json\r\n\r\n"
            . $json . "\r\n"
            . "--$grenze--\r\n";

        $headers = ['Accept: application/json, text/plain, */*',
                    'Content-Type: multipart/form-data; boundary=' . $grenze];
        if ($this->jwt !== null)      $headers[] = 'Authorization: Bearer ' . $this->jwt;
        if ($this->tenantId !== null) $headers[] = 'tenant-id: ' . $this->tenantId;
        if ($this->cookie !== null)   $headers[] = 'Cookie: ' . $this->cookie;

        $ch = curl_init($this->baseUrl . $pfad);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $koerper,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $text = curl_exec($ch);
        if ($text === false) {
            $fehler = curl_error($ch);
            curl_close($ch);
            return ['status' => 0, 'contentType' => '',
                    'text' => 'cURL: ' . $fehler, 'json' => null];
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct     = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $json = json_decode($text, true);
        return ['status' => $status, 'contentType' => $ct,
                'text' => $text, 'json' => is_array($json) ? $json : null];
    }

    private function rohGet(string $pfadMitQuery, array $extraHeader = []): array
    {
        $headers = array_merge(['Accept: application/json, text/plain'], $extraHeader);
        if ($this->cookie !== null) $headers[] = 'Cookie: ' . $this->cookie;

        $ch = curl_init($this->baseUrl . $pfadMitQuery);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $text = curl_exec($ch);
        if ($text === false) {
            $fehler = curl_error($ch);
            curl_close($ch);
            return ['status' => 0, 'contentType' => '', 'text' => 'cURL: ' . $fehler, 'json' => null];
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct     = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $json = json_decode($text, true);
        return ['status' => $status, 'contentType' => $ct,
                'text' => $text, 'json' => is_array($json) ? $json : null];
    }
}
