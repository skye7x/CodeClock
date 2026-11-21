-- Upgrade an EXISTING database (created from the old 001_schema.sql / baza/db.sql).
-- Run this ONCE, BEFORE uploading the new PHP code.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Cron queue fairness: order by the last attempt, back off from broken repos.
ALTER TABLE projects
  ADD COLUMN last_sync_attempt_at DATETIME NULL AFTER last_synced_at,
  ADD COLUMN sync_failures TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_sync_attempt_at,
  ADD KEY idx_projects_sync_queue (last_sync_attempt_at);

-- Projects that were already synced keep their place in the queue.
UPDATE projects SET last_sync_attempt_at = last_synced_at WHERE last_synced_at IS NOT NULL;

-- Generic rate limiter (replaces login_attempts).
CREATE TABLE rate_events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action     VARCHAR(32) NOT NULL,
  subject    VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rate_lookup (action, subject, created_at),
  KEY idx_rate_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only held throttling data (and plain e-mail addresses). No longer used.
DROP TABLE IF EXISTS login_attempts;
