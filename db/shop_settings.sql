USE pharmacy_mgmt;

-- Shop settings table
CREATE TABLE IF NOT EXISTS shop_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL COMMENT 'NULL = global setting',
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_branch_setting (branch_id, setting_key),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Insert default settings
INSERT IGNORE INTO shop_settings (branch_id, setting_key, setting_value) VALUES
(NULL, 'shop_name', 'PharmaCare Pro'),
(NULL, 'shop_tagline', 'Your Trusted Pharmacy Partner'),
(NULL, 'opening_time', '08:00'),
(NULL, 'closing_time', '20:00'),
(NULL, 'working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'),
(NULL, 'currency_symbol', 'ETB'),
(NULL, 'tax_rate', '0'),
(NULL, 'receipt_footer', 'Thank you for your purchase!'),
(NULL, 'low_stock_alert_email', 'admin@pharmacy.com'),
(NULL, 'enable_sms_notifications', '0'),
(NULL, 'sms_api_key', ''),
(NULL, 'enable_email_notifications', '0'),
(NULL, 'smtp_host', ''),
(NULL, 'smtp_port', '587'),
(NULL, 'smtp_username', ''),
(NULL, 'smtp_password', ''),
(NULL, 'enable_barcode_scanner', '0'),
(NULL, 'auto_backup_enabled', '0'),
(NULL, 'backup_frequency', 'daily');

-- Add shop status to branches
ALTER TABLE branches ADD COLUMN IF NOT EXISTS is_open TINYINT(1) DEFAULT 1 COMMENT '1=open, 0=closed';
ALTER TABLE branches ADD COLUMN IF NOT EXISTS opening_time TIME DEFAULT '08:00:00';
ALTER TABLE branches ADD COLUMN IF NOT EXISTS closing_time TIME DEFAULT '20:00:00';

-- Customer loyalty points
ALTER TABLE customers ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS last_purchase_date DATE NULL;

-- Add cost tracking to sale_items for profit calculation
ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS cost_price DECIMAL(10,2) DEFAULT 0.00;

-- Prescription tracking
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    doctor_name VARCHAR(150),
    prescription_number VARCHAR(100) UNIQUE,
    prescription_date DATE,
    expiry_date DATE,
    notes TEXT,
    image_path VARCHAR(255),
    status ENUM('active','expired','used') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Medicine interactions/warnings
CREATE TABLE IF NOT EXISTS medicine_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    interacts_with_medicine_id INT NOT NULL,
    severity ENUM('mild','moderate','severe') DEFAULT 'moderate',
    description TEXT,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (interacts_with_medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Shift management
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    opening_cash DECIMAL(10,2) DEFAULT 0.00,
    closing_cash DECIMAL(10,2) DEFAULT 0.00,
    total_sales DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('open','closed') DEFAULT 'open',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
