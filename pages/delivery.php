<?php
ob_start();
$page_title = 'จัดการการจัดส่ง';
require_once '../config/config.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'update_delivery_status':
                $deliveryId = intval($_POST['delivery_id']);
                $newStatus = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                $stmt = $conn->prepare("UPDATE delivery_address SET delivery_status = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$newStatus, $deliveryId]);
                
                echo json_encode(['success' => $result]);
                exit;
                
            case 'get_delivery_stats':
                $date = $_POST['date'] ?? date('Y-m-d');
                $employeeId = $_POST['employee_id'] ?? null;
                $zoneId = $_POST['zone_id'] ?? null;
                
                $whereClause = "WHERE DATE(da.created_at) = ?";
                $params = [$date];
                
                if ($employeeId) {
                    $empStmt = $conn->prepare("SELECT employee_name FROM delivery_zone_employees WHERE id = ?");
                    $empStmt->execute([$employeeId]);
                    $empName = $empStmt->fetchColumn();
                    
                    $whereClause .= " AND da.`ชื่อของพนักงานนำจ่าย` = ?";
                    $params[] = $empName ?: '';
                }
                
                if ($zoneId) {
                    $whereClause .= " AND da.zone_id = ?";
                    $params[] = $zoneId;
                }
                
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN da.delivery_status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN da.delivery_status = 'assigned' THEN 1 END) as assigned,
                        COUNT(CASE WHEN da.delivery_status = 'in_transit' THEN 1 END) as in_transit,
                        COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as delivered,
                        COUNT(CASE WHEN da.delivery_status = 'failed' THEN 1 END) as failed
                    FROM delivery_address da
                    LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id
                    $whereClause
                ");
                
                $stmt->execute($params);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode($stats);
                exit;
                
            case 'save_default_location':
                $locationName = $_POST['location_name'] ?? '';
                $latitude = floatval($_POST['latitude'] ?? 0);
                $longitude = floatval($_POST['longitude'] ?? 0);
                $isLocked = isset($_POST['is_locked']) && $_POST['is_locked'] === 'true' ? 1 : 0;
                
                if (empty($locationName) || $latitude == 0 || $longitude == 0) {
                    echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
                    exit;
                }
                
                // Unlock all other locations if this one is being locked
                if ($isLocked) {
                    $stmt = $conn->prepare("UPDATE default_starting_locations SET is_locked = FALSE");
                    $stmt->execute();
                }
                
                // Check if location with same name exists
                $stmt = $conn->prepare("SELECT id FROM default_starting_locations WHERE location_name = ? AND is_active = TRUE");
                $stmt->execute([$locationName]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing location
                    $stmt = $conn->prepare("
                        UPDATE default_starting_locations 
                        SET latitude = ?, longitude = ?, is_locked = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$latitude, $longitude, $isLocked, $existing['id']]);
                } else {
                    // Insert new location
                    $stmt = $conn->prepare("
                        INSERT INTO default_starting_locations (location_name, latitude, longitude, is_locked, is_active)
                        VALUES (?, ?, ?, ?, TRUE)
                    ");
                    $result = $stmt->execute([$locationName, $latitude, $longitude, $isLocked]);
                }
                
                echo json_encode(['success' => $result, 'message' => 'บันทึกข้อมูลสำเร็จ']);
                exit;
                
            case 'get_default_locations':
                $stmt = $conn->prepare("
                    SELECT id, location_name, latitude, longitude, is_locked, is_active
                    FROM default_starting_locations
                    WHERE is_active = TRUE
                    ORDER BY is_locked DESC, location_name ASC
                ");
                $stmt->execute();
                $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'locations' => $locations]);
                exit;
                
            case 'update_location_lock':
                $locationId = intval($_POST['location_id'] ?? 0);
                $isLocked = isset($_POST['is_locked']) && $_POST['is_locked'] === 'true' ? 1 : 0;
                
                // Unlock all other locations if this one is being locked
                if ($isLocked) {
                    $stmt = $conn->prepare("UPDATE default_starting_locations SET is_locked = FALSE");
                    $stmt->execute();
                }
                
                $stmt = $conn->prepare("UPDATE default_starting_locations SET is_locked = ? WHERE id = ?");
                $result = $stmt->execute([$isLocked, $locationId]);
                
                echo json_encode(['success' => $result, 'message' => 'อัปเดตสถานะล็อคสำเร็จ']);
                exit;
                
            case 'geocode_address':
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                $address = $_POST['address'] ?? '';
                
                if (!$deliveryId || !$address) {
                    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
                    exit;
                }
                
                // Get delivery address
                $stmt = $conn->prepare("SELECT address FROM delivery_address WHERE id = ?");
                $stmt->execute([$deliveryId]);
                $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$delivery) {
                    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลการจัดส่ง']);
                    exit;
                }
                
                $addressToGeocode = $address;
                
                // Create Google Maps Search URL
                $encodedAddress = urlencode($addressToGeocode);
                $googleMapsUrl = "https://www.google.com/maps/search/{$encodedAddress}";
                
                // Try to extract coordinates from Google Maps URL by following redirects
                // Note: This is a simplified approach - Google Maps may redirect multiple times
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $googleMapsUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, false);
                $response = curl_exec($ch);
                $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);
                
                // Extract coordinates from URL (format: @lat,lng,zoom or /@lat,lng,zoom)
                $lat = null;
                $lng = null;
                
                // Try multiple patterns to extract coordinates
                $patterns = [
                    '/@(-?\d+\.?\d*),(-?\d+\.?\d*),?\d*z/',  // @lat,lng,zoom
                    '/\/@(-?\d+\.?\d*),(-?\d+\.?\d*),?\d*z/', // /@lat,lng,zoom
                    '/@(-?\d+\.?\d*),(-?\d+\.?\d*)/',         // @lat,lng
                    '/\/@(-?\d+\.?\d*),(-?\d+\.?\d*)/',       // /@lat,lng
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $finalUrl, $matches)) {
                        $lat = floatval($matches[1]);
                        $lng = floatval($matches[2]);
                        if ($lat != 0 && $lng != 0) {
                            break;
                        }
                    }
                }
                
                // If coordinates found, return them
                if ($lat !== null && $lng !== null) {
                    echo json_encode([
                        'success' => true,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'formatted_address' => $addressToGeocode,
                        'google_maps_url' => $googleMapsUrl
                    ]);
                } else {
                    // If coordinates not found in URL, return the Google Maps URL for manual selection
                    echo json_encode([
                        'success' => true,
                        'latitude' => null,
                        'longitude' => null,
                        'formatted_address' => $addressToGeocode,
                        'google_maps_url' => $googleMapsUrl,
                        'message' => 'กรุณาเลือกตำแหน่งจากแผนที่'
                    ]);
                }
                exit;
                
            case 'update_coordinates':
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                $latitude = floatval($_POST['latitude'] ?? 0);
                $longitude = floatval($_POST['longitude'] ?? 0);
                
                if (!$deliveryId || !$latitude || !$longitude) {
                    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
                    exit;
                }
                
                // Check if geocoding_source column exists
                try {
                    $stmt = $conn->prepare("UPDATE delivery_address SET latitude = ?, longitude = ?, geocoded_at = NOW(), geocoding_status = 'success', geocoding_source = 'manual' WHERE id = ?");
                    $result = $stmt->execute([$latitude, $longitude, $deliveryId]);
                } catch (Exception $e) {
                    // If geocoding_source doesn't exist, update without it
                    $stmt = $conn->prepare("UPDATE delivery_address SET latitude = ?, longitude = ?, geocoded_at = NOW(), geocoding_status = 'success' WHERE id = ?");
                    $result = $stmt->execute([$latitude, $longitude, $deliveryId]);
                }
                
                echo json_encode(['success' => $result, 'message' => 'อัปเดตพิกัดสำเร็จ']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get current date for filtering
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedEmployee = $_GET['employee'] ?? '';
$selectedZone = $_GET['zone'] ?? '';

// Get employees from delivery_zone_employees
$employees = [];
try {
    $stmt = $conn->prepare("SELECT id, employee_name, nickname, zone_area FROM delivery_zone_employees WHERE status = 'active' ORDER BY employee_name");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Get zones
$zones = [];
try {
    $stmt = $conn->prepare("SELECT id, zone_name, zone_code, color_code FROM zone_area WHERE is_active = 1 ORDER BY zone_code");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Get default starting locations
$defaultLocations = [];
$lockedLocation = null;
try {
    $stmt = $conn->prepare("
        SELECT id, location_name, latitude, longitude, is_locked, is_active
        FROM default_starting_locations
        WHERE is_active = TRUE
        ORDER BY is_locked DESC, location_name ASC
    ");
    $stmt->execute();
    $defaultLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find locked location
    foreach ($defaultLocations as $loc) {
        if ($loc['is_locked']) {
            $lockedLocation = $loc;
            break;
        }
    }
} catch (Exception $e) {
    // Table might not exist yet, handle silently
}

// Get delivery data with employee and zone information
$deliveries = [];
$deliveryStats = ['total' => 0, 'pending' => 0, 'assigned' => 0, 'in_transit' => 0, 'delivered' => 0, 'failed' => 0];

try {
    $whereClause = "WHERE DATE(da.created_at) = ?";
    $params = [$selectedDate];
    
    if ($selectedEmployee) {
        $empName = '';
        foreach ($employees as $emp) {
            if ($emp['id'] == $selectedEmployee) {
                $empName = $emp['employee_name'];
                break;
            }
        }
        $whereClause .= " AND da.`ชื่อของพนักงานนำจ่าย` = ?";
        $params[] = $empName;
    }
    
    if ($selectedZone) {
        $whereClause .= " AND da.zone_id = ?";
        $params[] = $selectedZone;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            da.*,
            za.zone_name,
            za.zone_code,
            za.color_code,
            dze.employee_name,
            dze.nickname as employee_nickname,
            dze.phone as employee_phone,
            zea.assignment_type,
            CASE 
                WHEN da.latitude IS NOT NULL AND da.longitude IS NOT NULL 
                THEN SQRT(POW(69.1 * (da.latitude - 8.4304), 2) + POW(69.1 * (99.9631 - da.longitude) * COS(da.latitude / 57.3), 2))
                ELSE 999
            END as distance_from_center
        FROM delivery_address da
        LEFT JOIN zone_area za ON da.zone_id = za.id
        LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        $whereClause
        ORDER BY da.zone_id, distance_from_center, da.created_at
    ");
    
    $stmt->execute($params);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    foreach ($deliveries as $delivery) {
        $deliveryStats['total']++;
        $deliveryStats[$delivery['delivery_status']]++;
    }
    
} catch (Exception $e) {
    error_log("Delivery data error: " . $e->getMessage());
}

// Group deliveries by zone and employee
$deliveriesByZone = [];
foreach ($deliveries as $delivery) {
    $zoneId = $delivery['zone_id'] ?? 'unassigned';
    $employeeName = $delivery['employee_name'] ?? 'ไม่มีพนักงาน';
    
    if (!isset($deliveriesByZone[$zoneId])) {
        $deliveriesByZone[$zoneId] = [
            'zone_info' => [
                'id' => $zoneId,
                'name' => $delivery['zone_name'] ?? 'ไม่ระบุโซน',
                'code' => $delivery['zone_code'] ?? 'N/A',
                'color' => $delivery['color_code'] ?? '#6b7280'
            ],
            'employees' => []
        ];
    }
    
    if (!isset($deliveriesByZone[$zoneId]['employees'][$employeeName])) {
        $deliveriesByZone[$zoneId]['employees'][$employeeName] = [
            'employee_info' => [
                'name' => $employeeName,
                'nickname' => $delivery['employee_nickname'] ?? '',
                'phone' => $delivery['employee_phone'] ?? '',
                'assignment_type' => $delivery['assignment_type'] ?? ''
            ],
            'deliveries' => [],
            'stats' => ['total' => 0, 'pending' => 0, 'assigned' => 0, 'in_transit' => 0, 'delivered' => 0, 'failed' => 0]
        ];
    }
    
    $deliveriesByZone[$zoneId]['employees'][$employeeName]['deliveries'][] = $delivery;
    $deliveriesByZone[$zoneId]['employees'][$employeeName]['stats']['total']++;
    $deliveriesByZone[$zoneId]['employees'][$employeeName]['stats'][$delivery['delivery_status']]++;
}

function getStatusText($status) {
    $statusMap = [
        'pending' => 'รอจัดส่ง',
        'assigned' => 'มอบหมายแล้ว',
        'in_transit' => 'กำลังจัดส่ง',
        'delivered' => 'จัดส่งแล้ว',
        'failed' => 'ไม่สำเร็จ'
    ];
    return $statusMap[$status] ?? $status;
}

include '../includes/header.php';
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">จัดการการจัดส่ง</h1>
                <p class="text-gray-600">ระบบจัดการรายการจัดส่งและติดตามสถานะรายวัน</p>
            </div>
            <div class="bg-orange-100 p-3 rounded-lg">
                <i class="fas fa-truck-moving text-orange-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกวันที่</label>
                <input type="date" id="date-filter" value="<?php echo $selectedDate; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกพนักงาน</label>
                <select id="employee-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo ($selectedEmployee == $emp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['employee_name'] . ' (' . $emp['nickname'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกโซน</label>
                <select id="zone-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo $zone['id']; ?>" <?php echo ($selectedZone == $zone['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['zone_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="applyFilters()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>กรอง
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-gray-800"><?php echo $deliveryStats['total']; ?></div>
            <div class="text-sm text-gray-600">ทั้งหมด</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-yellow-600"><?php echo $deliveryStats['pending']; ?></div>
            <div class="text-sm text-gray-600">รอจัดส่ง</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-blue-600"><?php echo $deliveryStats['assigned']; ?></div>
            <div class="text-sm text-gray-600">มอบหมายแล้ว</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-purple-600"><?php echo $deliveryStats['in_transit']; ?></div>
            <div class="text-sm text-gray-600">กำลังจัดส่ง</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-green-600"><?php echo $deliveryStats['delivered']; ?></div>
            <div class="text-sm text-gray-600">จัดส่งแล้ว</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-red-600"><?php echo $deliveryStats['failed']; ?></div>
            <div class="text-sm text-gray-600">ไม่สำเร็จ</div>
        </div>
    </div>

    <!-- Delivery Management by Zone and Employee -->
    <?php if (empty($deliveriesByZone)): ?>
        <div class="bg-white p-12 rounded-lg shadow-md text-center">
            <i class="fas fa-inbox text-6xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">ไม่มีข้อมูลการจัดส่ง</h3>
            <p class="text-gray-500">ไม่พบข้อมูลการจัดส่งสำหรับวันที่ <?php echo date('d/m/Y', strtotime($selectedDate)); ?></p>
            <div class="mt-4">
                <a href="../pages/import.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-upload mr-2"></i>นำเข้าข้อมูลการจัดส่ง
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($deliveriesByZone as $zoneId => $zoneData): ?>
            <div class="bg-white rounded-lg shadow-md mb-6">
                <!-- Zone Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo $zoneData['zone_info']['color']; ?>"></div>
                            <h2 class="text-xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($zoneData['zone_info']['name']); ?>
                                <span class="text-sm text-gray-500">(<?php echo $zoneData['zone_info']['code']; ?>)</span>
                            </h2>
                        </div>
                        <div class="text-sm text-gray-600">
                            พนักงาน: <?php echo count($zoneData['employees']); ?> คน
                        </div>
                    </div>
                    
                    <!-- Default Starting Location Section -->
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-map-marker-alt mr-2"></i>ตำแหน่งเริ่มต้น (Lat, Long)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <!-- Location Name Select -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">เลือกตำแหน่งที่บันทึกไว้</label>
                                <select id="location-select-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" 
                                        class="w-full px-2 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        onchange="loadSelectedLocation('<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>')">
                                    <option value="">-- เลือกตำแหน่ง --</option>
                                    <?php foreach ($defaultLocations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>" 
                                                data-lat="<?php echo $loc['latitude']; ?>"
                                                data-lng="<?php echo $loc['longitude']; ?>"
                                                data-name="<?php echo htmlspecialchars($loc['location_name']); ?>">
                                            <?php echo htmlspecialchars($loc['location_name']); ?>
                                            <?php if ($loc['is_locked']): ?>
                                                <span class="text-red-600">🔒</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Latitude Input -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Latitude</label>
                                <input type="number" 
                                       id="start-lat-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" 
                                       step="0.00000001"
                                       class="w-full px-2 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                       placeholder="8.4304"
                                       value="<?php echo $lockedLocation ? $lockedLocation['latitude'] : ''; ?>">
                            </div>
                            
                            <!-- Longitude Input -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Longitude</label>
                                <input type="number" 
                                       id="start-lng-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" 
                                       step="0.00000001"
                                       class="w-full px-2 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                       placeholder="99.9631"
                                       value="<?php echo $lockedLocation ? $lockedLocation['longitude'] : ''; ?>">
                            </div>
                            
                            <!-- Location Name Input -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">ชื่อตำแหน่ง</label>
                                <input type="text" 
                                       id="location-name-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" 
                                       class="w-full px-2 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                       placeholder="เช่น สำนักงาน, คลังสินค้า">
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-end gap-2">
                                <button onclick="openMapPicker('<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>')" 
                                        class="flex-1 bg-blue-600 text-white px-3 py-2 text-sm rounded-md hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-map mr-1"></i>เลือกจากแผนที่
                                </button>
                                <button onclick="saveLocation('<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>')" 
                                        class="flex-1 bg-green-600 text-white px-3 py-2 text-sm rounded-md hover:bg-green-700 transition-colors">
                                    <i class="fas fa-save mr-1"></i>บันทึก
                                </button>
                            </div>
                        </div>
                        
                        <!-- Lock Location Checkbox -->
                        <div class="mt-3 flex items-center">
                            <input type="checkbox" 
                                   id="lock-location-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" 
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                   <?php echo ($lockedLocation && $lockedLocation['is_locked']) ? 'checked' : ''; ?>
                                   onchange="toggleLocationLock('<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>')">
                            <label for="lock-location-<?php echo htmlspecialchars($zoneId, ENT_QUOTES); ?>" class="ml-2 text-sm text-gray-700">
                                ล็อคตำแหน่งนี้ (ใช้ตำแหน่งนี้ทันทีเมื่อเข้าหน้าเว็บ)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Employees in Zone -->
                <?php foreach ($zoneData['employees'] as $employeeName => $employeeData): ?>
                    <?php $employeeId = md5($employeeName); // สร้าง ID สำหรับอ้างอิงใน Map Script ?>
                    <div class="p-6 border-b border-gray-100 last:border-b-0">
                        <!-- Employee Header -->
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-4 gap-4 md:gap-0">
                            <div class="flex items-center w-full md:w-auto">
                                <div class="bg-blue-100 p-2 rounded-lg mr-3 flex-shrink-0">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-800 truncate">
                                        <?php echo htmlspecialchars($employeeData['employee_info']['name']); ?>
                                        <?php if ($employeeData['employee_info']['nickname']): ?>
                                            <span class="text-sm text-gray-500">(<?php echo htmlspecialchars($employeeData['employee_info']['nickname']); ?>)</span>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($employeeData['employee_info']['phone']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($employeeData['employee_info']['phone']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 w-full md:w-auto">
                                <!-- Employee Stats -->
                                <div class="text-left sm:text-right w-full sm:w-auto">
                                    <div class="text-sm text-gray-600 mb-1 sm:mb-0">สถิติ:</div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <span class="bg-gray-100 px-2 py-1 rounded whitespace-nowrap">ทั้งหมด: <?php echo $employeeData['stats']['total']; ?></span>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded whitespace-nowrap">ส่งแล้ว: <?php echo $employeeData['stats']['delivered']; ?></span>
                                        <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded whitespace-nowrap">คงเหลือ: <?php echo ($employeeData['stats']['total'] - $employeeData['stats']['delivered']); ?></span>
                                    </div>
                                </div>
                                <button 
                                    onclick="showEmployeeMapWithId('<?php echo addslashes($employeeName); ?>', '<?php echo $employeeId; ?>')" 
                                    class="bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 transition-colors w-full sm:w-auto flex justify-center items-center">
                                    <i class="fas fa-map-marked-alt mr-1"></i>แผนที่
                                </button>

                                <script>
                                // เก็บข้อมูลใน JavaScript object
                                if (typeof employeeDeliveries === 'undefined') {
                                    var employeeDeliveries = {};
                                }
                                employeeDeliveries['<?php echo $employeeId; ?>'] = <?php echo json_encode($employeeData['deliveries']); ?>;

                                if (typeof showEmployeeMapWithId === 'undefined') {
                                    window.showEmployeeMapWithId = function(employeeName, employeeId) {
                                        const deliveryData = employeeDeliveries[employeeId];
                                        if (typeof showEmployeeMap === 'function') {
                                            showEmployeeMap(employeeName, deliveryData);
                                        }
                                    }
                                }
                                </script>
                            </div>
                        </div>

                        <!-- Delivery List -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left">ลำดับ</th>
                                        <th class="px-3 py-2 text-left">AWB</th>
                                        <th class="px-3 py-2 text-left">ผู้รับ</th>
                                        <th class="px-3 py-2 text-left">เบอร์โทร</th>
                                        <th class="px-3 py-2 text-left">ที่อยู่</th>
                                        <th class="px-3 py-2 text-left whitespace-nowrap">พิกัด</th>
                                        <th class="px-3 py-2 text-left whitespace-nowrap">สถานะ</th>
                                        <th class="px-3 py-2 text-left">การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeData['deliveries'] as $index => $delivery): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50" id="delivery-row-<?php echo $delivery['id']; ?>">
                                            <td class="px-3 py-2 font-medium"><?php echo $index + 1; ?></td>
                                            <td class="px-3 py-2">
                                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($delivery['awb_number']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($delivery['recipient_name']); ?></td>
                                            <td class="px-3 py-2">
                                                <?php if ($delivery['recipient_phone']): ?>
                                                    <a href="tel:<?php echo $delivery['recipient_phone']; ?>" class="text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($delivery['recipient_phone']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 max-w-xs truncate" title="<?php echo htmlspecialchars($delivery['address']); ?>">
                                                <?php echo htmlspecialchars($delivery['address']); ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <?php if ($delivery['latitude'] && $delivery['longitude']): ?>
                                                    <a href="https://www.google.com/maps?q=<?php echo $delivery['latitude']; ?>,<?php echo $delivery['longitude']; ?>" target="_blank" rel="noopener"
                                                       class="text-blue-600 hover:text-blue-800 text-xs">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>แผนที่
                                                        </a>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <?php echo number_format($delivery['distance_from_center'], 2); ?> กม.
                                                    </div>
                                                    <button onclick="editCoordinates(<?php echo $delivery['id']; ?>, <?php echo $delivery['latitude']; ?>, <?php echo $delivery['longitude']; ?>, '<?php echo addslashes($delivery['address']); ?>')" 
                                                            class="text-orange-600 hover:text-orange-800 text-xs mt-1 block"
                                                            style="display: none;">
                                                        <i class="fas fa-edit mr-1"></i>แก้ไขพิกัด
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">ไม่มีพิกัด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <span class="status-badge status-<?php echo $delivery['delivery_status']; ?>">
                                                    <?php echo getStatusText($delivery['delivery_status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex space-x-1">
                                                    <?php if ($delivery['delivery_status'] !== 'delivered'): ?>
                                                        <button onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'delivered')" 
                                                                class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($delivery['delivery_status'] !== 'failed'): ?>
                                                        <button onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'failed')" 
                                                                class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($delivery['delivery_status'] !== 'failed'): ?>
                                                        <button onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'map')" 
                                                                class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700"
                                                                style="background-color: #2563EB !important;">
                                                            <i class="fas fa-map"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Map Modal -->
<div id="map-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 id="map-title" class="text-lg font-semibold text-gray-800">แผนที่การจัดส่ง</h3>
                <button onclick="closeMapModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="delivery-map" style="height: 500px;"></div>
        </div>
    </div>
</div>

<!-- Map Picker Modal for Location Selection -->
<div id="map-picker-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">เลือกตำแหน่งจากแผนที่</h3>
                <button onclick="closeMapPickerModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-4">
                <div id="map-picker" style="height: 500px; width: 100%;"></div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex-1 mr-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input type="number" id="picker-lat" step="0.00000001" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input type="number" id="picker-lng" step="0.00000001" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="closeMapPickerModal()" 
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                            ยกเลิก
                        </button>
                        <button onclick="confirmMapPicker()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            ยืนยัน
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Geocode Address Modal -->
<div id="geocode-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-x-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">ค้นหาที่อยู่ใน Google Maps</h3>
                <button onclick="closeGeocodeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ที่อยู่ที่ต้องการค้นหา</label>
                    <input type="text" id="geocode-address" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="กรอกที่อยู่...">
                    <input type="hidden" id="geocode-delivery-id">
                </div>
                <div id="geocode-map" style="height: 500px; width: 100%;"></div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex-1 mr-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input type="number" id="geocode-lat" step="0.00000001" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input type="number" id="geocode-lng" step="0.00000001" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่ที่พบ</label>
                            <input type="text" id="geocode-formatted-address" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div class="mt-2" style="display: none;">
                            <a id="geocode-google-maps-link" href="#" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-external-link-alt mr-1"></i>เปิดใน Google Maps
                            </a>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="closeGeocodeModal()" 
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                            ยกเลิก
                        </button>
                        <button onclick="searchGeocode()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-search mr-1"></i>ค้นหา
                        </button>
                        <button onclick="saveGeocode()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <i class="fas fa-save mr-1"></i>บันทึก
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Coordinates Modal -->
<div id="edit-coordinates-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">แก้ไขพิกัด</h3>
                <button onclick="closeEditCoordinatesModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ที่อยู่</label>
                    <input type="text" id="edit-address" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                    <input type="hidden" id="edit-delivery-id">
                </div>
                <div id="edit-coordinates-map" style="height: 400px; width: 100%;"></div>
                <div class="mt-4">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                            <input type="number" id="edit-lat" step="0.00000001" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                            <input type="number" id="edit-lng" step="0.00000001" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-2 justify-end">
                        <button onclick="closeEditCoordinatesModal()" 
                                class="w-full md:w-auto px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                            ยกเลิก
                        </button>
                        <button onclick="openEditMapPicker()" 
                                class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-map mr-1"></i>เลือกจากแผนที่
                        </button>
                        <button onclick="saveEditCoordinates()" 
                                class="w-full md:w-auto px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <i class="fas fa-save mr-1"></i>บันทึก
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    @apply px-2 py-1 rounded-full text-xs font-medium;
}
.status-pending { @apply bg-yellow-100 text-yellow-800; }
.status-assigned { @apply bg-blue-100 text-blue-800; }
.status-in_transit { @apply bg-purple-100 text-purple-800; }
.status-delivered { @apply bg-green-100 text-green-800; }
.status-failed { @apply bg-red-100 text-red-800; }

/* Geocode map cursor */
#geocode-map {
    cursor: pointer !important;
}

#geocode-map .leaflet-container {
    cursor: pointer !important;
}
</style>

<script>
let deliveryMap = null;

function applyFilters() {
    const date = document.getElementById('date-filter').value;
    const employee = document.getElementById('employee-filter').value;
    const zone = document.getElementById('zone-filter').value;
    
    const params = new URLSearchParams();
    if (date) params.append('date', date);
    if (employee) params.append('employee', employee);
    if (zone) params.append('zone', zone);
    
    window.location.href = '?' + params.toString();
}

function updateDeliveryStatus(deliveryId, status) {
    // If status is 'map', open geocode modal instead
    if (status === 'map') {
        // Get delivery address
        const row = document.getElementById(`delivery-row-${deliveryId}`);
        if (!row) return;
        
        const addressCell = row.querySelector('td:nth-child(5)'); // Address column
        const address = addressCell ? addressCell.textContent.trim() : '';
        
        if (!address) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบที่อยู่',
                text: 'ไม่พบข้อมูลที่อยู่สำหรับการค้นหา'
            });
            return;
        }
        
        openGeocodeModal(deliveryId, address);
        return;
    }
    
    const notes = prompt(`กรุณาระบุหมายเหตุสำหรับการเปลี่ยนสถานะเป็น "${getStatusText(status)}":`);
    
    if (notes === null) return; // User cancelled
    
    const formData = new FormData();
    formData.append('action', 'update_delivery_status');
    formData.append('delivery_id', deliveryId);
    formData.append('status', status);
    formData.append('notes', notes);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the row
            const row = document.getElementById(`delivery-row-${deliveryId}`);
            const statusCell = row.querySelector('.status-badge');
            statusCell.className = `status-badge status-${status}`;
            statusCell.textContent = getStatusText(status);
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'อัปเดตสถานะสำเร็จ',
                text: `เปลี่ยนสถานะเป็น "${getStatusText(status)}" แล้ว`,
                timer: 2000,
                showConfirmButton: false
            });
            
            // Refresh page after 2 seconds to update statistics
            setTimeout(() => window.location.reload(), 2000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.error || 'ไม่สามารถอัปเดตสถานะได้'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
}

function showEmployeeMap(employeeName, deliveries) {
    document.getElementById('map-title').textContent = `แผนที่การจัดส่ง - ${employeeName}`;
    document.getElementById('map-modal').classList.remove('hidden');
    
    // Initialize map if not exists
    if (!deliveryMap) {
        deliveryMap = L.map('delivery-map').setView([8.4304, 99.9631], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(deliveryMap);
    }
    
    // Clear existing markers
    deliveryMap.eachLayer(layer => {
        if (layer instanceof L.Marker) {
            deliveryMap.removeLayer(layer);
        }
    });
    
    // Add markers for deliveries with coordinates
    const bounds = [];
    deliveries.forEach((delivery, index) => {
        if (delivery.latitude && delivery.longitude) {
            const lat = parseFloat(delivery.latitude);
            const lng = parseFloat(delivery.longitude);
            
            // Choose marker color based on status
            let markerColor = '#6b7280'; // gray
            if (delivery.delivery_status === 'delivered') markerColor = '#10b981'; // green
            else if (delivery.delivery_status === 'failed') markerColor = '#ef4444'; // red
            else if (delivery.delivery_status === 'in_transit') markerColor = '#8b5cf6'; // purple
            else if (delivery.delivery_status === 'assigned') markerColor = '#3b82f6'; // blue
            else if (delivery.delivery_status === 'pending') markerColor = '#f59e0b'; // yellow
            
            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'delivery-marker',
                    html: `<div style="background-color: ${markerColor}; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${index + 1}</div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(deliveryMap);
            
            marker.bindPopup(`
                <div class="p-2">
                    <h6 class="font-semibold">${index + 1}. ${delivery.recipient_name}</h6>
                    <p class="text-sm text-gray-600 mb-1">${delivery.awb_number}</p>
                    <p class="text-sm mb-2">${delivery.address}</p>
                    <div class="flex items-center justify-between">
                        <span class="status-badge status-${delivery.delivery_status}">${getStatusText(delivery.delivery_status)}</span>
                        ${delivery.recipient_phone ? `<a href="tel:${delivery.recipient_phone}" class="text-blue-600 text-sm"><i class="fas fa-phone mr-1"></i>${delivery.recipient_phone}</a>` : ''}
                    </div>
                </div>
            `);
            
            bounds.push([lat, lng]);
        }
    });
    
    // Fit map to show all markers
    if (bounds.length > 0) {
        if (bounds.length === 1) {
            deliveryMap.setView(bounds[0], 15);
        } else {
            deliveryMap.fitBounds(bounds, { padding: [20, 20] });
        }
    }
    
    // Resize map after modal is shown
    setTimeout(() => deliveryMap.invalidateSize(), 300);
}

function showLocationOnMap(lat, lng, name, zoneId) {
    document.getElementById('map-title').textContent = `เส้นทาง - ${name}`;
    document.getElementById('map-modal').classList.remove('hidden');
    
    // Get starting location from the zone's default location
    const startLatInput = document.getElementById(`start-lat-${zoneId}`);
    const startLngInput = document.getElementById(`start-lng-${zoneId}`);
    
    let startLat = <?php echo DEFAULT_MAP_CENTER_LAT; ?>;
    let startLng = <?php echo DEFAULT_MAP_CENTER_LNG; ?>;
    
    // Try to get from locked location first
    <?php if ($lockedLocation): ?>
    startLat = <?php echo $lockedLocation['latitude']; ?>;
    startLng = <?php echo $lockedLocation['longitude']; ?>;
    <?php endif; ?>
    
    // Override with zone-specific location if available
    if (startLatInput && startLatInput.value) {
        startLat = parseFloat(startLatInput.value);
    }
    if (startLngInput && startLngInput.value) {
        startLng = parseFloat(startLngInput.value);
    }
    
    const destinationLat = parseFloat(lat);
    const destinationLng = parseFloat(lng);
    
    // Initialize map if not exists
    if (!deliveryMap) {
        // Center map between start and destination
        const centerLat = (startLat + destinationLat) / 2;
        const centerLng = (startLng + destinationLng) / 2;
        deliveryMap = L.map('delivery-map').setView([centerLat, centerLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(deliveryMap);
    } else {
        // Clear existing markers and polylines
        deliveryMap.eachLayer(layer => {
            if (layer instanceof L.Marker || layer instanceof L.Polyline) {
                deliveryMap.removeLayer(layer);
            }
        });
        
        // Center map between start and destination
        const centerLat = (startLat + destinationLat) / 2;
        const centerLng = (startLng + destinationLng) / 2;
        deliveryMap.setView([centerLat, centerLng], 12);
    }
    
    // Add start marker
    const startMarker = L.marker([startLat, startLng], {
        icon: L.divIcon({
            className: 'custom-start-marker',
            html: '<div style="background-color: #10b981; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="fas fa-play" style="font-size: 12px;"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        })
    }).addTo(deliveryMap);
    startMarker.bindPopup(`<strong>จุดเริ่มต้น</strong><br>Lat: ${startLat.toFixed(8)}<br>Lng: ${startLng.toFixed(8)}`);
    
    // Add destination marker
    const destMarker = L.marker([destinationLat, destinationLng], {
        icon: L.divIcon({
            className: 'custom-dest-marker',
            html: '<div style="background-color: #ef4444; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="fas fa-map-marker-alt" style="font-size: 12px;"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        })
    }).addTo(deliveryMap);
    destMarker.bindPopup(`<strong>${name}</strong><br>Lat: ${destinationLat.toFixed(8)}<br>Lng: ${destinationLng.toFixed(8)}`).openPopup();
    
    // Create route using OSRM API
    const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${destinationLng},${destinationLat}?overview=full&geometries=geojson`;
    
    fetch(osrmUrl)
        .then(response => response.json())
        .then(data => {
            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                const coordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]); // Convert [lng, lat] to [lat, lng]
                
                // Draw route line
                const routeLine = L.polyline(coordinates, {
                    color: '#3B82F6',
                    opacity: 0.8,
                    weight: 5,
                    smoothFactor: 1
                }).addTo(deliveryMap);
                
                // Calculate distance and duration
                const distance = (route.distance / 1000).toFixed(2);
                const duration = Math.round(route.duration / 60);
                
                // Update popup with route info
                destMarker.setPopupContent(`
                    <strong>${name}</strong><br>
                    Lat: ${destinationLat.toFixed(8)}<br>
                    Lng: ${destinationLng.toFixed(8)}<br>
                    <hr style="margin: 5px 0;">
                    <strong>เส้นทาง:</strong><br>
                    ระยะทาง: ${distance} กม.<br>
                    เวลาโดยประมาณ: ${duration} นาที
                `);
                
                // Fit map to show entire route
                deliveryMap.fitBounds(routeLine.getBounds(), {padding: [50, 50]});
            } else {
                throw new Error('No route found');
            }
        })
        .catch(error => {
            console.warn('Routing error:', error);
            // Fallback: draw a straight line if routing fails
            const routeLine = L.polyline(
                [[startLat, startLng], [destinationLat, destinationLng]],
                {color: '#3B82F6', opacity: 0.6, weight: 3, dashArray: '10, 10'}
            ).addTo(deliveryMap);
            
            // Calculate straight-line distance as fallback
            const distance = deliveryMap.distance([startLat, startLng], [destinationLat, destinationLng]) / 1000;
            destMarker.setPopupContent(`
                <strong>${name}</strong><br>
                Lat: ${destinationLat.toFixed(8)}<br>
                Lng: ${destinationLng.toFixed(8)}<br>
                <hr style="margin: 5px 0;">
                <strong>ระยะทางตรง:</strong> ${distance.toFixed(2)} กม.<br>
                <small style="color: #666;">(ไม่สามารถคำนวณเส้นทางได้)</small>
            `);
        });
    
    // Resize map after modal is shown
    setTimeout(() => {
        deliveryMap.invalidateSize();
        // Fit bounds will be called after route is calculated
    }, 300);
}

function closeMapModal() {
    document.getElementById('map-modal').classList.add('hidden');
}

function getStatusText(status) {
    const statusMap = {
        'pending': 'รอจัดส่ง',
        'assigned': 'มอบหมายแล้ว',
        'in_transit': 'กำลังจัดส่ง',
        'delivered': 'จัดส่งแล้ว',
        'failed': 'ไม่สำเร็จ'
    };
    return statusMap[status] || status;
}

// Auto-refresh every 5 minutes
setInterval(() => {
    window.location.reload();
}, 300000);

// Location Management Functions
let currentZoneId = null;
let mapPickerMap = null;
let mapPickerMarker = null;

function loadSelectedLocation(zoneId) {
    const select = document.getElementById(`location-select-${zoneId}`);
    if (!select) return;
    
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const lat = selectedOption.getAttribute('data-lat');
        const lng = selectedOption.getAttribute('data-lng');
        const name = selectedOption.getAttribute('data-name');
        
        const latInput = document.getElementById(`start-lat-${zoneId}`);
        const lngInput = document.getElementById(`start-lng-${zoneId}`);
        const nameInput = document.getElementById(`location-name-${zoneId}`);
        
        if (latInput) latInput.value = lat || '';
        if (lngInput) lngInput.value = lng || '';
        if (nameInput) nameInput.value = name || '';
    }
}

function openMapPicker(zoneId) {
    currentZoneId = zoneId;
    
    // Handle edit coordinates mode
    let currentLat, currentLng;
    if (zoneId === 'edit') {
        const editLatInput = document.getElementById('edit-lat');
        const editLngInput = document.getElementById('edit-lng');
        currentLat = (editLatInput && editLatInput.value) ? editLatInput.value : <?php echo DEFAULT_MAP_CENTER_LAT; ?>;
        currentLng = (editLngInput && editLngInput.value) ? editLngInput.value : <?php echo DEFAULT_MAP_CENTER_LNG; ?>;
    } else {
        const latInput = document.getElementById(`start-lat-${zoneId}`);
        const lngInput = document.getElementById(`start-lng-${zoneId}`);
        currentLat = (latInput && latInput.value) ? latInput.value : <?php echo DEFAULT_MAP_CENTER_LAT; ?>;
        currentLng = (lngInput && lngInput.value) ? lngInput.value : <?php echo DEFAULT_MAP_CENTER_LNG; ?>;
    }
    
    document.getElementById('map-picker-modal').classList.remove('hidden');
    
    // Initialize map if not exists
    setTimeout(() => {
        if (!mapPickerMap) {
            mapPickerMap = L.map('map-picker').setView([parseFloat(currentLat), parseFloat(currentLng)], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPickerMap);
            
            // Add click handler
            mapPickerMap.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                document.getElementById('picker-lat').value = lat.toFixed(8);
                document.getElementById('picker-lng').value = lng.toFixed(8);
                
                // Update marker
                if (mapPickerMarker) {
                    mapPickerMap.removeLayer(mapPickerMarker);
                }
                
                mapPickerMarker = L.marker([lat, lng]).addTo(mapPickerMap)
                    .bindPopup(`ตำแหน่งที่เลือก<br>Lat: ${lat.toFixed(8)}<br>Lng: ${lng.toFixed(8)}`)
                    .openPopup();
            });
        } else {
            mapPickerMap.setView([parseFloat(currentLat), parseFloat(currentLng)], 13);
            
            // Clear existing marker
            if (mapPickerMarker) {
                mapPickerMap.removeLayer(mapPickerMarker);
                mapPickerMarker = null;
            }
        }
        
        // Set initial values
        document.getElementById('picker-lat').value = currentLat;
        document.getElementById('picker-lng').value = currentLng;
        
        mapPickerMap.invalidateSize();
    }, 100);
}

