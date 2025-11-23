-- Enhanced Geocoding Schema สำหรับระบบแยกที่อยู่ภาษาไทย
-- ขยายจากระบบเดิม เพื่อเก็บข้อมูลที่อยู่อย่างละเอียด

-- ขยายตาราง delivery_address เพื่อเก็บข้อมูลที่แยกแล้ว
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS address_components JSON COMMENT 'ส่วนประกอบที่อยู่ที่แยกแล้ว';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS house_number VARCHAR(20) COMMENT 'เลขที่บ้าน';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS building_name VARCHAR(255) COMMENT 'ชื่ออาคาร/โครงการ';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS soi VARCHAR(100) COMMENT 'ซอย';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS road VARCHAR(150) COMMENT 'ถนน';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS moo VARCHAR(20) COMMENT 'หมู่';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS keywords JSON COMMENT 'คำสำคัญ/สถานที่สำคัญ';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS parsing_quality ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'fair' COMMENT 'คุณภาพการแยกที่อยู่';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS geocoding_source ENUM('google_maps', 'nominatim', 'manual') DEFAULT 'nominatim' COMMENT 'แหล่งข้อมูลพิกัด';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS geocoding_confidence DECIMAL(5,2) DEFAULT 0 COMMENT 'ความเชื่อมั่นในพิกัด (0-100)';
ALTER TABLE delivery_address ADD COLUMN IF NOT EXISTS geocoding_accuracy ENUM('high', 'medium', 'low', 'very_low', 'unknown') DEFAULT 'unknown' COMMENT 'ความแม่นยำของพิกัด';

-- เพิ่ม indexes สำหรับการค้นหา
CREATE INDEX IF NOT EXISTS idx_house_number ON delivery_address(house_number);
CREATE INDEX IF NOT EXISTS idx_road ON delivery_address(road);
CREATE INDEX IF NOT EXISTS idx_soi ON delivery_address(soi);
CREATE INDEX IF NOT EXISTS idx_parsing_quality ON delivery_address(parsing_quality);
CREATE INDEX IF NOT EXISTS idx_geocoding_source ON delivery_address(geocoding_source);
CREATE INDEX IF NOT EXISTS idx_geocoding_confidence ON delivery_address(geocoding_confidence);

-- ตาราง geocoding_cache สำหรับเก็บผลลัพธ์ geocoding
CREATE TABLE IF NOT EXISTS geocoding_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'MD5 hash ของที่อยู่',
    original_address TEXT NOT NULL COMMENT 'ที่อยู่ต้นฉบับ',
    parsed_components JSON COMMENT 'ส่วนประกอบที่แยกแล้ว',
    search_queries JSON COMMENT 'คำค้นหาที่ใช้',
    latitude DECIMAL(10, 8) COMMENT 'ละติจูด',
    longitude DECIMAL(11, 8) COMMENT 'ลองติจูด',
    formatted_address TEXT COMMENT 'ที่อยู่ที่จัดรูปแบบแล้ว',
    geocoding_source ENUM('google_maps', 'nominatim', 'manual') NOT NULL COMMENT 'แหล่งข้อมูล',
    confidence DECIMAL(5,2) DEFAULT 0 COMMENT 'ความเชื่อมั่น',
    accuracy ENUM('high', 'medium', 'low', 'very_low', 'unknown') DEFAULT 'unknown' COMMENT 'ความแม่นยำ',
    api_response JSON COMMENT 'ผลลัพธ์เต็มจาก API',
    success BOOLEAN DEFAULT FALSE COMMENT 'สำเร็จหรือไม่',
    error_message TEXT COMMENT 'ข้อความข้อผิดพลาด',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    used_count INT DEFAULT 1 COMMENT 'จำนวนครั้งที่ใช้',
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'ใช้ครั้งล่าสุด',
    
    INDEX idx_address_hash (address_hash),
    INDEX idx_success (success),
    INDEX idx_source (geocoding_source),
    INDEX idx_location (latitude, longitude),
    INDEX idx_created (created_at),
    INDEX idx_used_count (used_count)
) COMMENT = 'Cache ผลลัพธ์ geocoding';

-- ตาราง address_patterns สำหรับเก็บรูปแบบที่อยู่ที่พบบ่อย
CREATE TABLE IF NOT EXISTS address_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_type ENUM('road', 'soi', 'subdistrict', 'district', 'province', 'building', 'landmark') NOT NULL,
    pattern_text VARCHAR(255) NOT NULL COMMENT 'รูปแบบ/คำสำคัญ',
    alternative_names JSON COMMENT 'ชื่อเรียกอื่น ๆ',
    frequency INT DEFAULT 1 COMMENT 'ความถี่ในการพบ',
    confidence_boost DECIMAL(3,2) DEFAULT 0 COMMENT 'การเพิ่มความเชื่อมั่น',
    region VARCHAR(100) COMMENT 'ภูมิภาค/จังหวัดที่พบบ่อย',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pattern (pattern_type, pattern_text),
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_frequency (frequency),
    INDEX idx_region (region),
    INDEX idx_active (is_active)
) COMMENT = 'รูปแบบที่อยู่ที่พบบ่อย';

