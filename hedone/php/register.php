<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';

post_guard();
if (!empty($_POST['website'])) json_out(['ok' => true, 'step' => 'done']);          // honeypot filled = a bot: refuse silently (protection 8)

$in = fn(string $k): string => trim((string)($_POST[$k] ?? ''));
$pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
$ymd = null; $e = [];

$e['rcap'] = captcha_ok() ? '' : 'Captcha verification failed.';                      // checked on the SERVER
$e['rid'] = v_id($in('rid'));
$e['fn'] = v_name($in('fn'), 'First Name'); $e['mn'] = v_name($in('mn'), 'Middle Name', true); $e['ln'] = v_name($in('ln'), 'Family Name');

// Name extension: Jr., Sr., or "Other" + a typed Roman numeral that is saved exactly as typed
$ext = $in('ext');
if ($ext === 'Other') {
    $typed = $in('ext_other');
    $e['ext'] = in_array($typed, ['I', 'II', 'III', 'IV', 'IX', 'X'], true) ? '' : 'Please enter a valid name extension (I, II, III, IV, IX or X).';
    $ext = $typed;
} else {
    $e['ext'] = ($ext === '' || in_array($ext, ['Jr.', 'Sr.'], true)) ? '' : 'Please select a valid name extension.';
}

$e['bd'] = v_birthdate($in('by'), $in('bm'), $in('bd'), $ymd);                       // age is recalculated here, never trusted from the browser
$e['em'] = v_email($in('em')); $e['un'] = v_username($in('un'));
$e['pw'] = v_password($pw); $e['pw2'] = ($pw2 === '' || $pw2 !== $pw) ? 'Passwords do not match.' : '';
$e['st'] = v_street($in('st'));
foreach (['br' => 'Barangay', 'ct' => 'Municipality/City', 'pv' => 'Province', 'co' => 'Country'] as $k => $l) $e[$k] = v_title($in($k), $l);
$e['zp'] = $in('zp') === '' ? REQ : (preg_match('/^\d{4}$/', $in('zp')) ? '' : 'ZIP Code must be exactly 4 digits.');
$e['cn'] = v_contact($in('cn')); $e['sc'] = v_school($in('sc')); $e['pr'] = v_program($in('pr'));

foreach (['q1', 'q2', 'q3'] as $q) $e[$q] = v_answer($in($q));
$qn = [(int)$in('qn1'), (int)$in('qn2'), (int)$in('qn3')];                            // the user picks 3 DIFFERENT questions from the bank
foreach ([1, 2, 3] as $i) {
    $n = $qn[$i - 1];
    $e['qn' . $i] = $in('qn' . $i) === '' ? REQ : (!isset(QUESTIONS[$n]) ? 'Please choose a valid question.' : (count(array_keys($qn, $n, true)) > 1 ? 'Choose three different questions.' : ''));
}
$e['consent'] = ($_POST['consent'] ?? '') === '1' ? '' : 'You must agree to the Privacy Notice to register.';
$e = array_filter($e);

if (!$e) {   // uniqueness (prepared statements: protection 1)
    foreach ([['rid', 'id_number', $in('rid'), 'This ID Number is already registered.'],
              ['em', 'email', strtolower($in('em')), 'This email is already registered.'],
              ['un', 'username', $in('un'), 'Username is already taken.']] as [$f, $col, $val, $msg]) {
        $s = db()->prepare("SELECT 1 FROM users WHERE $col = ?"); $s->execute([$val]);
        if ($s->fetchColumn()) $e[$f] = $msg;
    }
}
if ($e) json_out(['ok' => false, 'errors' => $e], 422);

$validUntil = date('Y-m-d', strtotime('+4 years'));                                  // card validity is set by the server, never by the user
$pdo = db();
try {
    $pdo->beginTransaction();
    $hash = hash_secret($pw);
    $pdo->prepare('INSERT INTO users (id_number, first_name, middle_name, family_name, name_ext, birthdate, street, barangay, city, province, country, zip,
                    email, username, password_hash, contact_enc, school, valid_until, program, consented_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$in('rid'), $in('fn'), $in('mn') ?: null, $in('ln'), $ext ?: null, $ymd, $in('st'), $in('br'), $in('ct'), $in('pv'), $in('co'), $in('zp'),
                   strtolower($in('em')), $in('un'), $hash, encrypt_field($in('cn')), $in('sc'), $validUntil, $in('pr'), date('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT INTO password_history (id_number, password_hash) VALUES (?,?)')->execute([$in('rid'), $hash]);
    $a = $pdo->prepare('INSERT INTO security_answers (id_number, slot, question_no, answer_hash) VALUES (?,?,?,?)');
    foreach ($qn as $i => $qno) $a->execute([$in('rid'), $i + 1, $qno, hash_secret(strtolower($in('q' . ($i + 1))))]);   // lowercase, then hash
    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($ex->getCode() === '23000') json_out(['ok' => false, 'errors' => ['_form' => 'ID Number, email or username is already registered.']], 422);
    throw $ex;
}
log_event('REGISTERED', $in('un'), $in('rid'));
set_pending($in('rid'), 'register');                                                 // the account stays inactive until the OTP is accepted
otp_send($in('rid'), strtolower($in('em')), 'register');
json_out(['ok' => true, 'step' => 'otp', 'purpose' => 'register', 'email' => mask_email(strtolower($in('em'))),
          'validUntil' => date('F j, Y', strtotime($validUntil))]);