function closeMapPickerModal() {
    document.getElementById('map-picker-modal').classList.add('hidden');
}

function confirmMapPicker() {
    // Check if in edit coordinates mode
    if (window.editCoordinatesPickerMode || currentZoneId === 'edit') {
        const lat = document.getElementById('picker-lat').value;
        const lng = document.getElementById('picker-lng').value;
        
        if (!lat || !lng) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกตำแหน่ง',
                text: 'กรุณาคลิกบนแผนที่เพื่อเลือกตำแหน่ง'
            });
            return;
        }
        
        document.getElementById('edit-lat').value = lat;
        document.getElementById('edit-lng').value = lng;
        
        // Update marker in edit coordinates map
        if (editCoordinatesMap && editCoordinatesMarker) {
            editCoordinatesMap.removeLayer(editCoordinatesMarker);
            editCoordinatesMarker = L.marker([parseFloat(lat), parseFloat(lng)]).addTo(editCoordinatesMap)
                .bindPopup(`ตำแหน่งที่เลือก<br>Lat: ${parseFloat(lat).toFixed(8)}<br>Lng: ${parseFloat(lng).toFixed(8)}`)
                .openPopup();
            editCoordinatesMap.setView([parseFloat(lat), parseFloat(lng)], 15);
        }
        
        closeMapPickerModal();
        window.editCoordinatesPickerMode = false;
        return;
    }
    
    // Original behavior for zone location picker
    if (!currentZoneId) return;
    
    const lat = document.getElementById('picker-lat').value;
    const lng = document.getElementById('picker-lng').value;
    
    if (!lat || !lng) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาเลือกตำแหน่ง',
            text: 'กรุณาคลิกบนแผนที่เพื่อเลือกตำแหน่ง'
        });
        return;
    }
    
    const latInput = document.getElementById(`start-lat-${currentZoneId}`);
    const lngInput = document.getElementById(`start-lng-${currentZoneId}`);
    
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
    
    closeMapPickerModal();
}

