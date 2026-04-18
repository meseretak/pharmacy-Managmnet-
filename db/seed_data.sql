USE pharmacy_mgmt;

-- =============================================
-- SAFE SEED DATA - Can be run multiple times
-- Cleans up first, then inserts fresh data
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clean up seed data
DELETE FROM sale_items WHERE sale_id IN (SELECT id FROM (SELECT id FROM sales WHERE invoice_number LIKE 'INV-A%') t);
DELETE FROM sales WHERE invoice_number LIKE 'INV-A%';
DELETE FROM purchase_items WHERE purchase_id IN (SELECT id FROM (SELECT id FROM purchases WHERE invoice_ref LIKE 'PO-2026%') t);
DELETE FROM purchases WHERE invoice_ref LIKE 'PO-2026%';
DELETE FROM prescription_items WHERE prescription_id IN (SELECT id FROM (SELECT id FROM prescriptions WHERE doctor_license LIKE 'LIC-%') t);
DELETE FROM prescriptions WHERE doctor_license LIKE 'LIC-%';
DELETE FROM stock_adjustments WHERE reason IN ('Bottles broken during storage','Expired batch removed from shelf','Inventory count discrepancy','Stock count correction after audit');
DELETE FROM stock_transfers WHERE notes IN ('Branch 2 running low on Amoxicillin','Emergency restock for Branch 3','Vitamin C transfer request','Insufficient stock at source');
DELETE FROM customers WHERE phone IN ('0911234567','0922345678','0933456789','0944567890','0955678901','0966789012','0977890123','0988901234','0999012345','0900123456');

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- BRANCHES
-- =============================================
INSERT IGNORE INTO branches (name, location, phone, email, opening_time, closing_time, is_open, status) VALUES
('PharmaCare Bole', 'Bole Road, Addis Ababa', '+251911001001', 'bole@pharmacare.et', '08:00:00', '21:00:00', 1, 'active'),
('PharmaCare Piassa', 'Piassa, Addis Ababa', '+251911002002', 'piassa@pharmacare.et', '08:00:00', '20:00:00', 1, 'active'),
('PharmaCare Megenagna', 'Megenagna, Addis Ababa', '+251911003003', 'megenagna@pharmacare.et', '07:30:00', '22:00:00', 1, 'active');

-- =============================================
-- CATEGORIES
-- =============================================
INSERT IGNORE INTO categories (name, description) VALUES
('Antibiotics', 'Medicines that kill or inhibit bacteria'),
('Analgesics', 'Pain relief medicines'),
('Antidiabetics', 'Medicines for diabetes management'),
('Cardiovascular', 'Heart and blood pressure medicines'),
('Vitamins & Supplements', 'Nutritional supplements'),
('Antifungals', 'Medicines for fungal infections'),
('Antiparasitics', 'Medicines for parasitic infections'),
('Respiratory', 'Medicines for respiratory conditions'),
('Gastrointestinal', 'Digestive system medicines'),
('Dermatology', 'Skin care medicines');

-- =============================================
-- SUPPLIERS
-- =============================================
INSERT IGNORE INTO suppliers (name, contact_person, phone, email, address, country, status) VALUES
('Ethiopian Pharmaceuticals', 'Abebe Girma', '+251911100001', 'info@ethiopharm.et', 'Addis Ababa, Ethiopia', 'Ethiopia', 'active'),
('Medtech Supplies', 'Sara Tadesse', '+251922200002', 'sara@medtech.et', 'Bole, Addis Ababa', 'Ethiopia', 'active'),
('Global Pharma Import', 'John Smith', '+251933300003', 'john@globalpharma.com', 'Nairobi, Kenya', 'Kenya', 'active'),
('Addis Pharma Dist.', 'Tigist Bekele', '+251944400004', 'tigist@addispharma.et', 'Merkato, Addis Ababa', 'Ethiopia', 'active'),
('Indian Pharma Co.', 'Raj Kumar', '+911234567890', 'raj@indianpharma.in', 'Mumbai, India', 'India', 'active');

