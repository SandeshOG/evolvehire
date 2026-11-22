-- Evolve Hire 001 — accounts, board, pipeline, CV bank, briefs, mail queue.
-- Idempotent: safe to re-run on a fresh database (CREATE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner', 'staff') NOT NULL,
  must_change_password TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  company VARCHAR(180) NOT NULL,
  industry VARCHAR(180) NOT NULL DEFAULT '',
  function_name VARCHAR(180) NOT NULL DEFAULT '',
  location VARCHAR(180) NOT NULL DEFAULT '',
  experience_label VARCHAR(80) NOT NULL DEFAULT '',
  experience_min DECIMAL(10,2) NULL,
  experience_max DECIMAL(10,2) NULL,
  salary_label VARCHAR(80) NOT NULL DEFAULT '',
  salary_min DECIMAL(12,2) NULL,
  salary_max DECIMAL(12,2) NULL,
  employment_type VARCHAR(40) NOT NULL DEFAULT '',
  work_mode VARCHAR(40) NOT NULL DEFAULT '',
  eligibility VARCHAR(500) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  responsibilities TEXT NOT NULL,
  requirements TEXT NOT NULL,
  interview_process TEXT NOT NULL,
  application_instructions TEXT NOT NULL,
  status ENUM('draft', 'published', 'closed') NOT NULL DEFAULT 'draft',
  featured TINYINT NOT NULL DEFAULT 0,
  urgent TINYINT NOT NULL DEFAULT 0,
  internal TINYINT NOT NULL DEFAULT 0,
  published_at TIMESTAMP NULL DEFAULT NULL,
  expires_at DATE NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY jobs_slug (slug),
  KEY jobs_status (status, published_at),
  KEY jobs_expires (expires_at),
  KEY jobs_industry (industry),
  KEY jobs_function (function_name),
  KEY jobs_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NULL,
  ref_code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  current_location VARCHAR(120) NOT NULL DEFAULT '',
  experience VARCHAR(80) NOT NULL DEFAULT '',
  notice_period VARCHAR(80) NOT NULL DEFAULT '',
  expected_salary VARCHAR(80) NOT NULL DEFAULT '',
  note VARCHAR(500) NOT NULL DEFAULT '',
  resume_path VARCHAR(180) NOT NULL DEFAULT '',
  status ENUM('new', 'screening', 'shortlisted', 'interview', 'selected', 'rejected', 'joined') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY applications_ref (ref_code),
  KEY applications_email (email),
  KEY applications_status (status),
  KEY applications_job (job_id),
  CONSTRAINT applications_job FOREIGN KEY (job_id) REFERENCES jobs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  actor VARCHAR(190) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY events_application (application_id),
  CONSTRAINT events_application FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  current_location VARCHAR(120) NOT NULL DEFAULT '',
  experience VARCHAR(80) NOT NULL DEFAULT '',
  designation VARCHAR(120) NOT NULL DEFAULT '',
  preferred_location VARCHAR(120) NOT NULL DEFAULT '',
  preferred_industry VARCHAR(180) NOT NULL DEFAULT '',
  preferred_function VARCHAR(180) NOT NULL DEFAULT '',
  expected_salary VARCHAR(80) NOT NULL DEFAULT '',
  notice_period VARCHAR(80) NOT NULL DEFAULT '',
  resume_path VARCHAR(180) NOT NULL DEFAULT '',
  status ENUM('new', 'reviewing', 'shortlisted', 'placed', 'archived') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(190) NOT NULL DEFAULT '',
  UNIQUE KEY candidates_ref (ref_code),
  UNIQUE KEY candidates_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(180) NOT NULL,
  contact VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  industry VARCHAR(180) NOT NULL DEFAULT '',
  role_title VARCHAR(180) NOT NULL,
  openings INT NOT NULL DEFAULT 1,
  location VARCHAR(120) NOT NULL DEFAULT '',
  experience VARCHAR(80) NOT NULL DEFAULT '',
  salary VARCHAR(80) NOT NULL DEFAULT '',
  employment_type VARCHAR(40) NOT NULL DEFAULT '',
  work_mode VARCHAR(40) NOT NULL DEFAULT '',
  detail VARCHAR(1000) NOT NULL DEFAULT '',
  jd_path VARCHAR(180) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS outbox (
  id INT AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