function saveLocation(zoneId) {
    const nameInput = document.getElementById(`location-name-${zoneId}`);
    const latInput = document.getElementById(`start-lat-${zoneId}`);
    const lngInput = document.getElementById(`start-lng-${zoneId}`);
    const lockInput = document.getElementById(`lock-location-${zoneId}`);
    
    if (!nameInput || !latInput || !lngInput) {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่พบฟอร์มที่ต้องการ'
        });
        return;
    }
    
    const locationName = nameInput.value.trim();
    const latitude = latInput.value;
    const longitude = lngInput.value;
    const isLocked = lockInput ? lockInput.checked : false;
    
    if (!locationName) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณากรอกชื่อตำแหน่ง',
            text: 'กรุณาระบุชื่อตำแหน่งเพื่อบันทึก'
        });
        return;
    }
    
    if (!latitude || !longitude) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณากรอกพิกัด',
            text: 'กรุณากรอก Latitude และ Longitude หรือเลือกจากแผนที่'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'save_default_location');
    formData.append('location_name', locationName);
    formData.append('latitude', latitude);
    formData.append('longitude', longitude);
    formData.append('is_locked', isLocked);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: data.message || 'บันทึกข้อมูลตำแหน่งสำเร็จ',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.error || 'ไม่สามารถบันทึกข้อมูลได้'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
}

