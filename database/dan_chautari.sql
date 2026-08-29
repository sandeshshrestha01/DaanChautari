-- ============================================================
-- DAAN CHAUTARI — Complete MySQL Database Schema
-- Donation & Volunteer Management System
-- BCA 4th Semester Project
-- ============================================================

CREATE DATABASE IF NOT EXISTS DaanChautari;
USE DaanChautari;

-- ============================================================
-- 1. USERS TABLE (Unified — Donors, Recipients & Admins)
--    role column distinguishes all three user types
-- ============================================================


CREATE TABLE users (
    user_id        INT           AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100)  NOT NULL,
    email          VARCHAR(100)  NOT NULL UNIQUE,
    password       VARCHAR(255)  NOT NULL COMMENT 'bcrypt hashed password',
    phone          VARCHAR(15)   DEFAULT NULL,
    town           VARCHAR(100)  DEFAULT NULL COMMENT 'used for location-based search',
    address        VARCHAR(255)  DEFAULT NULL,
    role           ENUM('donor','recipient','admin') NOT NULL,
    profile_photo  VARCHAR(255)  DEFAULT NULL COMMENT 'profile image file path',
    reset_otp      VARCHAR(6)    DEFAULT NULL COMMENT '6-digit password reset OTP',
    otp_expiry     DATETIME      DEFAULT NULL COMMENT 'Expiration timestamp for OTP',
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. RECIPIENTS TABLE
--    Extended profile info for recipients
--    Linked to users table via user_id (FK)
-- ============================================================