-- ตาราง geocoding_statistics สำหรับสถิติการทำงาน
CREATE TABLE IF NOT EXISTS geocoding_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    geocoding_source ENUM('google_maps', 'nominatim', 'manual', 'cache') NOT NULL,
    total_requests INT DEFAULT 0,
    successful_requests INT DEFAULT 0,
    failed_requests INT DEFAULT 0,
    avg_confidence DECIMAL(5,2) DEFAULT 0,
    avg_response_time DECIMAL(10,3) DEFAULT 0 COMMENT 'เวลาเฉลี่ย (วินาที)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date_source (date, geocoding_source),
    INDEX idx_date (date),
    INDEX idx_source (geocoding_source)
) COMMENT = 'สถิติการทำงานของ geocoding';

-- ตาราง route_optimization สำหรับเก็บผลลัพธ์การคำนวณเส้นทาง
CREATE TABLE IF NOT EXISTS route_optimization (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    route_name VARCHAR(255) NOT NULL,
    delivery_ids JSON NOT NULL COMMENT 'รายการ ID ของ delivery ตามลำดับ',
    total_distance DECIMAL(10,3) COMMENT 'ระยะทางรวม (กิโลเมตร)',
    estimated_time INT COMMENT 'เวลาโดยประมาณ (นาที)',
    algorithm_used VARCHAR(50) DEFAULT 'nearest_neighbor' COMMENT 'อัลกอริทึมที่ใช้',
    optimization_score DECIMAL(5,2) DEFAULT 0 COMMENT 'คะแนนการเพิ่มประสิทธิภาพ',
    route_points JSON COMMENT 'จุดต่าง ๆ ในเส้นทาง',
    created_by INT COMMENT 'ผู้สร้าง',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_zone (zone_id),
    INDEX idx_active (is_active),
    INDEX idx_created (created_at),
    INDEX idx_distance (total_distance),
    INDEX idx_time (estimated_time),
    FOREIGN KEY (zone_id) REFERENCES zone_area(id) ON DELETE CASCADE
) COMMENT = 'ผลลัพธ์การคำนวณเส้นทางที่เหมาะสม';

-- View สำหรับสรุปข้อมูล geocoding
CREATE OR REPLACE VIEW view_geocoding_summary AS
SELECT 
    da.province,
    da.district,
    da.geocoding_source,
    da.geocoding_accuracy,
    COUNT(*) as total_addresses,
    COUNT(CASE WHEN da.latitude IS NOT NULL THEN 1 END) as geocoded_count,
    AVG(da.geocoding_confidence) as avg_confidence,
    COUNT(CASE WHEN da.parsing_quality = 'excellent' THEN 1 END) as excellent_parsing,
    COUNT(CASE WHEN da.parsing_quality = 'good' THEN 1 END) as good_parsing,
    COUNT(CASE WHEN da.parsing_quality = 'fair' THEN 1 END) as fair_parsing,
    COUNT(CASE WHEN da.parsing_quality = 'poor' THEN 1 END) as poor_parsing
FROM delivery_address da
GROUP BY da.province, da.district, da.geocoding_source, da.geocoding_accuracy
ORDER BY da.province, da.district;

-- View สำหรับการวิเคราะห์คุณภาพที่อยู่
CREATE OR REPLACE VIEW view_address_quality_analysis AS
SELECT 
    da.province,
    da.district,
    da.parsing_quality,
    COUNT(*) as address_count,
    AVG(da.geocoding_confidence) as avg_confidence,
    COUNT(CASE WHEN da.house_number IS NOT NULL AND da.house_number != '' THEN 1 END) as has_house_number,
    COUNT(CASE WHEN da.road IS NOT NULL AND da.road != '' THEN 1 END) as has_road,
    COUNT(CASE WHEN da.soi IS NOT NULL AND da.soi != '' THEN 1 END) as has_soi,
    COUNT(CASE WHEN da.building_name IS NOT NULL AND da.building_name != '' THEN 1 END) as has_building,
    COUNT(CASE WHEN JSON_LENGTH(da.keywords) > 0 THEN 1 END) as has_keywords
FROM delivery_address da
WHERE da.address IS NOT NULL
GROUP BY da.province, da.district, da.parsing_quality
ORDER BY da.province, da.district, 
    FIELD(da.parsing_quality, 'excellent', 'good', 'fair', 'poor');