function toggleLocationLock(zoneId) {
    const checkbox = document.getElementById(`lock-location-${zoneId}`);
    const nameInput = document.getElementById(`location-name-${zoneId}`);
    const latInput = document.getElementById(`start-lat-${zoneId}`);
    const lngInput = document.getElementById(`start-lng-${zoneId}`);
    
    if (!checkbox || !nameInput || !latInput || !lngInput) {
        if (checkbox) checkbox.checked = false;
        return;
    }
    
    const locationName = nameInput.value.trim();
    const latitude = latInput.value;
    const longitude = lngInput.value;
    const isLocked = checkbox.checked;
    
    // If trying to lock, need to save location first if not exists
    if (isLocked && (!locationName || !latitude || !longitude)) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาบันทึกตำแหน่งก่อน',
            text: 'กรุณากรอกชื่อตำแหน่งและพิกัด แล้วกดบันทึกก่อนที่จะล็อคตำแหน่ง'
        });
        checkbox.checked = false;
        return;
    }
    
    // If location name exists, try to find it and update lock status
    if (locationName) {
        // First save the location if needed, then update lock
        const formData = new FormData();
        formData.append('action', 'save_default_location');
        formData.append('location_name', locationName);
        formData.append('latitude', latitude);
        formData.append('longitude', longitude);
        formData.append('is_locked', isLocked);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'อัปเดตสำเร็จ',
                    text: data.message || 'อัปเดตสถานะล็อคสำเร็จ',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                checkbox.checked = !isLocked;
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.error || 'ไม่สามารถอัปเดตสถานะได้'
                });
            }
        })
        .catch(error => {
            checkbox.checked = !isLocked;
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
            });
        });
    } else {
        // If no location name, just uncheck
        checkbox.checked = false;
    }
}

