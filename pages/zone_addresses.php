<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if (!isset($_GET['zone_id']) || empty($_GET['zone_id'])) {
    echo json_encode(['success' => false, 'message' => 'Zone ID is required']);
    exit;
}

$zone_id = intval($_GET['zone_id']);

try {
    // Get zone info
    $zone_stmt = $conn->prepare("SELECT zone_name, zone_code FROM zone_area WHERE id = ?");
    $zone_stmt->execute([$zone_id]);
    $zone = $zone_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$zone) {
        echo json_encode(['success' => false, 'message' => 'Zone not found']);
        exit;
    }
    
    // Check if delivery_tracking table has data and what columns it has
    $check_tracking_stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_tracking");
    $check_tracking_stmt->execute();
    $tracking_count = $check_tracking_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get column names from delivery_tracking
    $columns_stmt = $conn->prepare("SHOW COLUMNS FROM delivery_tracking");
    $columns_stmt->execute();
    $tracking_columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create dynamic column mapping
    $columnMap = [];
    $possibleMappings = [
        'id' => ['id'],
        'awb_number' => ['awb_number', 'AWB', 'เลขพัสดุ'],
        'recipient_name' => ['recipient_name', 'ชื่อผู้รับ', '收件人姓名'],
        'recipient_phone' => ['recipient_phone', 'เบอร์โทรผู้รับ', '收件人电话'],
        'address' => ['address', 'ที่อยู่', '地址'],
        'lat' => ['lat', 'latitude', 'ละติจูด'],
        'lng' => ['lng', 'longitude', 'ลองจิจูด'],
        'status' => ['status', 'สถานะ', '状态'],
        'zone_code' => ['zone_code', 'รหัสโซน', '区域代码']
    ];
    
    foreach ($tracking_columns as $column) {
        $columnName = $column['Field'];
        foreach ($possibleMappings as $key => $possibleNames) {
            if (in_array($columnName, $possibleNames)) {
                $columnMap[$key] = $columnName;
                break;
            }
        }
    }
    
    $addresses = [];
    
    // Check if delivery_address table exists and has data
    $check_address_stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_address WHERE zone_id = ?");
    $check_address_stmt->execute([$zone_id]);
    $address_count = $check_address_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($address_count > 0) {
        // Use delivery_address table with joins
        $sql = "SELECT 
            da.id,
            da.awb_number,
            da.recipient_name,
            da.recipient_phone,
            da.address,
            da.latitude as lat,
            da.longitude as lng,
            da.delivery_status as status,
            za.zone_code
        FROM delivery_address da
        LEFT JOIN zone_area za ON da.zone_id = za.id
        WHERE da.zone_id = ?
        ORDER BY da.created_at DESC
        LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$zone_id]);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($tracking_count > 0) {
        // Fallback to delivery_tracking table
        $selectFields = [];
        foreach ($columnMap as $key => $dbColumn) {
            $selectFields[] = "$dbColumn as $key";
        }
        
        // Add NULL fields for missing columns
        $requiredFields = ['id', 'awb_number', 'recipient_name', 'recipient_phone', 'address', 'lat', 'lng', 'status', 'zone_code'];
        foreach ($requiredFields as $field) {
            if (!isset($columnMap[$field])) {
                $selectFields[] = "NULL as $field";
            }
        }
        
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM delivery_tracking dt";
        
        // Add zone filter if zone_code column exists
        if (isset($columnMap['zone_code'])) {
            $sql .= " WHERE {$columnMap['zone_code']} = (SELECT zone_code FROM zone_area WHERE id = ?)";
            $stmt = $conn->prepare($sql . " ORDER BY " . ($columnMap['id'] ?? 'RAND()') . " DESC LIMIT 100");
            $stmt->execute([$zone_id]);
        } else {
            // If no zone_code column, get all records (limited)
            $stmt = $conn->prepare($sql . " ORDER BY " . ($columnMap['id'] ?? 'RAND()') . " DESC LIMIT 100");
            $stmt->execute();
        }
        
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Process addresses to ensure proper data types
    foreach ($addresses as &$addr) {
        if (isset($addr['lat']) && !empty($addr['lat'])) {
            $addr['lat'] = floatval($addr['lat']);
        }
        if (isset($addr['lng']) && !empty($addr['lng'])) {
            $addr['lng'] = floatval($addr['lng']);
        }
        
        // Ensure proper encoding
        $addr['recipient_name'] = $addr['recipient_name'] ? htmlspecialchars($addr['recipient_name'], ENT_QUOTES, 'UTF-8') : null;
        $addr['address'] = $addr['address'] ? htmlspecialchars($addr['address'], ENT_QUOTES, 'UTF-8') : null;
    }
    
    echo json_encode([
        'success' => true,
        'zone' => $zone,
        'addresses' => $addresses,
        'count' => count($addresses),
        'debug' => [
            'zone_id' => $zone_id,
            'delivery_address_count' => $address_count,
            'delivery_tracking_count' => $tracking_count,
            'column_mapping' => $columnMap
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Zone addresses error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'zone_id' => $zone_id ?? null,
            'error' => $e->getMessage()
        ]
    ]);
}
?>