-- =============================================
-- MEDICINES
-- =============================================
INSERT IGNORE INTO medicines (name, generic_name, category_id, supplier_id, unit, requires_prescription, status, description) VALUES
('Amoxicillin 500mg', 'Amoxicillin', (SELECT id FROM categories WHERE name='Antibiotics' LIMIT 1), 1, 'Capsule', 1, 'active', 'Broad-spectrum antibiotic'),
('Paracetamol 500mg', 'Paracetamol', (SELECT id FROM categories WHERE name='Analgesics' LIMIT 1), 2, 'Tablet', 0, 'active', 'Pain and fever relief'),
('Metformin 500mg', 'Metformin HCl', (SELECT id FROM categories WHERE name='Antidiabetics' LIMIT 1), 1, 'Tablet', 1, 'active', 'Type 2 diabetes management'),
('Amlodipine 5mg', 'Amlodipine Besylate', (SELECT id FROM categories WHERE name='Cardiovascular' LIMIT 1), 3, 'Tablet', 1, 'active', 'Calcium channel blocker'),
('Vitamin C 1000mg', 'Ascorbic Acid', (SELECT id FROM categories WHERE name='Vitamins & Supplements' LIMIT 1), 2, 'Tablet', 0, 'active', 'Immune system support'),
('Fluconazole 150mg', 'Fluconazole', (SELECT id FROM categories WHERE name='Antifungals' LIMIT 1), 4, 'Capsule', 1, 'active', 'Antifungal treatment'),
('Albendazole 400mg', 'Albendazole', (SELECT id FROM categories WHERE name='Antiparasitics' LIMIT 1), 1, 'Tablet', 0, 'active', 'Antiparasitic treatment'),
('Salbutamol Inhaler', 'Salbutamol', (SELECT id FROM categories WHERE name='Respiratory' LIMIT 1), 3, 'Inhaler', 1, 'active', 'Bronchodilator for asthma'),
('Omeprazole 20mg', 'Omeprazole', (SELECT id FROM categories WHERE name='Gastrointestinal' LIMIT 1), 2, 'Capsule', 0, 'active', 'Proton pump inhibitor'),
('Hydrocortisone Cream', 'Hydrocortisone', (SELECT id FROM categories WHERE name='Dermatology' LIMIT 1), 4, 'Cream', 0, 'active', 'Anti-inflammatory skin cream'),
('Ciprofloxacin 500mg', 'Ciprofloxacin', (SELECT id FROM categories WHERE name='Antibiotics' LIMIT 1), 1, 'Tablet', 1, 'active', 'Fluoroquinolone antibiotic'),
('Ibuprofen 400mg', 'Ibuprofen', (SELECT id FROM categories WHERE name='Analgesics' LIMIT 1), 2, 'Tablet', 0, 'active', 'NSAID pain relief'),
('Atorvastatin 20mg', 'Atorvastatin', (SELECT id FROM categories WHERE name='Cardiovascular' LIMIT 1), 3, 'Tablet', 1, 'active', 'Cholesterol lowering statin'),
('Zinc Sulfate 20mg', 'Zinc Sulfate', (SELECT id FROM categories WHERE name='Vitamins & Supplements' LIMIT 1), 2, 'Tablet', 0, 'active', 'Zinc supplement'),
('Metronidazole 400mg', 'Metronidazole', (SELECT id FROM categories WHERE name='Antiparasitics' LIMIT 1), 1, 'Tablet', 1, 'active', 'Antiprotozoal and antibacterial'),
('Cetirizine 10mg', 'Cetirizine HCl', (SELECT id FROM categories WHERE name='Respiratory' LIMIT 1), 4, 'Tablet', 0, 'active', 'Antihistamine for allergies'),
('Losartan 50mg', 'Losartan Potassium', (SELECT id FROM categories WHERE name='Cardiovascular' LIMIT 1), 3, 'Tablet', 1, 'active', 'ARB for hypertension'),
('Folic Acid 5mg', 'Folic Acid', (SELECT id FROM categories WHERE name='Vitamins & Supplements' LIMIT 1), 2, 'Tablet', 0, 'active', 'Vitamin B9 supplement'),
('Doxycycline 100mg', 'Doxycycline HCl', (SELECT id FROM categories WHERE name='Antibiotics' LIMIT 1), 1, 'Capsule', 1, 'active', 'Tetracycline antibiotic'),
('ORS Sachet', 'Oral Rehydration Salts', (SELECT id FROM categories WHERE name='Gastrointestinal' LIMIT 1), 4, 'Sachet', 0, 'active', 'Rehydration therapy');

