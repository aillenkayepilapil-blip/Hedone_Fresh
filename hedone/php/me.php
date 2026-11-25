<?php
// Who is logged in? The pages call this on load, and every few minutes while the user is active (keeps the session alive).
require __DIR__ . '/bootstrap.php';
$u = current_user();
json_out(['loggedIn' => (bool)$u, 'name' => $u['first_name'] ?? '', 'role' => $u['role'] ?? '', 'idle' => IDLE_SECONDS]);
