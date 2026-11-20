SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- USERS
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  github_token  TEXT NULL,                       -- store ENCRYPTED, never plain
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PROJECTS
CREATE TABLE projects (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  name           VARCHAR(100) NOT NULL,
  repo_owner     VARCHAR(100) NULL,
  repo_name      VARCHAR(100) NULL,
  last_synced_at DATETIME NULL,
  sync_started_at DATETIME NULL,                 -- simple lock: NULL = no sync running
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_user (user_id),
  CONSTRAINT fk_projects_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SESSIONS (coding timer)
CREATE TABLE sessions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,                  -- UTC, set by server
  ended_at   DATETIME NULL,                      -- NULL = timer still running
  note       VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_project_time (project_id, started_at),
  KEY idx_sessions_running (project_id, ended_at),
  CONSTRAINT fk_sessions_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COMMITS (synced from GitHub)
CREATE TABLE commits (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  sha         CHAR(40) NOT NULL,
  message     VARCHAR(500) NULL,
  author_date DATETIME NOT NULL,                 -- UTC
  PRIMARY KEY (id),
  UNIQUE KEY uq_commits_project_sha (project_id, sha),
  KEY idx_commits_project_date (project_id, author_date),
  CONSTRAINT fk_commits_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOGIN ATTEMPTS (for throttling)
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(255) NOT NULL,
  ip_address   VARCHAR(45) NOT NULL,             -- fits IPv6
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_email_time (email, attempted_at),
  KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;