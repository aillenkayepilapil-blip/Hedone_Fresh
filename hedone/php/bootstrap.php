<?php
declare(strict_types=1);
/* Shared setup for every PHP file in this folder: config, errors, headers, session,
   database, and the helper functions used by registration, log-in, OTP and alerts. */
date_default_timezone_set('Asia/Manila');


date_default_timezone_set('Asia/Manila');

// PERSONAL SEED: D1 = 1, D2 = 8

// Username requirements
const MIN_USERNAME_LENGTH = 7;
const MAX_USERNAME_LENGTH = 19;

// Password requirements
const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_LENGTH = 64;

// Name requirements
const MAX_NAME_LENGTH = 28;

// Login security
const FORGOT_PASSWORD_THRESHOLD = 3;
const LOCK_SECONDS = [16, 32, 64];

// OTP
const OTP_MINUTES = 5;

// Session security
const IDLE_SECONDS = 360;

// IP blocking
const IP_FAIL_LIMIT = 16;


// Typing a PHP address into the browser bar shows "Page Not Found" (fetch() and <img> are not "document" requests)
if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document') { http_response_code(404); exit('Page Not Found'); }

$cfgFile = __DIR__ . '/../../../hedone_private/config.php';        // C:\xampp\hedone_private (outside htdocs) - protection 11
if (!is_file($cfgFile)) { http_response_code(500); exit('Server is not configured.'); }
$CFG = require $cfgFile;

ini_set('display_errors', '0'); ini_set('log_errors', '1'); ini_set('error_log', $CFG['error_log']);   // protection 10
set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    http_response_code(500); header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Something went wrong. Please try again.']); exit;
});

header('X-Frame-Options: DENY');                                     // protection 12
header('X-Content-Type-Options: nosniff');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);   // protection 6
session_start();
if (isset($_SESSION['uid'], $_SESSION['last']) && time() - (int)$_SESSION['last'] > IDLE_SECONDS) {   // idle timeout enforced on the SERVER
    session_unset(); session_destroy(); session_start();
}

/* ---------------- basics ---------------- */
function db(): PDO {
    global $CFG; static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO("mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4", $CFG['db_user'], $CFG['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec("SET time_zone = '+08:00'");                      // MySQL clock = Philippine time too
    }
    return $pdo;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }              // protection 2
function json_out(array $d, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($d); exit;
}
function fail(string $msg, array $extra = [], int $code = 200): never { json_out(['ok' => false, 'error' => $msg] + $extra, $code); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }                     // never trust X-Forwarded-For
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }        // protection 3
function post_guard(): void {   // POST only, and the secret token must match
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(404); exit('Page Not Found'); }
    $t = (string)($_POST['csrf'] ?? '');
    if ($t === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
        log_event('CSRF_REJECTED', null, null, basename($_SERVER['SCRIPT_NAME']));
        fail('Your session expired. Please reload the page.', [], 403);
    }
}
function log_event(string $type, ?string $typed = null, ?string $uid = null, ?string $details = null): void {   // protection 9
    db()->prepare('INSERT INTO activity_log (event_type, username_typed, user_id, ip_address, user_agent, details) VALUES (?,?,?,?,?,?)')
        ->execute([$type, $typed === null ? null : substr($typed, 0, 255), $uid, client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $details]);
}
// Argon2id (memory-hard); falls back to bcrypt if this PHP build lacks it.
function hash_secret(string $s): string {
    return defined('PASSWORD_ARGON2ID')
        ? password_hash($s, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
        : password_hash($s, PASSWORD_BCRYPT, ['cost' => 12]);
}
function encrypt_field(string $plain): string {                                                  // protection 13
    global $CFG; $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($plain, 'aes-256-cbc', base64_decode($CFG['enc_key']), OPENSSL_RAW_DATA, $iv));
}
function decrypt_field(string $b64): string {   // only to show a value to its owner
    global $CFG; $raw = base64_decode($b64);
    return (string)openssl_decrypt(substr($raw, 16), 'aes-256-cbc', base64_decode($CFG['enc_key']), OPENSSL_RAW_DATA, substr($raw, 0, 16));
}