-- =============================================
-- DYNAMIC VARIABLES
-- =============================================
SET @uid1 = (SELECT id FROM users LIMIT 1);
SET @bid1 = (SELECT id FROM branches ORDER BY id LIMIT 1);
SET @bid2 = COALESCE((SELECT id FROM branches ORDER BY id LIMIT 1 OFFSET 1), @bid1);
SET @bid3 = COALESCE((SELECT id FROM branches ORDER BY id LIMIT 1 OFFSET 2), @bid1);

SET @m1  = (SELECT id FROM medicines WHERE name='Amoxicillin 500mg' LIMIT 1);
SET @m2  = (SELECT id FROM medicines WHERE name='Paracetamol 500mg' LIMIT 1);
SET @m3  = (SELECT id FROM medicines WHERE name='Metformin 500mg' LIMIT 1);
SET @m4  = (SELECT id FROM medicines WHERE name='Amlodipine 5mg' LIMIT 1);
SET @m5  = (SELECT id FROM medicines WHERE name='Vitamin C 1000mg' LIMIT 1);
SET @m6  = (SELECT id FROM medicines WHERE name='Fluconazole 150mg' LIMIT 1);
SET @m7  = (SELECT id FROM medicines WHERE name='Albendazole 400mg' LIMIT 1);
SET @m8  = (SELECT id FROM medicines WHERE name='Salbutamol Inhaler' LIMIT 1);
SET @m9  = (SELECT id FROM medicines WHERE name='Omeprazole 20mg' LIMIT 1);
SET @m10 = (SELECT id FROM medicines WHERE name='Hydrocortisone Cream' LIMIT 1);
SET @m11 = (SELECT id FROM medicines WHERE name='Ciprofloxacin 500mg' LIMIT 1);
SET @m12 = (SELECT id FROM medicines WHERE name='Ibuprofen 400mg' LIMIT 1);
SET @m13 = (SELECT id FROM medicines WHERE name='Atorvastatin 20mg' LIMIT 1);
SET @m14 = (SELECT id FROM medicines WHERE name='Zinc Sulfate 20mg' LIMIT 1);
SET @m15 = (SELECT id FROM medicines WHERE name='Metronidazole 400mg' LIMIT 1);
SET @m16 = (SELECT id FROM medicines WHERE name='Cetirizine 10mg' LIMIT 1);
SET @m17 = (SELECT id FROM medicines WHERE name='Losartan 50mg' LIMIT 1);
SET @m19 = (SELECT id FROM medicines WHERE name='Doxycycline 100mg' LIMIT 1);
SET @m20 = (SELECT id FROM medicines WHERE name='ORS Sachet' LIMIT 1);

