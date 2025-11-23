-- Geocode module schema
CREATE TABLE IF NOT EXISTS geocode_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    lat DECIMAL(10,6) NULL,
    lon DECIMAL(10,6) NULL,
    source ENUM('override','nominatim','manual') DEFAULT 'nominatim',
    error VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 