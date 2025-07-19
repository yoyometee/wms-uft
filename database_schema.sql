-- WMS Database Schema
-- Created for Austam Good WMS System

CREATE DATABASE IF NOT EXISTS wms_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wms_system;

-- 1. User Management Table
CREATE TABLE manage_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    รหัสผู้ใช้ VARCHAR(50),
    ชื่อ_สกุล VARCHAR(255) NOT NULL,
    ตำแหน่ง VARCHAR(100),
    email VARCHAR(255),
    role ENUM('admin', 'office', 'worker') DEFAULT 'worker',
    password VARCHAR(255) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Master Product Data
CREATE TABLE master_sku_by_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    product_name TEXT NOT NULL,
    type VARCHAR(100) DEFAULT 'สินค้าสำเร็จรูป',
    barcode VARCHAR(50),
    unit VARCHAR(50) DEFAULT 'ถุง',
    น้ำหนัก_ต่อ_ถุง DECIMAL(10,2) DEFAULT 1.00,
    จำนวนถุง_ต่อ_แพ็ค INT DEFAULT 1,
    จำนวนแพ็ค_ต่อ_พาเลท INT DEFAULT 1,
    ti INT DEFAULT 1,
    hi INT DEFAULT 1,
    จำนวนน้ำหนัก_ปกติ DECIMAL(10,2) DEFAULT 0,
    จำนวนถุง_ปกติ INT DEFAULT 0,
    จำนวนน้ำหนัก_เสีย DECIMAL(10,2) DEFAULT 0,
    จำนวนถุง_เสีย INT DEFAULT 0,
    min_stock INT DEFAULT 0,
    max_stock INT DEFAULT 0,
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Location Master Data
CREATE TABLE msaster_location_by_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id VARCHAR(50) UNIQUE NOT NULL,
    zone VARCHAR(100) DEFAULT 'Selective Rack',
    row_name VARCHAR(50),
    level_num INT,
    loc_num INT,
    sku_pick_face VARCHAR(50),
    max_weight DECIMAL(10,2) DEFAULT 1000,
    max_pallet INT DEFAULT 1,
    max_height INT DEFAULT 1800,
    status ENUM('ว่าง', 'เก็บสินค้า') DEFAULT 'ว่าง',
    pallet_id_check VARCHAR(50),
    pallet_id VARCHAR(50),
    last_updated_check DECIMAL(20,10),
    last_updated_check_2 VARCHAR(50),
    last_updated DECIMAL(20,10),
    sku VARCHAR(50),
    product_name TEXT,
    แพ็ค INT DEFAULT 0,
    ชิ้น INT DEFAULT 0,
    น้ำหนัก DECIMAL(10,2) DEFAULT 0,
    lot VARCHAR(50),
    received_date DECIMAL(10,2),
    expiration_date DECIMAL(10,2),
    barcode VARCHAR(50),
    name_edit VARCHAR(255),
    item_status VARCHAR(50),
    สีพาเลท VARCHAR(50),
    หมายเหตุ TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_location_id (location_id),
    INDEX idx_zone (zone),
    INDEX idx_status (status)
);

-- 4. Pick Face Stock
CREATE TABLE msaster_pf_by_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    product_name TEXT,
    type VARCHAR(100) DEFAULT 'สินค้าสำเร็จรูป',
    barcode VARCHAR(50),
    unit VARCHAR(50) DEFAULT 'ถุง',
    จำนวนน้ำหนัก_ปกติ DECIMAL(10,2) DEFAULT 0,
    จำนวนถุง_ปกติ INT DEFAULT 0,
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku)
);

-- 5. Premium Stock
CREATE TABLE msaster_pf_premium_by_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    product_name TEXT,
    type VARCHAR(100) DEFAULT 'สินค้า Premium',
    barcode VARCHAR(50),
    unit VARCHAR(50) DEFAULT 'กล่อง',
    จำนวน INT DEFAULT 0,
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku)
);