// Geocode Address Functions
let geocodeMap = null;
let geocodeMarker = null;
let currentGeocodeDeliveryId = null;

function openGeocodeModal(deliveryId, address) {
    currentGeocodeDeliveryId = deliveryId;
    document.getElementById('geocode-delivery-id').value = deliveryId;
    document.getElementById('geocode-address').value = address;
    document.getElementById('geocode-lat').value = '';
    document.getElementById('geocode-lng').value = '';
    document.getElementById('geocode-formatted-address').value = '';
    document.getElementById('geocode-modal').classList.remove('hidden');
    
    // Initialize map with click handler
    setTimeout(() => {
        if (!geocodeMap) {
            geocodeMap = L.map('geocode-map', {
                cursor: 'pointer'
            }).setView([<?php echo DEFAULT_MAP_CENTER_LAT; ?>, <?php echo DEFAULT_MAP_CENTER_LNG; ?>], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(geocodeMap);
            
            // Add click handler to place marker
            geocodeMap.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Update input fields
                document.getElementById('geocode-lat').value = lat.toFixed(8);
                document.getElementById('geocode-lng').value = lng.toFixed(8);
                
                // Update or create marker
                if (geocodeMarker) {
                    geocodeMap.removeLayer(geocodeMarker);
                }
                
                geocodeMarker = L.marker([lat, lng]).addTo(geocodeMap)
                    .bindPopup(`ตำแหน่งที่เลือก<br>Lat: ${lat.toFixed(8)}<br>Lng: ${lng.toFixed(8)}`)
                    .openPopup();
            });
        } else {
            geocodeMap.setView([<?php echo DEFAULT_MAP_CENTER_LAT; ?>, <?php echo DEFAULT_MAP_CENTER_LNG; ?>], 13);
        }
        
        // Clear existing marker
        if (geocodeMarker) {
            geocodeMap.removeLayer(geocodeMarker);
            geocodeMarker = null;
        }
        
        // Set cursor style
        const mapContainer = document.getElementById('geocode-map');
        if (mapContainer) {
            mapContainer.style.cursor = 'pointer';
        }
        
        geocodeMap.invalidateSize();
    }, 100);
}

