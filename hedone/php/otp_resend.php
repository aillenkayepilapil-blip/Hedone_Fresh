<?php
require __DIR__ . '/bootstrap.php';

post_guard();
$p = pending();
if (!$p) fail('Please start again.', ['restart' => true]);

$s = db()->prepare('SELECT created_at FROM otps WHERE id_number = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
$s->execute([$p['id'], $p['purpose']]);
$last = $s->fetchColumn();
if ($last && time() - strtotime((string)$last) < 60) {                              // 60-second cooldown
    $wait = 60 - (time() - strtotime((string)$last));
    fail("Please wait {$wait}s before requesting a new code.", ['wait' => $wait]);
}
$s = db()->prepare('SELECT COUNT(*) FROM otps WHERE id_number = ? AND purpose = ? AND created_at >= ?');
$s->execute([$p['id'], $p['purpose'], date('Y-m-d H:i:s', time() - 900)]);
if ((int)$s->fetchColumn() >= 4) fail('Resend limit reached. Please try again in 15 minutes.');   // first code + 3 resends per 15 minutes

$user = user_by_id($p['id']);
if (!otp_send($p['id'], $user['email'], $p['purpose'])) fail('We could not send the code. Please try again later.');
json_out(['ok' => true, 'wait' => 60]);
