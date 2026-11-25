<?php
require __DIR__ . '/bootstrap.php';
json_out(['token' => csrf_token()]);
