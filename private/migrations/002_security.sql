-- Evolve Hire 002 — security tables (MySQL fixed-window limiter, audit log).

CREATE TABLE IF NOT EXISTS rate_limits (
  rkey VARCHAR(120) NOT NULL PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  window_start INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(60) NOT NULL,
  actor VARCHAR(190) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY audit_event (event, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