-- =============================================
-- STOCK
-- =============================================
INSERT IGNORE INTO stock (medicine_id, branch_id, quantity, buying_price, selling_price, low_stock_threshold, batch_number, expiry_date) VALUES
(@m1,@bid1,150,45.00,75.00,20,'BATCH-AMX-001','2026-12-31'),
(@m2,@bid1,300,8.00,15.00,50,'BATCH-PCM-001','2027-06-30'),
(@m3,@bid1,200,35.00,60.00,30,'BATCH-MET-001','2026-09-30'),
(@m4,@bid1,120,55.00,90.00,20,'BATCH-AML-001','2027-03-31'),
(@m5,@bid1,400,12.00,20.00,50,'BATCH-VTC-001','2027-12-31'),
(@m6,@bid1,80,120.00,180.00,15,'BATCH-FLC-001','2026-08-31'),
(@m7,@bid1,250,18.00,30.00,40,'BATCH-ALB-001','2027-01-31'),
(@m8,@bid1,60,350.00,500.00,10,'BATCH-SAL-001','2026-11-30'),
(@m9,@bid1,180,25.00,45.00,30,'BATCH-OMP-001','2027-04-30'),
(@m10,@bid1,100,40.00,65.00,20,'BATCH-HYD-001','2026-10-31'),
(@m11,@bid1,90,65.00,110.00,15,'BATCH-CIP-001','2026-07-31'),
(@m12,@bid1,220,15.00,28.00,40,'BATCH-IBU-001','2027-05-31'),
(@m13,@bid1,75,85.00,140.00,15,'BATCH-ATV-001','2027-02-28'),
(@m14,@bid1,350,6.00,12.00,60,'BATCH-ZNC-001','2027-08-31'),
(@m15,@bid1,130,30.00,55.00,25,'BATCH-MTZ-001','2026-12-31'),
(@m16,@bid1,200,20.00,35.00,35,'BATCH-CTZ-001','2027-07-31'),
(@m17,@bid1,95,70.00,115.00,20,'BATCH-LST-001','2027-01-31'),
(@m19,@bid1,70,55.00,95.00,15,'BATCH-DOX-001','2026-06-30'),
(@m20,@bid1,500,3.00,8.00,80,'BATCH-ORS-001','2027-12-31'),
(@m1,@bid2,80,45.00,75.00,15,'BATCH-AMX-002','2026-12-31'),
(@m2,@bid2,200,8.00,15.00,40,'BATCH-PCM-002','2027-06-30'),
(@m3,@bid2,100,35.00,60.00,20,'BATCH-MET-002','2026-09-30'),
(@m5,@bid2,300,12.00,20.00,50,'BATCH-VTC-002','2027-12-31'),
(@m9,@bid2,120,25.00,45.00,25,'BATCH-OMP-002','2027-04-30'),
(@m12,@bid2,150,15.00,28.00,30,'BATCH-IBU-002','2027-05-31'),
(@m16,@bid2,180,20.00,35.00,30,'BATCH-CTZ-002','2027-07-31'),
(@m20,@bid2,400,3.00,8.00,60,'BATCH-ORS-002','2027-12-31'),
(@m2,@bid3,250,8.00,15.00,50,'BATCH-PCM-003','2027-06-30'),
(@m4,@bid3,90,55.00,90.00,15,'BATCH-AML-003','2027-03-31'),
(@m7,@bid3,200,18.00,30.00,35,'BATCH-ALB-003','2027-01-31'),
(@m11,@bid3,60,65.00,110.00,10,'BATCH-CIP-003','2026-07-31'),
(@m15,@bid3,100,30.00,55.00,20,'BATCH-MTZ-003','2026-12-31'),
(@m19,@bid3,5,55.00,95.00,10,'BATCH-DOX-003','2026-04-30');