CREATE TABLE recipients (
    recipient_id   INT           AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL UNIQUE COMMENT 'FK to users table',
    reason         TEXT          DEFAULT NULL  COMMENT 'Why they need donations',
    town           VARCHAR(100)  NOT NULL      COMMENT 'Recipient town for matching',
    address        VARCHAR(255)  DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_recipient_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ============================================================
-- 3. DONATIONS TABLE
--    Posted by donors
--    Categories: Food, Clothing, Education, Essential Needs
--    Auto-records donated_at timestamp
-- ============================================================

CREATE TABLE donations (
    donation_id    INT           AUTO_INCREMENT PRIMARY KEY,
    donor_id       INT           NOT NULL COMMENT 'FK to users table (role=donor)',
    title          VARCHAR(150)  NOT NULL,
    category       ENUM(
                       'Food',
                       'Clothing',
                       'Education',
                       'Essential Needs'
                   )             NOT NULL,
    quantity       INT           NOT NULL DEFAULT 1,
    description    TEXT          DEFAULT NULL,
    town           VARCHAR(100)  NOT NULL COMMENT 'Location of donation item',
    img_url        VARCHAR(255)  DEFAULT NULL COMMENT 'Image file path',
    status         ENUM(
                       'available',
                       'requested',
                       'approved',
                       'rejected'
                   )             NOT NULL DEFAULT 'available',
    donated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                                 COMMENT 'Auto-recorded when donor submits',
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_donation_donor
        FOREIGN KEY (donor_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_category (category),
    INDEX idx_town     (town),
    INDEX idx_status   (status),
    INDEX idx_donated_at (donated_at)
);

-- ============================================================
-- 4. DONATION REQUESTS TABLE
--    Submitted by recipients for specific donation items
--    Auto-records requested_at timestamp
--    Admin can approve or reject each request
-- ============================================================

CREATE TABLE donation_requests (
    request_id     INT           AUTO_INCREMENT PRIMARY KEY,
    donation_id    INT           NOT NULL COMMENT 'FK to donations table',
    recipient_id   INT           NOT NULL COMMENT 'FK to recipients table (recipient_id)',
    message        TEXT          DEFAULT NULL COMMENT 'Message from recipient to admin/donor',
    quantity       INT           NOT NULL DEFAULT 1 COMMENT 'How many items requested',
    status         ENUM(
                       'pending',
                       'approved',
                       'rejected'
                   )             NOT NULL DEFAULT 'pending',
    requested_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                                 COMMENT 'Auto-recorded when recipient submits request',
    reviewed_at    TIMESTAMP     DEFAULT NULL
                                 COMMENT 'When admin approved or rejected',
    reviewed_by    INT           DEFAULT NULL COMMENT 'FK to admin table',
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_request_donation
        FOREIGN KEY (donation_id)
        REFERENCES donations(donation_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_request_recipient
        FOREIGN KEY (recipient_id)
        REFERENCES recipients(recipient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_request_admin
        FOREIGN KEY (reviewed_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_request_status     (status),
    INDEX idx_requested_at       (requested_at),
    INDEX idx_request_donation   (donation_id),
    INDEX idx_request_recipient  (recipient_id)
);

-- ============================================================
-- 5. VOLUNTEERS TABLE
--    Public form submission — NO login required
--    Standalone table — no FK to users
--    Auto-records submitted_at timestamp
-- ============================================================

CREATE TABLE volunteers (
    volunteer_id   INT           AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100)  NOT NULL,
    email          VARCHAR(100)  DEFAULT NULL,
    phone          VARCHAR(15)   NOT NULL,
    town           VARCHAR(100)  NOT NULL,
    address        VARCHAR(255)  DEFAULT NULL,
    skills         VARCHAR(255)  NOT NULL COMMENT 'What skills volunteer offers',
    availability   VARCHAR(100)  NOT NULL COMMENT 'Available days/time',
    status         ENUM(
                       'pending',
                       'active',
                       'inactive'
                   )             NOT NULL DEFAULT 'pending'
                                 COMMENT 'Admin sets to active after review',
    submitted_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                                 COMMENT 'Auto-recorded on form submission',
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_volunteer_town   (town),
    INDEX idx_volunteer_status (status)
);

-- ============================================================
-- 6. ACTIVITY LOGS TABLE
--    Tracks all key actions for admin reporting
--    Used to power the date-range report generation
--    user_id covers all roles (donor, recipient, admin)
-- ============================================================

CREATE TABLE activity_logs (
    log_id         INT           AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           DEFAULT NULL COMMENT 'FK to users — covers all roles including admin',
    action         VARCHAR(255)  NOT NULL COMMENT 'e.g. Added donation, Sent request',
    module         VARCHAR(100)  NOT NULL COMMENT 'e.g. donations, requests, volunteers',
    reference_id   INT           DEFAULT NULL COMMENT 'ID of the affected record',
    logged_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_logged_at (logged_at),
    INDEX idx_module    (module)
);

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123 (bcrypt hashed — change before production)
-- ============================================================

INSERT INTO users (full_name, email, password, phone, town, role, status)
VALUES (
    'Super Admin',
    'admin@daanchautari.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '9800000000',
    'Kathmandu',
    'admin',
    'active'
);

-- ============================================================
-- SAMPLE CATEGORIES REFERENCE (for frontend dropdowns)
-- ============================================================
-- Food
-- Clothing
-- Education
-- Essential Needs

-- ============================================================
-- USEFUL QUERIES FOR ADMIN REPORTS
-- ============================================================

-- Total counts for Admin Dashboard overview:
-- SELECT COUNT(*) FROM users WHERE role = 'donor';
-- SELECT COUNT(*) FROM users WHERE role = 'recipient';
-- SELECT COUNT(*) FROM volunteers;
-- SELECT COUNT(*) FROM donations;

-- Report: Donations by date range:
-- SELECT d.donation_id, u.full_name AS donor, d.title, d.category,
--        d.town, d.quantity, d.status, d.donated_at
-- FROM donations d
-- JOIN users u ON d.donor_id = u.user_id
-- WHERE d.donated_at BETWEEN '2024-01-01' AND '2024-12-31'
-- ORDER BY d.donated_at DESC;

-- Report: Requests by date range:
-- SELECT r.request_id, u.full_name AS recipient, d.title AS item,
--        d.category, r.status, r.requested_at
-- FROM donation_requests r
-- JOIN users u ON r.recipient_id = u.user_id
-- JOIN donations d ON r.donation_id = d.donation_id
-- WHERE r.requested_at BETWEEN '2024-01-01' AND '2024-12-31'
-- ORDER BY r.requested_at DESC;

-- Report: Approved items by date range:
-- SELECT r.request_id, u.full_name AS recipient, d.title,
--        d.category, r.reviewed_at
-- FROM donation_requests r
-- JOIN users u ON r.recipient_id = u.user_id
-- JOIN donations d ON r.donation_id = d.donation_id
-- WHERE r.status = 'approved'
-- AND r.reviewed_at BETWEEN '2024-01-01' AND '2024-12-31';

-- Recent donations for home page:
-- SELECT d.donation_id, u.full_name AS donor, d.title,
--        d.category, d.town, d.quantity, d.img_url, d.donated_at
-- FROM donations d
-- JOIN users u ON d.donor_id = u.user_id
-- WHERE d.status = 'available'
-- ORDER BY d.donated_at DESC
-- LIMIT 8;

-- Search donations by town and category:
-- SELECT d.*, u.full_name AS donor_name
-- FROM donations d
-- JOIN users u ON d.donor_id = u.user_id
-- WHERE d.status = 'available'
-- AND d.town LIKE '%kathmandu%'
-- AND d.category = 'Food'
-- ORDER BY d.donated_at DESC;