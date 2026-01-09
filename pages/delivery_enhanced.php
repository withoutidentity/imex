<?php
$page_title = 'จัดการการจัดส่ง - Enhanced';
require_once '../config/config.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'update_delivery_status':
                $trackingId = $_POST['tracking_id'];
                $newStatus = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                // Map delivery.php statuses to delivery_tracking statuses
                $statusMapping = [
                    'pending' => 'pending',
                    'assigned' => 'picked_up',
                    'in_transit' => 'out_for_delivery',
                    'delivered' => 'delivered',
                    'failed' => 'failed'
                ];
                
                $trackingStatus = $statusMapping[$newStatus] ?? $newStatus;
                
                $stmt = $conn->prepare("UPDATE delivery_tracking SET current_status = ?, delivery_notes = CONCAT(COALESCE(delivery_notes, ''), ?, '\n'), updated_at = NOW() WHERE tracking_id = ?");
                $result = $stmt->execute([$trackingStatus, date('Y-m-d H:i:s') . ": " . $notes, $trackingId]);
                
                // If delivered, set actual delivery time
                if ($newStatus === 'delivered') {
                    $stmt = $conn->prepare("UPDATE delivery_tracking SET actual_delivery_time = NOW() WHERE tracking_id = ?");
                    $stmt->execute([$trackingId]);
                }
                
                echo json_encode(['success' => $result]);
                exit;
                
            case 'get_delivery_stats':
                $date = $_POST['date'] ?? date('Y-m-d');
                $employeeId = $_POST['employee_id'] ?? null;
                $zoneId = $_POST['zone_id'] ?? null;
                
                $whereClause = "WHERE DATE(dt.created_at) = ?";
                $params = [$date];
                
                if ($employeeId) {
                    $whereClause .= " AND zea.employee_id = ? AND zea.is_active = TRUE";
                    $params[] = $employeeId;
                }
                
                if ($zoneId) {
                    $whereClause .= " AND da.zone_id = ?";
                    $params[] = $zoneId;
                }
                
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN dt.current_status IN ('pending', 'picked_up') THEN 1 END) as pending,
                        COUNT(CASE WHEN dt.current_status = 'in_transit' THEN 1 END) as assigned,
                        COUNT(CASE WHEN dt.current_status = 'out_for_delivery' THEN 1 END) as in_transit,
                        COUNT(CASE WHEN dt.current_status = 'delivered' THEN 1 END) as delivered,
                        COUNT(CASE WHEN dt.current_status = 'failed' THEN 1 END) as failed
                    FROM delivery_tracking dt
                    LEFT JOIN delivery_address da ON dt.awb_number = da.awb_number
                    LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id
                    $whereClause
                ");
                
                $stmt->execute($params);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode($stats);
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

// Get delivery data from delivery_tracking with enhanced information
$deliveries = [];
$deliveryStats = ['total' => 0, 'pending' => 0, 'assigned' => 0, 'in_transit' => 0, 'delivered' => 0, 'failed' => 0];