function closeGeocodeModal() {
    document.getElementById('geocode-modal').classList.add('hidden');
    currentGeocodeDeliveryId = null;
}

function searchGeocode() {
    const address = document.getElementById('geocode-address').value.trim();
    const deliveryId = document.getElementById('geocode-delivery-id').value;
    
    if (!address) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณากรอกที่อยู่',
            text: 'กรุณากรอกที่อยู่ที่ต้องการค้นหา'
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'กำลังค้นหา...',
        text: 'กรุณารอสักครู่',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'geocode_address');
    formData.append('delivery_id', deliveryId);
    formData.append('address', address);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            // Update formatted address
            document.getElementById('geocode-formatted-address').value = data.formatted_address || address;
            
            // Update Google Maps link
            if (data.google_maps_url) {
                const link = document.getElementById('geocode-google-maps-link');
                if (link) {
                    link.href = data.google_maps_url;
                }
            }
            
            // If coordinates found, update map and inputs
            if (data.latitude !== null && data.longitude !== null) {
                const lat = parseFloat(data.latitude);
                const lng = parseFloat(data.longitude);
                
                // Update inputs
                document.getElementById('geocode-lat').value = lat.toFixed(8);
                document.getElementById('geocode-lng').value = lng.toFixed(8);
                
                // Clear existing marker
                if (geocodeMarker) {
                    geocodeMap.removeLayer(geocodeMarker);
                }
                
                // Add new marker
                geocodeMarker = L.marker([lat, lng]).addTo(geocodeMap)
                    .bindPopup(`<strong>ตำแหน่งที่พบ</strong><br>${data.formatted_address || address}<br>Lat: ${lat.toFixed(8)}<br>Lng: ${lng.toFixed(8)}`)
                    .openPopup();
                
                // Center map on marker
                geocodeMap.setView([lat, lng], 15);
                
                Swal.fire({
                    icon: 'success',
                    title: 'ค้นหาสำเร็จ',
                    text: 'พบตำแหน่งแล้ว กรุณาตรวจสอบและกดบันทึก',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                // No coordinates found - user needs to select manually
                Swal.fire({
                    icon: 'info',
                    title: 'กรุณาเลือกตำแหน่ง',
                    text: data.message || 'กรุณาคลิกบนแผนที่เพื่อเลือกตำแหน่ง หรือเปิด Google Maps เพื่อดูตำแหน่ง',
                    confirmButtonText: 'เข้าใจแล้ว'
                });
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไม่พบตำแหน่ง',
                text: data.error || 'ไม่สามารถค้นหาตำแหน่งได้ กรุณาลองอีกครั้ง'
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
}

function saveGeocode() {
    const deliveryId = document.getElementById('geocode-delivery-id').value;
    const lat = document.getElementById('geocode-lat').value;
    const lng = document.getElementById('geocode-lng').value;
    
    if (!lat || !lng) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาระบุพิกัด',
            text: 'กรุณากรอก Latitude และ Longitude หรือคลิกบนแผนที่เพื่อเลือกตำแหน่ง'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_coordinates');
    formData.append('delivery_id', deliveryId);
    formData.append('latitude', lat);
    formData.append('longitude', lng);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: 'บันทึกพิกัดสำเร็จแล้ว',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeGeocodeModal();
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.error || 'ไม่สามารถบันทึกพิกัดได้'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
}

// Edit Coordinates Functions
let editCoordinatesMap = null;
let editCoordinatesMarker = null;
let currentEditDeliveryId = null;

function editCoordinates(deliveryId, lat, lng, address) {
    currentEditDeliveryId = deliveryId;
    document.getElementById('edit-delivery-id').value = deliveryId;
    document.getElementById('edit-address').value = address;
    document.getElementById('edit-lat').value = lat;
    document.getElementById('edit-lng').value = lng;
    document.getElementById('edit-coordinates-modal').classList.remove('hidden');
    
    // Initialize map
    setTimeout(() => {
        if (!editCoordinatesMap) {
            editCoordinatesMap = L.map('edit-coordinates-map').setView([parseFloat(lat), parseFloat(lng)], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editCoordinatesMap);
            
            // Add click handler
            editCoordinatesMap.on('click', function(e) {
                const newLat = e.latlng.lat;
                const newLng = e.latlng.lng;
                
                document.getElementById('edit-lat').value = newLat.toFixed(8);
                document.getElementById('edit-lng').value = newLng.toFixed(8);
                
                // Update marker
                if (editCoordinatesMarker) {
                    editCoordinatesMap.removeLayer(editCoordinatesMarker);
                }
                
                editCoordinatesMarker = L.marker([newLat, newLng]).addTo(editCoordinatesMap)
                    .bindPopup(`ตำแหน่งที่เลือก<br>Lat: ${newLat.toFixed(8)}<br>Lng: ${newLng.toFixed(8)}`)
                    .openPopup();
            });
        } else {
            editCoordinatesMap.setView([parseFloat(lat), parseFloat(lng)], 15);
        }
        
        // Clear existing marker
        if (editCoordinatesMarker) {
            editCoordinatesMap.removeLayer(editCoordinatesMarker);
        }
        
        // Add marker for current position
        editCoordinatesMarker = L.marker([parseFloat(lat), parseFloat(lng)]).addTo(editCoordinatesMap)
            .bindPopup(`ตำแหน่งปัจจุบัน<br>Lat: ${parseFloat(lat).toFixed(8)}<br>Lng: ${parseFloat(lng).toFixed(8)}`)
            .openPopup();
        
        editCoordinatesMap.invalidateSize();
    }, 100);
}

function closeEditCoordinatesModal() {
    document.getElementById('edit-coordinates-modal').classList.add('hidden');
    currentEditDeliveryId = null;
}

function openEditMapPicker() {
    // Store current edit state
    window.editCoordinatesPickerMode = true;
    
    // Open map picker (will handle edit mode)
    openMapPicker('edit');
}

function saveEditCoordinates() {
    const deliveryId = document.getElementById('edit-delivery-id').value;
    const lat = document.getElementById('edit-lat').value;
    const lng = document.getElementById('edit-lng').value;
    
    if (!lat || !lng) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณากรอกพิกัด',
            text: 'กรุณากรอก Latitude และ Longitude'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_coordinates');
    formData.append('delivery_id', deliveryId);
    formData.append('latitude', lat);
    formData.append('longitude', lng);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: 'อัปเดตพิกัดสำเร็จแล้ว',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                closeEditCoordinatesModal();
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.error || 'ไม่สามารถบันทึกพิกัดได้'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    });
}

</script>

<?php include '../includes/footer.php'; ?>