-- =============================================
-- CUSTOMERS
-- =============================================
INSERT INTO customers (name, phone, email, address, branch_id, status) VALUES
('Abebe Kebede', '0911234567', 'abebe@email.com', 'Bole, Addis Ababa', @bid1, 'active'),
('Tigist Haile', '0922345678', 'tigist@email.com', 'Piassa, Addis Ababa', @bid1, 'active'),
('Dawit Bekele', '0933456789', 'dawit@email.com', 'Megenagna, Addis Ababa', @bid2, 'active'),
('Meron Tadesse', '0944567890', 'meron@email.com', 'Kazanchis, Addis Ababa', @bid1, 'active'),
('Yonas Girma', '0955678901', 'yonas@email.com', 'Sarbet, Addis Ababa', @bid2, 'active'),
('Hana Tesfaye', '0966789012', 'hana@email.com', 'CMC, Addis Ababa', @bid3, 'active'),
('Solomon Alemu', '0977890123', 'solomon@email.com', 'Gerji, Addis Ababa', @bid1, 'active'),
('Bethlehem Worku', '0988901234', 'bethlehem@email.com', 'Ayat, Addis Ababa', @bid3, 'active'),
('Kidus Mekonnen', '0999012345', 'kidus@email.com', 'Lebu, Addis Ababa', @bid2, 'active'),
('Selam Desta', '0900123456', 'selam@email.com', 'Lafto, Addis Ababa', @bid1, 'active');

-- =============================================
-- SALES
-- =============================================
INSERT INTO sales (invoice_number, branch_id, user_id, customer_name, customer_phone, total_amount, discount, paid_amount, payment_method, status, created_at) VALUES
('INV-A001',@bid1,@uid1,'Abebe Kebede','0911234567',225.00,0,225.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 1 DAY)),
('INV-A002',@bid1,@uid1,'Tigist Haile','0922345678',170.00,10.00,170.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 1 DAY)),
('INV-A003',@bid1,@uid1,'Walk-in Customer','',90.00,0,90.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 2 DAY)),
('INV-A004',@bid1,@uid1,'Dawit Bekele','0933456789',475.00,25.00,475.00,'card','completed',DATE_SUB(NOW(),INTERVAL 2 DAY)),
('INV-A005',@bid1,@uid1,'Meron Tadesse','0944567890',140.00,0,140.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 3 DAY)),
('INV-A006',@bid2,@uid1,'Yonas Girma','0955678901',300.00,0,300.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 3 DAY)),
('INV-A007',@bid2,@uid1,'Hana Tesfaye','0966789012',75.00,0,75.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 4 DAY)),
('INV-A008',@bid1,@uid1,'Solomon Alemu','0977890123',430.00,20.00,430.00,'chapa','completed',DATE_SUB(NOW(),INTERVAL 4 DAY)),
('INV-A009',@bid3,@uid1,'Bethlehem Worku','0988901234',160.00,0,160.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 5 DAY)),
('INV-A010',@bid1,@uid1,'Walk-in Customer','',60.00,0,60.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 5 DAY)),
('INV-A011',@bid1,@uid1,'Kidus Mekonnen','0999012345',280.00,0,280.00,'telebirr','completed',DATE_SUB(NOW(),INTERVAL 6 DAY)),
('INV-A012',@bid2,@uid1,'Selam Desta','0900123456',115.00,5.00,115.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 7 DAY)),
('INV-A013',@bid1,@uid1,'Abebe Kebede','0911234567',375.00,0,375.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 8 DAY)),
('INV-A014',@bid3,@uid1,'Walk-in Customer','',110.00,0,110.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 9 DAY)),
('INV-A015',@bid1,@uid1,'Tigist Haile','0922345678',500.00,40.00,500.00,'card','completed',DATE_SUB(NOW(),INTERVAL 10 DAY)),
('INV-A016',@bid2,@uid1,'Dawit Bekele','0933456789',90.00,0,90.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 12 DAY)),
('INV-A017',@bid1,@uid1,'Walk-in Customer','',182.00,0,182.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 14 DAY)),
('INV-A018',@bid3,@uid1,'Meron Tadesse','0944567890',300.00,30.00,300.00,'chapa','completed',DATE_SUB(NOW(),INTERVAL 15 DAY)),
('INV-A019',@bid1,@uid1,'Yonas Girma','0955678901',160.00,0,160.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 18 DAY)),
('INV-A020',@bid2,@uid1,'Walk-in Customer','',80.00,0,80.00,'cash','completed',DATE_SUB(NOW(),INTERVAL 20 DAY));

