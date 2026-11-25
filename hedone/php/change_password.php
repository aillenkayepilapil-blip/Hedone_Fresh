<?php
// Opens ONLY if the server remembers that the question AND the reset OTP were both passed.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';

post_guard();
$r = $_SESSION['reset_ok'] ?? null;
if (!$r || time() - (int)$r['at'] > 600) fail('Please start again.', ['restart' => true], 403);

$pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
if ($m = v_password($pw)) fail($m, ['field' => 'cp1']);
if ($pw !== $pw2) fail('Mismatch Password', ['field' => 'cp2']);

$s = db()->prepare('SELECT password_hash FROM password_history WHERE id_number = ? ORDER BY id DESC LIMIT 3');   // last 3 passwords (protection 7)
$s->execute([$r['id']]);
foreach ($s->fetchAll() as $row) if (password_verify($pw, $row['password_hash'])) fail('You cannot reuse a recent password.', ['field' => 'cp1']);

$hash = hash_secret($pw);
$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id_number = ?')->execute([$hash, $r['id']]);
$pdo->prepare('INSERT INTO password_history (id_number, password_hash) VALUES (?,?)')->execute([$r['id'], $hash]);
$pdo->commit();
unset($_SESSION['reset_ok']);                                                       // the reset is used up: reloading cannot use it again

$user = user_by_id($r['id']);
lock_reset($user['username']);
log_event('PASSWORD_CHANGED', $user['username'], $r['id']);
$body = "Hello {$user['first_name']},\n\nYour Hedone Fresh password was changed.\n\nTime: " . pht() . "\nIP address: " . client_ip()
      . "\nBrowser/device: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\nIf this was not you, reset your password now: " . base_url() . "/index.html#forgot\n";
if (send_mail($user['email'], 'Your password was changed', $body)) log_event('ALERT_EMAIL_SENT', null, $r['id'], 'password changed');
json_out(['ok' => true, 'message' => 'Successfully Change Password']);
