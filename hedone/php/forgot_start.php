<?php
// Forgot Password, step 1: username + CAPTCHA -> show that person's three questions (and only those).
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';

post_guard();
if (ip_block_left() > 0) fail('Too many attempts. Please try again later.', ['blocked' => true]);
$uname = trim((string)($_POST['username'] ?? ''));
if (!captcha_ok()) fail('Captcha verification failed.', ['field' => 'fcap']);
log_event('RESET_REQUESTED', $uname);

$user = v_username($uname) === '' ? user_by_username($uname) : null;
if ($user && $user['status'] !== 'active') $user = null;

if ($user) {
    $s = db()->prepare('SELECT question_no FROM security_answers WHERE id_number = ? ORDER BY slot');
    $s->execute([$user['id_number']]);
    $nos = array_map('intval', array_column($s->fetchAll(), 'question_no'));
} else {                                                                            // unknown name: behave EXACTLY as if it existed (same shape, same every time)
    $nos = [];
    for ($k = 0; count($nos) < 3; $k++) {
        $n = (crc32(strtolower($uname) . '|' . $k) % 8) + 1;
        if (!in_array($n, $nos, true)) $nos[] = $n;
    }
}
$_SESSION['fp'] = ['uname' => $uname, 'id' => $user['id_number'] ?? null, 'nos' => $nos, 'at' => time()];
json_out(['ok' => true, 'questions' => array_map(fn($n) => ['no' => $n, 'text' => QUESTIONS[$n]], $nos)]);
