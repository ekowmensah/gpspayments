-- Member Association Collection Management System
-- Database Schema v2 (MySQL 8+)
-- Generated: 2026-05-21

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS associations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255) NULL,
    registration_number VARCHAR(100) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    address TEXT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Accra',
    logo_path VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_associations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_units (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    parent_unit_id BIGINT UNSIGNED NULL,
    unit_type ENUM('branch', 'zone', 'department', 'unit', 'chapter', 'class', 'electoral_area', 'local_group', 'other') NOT NULL DEFAULT 'branch',
    code VARCHAR(50) NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_units_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_units_parent
        FOREIGN KEY (parent_unit_id) REFERENCES org_units(id) ON DELETE SET NULL,
    UNIQUE KEY uq_org_units_assoc_code (association_id, code),
    UNIQUE KEY uq_org_units_assoc_name_parent (association_id, name, parent_unit_id),
    KEY idx_org_units_assoc (association_id),
    KEY idx_org_units_parent (parent_unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    member_code VARCHAR(60) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) GENERATED ALWAYS AS (
        TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name))
    ) STORED,
    phone VARCHAR(30) NULL,
    alt_phone VARCHAR(30) NULL,
    email VARCHAR(191) NULL,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
    date_of_birth DATE NULL,
    address TEXT NULL,
    occupation VARCHAR(120) NULL,
    date_joined DATE NOT NULL,
    status ENUM('active', 'inactive', 'suspended', 'exited', 'deceased') NOT NULL DEFAULT 'active',
    status_reason VARCHAR(255) NULL,
    next_of_kin_name VARCHAR(255) NULL,
    next_of_kin_phone VARCHAR(30) NULL,
    next_of_kin_relationship VARCHAR(100) NULL,
    photo_path VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_members_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_members_unit
        FOREIGN KEY (unit_id) REFERENCES org_units(id) ON DELETE SET NULL,
    UNIQUE KEY uq_members_assoc_member_code (association_id, member_code),
    UNIQUE KEY uq_members_assoc_email (association_id, email),
    KEY idx_members_assoc_status (association_id, status),
    KEY idx_members_unit (unit_id),
    KEY idx_members_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_status_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    member_id BIGINT UNSIGNED NOT NULL,
    from_status ENUM('active', 'inactive', 'suspended', 'exited', 'deceased') NULL,
    to_status ENUM('active', 'inactive', 'suspended', 'exited', 'deceased') NOT NULL,
    reason VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_status_history_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    KEY idx_member_status_history_member (member_id),
    KEY idx_member_status_history_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    is_mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended', 'locked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_users_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_users_assoc_username (association_id, username),
    UNIQUE KEY uq_users_assoc_email (association_id, email),
    UNIQUE KEY uq_users_member (member_id),
    KEY idx_users_assoc_status (association_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_roles_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_roles_assoc_name (association_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('dues', 'levy', 'welfare', 'subscription', 'special_fundraising', 'donation', 'other') NOT NULL DEFAULT 'dues',
    charge_type ENUM('recurring', 'one_time', 'voluntary') NOT NULL DEFAULT 'recurring',
    frequency ENUM('monthly', 'quarterly', 'yearly', 'one_time', 'custom') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(12,2) NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    allow_partial_payment TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    due_day_of_month TINYINT UNSIGNED NULL,
    grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    penalty_type ENUM('none', 'fixed', 'percent') NOT NULL DEFAULT 'none',
    penalty_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    applies_scope ENUM('all_members', 'selected_units', 'selected_members') NOT NULL DEFAULT 'all_members',
    status ENUM('draft', 'active', 'paused', 'archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_collection_items_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_items_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_collection_items_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_collection_items_assoc_code (association_id, code),
    KEY idx_collection_items_assoc_status (association_id, status),
    KEY idx_collection_items_scope (applies_scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_item_units (
    collection_item_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_item_id, unit_id),
    CONSTRAINT fk_collection_item_units_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_item_units_unit
        FOREIGN KEY (unit_id) REFERENCES org_units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collection_item_members (
    collection_item_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_item_id, member_id),
    CONSTRAINT fk_collection_item_members_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_item_members_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_periods (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    period_code VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('open', 'closed', 'locked') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_billing_periods_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_billing_period_assoc_code (association_id, period_code),
    KEY idx_billing_period_assoc_dates (association_id, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS charge_runs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    collection_item_id BIGINT UNSIGNED NOT NULL,
    billing_period_id BIGINT UNSIGNED NULL,
    run_type ENUM('scheduled', 'manual', 'backfill') NOT NULL DEFAULT 'scheduled',
    run_status ENUM('queued', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    generated_count INT UNSIGNED NOT NULL DEFAULT 0,
    expected_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    error_message VARCHAR(500) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_charge_runs_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_charge_runs_collection_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_charge_runs_billing_period
        FOREIGN KEY (billing_period_id) REFERENCES billing_periods(id) ON DELETE SET NULL,
    CONSTRAINT fk_charge_runs_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_charge_runs_assoc_status (association_id, run_status),
    KEY idx_charge_runs_item (collection_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_charges (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    charge_reference VARCHAR(80) NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    collection_item_id BIGINT UNSIGNED NOT NULL,
    billing_period_id BIGINT UNSIGNED NULL,
    charge_run_id BIGINT UNSIGNED NULL,
    charge_date DATE NOT NULL,
    due_date DATE NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    waived_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('open', 'partial', 'paid', 'waived', 'cancelled') NOT NULL DEFAULT 'open',
    status_updated_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_charges_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_charges_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_charges_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_member_charges_period
        FOREIGN KEY (billing_period_id) REFERENCES billing_periods(id) ON DELETE SET NULL,
    CONSTRAINT fk_member_charges_run
        FOREIGN KEY (charge_run_id) REFERENCES charge_runs(id) ON DELETE SET NULL,
    CONSTRAINT fk_member_charges_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_member_charges_assoc_reference (association_id, charge_reference),
    KEY idx_member_charges_member_status (member_id, status),
    KEY idx_member_charges_due_date (due_date),
    KEY idx_member_charges_item_period (collection_item_id, billing_period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_intents (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    intent_reference VARCHAR(80) NOT NULL,
    member_id BIGINT UNSIGNED NULL,
    collection_item_id BIGINT UNSIGNED NULL,
    payer_type ENUM('member', 'non_member', 'anonymous') NOT NULL DEFAULT 'member',
    payer_name VARCHAR(255) NULL,
    payer_phone VARCHAR(30) NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'ussd', 'card') NOT NULL,
    provider_name VARCHAR(100) NULL,
    provider_intent_reference VARCHAR(120) NULL,
    idempotency_key VARCHAR(120) NULL,
    status ENUM('initiated', 'pending', 'success', 'failed', 'expired', 'cancelled') NOT NULL DEFAULT 'initiated',
    expires_at DATETIME NULL,
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_intents_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_intents_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_intents_collection_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_intents_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payment_intents_assoc_ref (association_id, intent_reference),
    UNIQUE KEY uq_payment_intents_assoc_idempotency (association_id, idempotency_key),
    KEY idx_payment_intents_assoc_status (association_id, status),
    KEY idx_payment_intents_provider_ref (provider_name, provider_intent_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    payment_reference VARCHAR(80) NOT NULL,
    payment_intent_id BIGINT UNSIGNED NULL,
    member_id BIGINT UNSIGNED NULL,
    collection_item_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    payment_method ENUM('cash', 'mobile_money', 'bank_transfer', 'ussd', 'card') NOT NULL,
    source ENUM('manual_entry', 'provider_callback', 'import') NOT NULL DEFAULT 'manual_entry',
    transaction_reference VARCHAR(120) NULL,
    provider_name VARCHAR(100) NULL,
    provider_transaction_reference VARCHAR(120) NULL,
    idempotency_key VARCHAR(120) NULL,
    payment_date DATETIME NOT NULL,
    posting_date DATE NOT NULL,
    status ENUM('recorded', 'pending_verification', 'verified', 'posted', 'failed', 'reversed', 'refunded', 'voided') NOT NULL DEFAULT 'recorded',
    reversal_reason VARCHAR(255) NULL,
    notes VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_intent
        FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_item
        FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_recorded_by
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payments_assoc_ref (association_id, payment_reference),
    UNIQUE KEY uq_payments_assoc_idempotency (association_id, idempotency_key),
    KEY idx_payments_assoc_status_date (association_id, status, posting_date),
    KEY idx_payments_member_date (member_id, posting_date),
    KEY idx_payments_txn_ref (provider_name, provider_transaction_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_callbacks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    provider_event_id VARCHAR(150) NOT NULL,
    provider_transaction_reference VARCHAR(120) NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    processing_status ENUM('received', 'processed', 'duplicate', 'invalid', 'failed') NOT NULL DEFAULT 'received',
    error_message VARCHAR(500) NULL,
    payload JSON NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    payment_id BIGINT UNSIGNED NULL,
    CONSTRAINT fk_payment_callbacks_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_callbacks_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payment_callbacks_provider_event (provider_name, provider_event_id),
    KEY idx_payment_callbacks_assoc_status (association_id, processing_status),
    KEY idx_payment_callbacks_txn_ref (provider_transaction_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_allocations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    member_charge_id BIGINT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(12,2) NOT NULL,
    allocation_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_allocations_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_allocations_charge
        FOREIGN KEY (member_charge_id) REFERENCES member_charges(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_payment_allocations_payment_charge (payment_id, member_charge_id),
    KEY idx_payment_allocations_charge (member_charge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    receipt_number VARCHAR(60) NOT NULL,
    receipt_type ENUM('payment', 'donation', 'refund', 'reversal') NOT NULL DEFAULT 'payment',
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by BIGINT UNSIGNED NULL,
    qr_token VARCHAR(120) NULL,
    pdf_path VARCHAR(255) NULL,
    verification_hash VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipts_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_receipts_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT fk_receipts_issued_by
        FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_receipts_assoc_number (association_id, receipt_number),
    UNIQUE KEY uq_receipts_payment (payment_id),
    KEY idx_receipts_issued_at (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    member_id BIGINT UNSIGNED NULL,
    donor_type ENUM('member', 'non_member', 'anonymous') NOT NULL DEFAULT 'member',
    donor_name VARCHAR(255) NULL,
    donor_phone VARCHAR(30) NULL,
    donor_email VARCHAR(191) NULL,
    purpose VARCHAR(255) NULL,
    project_code VARCHAR(100) NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    donation_date DATE NOT NULL,
    received_by BIGINT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_donations_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_donations_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    CONSTRAINT fk_donations_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_donations_received_by
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_donations_assoc_date (association_id, donation_date),
    KEY idx_donations_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    settlement_reference VARCHAR(120) NOT NULL,
    settlement_date DATE NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'GHS',
    expected_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    provider_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    variance_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('open', 'matched', 'variance', 'closed') NOT NULL DEFAULT 'open',
    imported_by BIGINT UNSIGNED NULL,
    imported_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settlements_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_settlements_imported_by
        FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_settlements_provider_ref (provider_name, settlement_reference),
    KEY idx_settlements_assoc_date (association_id, settlement_date),
    KEY idx_settlements_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlement_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    settlement_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    provider_transaction_reference VARCHAR(120) NOT NULL,
    provider_amount DECIMAL(12,2) NOT NULL,
    system_amount DECIMAL(12,2) NULL,
    variance_amount DECIMAL(12,2) NULL,
    match_status ENUM('matched', 'amount_mismatch', 'missing_in_system', 'missing_in_provider') NOT NULL DEFAULT 'matched',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settlement_items_settlement
        FOREIGN KEY (settlement_id) REFERENCES settlements(id) ON DELETE CASCADE,
    CONSTRAINT fk_settlement_items_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    KEY idx_settlement_items_settlement (settlement_id),
    KEY idx_settlement_items_txn_ref (provider_transaction_reference),
    KEY idx_settlement_items_match_status (match_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reconciliation_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    reconciliation_reference VARCHAR(80) NOT NULL,
    reconciliation_type ENUM('cash_end_of_day', 'cash_mid_day', 'digital_auto', 'manual') NOT NULL DEFAULT 'cash_end_of_day',
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    expected_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    recorded_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discrepancy_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('open', 'pending_review', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    reconciled_by BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reconciliation_batches_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_reconciliation_batches_reconciled_by
        FOREIGN KEY (reconciled_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reconciliation_batches_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reconciliation_batches_closed_by
        FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_reconciliation_batches_assoc_ref (association_id, reconciliation_reference),
    KEY idx_reconciliation_batches_assoc_status (association_id, status),
    KEY idx_reconciliation_batches_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reconciliation_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    action ENUM('include', 'exclude', 'flag_review', 'correct_amount') NOT NULL DEFAULT 'include',
    expected_amount DECIMAL(12,2) NULL,
    recorded_amount DECIMAL(12,2) NULL,
    corrected_amount DECIMAL(12,2) NULL,
    discrepancy_reason VARCHAR(255) NULL,
    resolution_note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reconciliation_items_batch
        FOREIGN KEY (batch_id) REFERENCES reconciliation_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_reconciliation_items_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    KEY idx_reconciliation_items_batch (batch_id),
    KEY idx_reconciliation_items_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    channel ENUM('sms', 'email', 'in_app') NOT NULL DEFAULT 'sms',
    subject VARCHAR(191) NULL,
    body TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_templates_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_notification_templates_assoc_code (association_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NULL,
    member_id BIGINT UNSIGNED NULL,
    channel ENUM('sms', 'email', 'in_app') NOT NULL,
    recipient VARCHAR(191) NOT NULL,
    message_subject VARCHAR(191) NULL,
    message_body TEXT NOT NULL,
    reference_type ENUM('member_charge', 'payment', 'donation', 'generic') NOT NULL DEFAULT 'generic',
    reference_id BIGINT UNSIGNED NULL,
    provider_name VARCHAR(100) NULL,
    provider_message_id VARCHAR(150) NULL,
    delivery_status ENUM('pending', 'queued', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
    error_message VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_logs_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_logs_template
        FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_logs_member
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    KEY idx_notification_logs_assoc_status (association_id, delivery_status),
    KEY idx_notification_logs_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_role VARCHAR(80) NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    change_summary VARCHAR(255) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    request_id VARCHAR(120) NULL,
    status ENUM('success', 'failed', 'attempted') NOT NULL DEFAULT 'success',
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_actor_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_audit_logs_assoc_created (association_id, created_at),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    association_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_association
        FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    CONSTRAINT fk_system_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_system_settings_assoc_key (association_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Derived view for quick arrears and payment status reporting.
CREATE OR REPLACE VIEW v_member_balances AS
SELECT
    mc.association_id,
    mc.member_id,
    m.member_code,
    m.full_name,
    SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) AS total_expected,
    COALESCE(SUM(pa.total_paid), 0.00) AS total_paid,
    SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(SUM(pa.total_paid), 0.00) AS outstanding_balance
FROM member_charges mc
JOIN members m ON m.id = mc.member_id
LEFT JOIN (
    SELECT member_charge_id, SUM(allocated_amount) AS total_paid
    FROM payment_allocations
    GROUP BY member_charge_id
) pa ON pa.member_charge_id = mc.id
WHERE mc.status IN ('open', 'partial', 'paid')
GROUP BY mc.association_id, mc.member_id, m.member_code, m.full_name;

-- Starter permission catalog.
INSERT IGNORE INTO permissions (code, description) VALUES
('members.view', 'View members'),
('members.create', 'Create members'),
('members.edit', 'Edit member records'),
('collections.view', 'View collection items'),
('collections.create', 'Create collection items'),
('collections.assign', 'Assign collection items'),
('payments.record', 'Record payments'),
('payments.verify', 'Verify and approve payments'),
('payments.void', 'Void posted payments'),
('payments.refund', 'Process refunds'),
('receipts.view', 'View receipts'),
('reports.view', 'View reports'),
('reports.export', 'Export reports'),
('arrears.view', 'View arrears'),
('reconciliation.manage', 'Manage reconciliation'),
('users.manage', 'Manage users and roles'),
('settings.manage', 'Manage system settings'),
('audit.view', 'View audit logs'),
('notifications.send', 'Send reminders and notices');
