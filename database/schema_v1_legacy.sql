-- Member Association Collection Management System
-- Database Schema
-- Includes: Members, Collections, Payments (with verification), Reconciliation, Audit Logs

-- ============================================
-- CORE TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS associations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    logo_path VARCHAR(255),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    member_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) GENERATED ALWAYS AS (CONCAT(first_name, ' ', last_name)) STORED,
    phone VARCHAR(20),
    email VARCHAR(100),
    gender ENUM('M', 'F', 'Other') DEFAULT NULL,
    date_of_birth DATE,
    address TEXT,
    occupation VARCHAR(100),
    branch_id INT DEFAULT NULL,
    next_of_kin VARCHAR(255),
    passport_photo_path VARCHAR(255),
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    date_joined DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    INDEX idx_member_id (member_id),
    INDEX idx_status (status),
    INDEX idx_branch (branch_id)
);

CREATE TABLE IF NOT EXISTS branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_code_per_assoc (association_id, code)
);

ALTER TABLE members ADD FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

-- ============================================
-- COLLECTION ITEMS
-- ============================================

CREATE TABLE IF NOT EXISTS collection_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10, 2),
    type ENUM('Recurring', 'One-time', 'Voluntary') DEFAULT 'Recurring',
    frequency ENUM('Monthly', 'Quarterly', 'Yearly', 'One-time', 'Custom') DEFAULT 'Monthly',
    is_required BOOLEAN DEFAULT TRUE,
    start_date DATE,
    due_date DATE,
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_association (association_id)
);

CREATE TABLE IF NOT EXISTS member_collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    collection_item_id INT NOT NULL,
    assignment_date DATE DEFAULT CURDATE(),
    status ENUM('Active', 'Completed', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_collection (member_id, collection_item_id),
    INDEX idx_status (status)
);

-- ============================================
-- USERS & ROLES (MUST EXIST BEFORE PAYMENTS FKs)
-- ============================================

CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    role_id INT NOT NULL,

    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255),

    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),

    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    last_login TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_association (association_id),
    INDEX idx_role (role_id),
    INDEX idx_status (status)
);

-- ============================================
-- PAYMENTS (CORE)
-- ============================================

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    member_id INT NOT NULL,
    collection_item_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Mobile Money', 'Bank Transfer', 'USSD', 'Card') DEFAULT 'Cash',
    payment_date DATE NOT NULL,
    payment_time TIME,
    transaction_reference VARCHAR(100),
    receipt_number VARCHAR(50),

    -- Status tracking
    status ENUM(
        'Pending_Entry',
        'Pending_Verification',
        'Pending_Reconciliation',
        'Verified',
        'Confirmed',
        'Failed',
        'Timeout',
        'Refunded'
    ) DEFAULT 'Pending_Entry',

    -- Entry and verification
    recorded_by INT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    confirmed_by INT,
    confirmed_at TIMESTAMP NULL,

    -- Notes
    notes TEXT,

    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE RESTRICT,
    FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    FOREIGN KEY (confirmed_by) REFERENCES users(id),

    INDEX idx_member (member_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date),
    INDEX idx_method (payment_method),
    INDEX idx_association (association_id),
    INDEX idx_transaction_ref (transaction_reference)
);

-- ============================================
-- PAYMENT VERIFICATION
-- ============================================

CREATE TABLE IF NOT EXISTS payment_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,

    -- Checks performed
    duplicate_check BOOLEAN DEFAULT FALSE,
    duplicate_check_result BOOLEAN DEFAULT FALSE,
    amount_validation BOOLEAN DEFAULT FALSE,
    amount_validation_result BOOLEAN DEFAULT FALSE,
    status_check BOOLEAN DEFAULT FALSE,
    status_check_result BOOLEAN DEFAULT FALSE,
    fraud_check BOOLEAN DEFAULT FALSE,
    fraud_check_result BOOLEAN DEFAULT FALSE,

    -- Overall result
    verification_result ENUM('Pass', 'Fail', 'Manual_Review_Required') DEFAULT 'Fail',
    failure_reason VARCHAR(255),

    -- Manual verification
    manually_verified BOOLEAN DEFAULT FALSE,
    manual_verified_by INT,
    manual_verified_at TIMESTAMP NULL,
    manual_verification_notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (manual_verified_by) REFERENCES users(id),
    INDEX idx_payment (payment_id),
    INDEX idx_result (verification_result)
);

-- ============================================
-- RECONCILIATION
-- ============================================

