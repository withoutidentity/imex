-- Simplified Zone Employee Management Schema
-- Basic tables without stored procedures and functions

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
    INDEX idx_zone_code_simple (zone_code),
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
    COALESCE(SUM(zea.workload_percentage), 0) as total_workload_percentage,
    
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