USE pharmacy_mgmt;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL = all users in branch',
    branch_id INT NULL COMMENT 'NULL = all branches',
    type ENUM('low_stock','expiry','sale','system','payment') DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Payments table (Chapa / Telebirr)
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    tx_ref VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'ETB',
    payment_gateway ENUM('chapa','telebirr','cash','card','mobile_money') DEFAULT 'chapa',
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    status ENUM('pending','success','failed','cancelled') DEFAULT 'pending',
    gateway_response TEXT,
    checkout_url TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Daily report cache
CREATE TABLE IF NOT EXISTS daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    report_date DATE NOT NULL,
    total_sales INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    total_items_sold INT DEFAULT 0,
    total_cost DECIMAL(12,2) DEFAULT 0.00,
    gross_profit DECIMAL(12,2) DEFAULT 0.00,
    top_medicine VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_branch_date (branch_id, report_date),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- Payment settings
CREATE TABLE IF NOT EXISTS payment_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway VARCHAR(50) NOT NULL UNIQUE,
    is_enabled TINYINT(1) DEFAULT 0,
    is_test_mode TINYINT(1) DEFAULT 1,
    public_key TEXT,
    secret_key TEXT,
    webhook_secret TEXT,
    extra_config TEXT COMMENT 'JSON for extra settings',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO payment_settings (gateway, is_enabled, is_test_mode, public_key, secret_key) VALUES
('chapa', 1, 1, 'CHAPUBK-TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'CHASECK-TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
('telebirr', 0, 1, '', '');

-- Add expiry_warning_days to medicines
ALTER TABLE medicines ADD COLUMN IF NOT EXISTS expiry_warning_days INT DEFAULT 30;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    branch_id INT,
    credit_balance DECIMAL(10,2) DEFAULT 0.00,
    total_purchases DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

INSERT IGNORE INTO customers (name, phone, branch_id) VALUES
('Walk-in Customer', '0000000000', 1),
('Abebe Kebede', '0911234567', 1),
('Tigist Haile', '0922345678', 1),
('Dawit Bekele', '0933456789', 2);