-- ฟังก์ชัน stored procedure สำหรับคำนวณระยะทางระหว่างจุด
DELIMITER //
CREATE FUNCTION IF NOT EXISTS DISTANCE_KM(lat1 DECIMAL(10,8), lng1 DECIMAL(11,8), lat2 DECIMAL(10,8), lng2 DECIMAL(11,8))
RETURNS DECIMAL(10,3)
READS SQL DATA
DETERMINISTIC
COMMENT 'คำนวณระยะทางระหว่าง 2 จุด (กิโลเมตร) ด้วย Haversine formula'
BEGIN
    DECLARE distance DECIMAL(10,3);
    DECLARE radius DECIMAL(10,3) DEFAULT 6371; -- รัศมีโลก (กิโลเมตร)
    DECLARE dlat DECIMAL(10,8);
    DECLARE dlng DECIMAL(10,8);
    DECLARE a DECIMAL(20,10);
    DECLARE c DECIMAL(20,10);
    
    IF lat1 IS NULL OR lng1 IS NULL OR lat2 IS NULL OR lng2 IS NULL THEN
        RETURN NULL;
    END IF;
    
    SET dlat = RADIANS(lat2 - lat1);
    SET dlng = RADIANS(lng2 - lng1);
    SET a = SIN(dlat/2) * SIN(dlat/2) + COS(RADIANS(lat1)) * COS(RADIANS(lat2)) * SIN(dlng/2) * SIN(dlng/2);
    SET c = 2 * ATAN2(SQRT(a), SQRT(1-a));
    SET distance = radius * c;
    
    RETURN distance;
END //
DELIMITER ;

-- Trigger สำหรับอัพเดท geocoding statistics
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_geocoding_update
AFTER UPDATE ON delivery_address
FOR EACH ROW
BEGIN
    IF NEW.latitude IS NOT NULL AND OLD.latitude IS NULL THEN
        INSERT INTO geocoding_statistics (date, geocoding_source, total_requests, successful_requests)
        VALUES (CURDATE(), NEW.geocoding_source, 1, 1)
        ON DUPLICATE KEY UPDATE 
            total_requests = total_requests + 1,
            successful_requests = successful_requests + 1,
            avg_confidence = (avg_confidence * (total_requests - 1) + NEW.geocoding_confidence) / total_requests;
    END IF;
END //
DELIMITER ;

-- สร้างข้อมูลตัวอย่าง address patterns
INSERT IGNORE INTO address_patterns (pattern_type, pattern_text, frequency, confidence_boost, region) VALUES
('road', 'ถนนสุขุมวิท', 100, 0.2, 'กรุงเทพมหานคร'),
('road', 'ถนนพหลโยธิน', 95, 0.2, 'กรุงเทพมหานคร'),
('road', 'ถนนราชดำเนิน', 80, 0.15, 'กรุงเทพมหานคร'),
('road', 'ถนนรัชดาภิเษก', 75, 0.15, 'กรุงเทพมหานคร'),
('landmark', 'สถานีรถไฟฟ้า', 200, 0.3, 'กรุงเทพมหานคร'),
('landmark', 'สถานี BTS', 150, 0.25, 'กรุงเทพมหานคร'),
('landmark', 'สถานี MRT', 120, 0.25, 'กรุงเทพมหานคร'),
('landmark', 'เซ็นทรัลเวิลด์', 50, 0.4, 'กรุงเทพมหานคร'),
('landmark', 'สยามพารากอน', 45, 0.4, 'กรุงเทพมหานคร'),
('building', 'คอนโดมิเนียม', 300, 0.1, 'ทั่วไป'),
('building', 'หมู่บ้าน', 250, 0.1, 'ทั่วไป'),
('building', 'โครงการ', 200, 0.1, 'ทั่วไป');

-- เพิ่มข้อมูลตัวอย่างใน geocoding_cache (เพื่อทดสอบ)
INSERT IGNORE INTO geocoding_cache (
    address_hash, 
    original_address, 
    latitude, 
    longitude, 
    formatted_address, 
    geocoding_source, 
    confidence, 
    accuracy, 
    success
) VALUES
(MD5('123 ถนนสุขุมวิท คลองเตย กรุงเทพมหานคร'), 
 '123 ถนนสุขุมวิท คลองเตย กรุงเทพมหานคร', 
 13.7363, 100.5619, 
 '123 Sukhumvit Road, Khlong Toei, Bangkok, Thailand', 
 'nominatim', 85.5, 'high', TRUE),

(MD5('456 ถนนพหลโยธิน พญาไท กรุงเทพมหานคร'), 
 '456 ถนนพหลโยธิน พญาไท กรุงเทพมหานคร', 
 13.7650, 100.5350, 
 '456 Phahon Yothin Road, Phaya Thai, Bangkok, Thailand', 
 'nominatim', 82.3, 'high', TRUE);

-- สร้าง index เพิ่มเติมสำหรับประสิทธิภาพ
CREATE INDEX IF NOT EXISTS idx_delivery_address_components ON delivery_address(house_number, road, district, province);
CREATE INDEX IF NOT EXISTS idx_delivery_address_quality ON delivery_address(parsing_quality, geocoding_confidence); 