-- Sale Items
SET @s1=(SELECT id FROM sales WHERE invoice_number='INV-A001');
SET @s2=(SELECT id FROM sales WHERE invoice_number='INV-A002');
SET @s3=(SELECT id FROM sales WHERE invoice_number='INV-A003');
SET @s4=(SELECT id FROM sales WHERE invoice_number='INV-A004');
SET @s5=(SELECT id FROM sales WHERE invoice_number='INV-A005');
SET @s6=(SELECT id FROM sales WHERE invoice_number='INV-A006');
SET @s7=(SELECT id FROM sales WHERE invoice_number='INV-A007');
SET @s8=(SELECT id FROM sales WHERE invoice_number='INV-A008');
SET @s9=(SELECT id FROM sales WHERE invoice_number='INV-A009');
SET @s10=(SELECT id FROM sales WHERE invoice_number='INV-A010');
SET @s11=(SELECT id FROM sales WHERE invoice_number='INV-A011');
SET @s12=(SELECT id FROM sales WHERE invoice_number='INV-A012');
SET @s13=(SELECT id FROM sales WHERE invoice_number='INV-A013');
SET @s14=(SELECT id FROM sales WHERE invoice_number='INV-A014');
SET @s15=(SELECT id FROM sales WHERE invoice_number='INV-A015');
SET @s16=(SELECT id FROM sales WHERE invoice_number='INV-A016');
SET @s17=(SELECT id FROM sales WHERE invoice_number='INV-A017');
SET @s18=(SELECT id FROM sales WHERE invoice_number='INV-A018');
SET @s19=(SELECT id FROM sales WHERE invoice_number='INV-A019');
SET @s20=(SELECT id FROM sales WHERE invoice_number='INV-A020');

INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES
(@s1,@m1,2,75.00,150.00),(@s1,@m2,5,15.00,75.00),
(@s2,@m9,4,45.00,180.00),
(@s3,@m2,6,15.00,90.00),
(@s4,@m8,1,500.00,500.00),
(@s5,@m5,7,20.00,140.00),
(@s6,@m3,5,60.00,300.00),
(@s7,@m2,5,15.00,75.00),
(@s8,@m4,5,90.00,450.00),
(@s9,@m11,1,110.00,110.00),(@s9,@m16,1,35.00,35.00),(@s9,@m2,1,15.00,15.00),
(@s10,@m2,4,15.00,60.00),
(@s11,@m13,2,140.00,280.00),
(@s12,@m5,6,20.00,120.00),
(@s13,@m1,3,75.00,225.00),(@s13,@m9,2,45.00,90.00),(@s13,@m2,4,15.00,60.00),
(@s14,@m15,2,55.00,110.00),
(@s15,@m4,6,90.00,540.00),
(@s16,@m2,6,15.00,90.00),
(@s17,@m12,4,28.00,112.00),(@s17,@m16,2,35.00,70.00),
(@s18,@m17,3,115.00,345.00),
(@s19,@m5,5,20.00,100.00),(@s19,@m14,5,12.00,60.00),
(@s20,@m20,10,8.00,80.00);

