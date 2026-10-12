<?php
// ============================================================
// WebUntisAuth.php – JSON-RPC-Client für WebUntis
// VENDORED aus hornse/webuntis-client-php v2.0.0 – dort ändern, hierher kopieren!
//
// Bekannte Fallstricke (siehe CLAUDE.md / Projektstunden NRW):
//  * JSESSIONID aus der authenticate-Antwort (Set-Cookie) merken
//    und bei ALLEN Folgeaufrufen als Cookie-Header mitschicken.
//  * personType 16 (WebUntis-Admin) hat personId = -1 und
//    KEINEN Eintrag in getTeachers().
//  * getTimetable(type=3) je Fach liefert Perioden mit
//    te-Array (Lehrer-IDs) und su-Array (Fach-IDs).
// ============================================================

declare(strict_types=1);

class WebUntisAuth
{
    private string $baseUrl;
    private string $school;
    private string $client;
    private ?string $sessionId    = null;  // aus authenticate-result
    private ?string $sessionCookie = null; // kompletter JSESSIONID-Cookie

    public function __construct(string $baseUrl, string $school, string $client)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->school  = $school;
        $this->client  = $client;
    }

    /** Login. Liefert das authenticate-result (sessionId, personType, personId, ...). */
    public function authenticate(string $username, string $password): array
    {
        $result = $this->rpc('authenticate', [
            'user'     => $username,
            'password' => $password,
            'client'   => $this->client,
        ], true);

        $this->sessionId = $result['sessionId'] ?? null;
        if ($this->sessionCookie === null && $this->sessionId !== null) {
            // Fallback, falls kein Set-Cookie geparst werden konnte
            $this->sessionCookie = 'JSESSIONID=' . $this->sessionId;
        }
        return $result;
    }

    public function getTeachers(): array { return $this->rpc('getTeachers'); }
    public function getSubjects(): array { return $this->rpc('getSubjects'); }
    public function getRooms(): array    { return $this->rpc('getRooms'); }

    // Die drei folgenden Abfragen sind in v2.0.0 aus der Anleitung
    // docs/WebUntisAuth_ergaenzung_v1.5.0.md übernommen.
    //
    // NICHT übernommen ist getStudents(): Die Schülerliste ist
    // personenbezogen, und das Modul wird in jede Anwendung verteilt –
    // auch in die, die sie nicht brauchen (REIHENREGELN 9). Wer sie
    // braucht, ruft sie in der eigenen Anwendung.

    /**
     * Alle Klassen. Ohne Parameter bezieht sich der Aufruf auf das
     * AKTUELLE Schuljahr; zwischen zwei Schuljahren gibt es keines, und
     * WebUntis antwortet mit -8998 („schoolyear is null“, gemessen im
     * Sommer 2026). Dann eine schoolyearId aus getSchoolyears() übergeben.
     */
    public function getKlassen(?int $schoolyearId = null): array
    {
        return $this->rpc('getKlassen',
            $schoolyearId === null ? [] : ['schoolyearId' => $schoolyearId]);
    }

    /** Alle Schuljahre mit id, name, startDate, endDate. */
    public function getSchoolyears(): array { return $this->rpc('getSchoolYears'); }

    /**
     * Aktuelles Schuljahr, oder [] wenn keines aktiv ist.
     *
     * [] heißt NUR „kein Schuljahr aktiv“: das Ergebnis null oder der
     * Fehler -8998. Jeder andere Fehler – nicht erreichbar, kein JSON,
     * Sitzung abgelaufen, ein anderer Fehlercode – wird weitergereicht.
     * Bis v2.0.0 fing die Anleitung jede RuntimeException, und ein
     * Netzfehler sah aus wie die Sommerferien.
     *
     * Nicht gemessen ist, welche der beiden Formen getCurrentSchoolyear
     * selbst zwischen zwei Schuljahren liefert; beide ergeben [].
     */
    public function getCurrentSchoolyear(): array
    {
        try {
            $r = $this->rpc('getCurrentSchoolyear');
        } catch (RuntimeException $e) {
            if ($e->getCode() === -8998) {
                return [];
            }
            throw $e;
        }
        return is_array($r) ? $r : [];
    }

    /** JSESSIONID-Cookie der laufenden Session (für die interne REST-API). */
    public function sessionCookie(): ?string { return $this->sessionCookie; }

    /**
     * Stundenplan eines Elements. type: 1=Klasse, 2=Lehrer, 3=Fach,
     * 4=Raum, 5=Schüler. Datumsformat: YYYYMMDD (int oder string).
     */
    public function getTimetable(int $type, int $id, string $startDate, string $endDate): array
    {
        return $this->rpc('getTimetable', [
            'id'        => $id,
            'type'      => $type,
            'startDate' => (int)$startDate,
            'endDate'   => (int)$endDate,
        ]);
    }

    public function logout(): void
    {
        try { $this->rpc('logout'); } catch (Throwable $e) { /* best effort */ }
        $this->sessionId = $this->sessionCookie = null;
    }

    // --------------------------------------------------------
    private function rpc(string $method, array $params = [], bool $captureCookie = false)
    {
        $url  = $this->baseUrl . '/WebUntis/jsonrpc.do?school=' . rawurlencode($this->school);
        $body = json_encode([
            'id'      => uniqid('fako_', false),
            'method'  => $method,
            'params'  => $params === [] ? new stdClass() : $params,
            'jsonrpc' => '2.0',
        ]);

        $headers = ['Content-Type: application/json'];
        if ($this->sessionCookie !== null) {
            $headers[] = 'Cookie: ' . $this->sessionCookie;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('WebUntis nicht erreichbar: ' . $err);
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($response, 0, $headerSize);
        $rawBody    = substr($response, $headerSize);

        if ($captureCookie
            && preg_match('/^Set-Cookie:\s*(JSESSIONID=[^;\r\n]+)/mi', $rawHeaders, $m)) {
            $this->sessionCookie = $m[1];
        }

        $json = json_decode($rawBody, true);
        if (!is_array($json)) {
            throw new RuntimeException('Unerwartete WebUntis-Antwort (kein JSON)');
        }
        if (isset($json['error'])) {
            $code = $json['error']['code'] ?? 0;
            $msg  = $json['error']['message'] ?? 'Unbekannter Fehler';
            // -8504 = bad credentials
            throw new RuntimeException("WebUntis-Fehler $code: $msg", (int)$code);
        }
        return $json['result'] ?? [];
    }
}
