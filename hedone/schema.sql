CREATE DATABASE IF NOT EXISTS hedone_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hedone_fresh;

CREATE TABLE users (
  id_number     CHAR(9) PRIMARY KEY,                 -- xxxx-xxxx
  first_name VARCHAR(50) NOT NULL, middle_name VARCHAR(50) NULL,
  family_name VARCHAR(50) NOT NULL, name_ext VARCHAR(10) NULL,
  birthdate DATE NOT NULL,
  street VARCHAR(50) NOT NULL, barangay VARCHAR(50) NOT NULL, city VARCHAR(50) NOT NULL,
  province VARCHAR(50) NOT NULL, country VARCHAR(50) NOT NULL, zip CHAR(4) NOT NULL,
  email    VARCHAR(100) NOT NULL UNIQUE,             -- stored lowercase
  username VARCHAR(20)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,               -- Argon2id, never plain text
  contact_enc   VARCHAR(255) NOT NULL,               -- AES-256 encrypted (protection 13)
  school VARCHAR(80) NOT NULL, valid_until DATE NOT NULL, program VARCHAR(50) NOT NULL,
  role   ENUM('user','admin')    NOT NULL DEFAULT 'user',
  status ENUM('pending','active') NOT NULL DEFAULT 'pending',  -- active after OTP
  consented_at DATETIME NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE security_answers (
  id_number CHAR(9) NOT NULL, slot TINYINT NOT NULL, question_no TINYINT NOT NULL,
  answer_hash VARCHAR(255) NOT NULL,                 -- lowercase, then hashed
  PRIMARY KEY (id_number, slot),
  FOREIGN KEY (id_number) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_history (
  id INT AUTO_INCREMENT PRIMARY KEY, id_number CHAR(9) NOT NULL,
  password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_number) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE otps (
  id INT AUTO_INCREMENT PRIMARY KEY, id_number CHAR(9) NOT NULL,
  purpose ENUM('register','login','reset') NOT NULL, code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL, attempts TINYINT NOT NULL DEFAULT 0, used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_number) REFERENCES users(id_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_log (                          -- append-only
  id BIGINT AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(40) NOT NULL,
  username_typed VARCHAR(255) NULL, user_id CHAR(9) NULL, ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL, details VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (event_type, created_at), INDEX (ip_address, created_at)
) ENGINE=InnoDB;

-- App account (run once, choose your own password, do NOT commit it):
-- CREATE USER 'hedone_app'@'localhost' IDENTIFIED BY '<your password>';
-- GRANT SELECT, INSERT, UPDATE ON hedone_fresh.users            TO 'hedone_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON hedone_fresh.otps             TO 'hedone_app'@'localhost';
-- GRANT SELECT, INSERT         ON hedone_fresh.security_answers TO 'hedone_app'@'localhost';
-- GRANT SELECT, INSERT         ON hedone_fresh.password_history TO 'hedone_app'@'localhost';
-- GRANT SELECT, INSERT         ON hedone_fresh.activity_log     TO 'hedone_app'@'localhost';  -- no UPDATE/DELETE

-- ---- added for log-in, lockout, IP blocking and monitoring ----
CREATE TABLE lockouts (                              -- consecutive failures per TYPED username (real or not)
  username VARCHAR(50) PRIMARY KEY, failed_count INT NOT NULL DEFAULT 0,
  lock_tier TINYINT NOT NULL DEFAULT 0, locked_until DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE ip_blocks (
  ip_address VARCHAR(45) PRIMARY KEY, blocked_until DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE security_flags (                        -- written by scripts/monitor.php
  id INT AUTO_INCREMENT PRIMARY KEY, pattern VARCHAR(40) NOT NULL, severity VARCHAR(4) NOT NULL,
  target VARCHAR(100) NOT NULL, details VARCHAR(255) NOT NULL, flag_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_day (pattern, target, flag_date)
) ENGINE=InnoDB;
