-- =====================================================================
-- COOP NATCCO PARTYLIST National Database Management System
-- Database schema (MySQL 5.5 compatible)
--
-- Rules followed for MySQL 5.5:
--   * InnoDB, utf8mb4 / utf8mb4_unicode_ci (requires MySQL 5.5.3+)
--   * Indexed VARCHAR columns are at most 191 characters (767-byte key limit)
--   * No JSON type, CTEs, window functions, generated columns or FULLTEXT on InnoDB
--   * DATETIME columns carry no CURRENT_TIMESTAMP default; the application sets them
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Geographic reference data
-- ---------------------------------------------------------------------
CREATE TABLE regions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  psgc_code VARCHAR(10) NOT NULL,
  name VARCHAR(150) NOT NULL,
  short_name VARCHAR(40) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_regions_psgc (psgc_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provinces (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id INT UNSIGNED NOT NULL,
  psgc_code VARCHAR(10) NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provinces_psgc (psgc_code),
  KEY idx_provinces_region (region_id),
  KEY idx_provinces_name (name),
  CONSTRAINT fk_provinces_region FOREIGN KEY (region_id) REFERENCES regions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- province_id is NULL for highly urbanized / independent cities and NCR LGUs
CREATE TABLE cities_municipalities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id INT UNSIGNED NOT NULL,
  province_id INT UNSIGNED NULL,
  psgc_code VARCHAR(10) NOT NULL,
  name VARCHAR(150) NOT NULL,
  lgu_type ENUM('city','municipality') NOT NULL DEFAULT 'municipality',
  city_class VARCHAR(20) NULL,
  is_independent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_citymun_psgc (psgc_code),
  KEY idx_citymun_region (region_id),
  KEY idx_citymun_province (province_id),
  KEY idx_citymun_name (name),
  CONSTRAINT fk_citymun_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_citymun_province FOREIGN KEY (province_id) REFERENCES provinces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Identity and authorization
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(60) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  scope_level ENUM('global','national','region','province','city','cooperative','assignment','none') NOT NULL DEFAULT 'none',
  rank_level SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  is_privileged TINYINT(1) NOT NULL DEFAULT 0,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  module VARCHAR(60) NOT NULL,
  label VARCHAR(150) NOT NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  person_id INT UNSIGNED NULL,
  account_status ENUM('pending','active','suspended','deactivated') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (account_status),
  KEY idx_users_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  assignment_status ENUM('pending','active','revoked') NOT NULL DEFAULT 'pending',
  assigned_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_role (user_id, role_id),
  KEY idx_ur_role (role_id),
  KEY idx_ur_status (assignment_status),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_scope_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  scope_type ENUM('national','region','province','city','cooperative') NOT NULL,
  region_id INT UNSIGNED NULL,
  province_id INT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  cooperative_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_usa_user (user_id),
  CONSTRAINT fk_usa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_usa_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_usa_province FOREIGN KEY (province_id) REFERENCES provinces (id),
  CONSTRAINT fk_usa_city FOREIGN KEY (city_id) REFERENCES cities_municipalities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authentication_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  identifier VARCHAR(191) NULL,
  event_type VARCHAR(40) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ae_user (user_id),
  KEY idx_ae_type_date (event_type, created_at),
  KEY idx_ae_date (created_at),
  KEY idx_ae_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE throttles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  throttle_key CHAR(64) NOT NULL,
  bucket VARCHAR(30) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  first_attempt_at DATETIME NOT NULL,
  last_attempt_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_throttle_key (throttle_key),
  KEY idx_throttle_bucket (bucket, last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  purpose ENUM('reset','invite') NOT NULL DEFAULT 'reset',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prt_hash (token_hash),
  KEY idx_prt_user (user_id),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mfa_credentials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'totp',
  secret_enc TEXT NOT NULL,
  recovery_codes TEXT NULL,
  confirmed_at DATETIME NULL,
  last_used_step BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mfa_user (user_id),
  CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  mfa_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  last_activity_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  revoked_reason VARCHAR(60) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sessions_hash (session_hash),
  KEY idx_sessions_user (user_id),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Media (all uploads are stored outside the public directory)
-- ---------------------------------------------------------------------
CREATE TABLE media_assets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  original_name VARCHAR(191) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  stored_path VARCHAR(191) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  extension VARCHAR(10) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  width SMALLINT UNSIGNED NULL,
  height SMALLINT UNSIGNED NULL,
  has_thumb TINYINT(1) NOT NULL DEFAULT 0,
  visibility ENUM('public','private') NOT NULL DEFAULT 'private',
  title VARCHAR(191) NULL,
  alt_text VARCHAR(255) NULL,
  caption VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_stored (stored_name),
  KEY idx_media_sha (sha256),
  KEY idx_media_visibility (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Organizational data
-- ---------------------------------------------------------------------
CREATE TABLE organizational_units (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  unit_type ENUM('national','department','committee','office','regional','provincial','local') NOT NULL DEFAULT 'department',
  region_id INT UNSIGNED NULL,
  province_id INT UNSIGNED NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  unit_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ou_parent (parent_id),
  KEY idx_ou_region (region_id),
  CONSTRAINT fk_ou_parent FOREIGN KEY (parent_id) REFERENCES organizational_units (id),
  CONSTRAINT fk_ou_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_ou_province FOREIGN KEY (province_id) REFERENCES provinces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  org_unit_id INT UNSIGNED NULL,
  rank_order INT NOT NULL DEFAULT 100,
  is_leadership TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  position_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_positions_unit (org_unit_id),
  CONSTRAINT fk_positions_unit FOREIGN KEY (org_unit_id) REFERENCES organizational_units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One record per real person; officers, personnel, point persons and
-- ambassadors all reference it so a person is never duplicated.
CREATE TABLE persons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  suffix VARCHAR(20) NULL,
  name_key VARCHAR(191) NOT NULL,
  email_enc TEXT NULL,
  email_bidx CHAR(64) NULL,
  phone_enc TEXT NULL,
  photo_media_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_persons_name (name_key),
  KEY idx_persons_email (email_bidx),
  KEY idx_persons_last (last_name),
  CONSTRAINT fk_persons_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE officers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id INT UNSIGNED NOT NULL,
  officer_code VARCHAR(30) NOT NULL,
  public_title VARCHAR(150) NULL,
  bio TEXT NULL,
  is_public_profile TINYINT(1) NOT NULL DEFAULT 0,
  photo_media_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  office_assignment VARCHAR(150) NULL,
  responsibilities TEXT NULL,
  contact_email VARCHAR(191) NULL,
  contact_phone VARCHAR(40) NULL,
  admin_notes TEXT NULL,
  officer_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_officers_person (person_id),
  UNIQUE KEY uq_officers_code (officer_code),
  KEY idx_officers_region (region_id),
  KEY idx_officers_review (review_status),
  CONSTRAINT fk_officers_person FOREIGN KEY (person_id) REFERENCES persons (id),
  CONSTRAINT fk_officers_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_officers_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE officer_appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  officer_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  org_unit_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  appointment_status ENUM('current','ended','suspended') NOT NULL DEFAULT 'current',
  appointment_reference VARCHAR(100) NULL,
  document_media_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_oa_officer (officer_id),
  KEY idx_oa_position (position_id),
  KEY idx_oa_status (appointment_status),
  CONSTRAINT fk_oa_officer FOREIGN KEY (officer_id) REFERENCES officers (id),
  CONSTRAINT fk_oa_position FOREIGN KEY (position_id) REFERENCES positions (id),
  CONSTRAINT fk_oa_unit FOREIGN KEY (org_unit_id) REFERENCES organizational_units (id),
  CONSTRAINT fk_oa_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_oa_doc FOREIGN KEY (document_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coop_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperatives (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_no VARCHAR(30) NOT NULL,
  name VARCHAR(191) NOT NULL,
  name_key VARCHAR(191) NOT NULL,
  cda_registration_no VARCHAR(60) NULL,
  cooperative_type_id INT UNSIGNED NULL,
  category ENUM('primary','secondary','tertiary') NOT NULL DEFAULT 'primary',
  registered_address VARCHAR(255) NULL,
  operating_address VARCHAR(255) NULL,
  region_id INT UNSIGNED NOT NULL,
  province_id INT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  contact_person VARCHAR(150) NULL,
  contact_email VARCHAR(191) NULL,
  contact_phone VARCHAR(40) NULL,
  website VARCHAR(191) NULL,
  date_registered DATE NULL,
  reported_member_count INT UNSIGNED NULL,
  org_status ENUM('active','inactive','suspended','dissolved') NOT NULL DEFAULT 'active',
  verification_status ENUM('unverified','verified','needs_update') NOT NULL DEFAULT 'unverified',
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  is_public_listing TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coops_ref (reference_no),
  UNIQUE KEY uq_coops_cda (cda_registration_no),
  KEY idx_coops_name_key (name_key),
  KEY idx_coops_name (name),
  KEY idx_coops_region (region_id),
  KEY idx_coops_province (province_id),
  KEY idx_coops_city (city_id),
  KEY idx_coops_status (org_status, verification_status),
  KEY idx_coops_review (review_status),
  CONSTRAINT fk_coops_type FOREIGN KEY (cooperative_type_id) REFERENCES cooperative_types (id),
  CONSTRAINT fk_coops_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_coops_province FOREIGN KEY (province_id) REFERENCES provinces (id),
  CONSTRAINT fk_coops_city FOREIGN KEY (city_id) REFERENCES cities_municipalities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_scope_assignments
  ADD CONSTRAINT fk_usa_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id);

CREATE TABLE cooperative_personnel (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cooperative_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  personnel_type ENUM('officer','staff') NOT NULL DEFAULT 'staff',
  position_title VARCHAR(120) NOT NULL,
  department VARCHAR(120) NULL,
  employment_status ENUM('active','on_leave','ended','resigned','terminated') NOT NULL DEFAULT 'active',
  start_date DATE NULL,
  end_date DATE NULL,
  system_role_note VARCHAR(150) NULL,
  notes TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cp_coop (cooperative_id),
  KEY idx_cp_person (person_id),
  KEY idx_cp_status (employment_status),
  CONSTRAINT fk_cp_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_cp_person FOREIGN KEY (person_id) REFERENCES persons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cnpl_point_persons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id INT UNSIGNED NOT NULL,
  cooperative_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  role_title VARCHAR(120) NOT NULL DEFAULT 'CNPL Point Person',
  coverage_region_id INT UNSIGNED NULL,
  coverage_province_id INT UNSIGNED NULL,
  coverage_city_id INT UNSIGNED NULL,
  coverage_notes VARCHAR(255) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  assignment_status ENUM('active','replaced','ended','suspended') NOT NULL DEFAULT 'active',
  replaced_by_id INT UNSIGNED NULL,
  notes TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pp_person (person_id),
  KEY idx_pp_coop (cooperative_id),
  KEY idx_pp_user (user_id),
  KEY idx_pp_status (assignment_status),
  CONSTRAINT fk_pp_person FOREIGN KEY (person_id) REFERENCES persons (id),
  CONSTRAINT fk_pp_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_pp_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_pp_region FOREIGN KEY (coverage_region_id) REFERENCES regions (id),
  CONSTRAINT fk_pp_province FOREIGN KEY (coverage_province_id) REFERENCES provinces (id),
  CONSTRAINT fk_pp_city FOREIGN KEY (coverage_city_id) REFERENCES cities_municipalities (id),
  CONSTRAINT fk_pp_replaced FOREIGN KEY (replaced_by_id) REFERENCES cnpl_point_persons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE point_person_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  point_person_id INT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  details TEXT NULL,
  task_type ENUM('task','correspondence') NOT NULL DEFAULT 'task',
  due_date DATE NULL,
  task_status ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ppt_pp (point_person_id),
  KEY idx_ppt_status (task_status),
  CONSTRAINT fk_ppt_pp FOREIGN KEY (point_person_id) REFERENCES cnpl_point_persons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CNPL ambassadors and focal persons (designation_type distinguishes them)
CREATE TABLE ambassadors_focal_persons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id INT UNSIGNED NOT NULL,
  designation_type ENUM('ambassador','focal_person') NOT NULL,
  designation_title VARCHAR(120) NOT NULL,
  cooperative_id INT UNSIGNED NULL,
  affiliation VARCHAR(191) NULL,
  user_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  province_id INT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  scope_notes VARCHAR(255) NULL,
  appointment_date DATE NULL,
  end_date DATE NULL,
  appointment_reference VARCHAR(100) NULL,
  designation_status ENUM('active','inactive','ended','suspended') NOT NULL DEFAULT 'active',
  responsibilities TEXT NULL,
  notes TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_afp_person (person_id),
  KEY idx_afp_type (designation_type),
  KEY idx_afp_region (region_id),
  KEY idx_afp_province (province_id),
  KEY idx_afp_city (city_id),
  KEY idx_afp_user (user_id),
  CONSTRAINT fk_afp_person FOREIGN KEY (person_id) REFERENCES persons (id),
  CONSTRAINT fk_afp_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_afp_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_afp_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_afp_province FOREIGN KEY (province_id) REFERENCES provinces (id),
  CONSTRAINT fk_afp_city FOREIGN KEY (city_id) REFERENCES cities_municipalities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regular members: confidential, minimal fields, contact data encrypted in PHP
CREATE TABLE members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_ref VARCHAR(30) NOT NULL,
  cooperative_id INT UNSIGNED NOT NULL,
  coop_member_no VARCHAR(60) NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  suffix VARCHAR(20) NULL,
  name_key VARCHAR(191) NOT NULL,
  email_enc TEXT NULL,
  phone_enc TEXT NULL,
  address_enc TEXT NULL,
  membership_type ENUM('regular','associate') NOT NULL DEFAULT 'regular',
  membership_status ENUM('active','inactive','withdrawn','terminated','deceased') NOT NULL DEFAULT 'active',
  date_admitted DATE NULL,
  date_ended DATE NULL,
  lawful_basis ENUM('contract','legal_obligation','legitimate_interest','consent','public_task') NOT NULL DEFAULT 'contract',
  consent_recorded_at DATETIME NULL,
  consent_reference VARCHAR(150) NULL,
  notes TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_ref (member_ref),
  UNIQUE KEY uq_members_coop_no (cooperative_id, coop_member_no),
  KEY idx_members_name (name_key),
  KEY idx_members_status (membership_status),
  CONSTRAINT fk_members_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membership history (one row per membership period)
CREATE TABLE cooperative_memberships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id INT UNSIGNED NOT NULL,
  cooperative_id INT UNSIGNED NOT NULL,
  coop_member_no VARCHAR(60) NULL,
  membership_type ENUM('regular','associate') NOT NULL DEFAULT 'regular',
  membership_status ENUM('active','inactive','withdrawn','terminated','deceased') NOT NULL DEFAULT 'active',
  start_date DATE NULL,
  end_date DATE NULL,
  change_reason VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cm_member (member_id),
  KEY idx_cm_coop (cooperative_id),
  CONSTRAINT fk_cm_member FOREIGN KEY (member_id) REFERENCES members (id),
  CONSTRAINT fk_cm_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  activity_type ENUM('meeting','training','outreach','assembly','consultation','coordination','field_report','other') NOT NULL DEFAULT 'meeting',
  activity_date DATE NOT NULL,
  end_date DATE NULL,
  cooperative_id INT UNSIGNED NULL,
  ambassador_id INT UNSIGNED NULL,
  point_person_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  province_id INT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  venue VARCHAR(191) NULL,
  participants_count INT UNSIGNED NULL,
  description TEXT NULL,
  outcome TEXT NULL,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  review_remarks VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_act_date (activity_date),
  KEY idx_act_coop (cooperative_id),
  KEY idx_act_region (region_id),
  KEY idx_act_amb (ambassador_id),
  KEY idx_act_pp (point_person_id),
  CONSTRAINT fk_act_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_act_amb FOREIGN KEY (ambassador_id) REFERENCES ambassadors_focal_persons (id),
  CONSTRAINT fk_act_pp FOREIGN KEY (point_person_id) REFERENCES cnpl_point_persons (id),
  CONSTRAINT fk_act_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_act_province FOREIGN KEY (province_id) REFERENCES provinces (id),
  CONSTRAINT fk_act_city FOREIGN KEY (city_id) REFERENCES cities_municipalities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Election performance (aggregate official data only)
-- ---------------------------------------------------------------------
CREATE TABLE election_years (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_year SMALLINT UNSIGNED NOT NULL,
  election_date DATE NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ey_year (election_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_sources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  source_type ENUM('comelec_official','certified_copy','canvass_report','other') NOT NULL DEFAULT 'comelec_official',
  issuing_body VARCHAR(191) NULL,
  reference_no VARCHAR(100) NULL,
  reference_url VARCHAR(255) NULL,
  date_issued DATE NULL,
  document_media_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_es_doc FOREIGN KEY (document_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_year_id INT UNSIGNED NOT NULL,
  geo_level ENUM('national','region','province','city') NOT NULL,
  geo_key VARCHAR(30) NOT NULL,
  region_id INT UNSIGNED NULL,
  province_id INT UNSIGNED NULL,
  city_id INT UNSIGNED NULL,
  votes_obtained INT UNSIGNED NOT NULL,
  total_votes_cast INT UNSIGNED NULL,
  registered_voters INT UNSIGNED NULL,
  rank_position SMALLINT UNSIGNED NULL,
  source_id INT UNSIGNED NOT NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_er_year_geo (election_year_id, geo_key),
  KEY idx_er_level (geo_level),
  KEY idx_er_region (region_id),
  KEY idx_er_province (province_id),
  KEY idx_er_city (city_id),
  KEY idx_er_verif (verification_status),
  CONSTRAINT fk_er_year FOREIGN KEY (election_year_id) REFERENCES election_years (id),
  CONSTRAINT fk_er_source FOREIGN KEY (source_id) REFERENCES election_sources (id),
  CONSTRAINT fk_er_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_er_province FOREIGN KEY (province_id) REFERENCES provinces (id),
  CONSTRAINT fk_er_city FOREIGN KEY (city_id) REFERENCES cities_municipalities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE election_result_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  result_id INT UNSIGNED NOT NULL,
  action ENUM('submitted','updated','verified','rejected','reopened') NOT NULL,
  remarks VARCHAR(500) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_erv_result (result_id),
  CONSTRAINT fk_erv_result FOREIGN KEY (result_id) REFERENCES election_results (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Public website / CMS
-- ---------------------------------------------------------------------
CREATE TABLE content_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope ENUM('post','document','event','legislative','award','gallery','faq') NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_scope_slug (scope, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,
  title VARCHAR(191) NOT NULL,
  summary VARCHAR(500) NULL,
  body MEDIUMTEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  show_in_footer TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug),
  KEY idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  category_id INT UNSIGNED NULL,
  summary VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  location_name VARCHAR(191) NULL,
  venue_address VARCHAR(255) NULL,
  region_id INT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  organizer VARCHAR(191) NULL,
  contact_email VARCHAR(191) NULL,
  cover_media_id INT UNSIGNED NULL,
  visibility ENUM('public','private') NOT NULL DEFAULT 'public',
  registration_enabled TINYINT(1) NOT NULL DEFAULT 0,
  registration_limit INT UNSIGNED NULL,
  registration_deadline DATETIME NULL,
  event_status ENUM('scheduled','postponed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_events_slug (slug),
  KEY idx_events_start (starts_at),
  KEY idx_events_status (status, visibility),
  CONSTRAINT fk_events_cat FOREIGN KEY (category_id) REFERENCES content_categories (id),
  CONSTRAINT fk_events_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_events_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News articles, announcements, achievements, newsletters, programs,
-- featured activities and publications share one publishing table.
CREATE TABLE posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_type ENUM('news','announcement','achievement','newsletter','program','activity','publication') NOT NULL,
  category_id INT UNSIGNED NULL,
  event_id INT UNSIGNED NULL,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  summary VARCHAR(500) NULL,
  body MEDIUMTEXT NULL,
  cover_media_id INT UNSIGNED NULL,
  attachment_media_id INT UNSIGNED NULL,
  video_url VARCHAR(255) NULL,
  author_name VARCHAR(120) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_type_status (post_type, status, publish_at),
  KEY idx_posts_category (category_id),
  KEY idx_posts_event (event_id),
  CONSTRAINT fk_posts_cat FOREIGN KEY (category_id) REFERENCES content_categories (id),
  CONSTRAINT fk_posts_event FOREIGN KEY (event_id) REFERENCES events (id),
  CONSTRAINT fk_posts_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id),
  CONSTRAINT fk_posts_attach FOREIGN KEY (attachment_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_registrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  reference_code VARCHAR(20) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email_enc TEXT NOT NULL,
  email_bidx CHAR(64) NOT NULL,
  phone_enc TEXT NULL,
  organization VARCHAR(191) NULL,
  cooperative_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  registration_status ENUM('registered','waitlisted','cancelled','attended','no_show') NOT NULL DEFAULT 'registered',
  consent_given TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reg_ref (reference_code),
  UNIQUE KEY uq_reg_event_email (event_id, email_bidx),
  KEY idx_reg_status (registration_status),
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events (id),
  CONSTRAINT fk_reg_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legislative_updates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  measure_type ENUM('house_bill','senate_bill','house_resolution','senate_resolution','joint_resolution','republic_act','executive_issuance','other') NOT NULL DEFAULT 'house_bill',
  measure_number VARCHAR(60) NULL,
  congress VARCHAR(40) NULL,
  legislative_status ENUM('filed','pending_committee','committee_approved','second_reading','third_reading','bicameral','enrolled','enacted','vetoed','adopted','archived','other') NOT NULL DEFAULT 'filed',
  filing_date DATE NULL,
  approval_date DATE NULL,
  sponsors TEXT NULL,
  official_summary MEDIUMTEXT NULL,
  official_source_url VARCHAR(255) NULL,
  is_officially_verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_note VARCHAR(255) NULL,
  commentary MEDIUMTEXT NULL,
  reference_links TEXT NULL,
  document_media_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leg_slug (slug),
  KEY idx_leg_status (status),
  KEY idx_leg_measure (measure_type, legislative_status),
  KEY idx_leg_filing (filing_date),
  CONSTRAINT fk_leg_doc FOREIGN KEY (document_media_id) REFERENCES media_assets (id),
  CONSTRAINT fk_leg_cat FOREIGN KEY (category_id) REFERENCES content_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE awards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  recipient_type ENUM('officer','cooperative','organization','individual') NOT NULL DEFAULT 'cooperative',
  recipient_name VARCHAR(191) NOT NULL,
  officer_id INT UNSIGNED NULL,
  cooperative_id INT UNSIGNED NULL,
  awarding_institution VARCHAR(191) NOT NULL,
  category_id INT UNSIGNED NULL,
  award_date DATE NULL,
  description MEDIUMTEXT NULL,
  cover_media_id INT UNSIGNED NULL,
  document_media_id INT UNSIGNED NULL,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_awards_slug (slug),
  KEY idx_awards_date (award_date),
  KEY idx_awards_status (status),
  CONSTRAINT fk_awards_officer FOREIGN KEY (officer_id) REFERENCES officers (id),
  CONSTRAINT fk_awards_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_awards_cat FOREIGN KEY (category_id) REFERENCES content_categories (id),
  CONSTRAINT fk_awards_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id),
  CONSTRAINT fk_awards_doc FOREIGN KEY (document_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  doc_type ENUM('policy','procedure','guideline','circular','memorandum','manual','form') NOT NULL DEFAULT 'policy',
  category_id INT UNSIGNED NULL,
  reference_no VARCHAR(60) NULL,
  summary VARCHAR(500) NULL,
  visibility ENUM('public','internal') NOT NULL DEFAULT 'public',
  current_version_id INT UNSIGNED NULL,
  effective_date DATE NULL,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_policies_slug (slug),
  KEY idx_policies_type (doc_type),
  KEY idx_policies_status (status, visibility),
  CONSTRAINT fk_policies_cat FOREIGN KEY (category_id) REFERENCES content_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policy_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_id INT UNSIGNED NOT NULL,
  version_label VARCHAR(30) NOT NULL,
  effective_date DATE NULL,
  change_notes VARCHAR(500) NULL,
  body MEDIUMTEXT NULL,
  document_media_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  superseded_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pv_policy (policy_id),
  CONSTRAINT fk_pv_policy FOREIGN KEY (policy_id) REFERENCES policies (id),
  CONSTRAINT fk_pv_doc FOREIGN KEY (document_media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE history_milestones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  milestone_date DATE NULL,
  date_label VARCHAR(60) NOT NULL,
  title VARCHAR(191) NOT NULL,
  description TEXT NULL,
  media_id INT UNSIGNED NULL,
  source_reference VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_hm_date (milestone_date),
  CONSTRAINT fk_hm_media FOREIGN KEY (media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE galleries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(191) NOT NULL,
  description TEXT NULL,
  cover_media_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  event_id INT UNSIGNED NULL,
  is_archival TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gal_slug (slug),
  CONSTRAINT fk_gal_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id),
  CONSTRAINT fk_gal_cat FOREIGN KEY (category_id) REFERENCES content_categories (id),
  CONSTRAINT fk_gal_event FOREIGN KEY (event_id) REFERENCES events (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gallery_id INT UNSIGNED NOT NULL,
  media_id INT UNSIGNED NOT NULL,
  caption VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_gi_gallery (gallery_id),
  CONSTRAINT fk_gi_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE,
  CONSTRAINT fk_gi_media FOREIGN KEY (media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  category_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','in_review','published','archived') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_faq_cat FOREIGN KEY (category_id) REFERENCES content_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_location ENUM('main','footer') NOT NULL DEFAULT 'main',
  parent_id INT UNSIGNED NULL,
  label VARCHAR(80) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  opens_new_tab TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_menu_loc (menu_location, parent_id, sort_order),
  CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value MEDIUMTEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  is_critical TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_code VARCHAR(20) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email_enc TEXT NOT NULL,
  subject VARCHAR(191) NOT NULL,
  message TEXT NOT NULL,
  message_status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  handled_by INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cm_ref (reference_code),
  KEY idx_cm_status (message_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Help desk
-- ---------------------------------------------------------------------
CREATE TABLE helpdesk_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  default_assignee_id INT UNSIGNED NULL,
  default_priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hdc_name (name),
  CONSTRAINT fk_hdc_assignee FOREIGN KEY (default_assignee_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_no VARCHAR(30) NOT NULL,
  access_key_hash CHAR(64) NOT NULL,
  category_id INT UNSIGNED NULL,
  subject VARCHAR(191) NOT NULL,
  description TEXT NOT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  ticket_status ENUM('open','assigned','in_progress','awaiting_response','resolved','closed') NOT NULL DEFAULT 'open',
  is_confidential TINYINT(1) NOT NULL DEFAULT 0,
  requester_name VARCHAR(150) NOT NULL,
  requester_email_enc TEXT NOT NULL,
  requester_email_bidx CHAR(64) NOT NULL,
  requester_phone_enc TEXT NULL,
  cooperative_id INT UNSIGNED NULL,
  cooperative_name VARCHAR(191) NULL,
  region_id INT UNSIGNED NULL,
  submitted_by_user_id INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  first_response_at DATETIME NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_no (ticket_no),
  KEY idx_ticket_status (ticket_status),
  KEY idx_ticket_assigned (assigned_to),
  KEY idx_ticket_category (category_id),
  KEY idx_ticket_created (created_at),
  KEY idx_ticket_email (requester_email_bidx),
  CONSTRAINT fk_ticket_cat FOREIGN KEY (category_id) REFERENCES helpdesk_categories (id),
  CONSTRAINT fk_ticket_coop FOREIGN KEY (cooperative_id) REFERENCES cooperatives (id),
  CONSTRAINT fk_ticket_region FOREIGN KEY (region_id) REFERENCES regions (id),
  CONSTRAINT fk_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES users (id),
  CONSTRAINT fk_ticket_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_replies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  author_type ENUM('staff','requester','system') NOT NULL,
  body TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tr_ticket (ticket_id),
  CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id),
  CONSTRAINT fk_tr_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id INT UNSIGNED NOT NULL,
  reply_id INT UNSIGNED NULL,
  media_id INT UNSIGNED NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ta_ticket (ticket_id),
  KEY idx_ta_media (media_id),
  CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id),
  CONSTRAINT fk_ta_reply FOREIGN KEY (reply_id) REFERENCES ticket_replies (id),
  CONSTRAINT fk_ta_media FOREIGN KEY (media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notifications, approvals, imports, exports
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  notif_type VARCHAR(40) NOT NULL,
  title VARCHAR(191) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  read_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  notif_type VARCHAR(40) NOT NULL,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  email_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  PRIMARY KEY (user_id, notif_type),
  CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_masked VARCHAR(191) NOT NULL,
  subject VARCHAR(191) NOT NULL,
  notif_type VARCHAR(40) NULL,
  delivery_status ENUM('sent','logged','failed','skipped') NOT NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_email_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module VARCHAR(60) NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  request_type ENUM('create','update','role_assignment','import','publish') NOT NULL,
  summary VARCHAR(255) NOT NULL,
  request_status ENUM('pending','approved','rejected','superseded') NOT NULL DEFAULT 'pending',
  requested_by INT UNSIGNED NULL,
  reviewed_by INT UNSIGNED NULL,
  review_remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ar_status (request_status, module),
  KEY idx_ar_record (module, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module VARCHAR(60) NOT NULL,
  original_name VARCHAR(191) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  file_type VARCHAR(10) NOT NULL,
  column_map TEXT NULL,
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
  invalid_rows INT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_rows INT UNSIGNED NOT NULL DEFAULT 0,
  imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
  batch_status ENUM('uploaded','mapped','validated','pending_approval','approved','processing','completed','rejected','failed') NOT NULL DEFAULT 'uploaded',
  uploaded_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ib_module (module, batch_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_rows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id INT UNSIGNED NOT NULL,
  row_no INT UNSIGNED NOT NULL,
  row_data MEDIUMTEXT NOT NULL,
  row_status ENUM('valid','invalid','duplicate','imported','failed') NOT NULL,
  errors TEXT NULL,
  record_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_ir_batch (batch_id, row_status),
  CONSTRAINT fk_ir_batch FOREIGN KEY (batch_id) REFERENCES import_batches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE export_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  module VARCHAR(60) NOT NULL,
  filters VARCHAR(500) NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_el_date (created_at),
  KEY idx_el_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Audit, security, backups, privacy
-- ---------------------------------------------------------------------
-- Hash-chained: entry_hash = sha256(prev_hash + canonical row). The
-- application account should have INSERT/SELECT only on this table.
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  actor_name VARCHAR(150) NULL,
  action VARCHAR(60) NOT NULL,
  module VARCHAR(60) NOT NULL,
  record_id INT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  outcome ENUM('success','failure','denied') NOT NULL DEFAULT 'success',
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  changes MEDIUMTEXT NULL,
  prev_hash CHAR(64) NOT NULL,
  entry_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_date (created_at),
  KEY idx_audit_user (user_id),
  KEY idx_audit_module (module, record_id),
  KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(60) NOT NULL,
  severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'low',
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  description VARCHAR(500) NOT NULL,
  event_status ENUM('open','reviewed','dismissed','escalated') NOT NULL DEFAULT 'open',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_se_status (event_status, severity),
  KEY idx_se_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_incidents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  detected_at DATETIME NOT NULL,
  description TEXT NOT NULL,
  affected_systems VARCHAR(255) NULL,
  affected_data TEXT NULL,
  is_personal_data_breach TINYINT(1) NOT NULL DEFAULT 0,
  actions_taken TEXT NULL,
  npc_notified_at DATETIME NULL,
  subjects_notified_at DATETIME NULL,
  incident_status ENUM('open','contained','resolved','closed') NOT NULL DEFAULT 'open',
  related_event_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_si_status (incident_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(191) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL,
  is_encrypted TINYINT(1) NOT NULL DEFAULT 1,
  backup_status ENUM('completed','failed','deleted') NOT NULL,
  trigger_type ENUM('scheduled','manual') NOT NULL DEFAULT 'manual',
  verified_at DATETIME NULL,
  verify_result VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_br_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE privacy_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_code VARCHAR(20) NOT NULL,
  requester_name VARCHAR(150) NOT NULL,
  requester_email_enc TEXT NOT NULL,
  request_type ENUM('access','correction','deletion','objection','portability','other') NOT NULL,
  details TEXT NOT NULL,
  member_id INT UNSIGNED NULL,
  request_status ENUM('received','in_review','completed','denied') NOT NULL DEFAULT 'received',
  due_date DATE NULL,
  resolution TEXT NULL,
  handled_by INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr_ref (reference_code),
  KEY idx_pr_status (request_status),
  CONSTRAINT fk_pr_member FOREIGN KEY (member_id) REFERENCES members (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Records of processing activities (RA 10173 / NPC accountability)
CREATE TABLE processing_activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  purpose TEXT NOT NULL,
  lawful_basis VARCHAR(191) NOT NULL,
  data_subjects VARCHAR(255) NOT NULL,
  data_categories TEXT NOT NULL,
  includes_sensitive_pi TINYINT(1) NOT NULL DEFAULT 0,
  recipients VARCHAR(255) NULL,
  retention_period VARCHAR(191) NOT NULL,
  security_measures TEXT NULL,
  pia_status ENUM('not_started','in_progress','completed','not_required') NOT NULL DEFAULT 'not_started',
  pia_notes TEXT NULL,
  data_owner VARCHAR(150) NULL,
  last_reviewed_at DATE NULL,
  activity_status ENUM('active','suspended','retired') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE record_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  module VARCHAR(60) NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  action VARCHAR(30) NOT NULL,
  changes MEDIUMTEXT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rev_record (module, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE record_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module VARCHAR(60) NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  media_id INT UNSIGNED NOT NULL,
  title VARCHAR(191) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ra_record (module, record_id),
  KEY idx_ra_media (media_id),
  CONSTRAINT fk_ra_media FOREIGN KEY (media_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
