-- Table for storing default starting locations (lat, long)
-- สำหรับเก็บข้อมูลพิกัดเริ่มต้นที่สามารถเลือกใช้ได้

CREATE TABLE IF NOT EXISTS default_starting_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_name VARCHAR(255) NOT NULL COMMENT 'ชื่อของตำแหน่งเริ่มต้น',
    latitude DECIMAL(10, 8) NOT NULL COMMENT 'ละติจูด',
    longitude DECIMAL(11, 8) NOT NULL COMMENT 'ลองจิจูด',
    is_locked BOOLEAN DEFAULT FALSE COMMENT 'ล็อคตำแหน่งนี้ให้ใช้ทันทีเมื่อเข้าหน้าเว็บ',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'สถานะการใช้งาน',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_location_name (location_name),
    INDEX idx_is_locked (is_locked),
    INDEX idx_is_active (is_active),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูลพิกัดเริ่มต้นสำหรับการจัดส่ง';


