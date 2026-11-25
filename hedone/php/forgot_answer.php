<?php
// Forgot Password, step 2: the answer. A wrong answer counts toward the SAME lockout as log-in.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';

post_guard();
$fp = $_SESSION['fp'] ?? null;
if (!$fp || time() - (int)$fp['at'] > 900) fail('Please start again.', ['restart' => true]);
if (ip_block_left() > 0) fail('Too many attempts. Please try again later.', ['blocked' => true]);

$left = lock_seconds_left($fp['uname']);
if ($left > 0) fail("Too many attempts. Try again in {$left}s.", ['locked' => $left]);

$qno = (int)($_POST['qno'] ?? 0);
$a1 = trim((string)($_POST['answer'] ?? '')); $a2 = trim((string)($_POST['answer2'] ?? ''));
if (!in_array($qno, $fp['nos'], true)) fail('Please choose one of the questions shown.');
if ($m = v_answer($a1)) fail($m, ['field' => 'fa']);
if ($a1 !== $a2) fail('Answers do not match.', ['field' => 'fa2']);

$ok = false;
if ($fp['id']) {
    $s = db()->prepare('SELECT answer_hash FROM security_answers WHERE id_number = ? AND question_no = ?');
    $s->execute([$fp['id'], $qno]);
    $ok = password_verify(strtolower($a1), (string)$s->fetchColumn());
} else {
    password_verify(strtolower($a1), hash_secret('dummy'));                          // same work for unknown names
}
if (!$ok) {
    $user = $fp['id'] ? user_by_id($fp['id']) : null;
    log_event('LOGIN_FAILED', $fp['uname'], $fp['id'], 'forgot-password answer');
    [$n, $secs] = lock_fail($fp['uname'], $user);
    ip_note_failure();
    fail('Incorrect answer.', ['locked' => $secs]);
}

$user = user_by_id($fp['id']);
unset($_SESSION['fp']);
set_pending($fp['id'], 'reset');
otp_send($fp['id'], $user['email'], 'reset');
json_out(['ok' => true, 'step' => 'otp', 'purpose' => 'reset', 'email' => mask_email($user['email'])]);
