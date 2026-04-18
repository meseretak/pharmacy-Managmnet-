USE pharmacy_mgmt;

-- =============================================
-- MISSING FEATURES SCHEMA
-- =============================================

-- 1. Stock Adjustments (damage, loss, correction, transfer)
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    adjustment_type ENUM('damage','loss','correction','transfer_out','transfer_in','expiry_removal') NOT NULL,
    quantity_before INT NOT NULL,
    quantity_change INT NOT NULL COMMENT 'negative = reduction, positive = addition',
    quantity_after INT NOT NULL,
    reason TEXT,
    reference_id INT NULL COMMENT 'transfer pair ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stock(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 2. Stock Transfers between branches
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
    requested_by INT NOT NULL,
    approved_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Refunds / Returns
CREATE TABLE IF NOT EXISTS sale_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    refund_type ENUM('full','partial') DEFAULT 'full',
    restock TINYINT(1) DEFAULT 1 COMMENT 'whether to return items to stock',
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sale_refund_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    refund_id INT NOT NULL,
    sale_item_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (refund_id) REFERENCES sale_refunds(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_item_id) REFERENCES sale_items(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- 4. Prescriptions
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    doctor_name VARCHAR(100),
    doctor_license VARCHAR(50),
    hospital_clinic VARCHAR(150),
    issue_date DATE,
    expiry_date DATE,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    sale_id INT NULL,
    status ENUM('pending','dispensed','expired','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS prescription_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    dosage VARCHAR(100),
    instructions TEXT,
    dispensed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- 5. Tax on sales
ALTER TABLE sales ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE sales ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0.00;

-- 6. Customer credit
ALTER TABLE customers ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notes TEXT;

-- Link sales to customers
ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT NULL;
ALTER TABLE sales ADD FOREIGN KEY IF NOT EXISTS (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- 7. Medicine interactions
CREATE TABLE IF NOT EXISTS medicine_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id_1 INT NOT NULL,
    medicine_id_2 INT NOT NULL,
    severity ENUM('mild','moderate','severe') DEFAULT 'moderate',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id_1) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id_2) REFERENCES medicines(id) ON DELETE CASCADE
);

-- 8. Barcode field on medicines
ALTER TABLE medicines ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) NULL;
ALTER TABLE medicines ADD COLUMN IF NOT EXISTS barcode_type ENUM('EAN13','EAN8','CODE128','QR') DEFAULT 'CODE128';

-- 9. Audit trail improvements
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS old_value TEXT NULL;
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS new_value TEXT NULL;
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS record_id INT NULL;
ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS table_name VARCHAR(50) NULL;
