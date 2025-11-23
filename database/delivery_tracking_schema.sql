-- เพิ่มตาราง delivery_tracking สำหรับติดตามการจัดส่งแบบละเอียด

-- ลบตารางเก่าถ้ามี (เพื่อให้แน่ใจว่าเริ่มต้นใหม่)
DROP TABLE IF EXISTS delivery_tracking;

-- Table: delivery_tracking
-- ติดตามการจัดส่งแบบละเอียด Real-time
CREATE TABLE delivery_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(50) NOT NULL UNIQUE,
    awb_number VARCHAR(50) NOT NULL,
    delivery_address_id INT,
    rider_id INT,
    route_id INT,
    current_status ENUM('pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned', 'cancelled') DEFAULT 'pending',
    current_location_lat DECIMAL(10, 8),
    current_location_lng DECIMAL(11, 8),
    current_location_address TEXT,
    estimated_delivery_time DATETIME,
    actual_delivery_time DATETIME NULL,
    delivery_attempts INT DEFAULT 0,
    delivery_notes TEXT,
    recipient_signature VARCHAR(255),
    delivery_photo VARCHAR(255),
    priority_level ENUM('normal', 'urgent', 'express', 'standard') DEFAULT 'normal',
    special_instructions TEXT,
    contact_attempts INT DEFAULT 0,
    last_contact_time DATETIME NULL,
    failure_reason ENUM('address_not_found', 'recipient_not_available', 'refused_delivery', 'damaged_package', 'weather', 'vehicle_breakdown', 'other') NULL,
    return_reason TEXT,
    cod_amount DECIMAL(10, 2) DEFAULT 0.00,
    cod_status ENUM('not_applicable', 'pending', 'collected', 'failed') DEFAULT 'not_applicable',
    package_weight DECIMAL(8, 2),
    package_dimensions VARCHAR(50),
    service_type ENUM('standard', 'express', 'same_day', 'next_day') DEFAULT 'standard',
    insurance_value DECIMAL(10, 2) DEFAULT 0.00,
    tracking_events JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- เพิ่ม Foreign Keys แยกออกมา
ALTER TABLE delivery_tracking ADD CONSTRAINT fk_delivery_tracking_address 
    FOREIGN KEY (delivery_address_id) REFERENCES delivery_address(id) ON DELETE SET NULL;
    
ALTER TABLE delivery_tracking ADD CONSTRAINT fk_delivery_tracking_rider 
    FOREIGN KEY (rider_id) REFERENCES rider(id) ON DELETE SET NULL;
    
ALTER TABLE delivery_tracking ADD CONSTRAINT fk_delivery_tracking_route 
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE SET NULL;

-- เพิ่ม Indexes
CREATE INDEX idx_tracking_id ON delivery_tracking(tracking_id);
CREATE INDEX idx_awb ON delivery_tracking(awb_number);
CREATE INDEX idx_status ON delivery_tracking(current_status);
CREATE INDEX idx_rider ON delivery_tracking(rider_id);
CREATE INDEX idx_route ON delivery_tracking(route_id);
CREATE INDEX idx_delivery_time ON delivery_tracking(estimated_delivery_time);
CREATE INDEX idx_location ON delivery_tracking(current_location_lat, current_location_lng);
CREATE INDEX idx_created ON delivery_tracking(created_at);
CREATE INDEX idx_delivery_tracking_performance ON delivery_tracking(current_status, estimated_delivery_time, actual_delivery_time);
CREATE INDEX idx_delivery_tracking_cod ON delivery_tracking(cod_status, cod_amount);
CREATE INDEX idx_delivery_tracking_priority ON delivery_tracking(priority_level, service_type);

-- เพิ่มข้อมูลตัวอย่างสำหรับ delivery_tracking (นครศรีธรรมราช)
INSERT INTO delivery_tracking (
    tracking_id, awb_number, current_status, current_location_lat, current_location_lng, 
    current_location_address, estimated_delivery_time, delivery_attempts, priority_level, 
    service_type, package_weight, cod_amount, special_instructions, tracking_events
) VALUES
('TRK240101001', 'NST001', 'in_transit', 8.4304, 99.9631, 'ใกล้ถนนราชดำเนิน ตำบลในเมือง', '2024-01-15 14:30:00', 0, 'normal', 'standard', 2.5, 0.00, 'ระวังของแตก', '{"events": [{"time": "2024-01-15 09:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 10:30:00", "status": "in_transit", "location": "ออกจัดส่ง"}]}'),

('TRK240101002', 'NST002', 'out_for_delivery', 8.4254, 99.9681, 'ถนนพัฒนการ ตำบลในเมือง', '2024-01-15 15:00:00', 1, 'urgent', 'express', 1.8, 1250.00, 'COD - เก็บเงินปลายทาง', '{"events": [{"time": "2024-01-15 08:30:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 11:00:00", "status": "out_for_delivery", "location": "พื้นที่จัดส่ง"}]}'),