-- =============================================
-- PURCHASES
-- =============================================
INSERT INTO purchases (branch_id, supplier_id, user_id, invoice_ref, total_amount, status, notes, created_at) VALUES
(@bid1,1,@uid1,'PO-2026-001',12500.00,'received','Monthly antibiotics restock',DATE_SUB(NOW(),INTERVAL 5 DAY)),
(@bid1,2,@uid1,'PO-2026-002',8750.00,'received','OTC medicines restock',DATE_SUB(NOW(),INTERVAL 8 DAY)),
(@bid2,3,@uid1,'PO-2026-003',15000.00,'received','Cardiovascular medicines',DATE_SUB(NOW(),INTERVAL 10 DAY)),
(@bid3,4,@uid1,'PO-2026-004',6200.00,'received','Branch 3 restock',DATE_SUB(NOW(),INTERVAL 12 DAY)),
(@bid1,1,@uid1,'PO-2026-005',9800.00,'pending','Upcoming order',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(@bid2,5,@uid1,'PO-2026-006',22000.00,'received','Imported medicines',DATE_SUB(NOW(),INTERVAL 20 DAY));

SET @p1=(SELECT id FROM purchases WHERE invoice_ref='PO-2026-001');
SET @p2=(SELECT id FROM purchases WHERE invoice_ref='PO-2026-002');
SET @p3=(SELECT id FROM purchases WHERE invoice_ref='PO-2026-003');
SET @p4=(SELECT id FROM purchases WHERE invoice_ref='PO-2026-004');
SET @p6=(SELECT id FROM purchases WHERE invoice_ref='PO-2026-006');

INSERT INTO purchase_items (purchase_id, medicine_id, quantity, unit_cost, subtotal, batch_number, expiry_date) VALUES
(@p1,@m1,200,45.00,9000.00,'BATCH-AMX-003','2027-06-30'),
(@p1,@m11,50,65.00,3250.00,'BATCH-CIP-004','2027-03-31'),
(@p2,@m2,500,8.00,4000.00,'BATCH-PCM-004','2028-01-31'),
(@p2,@m5,300,12.00,3600.00,'BATCH-VTC-003','2028-06-30'),
(@p3,@m4,100,55.00,5500.00,'BATCH-AML-004','2027-09-30'),
(@p3,@m13,50,85.00,4250.00,'BATCH-ATV-002','2027-12-31'),
(@p4,@m15,100,30.00,3000.00,'BATCH-MTZ-004','2027-06-30'),
(@p4,@m19,50,55.00,2750.00,'BATCH-DOX-004','2027-03-31'),
(@p6,@m8,30,350.00,10500.00,'BATCH-SAL-002','2027-08-31'),
(@p6,@m6,50,120.00,6000.00,'BATCH-FLC-003','2027-05-31');

-- =============================================
-- PRESCRIPTIONS
-- =============================================
INSERT INTO prescriptions (customer_name, customer_phone, doctor_name, doctor_license, hospital_clinic, issue_date, expiry_date, branch_id, user_id, status, notes) VALUES
('Abebe Kebede','0911234567','Dr. Tesfaye Alemu','LIC-12345','Black Lion Hospital',DATE_SUB(CURDATE(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 28 DAY),@bid1,@uid1,'pending','Hypertension treatment'),
('Tigist Haile','0922345678','Dr. Sara Bekele','LIC-23456','St. Paul Hospital',DATE_SUB(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 25 DAY),@bid1,@uid1,'dispensed','Diabetes management'),
('Dawit Bekele','0933456789','Dr. Yonas Girma','LIC-34567','Tikur Anbessa Hospital',DATE_SUB(CURDATE(),INTERVAL 1 DAY),DATE_ADD(CURDATE(),INTERVAL 29 DAY),@bid2,@uid1,'pending','Respiratory infection'),
('Meron Tadesse','0944567890','Dr. Hana Tesfaye','LIC-45678','Yekatit 12 Hospital',DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),@bid1,@uid1,'pending','Skin condition'),
('Solomon Alemu','0977890123','Dr. Kidus Mekonnen','LIC-56789','Bethzatha Hospital',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),@bid3,@uid1,'pending','Cholesterol management');

SET @rx1=(SELECT id FROM prescriptions WHERE doctor_license='LIC-12345' LIMIT 1);
SET @rx2=(SELECT id FROM prescriptions WHERE doctor_license='LIC-23456' LIMIT 1);
SET @rx3=(SELECT id FROM prescriptions WHERE doctor_license='LIC-34567' LIMIT 1);
SET @rx4=(SELECT id FROM prescriptions WHERE doctor_license='LIC-45678' LIMIT 1);
SET @rx5=(SELECT id FROM prescriptions WHERE doctor_license='LIC-56789' LIMIT 1);

