-- Pharmacy Management System Database
CREATE DATABASE IF NOT EXISTS pharmacy_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy_mgmt;

-- Roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);
INSERT INTO roles (name) VALUES ('super_admin'), ('branch_manager'), ('pharmacist');

-- Branches
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO branches (name, location, phone, email) VALUES
('Main Branch - Addis Ababa', 'Bole Road, Addis Ababa', '+251911000001', 'main@pharmacy.com'),
('Branch 2 - Hawassa', 'Piazza, Hawassa', '+251911000002', 'hawassa@pharmacy.com'),
('Branch 3 - Bahir Dar', 'Main Street, Bahir Dar', '+251911000003', 'bahirdar@pharmacy.com');

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    branch_id INT NULL COMMENT 'NULL means access to all branches (super admin)',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Default password: admin123
INSERT INTO users (name, email, password, role_id, branch_id) VALUES
('Super Admin', 'admin@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL),
('Branch Manager 1', 'manager1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1),
('Pharmacist 1', 'pharma1@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1);

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);
INSERT INTO categories (name, description) VALUES
('Antibiotics', 'Medicines that fight bacterial infections'),
('Analgesics', 'Pain relief medicines'),
('Antihypertensives', 'Blood pressure medicines'),
('Vitamins & Supplements', 'Nutritional supplements'),
('Antidiabetics', 'Diabetes management medicines'),
('Antihistamines', 'Allergy medicines'),
('Antifungals', 'Fungal infection treatments'),
('Gastrointestinal', 'Digestive system medicines');

-- Suppliers
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    country VARCHAR(100) DEFAULT 'Ethiopia',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO suppliers (name, contact_person, phone, email, country) VALUES
('PharmaCorp International', 'John Smith', '+1-800-555-0100', 'supply@pharmacorp.com', 'USA'),
('MediSupply Africa', 'Abebe Kebede', '+251911100001', 'info@medisupply.et', 'Ethiopia'),
('EuroPharma GmbH', 'Hans Mueller', '+49-30-555-0200', 'orders@europharma.de', 'Germany');

-- Medicines
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    generic_name VARCHAR(150),
    category_id INT,
    supplier_id INT,
    unit VARCHAR(50) DEFAULT 'Tablet',
    description TEXT,
    requires_prescription TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

INSERT INTO medicines (name, generic_name, category_id, supplier_id, unit, requires_prescription) VALUES
('Amoxicillin 500mg', 'Amoxicillin', 1, 1, 'Capsule', 1),
('Paracetamol 500mg', 'Paracetamol', 2, 2, 'Tablet', 0),
('Ibuprofen 400mg', 'Ibuprofen', 2, 1, 'Tablet', 0),
('Amlodipine 5mg', 'Amlodipine', 3, 3, 'Tablet', 1),
('Metformin 500mg', 'Metformin', 5, 1, 'Tablet', 1),
('Vitamin C 1000mg', 'Ascorbic Acid', 4, 2, 'Tablet', 0),
('Cetirizine 10mg', 'Cetirizine', 6, 3, 'Tablet', 0),
('Omeprazole 20mg', 'Omeprazole', 8, 1, 'Capsule', 0),
('Fluconazole 150mg', 'Fluconazole', 7, 3, 'Capsule', 1),
('Atorvastatin 20mg', 'Atorvastatin', 3, 1, 'Tablet', 1);

-- Stock (per branch)
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    branch_id INT NOT NULL,
    quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 20,
    buying_price DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    expiry_date DATE,
    batch_number VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    UNIQUE KEY unique_medicine_branch_batch (medicine_id, branch_id, batch_number)
);

INSERT INTO stock (medicine_id, branch_id, quantity, low_stock_threshold, buying_price, selling_price, expiry_date, batch_number) VALUES
(1, 1, 150, 20, 45.00, 75.00, '2026-12-31', 'BATCH001'),
(2, 1, 500, 50, 5.00, 12.00, '2027-06-30', 'BATCH002'),
(3, 1, 200, 30, 15.00, 28.00, '2027-03-31', 'BATCH003'),
(4, 1, 80, 15, 35.00, 65.00, '2026-09-30', 'BATCH004'),
(5, 1, 15, 20, 20.00, 40.00, '2027-01-31', 'BATCH005'),
(6, 1, 300, 50, 8.00, 18.00, '2027-12-31', 'BATCH006'),
(7, 1, 120, 25, 12.00, 25.00, '2026-11-30', 'BATCH007'),
(8, 1, 10, 20, 30.00, 55.00, '2026-08-31', 'BATCH008'),
(9, 1, 60, 15, 55.00, 95.00, '2026-10-31', 'BATCH009'),
(10, 1, 90, 20, 40.00, 72.00, '2027-02-28', 'BATCH010'),
(1, 2, 100, 20, 45.00, 75.00, '2026-12-31', 'BATCH011'),
(2, 2, 8, 50, 5.00, 12.00, '2027-06-30', 'BATCH012'),
(3, 2, 150, 30, 15.00, 28.00, '2027-03-31', 'BATCH013'),
(1, 3, 200, 20, 45.00, 75.00, '2026-12-31', 'BATCH014'),
(4, 3, 5, 15, 35.00, 65.00, '2026-09-30', 'BATCH015');

-- Sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(100) DEFAULT 'Walk-in Customer',
    customer_phone VARCHAR(20),
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash','card','mobile_money') DEFAULT 'cash',
    status ENUM('completed','refunded','pending') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale Items
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- Stock Purchases (restocking)
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,
    invoice_ref VARCHAR(100),
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending','received','cancelled') DEFAULT 'received',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    expiry_date DATE,
    batch_number VARCHAR(50),
    FOREIGN KEY (purchase_id) REFERENCES purchases(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- Activity Log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    module VARCHAR(100),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
