<?php
// Log-in step 1: username + password + CAPTCHA. If correct, an OTP is emailed. NO session is created yet.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';

post_guard();
if (ip_block_left() > 0) fail('Too many attempts. Please try again later.', ['blocked' => true]);

$uname = trim((string)($_POST['username'] ?? ''));
$pw = (string)($_POST['password'] ?? '');

$left = lock_seconds_left($uname);                                                   // the lockout lives on the server
if ($left > 0) fail("Too many attempts. Try again in {$left}s.", ['locked' => $left, 'consecutive' => (int)lock_row($uname)['failed_count']]);

// YOUR CALL: a wrong CAPTCHA is logged (CAPTCHA_FAILED) but does NOT count as a failed log-in,
// otherwise anyone could lock a victim's account just by sending bad CAPTCHAs.
if (!captcha_ok()) fail('Captcha verification failed.', ['field' => 'lcap']);

$user = (v_username($uname) === '' && strlen($pw) >= 8 && strlen($pw) <= 64) ? user_by_username($uname) : null;
$hash = $user['password_hash'] ?? hash_secret('dummy-password');                    // same work whether or not the username exists
$ok = password_verify($pw, $hash) && $user !== null;

if (!$ok) {                                                                          // identical message for wrong username AND wrong password
    log_event('LOGIN_FAILED', $uname, $user['id_number'] ?? null);
    [$n, $secs] = lock_fail($uname, $user);
    ip_note_failure();
    fail('Invalid username or password.', ['consecutive' => $n, 'locked' => $secs]);
}

$purpose = $user['status'] === 'active' ? 'login' : 'register';                      // not verified yet: finish verification first
set_pending($user['id_number'], $purpose);
if (!otp_send($user['id_number'], $user['email'], $purpose)) fail('We could not send the code. Please try again later.');
json_out(['ok' => true, 'step' => 'otp', 'purpose' => $purpose, 'email' => mask_email($user['email'])]);
