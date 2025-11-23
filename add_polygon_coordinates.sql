-- Add polygon coordinates support to zone_area table
USE smart_delivery_db;

-- Add column for storing complex polygon coordinates
ALTER TABLE zone_area 
ADD COLUMN polygon_coordinates TEXT AFTER color_code,
ADD COLUMN polygon_type ENUM('rectangle', 'polygon') DEFAULT 'rectangle' AFTER polygon_coordinates;

-- Update existing zones to have polygon_type = 'rectangle'
UPDATE zone_area SET polygon_type = 'rectangle' WHERE polygon_coordinates IS NULL;

-- Add index for polygon_type
ALTER TABLE zone_area ADD INDEX idx_polygon_type (polygon_type);
