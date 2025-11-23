<?php
require_once 'config/config.php';

echo "<h2>🎭 Creating Mockup Data for Delivery System</h2>";

try {
    // Check if tables exist
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_tracking'");
    $stmt->execute();
    $trackingExists = $stmt->rowCount() > 0;
    
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_address'");
    $stmt->execute();
    $addressExists = $stmt->rowCount() > 0;
    
    $stmt = $conn->prepare("SHOW TABLES LIKE 'zone_area'");
    $stmt->execute();
    $zoneExists = $stmt->rowCount() > 0;
    
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_zone_employees'");
    $stmt->execute();
    $employeeExists = $stmt->rowCount() > 0;
    
    echo "<h3>📋 Table Status:</h3>";
    echo "<ul>";
    echo "<li>delivery_tracking: " . ($trackingExists ? "✅ Exists" : "❌ Missing") . "</li>";
    echo "<li>delivery_address: " . ($addressExists ? "✅ Exists" : "❌ Missing") . "</li>";
    echo "<li>zone_area: " . ($zoneExists ? "✅ Exists" : "❌ Missing") . "</li>";
    echo "<li>delivery_zone_employees: " . ($employeeExists ? "✅ Exists" : "❌ Missing") . "</li>";
    echo "</ul>";
    
    // Create delivery_address table if not exists
    if (!$addressExists) {
        echo "<p>🔧 Creating delivery_address table...</p>";
        $stmt = $conn->prepare("
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
            )
        ");
        $stmt->execute();
        echo "<p>✅ delivery_address table created</p>";
    }
    
    // Create delivery_tracking table if not exists
    if (!$trackingExists) {
        echo "<p>🔧 Creating delivery_tracking table...</p>";
        $stmt = $conn->prepare("
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
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tracking_id (tracking_id),
                INDEX idx_awb (awb_number),
                INDEX idx_status (current_status),
                INDEX idx_location (current_location_lat, current_location_lng),
                INDEX idx_created (created_at)
            )
        ");
        $stmt->execute();
        echo "<p>✅ delivery_tracking table created</p>";
    }
    
    // Clear existing data for fresh mockup
    if (isset($_GET['reset']) && $_GET['reset'] == '1') {
        echo "<p>🗑️ Clearing existing data...</p>";
        $conn->prepare("DELETE FROM delivery_address")->execute();
        $conn->prepare("DELETE FROM delivery_tracking")->execute();
        echo "<p>✅ Data cleared</p>";
    }
    
    // Check if we have zones and employees
    if ($zoneExists) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM zone_area");
        $stmt->execute();
        $zoneCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>📍 Zones available: {$zoneCount}</p>";
    }
    
    if ($employeeExists) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_zone_employees WHERE status = 'active'");
        $stmt->execute();
        $employeeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>👥 Active employees: {$employeeCount}</p>";
    }
    
    // Create mockup delivery_address data
    echo "<h3>🎭 Creating Mockup Delivery Address Data</h3>";
    
    $mockupAddresses = [
        [
            'awb_number' => 'NST001' . date('His'),
            'recipient_name' => 'นายสมชาย ใจดี',
            'recipient_phone' => '081-234-5678',
            'address' => '123/45 ถนนราชดำเนิน ตำบลในเมือง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ในเมือง',
            'postal_code' => '80000',
            'latitude' => 8.4304,
            'longitude' => 99.9631,
            'zone_id' => 1,
            'delivery_status' => 'pending'
        ],
        [
            'awb_number' => 'NST002' . date('His'),
            'recipient_name' => 'นางสาวมาลี สวยงาม',
            'recipient_phone' => '082-345-6789',
            'address' => '67/89 ถนนนครศรีธรรมราช ตำบลปากนคร อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ปากนคร',
            'postal_code' => '80000',
            'latitude' => 8.4250,
            'longitude' => 99.9580,
            'zone_id' => 2,
            'delivery_status' => 'assigned'
        ],
        [
            'awb_number' => 'NST003' . date('His'),
            'recipient_name' => 'นายวิชัย รุ่งเรือง',
            'recipient_phone' => '083-456-7890',
            'address' => '234/56 ถนนชัยภูมิ ตำบลท่าวัง อำเภอเมืองนครศrีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ท่าวัง',
            'postal_code' => '80000',
            'latitude' => 8.4380,
            'longitude' => 99.9650,
            'zone_id' => 1,
            'delivery_status' => 'in_transit'
        ],
        [
            'awb_number' => 'NST004' . date('His'),
            'recipient_name' => 'นางพิมพ์ใจ เก่งกาจ',
            'recipient_phone' => '084-567-8901',
            'address' => '345/67 ถนนเทพา ตำบลบ่อยาง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'บ่อยาง',
            'postal_code' => '80000',
            'latitude' => 8.4200,
            'longitude' => 99.9700,
            'zone_id' => 3,
            'delivery_status' => 'delivered'
        ],
        [
            'awb_number' => 'NST005' . date('His'),
            'recipient_name' => 'นายประสิทธิ์ มั่นคง',
            'recipient_phone' => '085-678-9012',
            'address' => '456/78 ถนนนครบาล ตำบลคลัง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'คลัง',
            'postal_code' => '80000',
            'latitude' => 8.4350,
            'longitude' => 99.9550,
            'zone_id' => 2,
            'delivery_status' => 'failed'
        ],
        [
            'awb_number' => 'NST006' . date('His'),
            'recipient_name' => 'นางสาวอรุณี แจ่มใส',
            'recipient_phone' => '086-789-0123',
            'address' => '567/89 ถนนพัฒนาการ ตำบลในเมือง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ในเมือง',
            'postal_code' => '80000',
            'latitude' => 8.4280,
            'longitude' => 99.9620,
            'zone_id' => 1,
            'delivery_status' => 'pending'
        ],
        [
            'awb_number' => 'NST007' . date('His'),
            'recipient_name' => 'นายสุรชัย เจริญ',
            'recipient_phone' => '087-890-1234',
            'address' => '678/90 ถนนกรุงเทพ ตำบลปากนคร อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ปากนคร',
            'postal_code' => '80000',
            'latitude' => 8.4320,
            'longitude' => 99.9580,
            'zone_id' => 2,
            'delivery_status' => 'assigned'
        ],
        [
            'awb_number' => 'NST008' . date('His'),
            'recipient_name' => 'นางวันเพ็ญ สุขใจ',
            'recipient_phone' => '088-901-2345',
            'address' => '789/01 ถนนสุรินทร์ ตำบลท่าวัง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'province' => 'นครศรีธรรมราช',
            'district' => 'เมืองนครศรีธรรมราช',
            'subdistrict' => 'ท่าวัง',
            'postal_code' => '80000',
            'latitude' => 8.4370,
            'longitude' => 99.9680,
            'zone_id' => 1,
            'delivery_status' => 'in_transit'
        ]
    ];
    
    $addressInserted = 0;
    foreach ($mockupAddresses as $addr) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO delivery_address 
                (awb_number, recipient_name, recipient_phone, address, province, district, subdistrict, postal_code, latitude, longitude, zone_id, delivery_status, geocoding_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'success', ?)
            ");
            
            $result = $stmt->execute([
                $addr['awb_number'],
                $addr['recipient_name'],
                $addr['recipient_phone'],
                $addr['address'],
                $addr['province'],
                $addr['district'],
                $addr['subdistrict'],
                $addr['postal_code'],
                $addr['latitude'],
                $addr['longitude'],
                $addr['zone_id'],
                $addr['delivery_status'],
                date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $addressInserted++;
                echo "<p>✅ Added: {$addr['recipient_name']} - {$addr['awb_number']}</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>⚠️ Skipped {$addr['awb_number']}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create mockup delivery_tracking data
    echo "<h3>🚚 Creating Mockup Delivery Tracking Data</h3>";
    
    $mockupTracking = [
        [
            'tracking_id' => 'TRK' . date('YmdHis') . '001',
            'awb_number' => 'NST001' . date('His'),
            'current_status' => 'in_transit',
            'current_location_lat' => 8.4304,
            'current_location_lng' => 99.9631,
            'current_location_address' => 'ใกล้ถนนราชดำเนิน ตำบลในเมือง',
            'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 2.5,
            'cod_amount' => 0.00,
            'special_instructions' => 'ระวังของแตก',
        ],
        [
            'tracking_id' => 'TRK' . date('YmdHis') . '002',
            'awb_number' => 'NST002' . date('His'),
            'current_status' => 'out_for_delivery',
            'current_location_lat' => 8.4250,
            'current_location_lng' => 99.9580,
            'current_location_address' => 'ใกล้ถนนนครศรีธรรมราช ตำบลปากนคร',
            'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'priority_level' => 'urgent',
            'service_type' => 'express',
            'package_weight' => 1.2,
            'cod_amount' => 250.00,
            'special_instructions' => 'โทรก่อนส่ง',
        ],
        [
            'tracking_id' => 'TRK' . date('YmdHis') . '003',
            'awb_number' => 'NST003' . date('His'),
            'current_status' => 'delivered',
            'current_location_lat' => 8.4380,
            'current_location_lng' => 99.9650,
            'current_location_address' => 'ถนนชัยภูมิ ตำบลท่าวัง',
            'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'actual_delivery_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 3.1,
            'cod_amount' => 450.00,
            'special_instructions' => 'ส่งช่วงเช้า',
        ],
        [
            'tracking_id' => 'TRK' . date('YmdHis') . '004',
            'awb_number' => 'NST004' . date('His'),
            'current_status' => 'failed',
            'current_location_lat' => 8.4200,
            'current_location_lng' => 99.9700,
            'current_location_address' => 'ถนนเทพา ตำบลบ่อยาง',
            'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 0.8,
            'cod_amount' => 150.00,
            'failure_reason' => 'recipient_not_available',
            'delivery_attempts' => 2,
            'special_instructions' => 'ติดต่อก่อนส่ง',
        ]
    ];
    
    $trackingInserted = 0;
    foreach ($mockupTracking as $track) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO delivery_tracking 
                (tracking_id, awb_number, current_status, current_location_lat, current_location_lng, current_location_address, estimated_delivery_time, actual_delivery_time, priority_level, service_type, package_weight, cod_amount, special_instructions, failure_reason, delivery_attempts, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $track['tracking_id'],
                $track['awb_number'],
                $track['current_status'],
                $track['current_location_lat'],
                $track['current_location_lng'],
                $track['current_location_address'],
                $track['estimated_delivery_time'],
                $track['actual_delivery_time'] ?? null,
                $track['priority_level'],
                $track['service_type'],
                $track['package_weight'],
                $track['cod_amount'],
                $track['special_instructions'],
                $track['failure_reason'] ?? null,
                $track['delivery_attempts'] ?? 0,
                date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $trackingInserted++;
                echo "<p>✅ Added tracking: {$track['tracking_id']} - {$track['current_status']}</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>⚠️ Skipped {$track['tracking_id']}: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>📊 Summary</h3>";
    echo "<ul>";
    echo "<li>✅ Delivery Addresses Created: {$addressInserted}</li>";
    echo "<li>✅ Delivery Tracking Records Created: {$trackingInserted}</li>";
    echo "</ul>";
    
    echo "<h3>🔗 Next Steps</h3>";
    echo "<ul>";
    echo "<li><a href='pages/delivery.php' target='_blank'>Test Delivery Management System</a></li>";
    echo "<li><a href='view_delivery_tracking.php' target='_blank'>View Delivery Tracking Data</a></li>";
    echo "<li><a href='pages/address_info.php' target='_blank'>View Address Information</a></li>";
    echo "<li><a href='?reset=1' onclick='return confirm(\"Are you sure you want to reset all data?\")'>🗑️ Reset All Data</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
