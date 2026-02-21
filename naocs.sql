-- NACOS Digital Library — Full SQL Migration (MySQL)
-- Creates database, hardened schema and auxiliary tables to support the 85-feature spec.

CREATE DATABASE IF NOT EXISTS nacos_library
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
USE nacos_library;

-- 1. USERS
  CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    fullname VARCHAR(150) NOT NULL,
    matric_number VARCHAR(64) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    level ENUM('ND1','ND2','ND3','HND1','HND2','HND3') NOT NULL,
    programme ENUM('Full-time','Part-time','CODFEL') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','course_rep','governor','admin') NOT NULL DEFAULT 'student',
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    failed_logins SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_department (department),
    INDEX idx_users_role (role)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. UPLOAD SESSIONS
CREATE TABLE IF NOT EXISTS upload_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  uploader_id INT UNSIGNED NOT NULL,
  title VARCHAR(255),
  department VARCHAR(100),
  total_images INT UNSIGNED DEFAULT 0,
  uploaded_images INT UNSIGNED DEFAULT 0,
  status ENUM('in_progress','processing','completed','failed') DEFAULT 'in_progress',
  meta JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_upload_sessions_uploader (uploader_id),
  INDEX idx_upload_sessions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. BOOKS
CREATE TABLE IF NOT EXISTS books (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(150) DEFAULT NULL,
  uploader_id INT UNSIGNED NOT NULL,
  department VARCHAR(100) NOT NULL,
  status ENUM('pending','approved','rejected','archived') DEFAULT 'pending',
  visibility ENUM('private','department','public') DEFAULT 'department',
  thumbnail_path VARCHAR(255) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  description TEXT,
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_books_status (status),
  INDEX idx_books_department (department),
  INDEX idx_books_uploader (uploader_id),
  INDEX idx_books_created_at (created_at),
  INDEX idx_books_dept_status_created (department, status, created_at),
  FULLTEXT INDEX ft_books_title_author (title, author)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. BOOK_FILES
CREATE TABLE IF NOT EXISTS book_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  file_name VARCHAR(255),
  mime VARCHAR(100),
  size_bytes BIGINT UNSIGNED,
  pages INT UNSIGNED DEFAULT NULL,
  version INT UNSIGNED DEFAULT 1,
  is_compiled BOOLEAN DEFAULT TRUE,
  ocr_text LONGTEXT DEFAULT NULL,
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  INDEX idx_book_files_book (book_id),
  INDEX idx_book_files_storage_key (storage_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. PDF ACCESS TOKENS
CREATE TABLE IF NOT EXISTS pdf_access_tokens (
  token CHAR(128) PRIMARY KEY,
  book_file_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  issued_by INT UNSIGNED DEFAULT NULL,
  ip_restriction VARCHAR(45) DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_file_id) REFERENCES book_files(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_pdf_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. BOOKMARKS
CREATE TABLE IF NOT EXISTS bookmarks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  book_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  UNIQUE KEY unique_bookmark (user_id, book_id),
  INDEX idx_bookmarks_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. APPROVALS / MODERATION LOG
CREATE TABLE IF NOT EXISTS approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id BIGINT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED DEFAULT NULL, -- <--- THIS WAS FIXED!
  action ENUM('approved','rejected','returned_for_edit') NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_approvals_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ANNOUNCEMENTS
CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  start_at DATETIME DEFAULT NULL,
  end_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. DID YOU KNOW (trivia)
CREATE TABLE IF NOT EXISTS did_you_know (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fact TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. PASSWORD RESET TOKENS
CREATE TABLE IF NOT EXISTS password_resets (
  token CHAR(128) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_password_resets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. AUDIT LOGS
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  object_type VARCHAR(50) DEFAULT NULL,
  object_id BIGINT UNSIGNED DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  meta JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_logs_user (user_id),
  INDEX idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. RATE LIMIT / LOCKOUT
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_identifier VARCHAR(128) NOT NULL,
  attempts INT UNSIGNED DEFAULT 0,
  last_attempt DATETIME DEFAULT NULL,
  locked_until DATETIME DEFAULT NULL,
  meta JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_key (user_identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. VIRUS / SCAN RESULTS
CREATE TABLE IF NOT EXISTS file_scans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  storage_key VARCHAR(255) NOT NULL,
  scan_status ENUM('pending','clean','infected','error') DEFAULT 'pending',
  scanner_output TEXT,
  scanned_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_file_scans_key (storage_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. OPTIONAL: TAGS & BOOK_TAGS
CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS book_tags (
  book_id BIGINT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (book_id, tag_id),
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
  INDEX idx_book_tags_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER DATABASE nacos_library CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;