try {
    $whereClause = "WHERE DATE(dt.created_at) = ?";
    $params = [$selectedDate];
    
    if ($selectedEmployee) {
        $whereClause .= " AND zea.employee_id = ? AND zea.is_active = TRUE";
        $params[] = $selectedEmployee;
    }
    
    if ($selectedZone) {
        $whereClause .= " AND da.zone_id = ?";
        $params[] = $selectedZone;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            dt.*,
            da.recipient_name,
            da.recipient_phone,
            da.address,
            da.latitude,
            da.longitude,
            za.zone_name,
            za.zone_code,
            za.color_code,
            dze.employee_name,
            dze.nickname as employee_nickname,
            dze.phone as employee_phone,
            zea.assignment_type,
            CASE 
                WHEN dt.current_location_lat IS NOT NULL AND dt.current_location_lng IS NOT NULL 
                THEN SQRT(POW(69.1 * (dt.current_location_lat - 8.4304), 2) + POW(69.1 * (99.9631 - dt.current_location_lng) * COS(dt.current_location_lat / 57.3), 2))
                ELSE 999
            END as distance_from_center,
            CASE 
                WHEN dt.current_status IN ('pending', 'picked_up') THEN 'pending'
                WHEN dt.current_status = 'in_transit' THEN 'assigned'
                WHEN dt.current_status = 'out_for_delivery' THEN 'in_transit'
                WHEN dt.current_status = 'delivered' THEN 'delivered'
                WHEN dt.current_status = 'failed' THEN 'failed'
                ELSE 'pending'
            END as mapped_status
        FROM delivery_tracking dt
        LEFT JOIN delivery_address da ON dt.awb_number = da.awb_number
        LEFT JOIN zone_area za ON da.zone_id = za.id
        LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        $whereClause
        ORDER BY da.zone_id, distance_from_center, dt.created_at
    ");
    
    $stmt->execute($params);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics using mapped status
    foreach ($deliveries as $delivery) {
        $deliveryStats['total']++;
        $deliveryStats[$delivery['mapped_status']]++;
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
    $deliveriesByZone[$zoneId]['employees'][$employeeName]['stats'][$delivery['mapped_status']]++;
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

function getTrackingStatusText($status) {
    $statusMap = [
        'pending' => 'รอดำเนินการ',
        'picked_up' => 'รับพัสดุแล้ว',
        'in_transit' => 'ขนส่ง',
        'out_for_delivery' => 'กำลังจัดส่ง',
        'delivered' => 'จัดส่งแล้ว',
        'failed' => 'ไม่สำเร็จ',
        'returned' => 'ส่งคืน',
        'cancelled' => 'ยกเลิก'
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
                <h1 class="text-2xl font-bold text-gray-800">จัดการการจัดส่ง - Enhanced</h1>
                <p class="text-gray-600">ระบบจัดการรายการจัดส่งและติดตามสถานะรายวัน (ใช้ข้อมูลจาก delivery_tracking)</p>
            </div>
            <div class="bg-orange-100 p-3 rounded-lg">
                <i class="fas fa-truck-moving text-orange-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Debug Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="text-blue-800 font-semibold mb-2">🔍 Debug Information</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <strong>Data Source:</strong> delivery_tracking table
            </div>
            <div>
                <strong>Total Records:</strong> <?php echo count($deliveries); ?>
            </div>
            <div>
                <strong>Selected Date:</strong> <?php echo $selectedDate; ?>
            </div>
            <div>
                <strong>Zones Available:</strong> <?php echo count($zones); ?>
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
                <a href="../create_mockup_data.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>สร้างข้อมูลทดสอบ
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($deliveriesByZone as $zoneId => $zoneData): ?>
            <div class="bg-white rounded-lg shadow-md mb-6">
                <!-- Zone Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
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
                </div>

                <!-- Employees in Zone -->
                <?php foreach ($zoneData['employees'] as $employeeName => $employeeData): ?>
                    <div class="p-6 border-b border-gray-100 last:border-b-0">
                        <!-- Employee Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">
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
                            <div class="flex items-center space-x-4">
                                <!-- Employee Stats -->
                                <div class="text-right">
                                    <div class="text-sm text-gray-600">สถิติ:</div>
                                    <div class="flex space-x-2 text-xs">
                                        <span class="bg-gray-100 px-2 py-1 rounded">ทั้งหมด: <?php echo $employeeData['stats']['total']; ?></span>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded">ส่งแล้ว: <?php echo $employeeData['stats']['delivered']; ?></span>
                                        <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">คงเหลือ: <?php echo ($employeeData['stats']['total'] - $employeeData['stats']['delivered']); ?></span>
                                    </div>
                                </div>
                                <button onclick="showEmployeeMap('<?php echo addslashes($employeeName); ?>', <?php echo json_encode($employeeData['deliveries']); ?>)" 
                                        class="bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 transition-colors">
                                    <i class="fas fa-map-marked-alt mr-1"></i>แผนที่
                                </button>
                            </div>
                        </div>

                        <!-- Delivery List -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left">ลำดับ</th>
                                        <th class="px-3 py-2 text-left">Tracking ID</th>
                                        <th class="px-3 py-2 text-left">AWB</th>
                                        <th class="px-3 py-2 text-left">ผู้รับ</th>
                                        <th class="px-3 py-2 text-left">เบอร์โทร</th>
                                        <th class="px-3 py-2 text-left">ที่อยู่</th>
                                        <th class="px-3 py-2 text-left">พิกัดปัจจุบัน</th>
                                        <th class="px-3 py-2 text-left">สถานะ</th>
                                        <th class="px-3 py-2 text-left">ข้อมูลเพิ่มเติม</th>
                                        <th class="px-3 py-2 text-left">การดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeData['deliveries'] as $index => $delivery): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50" id="delivery-row-<?php echo $delivery['tracking_id']; ?>">
                                            <td class="px-3 py-2 font-medium"><?php echo $index + 1; ?></td>
                                            <td class="px-3 py-2">
                                                <span class="font-mono text-xs bg-blue-100 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($delivery['tracking_id']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($delivery['awb_number']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($delivery['recipient_name'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2">
                                                <?php if ($delivery['recipient_phone']): ?>
                                                    <a href="tel:<?php echo $delivery['recipient_phone']; ?>" class="text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($delivery['recipient_phone']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 max-w-xs truncate" title="<?php echo htmlspecialchars($delivery['address'] ?? $delivery['current_location_address']); ?>">
                                                <?php echo htmlspecialchars($delivery['address'] ?? $delivery['current_location_address'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <?php if ($delivery['current_location_lat'] && $delivery['current_location_lng']): ?>
                                                    <button onclick="showLocationOnMap(<?php echo $delivery['current_location_lat']; ?>, <?php echo $delivery['current_location_lng']; ?>, '<?php echo addslashes($delivery['recipient_name'] ?? 'Unknown'); ?>')" 
                                                            class="text-blue-600 hover:text-blue-800 text-xs">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>แผนที่
                                                    </button>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <?php echo number_format($delivery['distance_from_center'], 2); ?> กม.
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">ไม่มีพิกัด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="space-y-1">
                                                    <span class="status-badge status-<?php echo $delivery['mapped_status']; ?>">
                                                        <?php echo getStatusText($delivery['mapped_status']); ?>
                                                    </span>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo getTrackingStatusText($delivery['current_status']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="text-xs space-y-1">
                                                    <?php if ($delivery['priority_level']): ?>
                                                        <div class="<?php echo $delivery['priority_level'] == 'urgent' ? 'text-red-600' : 'text-gray-600'; ?>">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i><?php echo ucfirst($delivery['priority_level']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($delivery['package_weight']): ?>
                                                        <div class="text-gray-600">
                                                            <i class="fas fa-weight mr-1"></i><?php echo $delivery['package_weight']; ?> กก.
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($delivery['cod_amount'] > 0): ?>
                                                        <div class="text-green-600">
                                                            <i class="fas fa-money-bill mr-1"></i>COD: ฿<?php echo number_format($delivery['cod_amount'], 2); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex space-x-1">
                                                    <?php if ($delivery['mapped_status'] !== 'delivered'): ?>
                                                        <button onclick="updateDeliveryStatus('<?php echo $delivery['tracking_id']; ?>', 'delivered')" 
                                                                class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($delivery['mapped_status'] !== 'failed'): ?>
                                                        <button onclick="updateDeliveryStatus('<?php echo $delivery['tracking_id']; ?>', 'failed')" 
                                                                class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button onclick="showTrackingDetails('<?php echo $delivery['tracking_id']; ?>')" 
                                                            class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700">
                                                        <i class="fas fa-info"></i>
                                                    </button>
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

<style>
.status-badge {
    @apply px-2 py-1 rounded-full text-xs font-medium;
}
.status-pending { @apply bg-yellow-100 text-yellow-800; }
.status-assigned { @apply bg-blue-100 text-blue-800; }
.status-in_transit { @apply bg-purple-100 text-purple-800; }
.status-delivered { @apply bg-green-100 text-green-800; }
.status-failed { @apply bg-red-100 text-red-800; }
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

function updateDeliveryStatus(trackingId, status) {
    const notes = prompt(`กรุณาระบุหมายเหตุสำหรับการเปลี่ยนสถานะเป็น "${getStatusText(status)}":`);
    
    if (notes === null) return; // User cancelled
    
    const formData = new FormData();
    formData.append('action', 'update_delivery_status');
    formData.append('tracking_id', trackingId);
    formData.append('status', status);
    formData.append('notes', notes);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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
        const lat = delivery.current_location_lat || delivery.latitude;
        const lng = delivery.current_location_lng || delivery.longitude;
        
        if (lat && lng) {
            // Choose marker color based on status
            let markerColor = '#6b7280'; // gray
            if (delivery.mapped_status === 'delivered') markerColor = '#10b981'; // green
            else if (delivery.mapped_status === 'failed') markerColor = '#ef4444'; // red
            else if (delivery.mapped_status === 'in_transit') markerColor = '#8b5cf6'; // purple
            else if (delivery.mapped_status === 'assigned') markerColor = '#3b82f6'; // blue
            else if (delivery.mapped_status === 'pending') markerColor = '#f59e0b'; // yellow
            
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
                    <h6 class="font-semibold">${index + 1}. ${delivery.recipient_name || 'N/A'}</h6>
                    <p class="text-sm text-gray-600 mb-1">Tracking: ${delivery.tracking_id}</p>
                    <p class="text-sm text-gray-600 mb-1">AWB: ${delivery.awb_number}</p>
                    <p class="text-sm mb-2">${delivery.address || delivery.current_location_address || 'N/A'}</p>
                    <div class="flex items-center justify-between">
                        <span class="status-badge status-${delivery.mapped_status}">${getStatusText(delivery.mapped_status)}</span>
                        ${delivery.recipient_phone ? `<a href="tel:${delivery.recipient_phone}" class="text-blue-600 text-sm"><i class="fas fa-phone mr-1"></i>${delivery.recipient_phone}</a>` : ''}
                    </div>
                    ${delivery.priority_level === 'urgent' ? '<div class="text-xs text-red-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>ด่วน</div>' : ''}
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

function showLocationOnMap(lat, lng, name) {
    document.getElementById('map-title').textContent = `ตำแหน่ง - ${name}`;
    document.getElementById('map-modal').classList.remove('hidden');
    
    // Initialize map if not exists
    if (!deliveryMap) {
        deliveryMap = L.map('delivery-map').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(deliveryMap);
    } else {
        // Clear existing markers
        deliveryMap.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                deliveryMap.removeLayer(layer);
            }
        });
        deliveryMap.setView([lat, lng], 15);
    }
    
    // Add marker
    L.marker([lat, lng]).addTo(deliveryMap)
        .bindPopup(`<strong>${name}</strong><br>Lat: ${lat}<br>Lng: ${lng}`)
        .openPopup();
    
    // Resize map after modal is shown
    setTimeout(() => deliveryMap.invalidateSize(), 300);
}

function closeMapModal() {
    document.getElementById('map-modal').classList.add('hidden');
}

function showTrackingDetails(trackingId) {
    Swal.fire({
        title: 'รายละเอียดการติดตาม',
        text: `Tracking ID: ${trackingId}`,
        icon: 'info',
        html: `
            <div class="text-left">
                <p><strong>Tracking ID:</strong> ${trackingId}</p>
                <p class="mt-2">ฟีเจอร์รายละเอียดเพิ่มเติมกำลังพัฒนา...</p>
            </div>
        `
    });
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
</script>

<?php include '../includes/footer.php'; ?>
