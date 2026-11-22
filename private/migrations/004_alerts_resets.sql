-- Evolve Hire 004 — job alerts and password reset requests.

CREATE TABLE IF NOT EXISTS job_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  industry VARCHAR(180) NOT NULL DEFAULT '',
  function_name VARCHAR(180) NOT NULL DEFAULT '',
  location VARCHAR(120) NOT NULL DEFAULT '',
  experience VARCHAR(80) NOT NULL DEFAULT '',
  work_mode VARCHAR(40) NOT NULL DEFAULT '',
  employment_type VARCHAR(40) NOT NULL DEFAULT '',
  unsubscribe_token VARCHAR(64) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY alerts_unique (email, industry, function_name, location),
  KEY alerts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(64) NOT NULL DEFAULT '',
  status ENUM('open', 'done') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  KEY resets_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
