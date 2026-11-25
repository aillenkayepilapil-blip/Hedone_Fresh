<?php
// Our own image CAPTCHA (PHP GD): 5 random characters, noise lines, stretched. The answer lives in the SESSION, never in the page.
require __DIR__ . '/bootstrap.php';
if (!function_exists('imagecreatetruecolor')) { http_response_code(500); exit; }
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';                      // no 0/O or 1/I to avoid confusion
$code = '';
for ($i = 0; $i < 5; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
$_SESSION['captcha'] = $code;

$w = 110; $h = 30;
$im = imagecreatetruecolor($w, $h);
imagefill($im, 0, 0, imagecolorallocate($im, 244, 239, 224));
for ($i = 0; $i < 7; $i++) imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($im, random_int(110, 200), random_int(110, 200), random_int(110, 200)));
for ($i = 0; $i < 5; $i++) imagestring($im, 5, 8 + $i * 19 + random_int(-2, 2), random_int(2, 9), $code[$i], imagecolorallocate($im, random_int(30, 110), random_int(30, 110), random_int(30, 110)));
for ($i = 0; $i < 80; $i++) imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), imagecolorallocate($im, random_int(60, 160), random_int(60, 160), random_int(60, 160)));
$out = imagecreatetruecolor(220, 60);
imagecopyresampled($out, $im, 0, 0, 0, 0, 220, 60, $w, $h);
header('Content-Type: image/png'); header('Cache-Control: no-store');
imagepng($out);
