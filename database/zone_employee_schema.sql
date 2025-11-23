-- Zone Employee Management Schema
-- This schema handles employee assignment to delivery zones

-- Create table for zone employees
CREATE TABLE IF NOT EXISTS delivery_zone_employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    employee_name VARCHAR(100) NOT NULL,
    position ENUM('SPT', 'SPT+C', 'SPT+S', 'Manager', 'Supervisor') DEFAULT 'SPT',
    zone_area VARCHAR(100) NOT NULL,
    zone_code VARCHAR(100) NOT NULL,
    nickname VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    hire_date DATE,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_employee_code (employee_code),
    INDEX idx_zone_code (zone_code),
    INDEX idx_status (status),
    INDEX idx_position (position)
);

-- Create table for zone assignments (many-to-many relationship)
CREATE TABLE IF NOT EXISTS zone_employee_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    zone_id INT,
    employee_id INT,
    assignment_type ENUM('primary', 'backup', 'support') DEFAULT 'primary',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    workload_percentage DECIMAL(5,2) DEFAULT 100.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (zone_id) REFERENCES zone_area(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES delivery_zone_employees(id) ON DELETE CASCADE,
    INDEX idx_zone_assignment (zone_id, is_active),
    INDEX idx_employee_assignment (employee_id, is_active),
    INDEX idx_assignment_type (assignment_type)
);

-- Create view for comprehensive zone-employee information
CREATE OR REPLACE VIEW view_zone_employee_details AS
SELECT 
    za.id as zone_id,
    za.zone_code,
    za.zone_name,
    za.zone_type,
    za.color_code,
    za.total_area_km2,
    
    dze.id as employee_id,
    dze.employee_code,
    dze.employee_name,
    dze.position,
    dze.zone_area,
    dze.nickname,
    dze.phone,
    dze.email,
    dze.status as employee_status,
    
    zea.assignment_type,
    zea.start_date,
    zea.end_date,
    zea.workload_percentage,
    zea.is_active as assignment_active,
    
    -- Calculate delivery statistics
    (SELECT COUNT(*) FROM delivery_address da WHERE da.zone_id = za.id) as total_deliveries,
    (SELECT COUNT(*) FROM delivery_address da WHERE da.zone_id = za.id AND da.delivery_status = 'pending') as pending_deliveries,
    (SELECT COUNT(*) FROM delivery_address da WHERE da.zone_id = za.id AND da.delivery_status = 'delivered') as completed_deliveries
    
FROM zone_area za
LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id
ORDER BY za.zone_code, dze.employee_name;

-- Create view for employee workload summary
CREATE OR REPLACE VIEW view_employee_workload AS
SELECT 
    dze.id as employee_id,
    dze.employee_code,
    dze.employee_name,
    dze.position,
    dze.nickname,
    dze.status,
    
    COUNT(zea.zone_id) as assigned_zones,
    SUM(zea.workload_percentage) as total_workload_percentage,
    
    -- Calculate delivery statistics across all zones
    COALESCE(SUM(
        (SELECT COUNT(*) FROM delivery_address da WHERE da.zone_id = zea.zone_id)
    ), 0) as total_deliveries,
    
    COALESCE(SUM(
        (SELECT COUNT(*) FROM delivery_address da WHERE da.zone_id = zea.zone_id AND da.delivery_status = 'pending')
    ), 0) as pending_deliveries,
    
    GROUP_CONCAT(DISTINCT za.zone_code ORDER BY za.zone_code) as zone_codes
    
FROM delivery_zone_employees dze
LEFT JOIN zone_employee_assignments zea ON dze.id = zea.employee_id AND zea.is_active = TRUE
LEFT JOIN zone_area za ON zea.zone_id = za.id
WHERE dze.status = 'active'
GROUP BY dze.id, dze.employee_code, dze.employee_name, dze.position, dze.nickname, dze.status
ORDER BY dze.employee_name;

-- Insert sample zones if they don't exist
INSERT IGNORE INTO zone_area (zone_code, zone_name, zone_type, color_code, description) VALUES
('พัฒนา', 'โซนพัฒนาการ', 'urban', '#3B82F6', 'พื้นที่ถนนพัฒนาการและโดยรอบ'),
('ราชดำเนิน', 'โซนราชดำเนิน', 'urban', '#10B981', 'พื้นที่ถนนราชดำเนินและโดยรอบ'),
('เมืองทอง', 'โซนเมืองทองธานี', 'urban', '#F59E0B', 'พื้นที่เมืองทองธานีและโดยรอบ'),
('ศรีธรรมโศก', 'โซนศรีธรรมโศก', 'urban', '#EF4444', 'พื้นที่ถนนศรีธรรมโศกและโดยรอบ');

-- Add indexes for better performance
CREATE INDEX idx_delivery_address_zone_status ON delivery_address(zone_id, delivery_status);
CREATE INDEX idx_zone_area_code ON zone_area(zone_code);

-- Create stored procedure for assigning employee to zone
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS AssignEmployeeToZone(
    IN p_employee_id INT,
    IN p_zone_id INT,
    IN p_assignment_type ENUM('primary', 'backup', 'support'),
    IN p_workload_percentage DECIMAL(5,2),
    IN p_start_date DATE
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Deactivate existing assignments if this is a primary assignment
    IF p_assignment_type = 'primary' THEN
        UPDATE zone_employee_assignments 
        SET is_active = FALSE, end_date = CURDATE()
        WHERE zone_id = p_zone_id AND assignment_type = 'primary' AND is_active = TRUE;
    END IF;
    
    -- Insert new assignment
    INSERT INTO zone_employee_assignments (
        zone_id, employee_id, assignment_type, start_date, workload_percentage, is_active
    ) VALUES (
        p_zone_id, p_employee_id, p_assignment_type, p_start_date, p_workload_percentage, TRUE
    );
    
    COMMIT;
END //

DELIMITER ;

-- Create function to get zone coverage statistics
DELIMITER //
CREATE FUNCTION IF NOT EXISTS GetZoneCoverage(p_zone_id INT) 
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result JSON;
    
    SELECT JSON_OBJECT(
        'total_deliveries', COALESCE(COUNT(*), 0),
        'pending_deliveries', COALESCE(SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END), 0),
        'in_transit_deliveries', COALESCE(SUM(CASE WHEN delivery_status = 'in_transit' THEN 1 ELSE 0 END), 0),
        'delivered_count', COALESCE(SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END), 0),
        'geocoded_addresses', COALESCE(SUM(CASE WHEN latitude IS NOT NULL THEN 1 ELSE 0 END), 0)
    ) INTO result
    FROM delivery_address 
    WHERE zone_id = p_zone_id;
    
    RETURN result;
END //
DELIMITER ; 