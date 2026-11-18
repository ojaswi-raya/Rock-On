<?php
/**
 * RockOn — bootstrap included by every page.
 * Sessions, CSRF protection, the JSON data store, URL helpers and flash messages.
 */
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const DATA_FILE = APP_ROOT . '/data/rockon.json';
const APP_TIMEZONE = 'Europe/Paris';

date_default_timezone_set(APP_TIMEZONE);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_name('rockon_session');
session_start();

require __DIR__ . '/dates.php';
require __DIR__ . '/places.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/rules.php';
require __DIR__ . '/store.php';
require __DIR__ . '/components.php';
require __DIR__ . '/partials.php';

// ---------- Output & URLs ----------

/** Escape for HTML output. Use for every value that came from data or the user. */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Relative path from the current script back to the app root, so the app works in any sub-folder (e.g. htdocs/rockon). */
function base(): string
{
    static $base = null;
    if ($base === null) {
        $root = realpath(APP_ROOT);
        $dir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
        $rel = trim(substr($dir, strlen($root)), DIRECTORY_SEPARATOR);
        $base = $rel === '' ? '' : str_repeat('../', substr_count($rel, DIRECTORY_SEPARATOR) + 1);
    }
    return $base;
}

function url(string $path = '', array $query = []): string
{
    $u = base() . ($path === '' ? 'index.php' : $path);
    if ($query) {
        $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    return base() . 'assets/' . $path;
}

function redirect(string $path, array $query = []): void
{
    header('Location: ' . url($path, $query));
    exit;
}

/** Redirect back to a safe, app-relative path (used after actions). */
function redirect_back(string $fallback = 'explore.php'): void
{
    $to = $_POST['back'] ?? $_GET['back'] ?? '';
    if (!is_string($to) || $to === '' || preg_match('#^(https?:)?//#i', $to) || str_contains($to, '..')) {
        $to = $fallback;
    }
    header('Location: ' . base() . ltrim($to, '/'));
    exit;
}

/** The current page as an app-relative path, for "back" fields and ?next= links. */
function current_path(): string
{
    $root = str_replace('\\', '/', realpath(APP_ROOT));
    $file = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']));
    $p = ltrim(substr($file, strlen($root)), '/');
    $q = $_SERVER['QUERY_STRING'] ?? '';
    return $p . ($q !== '' ? '?' . $q : '');
}

function safe_next(?string $next): ?string
{
    if (!$next || preg_match('#^(https?:)?//#i', $next) || str_contains($next, '..') || !preg_match('#^[a-z0-9/_\-]+\.php#i', $next)) {
        return null;
    }
    return $next;
}

// ---------- Flash messages (shown once, as toasts) ----------

function flash(string $text, string $tone = 'ok'): void
{
    $_SESSION['flash'][] = ['text' => $text, 'tone' => $tone];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Old form input + errors survive one redirect (Post/Redirect/Get). */
function keep_form(array $values, array $errors): void
{
    $_SESSION['form'] = ['values' => $values, 'errors' => $errors];
}

function take_form(): array
{
    $f = $_SESSION['form'] ?? ['values' => [], 'errors' => []];
    unset($_SESSION['form']);
    return $f;
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        flash('Your session expired. Please try again.', 'err');
        redirect_back();
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

// ---------- Authentication ----------

function me(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $u = user_by_id($id);
    if (!$u || !empty($u['suspended'])) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $u;
}

function login_as(string $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

/** Pages that include "Log in" in the use-case diagram call this. */
function require_member(): array
{
    $me = me();
    if (!$me) {
        redirect('login.php', ['next' => current_path()]);
    }
    if ($me['kind'] === 'admin') {
        redirect('admin/index.php');
    }
    return $me;
}

function require_admin(): array
{
    $me = me();
    if (!$me) {
        redirect('login.php', ['next' => current_path()]);
    }
    if ($me['kind'] !== 'admin') {
        redirect('dashboard.php');
    }
    return $me;
}

function uid(string $prefix): string
{
    return $prefix . '-' . bin2hex(random_bytes(4));
}

function now_iso(): string
{
    return date('c');
}

// Load the data once per request.
db_load();