INSERT INTO prescription_items (prescription_id, medicine_id, quantity, dosage, dispensed) VALUES
(@rx1,@m4,30,'1 tablet daily',0),
(@rx1,@m17,30,'1 tablet daily',0),
(@rx2,@m3,60,'1 tablet twice daily',1),
(@rx2,@m5,30,'1 tablet daily',1),
(@rx3,@m1,21,'1 capsule 3x daily for 7 days',0),
(@rx3,@m16,10,'1 tablet daily',0),
(@rx4,@m10,1,'Apply twice daily',0),
(@rx5,@m13,30,'1 tablet daily at night',0);

-- =============================================
-- STOCK ADJUSTMENTS
-- =============================================
SET @stk1=(SELECT id FROM stock WHERE medicine_id=@m1 AND branch_id=@bid1 LIMIT 1);
SET @stk6=(SELECT id FROM stock WHERE medicine_id=@m6 AND branch_id=@bid1 LIMIT 1);
SET @stk19=(SELECT id FROM stock WHERE medicine_id=@m19 AND branch_id=@bid1 LIMIT 1);
SET @stk2=(SELECT id FROM stock WHERE medicine_id=@m2 AND branch_id=@bid1 LIMIT 1);

INSERT INTO stock_adjustments (stock_id, branch_id, user_id, adjustment_type, quantity_before, quantity_change, quantity_after, reason) VALUES
(@stk1,@bid1,@uid1,'damage',150,-5,145,'Bottles broken during storage'),
(@stk6,@bid1,@uid1,'expiry_removal',80,-3,77,'Expired batch removed from shelf'),
(@stk19,@bid1,@uid1,'loss',70,-2,68,'Inventory count discrepancy'),
(@stk2,@bid1,@uid1,'correction',300,50,350,'Stock count correction after audit');

-- =============================================
-- STOCK TRANSFERS
-- =============================================
INSERT INTO stock_transfers (from_branch_id, to_branch_id, medicine_id, quantity, status, requested_by, approved_by, notes) VALUES
(@bid1,@bid2,@m1,30,'completed',@uid1,@uid1,'Branch 2 running low on Amoxicillin'),
(@bid1,@bid3,@m2,100,'completed',@uid1,@uid1,'Emergency restock for Branch 3'),
(@bid2,@bid3,@m5,50,'pending',@uid1,NULL,'Vitamin C transfer request'),
(@bid3,@bid1,@m15,20,'rejected',@uid1,@uid1,'Insufficient stock at source');

-- =============================================
-- NOTIFICATIONS
-- =============================================
INSERT INTO notifications (user_id, branch_id, type, title, message, is_read, link) VALUES
(NULL,@bid1,'low_stock','Low Stock Alert','Fluconazole 150mg has only 77 units left.',0,'/pharmacy/stock/index.php?filter=low'),
(NULL,@bid1,'expiry','Expiry Warning','Doxycycline 100mg expires soon',0,'/pharmacy/reports/expiry.php'),
(NULL,@bid2,'low_stock','Low Stock Alert','Ciprofloxacin 500mg has only 60 units left.',0,'/pharmacy/stock/index.php?filter=low'),
(NULL,NULL,'system','System Update','New features: Prescriptions, Refunds, Stock Transfers',1,'/pharmacy/dashboard.php');

-- =============================================
-- SHIFTS
-- =============================================
INSERT INTO shifts (branch_id, user_id, shift_date, start_time, end_time, opening_cash, closing_cash, status, notes) VALUES
(@bid1,@uid1,CURDATE(),'08:00:00','20:00:00',500.00,NULL,'open','Morning shift'),
(@bid1,@uid1,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'08:00:00','20:00:00',500.00,1250.00,'closed','Good day'),
(@bid2,@uid1,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'08:00:00','20:00:00',300.00,890.00,'closed','Normal shift'),
(@bid3,@uid1,CURDATE(),'12:00:00','22:00:00',400.00,NULL,'open','Afternoon shift');
