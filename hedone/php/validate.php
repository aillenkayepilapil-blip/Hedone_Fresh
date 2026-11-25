<?php
declare(strict_types=1);
// Server-side rules. Messages match the handout's section 4.10. Every rule is checked again here
// because browser checks can be bypassed.
const REQ = 'This is a required field.';
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const SCHOOLS = ['Caraga State University','Father Saturnino Urios University','Surigao del Norte State University'];
const EXTENSIONS = ['Jr.', 'Sr.', 'II', 'III', 'IV'];
const QUESTIONS = [1 => 'What was the name of your first pet?', 2 => 'Who was your best friend in elementary school?', 3 => 'Who was your favorite teacher in high school?',
  4 => 'What is your mother’s maiden first name?', 5 => 'What street did you live on in Grade 1?', 6 => 'What was the first song you learned by heart?',
  7 => 'What was the name of your elementary school?', 8 => 'What dish did you cook for the first time?'];

function v_name(string $v, string $label, bool $optional = false): string {
    if ($v === '') return $optional ? '' : REQ;
    if (str_contains($v, '  ')) return 'Double space is not allowed.';
    if (preg_match('/\d/', $v)) return "Numbers are not allowed in $label.";
    if (preg_match('/[^A-Za-z ]/', $v)) return "Special characters are not allowed in $label.";
    if (strlen($v) > 1 && $v === strtoupper($v)) return 'Do not use all capital letters.';
    if (preg_match('/(.)\1\1/i', $v)) return 'Three consecutive identical letters are not allowed.';
    foreach (explode(' ', $v) as $w) if (!preg_match('/^[A-Z][a-z]*$/', $w)) return 'First letter of your name must be capital.';
    if (strlen($v) < 2 || strlen($v) > 50) return "$label must be 2–50 characters.";
    return '';
}
function v_title(string $v, string $label, int $min = 2, int $max = 50): string {
    if ($v === '') return REQ;
    if (str_contains($v, '  ')) return 'Double space is not allowed.';
    if (strlen($v) < $min || strlen($v) > $max) return "$label must be $min–$max characters.";
    foreach (explode(' ', $v) as $w) if (!preg_match('/^[A-Z][a-z]*$/', $w)) return "$label must use letters and spaces only, in Title Case.";
    return '';
}
function v_street(string $v): string {
    if ($v === '') return REQ;
    if (str_contains($v, '  ')) return 'Double space is not allowed.';
    return preg_match('/^[A-Za-z0-9 ]{3,50}$/', $v) ? '' : 'Purok/Street must be 3–50 letters, digits and spaces.';
}
function v_id(string $v): string { return $v === '' ? REQ : (preg_match('/^\d{4}-\d{4}$/', $v) ? '' : 'ID Number must follow the format xxxx-xxxx.'); }
function v_email(string $v): string {
    if ($v === '') return REQ;
    return (preg_match('/\s/', $v) || strlen($v) > 100 || !filter_var($v, FILTER_VALIDATE_EMAIL)) ? 'Please enter a valid email address.' : '';
}
function v_username(string $v): string {
    if ($v === '') return REQ;
    return preg_match('/^[A-Za-z][A-Za-z0-9]{5,19}$/', $v) ? '' : 'Username must be 6–20 letters or digits, starting with a letter.';
}
function pw_strength(string $p): int {   // 1 Weak, 2 Medium, 3 Strong
    $s = preg_match_all('/[^A-Za-z0-9\s]/', $p);
    return (strlen($p) >= 13 && $s >= 3) ? 3 : ((strlen($p) >= 12 && $s >= 2) ? 2 : 1);
}
function v_password(string $p): string {
    if ($p === '') return REQ;
    if (strlen($p) > 64) return 'Password must be at most 64 characters.';
    return pw_strength($p) < 2 ? 'Password is too weak. Use at least 12 characters with 2 special characters.' : '';
}
function age_on(string $ymd): int { return (int)(new DateTime($ymd))->diff(new DateTime('today'))->y; }   // server computes age itself
function v_birthdate(string $y, string $m, string $d, ?string &$ymd): string {
    $mi = array_search($m, MONTHS, true);
    if ($y === '' || $d === '' || $mi === false || !ctype_digit($y) || !ctype_digit($d)) return REQ;
    if (!checkdate($mi + 1, (int)$d, (int)$y)) return REQ;
    $ymd = sprintf('%04d-%02d-%02d', $y, $mi + 1, $d);
    if ($ymd > date('Y-m-d')) return 'Birthdate cannot be in the future.';
    return age_on($ymd) < 18 ? 'You must be at least 18 years old to register.' : '';
}
function v_answer(string $v): string {
    if ($v === '') return REQ;
    if (str_contains($v, '  ')) return 'Double space is not allowed.';
    return preg_match('/^[A-Za-z]+( [A-Za-z]+)*$/', $v) && strlen($v) >= 2 && strlen($v) <= 50 ? '' : 'Answer must be 2–50 letters and spaces only.';
}
// ---- system fields ----
function v_contact(string $v): string { return $v === '' ? REQ : (preg_match('/^09\d{9}$/', $v) ? '' : 'Contact number must be 11 digits and start with 09.'); }
function v_school(string $v): string { return $v === '' ? REQ : (in_array($v, SCHOOLS, true) ? '' : 'Please select a valid school.'); }   // dropdown can be faked
function v_program(string $v): string { return $v === '' ? REQ : (preg_match('/^[A-Za-z]+( [A-Za-z]+)*$/', $v) && strlen($v) >= 2 && strlen($v) <= 50 ? '' : 'Course/Program must be 2–50 letters and single spaces only.'); }