/* ---------------- who is logged in ---------------- */
function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $s = db()->prepare('SELECT id_number, first_name, role, status FROM users WHERE id_number = ?');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u || $u['status'] !== 'active') return null;
    $_SESSION['last'] = time();
    return $u;
}
function user_by_id(string $id): ?array {
    $s = db()->prepare('SELECT * FROM users WHERE id_number = ?'); $s->execute([$id]);
    return $s->fetch() ?: null;
}
function user_by_username(string $name): ?array {
    $s = db()->prepare('SELECT * FROM users WHERE username = ?'); $s->execute([$name]);
    return $s->fetch() ?: null;
}
function mask_email(string $e): string { [$a, $b] = explode('@', $e) + [1 => '']; return substr($a, 0, 2) . str_repeat('*', max(1, strlen($a) - 2)) . '@' . $b; }

/* ---------------- steps in progress (NOT a logged-in session) ---------------- */
function set_pending(string $id, string $purpose): void { $_SESSION['pending'] = ['id' => $id, 'purpose' => $purpose, 'at' => time()]; }
function pending(): ?array {
    $p = $_SESSION['pending'] ?? null;
    if (!$p || time() - (int)$p['at'] > 900) { unset($_SESSION['pending']); return null; }
    return $p;
}

/* ---------------- CAPTCHA (image made by captcha.php, answer kept in the session) ---------------- */
function captcha_ok(): bool {
    $given = strtoupper(trim((string)($_POST['captcha'] ?? '')));
    $real = (string)($_SESSION['captcha'] ?? '');
    unset($_SESSION['captcha']);                                 // one try per picture
    if ($real === '' || $given === '' || !hash_equals($real, $given)) {
        log_event('CAPTCHA_FAILED', (string)($_POST['username'] ?? $_POST['un'] ?? ''), null, basename($_SERVER['SCRIPT_NAME']));
        return false;
    }
    return true;
}

/* ---------------- email ---------------- */
function send_mail(string $to, string $subject, string $body): bool {
    global $CFG;
    if (($CFG['mail_mode'] ?? 'log') !== 'smtp') {               // testing mode: write the email into a text file
        $file = $CFG['mail_log'] ?? dirname($CFG['error_log']) . '/mail-outbox.log';
        return file_put_contents($file, "To: $to\nSubject: $subject\n\n$body\n------------------------------\n", FILE_APPEND) !== false;
    }
    $dir = __DIR__ . '/lib/PHPMailer/src/';
    require_once $dir . 'Exception.php'; require_once $dir . 'PHPMailer.php'; require_once $dir . 'SMTP.php';
    try {
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP(); $m->Host = $CFG['smtp_host']; $m->Port = (int)$CFG['smtp_port']; $m->SMTPAuth = true;
        $m->Username = $CFG['smtp_user']; $m->Password = $CFG['smtp_pass']; $m->SMTPSecure = 'tls';
        $m->setFrom($CFG['smtp_from'], 'Hedone Fresh'); $m->addAddress($to);
        $m->Subject = $subject; $m->Body = $body; $m->isHTML(false);
        $m->send();
        return true;
    } catch (Throwable $e) { error_log('MAIL: ' . $e->getMessage()); return false; }
}
function base_url(): string { global $CFG; return $CFG['base_url'] ?? 'http://localhost/hedone'; }
function pht(): string { return date('F j, Y g:i A') . ' (Philippine time)'; }