('TRK240101003', 'NST003', 'delivered', 8.4354, 99.9581, 'ถนนเจริญเมือง ตำบลปากนคร', '2024-01-15 13:00:00', 1, 'normal', 'standard', 3.2, 0.00, NULL, '{"events": [{"time": "2024-01-15 09:15:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 12:45:00", "status": "delivered", "location": "ที่อยู่ผู้รับ"}]}'),

('TRK240101004', 'NST004', 'pending', 8.4404, 99.9531, 'ถนนราชดำเนิน ตำบลคลัง', '2024-01-15 16:30:00', 0, 'express', 'same_day', 0.8, 850.00, 'จัดส่งด่วน - ต้องได้วันนี้', '{"events": [{"time": "2024-01-15 08:00:00", "status": "pending", "location": "รอจัดส่ง"}]}'),

('TRK240101005', 'NST005', 'failed', 8.4204, 99.9731, 'ถนนส่องแสง ตำบลในเมือง', '2024-01-15 12:00:00', 2, 'normal', 'standard', 4.1, 0.00, 'ติดต่อก่อนส่ง', '{"events": [{"time": "2024-01-15 10:00:00", "status": "out_for_delivery", "location": "ออกจัดส่ง"}, {"time": "2024-01-15 12:30:00", "status": "failed", "location": "ไม่พบที่อยู่"}]}'),

('TRK240101006', 'NST006', 'in_transit', 8.4154, 99.9481, 'ถนนชนะเขต ตำบลบ้านใต้', '2024-01-15 17:00:00', 0, 'normal', 'standard', 2.3, 0.00, NULL, '{"events": [{"time": "2024-01-15 13:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 14:30:00", "status": "in_transit", "location": "ระหว่างทาง"}]}'),

('TRK240101007', 'NST007', 'delivered', 8.4454, 99.9781, 'ถนนจำลอง ตำบลท่าวัง', '2024-01-15 11:30:00', 1, 'urgent', 'express', 1.5, 2100.00, 'COD - ชำระเงินสด', '{"events": [{"time": "2024-01-15 08:45:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 11:15:00", "status": "delivered", "location": "ส่งสำเร็จ"}]}'),

('TRK240101008', 'NST008', 'out_for_delivery', 8.4504, 99.9831, 'ถนนราชดำเนิน ตำบลโพธิ์เสด็จ', '2024-01-15 15:45:00', 0, 'normal', 'standard', 2.8, 0.00, 'บ้านเลขที่อาจไม่ชัดเจน', '{"events": [{"time": "2024-01-15 12:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 14:00:00", "status": "out_for_delivery", "location": "กำลังจัดส่ง"}]}'),

('TRK240101009', 'NST009', 'returned', 8.4104, 99.9881, 'ถนนธรรมราช ตำบลไชยา', '2024-01-15 10:00:00', 3, 'normal', 'standard', 5.2, 0.00, 'ผู้รับปฏิเสธรับของ', '{"events": [{"time": "2024-01-15 09:00:00", "status": "out_for_delivery", "location": "ออกจัดส่ง"}, {"time": "2024-01-15 16:00:00", "status": "returned", "location": "ส่งคืนคลัง"}]}'),

('TRK240101010', 'NST010', 'pending', 8.4604, 99.9431, 'ถนนธานี ตำบลนาเกลียง', '2024-01-15 18:00:00', 0, 'express', 'next_day', 1.2, 750.00, 'จัดส่งพรุ่งนี้', '{"events": [{"time": "2024-01-15 16:00:00", "status": "pending", "location": "เตรียมจัดส่ง"}]}'),

('TRK240101011', 'NST011', 'delivered', 8.4654, 99.9381, 'ถนนมหาราช ตำบลกะปิ', '2024-01-15 13:45:00', 1, 'normal', 'standard', 3.7, 0.00, NULL, '{"events": [{"time": "2024-01-15 10:30:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 13:30:00", "status": "delivered", "location": "จัดส่งสำเร็จ"}]}'),

('TRK240101012', 'NST012', 'in_transit', 8.4054, 99.9931, 'ถนนจำลอง ตำบลดอนทราย', '2024-01-15 16:15:00', 0, 'normal', 'standard', 2.1, 0.00, 'เส้นทางอาจติดขัด', '{"events": [{"time": "2024-01-15 14:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 15:30:00", "status": "in_transit", "location": "ระหว่างทาง"}]}'),

('TRK240101013', 'NST013', 'failed', 8.4704, 99.9981, 'ถนนคลัง ตำบลน้ำใส', '2024-01-15 14:30:00', 1, 'urgent', 'express', 1.9, 950.00, 'ติดต่อไม่ได้', '{"events": [{"time": "2024-01-15 11:00:00", "status": "out_for_delivery", "location": "ออกจัดส่ง"}, {"time": "2024-01-15 15:00:00", "status": "failed", "location": "ติดต่อไม่ได้"}]}'),

