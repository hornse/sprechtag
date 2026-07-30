<?php
// ============================================================
// auth.php – Session, Rollen, Guards
//
// Rollenmodell:
//   admin      – personType 16, Kürzel in config admin_kuerzel
//                oder Eintrag in app_admins
//   lehrkraft  – personType 2
//   eltern     – personType 12 (LEGAL_GUARDIAN)
//   schueler   – personType 5 (volljährig, bucht für sich selbst)
// ============================================================

declare(strict_types=1);

/**
 * Aktueller Benutzer aus der Session oder null.
 * WICHTIG: isset() statt empty() – IDs dürfen 0 sein.
 */
function auth_user(): ?array
{
    if (!isset($_SESSION['rolle'])) return null;
    return [
        'rolle'     => (string)$_SESSION['rolle'],
        'name'      => (string)($_SESSION['name'] ?? ''),
        'kuerzel'   => $_SESSION['kuerzel'] ?? null,
        'lehrer_id' => isset($_SESSION['lehrer_id']) && $_SESSION['lehrer_id'] !== null
            ? (int)$_SESSION['lehrer_id'] : null,
        'user_id'   => isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null
            ? (int)$_SESSION['user_id'] : null,
        'person_id' => isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : null,
        'kinder'    => (array)($_SESSION['kinder'] ?? []),
    ];
}

function auth_login_speichern(array $daten): void
{
    session_regenerate_id(true);   // Session-Fixation verhindern
    $_SESSION['rolle']     = $daten['rolle'];
    $_SESSION['name']      = $daten['name'];
    $_SESSION['kuerzel']   = $daten['kuerzel'];
    $_SESSION['lehrer_id'] = $daten['lehrer_id'];
    $_SESSION['user_id']   = $daten['user_id'];
    $_SESSION['person_id'] = $daten['person_id'];
    $_SESSION['kinder']    = $daten['kinder'];
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Erzwingt Anmeldung. */
function auth_require(): array
{
    $u = auth_user();
    if ($u === null) json_err('Nicht angemeldet', 401);
    return $u;
}

/** Erzwingt Admin-Rolle. */
function auth_require_admin(): array
{
    $u = auth_require();
    if ($u['rolle'] !== 'admin') {
        json_err('Diese Aktion ist nur für die Sprechtags-Administration möglich', 403);
    }
    return $u;
}

/** Erzwingt Lehrkraft oder Admin. */
function auth_require_lehrkraft(): array
{
    $u = auth_require();
    if (!in_array($u['rolle'], ['lehrkraft', 'admin'], true)) {
        json_err('Diese Aktion ist Lehrkräften vorbehalten', 403);
    }
    return $u;
}

/** Prüft, ob eine Schüler-ID zu diesem Konto gehört (Datenschutz!). */
function auth_kind_erlaubt(array $user, int $schuelerId): bool
{
    foreach ($user['kinder'] as $k) {
        if ((int)($k['id'] ?? 0) === $schuelerId) return true;
    }
    return false;
}
