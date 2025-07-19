-- WMS Database Schema (Complete & Corrected Version)
-- Generated based on full project analysis

-- Step 1: Drop the old database if it exists and create a new one
DROP DATABASE IF EXISTS wms_system;
CREATE DATABASE wms_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wms_system;

-- Table: manage_user (User Management)
CREATE TABLE `manage_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `รหัสผู้ใช้` varchar(50) DEFAULT NULL,
  `ชื่อ_สกุล` varchar(255) NOT NULL,
  `ตำแหน่ง` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','office','worker') DEFAULT 'worker',
  `password` varchar(255) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: master_sku_by_stock (Product Master)
CREATE TABLE `master_sku_by_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `product_name` text NOT NULL,
  `type` varchar(100) DEFAULT 'สินค้าสำเร็จรูป',
  `barcode` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'ถุง',
  `น้ำหนัก_ต่อ_ถุง` decimal(10,2) DEFAULT 1.00,
  `จำนวนถุง_ต่อ_แพ็ค` int(11) DEFAULT 1,
  `จำนวนแพ็ค_ต่อ_พาเลท` int(11) DEFAULT 1,
  `ti` int(11) DEFAULT 1,
  `hi` int(11) DEFAULT 1,
  `จำนวนน้ำหนัก_ปกติ` decimal(10,2) DEFAULT 0.00,
  `จำนวนถุง_ปกติ` int(11) DEFAULT 0,
  `จำนวนน้ำหนัก_เสีย` decimal(10,2) DEFAULT 0.00,
  `จำนวนถุง_เสีย` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 0,
  `max_stock` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคาต้นทุนต่อหน่วย',
  `supplier_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `category` varchar(100) DEFAULT NULL,
  `จุดสั่งซื้อ` decimal(10,2) DEFAULT 0,
  `จำนวนสั่งซื้อ` decimal(10,2) DEFAULT 0,
  `ผู้จำหน่าย` varchar(255) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: msaster_location_by_stock (Location Master)
CREATE TABLE `msaster_location_by_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` varchar(50) NOT NULL,
  `zone` varchar(100) DEFAULT 'Selective Rack',
  `row_name` varchar(50) DEFAULT NULL,
  `level_num` int(11) DEFAULT NULL,
  `loc_num` int(11) DEFAULT NULL,
  `max_weight` decimal(10,2) DEFAULT 1000.00,
  `max_pallet` int(11) DEFAULT 1,
  `max_height` int(11) DEFAULT 1800,
  `status` enum('ว่าง','เก็บสินค้า','ซ่อมแซม') DEFAULT 'ว่าง',
  `pallet_id` varchar(50) DEFAULT NULL,
  `last_updated` decimal(20,10) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `product_name` text DEFAULT NULL,
  `แพ็ค` int(11) DEFAULT 0,
  `ชิ้น` int(11) DEFAULT 0,
  `น้ำหนัก` decimal(10,2) DEFAULT 0.00,
  `lot` varchar(50) DEFAULT NULL,
  `received_date` decimal(20,0) DEFAULT NULL,
  `expiration_date` decimal(20,0) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `name_edit` varchar(255) DEFAULT NULL,
  `สีพาเลท` varchar(50) DEFAULT NULL,
  `หมายเหตุ` text DEFAULT NULL,
  `coordinate_x` int(11) DEFAULT 0,
  `coordinate_y` int(11) DEFAULT 0,
  `coordinate_z` int(11) DEFAULT 0,
  `ราคาต้นทุน` decimal(10,2) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `location_id` (`location_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_zone` (`zone`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: receive_transactions (Base table for all transactions)
CREATE TABLE `receive_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tags_id` varchar(50) NOT NULL,
  `ประเภทหลัก` varchar(100) DEFAULT 'รับสินค้า',
  `ประเภทย่อย` varchar(100) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `product_name` text DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `pallet_id` varchar(50) DEFAULT NULL,
  `zone_location` varchar(50) DEFAULT NULL,
  `status_location` varchar(50) DEFAULT NULL,
  `location_id` varchar(50) DEFAULT NULL,
  `ตำแหน่ง` varchar(50) DEFAULT NULL,
  `ผู้ใช้งาน` int(11) DEFAULT NULL,
  `สีพาเลท` varchar(50) DEFAULT NULL,
  `แพ็ค` int(11) DEFAULT 0,
  `ชิ้น` int(11) DEFAULT 0,
  `น้ำหนัก` decimal(10,2) DEFAULT 0.00,
  `lot` varchar(50) DEFAULT NULL,
  `รหัสลูกค้า` varchar(50) DEFAULT NULL,
  `ชื่อร้านค้า` varchar(255) DEFAULT NULL,
  `received_date` decimal(20,0) DEFAULT NULL,
  `expiration_date` decimal(20,0) DEFAULT NULL,
  `คันที่` varchar(50) DEFAULT NULL,
  `transaction_status` varchar(50) DEFAULT 'ปกติ',
  `last_updated` decimal(20,10) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `number_pallet` varchar(50) DEFAULT NULL,
  `name_edit` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `เลขเอกสาร` varchar(50) DEFAULT NULL,
  `จุดที่` varchar(50) DEFAULT NULL,
  `เลขงานจัดส่ง` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_id` (`tags_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_pallet_id` (`pallet_id`),
  KEY `idx_location_id` (`location_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create other transaction tables based on the main one
CREATE TABLE `picking_transactions` LIKE `receive_transactions`;
CREATE TABLE `movement_transactions` LIKE `receive_transactions`;
CREATE TABLE `online_transactions` LIKE `receive_transactions`;
CREATE TABLE `premium_transactions` LIKE `receive_transactions`;
CREATE TABLE `conversion_transactions` LIKE `receive_transactions`;
CREATE TABLE `adjust_by_pf_transactions` LIKE `receive_transactions`;
CREATE TABLE `adjust_by_location_transactions` LIKE `receive_transactions`;
CREATE TABLE `rp_transactions` LIKE `receive_transactions`;

-- Table: system_settings
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: push_subscriptions (For PWA Notifications)
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh_key` text DEFAULT NULL,
  `auth_key` text DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_endpoint` (`user_id`,`endpoint`(255)),
  KEY `idx_user_active` (`user_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: report_exports
CREATE TABLE `report_exports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI & Analytics Tables
CREATE TABLE `analytics_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `cache_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cache_data`)),
  `cache_type` enum('kpi','trend','forecast','analysis') DEFAULT 'analysis',
  `valid_until` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `idx_valid_until` (`valid_until`),
  KEY `idx_cache_type` (`cache_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_pick_models` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `model_type` VARCHAR(50) NOT NULL,
    `model_data` JSON,
    `accuracy` DECIMAL(5,4) DEFAULT 0,
    `training_data_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` BOOLEAN DEFAULT TRUE,
    INDEX `idx_type_active` (`model_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pick_optimization_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `optimization_method` VARCHAR(50) NOT NULL,
    `original_path` JSON,
    `optimized_path` JSON,
    `total_distance` DECIMAL(10,2),
    `estimated_time` DECIMAL(10,2),
    `distance_saved` DECIMAL(5,2),
    `time_saved` DECIMAL(5,2),
    `efficiency_score` DECIMAL(5,2),
    `items_count` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_date` (`user_id`, `created_at`),
    INDEX `idx_method` (`optimization_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --- INSERT DEFAULT DATA ---

-- Insert default users
INSERT INTO `manage_user` (`user_id`, `รหัสผู้ใช้`, `ชื่อ_สกุล`, `ตำแหน่ง`, `email`, `role`, `password`, `active`) VALUES
('ADMIN001', 'ADMIN001', 'ผู้ดูแลระบบ', 'ผู้จัดการ', 'admin@austam.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('WH001', 'WH001', 'พนักงานคลัง 1', 'พนักงาน', 'wh001@austam.com', 'worker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('OFFICE001', 'OFFICE001', 'เจ้าหน้าที่สำนักงาน', 'เจ้าหน้าที่', 'office@austam.com', 'office', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Insert sample products
INSERT INTO `master_sku_by_stock` (`sku`, `product_name`, `type`, `barcode`, `unit`, `น้ำหนัก_ต่อ_ถุง`, `จำนวนถุง_ต่อ_แพ็ค`, `min_stock`, `max_stock`, `unit_cost`) VALUES
('ATG001', 'อาหารสุนัขรสเนื้อ 1กก.', 'สินค้าสำเร็จรูป', '1234567890123', 'ถุง', 1.00, 10, 100, 1000, 50.00),
('ATG002', 'อาหารสุนัขรสไก่ 1กก.', 'สินค้าสำเร็จรูป', '1234567890124', 'ถุง', 1.00, 10, 100, 1000, 52.50),
('ATG003', 'อาหารแมวรสทูน่า 500กรัม', 'สินค้าสำเร็จรูป', '1234567890125', 'ถุง', 0.50, 20, 200, 2000, 35.00),
('ATG004', 'ขนมสุนัขรสเนื้อ 200กรัม', 'สินค้า Premium', '1234567890126', 'กล่อง', 0.20, 50, 500, 5000, 25.00);

-- Insert sample locations
INSERT INTO `msaster_location_by_stock` (`location_id`, `zone`, `row_name`, `level_num`, `loc_num`, `max_weight`, `max_pallet`, `max_height`, `status`) VALUES
('A-01-01-01', 'Selective Rack', 'A-01', 1, 1, 1000, 1, 1800, 'ว่าง'),
('A-01-01-02', 'Selective Rack', 'A-01', 1, 2, 1000, 1, 1800, 'ว่าง'),
('PF-Zone-01', 'PF-Zone', 'PF-01', 1, 1, 500, 1, 1200, 'ว่าง'),
('PF-Premium-01', 'PF-Premium', 'PR-01', 1, 1, 300, 1, 1000, 'ว่าง'),
('Packaging-01', 'Packaging', 'PK-01', 1, 1, 200, 1, 800, 'ว่าง'),
('Damaged-01', 'Damaged', 'DM-01', 1, 1, 500, 1, 1000, 'ว่าง');

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('system_name', 'Austam Good WMS', 'ชื่อระบบ'),
('company_name', 'Austam Good Co., Ltd.', 'ชื่อบริษัท'),
('fefo_enabled', '1', 'เปิดใช้งาน FEFO'),
('barcode_enabled', '1', 'เปิดใช้งาน Barcode'),
('session_timeout', '7200', 'เวลา Session หมดอายุ (วินาที)');