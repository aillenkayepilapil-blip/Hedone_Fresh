<?php
// Live "is this username taken?" check while the user types.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/validate.php';
$u = (string)($_GET['u'] ?? '');
if (v_username($u) !== '') json_out(['available' => false]);     // the page already shows the format error
json_out(['available' => user_by_username($u) === null]);
