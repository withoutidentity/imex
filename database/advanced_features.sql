-- Advanced Database Features for Smart Delivery Zone Planner
-- This file contains triggers and stored procedures for advanced functionality
-- Run this after the basic installation is complete

-- Use the database
USE smart_delivery_db;

-- Create triggers for automatic updates
-- Note: These need to be executed one by one in MySQL command line or phpMyAdmin

-- Trigger 1: Automatic zone assignment when coordinates are updated
DROP TRIGGER IF EXISTS update_delivery_address_zone;
CREATE TRIGGER update_delivery_address_zone 
BEFORE UPDATE ON delivery_address
FOR EACH ROW
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL AND NEW.zone_id IS NULL THEN
        SET NEW.zone_id = (
            SELECT id FROM zone_area 
            WHERE NEW.latitude BETWEEN min_lat AND max_lat 
            AND NEW.longitude BETWEEN min_lng AND max_lng 
            AND is_active = TRUE
            LIMIT 1
        );
    END IF;
END;

-- Trigger 2: Update route statistics when delivery status changes
DROP TRIGGER IF EXISTS update_route_stats;
CREATE TRIGGER update_route_stats
AFTER UPDATE ON delivery_route_items
FOR EACH ROW
BEGIN
    IF NEW.delivery_status != OLD.delivery_status THEN
        UPDATE delivery_route 
        SET completed_deliveries = (
            SELECT COUNT(*) 
            FROM delivery_route_items 
            WHERE route_id = NEW.route_id 
            AND delivery_status = 'delivered'
        )
        WHERE id = NEW.route_id;
    END IF;
END;

-- Stored Procedures for common operations
-- Note: These need to be executed one by one in MySQL command line or phpMyAdmin

-- Procedure 1: Get all deliveries in a specific zone
DROP PROCEDURE IF EXISTS GetDeliveriesInZone;
CREATE PROCEDURE GetDeliveriesInZone(IN zone_id INT)
BEGIN
    SELECT da.*, za.zone_name, za.zone_code
    FROM delivery_address da
    JOIN zone_area za ON da.zone_id = za.id
    WHERE da.zone_id = zone_id
    AND da.delivery_status = 'pending';
END;

-- Procedure 2: Get rider workload for a specific date
DROP PROCEDURE IF EXISTS GetRiderWorkload;
CREATE PROCEDURE GetRiderWorkload(IN rider_id INT, IN target_date DATE)
BEGIN
    SELECT 
        dr.route_code,
        dr.total_deliveries,
        dr.completed_deliveries,
        dr.route_status,
        COUNT(dri.id) as total_items
    FROM delivery_route dr
    LEFT JOIN delivery_route_items dri ON dr.id = dri.route_id
    WHERE dr.rider_id = rider_id
    AND dr.route_date = target_date
    GROUP BY dr.id;
END;

-- Procedure 3: Get delivery statistics for a date range
DROP PROCEDURE IF EXISTS GetDeliveryStats;
CREATE PROCEDURE GetDeliveryStats(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        DATE(created_at) as delivery_date,
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
        SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries,
        ROUND(SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate
    FROM delivery_address 
    WHERE DATE(created_at) BETWEEN start_date AND end_date
    GROUP BY DATE(created_at)
    ORDER BY delivery_date;
END;

-- Procedure 4: Optimize zone assignment for unassigned addresses
DROP PROCEDURE IF EXISTS OptimizeZoneAssignment;
CREATE PROCEDURE OptimizeZoneAssignment()
BEGIN
    UPDATE delivery_address da
    SET zone_id = (
        SELECT za.id 
        FROM zone_area za
        WHERE da.latitude BETWEEN za.min_lat AND za.max_lat 
        AND da.longitude BETWEEN za.min_lng AND za.max_lng 
        AND za.is_active = TRUE
        LIMIT 1
    )
    WHERE da.latitude IS NOT NULL 
    AND da.longitude IS NOT NULL 
    AND da.zone_id IS NULL;
    
    SELECT ROW_COUNT() as updated_records;
END;

-- Function: Calculate distance between two coordinates (Haversine formula)
DROP FUNCTION IF EXISTS CalculateDistance;
CREATE FUNCTION CalculateDistance(lat1 DECIMAL(10,8), lng1 DECIMAL(11,8), lat2 DECIMAL(10,8), lng2 DECIMAL(11,8))
RETURNS DECIMAL(10,3)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE distance DECIMAL(10,3);
    DECLARE earth_radius DECIMAL(10,3) DEFAULT 6371.0; -- Earth radius in kilometers
    
    SET distance = earth_radius * ACOS(
        COS(RADIANS(lat1)) * COS(RADIANS(lat2)) * 
        COS(RADIANS(lng2) - RADIANS(lng1)) + 
        SIN(RADIANS(lat1)) * SIN(RADIANS(lat2))
    );
    
    RETURN distance;
END;

-- Usage Examples:
-- 1. Get deliveries in zone 1: CALL GetDeliveriesInZone(1);
-- 2. Get rider workload: CALL GetRiderWorkload(1, '2024-01-01');
-- 3. Get delivery statistics: CALL GetDeliveryStats('2024-01-01', '2024-01-31');
-- 4. Optimize zone assignment: CALL OptimizeZoneAssignment();
-- 5. Calculate distance: SELECT CalculateDistance(13.7563, 100.5018, 13.7463, 100.5118) as distance_km; 