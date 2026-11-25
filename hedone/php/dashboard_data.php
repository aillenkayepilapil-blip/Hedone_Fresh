<?php
// Admin-only data for the dashboard. Everything is checked on the SERVER; hiding the page in the browser is not security.
require __DIR__ . '/bootstrap.php';

const EVENT_TYPES = ['REGISTERED', 'ACCOUNT_VERIFIED', 'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOCKOUT_TIER_1', 'LOCKOUT_TIER_2', 'LOCKOUT_TIER_3',
    'IP_BLOCKED', 'OTP_SENT', 'OTP_FAILED', 'CAPTCHA_FAILED', 'RESET_REQUESTED', 'PASSWORD_CHANGED', 'ALERT_EMAIL_SENT', 'CSRF_REJECTED',
    'ADMIN_VIEWED_DASHBOARD', 'ADMIN_ACCESS_DENIED'];

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    log_event('ADMIN_ACCESS_DENIED', null, $u['id_number'] ?? null, 'dashboard');
    fail('Access denied', [], 403);
}
if (($_GET['open'] ?? '') === '1') log_event('ADMIN_VIEWED_DASHBOARD', null, $u['id_number']);   // only when the page is opened, not on each refresh

$pdo = db();
$count = $pdo->query('SELECT event_type, COUNT(*) AS c FROM activity_log WHERE created_at >= CURDATE() GROUP BY event_type')->fetchAll(PDO::FETCH_KEY_PAIR);
$cards = [
    ['Successful log-ins today', (int)($count['LOGIN_SUCCESS'] ?? 0)],
    ['Failed log-ins today', (int)($count['LOGIN_FAILED'] ?? 0)],
    ['Accounts locked now', (int)$pdo->query('SELECT COUNT(*) FROM lockouts WHERE locked_until > NOW()')->fetchColumn()],
    ['IPs blocked now', (int)$pdo->query('SELECT COUNT(*) FROM ip_blocks WHERE blocked_until > NOW()')->fetchColumn()],
    ['OTP codes sent today', (int)($count['OTP_SENT'] ?? 0)],
    ['OTP failures today', (int)($count['OTP_FAILED'] ?? 0)],
    ['Alert emails sent today', (int)($count['ALERT_EMAIL_SENT'] ?? 0)],
];

// failed log-ins per hour, last 24 hours (empty hours show as 0)
$rows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H') AS h, COUNT(*) AS c FROM activity_log
                     WHERE event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 24 HOUR) GROUP BY h")->fetchAll(PDO::FETCH_KEY_PAIR);
$hours = [];
for ($i = 23; $i >= 0; $i--) { $t = time() - $i * 3600; $hours[] = ['label' => date('gA', $t), 'count' => (int)($rows[date('Y-m-d H', $t)] ?? 0)]; }

// top 5 IPs by failed log-ins, and whether each is blocked right now
$ips = [];
$q = $pdo->query("SELECT ip_address, COUNT(*) AS c FROM activity_log WHERE event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 24 HOUR)
                  GROUP BY ip_address ORDER BY c DESC LIMIT 5");
$b = $pdo->prepare('SELECT 1 FROM ip_blocks WHERE ip_address = ? AND blocked_until > NOW()');
foreach ($q->fetchAll() as $r) { $b->execute([$r['ip_address']]); $ips[] = ['ip' => h($r['ip_address']), 'count' => (int)$r['c'], 'blocked' => (bool)$b->fetchColumn()]; }

$flags = array_map(fn($r) => array_map(fn($v) => h((string)$v), $r),
    $pdo->query('SELECT pattern, severity, target, details, flag_date FROM security_flags ORDER BY id DESC LIMIT 20')->fetchAll());

// recent events with filters: only fixed text goes into the SQL, every value goes through a ? placeholder
$w = []; $p = [];
$from = trim((string)($_GET['from'] ?? '')); $to = trim((string)($_GET['to'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $w[] = 'created_at >= ?'; $p[] = "$from 00:00:00"; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $w[] = 'created_at <= ?'; $p[] = "$to 23:59:59"; }
$type = (string)($_GET['type'] ?? '');
if (in_array($type, EVENT_TYPES, true)) { $w[] = 'event_type = ?'; $p[] = $type; }
$name = substr(trim((string)($_GET['username'] ?? '')), 0, 50);
if ($name !== '') { $w[] = 'username_typed LIKE ?'; $p[] = '%' . addcslashes($name, '%_\\') . '%'; }
$ip = substr(trim((string)($_GET['ip'] ?? '')), 0, 45);
if ($ip !== '') { $w[] = 'ip_address LIKE ?'; $p[] = addcslashes($ip, '%_\\') . '%'; }
$s = $pdo->prepare('SELECT event_type, username_typed, user_id, ip_address, user_agent, details, created_at FROM activity_log'
                 . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT 50');
$s->execute($p);
$events = array_map(fn($r) => array_map(fn($v) => h((string)$v), $r), $s->fetchAll());   // protection 2: escape everything shown

json_out(['ok' => true, 'name' => $u['first_name'], 'cards' => $cards, 'hours' => $hours, 'ips' => $ips, 'flags' => $flags, 'events' => $events, 'types' => EVENT_TYPES]);
