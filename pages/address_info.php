<?php
$page_title = 'ข้อมูลที่อยู่และโซน';
require_once '../config/config.php';

// Get filter parameters
$selectedZone = $_GET['zone_filter'] ?? '';
$selectedStatus = $_GET['status_filter'] ?? '';
$selectedEmployee = $_GET['employee_filter'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$currentPage = intval($_GET['page'] ?? 1);
$itemsPerPage = intval($_GET['per_page'] ?? 20);

// Get zones with full information
$zones = [];
try {
    $stmt = $conn->prepare("
        SELECT za.*, 
               za.polygon_coordinates,
               za.polygon_type,
               COUNT(DISTINCT da.id) as delivery_count,
               COUNT(CASE WHEN da.delivery_status = 'pending' THEN 1 END) as pending_count,
               COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as delivered_count,
               COUNT(CASE WHEN da.delivery_status = 'failed' THEN 1 END) as failed_count,
               GROUP_CONCAT(
                   DISTINCT CONCAT(dze.employee_name, ' (', dze.nickname, ')')
                   ORDER BY zea.assignment_type, dze.employee_name
                   SEPARATOR ', '
               ) as assigned_employees,
               COUNT(DISTINCT dze.id) as employee_count
        FROM zone_area za 
        LEFT JOIN delivery_address da ON za.id = da.zone_id 
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        WHERE za.is_active = 1
        GROUP BY za.id 
        ORDER BY za.zone_code
    ");
        $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    error_log("Error fetching zones: " . $e->getMessage());
}

// Get employees for filter
$employees = [];
try {
    $stmt = $conn->prepare("SELECT id, employee_name, nickname FROM delivery_zone_employees WHERE status = 'active' ORDER BY employee_name");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle silently
}

// Build query for addresses
$whereConditions = ["1=1"];
$params = [];

if ($selectedZone) {
    $whereConditions[] = "(COALESCE(za.id, za2.id) = ? OR dt.zone_code = (SELECT zone_code FROM zone_area WHERE id = ?))";
    $params[] = $selectedZone;
    $params[] = $selectedZone;
}

if ($selectedStatus) {
    $whereConditions[] = "COALESCE(da.delivery_status, da2.delivery_status, 'pending') = ?";
    $params[] = $selectedStatus;
}

if ($selectedEmployee) {
    $whereConditions[] = "(COALESCE(zea.employee_id, zea2.employee_id) = ? AND COALESCE(zea.is_active, zea2.is_active) = TRUE)";
    $params[] = $selectedEmployee;
}

if ($searchQuery) {
    $whereConditions[] = "(
        COALESCE(da.recipient_name, da2.recipient_name) LIKE ? OR 
        COALESCE(da.address, da2.address) LIKE ? OR 
        COALESCE(da.awb_number, da2.awb_number, dt.AWB) LIKE ? OR 
        COALESCE(da.recipient_phone, da2.recipient_phone) LIKE ? OR
        dt.AWB LIKE ? OR
        dt.`ชื่อเขต` LIKE ? OR
        dt.`ชื่อของพนักงานนำจ่าย` LIKE ?
    )";
    $searchParam = "%{$searchQuery}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Check if delivery_tracking table has data and get column mapping
$trackingColumns = [];
$hasTrackingData = false;
$trackingColumnMap = [];

try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_tracking LIMIT 1");
    $checkStmt->execute();
    $hasTrackingData = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($hasTrackingData) {
        // Get column structure from delivery_tracking
        $columnsStmt = $conn->prepare("SHOW COLUMNS FROM delivery_tracking");
        $columnsStmt->execute();
        $trackingColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Map common columns - Updated for Thai column names
        $possibleMappings = [
            'awb_number' => ['AWB', 'awb_number', 'awb', 'เลขพัสดุ', '单号', '運單號'],
            'zone_name' => ['ชื่อเขต', 'zone_name', 'ชื่อโซน'],
            'zone_code' => ['รหัสเขต', 'zone_code', 'รหัสโซน', '区域代码', '區域代碼'],
            'franchise_name' => ['ชื่อแฟรนไชส์', 'franchise_name'],
            'franchise_code' => ['รหัสแฟรนไชส์', 'franchise_code'],
            'delivery_branch' => ['ชื่อสาขานำจ่าย', 'delivery_branch', 'สาขาจัดส่ง'],
            'delivery_employee' => ['ชื่อของพนักงานนำจ่าย', 'delivery_employee', 'พนักงานจัดส่ง'],
            'delivery_time' => ['เวลาที่นำจ่ายพัสดุ', 'delivery_time'],
            'gateway_time' => ['เวลาเกทเวย์นำส่ง', 'gateway_time'],
            'branch_arrival_time' => ['เวลาที่พัสดุถึงสาขา', 'branch_arrival_time']
        ];
        
        foreach ($possibleMappings as $alias => $possibleNames) {
            foreach ($possibleNames as $name) {
                if (in_array($name, $trackingColumns)) {
                    $trackingColumnMap[$alias] = $name;
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error checking tracking data: " . $e->getMessage());
}

// Get total count for pagination
$totalAddresses = 0;
try {
    // Check if we should count from delivery_tracking or delivery_address
    if ($hasTrackingData) {
        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT dt.id) as total
            FROM delivery_tracking dt
            LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
            LEFT JOIN zone_area za ON da.zone_id = za.id
            LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
            LEFT JOIN delivery_address da2 ON dt.awb_number = da2.awb_number AND da.id IS NULL
            LEFT JOIN zone_area za2 ON da2.zone_id = za2.id AND za.id IS NULL
            LEFT JOIN zone_employee_assignments zea2 ON da2.zone_id = zea2.zone_id AND zea2.is_active = TRUE AND zea.id IS NULL
            LEFT JOIN delivery_zone_employees dze2 ON zea2.employee_id = dze2.id AND dze2.status = 'active' AND dze.id IS NULL
            WHERE $whereClause
        ");
    } else {
        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT da.id) as total
            FROM delivery_address da
            LEFT JOIN zone_area za ON da.zone_id = za.id
            LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
            WHERE $whereClause
        ");
    }
    $countStmt->execute($params);
    $totalAddresses = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    error_log("Error counting addresses: " . $e->getMessage());
}

// Calculate pagination
$totalPages = ceil($totalAddresses / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// Debug information
$debugInfo = [
    'hasTrackingData' => $hasTrackingData,
    'trackingColumnMapCount' => count($trackingColumnMap),
    'trackingColumnMap' => $trackingColumnMap,
    'whereClause' => $whereClause,
    'params' => $params
];

// Get addresses with delivery_tracking integration
$addresses = [];
try {
    if ($hasTrackingData) {
        // Always try to get data from delivery_tracking if it has data
        // Query with delivery_tracking as primary source
        $trackingSelects = [];
        if (!empty($trackingColumnMap)) {
            foreach ($trackingColumnMap as $alias => $actualCol) {
                $trackingSelects[] = "dt.`{$actualCol}` AS {$alias}";
            }
        }
        
        // Build the SELECT clause
        $additionalSelects = !empty($trackingSelects) ? ', ' . implode(', ', $trackingSelects) : '';
        
        // Query with Thai column names from delivery_tracking
        $stmt = $conn->prepare("
            SELECT 
                dt.AWB as tracking_number,
                dt.AWB as dt_awb,
                dt.`ชื่อเขต` as zone_name_thai,
                dt.`รหัสเขต` as zone_code_thai,
                dt.`ชื่อแฟรนไชส์` as franchise_name,
                dt.`รหัสแฟรนไชส์` as franchise_code,
                dt.`ชื่อสาขานำจ่าย` as delivery_branch_name,
                dt.`ชื่อของพนักงานนำจ่าย` as delivery_employee_name,
                dt.`เวลาที่นำจ่ายพัสดุ` as delivery_time,
                dt.`เวลาเกทเวย์นำส่ง` as gateway_time,
                dt.`เวลาที่พัสดุถึงสาขา` as branch_arrival_time,
                'delivery_tracking' as data_source,
                NULL as latitude,
                NULL as longitude,
                NULL as current_location_address,
                NULL as failure_reason,
                NULL as delivery_notes,
                NULL as created_at,
                NULL as updated_at
                {$additionalSelects},
                -- Get data from delivery_address if linked by ID or AWB
                COALESCE(da.id, da2.id) as address_id,
                COALESCE(da.recipient_name, da2.recipient_name, 'ไม่ระบุ') as recipient_name,
                COALESCE(da.address, da2.address, 'ไม่ระบุ') as address,
                COALESCE(da.recipient_phone, da2.recipient_phone, 'ไม่ระบุ') as recipient_phone,
                COALESCE(da.latitude, da2.latitude) as da_latitude,
                COALESCE(da.longitude, da2.longitude) as da_longitude,
                COALESCE(da.delivery_status, da2.delivery_status, 'pending') as da_status,
                COALESCE(za.zone_name, za2.zone_name, dt.`ชื่อเขต`) as zone_name,
                COALESCE(za.zone_code, za2.zone_code, dt.`รหัสเขต`) as zone_code,
                COALESCE(za.color_code, za2.color_code, '#dc2626') as color_code,
                COALESCE(za.description, za2.description) as zone_description,
                COALESCE(dze.employee_name, dze2.employee_name, dt.`ชื่อของพนักงานนำจ่าย`) as employee_name,
                COALESCE(dze.nickname, dze2.nickname) as employee_nickname,
                COALESCE(dze.phone, dze2.phone) as employee_phone,
                COALESCE(zea.assignment_type, zea2.assignment_type) as assignment_type,
                CASE 
                    WHEN COALESCE(dt.current_location_lat, da.latitude, da2.latitude) IS NOT NULL AND COALESCE(dt.current_location_lng, da.longitude, da2.longitude) IS NOT NULL 
                    THEN SQRT(POW(69.1 * (COALESCE(dt.current_location_lat, da.latitude, da2.latitude) - 8.4304), 2) + POW(69.1 * (99.9631 - COALESCE(dt.current_location_lng, da.longitude, da2.longitude)) * COS(COALESCE(dt.current_location_lat, da.latitude, da2.latitude) / 57.3), 2))
                    ELSE NULL
                END as distance_from_center,
                CASE 
                    WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time <= dt.estimated_delivery_time 
                    THEN 'ตรงเวลา'
                    WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time > dt.estimated_delivery_time 
                    THEN 'ล่าช้า'
                    WHEN dt.estimated_delivery_time < NOW() AND dt.current_status NOT IN ('delivered', 'cancelled')
                    THEN 'เกินกำหนด'
                    ELSE 'ปกติ'
                END as performance_status
            FROM delivery_tracking dt
            -- Try to join by AWB number to get zone and employee info
            LEFT JOIN delivery_address da ON dt.AWB = da.awb_number
            LEFT JOIN zone_area za ON da.zone_id = za.id
            LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
            -- Additional fallback joins
            LEFT JOIN delivery_address da2 ON dt.AWB = da2.awb_number AND da.id IS NULL
            LEFT JOIN zone_area za2 ON da2.zone_id = za2.id AND za.id IS NULL
            LEFT JOIN zone_employee_assignments zea2 ON da2.zone_id = zea2.zone_id AND zea2.is_active = TRUE AND zea.id IS NULL
            LEFT JOIN delivery_zone_employees dze2 ON zea2.employee_id = dze2.id AND dze2.status = 'active' AND dze.id IS NULL
            WHERE $whereClause
            ORDER BY COALESCE(za.zone_code, za2.zone_code, dt.`รหัสเขต`), distance_from_center
            LIMIT $itemsPerPage OFFSET $offset
        ");
    } else {
        // Fallback to delivery_address only
        $stmt = $conn->prepare("
            SELECT 
                da.*,
                da.id as address_id,
                NULL as tracking_id,
                NULL as tracking_number,
                da.delivery_status as current_status,
                'standard' as priority_level,
                'standard' as service_type,
                NULL as package_weight,
                NULL as cod_amount,
                0 as delivery_attempts,
                NULL as estimated_delivery_time,
                NULL as actual_delivery_time,
                NULL as current_location_address,
                NULL as failure_reason,
                NULL as delivery_notes,
                'ปกติ' as performance_status,
                za.zone_name,
                za.zone_code as za_zone_code,
                za.color_code,
                za.description as zone_description,
                dze.employee_name,
                dze.nickname as employee_nickname,
                dze.phone as employee_phone,
                zea.assignment_type,
                CASE 
                    WHEN da.latitude IS NOT NULL AND da.longitude IS NOT NULL 
                    THEN SQRT(POW(69.1 * (da.latitude - 8.4304), 2) + POW(69.1 * (99.9631 - da.longitude) * COS(da.latitude / 57.3), 2))
                    ELSE NULL
                END as distance_from_center
            FROM delivery_address da
            LEFT JOIN zone_area za ON da.zone_id = za.id
            LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
            WHERE $whereClause
            ORDER BY za.zone_code, distance_from_center, da.created_at DESC
            LIMIT $itemsPerPage OFFSET $offset
        ");
    }
    
    $stmt->execute($params);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    } catch (Exception $e) {
    error_log("Error fetching addresses: " . $e->getMessage());
}

// Calculate statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'assigned' => 0,
    'in_transit' => 0,
    'delivered' => 0,
    'failed' => 0,
    'with_coordinates' => 0,
    'zones_count' => 0
];

foreach ($addresses as $addr) {
    $stats['total']++;
    $stats[$addr['delivery_status']]++;
    if ($addr['latitude'] && $addr['longitude']) {
        $stats['with_coordinates']++;
    }
}

$stats['zones_count'] = count(array_unique(array_column($addresses, 'zone_id')));

include '../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <!-- Modern Header with Gradient -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between">
            <div>
                    <h1 class="text-3xl font-bold mb-2">📍 ข้อมูลที่อยู่และโซนจัดส่ง</h1>
                    <p class="text-blue-100 text-lg">จัดการและติดตามที่อยู่จัดส่งพร้อมข้อมูลโซนและพนักงาน</p>
                    
                    <!-- Debug Info -->
                    <div class="mt-4 p-3 bg-white/10 rounded-lg text-sm">
                        <strong>Debug Info:</strong>
                        <ul class="mt-2 space-y-1">
                            <li>• Has Tracking Data: <?php echo $debugInfo['hasTrackingData'] ? 'Yes' : 'No'; ?></li>
                            <li>• Column Map Count: <?php echo $debugInfo['trackingColumnMapCount']; ?></li>
                            <li>• Total Addresses: <?php echo count($addresses); ?></li>
                            <?php if (!empty($debugInfo['trackingColumnMap'])): ?>
                                <li>• Mapped Columns: <?php echo implode(', ', array_keys($debugInfo['trackingColumnMap'])); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="mt-3 flex items-center space-x-4 text-sm">
                        <span class="bg-white/20 px-3 py-1 rounded-full">
                            <i class="fas fa-database mr-1"></i>
                            <?php echo number_format($totalAddresses); ?> รายการทั้งหมด
                        </span>
                        <span class="bg-white/20 px-3 py-1 rounded-full">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <?php echo count($zones); ?> โซน
                        </span>
                        <?php if ($hasTrackingData): ?>
                            <span class="bg-green-400/20 px-3 py-1 rounded-full">
                                <i class="fas fa-truck mr-1"></i>
                                Delivery Tracking เปิดใช้งาน
                            </span>
                        <?php else: ?>
                            <span class="bg-yellow-400/20 px-3 py-1 rounded-full">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                ใช้ข้อมูลจาก Address เท่านั้น
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hidden lg:flex space-x-3">
                    <a href="zones.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-all duration-200">
                        <i class="fas fa-map mr-2"></i>จัดการโซน
                    </a>
                    <a href="zone_employee_matching.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition-all duration-200">
                        <i class="fas fa-users-cog mr-2"></i>จับคู่โซน-พนักงาน
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards with Modern Design -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-list-alt text-2xl text-blue-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ทั้งหมด</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalAddresses); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-2xl text-yellow-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">รอจัดส่ง</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-check text-2xl text-blue-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">มอบหมายแล้ว</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['assigned']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-truck text-2xl text-purple-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">กำลังส่ง</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['in_transit']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-2xl text-green-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">จัดส่งแล้ว</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['delivered']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ไม่สำเร็จ</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['failed']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modern Search and Filter Panel -->
        <div class="bg-white rounded-xl shadow-lg mb-8">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🔍 ค้นหาและกรองข้อมูล</h3>
                
                <form method="GET" class="space-y-4">
                    <!-- Search Bar -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                               placeholder="ค้นหาชื่อผู้รับ, ที่อยู่, AWB, หรือเบอร์โทร..."
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Filters Row -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">โซน</label>
                            <select name="zone_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">ทุกโซน</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>" <?php echo ($selectedZone == $zone['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['zone_code'] . ')'); ?>
                                        (<?php echo $zone['delivery_count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">สถานะ</label>
                            <select name="status_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">ทุกสถานะ</option>
                                <option value="pending" <?php echo ($selectedStatus == 'pending') ? 'selected' : ''; ?>>รอจัดส่ง</option>
                                <option value="assigned" <?php echo ($selectedStatus == 'assigned') ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                                <option value="in_transit" <?php echo ($selectedStatus == 'in_transit') ? 'selected' : ''; ?>>กำลังจัดส่ง</option>
                                <option value="delivered" <?php echo ($selectedStatus == 'delivered') ? 'selected' : ''; ?>>จัดส่งแล้ว</option>
                                <option value="failed" <?php echo ($selectedStatus == 'failed') ? 'selected' : ''; ?>>ไม่สำเร็จ</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">พนักงาน</label>
                            <select name="employee_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">ทุกคน</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($selectedEmployee == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['employee_name'] . ' (' . $emp['nickname'] . ')'); ?>
                                    </option>
            <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">แสดงต่อหน้า</label>
                            <select name="per_page" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="20" <?php echo ($itemsPerPage == 20) ? 'selected' : ''; ?>>20 รายการ</option>
                                <option value="50" <?php echo ($itemsPerPage == 50) ? 'selected' : ''; ?>>50 รายการ</option>
                                <option value="100" <?php echo ($itemsPerPage == 100) ? 'selected' : ''; ?>>100 รายการ</option>
                            </select>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>ค้นหา
                        </button>
                        <a href="?" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-undo mr-2"></i>ล้างตัวกรอง
                        </a>
                        <button type="button" onclick="exportData()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Zone Summary (if no specific zone selected) -->
        <?php if (!$selectedZone && count($zones) > 0): ?>
            <div class="bg-white rounded-xl shadow-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">📊 สรุปโซนการจัดส่ง</h3>
                    <p class="text-gray-600">ภาพรวมการจัดส่งแยกตามโซน</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach (array_slice($zones, 0, 6) as $zone): ?>
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-4 border-l-4 hover:shadow-md transition-shadow" 
                                 style="border-left-color: <?php echo $zone['color_code']; ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-4 h-4 rounded-full" style="background-color: <?php echo $zone['color_code']; ?>"></div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($zone['zone_name']); ?></h4>
                                    </div>
                                    <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full">
                                        <?php echo htmlspecialchars($zone['zone_code']); ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2 text-center text-sm">
                                    <div>
                                        <div class="font-bold text-gray-900"><?php echo $zone['delivery_count']; ?></div>
                                        <div class="text-gray-500">ทั้งหมด</div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-green-600"><?php echo $zone['delivered_count']; ?></div>
                                        <div class="text-gray-500">ส่งแล้ว</div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-yellow-600"><?php echo $zone['pending_count']; ?></div>
                                        <div class="text-gray-500">รอส่ง</div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 flex justify-between items-center">
                                    <div class="text-xs text-gray-600">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $zone['employee_count']; ?> พนักงาน
                                    </div>
                                    <a href="?zone_filter=<?php echo $zone['id']; ?>" 
                                       class="text-xs bg-blue-600 text-white px-3 py-1 rounded-full hover:bg-blue-700 transition-colors">
                                        ดูรายละเอียด
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($zones) > 6): ?>
                        <div class="mt-6 text-center">
                            <a href="zones.php" class="text-blue-600 hover:text-blue-800 font-medium">
                                ดูโซนทั้งหมด (<?php echo count($zones); ?> โซน) →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Results Table -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">📋 รายละเอียดที่อยู่</h3>
                        <p class="text-gray-600">
                            แสดง <?php echo count($addresses); ?> รายการจากทั้งหมด <?php echo number_format($totalAddresses); ?> รายการ
                            <?php if ($selectedZone): ?>
                                <?php 
                                $selectedZoneData = array_filter($zones, function($z) use ($selectedZone) { 
                                    return $z['id'] == $selectedZone; 
                                });
                                if (!empty($selectedZoneData)) {
                                    $selectedZoneData = array_values($selectedZoneData)[0];
                                    echo " - โซน: " . htmlspecialchars($selectedZoneData['zone_name']);
                                }
                                ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- Pagination Info -->
                    <?php if ($totalPages > 1): ?>
                        <div class="text-sm text-gray-500">
                            หน้า <?php echo $currentPage; ?> จาก <?php echo $totalPages; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($addresses)): ?>
                <div class="p-12 text-center">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-search text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">ไม่พบข้อมูลที่อยู่</h3>
                    <p class="text-gray-500 mb-4">ไม่พบข้อมูลที่อยู่ตามเงื่อนไขที่เลือก</p>
                    <a href="?" class="text-blue-600 hover:text-blue-800 font-medium">
                        ล้างตัวกรองและแสดงทั้งหมด →
                    </a>
                </div>
            <?php else: ?>
                <!-- Mobile Card View -->
                <div class="lg:hidden">
                    <?php foreach ($addresses as $index => $addr): ?>
                        <div class="p-4 border-b border-gray-200 last:border-b-0">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($addr['recipient_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">
                                        <?php echo htmlspecialchars($addr['address']); ?>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                            <?php echo htmlspecialchars($addr['awb_number']); ?>
                                        </span>
                                        <?php if ($addr['recipient_phone']): ?>
                                            <a href="tel:<?php echo $addr['recipient_phone']; ?>" class="bg-blue-100 text-blue-700 px-2 py-1 rounded">
                                                <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($addr['recipient_phone']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="ml-4 text-right">
                                    <?php if ($addr['zone_name']): ?>
                                        <div class="flex items-center justify-end mb-2">
                                            <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $addr['color_code']; ?>"></div>
                                            <span class="text-sm font-medium"><?php echo htmlspecialchars($addr['zone_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'assigned' => 'bg-blue-100 text-blue-800',
                                        'in_transit' => 'bg-purple-100 text-purple-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusTexts = [
                                        'pending' => 'รอจัดส่ง',
                                        'assigned' => 'มอบหมายแล้ว',
                                        'in_transit' => 'กำลังจัดส่ง',
                                        'delivered' => 'จัดส่งแล้ว',
                                        'failed' => 'ไม่สำเร็จ'
                                    ];
                                    $status = $addr['delivery_status'];
                                    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                    $statusText = $statusTexts[$status] ?? $status;
                                    ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo $colorClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
        </div>
    </div>

                            <?php if ($addr['employee_name']): ?>
                                <div class="flex items-center text-sm text-gray-600 mb-2">
                                    <i class="fas fa-user mr-2"></i>
                                    <?php echo htmlspecialchars($addr['employee_name'] . ' (' . $addr['employee_nickname'] . ')'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($addr['latitude'] && $addr['longitude']): ?>
                                <button onclick="showLocationOnMap(<?php echo $addr['latitude']; ?>, <?php echo $addr['longitude']; ?>, '<?php echo addslashes($addr['recipient_name']); ?>')" 
                                        class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-map-marker-alt mr-1"></i>ดูแผนที่
                                    <?php if ($addr['distance_from_center']): ?>
                                        (<?php echo number_format($addr['distance_from_center'], 2); ?> กม.)
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                                                <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้รับ</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ที่อยู่</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โซน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">พนักงาน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ & ประสิทธิภาพ</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รายละเอียด</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">การดำเนินการ</th>
                </tr>
            </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($addresses as $index => $addr): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo ($currentPage - 1) * $itemsPerPage + $index + 1; ?>
                                    </td>
                                                                        <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($addr['tracking_number']): ?>
                                            <div class="space-y-1">
                                                <span class="font-mono text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                    📦 <?php echo htmlspecialchars($addr['tracking_number']); ?>
                                                </span>
                                                <?php if (isset($addr['awb_number']) && $addr['awb_number']): ?>
                                                    <div class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                        AWB: <?php echo htmlspecialchars($addr['awb_number']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                <?php else: ?>
                                            <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($addr['awb_number'] ?? 'N/A'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                                                        <td class="px-6 py-4">
                        <?php 
                                        $recipientName = $addr['recipient_name'] ?? $addr['da_recipient_name'] ?? 'ไม่ระบุ';
                                        $recipientPhone = $addr['recipient_phone'] ?? $addr['da_phone'] ?? null;
                                        ?>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($recipientName); ?></div>
                                        <?php if ($recipientPhone): ?>
                                            <div class="text-sm text-gray-500">
                                                <a href="tel:<?php echo $recipientPhone; ?>" class="text-blue-600 hover:underline">
                                                    <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($recipientPhone); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                            </td>
                                                                        <td class="px-6 py-4">
                                        <?php 
                                        $address = $addr['address'] ?? $addr['da_address'] ?? 'ไม่ระบุ';
                                        $currentLocationAddress = $addr['current_location_address'] ?? null;
                                        ?>
                                        <div class="space-y-1">
                                            <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($address); ?>">
                                                <i class="fas fa-home mr-1 text-gray-400"></i><?php echo htmlspecialchars($address); ?>
                                            </div>
                                            <?php if ($currentLocationAddress && $currentLocationAddress != $address): ?>
                                                <div class="text-xs text-blue-600 max-w-xs truncate" title="<?php echo htmlspecialchars($currentLocationAddress); ?>">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>ปัจจุบัน: <?php echo htmlspecialchars($currentLocationAddress); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                            </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($addr['zone_name']): ?>
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $addr['color_code']; ?>"></div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($addr['zone_name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($addr['zone_code']); ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">ไม่ระบุ</span>
                                        <?php endif; ?>
                            </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($addr['employee_name']): ?>
                                            <div class="text-sm">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($addr['employee_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($addr['employee_nickname']); ?></div>
                                            </div>
                                <?php else: ?>
                                            <span class="text-gray-400">ไม่มีพนักงาน</span>
                                <?php endif; ?>
                            </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-2">
                                            <?php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'picked_up' => 'bg-indigo-100 text-indigo-800',
                                                'in_transit' => 'bg-purple-100 text-purple-800',
                                                'out_for_delivery' => 'bg-blue-100 text-blue-800',
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'failed' => 'bg-red-100 text-red-800',
                                                'returned' => 'bg-orange-100 text-orange-800',
                                                'cancelled' => 'bg-gray-100 text-gray-800',
                                                'assigned' => 'bg-blue-100 text-blue-800'
                                            ];
                                            $statusTexts = [
                                                'pending' => 'รอจัดส่ง',
                                                'picked_up' => 'เก็บแล้ว',
                                                'in_transit' => 'กำลังขนส่ง',
                                                'out_for_delivery' => 'กำลังจัดส่ง',
                                                'delivered' => 'จัดส่งแล้ว',
                                                'failed' => 'ไม่สำเร็จ',
                                                'returned' => 'ส่งคืน',
                                                'cancelled' => 'ยกเลิก',
                                                'assigned' => 'มอบหมายแล้ว'
                                            ];
                                            $status = $addr['current_status'] ?? $addr['delivery_status'] ?? 'pending';
                                            $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                            $statusText = $statusTexts[$status] ?? $status;
                                            ?>
                                            
                                            <!-- Main Status -->
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                            
                                            <!-- Performance Status -->
                                            <?php if (isset($addr['performance_status']) && $addr['performance_status'] != 'ปกติ'): ?>
                                                <?php
                                                $perfColors = [
                                                    'ตรงเวลา' => 'bg-green-100 text-green-700',
                                                    'ล่าช้า' => 'bg-red-100 text-red-700',
                                                    'เกินกำหนด' => 'bg-orange-100 text-orange-700'
                                                ];
                                                $perfColor = $perfColors[$addr['performance_status']] ?? 'bg-gray-100 text-gray-700';
                                                ?>
                                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo $perfColor; ?>">
                                                    <i class="fas fa-clock mr-1"></i><?php echo $addr['performance_status']; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Priority & Service Type -->
                                            <?php if (isset($addr['priority_level']) && $addr['priority_level'] != 'standard'): ?>
                                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700">
                                                    <i class="fas fa-star mr-1"></i><?php echo ucfirst($addr['priority_level']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- รายละเอียด -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-1 text-xs">
                                            <?php if (isset($addr['package_weight']) && $addr['package_weight']): ?>
                                                <div class="flex items-center text-gray-600">
                                                    <i class="fas fa-weight mr-1"></i>
                                                    <?php echo number_format($addr['package_weight'], 2); ?> กก.
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($addr['cod_amount']) && $addr['cod_amount'] > 0): ?>
                                                <div class="flex items-center text-green-600">
                                                    <i class="fas fa-money-bill mr-1"></i>
                                                    COD: ฿<?php echo number_format($addr['cod_amount'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($addr['delivery_attempts']) && $addr['delivery_attempts'] > 0): ?>
                                                <div class="flex items-center text-orange-600">
                                                    <i class="fas fa-redo mr-1"></i>
                                                    ความพยายาม: <?php echo $addr['delivery_attempts']; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($addr['estimated_delivery_time']) && $addr['estimated_delivery_time']): ?>
                                                <div class="flex items-center text-blue-600">
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($addr['estimated_delivery_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($addr['actual_delivery_time']) && $addr['actual_delivery_time']): ?>
                                                <div class="flex items-center text-green-600">
                                                    <i class="fas fa-check mr-1"></i>
                                                    จัดส่ง: <?php echo date('d/m/Y H:i', strtotime($addr['actual_delivery_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($addr['failure_reason']) && $addr['failure_reason']): ?>
                                                <div class="flex items-center text-red-600">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <?php 
                                                    $failureReasons = [
                                                        'address_not_found' => 'หาที่อยู่ไม่เจอ',
                                                        'recipient_not_available' => 'ผู้รับไม่อยู่',
                                                        'refused_delivery' => 'ปฏิเสธรับ',
                                                        'damaged_package' => 'พัสดุเสียหาย',
                                                        'weather' => 'สภาพอากาศ',
                                                        'vehicle_breakdown' => 'รถเสีย',
                                                        'other' => 'อื่นๆ'
                                                    ];
                                                    echo $failureReasons[$addr['failure_reason']] ?? $addr['failure_reason'];
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- การดำเนินการ -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center space-x-2">
                                            <?php 
                                            $lat = $addr['latitude'] ?? $addr['da_latitude'] ?? null;
                                            $lng = $addr['longitude'] ?? $addr['da_longitude'] ?? null;
                                            $name = $addr['recipient_name'] ?? $addr['da_recipient_name'] ?? 'ไม่ระบุ';
                                            ?>
                                            <?php if ($lat && $lng): ?>
                                                <button onclick="showLocationOnMap(<?php echo $lat; ?>, <?php echo $lng; ?>, '<?php echo addslashes($name); ?>')" 
                                                        class="text-blue-600 hover:text-blue-900" title="ดูแผนที่">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="viewTrackingDetails('<?php echo $addr['tracking_id'] ?? $addr['address_id'] ?? $addr['id']; ?>')" 
                                                    class="text-green-600 hover:text-green-900" title="ดูรายละเอียด">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (isset($addr['tracking_number']) && $addr['tracking_number']): ?>
                                                <button onclick="trackPackage('<?php echo $addr['tracking_number']; ?>')" 
                                                        class="text-purple-600 hover:text-purple-900" title="ติดตามพัสดุ">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
            </tbody>
        </table>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            แสดง <?php echo ($currentPage - 1) * $itemsPerPage + 1; ?> ถึง 
                            <?php echo min($currentPage * $itemsPerPage, $totalAddresses); ?> 
                            จากทั้งหมด <?php echo number_format($totalAddresses); ?> รายการ
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <?php if ($currentPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    ← ก่อนหน้า
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-2 text-sm font-medium <?php echo ($i == $currentPage) ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-md">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    ถัดไป →
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Map Modal -->
<div id="map-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 id="map-title" class="text-lg font-semibold text-gray-900">ตำแหน่งที่อยู่</h3>
                <button onclick="closeMapModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="address-map" style="height: 500px;"></div>
        </div>
    </div>
</div>

<script>
let addressMap = null;

function showLocationOnMap(lat, lng, name) {
    document.getElementById('map-title').textContent = `ตำแหน่ง - ${name}`;
    document.getElementById('map-modal').classList.remove('hidden');
    
    // Initialize map if not exists
    if (!addressMap) {
        addressMap = L.map('address-map').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(addressMap);
    } else {
        // Clear existing markers
        addressMap.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                addressMap.removeLayer(layer);
            }
        });
        addressMap.setView([lat, lng], 15);
    }
    
    // Add marker
    L.marker([lat, lng]).addTo(addressMap)
        .bindPopup(`<strong>${name}</strong><br>Lat: ${lat}<br>Lng: ${lng}`)
        .openPopup();
    
    // Resize map after modal is shown
    setTimeout(() => addressMap.invalidateSize(), 300);
}

function closeMapModal() {
    document.getElementById('map-modal').classList.add('hidden');
}

function exportData() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create download link
    const link = document.createElement('a');
    link.href = '?' + params.toString();
    link.download = 'address_data.csv';
    link.click();
}

function viewTrackingDetails(trackingId) {
    // Show tracking details in a modal
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '🔍 รายละเอียดการติดตาม',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-sm"><strong class="text-blue-700">Tracking ID:</strong> <span class="font-mono">${trackingId}</span></p>
                    </div>
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="text-sm text-gray-600 mt-2">กำลังโหลดข้อมูลเพิ่มเติม...</p>
                    </div>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: false,
            width: 700,
            didOpen: () => {
                // Load tracking details via AJAX
                fetch(`../api/tracking_details.php?id=${trackingId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const details = data.data;
                            Swal.update({
                                html: `
                                    <div class="text-left space-y-4">
                                        <div class="bg-blue-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-blue-800 mb-2">ข้อมูลพัสดุ</h3>
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><strong>Tracking:</strong> ${details.tracking_id || 'N/A'}</div>
                                                <div><strong>AWB:</strong> ${details.awb_number || 'N/A'}</div>
                                                <div><strong>สถานะ:</strong> <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">${details.current_status || 'N/A'}</span></div>
                                                <div><strong>ความสำคัญ:</strong> ${details.priority_level || 'N/A'}</div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-green-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-green-800 mb-2">ข้อมูลผู้รับ</h3>
                                            <div class="text-sm space-y-1">
                                                <div><strong>ชื่อ:</strong> ${details.recipient_name || 'N/A'}</div>
                                                <div><strong>เบอร์:</strong> ${details.recipient_phone || 'N/A'}</div>
                                                <div><strong>ที่อยู่:</strong> ${details.recipient_address || 'N/A'}</div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-yellow-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-yellow-800 mb-2">รายละเอียดการจัดส่ง</h3>
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div><strong>น้ำหนัก:</strong> ${details.package_weight ? details.package_weight + ' กก.' : 'N/A'}</div>
                                                <div><strong>COD:</strong> ${details.cod_amount ? '฿' + parseFloat(details.cod_amount).toLocaleString() : 'ไม่มี'}</div>
                                                <div><strong>ความพยายาม:</strong> ${details.delivery_attempts || 0} ครั้ง</div>
                                                <div><strong>ประเภทบริการ:</strong> ${details.service_type || 'N/A'}</div>
                                            </div>
                                        </div>
                                        
                                        ${details.estimated_delivery_time ? `
                                        <div class="bg-purple-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-purple-800 mb-2">เวลาจัดส่ง</h3>
                                            <div class="text-sm space-y-1">
                                                <div><strong>กำหนดส่ง:</strong> ${new Date(details.estimated_delivery_time).toLocaleString('th-TH')}</div>
                                                ${details.actual_delivery_time ? `<div><strong>ส่งจริง:</strong> ${new Date(details.actual_delivery_time).toLocaleString('th-TH')}</div>` : ''}
                                            </div>
                                        </div>
                                        ` : ''}
                                        
                                        ${details.failure_reason ? `
                                        <div class="bg-red-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-red-800 mb-2">สาเหตุที่ไม่สำเร็จ</h3>
                                            <div class="text-sm">${details.failure_reason}</div>
                                        </div>
                                        ` : ''}
                                        
                                        ${details.delivery_notes ? `
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h3 class="font-semibold text-gray-800 mb-2">หมายเหตุ</h3>
                                            <div class="text-sm">${details.delivery_notes}</div>
                                        </div>
                                        ` : ''}
                                    </div>
                                `
                            });
                        } else {
                            Swal.update({
                                html: `
                                    <div class="text-center py-8">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                                        <p class="text-red-600">ไม่พบข้อมูลการติดตาม</p>
                                        <p class="text-sm text-gray-500 mt-2">${data.message || 'กรุณาลองใหม่อีกครั้ง'}</p>
                                    </div>
                                `
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching tracking details:', error);
                        Swal.update({
                            html: `
                                <div class="text-center py-8">
                                    <i class="fas fa-wifi text-red-500 text-3xl mb-3"></i>
                                    <p class="text-red-600">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>
                                    <p class="text-sm text-gray-500 mt-2">กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p>
                                </div>
                            `
                        });
                    });
            }
        });
    } else {
        alert('ดูรายละเอียดการติดตาม ID: ' + trackingId);
    }
}

function trackPackage(trackingNumber) {
    // Open tracking page or show tracking info
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'ติดตามพัสดุ',
            html: `
                <div class="text-center">
                    <div class="text-lg font-mono bg-gray-100 p-3 rounded mb-4">
                        ${trackingNumber}
                    </div>
                    <p class="text-sm text-gray-600">หมายเลขติดตามพัสดุ</p>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: true,
            confirmButtonText: 'คัดลอกหมายเลข',
            confirmButtonColor: '#3B82F6'
        }).then((result) => {
            if (result.isConfirmed) {
                navigator.clipboard.writeText(trackingNumber).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'คัดลอกแล้ว!',
                        text: 'หมายเลขติดตามถูกคัดลอกไปยังคลิปบอร์ดแล้ว',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            }
        });
    } else {
        prompt('หมายเลขติดตามพัสดุ:', trackingNumber);
    }
}

// Auto-submit form on filter change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('select[name="zone_filter"], select[name="status_filter"], select[name="employee_filter"], select[name="per_page"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