CREATE TABLE IF NOT EXISTS reconciliation_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    reconciliation_type ENUM('Cash_End_of_Day', 'Cash_Mid_Day', 'Digital_Auto', 'Manual') DEFAULT 'Cash_End_of_Day',

    -- Batch info
    reconciliation_date DATE NOT NULL,
    reconciliation_time TIME,
    start_time DATETIME,
    end_time DATETIME,

    -- Amounts
    total_expected DECIMAL(12, 2),
    total_recorded DECIMAL(12, 2),
    total_discrepancy DECIMAL(12, 2),

    -- Status
    status ENUM('Open', 'Pending_Review', 'Resolved', 'Closed') DEFAULT 'Open',

    -- Who reconciled
    reconciled_by INT,
    reviewed_by INT,
    closed_by INT,

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (reconciled_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (closed_by) REFERENCES users(id),
    INDEX idx_association (association_id),
    INDEX idx_date (reconciliation_date),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS reconciliation_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id INT NOT NULL,
    payment_id INT,

    action ENUM('Include', 'Exclude', 'Flag_For_Review', 'Correct_Amount') DEFAULT 'Include',
    original_amount DECIMAL(10, 2),
    corrected_amount DECIMAL(10, 2),

    discrepancy_reason VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (batch_id) REFERENCES reconciliation_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_batch (batch_id)
);

-- ============================================
-- RECEIPTS
-- ============================================

CREATE TABLE IF NOT EXISTS receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL UNIQUE,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,

    association_name VARCHAR(255),
    logo_path VARCHAR(255),

    member_name VARCHAR(255),
    member_id_number VARCHAR(50),

    amount DECIMAL(10, 2),
    collection_item_name VARCHAR(100),
    payment_method VARCHAR(50),
    transaction_reference VARCHAR(100),

    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by INT,

    qr_code_path VARCHAR(255),
    pdf_path VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_payment (payment_id)
);

-- ============================================
-- AUDIT LOGS
-- ============================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT,

    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,

    user_id INT,
    user_role VARCHAR(50),

    previous_value VARCHAR(500),
    new_value VARCHAR(500),

    ip_address VARCHAR(45),
    user_agent VARCHAR(500),

    status ENUM('Success', 'Failed', 'Attempted') DEFAULT 'Success',
    error_message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_association (association_id),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- NOTIFICATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,

    notification_type ENUM('Payment_Received', 'Arrears_Alert', 'Overpayment', 'Refund', 'Collection_Assigned') DEFAULT 'Payment_Received',

    channel ENUM('SMS', 'Email', 'In_App') DEFAULT 'SMS',
    recipient_value VARCHAR(255),

    message_template VARCHAR(50),
    message_body TEXT,

    status ENUM('Pending', 'Sent', 'Delivered', 'Failed') DEFAULT 'Pending',
    sent_at TIMESTAMP NULL,

    reference_type VARCHAR(50),
    reference_id INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member (member_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- USERS & ROLES already defined before PAYMENTS section.

-- ============================================
-- DONATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    association_id INT NOT NULL,
    member_id INT,

    donor_name VARCHAR(255),
    donor_phone VARCHAR(20),
    donor_email VARCHAR(100),

    amount DECIMAL(10, 2) NOT NULL,
    purpose VARCHAR(255),
    payment_method ENUM('Cash', 'Mobile Money', 'Bank Transfer', 'USSD', 'Card'),

    transaction_reference VARCHAR(100),
    receipt_number VARCHAR(50),

    is_anonymous BOOLEAN DEFAULT FALSE,
    is_member_donation BOOLEAN DEFAULT FALSE,

    donation_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_association (association_id),
    INDEX idx_member (member_id),
    INDEX idx_date (donation_date)
);

-- ============================================
-- ARREARS VIEW
-- ============================================

CREATE VIEW IF NOT EXISTS member_arrears AS
SELECT
    m.id AS member_id,
    m.member_id AS member_id_number,
    m.full_name,
    ci.id AS collection_item_id,
    ci.name AS collection_name,
    ci.amount,
    COUNT(mc.id) AS expected_payments_count,
    COALESCE(COUNT(DISTINCT p.id), 0) AS paid_count,
    COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) AS total_paid,
    (ci.amount * COUNT(mc.id)) - COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) AS balance_owed,
    CASE
        WHEN COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) = 0 THEN 'Defaulter'
        WHEN COALESCE(SUM(CASE WHEN p.status = 'Confirmed' THEN p.amount ELSE 0 END), 0) >= (ci.amount * COUNT(mc.id)) THEN 'Paid'
        ELSE 'Partial'
    END AS status
FROM
    members m
    JOIN member_collections mc ON m.id = mc.member_id
    JOIN collection_items ci ON mc.collection_item_id = ci.id
    LEFT JOIN payments p ON m.id = p.member_id AND ci.id = p.collection_item_id AND p.status = 'Confirmed'
WHERE
    m.status = 'Active'
    AND mc.status = 'Active'
    AND ci.status = 'Active'
GROUP BY
    m.id, ci.id;

-- ============================================
-- DEFAULT ROLES
-- ============================================

INSERT INTO roles (name, description) VALUES
('Administrator', 'Full system access and management'),
('Treasurer', 'Payment recording and financial operations'),
('Secretary', 'Member management and records'),
('Auditor', 'Read-only oversight role'),
('Member', 'Limited member portal access');

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_payments_member_date ON payments(member_id, payment_date);
CREATE INDEX idx_payments_status_date ON payments(status, payment_date);
CREATE INDEX idx_member_collections_member ON member_collections(member_id);
CREATE INDEX idx_audit_logs_timestamp ON audit_logs(created_at DESC);