/* ---------------- one-time codes ---------------- */
function otp_send(string $id, string $email, string $purpose): bool {
    $pdo = db();
    $pdo->prepare('UPDATE otps SET used = 1 WHERE id_number = ? AND purpose = ? AND used = 0')->execute([$id, $purpose]);   // new code cancels the old one
    $code = (string)random_int(100000, 999999);                  // random_int, never rand()
    $exp = date('Y-m-d H:i:s', time() + OTP_MINUTES * 60);
    $pdo->prepare('INSERT INTO otps (id_number, purpose, code_hash, expires_at, created_at) VALUES (?,?,?,?,?)')
        ->execute([$id, $purpose, hash_secret($code), $exp, date('Y-m-d H:i:s')]);
    $title = ['register' => 'Verify your account', 'login' => 'Your log-in code', 'reset' => 'Password reset code'][$purpose];
    $body = "Hedone Fresh - $title\n\nYour code: $code\nThis code expires at " . date('g:i A', strtotime($exp)) . ' (Philippine time), in ' . OTP_MINUTES . " minutes.\n\n"
          . "If you did not request this code, ignore this email and consider changing your password.\n";
    $ok = send_mail($email, "Hedone Fresh - $title", $body);
    log_event('OTP_SENT', null, $id, $purpose . ($ok ? '' : ' (email failed)'));
    return $ok;
}
function otp_check(string $id, string $purpose, string $code): string {   // 'ok' | 'bad' | 'dead'
    $s = db()->prepare('SELECT * FROM otps WHERE id_number = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $s->execute([$id, $purpose]);
    $o = $s->fetch();
    if (!$o || strtotime($o['expires_at']) < time() || (int)$o['attempts'] >= 3) { log_event('OTP_FAILED', null, $id, 'expired or cancelled'); return 'dead'; }
    if (password_verify($code, $o['code_hash'])) { db()->prepare('UPDATE otps SET used = 1 WHERE id = ?')->execute([$o['id']]); return 'ok'; }   // single use
    $n = (int)$o['attempts'] + 1;
    db()->prepare('UPDATE otps SET attempts = ?, used = ? WHERE id = ?')->execute([$n, $n >= 3 ? 1 : 0, $o['id']]);   // 3 wrong tries cancel it
    log_event('OTP_FAILED', null, $id, "wrong code ($n of 3)");
    return $n >= 3 ? 'dead' : 'bad';
}

/* ---------------- lockout (kept on the SERVER, per typed username, real or not) ---------------- */
function lock_key(string $u): string { return strtolower(substr($u, 0, 50)); }
function lock_row(string $u): array {
    $s = db()->prepare('SELECT * FROM lockouts WHERE username = ?'); $s->execute([lock_key($u)]);
    return $s->fetch() ?: ['failed_count' => 0, 'lock_tier' => 0, 'locked_until' => null];
}
function lock_seconds_left(string $u): int { $r = lock_row($u); return $r['locked_until'] ? max(0, strtotime($r['locked_until']) - time()) : 0; }
function lock_fail(string $u, ?array $user): array {   // returns [consecutive failures, seconds locked now]
    $r = lock_row($u); $n = (int)$r['failed_count'] + 1; $tier = (int)$r['lock_tier']; $until = $r['locked_until']; $secs = 0;
    if ($n % 3 === 0) {
        $t = min(intdiv($n, 3), 3); $secs = LOCK_SECONDS[$t - 1]; $until = date('Y-m-d H:i:s', time() + $secs);
        if ($t > $tier) {                                         // one alert per tier, never one per attempt
            $tier = $t; log_event("LOCKOUT_TIER_$t", $u, $user['id_number'] ?? null);
            if ($user) send_lock_alert($user, $n);
        }
    }
    db()->prepare('INSERT INTO lockouts (username, failed_count, lock_tier, locked_until) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE failed_count = VALUES(failed_count), lock_tier = VALUES(lock_tier), locked_until = VALUES(locked_until)')
        ->execute([lock_key($u), $n, $tier, $until]);
    return [$n, $secs];
}
function lock_reset(string $u): void { db()->prepare('DELETE FROM lockouts WHERE username = ?')->execute([lock_key($u)]); }
function send_lock_alert(array $user, int $attempts): void {
    $body = "Hello {$user['first_name']},\n\nThere were $attempts failed sign-in attempts on your Hedone Fresh account.\n\n"
          . 'Time: ' . pht() . "\nIP address: " . client_ip() . "\nBrowser/device: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\n"
          . 'If this was not you, reset your password now: ' . base_url() . "/index.html#forgot\n";
    if (send_mail($user['email'], 'Failed sign-in attempts on your account', $body)) log_event('ALERT_EMAIL_SENT', null, $user['id_number'], 'lockout alert');
}

/* ---------------- IP blocking (failures from one IP, across ANY accounts) ---------------- */
function ip_block_left(): int {
    $s = db()->prepare('SELECT blocked_until FROM ip_blocks WHERE ip_address = ?'); $s->execute([client_ip()]);
    $t = $s->fetchColumn();
    return $t ? max(0, strtotime((string)$t) - time()) : 0;
}
function ip_note_failure(): void {
    if (ip_block_left() > 0) return;
    $s = db()->prepare("SELECT COUNT(*) FROM activity_log WHERE event_type = 'LOGIN_FAILED' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
    $s->execute([client_ip()]);
    if ((int)$s->fetchColumn() >= IP_FAIL_LIMIT) {
        db()->prepare('INSERT INTO ip_blocks (ip_address, blocked_until) VALUES (?,?) ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until)')
            ->execute([client_ip(), date('Y-m-d H:i:s', time() + 600)]);   // blocked for 10 minutes
        log_event('IP_BLOCKED', null, null, IP_FAIL_LIMIT . ' failures in 10 minutes');
    }
}
