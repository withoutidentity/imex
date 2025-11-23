<?php
require_once 'config/config.php';

echo "<h2>📅 Adding Today's Mockup Data</h2>";

try {
    $today = date('Y-m-d');
    $todayTime = date('Y-m-d H:i:s');
    
    echo "<p><strong>Today:</strong> {$today}</p>";
    
    // Check if we have zones and employees
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM zone_area WHERE is_active = 1");
    $stmt->execute();
    $zoneCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_zone_employees WHERE status = 'active'");
    $stmt->execute();
    $employeeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p>Available zones: {$zoneCount}, Active employees: {$employeeCount}</p>";
    
    // Get available zones
    $stmt = $conn->prepare("SELECT id, zone_name, zone_code FROM zone_area WHERE is_active = 1 LIMIT 5");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clear existing today's data
    $stmt = $conn->prepare("DELETE FROM delivery_address WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    
    $stmt = $conn->prepare("DELETE FROM delivery_tracking WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    
    echo "<p>✅ Cleared existing today's data</p>";
    
    // Create more comprehensive mockup data for today
    $mockupData = [
        // Zone 1 deliveries
        [
            'zone_id' => 1,
            'awb_number' => 'NST' . date('His') . '001',
            'tracking_id' => 'TRK' . date('YmdHis') . '001',
            'recipient_name' => 'นายสมชาย ใจดี',
            'recipient_phone' => '081-234-5678',
            'address' => '123/45 ถนนราชดำเนิน ตำบลในเมือง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4304,
            'longitude' => 99.9631,
            'current_location_lat' => 8.4304,
            'current_location_lng' => 99.9631,
            'current_location_address' => 'ใกล้ถนนราชดำเนิน ตำบลในเมือง',
            'delivery_status' => 'pending',
            'current_status' => 'pending',
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 2.5,
            'cod_amount' => 0.00,
            'special_instructions' => 'ระวังของแตก'
        ],
        [
            'zone_id' => 1,
            'awb_number' => 'NST' . date('His') . '002',
            'tracking_id' => 'TRK' . date('YmdHis') . '002',
            'recipient_name' => 'นางสาวมาลี สวยงาม',
            'recipient_phone' => '082-345-6789',
            'address' => '67/89 ถนนนครศรีธรรมราช ตำบลปากนคร อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4250,
            'longitude' => 99.9580,
            'current_location_lat' => 8.4250,
            'current_location_lng' => 99.9580,
            'current_location_address' => 'ใกล้ถนนนครศรีธรรมราช ตำบลปากนคร',
            'delivery_status' => 'assigned',
            'current_status' => 'picked_up',
            'priority_level' => 'urgent',
            'service_type' => 'express',
            'package_weight' => 1.2,
            'cod_amount' => 250.00,
            'special_instructions' => 'โทรก่อนส่ง'
        ],
        [
            'zone_id' => 1,
            'awb_number' => 'NST' . date('His') . '003',
            'tracking_id' => 'TRK' . date('YmdHis') . '003',
            'recipient_name' => 'นายวิชัย รุ่งเรือง',
            'recipient_phone' => '083-456-7890',
            'address' => '234/56 ถนนชัยภูมิ ตำบลท่าวัง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4380,
            'longitude' => 99.9650,
            'current_location_lat' => 8.4380,
            'current_location_lng' => 99.9650,
            'current_location_address' => 'ถนนชัยภูมิ ตำบลท่าวัง',
            'delivery_status' => 'delivered',
            'current_status' => 'delivered',
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 3.1,
            'cod_amount' => 450.00,
            'special_instructions' => 'ส่งช่วงเช้า'
        ],
        // Zone 2 deliveries
        [
            'zone_id' => 2,
            'awb_number' => 'NST' . date('His') . '004',
            'tracking_id' => 'TRK' . date('YmdHis') . '004',
            'recipient_name' => 'นางพิมพ์ใจ เก่งกาจ',
            'recipient_phone' => '084-567-8901',
            'address' => '345/67 ถนนเทพา ตำบลบ่อยาง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4200,
            'longitude' => 99.9700,
            'current_location_lat' => 8.4200,
            'current_location_lng' => 99.9700,
            'current_location_address' => 'ถนนเทพา ตำบลบ่อยาง',
            'delivery_status' => 'failed',
            'current_status' => 'failed',
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 0.8,
            'cod_amount' => 150.00,
            'special_instructions' => 'ติดต่อก่อนส่ง',
            'failure_reason' => 'recipient_not_available',
            'delivery_attempts' => 2
        ],
        [
            'zone_id' => 2,
            'awb_number' => 'NST' . date('His') . '005',
            'tracking_id' => 'TRK' . date('YmdHis') . '005',
            'recipient_name' => 'นายประสิทธิ์ มั่นคง',
            'recipient_phone' => '085-678-9012',
            'address' => '456/78 ถนนนครบาล ตำบลคลัง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4350,
            'longitude' => 99.9550,
            'current_location_lat' => 8.4350,
            'current_location_lng' => 99.9550,
            'current_location_address' => 'ถนนนครบาล ตำบลคลัง',
            'delivery_status' => 'in_transit',
            'current_status' => 'out_for_delivery',
            'priority_level' => 'urgent',
            'service_type' => 'same_day',
            'package_weight' => 1.5,
            'cod_amount' => 320.00,
            'special_instructions' => 'ส่งก่อน 17:00 น.'
        ],
        // Zone 3 deliveries
        [
            'zone_id' => 3,
            'awb_number' => 'NST' . date('His') . '006',
            'tracking_id' => 'TRK' . date('YmdHis') . '006',
            'recipient_name' => 'นางสาวอรุณี แจ่มใส',
            'recipient_phone' => '086-789-0123',
            'address' => '567/89 ถนนพัฒนาการ ตำบลในเมือง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4280,
            'longitude' => 99.9620,
            'current_location_lat' => 8.4280,
            'current_location_lng' => 99.9620,
            'current_location_address' => 'ถนนพัฒนาการ ตำบลในเมือง',
            'delivery_status' => 'assigned',
            'current_status' => 'in_transit',
            'priority_level' => 'express',
            'service_type' => 'express',
            'package_weight' => 2.8,
            'cod_amount' => 680.00,
            'special_instructions' => 'ของเปราะ ระวังการขนส่ง'
        ],
        [
            'zone_id' => 3,
            'awb_number' => 'NST' . date('His') . '007',
            'tracking_id' => 'TRK' . date('YmdHis') . '007',
            'recipient_name' => 'นายสุรชัย เจริญ',
            'recipient_phone' => '087-890-1234',
            'address' => '678/90 ถนนกรุงเทพ ตำบลปากนคร อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4320,
            'longitude' => 99.9580,
            'current_location_lat' => 8.4320,
            'current_location_lng' => 99.9580,
            'current_location_address' => 'ถนนกรุงเทพ ตำบลปากนคร',
            'delivery_status' => 'pending',
            'current_status' => 'pending',
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 4.2,
            'cod_amount' => 0.00,
            'special_instructions' => 'ส่งช่วงบ่าย'
        ],
        [
            'zone_id' => 3,
            'awb_number' => 'NST' . date('His') . '008',
            'tracking_id' => 'TRK' . date('YmdHis') . '008',
            'recipient_name' => 'นางวันเพ็ญ สุขใจ',
            'recipient_phone' => '088-901-2345',
            'address' => '789/01 ถนนสุรินทร์ ตำบลท่าวัง อำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช 80000',
            'latitude' => 8.4370,
            'longitude' => 99.9680,
            'current_location_lat' => 8.4370,
            'current_location_lng' => 99.9680,
            'current_location_address' => 'ถนนสุรินทร์ ตำบลท่าวัง',
            'delivery_status' => 'delivered',
            'current_status' => 'delivered',
            'priority_level' => 'normal',
            'service_type' => 'standard',
            'package_weight' => 1.8,
            'cod_amount' => 290.00,
            'special_instructions' => 'โทรแจ้งก่อนส่ง'
        ]
    ];
    
    $addressInserted = 0;
    $trackingInserted = 0;
    
    foreach ($mockupData as $data) {
        try {
            // Insert into delivery_address
            $stmt = $conn->prepare("
                INSERT INTO delivery_address 
                (awb_number, recipient_name, recipient_phone, address, province, district, subdistrict, postal_code, latitude, longitude, zone_id, delivery_status, geocoding_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'success', ?)
            ");
            
            $result = $stmt->execute([
                $data['awb_number'],
                $data['recipient_name'],
                $data['recipient_phone'],
                $data['address'],
                'นครศรีธรรมราช',
                'เมืองนครศรีธรรมราช',
                'ในเมือง',
                '80000',
                $data['latitude'],
                $data['longitude'],
                $data['zone_id'],
                $data['delivery_status'],
                $todayTime
            ]);
            
            if ($result) {
                $addressInserted++;
                $addressId = $conn->lastInsertId();
                
                // Insert into delivery_tracking
                $stmt = $conn->prepare("
                    INSERT INTO delivery_tracking 
                    (tracking_id, awb_number, delivery_address_id, current_status, current_location_lat, current_location_lng, current_location_address, estimated_delivery_time, actual_delivery_time, priority_level, service_type, package_weight, cod_amount, special_instructions, failure_reason, delivery_attempts, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $estimatedTime = date('Y-m-d H:i:s', strtotime($todayTime . ' +' . rand(1, 6) . ' hours'));
                $actualTime = null;
                if ($data['current_status'] === 'delivered') {
                    $actualTime = date('Y-m-d H:i:s', strtotime($todayTime . ' +' . rand(1, 4) . ' hours'));
                }
                
                $result2 = $stmt->execute([
                    $data['tracking_id'],
                    $data['awb_number'],
                    $addressId,
                    $data['current_status'],
                    $data['current_location_lat'],
                    $data['current_location_lng'],
                    $data['current_location_address'],
                    $estimatedTime,
                    $actualTime,
                    $data['priority_level'],
                    $data['service_type'],
                    $data['package_weight'],
                    $data['cod_amount'],
                    $data['special_instructions'],
                    $data['failure_reason'] ?? null,
                    $data['delivery_attempts'] ?? 0,
                    $todayTime
                ]);
                
                if ($result2) {
                    $trackingInserted++;
                    echo "<p>✅ Added: {$data['recipient_name']} - {$data['awb_number']} - Zone {$data['zone_id']}</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p>⚠️ Skipped {$data['awb_number']}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Get statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN dt.current_status IN ('pending', 'picked_up') THEN 1 END) as pending,
            COUNT(CASE WHEN dt.current_status = 'in_transit' THEN 1 END) as assigned,
            COUNT(CASE WHEN dt.current_status = 'out_for_delivery' THEN 1 END) as in_transit,
            COUNT(CASE WHEN dt.current_status = 'delivered' THEN 1 END) as delivered,
            COUNT(CASE WHEN dt.current_status = 'failed' THEN 1 END) as failed
        FROM delivery_tracking dt
        WHERE DATE(dt.created_at) = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>📊 Today's Summary ({$today})</h3>";
    echo "<ul>";
    echo "<li>✅ Delivery Addresses Created: {$addressInserted}</li>";
    echo "<li>✅ Delivery Tracking Records Created: {$trackingInserted}</li>";
    echo "</ul>";
    
    echo "<h3>📈 Today's Statistics</h3>";
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 20px 0;'>";
    echo "<div style='background: #f3f4f6; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #374151;'>{$stats['total']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>ทั้งหมด</div>";
    echo "</div>";
    echo "<div style='background: #fef3c7; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #d97706;'>{$stats['pending']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>รอจัดส่ง</div>";
    echo "</div>";
    echo "<div style='background: #dbeafe; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #2563eb;'>{$stats['assigned']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>มอบหมายแล้ว</div>";
    echo "</div>";
    echo "<div style='background: #e9d5ff; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #7c3aed;'>{$stats['in_transit']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>กำลังจัดส่ง</div>";
    echo "</div>";
    echo "<div style='background: #d1fae5; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #059669;'>{$stats['delivered']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>จัดส่งแล้ว</div>";
    echo "</div>";
    echo "<div style='background: #fee2e2; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #dc2626;'>{$stats['failed']}</div>";
    echo "<div style='font-size: 12px; color: #6b7280;'>ไม่สำเร็จ</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<h3>🔗 Test Links</h3>";
    echo "<ul>";
    echo "<li><a href='pages/delivery_enhanced.php' target='_blank' style='color: #2563eb; text-decoration: none; font-weight: 500;'>🚚 Test Enhanced Delivery System</a></li>";
    echo "<li><a href='pages/delivery.php' target='_blank' style='color: #2563eb; text-decoration: none; font-weight: 500;'>📦 Test Original Delivery System</a></li>";
    echo "<li><a href='view_delivery_tracking.php' target='_blank' style='color: #2563eb; text-decoration: none; font-weight: 500;'>👀 View Raw Tracking Data</a></li>";
    echo "<li><a href='pages/address_info.php' target='_blank' style='color: #2563eb; text-decoration: none; font-weight: 500;'>📍 View Address Information</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
