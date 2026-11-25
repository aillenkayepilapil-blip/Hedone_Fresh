<?php
// The code is checked against the step the SERVER remembers (pending), never against what the browser claims.
require __DIR__ . '/bootstrap.php';

post_guard();
$p = pending();
if (!$p) fail('Please start again.', ['restart' => true]);
if (ip_block_left() > 0) fail('Too many attempts. Please try again later.', ['blocked' => true]);

$code = trim((string)($_POST['code'] ?? ''));
if (!preg_match('/^\d{6}$/', $code)) fail('Enter the 6-digit code.');

$r = otp_check($p['id'], $p['purpose'], $code);
if ($r === 'bad') fail('Wrong code. Please try again.');
if ($r === 'dead') fail('This code is no longer valid. Please request a new one.', ['dead' => true]);

unset($_SESSION['pending']);
$user = user_by_id($p['id']);

if ($p['purpose'] === 'register') {
    db()->prepare("UPDATE users SET status = 'active' WHERE id_number = ?")->execute([$p['id']]);
    log_event('ACCOUNT_VERIFIED', $user['username'], $p['id']);
    json_out(['ok' => true, 'next' => 'login']);
}
if ($p['purpose'] === 'login') {
    session_regenerate_id(true);                                                    // new session ID after log-in (protection 6)
    $_SESSION['uid'] = $p['id']; $_SESSION['last'] = time();
    lock_reset($user['username']);                                                  // a successful log-in resets the counter
    log_event('LOGIN_SUCCESS', $user['username'], $p['id']);
    json_out(['ok' => true, 'next' => 'loggedin', 'name' => $user['first_name'], 'role' => $user['role']]);
}
$_SESSION['reset_ok'] = ['id' => $p['id'], 'at' => time()];                         // forgot-password: the server remembers steps 1-5 are done
json_out(['ok' => true, 'next' => 'change']);