('TRK240101014', 'NST014', 'delivered', 8.4754, 99.9331, 'ถนนเสด็จ ตำบลท่าเรือ', '2024-01-15 12:15:00', 1, 'express', 'same_day', 0.9, 1800.00, 'COD - จัดส่งด่วน', '{"events": [{"time": "2024-01-15 09:30:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 12:00:00", "status": "delivered", "location": "ส่งสำเร็จ"}]}'),

('TRK240101015', 'NST015', 'out_for_delivery', 8.4004, 99.9081, 'ถนนเทพนคร ตำบลไทรบุรี', '2024-01-15 17:30:00', 0, 'normal', 'standard', 4.5, 0.00, 'พื้นที่ห่างไกล', '{"events": [{"time": "2024-01-15 15:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 16:30:00", "status": "out_for_delivery", "location": "กำลังจัดส่ง"}]}'),

('TRK240101016', 'NST016', 'pending', 8.4804, 99.9131, 'ถนนสำโรง ตำบลกะเปา', '2024-01-16 09:00:00', 0, 'normal', 'next_day', 2.7, 0.00, 'จัดส่งวันถัดไป', '{"events": [{"time": "2024-01-15 17:00:00", "status": "pending", "location": "รอจัดส่งวันถัดไป"}]}'),

('TRK240101017', 'NST017', 'delivered', 8.4854, 99.9181, 'ถนนนครศรี ตำบลทุ่งใหญ่', '2024-01-15 14:00:00', 1, 'urgent', 'express', 1.6, 1350.00, 'COD - ส่งด่วน', '{"events": [{"time": "2024-01-15 11:30:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 13:45:00", "status": "delivered", "location": "จัดส่งสำเร็จ"}]}'),

('TRK240101018', 'NST018', 'in_transit', 8.4904, 99.9231, 'ถนนธรรมราช ตำบลขนอม', '2024-01-15 18:30:00', 0, 'normal', 'standard', 3.3, 0.00, NULL, '{"events": [{"time": "2024-01-15 16:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 17:30:00", "status": "in_transit", "location": "ออกจัดส่ง"}]}'),

('TRK240101019', 'NST019', 'cancelled', 8.4954, 99.9281, 'ถนนกิ่งแก้ว ตำบลหัวไผ่', NULL, 0, 'normal', 'standard', 2.2, 0.00, 'ลูกค้ายกเลิกการสั่งซื้อ', '{"events": [{"time": "2024-01-15 08:00:00", "status": "pending", "location": "รอจัดส่ง"}, {"time": "2024-01-15 10:00:00", "status": "cancelled", "location": "ยกเลิกแล้ว"}]}'),

('TRK240101020', 'NST020', 'delivered', 8.4004, 99.9631, 'ถนนสีแก้ว ตำบลฉวาง', '2024-01-15 15:30:00', 1, 'express', 'same_day', 1.1, 2250.00, 'COD - จัดส่งด่วนมาก', '{"events": [{"time": "2024-01-15 12:00:00", "status": "picked_up", "location": "คลังสินค้า"}, {"time": "2024-01-15 15:15:00", "status": "delivered", "location": "ส่งสำเร็จ"}]}');

-- สร้าง View สำหรับการวิเคราะห์ข้อมูล delivery_tracking
DROP VIEW IF EXISTS view_delivery_tracking_analysis;
CREATE VIEW view_delivery_tracking_analysis AS
SELECT 
    dt.id,
    dt.tracking_id,
    dt.awb_number,
    da.recipient_name,
    da.address,
    da.province,
    da.district,
    da.subdistrict,
    r.name as rider_name,
    r.rider_code,
    dt.current_status,
    dt.priority_level,
    dt.service_type,
    dt.delivery_attempts,
    dt.package_weight,
    dt.cod_amount,
    dt.cod_status,
    dt.estimated_delivery_time,
    dt.actual_delivery_time,
    dt.failure_reason,
    dt.current_location_lat,
    dt.current_location_lng,
    dt.current_location_address,
    za.zone_name,
    za.zone_code,
    CASE 
        WHEN dt.actual_delivery_time IS NOT NULL AND dt.estimated_delivery_time IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, dt.estimated_delivery_time, dt.actual_delivery_time)
        ELSE NULL 
    END as delivery_time_variance_minutes,
    CASE 
        WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time <= dt.estimated_delivery_time 
        THEN 'on_time'
        WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time > dt.estimated_delivery_time 
        THEN 'late'
        WHEN dt.estimated_delivery_time < NOW() AND dt.current_status NOT IN ('delivered', 'cancelled')
        THEN 'overdue'
        ELSE 'pending'
    END as delivery_performance,
    dt.created_at,
    dt.updated_at
FROM delivery_tracking dt
LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
LEFT JOIN rider r ON dt.rider_id = r.id
LEFT JOIN zone_area za ON da.zone_id = za.id 