-- Smart Delivery Zone Planner Database Schema
-- Created: 2024

-- Create database
CREATE DATABASE IF NOT EXISTS smart_delivery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use database
USE smart_delivery_db;

-- Table: delivery_address
-- รายการพัสดุ + พิกัด + โซน
CREATE TABLE delivery_address (
    id INT AUTO_INCREMENT PRIMARY KEY,
    awb_number VARCHAR(50) NOT NULL UNIQUE,
    tracking_number VARCHAR(50),
    recipient_name VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(20),
    address TEXT NOT NULL,
    province VARCHAR(100),
    district VARCHAR(100),
    subdistrict VARCHAR(100),
    postal_code VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    zone_id INT,
    geocoded_at TIMESTAMP NULL,
    geocoding_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    delivery_status ENUM('pending', 'assigned', 'in_transit', 'delivered', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_awb (awb_number),
    INDEX idx_zone (zone_id),
    INDEX idx_location (latitude, longitude),
    INDEX idx_status (delivery_status)
);

-- Table: zone_area
-- พื้นที่โซน + ขอบเขต
CREATE TABLE zone_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_code VARCHAR(10) NOT NULL UNIQUE,
    zone_name VARCHAR(255) NOT NULL,
    description TEXT,
    min_lat DECIMAL(10, 8) NOT NULL,
    max_lat DECIMAL(10, 8) NOT NULL,
    min_lng DECIMAL(11, 8) NOT NULL,
    max_lng DECIMAL(11, 8) NOT NULL,
    center_lat DECIMAL(10, 8),
    center_lng DECIMAL(11, 8),
    color_code VARCHAR(7) DEFAULT '#3B82F6',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone_code (zone_code),
    INDEX idx_bounds (min_lat, max_lat, min_lng, max_lng)
);

-- Table: rider
-- ข้อมูลพนักงานขนส่ง
CREATE TABLE rider (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    vehicle_type ENUM('motorcycle', 'car', 'truck') DEFAULT 'motorcycle',
    vehicle_number VARCHAR(50),
    license_number VARCHAR(50),
    max_capacity INT DEFAULT 50,
    status ENUM('active', 'inactive', 'busy') DEFAULT 'active',
    current_location_lat DECIMAL(10, 8),
    current_location_lng DECIMAL(11, 8),
    last_location_update TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rider_code (rider_code),
    INDEX idx_status (status),
    INDEX idx_location (current_location_lat, current_location_lng)
);

-- Table: delivery_route
-- เส้นทางจัดส่งแต่ละวันของ Rider
CREATE TABLE delivery_route (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(20) NOT NULL UNIQUE,
    rider_id INT NOT NULL,
    route_date DATE NOT NULL,
    start_location_lat DECIMAL(10, 8),
    start_location_lng DECIMAL(11, 8),
    end_location_lat DECIMAL(10, 8),
    end_location_lng DECIMAL(11, 8),
    total_distance DECIMAL(10, 2),
    estimated_duration INT, -- in minutes
    actual_duration INT, -- in minutes
    total_deliveries INT DEFAULT 0,
    completed_deliveries INT DEFAULT 0,
    route_status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES rider(id) ON DELETE CASCADE,
    INDEX idx_route_code (route_code),
    INDEX idx_rider_date (rider_id, route_date),
    INDEX idx_status (route_status)
);

-- Table: delivery_route_items
-- รายการจัดส่งในแต่ละเส้นทาง
CREATE TABLE delivery_route_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    address_id INT NOT NULL,
    sequence_order INT NOT NULL,
    estimated_arrival_time TIME,
    actual_arrival_time TIME,
    delivery_status ENUM('pending', 'arrived', 'delivered', 'failed') DEFAULT 'pending',
    delivery_notes TEXT,
    photo_evidence VARCHAR(255),
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE CASCADE,
    FOREIGN KEY (address_id) REFERENCES delivery_address(id) ON DELETE CASCADE,
    INDEX idx_route_sequence (route_id, sequence_order),
    INDEX idx_status (delivery_status)
);

-- Table: delivery_history
-- ประวัติการจัดส่ง เช่น เวลาถึง, สำเร็จ/ล้มเหลว
CREATE TABLE delivery_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address_id INT NOT NULL,
    rider_id INT NOT NULL,
    route_id INT,
    action_type ENUM('assigned', 'picked_up', 'in_transit', 'arrived', 'delivered', 'failed', 'returned') NOT NULL,
    location_lat DECIMAL(10, 8),
    location_lng DECIMAL(11, 8),
    notes TEXT,
    photo_evidence VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (address_id) REFERENCES delivery_address(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES rider(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE SET NULL,
    INDEX idx_address (address_id),
    INDEX idx_rider (rider_id),
    INDEX idx_action_time (action_type, created_at)
);

-- Table: import_logs
-- บันทึกไฟล์นำเข้า หรือการ sync รายวัน
CREATE TABLE import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    total_records INT DEFAULT 0,
    successful_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    import_status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_message TEXT,
    import_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_filename (filename),
    INDEX idx_status (import_status),
    INDEX idx_created (created_at)
);