-- 6. Transaction Tables (Template)
CREATE TABLE receive_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tags_id VARCHAR(50) UNIQUE NOT NULL,
    ประเภทหลัก VARCHAR(100) DEFAULT 'รับสินค้า',
    ประเภทย่อย VARCHAR(100),
    sku VARCHAR(50) NOT NULL,
    product_name TEXT,
    barcode VARCHAR(50),
    pallet_id VARCHAR(50),
    zone_location VARCHAR(50),
    status_location VARCHAR(50),
    location_id VARCHAR(50),
    สีพาเลท VARCHAR(50),
    แพ็ค INT DEFAULT 0,
    ชิ้น INT DEFAULT 0,
    น้ำหนัก DECIMAL(10,2) DEFAULT 0,
    lot VARCHAR(50),
    รหัสลูกค้า VARCHAR(50),
    ชื่อร้านค้า VARCHAR(255),
    received_date DECIMAL(10,2),
    expiration_date DECIMAL(10,2),
    คันที่ VARCHAR(50),
    transaction_status VARCHAR(50) DEFAULT 'ปกติ',
    last_updated DECIMAL(20,10),
    remark TEXT,
    number_pallet VARCHAR(50),
    name_edit VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tags_id (tags_id),
    INDEX idx_sku (sku),
    INDEX idx_pallet_id (pallet_id),
    INDEX idx_location_id (location_id),
    INDEX idx_created_at (created_at)
);

-- Create other transaction tables with similar structure
CREATE TABLE picking_transactions LIKE receive_transactions;
CREATE TABLE movement_transactions LIKE receive_transactions;
CREATE TABLE online_transactions LIKE receive_transactions;
CREATE TABLE premium_transactions LIKE receive_transactions;
CREATE TABLE conversion_transactions LIKE receive_transactions;
CREATE TABLE adjust_by_pf_transactions LIKE receive_transactions;
CREATE TABLE adjust_by_location_transactions LIKE receive_transactions;
CREATE TABLE rp_transactions LIKE receive_transactions;

-- Add specific fields for picking and online transactions
ALTER TABLE picking_transactions ADD COLUMN เลขเอกสาร VARCHAR(50);
ALTER TABLE picking_transactions ADD COLUMN จุดที่ VARCHAR(50);
ALTER TABLE picking_transactions ADD COLUMN เลขงานจัดส่ง VARCHAR(50);

ALTER TABLE online_transactions ADD COLUMN เลขเอกสาร VARCHAR(50);
ALTER TABLE online_transactions ADD COLUMN จุดที่ VARCHAR(50);
ALTER TABLE online_transactions ADD COLUMN เลขงานจัดส่ง VARCHAR(50);

-- 7. Bill of Materials
CREATE TABLE msaster_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    สินค้าสำเร็จรูป TEXT,
    วัตถุดิบ TEXT,
    อาหาร TEXT,
    ถุง TEXT,
    สติ๊กเกอร์_1 TEXT,
    สติ๊กเกอร์_2 TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Product Conversion
CREATE TABLE product_conversion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversion_id VARCHAR(255) UNIQUE,
    conversion_date DECIMAL(10,2),
    activity_type VARCHAR(255) DEFAULT 'Austam Goods',
    ใบที่ INT,
    product_name_fg TEXT,
    ชิ้น_fg INT,
    น้ำหนัก_fg DECIMAL(10,2),
    วันหมดอายุ_fg DECIMAL(10,2),
    convert_type VARCHAR(255) DEFAULT 'ผลิตสินค้าสำเร็จรูป',
    product_name_rm TEXT,
    pallet_id VARCHAR(255),
    ชิ้น_rm INT,
    น้ำหนัก_rm DECIMAL(10,2),
    sku_ถุง TEXT,
    จำนวนถุง INT,
    sku_1_สติ้กเกอร์ TEXT,
    จำนวนสติ้กเกอร์_1 INT,
    sku_2_สติ้กเกอร์ TEXT,
    จำนวนสติ้กเกอร์_2 INT,
    ผู้บันทึก VARCHAR(255),
    หมายเหตุ TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Menu Management
