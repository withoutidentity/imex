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
    $whereConditions[] = "za.id = ?";
    $params[] = $selectedZone;
}

if ($selectedStatus) {
    $whereConditions[] = "da.delivery_status = ?";
    $params[] = $selectedStatus;
}

if ($selectedEmployee) {
    $whereConditions[] = "zea.employee_id = ? AND zea.is_active = TRUE";
    $params[] = $selectedEmployee;
}

if ($searchQuery) {
    $whereConditions[] = "(da.recipient_name LIKE ? OR da.address LIKE ? OR da.awb_number LIKE ? OR da.recipient_phone LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count for pagination
$totalAddresses = 0;
try {
    $countStmt = $conn->prepare("
        SELECT COUNT(DISTINCT da.id) as total
        FROM delivery_address da
        LEFT JOIN zone_area za ON da.zone_id = za.id
        LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalAddresses = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    error_log("Error counting addresses: " . $e->getMessage());
}

// Calculate pagination
$totalPages = ceil($totalAddresses / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// Get addresses with pagination
$addresses = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            da.*,
            za.zone_name,
            za.zone_code,
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
                    <div class="mt-3 flex items-center space-x-4 text-sm">
                        <span class="bg-white/20 px-3 py-1 rounded-full">
                            <i class="fas fa-database mr-1"></i>
                            <?php echo number_format($totalAddresses); ?> รายการทั้งหมด
                        </span>
                        <span class="bg-white/20 px-3 py-1 rounded-full">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <?php echo count($zones); ?> โซน
                        </span>
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
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AWB</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้รับ</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ที่อยู่</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โซน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">พนักงาน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
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
                                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                            <?php echo htmlspecialchars($addr['awb_number']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($addr['recipient_name']); ?></div>
                                        <?php if ($addr['recipient_phone']): ?>
                                            <div class="text-sm text-gray-500">
                                                <a href="tel:<?php echo $addr['recipient_phone']; ?>" class="text-blue-600 hover:underline">
                                                    <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($addr['recipient_phone']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($addr['address']); ?>">
                                            <?php echo htmlspecialchars($addr['address']); ?>
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
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center space-x-2">
                                            <?php if ($addr['latitude'] && $addr['longitude']): ?>
                                                <button onclick="showLocationOnMap(<?php echo $addr['latitude']; ?>, <?php echo $addr['longitude']; ?>, '<?php echo addslashes($addr['recipient_name']); ?>')" 
                                                        class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewDetails('<?php echo $addr['id']; ?>')" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
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
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
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

function viewDetails(addressId) {
    // Implementation for viewing address details
    alert('ดูรายละเอียดที่อยู่ ID: ' + addressId);
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