-- Table: system_settings
-- การตั้งค่าระบบ
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert default system settings (ignore duplicates)
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('google_maps_api_key', '', 'string', 'Google Maps API Key'),
('default_map_center_lat', '13.7563', 'string', 'Default map center latitude (Bangkok)'),
('default_map_center_lng', '100.5018', 'string', 'Default map center longitude (Bangkok)'),
('default_zone_radius', '5', 'integer', 'Default zone radius in kilometers'),
('max_deliveries_per_route', '50', 'integer', 'Maximum deliveries per route'),
('max_file_upload_size', '10', 'integer', 'Maximum file upload size in MB'),
('auto_geocoding', 'true', 'boolean', 'Enable automatic geocoding for new addresses'),
('auto_zone_assignment', 'true', 'boolean', 'Enable automatic zone assignment');

-- Create indexes for better performance (drop existing ones first)
DROP INDEX IF EXISTS idx_delivery_address_geocoding ON delivery_address;
CREATE INDEX idx_delivery_address_geocoding ON delivery_address(geocoding_status, created_at);

DROP INDEX IF EXISTS idx_delivery_address_zone ON delivery_address;
CREATE INDEX idx_delivery_address_zone ON delivery_address(zone_id, delivery_status);

DROP INDEX IF EXISTS idx_delivery_route_date ON delivery_route;
CREATE INDEX idx_delivery_route_date ON delivery_route(route_date, route_status);

DROP INDEX IF EXISTS idx_delivery_history_date ON delivery_history;
CREATE INDEX idx_delivery_history_date ON delivery_history(created_at);

-- Create views for common queries (drop existing ones first)
DROP VIEW IF EXISTS view_delivery_summary;
CREATE VIEW view_delivery_summary AS
SELECT 
    da.id,
    da.awb_number,
    da.recipient_name,
    da.address,
    da.latitude,
    da.longitude,
    za.zone_name,
    da.delivery_status,
    r.name as rider_name,
    dr.route_code,
    dr.route_date
FROM delivery_address da
LEFT JOIN zone_area za ON da.zone_id = za.id
LEFT JOIN delivery_route_items dri ON da.id = dri.address_id
LEFT JOIN delivery_route dr ON dri.route_id = dr.id
LEFT JOIN rider r ON dr.rider_id = r.id;

DROP VIEW IF EXISTS view_route_summary;
CREATE VIEW view_route_summary AS
SELECT 
    dr.id,
    dr.route_code,
    dr.route_date,
    r.name as rider_name,
    r.phone as rider_phone,
    dr.total_deliveries,
    dr.completed_deliveries,
    dr.route_status,
    ROUND(dr.total_distance, 2) as total_distance_km,
    ROUND(dr.estimated_duration/60, 2) as estimated_hours
FROM delivery_route dr
JOIN rider r ON dr.rider_id = r.id;

-- Sample data for testing (ignore duplicates)
INSERT IGNORE INTO zone_area (zone_code, zone_name, description, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code) VALUES
('BKK01', 'กรุงเทพฯ เขตกลาง', 'พื้นที่ใจกลางกรุงเทพฯ', 13.7200, 13.7800, 100.4800, 100.5400, 13.7500, 100.5100, '#3B82F6'),
('BKK02', 'กรุงเทพฯ เขตเหนือ', 'พื้นที่ทางเหนือของกรุงเทพฯ', 13.7800, 13.8400, 100.4800, 100.5400, 13.8100, 100.5100, '#10B981'),
('BKK03', 'กรุงเทพฯ เขตใต้', 'พื้นที่ทางใต้ของกรุงเทพฯ', 13.6600, 13.7200, 100.4800, 100.5400, 13.6900, 100.5100, '#F59E0B'),
('BKK04', 'กรุงเทพฯ เขตตะวันออก', 'พื้นที่ทางตะวันออกของกรุงเทพฯ', 13.7200, 13.7800, 100.5400, 100.6000, 13.7500, 100.5700, '#EF4444'),
('BKK05', 'กรุงเทพฯ เขตตะวันตก', 'พื้นที่ทางตะวันตกของกรุงเทพฯ', 13.7200, 13.7800, 100.4200, 100.4800, 13.7500, 100.4500, '#8B5CF6');

INSERT IGNORE INTO rider (rider_code, name, phone, email, vehicle_type, vehicle_number, max_capacity, status) VALUES
('RD001', 'สมชาย ใจดี', '081-234-5678', 'somchai@example.com', 'motorcycle', 'กข 1234', 30, 'active'),
('RD002', 'สมหญิง รักดี', '082-345-6789', 'somying@example.com', 'car', 'กข 5678', 50, 'active'),
('RD003', 'สมศักดิ์ ขยันดี', '083-456-7890', 'somsak@example.com', 'motorcycle', 'กข 9012', 30, 'active'); 