CREATE TABLE management_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    รูป VARCHAR(255),
    เมนู VARCHAR(255) NOT NULL,
    link VARCHAR(255),
    sort_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE
);

CREATE TABLE report_menu LIKE management_menu;

-- 10. System Settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default users
INSERT INTO manage_user (user_id, รหัสผู้ใช้, ชื่อ_สกุล, ตำแหน่ง, email, role, password) VALUES
('ADMIN001', 'ADMIN001', 'ผู้ดูแลระบบ', 'ผู้จัดการ', 'admin@austam.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('WH001', 'WH001', 'พนักงานคลัง 1', 'พนักงาน', 'wh001@austam.com', 'worker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('OFFICE001', 'OFFICE001', 'เจ้าหน้าที่สำนักงาน', 'เจ้าหน้าที่', 'office@austam.com', 'office', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample products
INSERT INTO master_sku_by_stock (sku, product_name, type, barcode, unit, น้ำหนัก_ต่อ_ถุง, จำนวนถุง_ต่อ_แพ็ค, min_stock, max_stock) VALUES
('ATG001', 'อาหารสุนัขรสเนื้อ 1กก.', 'สินค้าสำเร็จรูป', '1234567890123', 'ถุง', 1.00, 10, 100, 1000),
('ATG002', 'อาหารสุนัขรสไก่ 1กก.', 'สินค้าสำเร็จรูป', '1234567890124', 'ถุง', 1.00, 10, 100, 1000),
('ATG003', 'อาหารแมวรสทูน่า 500กรัม', 'สินค้าสำเร็จรูป', '1234567890125', 'ถุง', 0.50, 20, 200, 2000),
('ATG004', 'ขนมสุนัขรสเนื้อ 200กรัม', 'สินค้า Premium', '1234567890126', 'กล่อง', 0.20, 50, 500, 5000);

-- Insert sample locations
INSERT INTO msaster_location_by_stock (location_id, zone, row_name, level_num, loc_num, max_weight, max_pallet, max_height, status) VALUES
('A-01-01-01', 'Selective Rack', 'A-01', 1, 1, 1000, 1, 1800, 'ว่าง'),
('A-01-01-02', 'Selective Rack', 'A-01', 1, 2, 1000, 1, 1800, 'ว่าง'),
('A-01-02-01', 'Selective Rack', 'A-01', 2, 1, 1000, 1, 1800, 'ว่าง'),
('A-01-02-02', 'Selective Rack', 'A-01', 2, 2, 1000, 1, 1800, 'ว่าง'),
('PF-Zone-01', 'PF-Zone + Selective Rack', 'PF-01', 1, 1, 500, 1, 1200, 'ว่าง'),
('PF-Zone-02', 'PF-Zone + Selective Rack', 'PF-01', 1, 2, 500, 1, 1200, 'ว่าง'),
('PF-Premium-01', 'PF-Premium', 'PR-01', 1, 1, 300, 1, 1000, 'ว่าง'),
('PF-Premium-02', 'PF-Premium', 'PR-01', 1, 2, 300, 1, 1000, 'ว่าง'),
('Packaging-01', 'Packaging', 'PK-01', 1, 1, 200, 1, 800, 'ว่าง'),
('Packaging-02', 'Packaging', 'PK-01', 1, 2, 200, 1, 800, 'ว่าง'),
('Damaged-01', 'Damaged', 'DM-01', 1, 1, 500, 1, 1000, 'ว่าง');

-- Insert default menu items
INSERT INTO management_menu (รูป, เมนู, link, sort_order, active) VALUES
('📦', 'รับสินค้า', 'modules/receive/', 1, TRUE),
('📋', 'จัดเตรียมสินค้า', 'modules/picking/', 2, TRUE),
('🚛', 'ย้ายสินค้า', 'modules/movement/', 3, TRUE),
('📊', 'ปรับสต็อก', 'modules/inventory/', 4, TRUE),
('🔄', 'การแปลง', 'modules/conversion/', 5, TRUE),
('⭐', 'Premium', 'modules/premium/', 6, TRUE),
('📋', 'รายงาน', 'modules/reports/', 7, TRUE),
('⚙️', 'จัดการระบบ', 'modules/admin/', 8, TRUE);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'Austam Good WMS', 'ชื่อระบบ'),
('company_name', 'Austam Good Co., Ltd.', 'ชื่อบริษัท'),
('fefo_enabled', '1', 'เปิดใช้งาน FEFO'),
('auto_stock_update', '1', 'อัปเดตสต็อกอัตโนมัติ'),
('barcode_enabled', '1', 'เปิดใช้งาน Barcode'),
('session_timeout', '7200', 'เวลา Session หมดอายุ (วินาที)');

-- Create foreign key constraints
ALTER TABLE msaster_location_by_stock ADD CONSTRAINT fk_location_sku FOREIGN KEY (sku) REFERENCES master_sku_by_stock(sku) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE msaster_pf_by_stock ADD CONSTRAINT fk_pf_sku FOREIGN KEY (sku) REFERENCES master_sku_by_stock(sku) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE msaster_pf_premium_by_stock ADD CONSTRAINT fk_premium_sku FOREIGN KEY (sku) REFERENCES master_sku_by_stock(sku) ON UPDATE CASCADE ON DELETE CASCADE;

-- Create views for easier reporting
CREATE VIEW v_stock_summary AS
SELECT 
    p.sku,
    p.product_name,
    p.type,
    p.unit,
    p.จำนวนถุง_ปกติ as master_stock,
    p.จำนวนน้ำหนัก_ปกติ as master_weight,
    p.จำนวนถุง_เสีย as damaged_stock,
    p.min_stock,
    p.max_stock,
    COALESCE(l.location_count, 0) as locations_used,
    COALESCE(l.total_pieces, 0) as total_location_pieces,
    COALESCE(pf.จำนวนถุง_ปกติ, 0) as pf_stock,
    COALESCE(pr.จำนวน, 0) as premium_stock,
    CASE 
        WHEN p.จำนวนถุง_ปกติ <= p.min_stock THEN 'ต่ำกว่าขั้นต่ำ'
        WHEN p.จำนวนถุง_ปกติ >= p.max_stock THEN 'สูงกว่าขั้นสูง'
        ELSE 'ปกติ'
    END as stock_status
FROM master_sku_by_stock p
LEFT JOIN (
    SELECT sku, COUNT(*) as location_count, SUM(ชิ้น) as total_pieces
    FROM msaster_location_by_stock 
    WHERE status = 'เก็บสินค้า' AND sku IS NOT NULL
    GROUP BY sku
) l ON p.sku = l.sku
LEFT JOIN msaster_pf_by_stock pf ON p.sku = pf.sku
LEFT JOIN msaster_pf_premium_by_stock pr ON p.sku = pr.sku;

-- Create view for location utilization
CREATE VIEW v_location_utilization AS
SELECT 
    zone,
    COUNT(*) as total_locations,
    SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) as occupied_locations,
    SUM(CASE WHEN status = 'ว่าง' THEN 1 ELSE 0 END) as available_locations,
    ROUND((SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as utilization_percent
FROM msaster_location_by_stock
GROUP BY zone;

-- Create indexes for performance
CREATE INDEX idx_product_name ON master_sku_by_stock(product_name(100));
CREATE INDEX idx_barcode ON master_sku_by_stock(barcode);
CREATE INDEX idx_location_zone_status ON msaster_location_by_stock(zone, status);
CREATE INDEX idx_location_expiry ON msaster_location_by_stock(expiration_date);
CREATE INDEX idx_receive_date ON receive_transactions(created_at);
CREATE INDEX idx_pallet_sku ON receive_transactions(pallet_id, sku);