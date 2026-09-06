-- =====================================================
-- DMP AI Digital Institute - CRM Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS dmp_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dmp_crm;

-- ---------------------------
-- Users (Admin / Manager / Counselor)
-- ---------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    photo_path VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','counselor') NOT NULL DEFAULT 'counselor',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    monthly_target INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_resets_expiry (expires_at)
) ENGINE=InnoDB;

-- ---------------------------
-- Courses
-- ---------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    duration VARCHAR(50),
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------
-- Batches
-- ---------------------------
CREATE TABLE batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    start_date DATE,
    timing VARCHAR(100),
    mode ENUM('online','offline','hybrid') DEFAULT 'offline',
    status ENUM('upcoming','running','completed') DEFAULT 'upcoming',
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------
-- Leads
-- ---------------------------
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150),
    city VARCHAR(100),
    source ENUM('facebook','google','instagram','whatsapp','referral','walk-in','website','other') DEFAULT 'other',
    course_id INT,
    status ENUM('new','contacted','follow-up','interested','not-interested','converted','junk') DEFAULT 'new',
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ---------------------------
-- Lead Notes / Remarks (timeline)
-- ---------------------------
CREATE TABLE lead_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    note TEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------
-- Follow-ups
-- ---------------------------
CREATE TABLE follow_ups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    follow_up_date DATETIME NOT NULL,
    remarks VARCHAR(255),
    status ENUM('pending','done','missed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------
-- Admissions (converted leads / students)
-- ---------------------------
CREATE TABLE admissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_code VARCHAR(30) NOT NULL UNIQUE,
    lead_id INT,
    student_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150),
    course_id INT NOT NULL,
    batch_id INT,
    counselor_id INT,
    admission_date DATE NOT NULL,
    total_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending-docs','confirmed','cancelled') DEFAULT 'pending-docs',
    id_proof_path VARCHAR(255),
    photo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------
-- Payments
-- ---------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    mode ENUM('cash','upi','bank-transfer','card','cheque') DEFAULT 'cash',
    payment_date DATE NOT NULL,
    remarks VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------
-- Activity Logs
-- ---------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------
-- Seed: default admin (password: Admin@123 -> hashed below)
-- Change this password immediately after first login.
-- Hash generated with PHP password_hash('Admin@123', PASSWORD_BCRYPT)
-- ---------------------------
INSERT INTO users (name, email, password, role, status) VALUES
('Super Admin', 'admin@dmpschool.com', '$2y$10$29GiQUfn.xkoefZB1NbU/eggSJfA/8UclXetK2X6V2CueYSTXFpti', 'admin', 'active');

-- Seed: a couple of sample courses
INSERT INTO courses (name, duration, fee, description) VALUES
('Digital Marketing Master Course', '6 Months', 45000.00, 'SEO, SEM, Social Media, Content, Analytics'),
('Performance Marketing (Meta & Google Ads)', '2 Months', 20000.00, 'Paid ads specialisation'),
('SEO Specialist Course', '2 Months', 15000.00, 'On-page, off-page, technical SEO');
