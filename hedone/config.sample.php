<?php
// Fake values. Copy to C:\xampp\hedone_private\config.php (OUTSIDE the web folder) and fill in real ones.
// config.php is listed in .gitignore. NEVER commit real passwords or keys.
return [
  'db_host' => 'localhost', 'db_name' => 'hedone_fresh',
  'db_user' => 'hedone_app', 'db_pass' => '123',
  'enc_key' => '3En71IZTMIw+c03WUUYLLnmnEheopVCskEEEeM+xOw4=',                              // php -r "echo base64_encode(random_bytes(32));"
  'error_log' => __DIR__ . '/php-errors.log',

  // 'log' = write emails into a text file (for testing). 'smtp' = send real emails (needed for the demo).
  'mail_mode' => 'log',
  'mail_log'  => __DIR__ . '/mail-outbox.log',
  'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587,
  'smtp_user' => 'you@gmail.com',  'smtp_pass' => 'CHANGE_ME_APP_PASSWORD',   // Gmail App Password
  'smtp_from' => 'you@gmail.com',
];
