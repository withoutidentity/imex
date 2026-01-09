<?php
$page_title = 'ข้อมูลที่อยู่และโซน';
require_once '../config/config.php';

// Get filter parameters
$selectedZone = $_GET['zone_filter'] ?? '';
$selectedStatus = $_GET['status_filter'] ?? '';
$selectedEmployee = $_GET['employee_filter'] ?? '';

// Get zones with full information like zones.php
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

// Get delivery addresses with zone information
$addresses = [];
$totalAddresses = 0;

try {
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
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get addresses with zone and employee information
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
        ORDER BY za.zone_code, distance_from_center, da.created_at
        LIMIT 1000
    ");
    
    $stmt->execute($params);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
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

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">ข้อมูลที่อยู่และโซนการจัดส่ง</h1>
                <p class="text-gray-600">ข้อมูลที่อยู่พร้อมโซนและพนักงานรับผิดชอบ เชื่อมกับข้อมูลจากระบบจัดการโซน</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fas fa-map-marked-alt text-blue-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></div>
            <div class="text-sm text-gray-600">ทั้งหมด</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></div>
            <div class="text-sm text-gray-600">รอจัดส่ง</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-blue-600"><?php echo $stats['assigned']; ?></div>
            <div class="text-sm text-gray-600">มอบหมายแล้ว</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-purple-600"><?php echo $stats['in_transit']; ?></div>
            <div class="text-sm text-gray-600">กำลังจัดส่ง</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-green-600"><?php echo $stats['delivered']; ?></div>
            <div class="text-sm text-gray-600">จัดส่งแล้ว</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-red-600"><?php echo $stats['failed']; ?></div>
            <div class="text-sm text-gray-600">ไม่สำเร็จ</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-indigo-600"><?php echo $stats['zones_count']; ?></div>
            <div class="text-sm text-gray-600">โซน</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">🔍 กรองข้อมูล</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Zone Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกโซน</label>
                <select name="zone_filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกโซน</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo $zone['id']; ?>" <?php echo ($selectedZone == $zone['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['zone_code'] . ')'); ?>
                            - <?php echo $zone['delivery_count']; ?> รายการ
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">สถานะการจัดส่ง</label>
                <select name="status_filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?php echo ($selectedStatus == 'pending') ? 'selected' : ''; ?>>รอจัดส่ง</option>
                    <option value="assigned" <?php echo ($selectedStatus == 'assigned') ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                    <option value="in_transit" <?php echo ($selectedStatus == 'in_transit') ? 'selected' : ''; ?>>กำลังจัดส่ง</option>
                    <option value="delivered" <?php echo ($selectedStatus == 'delivered') ? 'selected' : ''; ?>>จัดส่งแล้ว</option>
                    <option value="failed" <?php echo ($selectedStatus == 'failed') ? 'selected' : ''; ?>>ไม่สำเร็จ</option>
                </select>
            </div>

            <!-- Employee Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">พนักงานจัดส่ง</label>
                <select name="employee_filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกคน</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo ($selectedEmployee == $emp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['employee_name'] . ' (' . $emp['nickname'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>กรอง
                </button>
            </div>
        </form>

        <!-- Clear Filters -->
        <?php if ($selectedZone || $selectedStatus || $selectedEmployee): ?>
            <div class="mt-4">
                <a href="?" class="text-sm text-blue-600 hover:text-blue-800">
                    <i class="fas fa-times mr-1"></i>ล้างตัวกรอง
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Zone Summary Table -->
    <?php if (!$selectedZone): ?>
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">📊 สรุปข้อมูลตามโซน</h3>
                <p class="text-sm text-gray-600">ข้อมูลสรุปจากระบบจัดการโซน (เชื่อมกับ zones.php)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">โซน</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">คำอธิบาย</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">จำนวนที่อยู่</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">สถิติการจัดส่ง</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">พนักงาน</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($zones as $zone): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-4 h-4 rounded-full" style="background-color: <?php echo $zone['color_code']; ?>"></div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($zone['zone_name']); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                    <?php echo htmlspecialchars($zone['zone_code']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($zone['description'] ?? 'ไม่ระบุ'); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-lg font-bold text-gray-900"><?php echo $zone['delivery_count']; ?></div>
                                    <div class="text-xs text-gray-500">รายการ</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                                            รอส่ง: <?php echo $zone['pending_count']; ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-800">
                                            ส่งแล้ว: <?php echo $zone['delivered_count']; ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-red-100 text-red-800">
                                            ไม่สำเร็จ: <?php echo $zone['failed_count']; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($zone['assigned_employees']): ?>
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-users text-blue-600 mr-1"></i>
                                            <?php echo $zone['employee_count']; ?> คน
                                        </div>
                                        <div class="text-xs text-gray-500 truncate max-w-xs" title="<?php echo htmlspecialchars($zone['assigned_employees']); ?>">
                                            <?php echo htmlspecialchars($zone['assigned_employees']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">ไม่มีพนักงาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <a href="?zone_filter=<?php echo $zone['id']; ?>" 
                                           class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-blue-600 bg-blue-100 hover:bg-blue-200">
                                            <i class="fas fa-eye mr-1"></i>ดูที่อยู่
                                        </a>
                                        <a href="zones.php?edit=<?php echo $zone['id']; ?>" 
                                           class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-green-600 bg-green-100 hover:bg-green-200">
                                            <i class="fas fa-edit mr-1"></i>จัดการ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Address Details Table -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">📋 รายละเอียดที่อยู่</h3>
                    <p class="text-sm text-gray-600">
                        แสดง <?php echo count($addresses); ?> รายการจากทั้งหมด <?php echo $totalAddresses; ?> รายการ
                        <?php if ($selectedZone): ?>
                            <?php 
                            $selectedZoneData = array_filter($zones, function($z) use ($selectedZone) { 
                                return $z['id'] == $selectedZone; 
                            });
                            if (!empty($selectedZoneData)) {
                                $selectedZoneData = array_values($selectedZoneData)[0];
                                echo "- โซน: " . htmlspecialchars($selectedZoneData['zone_name']);
                            }
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($selectedZone): ?>
                    <a href="zones.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-map mr-2"></i>ไปยังระบบจัดการโซน
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($addresses)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">ไม่มีข้อมูลที่อยู่</h3>
                <p class="text-gray-500">ไม่พบข้อมูลที่อยู่ตามเงื่อนไขที่เลือก</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">#</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">AWB</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">ผู้รับ</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">เบอร์โทร</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">ที่อยู่</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">โซน</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">พนักงาน</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">พิกัด</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-900">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($addresses as $index => $addr): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900"><?php echo $index + 1; ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($addr['awb_number']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($addr['recipient_name']); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($addr['recipient_phone']): ?>
                                        <a href="tel:<?php echo $addr['recipient_phone']; ?>" class="text-blue-600 hover:underline">
                                            <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($addr['recipient_phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <div class="truncate" title="<?php echo htmlspecialchars($addr['address']); ?>">
                                        <?php echo htmlspecialchars($addr['address']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($addr['zone_name']): ?>
                                        <div class="flex items-center space-x-2">
                                            <div class="w-3 h-3 rounded-full" style="background-color: <?php echo $addr['color_code']; ?>"></div>
                                            <div>
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($addr['zone_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($addr['zone_code']); ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">ไม่ระบุ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($addr['employee_name']): ?>
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($addr['employee_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($addr['employee_nickname']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">ไม่มีพนักงาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($addr['latitude'] && $addr['longitude']): ?>
                                        <button onclick="showLocationOnMap(<?php echo $addr['latitude']; ?>, <?php echo $addr['longitude']; ?>, '<?php echo addslashes($addr['recipient_name']); ?>')" 
                                                class="text-blue-600 hover:text-blue-800 text-xs">
                                            <i class="fas fa-map-marker-alt mr-1"></i>แผนที่
                                        </button>
                                        <?php if ($addr['distance_from_center']): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php echo number_format($addr['distance_from_center'], 2); ?> กม.
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">ไม่มีพิกัด</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
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
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $colorClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Map Modal -->
<div id="map-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 id="map-title" class="text-lg font-semibold text-gray-800">ตำแหน่งที่อยู่</h3>
                <button onclick="closeMapModal()" class="text-gray-400 hover:text-gray-600">
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
</script>

<?php include '../includes/footer.php'; ?>
