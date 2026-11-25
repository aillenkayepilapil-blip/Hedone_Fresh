<?php
/* Monitoring script. Run it from the command line:
       C:\xampp\php\php.exe C:\xampp\htdocs\hedone\scripts\monitor.php
   It reads the activity log, looks for 4 suspicious patterns in the last 24 hours and saves each finding
   in the security_flags table (one flag per pattern + target + day, so running it again never makes duplicates). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Page Not Found'); }          // never reachable from a browser

date_default_timezone_set('Asia/Manila');
$CFG = require __DIR__ . '/../../../hedone_private/config.php';
$pdo = new PDO("mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4", $CFG['db_user'], $CFG['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("SET time_zone = '+08:00'");

$found = []; $new = 0;
function flag(PDO $pdo, string $pattern, string $sev, string $target, string $details): void {
    global $found, $new;
    $s = $pdo->prepare('INSERT IGNORE INTO security_flags (pattern, severity, target, details, flag_date) VALUES (?,?,?,?,CURDATE())');
    $s->execute([$pattern, $sev, $target, $details]);
    $isNew = $s->rowCount() === 1; if ($isNew) $new++;
    $found[] = [$pattern, $sev, $target, $details, $isNew ? 'NEW' : 'already flagged today'];
}

// 1. One IP with many failed log-ins (the same limit as IP blocking: 10)
foreach ($pdo->query("SELECT ip_address, COUNT(*) c, COUNT(DISTINCT username_typed) u FROM activity_log
        WHERE event_type = 'LOGIN_FAILED' AND created_at >= (NOW() - INTERVAL 24 HOUR) GROUP BY ip_address HAVING c >= 10")->fetchAll() as $r)
    flag($pdo, 'IP_BRUTE_FORCE', 'HIGH', $r['ip_address'], "{$r['c']} failed log-ins against {$r['u']} usernames in 24 hours");

// 2. One account attacked from 3 or more different IPs
foreach ($pdo->query("SELECT username_typed, COUNT(DISTINCT ip_address) ips, COUNT(*) c FROM activity_log
        WHERE event_type = 'LOGIN_FAILED' AND username_typed IS NOT NULL AND created_at >= (NOW() - INTERVAL 24 HOUR)
        GROUP BY username_typed HAVING ips >= 3")->fetchAll() as $r)
    flag($pdo, 'DISTRIBUTED_ATTACK', 'HIGH', $r['username_typed'], "{$r['c']} failures from {$r['ips']} different IP addresses");

// 3. OTP guessing: 5 or more OTP failures for one account inside 10 minutes
$byUser = [];
foreach ($pdo->query("SELECT user_id, UNIX_TIMESTAMP(created_at) t FROM activity_log
        WHERE event_type = 'OTP_FAILED' AND user_id IS NOT NULL AND created_at >= (NOW() - INTERVAL 24 HOUR) ORDER BY created_at")->fetchAll() as $r)
    $byUser[$r['user_id']][] = (int)$r['t'];
foreach ($byUser as $uid => $times) {
    for ($i = 0; $i + 4 < count($times); $i++) {
        if ($times[$i + 4] - $times[$i] <= 600) { flag($pdo, 'OTP_GUESSING', 'MED', (string)$uid, '5+ wrong OTP codes within 10 minutes'); break; }
    }
}

// 4. Successful log-in at an odd hour (midnight to 5 AM Philippine time)
foreach ($pdo->query("SELECT username_typed, COUNT(*) c FROM activity_log WHERE event_type = 'LOGIN_SUCCESS'
        AND created_at >= (NOW() - INTERVAL 24 HOUR) AND HOUR(created_at) < 5 GROUP BY username_typed")->fetchAll() as $r)
    flag($pdo, 'OFF_HOURS_LOGIN', 'MED', (string)$r['username_typed'], "{$r['c']} successful log-in(s) between 12 AM and 5 AM");

echo "\nHedone Fresh monitoring report - " . date('Y-m-d H:i') . "\n" . str_repeat('=', 60) . "\n";
if (!$found) echo "No suspicious patterns found in the last 24 hours.\n";
foreach ($found as [$p, $s, $t, $d, $n]) echo sprintf("[%-4s] %-18s %-20s %s (%s)\n", $s, $p, $t, $d, $n);
echo str_repeat('-', 60) . "\n" . count($found) . " pattern(s) found, $new new flag(s) saved.\n";
