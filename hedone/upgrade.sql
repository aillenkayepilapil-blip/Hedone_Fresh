-- Run this ONCE in phpMyAdmin (SQL tab) if you already created the database earlier.
USE hedone_fresh;

CREATE TABLE IF NOT EXISTS lockouts (
  username VARCHAR(50) PRIMARY KEY, failed_count INT NOT NULL DEFAULT 0,
  lock_tier TINYINT NOT NULL DEFAULT 0, locked_until DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ip_blocks (
  ip_address VARCHAR(45) PRIMARY KEY, blocked_until DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_flags (
  id INT AUTO_INCREMENT PRIMARY KEY, pattern VARCHAR(40) NOT NULL, severity VARCHAR(4) NOT NULL,
  target VARCHAR(100) NOT NULL, details VARCHAR(255) NOT NULL, flag_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_day (pattern, target, flag_date)
) ENGINE=InnoDB;

-- Permissions for the website's database account (the log table stays append-only: no UPDATE/DELETE)
GRANT SELECT, INSERT, UPDATE, DELETE ON hedone_fresh.lockouts       TO 'hedone_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON hedone_fresh.ip_blocks      TO 'hedone_app'@'localhost';
GRANT SELECT, INSERT                 ON hedone_fresh.security_flags TO 'hedone_app'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON hedone_fresh.otps           TO 'hedone_app'@'localhost';
FLUSH PRIVILEGES;
