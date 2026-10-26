-- OGES-SHIELD — schema inti (Phase 0: kolom minimal, boleh ALTER nanti)
-- MySQL 8.x / MariaDB 10.6+, engine InnoDB, charset utf8mb4

CREATE DATABASE IF NOT EXISTS oges_shield
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE oges_shield;

-- ============================================================
-- users — auth & RBAC (Phase 2 finalisasi kolom approved_*)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(190)  NOT NULL UNIQUE,
  username      VARCHAR(60)   NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,            -- password_hash() bcrypt/argon2
  role          ENUM('pending','user','superadmin','rejected') NOT NULL DEFAULT 'pending',
  registration_reason VARCHAR(500) NULL,           -- justifikasi akses, dilihat superadmin
  rejection_reason VARCHAR(500) NULL,              -- alasan reject, ditampilkan saat login attempt
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at   TIMESTAMP NULL,
  approved_by   INT UNSIGNED NULL,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- targets — asset inventory, scope enforcement sumber kebenaran
-- ============================================================
CREATE TABLE IF NOT EXISTS targets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,             -- pemilik asset
  name          VARCHAR(190)  NOT NULL,            -- label tampilan
  type          ENUM('domain','ip','url','cidr') NOT NULL DEFAULT 'domain',
  value         VARCHAR(255)  NOT NULL,            -- host/url/cidr aktual
  environment   ENUM('lab','staging','production') NOT NULL DEFAULT 'lab',
  in_scope      TINYINT(1)    NOT NULL DEFAULT 1,  -- server-side scope enforcement
  notes         TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target_value (user_id, type, value),
  INDEX idx_targets_scope (in_scope),
  CONSTRAINT fk_targets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- workflow_templates — definisi template workflow (mirror JSON file)
-- ============================================================
CREATE TABLE IF NOT EXISTS workflow_templates (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(120)  NOT NULL UNIQUE,
  name          VARCHAR(190)  NOT NULL,
  description   TEXT NULL,
  phases        JSON NOT NULL,                     -- urutan phase (scope_validation, recon, ...)
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- workflow_runs — 1 eksekusi workflow (di-create PHP, dijalankan orchestrator)
-- ============================================================
CREATE TABLE IF NOT EXISTS workflow_runs (
  id            CHAR(36)     NOT NULL PRIMARY KEY,  -- UUID
  template_id   INT UNSIGNED NOT NULL,
  target_id     INT UNSIGNED NOT NULL,
  created_by    INT UNSIGNED NOT NULL,
  status        ENUM('pending','running','awaiting_confirmation','completed','failed','stopped') NOT NULL DEFAULT 'pending',
  current_phase VARCHAR(60)  NULL,
  phases        JSON NULL,                          -- snapshot urutan phase utk run ini
  error_message TEXT NULL,
  started_at    TIMESTAMP NULL,
  finished_at   TIMESTAMP NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_runs_template FOREIGN KEY (template_id) REFERENCES workflow_templates(id),
  CONSTRAINT fk_runs_target   FOREIGN KEY (target_id)   REFERENCES targets(id),
  CONSTRAINT fk_runs_user     FOREIGN KEY (created_by)  REFERENCES users(id),
  INDEX idx_runs_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- run_events — feed progress granular (sumber polling SSE/polling PHP)
-- ============================================================
CREATE TABLE IF NOT EXISTS run_events (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- incremental utk last_event_id
  run_id        CHAR(36)     NOT NULL,
  phase         VARCHAR(60)  NOT NULL,
  type          ENUM('tool_started','tool_output','tool_completed','tool_failed',
                   'tool_skipped','ai_summary','ai_decision','phase_started',
                   'phase_complete','run_started','run_completed','run_stopped',
                   'run_failed','confirmation_required','confirmation_result',
                   'security_event','error') NOT NULL,
  payload       JSON NULL,
  created_at    TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_events_run FOREIGN KEY (run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE,
  INDEX idx_events_run_id (run_id, id)
) ENGINE=InnoDB;

-- ============================================================
-- findings — hasil positif tool yang di-mapping + diperkaya AI
-- ============================================================
CREATE TABLE IF NOT EXISTS findings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id        CHAR(36)     NOT NULL,
  asset_id      INT UNSIGNED NULL,                 -- FK targets, nullable kalau belum dipetakan
  severity      ENUM('critical','high','medium','low','info') NOT NULL DEFAULT 'info',
  title         VARCHAR(255)  NOT NULL,
  cvss_score    DECIMAL(3,1) NULL,                 -- 0.0 - 10.0
  cwe           VARCHAR(20)   NULL,
  cve           VARCHAR(20)   NULL,
  summary       TEXT NULL,
  evidence_path VARCHAR(500)  NULL,
  impact        TEXT NULL,
  remediation   TEXT NULL,
  `references`  JSON NULL,                         -- reserved word → backtick
  verification_status ENUM('unverified','verified','false_positive') NOT NULL DEFAULT 'unverified',
  source_tool   VARCHAR(60)   NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_findings_run   FOREIGN KEY (run_id)   REFERENCES workflow_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_findings_asset FOREIGN KEY (asset_id) REFERENCES targets(id) ON DELETE SET NULL,
  INDEX idx_findings_severity (severity),
  INDEX idx_findings_run (run_id)
) ENGINE=InnoDB;

-- ============================================================
-- reports — metadata laporan PDF per run
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id        CHAR(36)     NOT NULL UNIQUE,
  file_path     VARCHAR(500)  NOT NULL,            -- path relatif storage/reports/{run_id}.pdf
  overall_summary TEXT NULL,                       -- teks eksekutif hasil AI
  generated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_run FOREIGN KEY (run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- tool_registry_cache — mirror tools.json utk dibaca PHP (admin UI editor)
-- ============================================================
CREATE TABLE IF NOT EXISTS tool_registry_cache (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tool_id       VARCHAR(60)   NOT NULL UNIQUE,     -- id di tools.json (mis. "subfinder")
  definition    JSON NOT NULL,                     -- full tool object
  enabled       TINYINT(1)    NOT NULL DEFAULT 1,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- audit_logs — semua eksekusi tool + security events, permanen
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  run_id        CHAR(36)     NULL,
  action        VARCHAR(60)  NOT NULL,             -- tool_executed, scope_violation, login, dst.
  target_value  VARCHAR(255) NULL,
  tool_id       VARCHAR(60)  NULL,
  detail        JSON NULL,
  created_at    TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_run (run_id)
) ENGINE=InnoDB;

-- ============================================================
-- seed minimal: 1 workflow template awal
-- ============================================================
INSERT INTO workflow_templates (slug, name, description, phases)
VALUES (
  'full-web-app-assessment',
  'Full Web App Assessment',
  'Alur lengkap: scope validation → recon → service enum → vulnerability assessment → exploit validation → cleanup → report.',
  '["scope_validation","reconnaissance","service_enumeration","vulnerability_assessment","exploitation_validation","cleanup_verification","report_generation"]'
)
ON DUPLICATE KEY UPDATE name = VALUES(name);
