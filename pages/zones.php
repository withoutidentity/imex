<?php
$page_title = 'จัดการโซนการจัดส่ง';
require_once '../config/config.php';
include '../includes/header.php';

// Handle form submissions
$action_result = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_zone'])) {
        $result = createZone($_POST);
        if ($result['success']) {
            $action_result = "สร้างโซนใหม่สำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['update_zone'])) {
        $result = updateZone($_POST);
        if ($result['success']) {
            $action_result = "อัปเดตโซนสำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['delete_zone'])) {
        $result = deleteZone($_POST['zone_id']);
        if ($result['success']) {
            $action_result = "ลบโซนสำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['auto_create_zones'])) {
        $result = autoCreateZones();
        if ($result['success']) {
            $action_result = "สร้างโซนอัตโนมัติสำเร็จ: " . $result['count'] . " โซน";
        } else {
            $action_error = $result['error'];
        }
    }
}

function createZone($data) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO zone_area (zone_code, zone_name, description, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['zone_code'],
            $data['zone_name'],
            $data['description'],
            $data['min_lat'],
            $data['max_lat'],
            $data['min_lng'],
            $data['max_lng'],
            $data['center_lat'],
            $data['center_lng'],
            $data['color_code']
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateZone($data) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE zone_area SET zone_name = ?, description = ?, min_lat = ?, max_lat = ?, min_lng = ?, max_lng = ?, center_lat = ?, center_lng = ?, color_code = ?, polygon_coordinates = ?, polygon_type = ? WHERE id = ?");
        $stmt->execute([
            $data['zone_name'],
            $data['description'],
            $data['min_lat'],
            $data['max_lat'],
            $data['min_lng'],
            $data['max_lng'],
            $data['center_lat'],
            $data['center_lng'],
            $data['color_code'],
            $data['polygon_coordinates'] ?? null,
            $data['polygon_type'] ?? 'rectangle',
            $data['zone_id']
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteZone($zone_id) {
    global $conn;
    
    try {
        // Check if zone has deliveries
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_address WHERE zone_id = ?");
        $stmt->execute([$zone_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return ['success' => false, 'error' => 'ไม่สามารถลบโซนได้ เนื่องจากมีพัสดุในโซนนี้'];
        }
        
        $stmt = $conn->prepare("DELETE FROM zone_area WHERE id = ?");
        $stmt->execute([$zone_id]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function autoCreateZones() {
    global $conn;
    
    try {
        // Get all geocoded addresses
        $stmt = $conn->prepare("SELECT latitude, longitude FROM delivery_address WHERE geocoding_status = 'success' AND latitude IS NOT NULL AND longitude IS NOT NULL");
        $stmt->execute();
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($addresses) < 3) {
            return ['success' => false, 'error' => 'ต้องมีที่อยู่ที่แปลงพิกัดแล้วอย่างน้อย 3 รายการ'];
        }
        
        // Simple clustering algorithm
        $zones = performSimpleClustering($addresses);
        
        // Clear existing zones
        $conn->exec("DELETE FROM zone_area");
        
        // Insert new zones
        $zone_count = 0;
        foreach ($zones as $index => $zone) {
            $zone_id = $index + 1;
            $stmt = $conn->prepare("INSERT INTO zone_area (zone_code, zone_name, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'AUTO' . sprintf('%02d', $zone_id),
                'โซนอัตโนมัติ ' . $zone_id,
                $zone['min_lat'],
                $zone['max_lat'],
                $zone['min_lng'],
                $zone['max_lng'],
                $zone['center_lat'],
                $zone['center_lng'],
                $zone['color']
            ]);
            $zone_count++;
        }
        
        return ['success' => true, 'count' => $zone_count];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function performSimpleClustering($addresses) {
    // Simple grid-based clustering
    $min_lat = min(array_column($addresses, 'latitude'));
    $max_lat = max(array_column($addresses, 'latitude'));
    $min_lng = min(array_column($addresses, 'longitude'));
    $max_lng = max(array_column($addresses, 'longitude'));
    
    $lat_step = ($max_lat - $min_lat) / 3;
    $lng_step = ($max_lng - $min_lng) / 3;
    
    $zones = [];
    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#F97316', '#84CC16', '#EC4899'];
    
    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
            $zone_min_lat = $min_lat + ($i * $lat_step);
            $zone_max_lat = $min_lat + (($i + 1) * $lat_step);
            $zone_min_lng = $min_lng + ($j * $lng_step);
            $zone_max_lng = $min_lng + (($j + 1) * $lng_step);
            
            // Check if this zone has any addresses
            $has_addresses = false;
            foreach ($addresses as $addr) {
                if ($addr['latitude'] >= $zone_min_lat && $addr['latitude'] <= $zone_max_lat &&
                    $addr['longitude'] >= $zone_min_lng && $addr['longitude'] <= $zone_max_lng) {
                    $has_addresses = true;
                    break;
                }
            }
            
            if ($has_addresses) {
                $zones[] = [
                    'min_lat' => $zone_min_lat,
                    'max_lat' => $zone_max_lat,
                    'min_lng' => $zone_min_lng,
                    'max_lng' => $zone_max_lng,
                    'center_lat' => ($zone_min_lat + $zone_max_lat) / 2,
                    'center_lng' => ($zone_min_lng + $zone_max_lng) / 2,
                    'color' => $colors[count($zones) % count($colors)]
                ];
            }
        }
    }
    
    return $zones;
}

// Get zones with delivery counts and employee information
$zones = [];
try {
    $stmt = $conn->prepare("
        SELECT za.*, 
               za.polygon_coordinates,
               za.polygon_type,
               COUNT(DISTINCT da.id) as delivery_count,
               COUNT(CASE WHEN da.delivery_status = 'pending' THEN 1 END) as pending_count,
               COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as delivered_count,
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
    // Error handled silently
}

// Get zone for editing
$edit_zone = null;
$zone_employees = [];
$all_employees = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM zone_area WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get employees assigned to this zone
        if ($edit_zone) {
            $stmt = $conn->prepare("
                SELECT dze.*, zea.assignment_type, zea.start_date, zea.workload_percentage
                FROM delivery_zone_employees dze
                JOIN zone_employee_assignments zea ON dze.id = zea.employee_id
                WHERE zea.zone_id = ? AND zea.is_active = TRUE AND dze.status = 'active'
                ORDER BY zea.assignment_type, dze.employee_name
            ");
            $stmt->execute([$_GET['edit']]);
            $zone_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all available employees for assignment
            $stmt = $conn->prepare("
                SELECT dze.* 
                FROM delivery_zone_employees dze
                WHERE dze.status = 'active'
                ORDER BY dze.employee_name
            ");
            $stmt->execute();
            $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Error handled silently
    }
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">จัดการโซนการจัดส่ง</h1>
                <p class="text-gray-600 mt-2">สร้าง แก้ไข และจัดการโซนการจัดส่งพัสดุ</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-layer-group text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if (!empty($action_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($action_result); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($action_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($action_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-4 mb-6">
        <button onclick="showCreateZoneModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>สร้างโซนใหม่
        </button>
        
        <form method="POST" class="inline">
            <button type="submit" name="auto_create_zones" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors" onclick="return confirm('สร้างโซนอัตโนมัติจะลบโซนเดิมทั้งหมด ต้องการดำเนินการต่อหรือไม่?')">
                <i class="fas fa-magic mr-2"></i>สร้างโซนอัตโนมัติ
            </button>
        </form>
        
        <button id="show-all-zones-btn" onclick="openAllZonesMapPage()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-map mr-2"></i>แสดงแผนที่ทั้งหมด
        </button>
        
        <button onclick="testMapFunction()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-bug mr-2"></i>ทดสอบระบบ
        </button>
        
        <button onclick="forceInitializeMap()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors">
            <i class="fas fa-redo mr-2"></i>รีเซ็ตแผนที่
        </button>
    </div>

    <!-- Zones Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-table text-blue-600"></i>
                ตารางข้อมูลโซนทั้งหมด
                <span class="text-sm font-normal text-gray-600">(<?php echo count($zones); ?> โซน)</span>
            </h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โซน</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">พื้นที่โซน</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถิติการจัดส่ง</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">พนักงาน</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">พิกัด</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($zones as $index => $zone): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-200" data-zone-id="<?php echo $zone['id']; ?>">
                            <!-- Zone Info -->
                            <td class="px-4 py-4">
                    <div class="flex items-center space-x-3">
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-300 flex-shrink-0" style="background-color: <?php echo $zone['color_code']; ?>"></div>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900 zone-name"><?php echo htmlspecialchars($zone['zone_name']); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                <?php echo htmlspecialchars($zone['zone_code']); ?>
                                            </span>
                    </div>
                </div>
                                </div>
                            </td>
                
                            <!-- Zone Area Description -->
                            <td class="px-4 py-4">
                                <div class="text-sm text-gray-900 max-w-xs">
                <?php if (!empty($zone['description'])): ?>
                                        <div class="flex items-start space-x-2">
                                            <i class="fas fa-map-marker-alt text-red-500 text-xs mt-0.5 flex-shrink-0"></i>
                                            <div>
                                                <div class="font-medium text-gray-800 mb-1">พื้นที่ความรับผิดชอบ</div>
                                                <div class="text-sm text-gray-600 leading-relaxed" title="<?php echo htmlspecialchars($zone['description']); ?>">
                                                    <?php echo htmlspecialchars($zone['description']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center space-x-2 text-gray-400">
                                            <i class="fas fa-map-marker-alt text-xs"></i>
                                            <span class="italic text-sm">ไม่ได้ระบุพื้นที่</span>
                                        </div>
                <?php endif; ?>
                                </div>
                            </td>
                
                <!-- Statistics -->
                            <td class="px-4 py-4 text-center">
                                <div class="grid grid-cols-3 gap-1 text-xs">
                                    <div class="bg-blue-50 p-2 rounded">
                                        <div class="font-bold text-blue-600"><?php echo $zone['delivery_count']; ?></div>
                                        <div class="text-blue-500">ทั้งหมด</div>
                    </div>
                                    <div class="bg-orange-50 p-2 rounded">
                                        <div class="font-bold text-orange-600"><?php echo $zone['pending_count']; ?></div>
                                        <div class="text-orange-500">รอส่ง</div>
                    </div>
                                    <div class="bg-green-50 p-2 rounded">
                                        <div class="font-bold text-green-600"><?php echo $zone['delivered_count']; ?></div>
                                        <div class="text-green-500">ส่งแล้ว</div>
                    </div>
                </div>
                            </td>
                            
                            <!-- Employees -->
                            <td class="px-4 py-4">
                                <div class="space-y-2">
                                    <!-- Employee Count Badge -->
                                    <div class="flex justify-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                            <i class="fas fa-users mr-1"></i>
                            <?php echo $zone['employee_count']; ?> คน
                                        </span>
                        </div>
                                    
                                    <!-- Employee Names -->
                        <?php if (!empty($zone['assigned_employees'])): ?>
                                        <div class="max-w-xs">
                                            <?php 
                                            $employees = explode(', ', $zone['assigned_employees']);
                                            foreach ($employees as $index => $employee): 
                                                if ($index >= 3) break; // แสดงเฉพาะ 3 คนแรก
                                            ?>
                                                <div class="flex items-center space-x-1 mb-1">
                                                    <i class="fas fa-user text-xs text-gray-400"></i>
                                                    <span class="text-xs text-gray-700 font-medium"><?php echo htmlspecialchars($employee); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (count($employees) > 3): ?>
                                                <div class="text-xs text-gray-500 italic">
                                                    และอีก <?php echo count($employees) - 3; ?> คน...
                                                    <button onclick="showEmployeeDetails(<?php echo $zone['id']; ?>, '<?php echo htmlspecialchars(addslashes($zone['assigned_employees'])); ?>')" 
                                                            class="text-blue-600 hover:text-blue-800 underline ml-1">
                                                        ดูทั้งหมด
                                                    </button>
                                                </div>
                        <?php endif; ?>
                    </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <div class="text-xs text-gray-400 italic mb-1">
                                                <i class="fas fa-user-slash mr-1"></i>ยังไม่มีพนักงาน
                </div>
                                            <button onclick="assignEmployeeToZone(<?php echo $zone['id']; ?>)" 
                                                    class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200 transition-colors">
                                                <i class="fas fa-plus mr-1"></i>เพิ่มพนักงาน
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                
                <!-- Coordinates -->
                            <td class="px-4 py-4 text-center">
                                <div class="text-xs text-gray-600 font-mono">
                                    <div class="mb-1">
                                        <span class="text-gray-500">Center:</span><br>
                                        <?php echo number_format($zone['center_lat'], 4); ?>,<br>
                                        <?php echo number_format($zone['center_lng'], 4); ?>
                </div>
                                    <div class="text-xs text-gray-400">
                                        <?php echo number_format($zone['min_lat'], 4); ?> - <?php echo number_format($zone['max_lat'], 4); ?>
                                    </div>
                                </div>
                            </td>
                
                <!-- Actions -->
                            <td class="px-4 py-4">
                                <div class="flex flex-col space-y-1">
                                    <button onclick="showZoneOnMap(<?php echo $zone['id']; ?>)" class="w-full bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600 transition-colors">
                        <i class="fas fa-map-marker-alt mr-1"></i>แผนที่
                    </button>
                                    <a href="zone_edit.php?id=<?php echo $zone['id']; ?>" class="w-full bg-green-500 text-white px-2 py-1 rounded text-xs text-center hover:bg-green-600 transition-colors">
                        <i class="fas fa-edit mr-1"></i>แก้ไข
                    </a>
                    <?php if ($zone['delivery_count'] == 0): ?>
                                        <form method="POST" class="w-full">
                            <input type="hidden" name="zone_id" value="<?php echo $zone['id']; ?>">
                                            <button type="submit" name="delete_zone" class="w-full bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition-colors" onclick="return confirm('ต้องการลบโซนนี้หรือไม่?')">
                                <i class="fas fa-trash mr-1"></i>ลบ
                            </button>
                        </form>
                    <?php endif; ?>
                <?php if ($zone['delivery_count'] > 0): ?>
                                        <a href="route_planner.php?zone_id=<?php echo $zone['id']; ?>" class="w-full bg-purple-500 text-white px-2 py-1 rounded text-xs text-center hover:bg-purple-600 transition-colors">
                                            <i class="fas fa-route mr-1"></i>เส้นทาง
                    </a>
                <?php endif; ?>
            </div>
                            </td>
                        </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($zones)): ?>
            <div class="text-center py-12">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-map-marked-alt text-4xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">ยังไม่มีโซนการจัดส่ง</h3>
                <p class="text-gray-500 mb-4">เริ่มต้นโดยการสร้างโซนแรกของคุณ</p>
                <button onclick="showZoneModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>สร้างโซนใหม่
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Map Container -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center space-x-4">
                <h2 class="text-xl font-bold text-gray-800">แผนที่โซน</h2>
                <div id="zone-counter" class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                    <i class="fas fa-map-marked-alt"></i> 
                    <span id="zone-count"><?php echo count($zones); ?></span> โซน
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="showAllZonesOnMap()" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-expand-arrows-alt mr-1"></i>แสดงทั้งหมด
                </button>
                <button onclick="refreshMap()" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-sync-alt mr-1"></i>รีเฟรช
                </button>
                <button onclick="toggleLegend()" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-palette mr-1"></i>สีโซน
                </button>
            </div>
        </div>
        <div id="map-status" class="mb-2 text-sm text-gray-600 hidden">
            <i class="fas fa-spinner fa-spin mr-1"></i>กำลังโหลดแผนที่...
        </div>
        
        <!-- Debug Info -->
        <div id="debug-info" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="text-sm font-medium text-yellow-800 mb-2">
                <i class="fas fa-bug mr-1"></i>Debug Information
            </div>
            <div class="text-xs text-yellow-700 space-y-1">
                <div>จำนวนโซนทั้งหมด: <span class="font-mono"><?php echo count($zones); ?></span> โซน</div>
                <div>Leaflet.js: <span id="leaflet-status" class="font-mono">กำลังตรวจสอบ...</span></div>
                <div>Map Instance: <span id="map-instance-status" class="font-mono">กำลังตรวจสอบ...</span></div>
                <div>Polygons Created: <span id="polygons-count" class="font-mono">0</span></div>
                <div>Markers Created: <span id="markers-count" class="font-mono">0</span></div>
            </div>
        </div>
        
        <!-- Zone Legend -->
        <div id="zone-legend" class="mb-4 p-4 bg-gray-50 rounded-lg hidden">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">
                <i class="fas fa-palette"></i> สีโซนการจัดส่ง
            </h4>
            <div id="legend-items" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                <?php foreach ($zones as $zone): ?>
                    <div class="zone-legend-item flex items-center space-x-2 p-2 bg-white rounded border cursor-pointer" onclick="showZoneOnMap(<?php echo $zone['id']; ?>)">
                        <div class="w-4 h-4 rounded-full border-2 border-gray-300 flex-shrink-0" style="background-color: <?php echo $zone['color_code']; ?>"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-medium text-gray-800 truncate"><?php echo htmlspecialchars($zone['zone_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo $zone['delivery_count']; ?> รายการ</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="zones-map" class="map-container"></div>
    </div>

    <!-- Zone Form Modal -->
    <div id="zone-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="modal-title" class="text-lg font-bold text-gray-800">สร้างโซนใหม่</h3>
                    <button onclick="hideZoneModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="zone-form">
                    <input type="hidden" name="zone_id" id="edit_zone_id" value="">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">รหัสโซน</label>
                            <input type="text" name="zone_code" id="zone_code" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อโซน</label>
                            <input type="text" name="zone_name" id="zone_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">คำอธิบาย</label>
                            <textarea name="description" id="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ละติจูดต่ำสุด</label>
                                <input type="number" name="min_lat" id="min_lat" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ละติจูดสูงสุด</label>
                                <input type="number" name="max_lat" id="max_lat" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ลองจิจูดต่ำสุด</label>
                                <input type="number" name="min_lng" id="min_lng" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ลองจิจูดสูงสุด</label>
                                <input type="number" name="max_lng" id="max_lng" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ละติจูดกลาง</label>
                                <input type="number" name="center_lat" id="center_lat" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ลองจิจูดกลาง</label>
                                <input type="number" name="center_lng" id="center_lng" step="0.000001" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">สีโซน</label>
                            <input type="color" name="color_code" id="color_code" class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="#3B82F6">
                        </div>

                        <!-- Map picker trigger -->
                        <div class="pt-2">
                            <button type="button" onclick="openLeafletPicker()" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors">
                                <i class="fas fa-map-marked-alt mr-2"></i>เปิดแผนที่เพื่อปักพิกัด
                            </button>
                            <p class="text-xs text-gray-500 mt-2">คลิกเพื่อเปิดแผนที่และลากหมุดเพื่อกำหนดพิกัดขอบเขต ระบบจะกรอกค่าให้โดยอัตโนมัติ</p>
                        </div>
                        
                        <!-- Employee Assignment Section (only for edit mode) -->
                        <div id="employee-section" style="display: none;">
                            <hr class="my-4">
                            <h4 class="text-md font-semibold text-gray-800 mb-3">
                                <i class="fas fa-users mr-2 text-blue-600"></i>พนักงานที่รับผิดชอบ
                            </h4>
                            
                            <!-- Current Employees -->
                            <div id="current-employees" class="mb-4">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-sm font-medium text-gray-700 mb-2">พนักงานปัจจุบัน:</div>
                                    <div id="employee-list" class="space-y-2">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add New Employee -->
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="text-sm font-medium text-gray-700 mb-2">เพิ่มพนักงานใหม่:</div>
                                <div class="flex space-x-2">
                                    <select id="new-employee-select" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">เลือกพนักงาน...</option>
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                    <select id="assignment-type" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="primary">หลัก</option>
                                        <option value="backup">สำรอง</option>
                                        <option value="support">สนับสนุน</option>
                                    </select>
                                    <button type="button" onclick="addEmployeeToZone()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 mt-6">
                        <button type="submit" name="create_zone" id="submit_btn" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>บันทึก
                        </button>
                        <button type="button" onclick="hideZoneModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition-colors">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let zonesMap;
let zoneMarkers = [];
let zonePolygons = [];
const zones = <?php echo json_encode($zones); ?>;

function initializeZonesMap() {
    const statusEl = document.getElementById('map-status');
    const mapEl = document.getElementById('zones-map');
    
    // Check if elements exist
    if (!statusEl || !mapEl) {
        console.warn('Map elements not found');
        return;
    }
    
    // Check if map is already initialized
    if (zonesMap) {
        console.log('Map already initialized');
        showAllZonesOnMap();
        return;
    }
    
    try {
        statusEl.classList.remove('hidden');
        
        // Clear any existing content
        mapEl.innerHTML = '';
        
        // Wait for element to be ready
        setTimeout(() => {
            try {
                // Use Leaflet directly for better reliability - Center on Mueang Nakhon Si Thammarat
                zonesMap = L.map('zones-map').setView([8.4304, 99.9631], 13);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(zonesMap);
                
                // Add boundary marker for Mueang Nakhon Si Thammarat
                const districtBounds = [
                    [8.3800, 99.9200], // Southwest
                    [8.3800, 100.0200], // Southeast  
                    [8.4800, 100.0200], // Northeast
                    [8.4800, 99.9200]   // Northwest
                ];
                
                // Add district boundary rectangle
                L.polygon(districtBounds, {
                    color: '#ff7800',
                    weight: 2,
                    opacity: 0.8,
                    fillColor: '#ff7800',
                    fillOpacity: 0.1,
                    dashArray: '5, 5'
                }).addTo(zonesMap).bindPopup(`
                    <div class="text-center">
                        <h6 class="font-bold text-orange-600">🏛️ อำเภอเมือง</h6>
                        <p class="text-sm text-gray-600">นครศรีธรรมราช</p>
                        <p class="text-xs text-gray-500 mt-1">ขอบเขตพื้นที่การจัดส่ง</p>
                    </div>
                `);
                
                // Add center marker for district
                L.marker([8.4304, 99.9631], {
                    icon: L.divIcon({
                        className: 'district-marker',
                        html: '<div style="background-color: #ff7800; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    })
                }).addTo(zonesMap).bindPopup(`
                    <div class="text-center">
                        <h6 class="font-bold text-orange-600">📍 ศูนย์กลางเมือง</h6>
                        <p class="text-sm text-gray-600">นครศรีธรรมราช</p>
                        <p class="text-xs text-gray-400 font-mono">8.4304, 99.9631</p>
                    </div>
                `);
                
                // Set global reference
                window.currentMapInstance = zonesMap;
                
                // Hide loading status and show map
                statusEl.classList.add('hidden');
                updateDebugInfo();
                showAllZonesOnMap();
                
            } catch (innerError) {
                console.error('Inner map initialization error:', innerError);
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle mr-1 text-red-500"></i>ไม่สามารถโหลดแผนที่ได้';
                statusEl.classList.remove('hidden');
            }
        }, 100);
        
    } catch (error) {
        console.error('Map initialization error:', error);
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle mr-1 text-red-500"></i>ไม่สามารถโหลดแผนที่ได้';
        statusEl.classList.remove('hidden');
        mapEl.innerHTML = '<div class="flex items-center justify-center h-96 bg-gray-100 rounded"><p class="text-gray-600">กรุณารีเฟรชหน้าเว็บ หรือตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p></div>';
    }
}

// Alternative initialization function name for header.php
function initZonesMap() {
    initializeZonesMap();
}

// Function to update debug info
function updateDebugInfo() {
    // Check Leaflet availability
    const leafletStatus = document.getElementById('leaflet-status');
    if (leafletStatus) {
        leafletStatus.textContent = typeof L !== 'undefined' ? '✅ โหลดแล้ว' : '❌ ไม่พบ';
    }
    
    // Check map instance
    const mapInstanceStatus = document.getElementById('map-instance-status');
    if (mapInstanceStatus) {
        mapInstanceStatus.textContent = zonesMap ? '✅ สร้างแล้ว' : '❌ ยังไม่ได้สร้าง';
    }
    
    // Update polygon and marker counts
    const polygonsCount = document.getElementById('polygons-count');
    const markersCount = document.getElementById('markers-count');
    if (polygonsCount) {
        polygonsCount.textContent = zonePolygons ? zonePolygons.length : 0;
    }
    if (markersCount) {
        markersCount.textContent = zoneMarkers ? zoneMarkers.length : 0;
    }
}

// Function to edit zone from map popup
function editZone(zoneId) {
    window.location.href = `zone_edit.php?id=${zoneId}`;
}

// Function to open all zones map in new page
function openAllZonesMapPage() {
    console.log('Opening all zones map in new page...');
    
    // Show loading message
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'กำลังเปิดแผนที่...',
            text: 'กำลังเตรียมข้อมูลแผนที่ทั้งหมด',
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
    
    // Create URL parameters for all zones
    const params = new URLSearchParams();
    
    // Add all zones data
    if (zones && zones.length > 0) {
        // Calculate bounds for all zones
        let minLat = Infinity, maxLat = -Infinity;
        let minLng = Infinity, maxLng = -Infinity;
        
        zones.forEach(zone => {
            minLat = Math.min(minLat, parseFloat(zone.min_lat));
            maxLat = Math.max(maxLat, parseFloat(zone.max_lat));
            minLng = Math.min(minLng, parseFloat(zone.min_lng));
            maxLng = Math.max(maxLng, parseFloat(zone.max_lng));
        });
        
        // Add some padding
        const latPadding = (maxLat - minLat) * 0.1;
        const lngPadding = (maxLng - minLng) * 0.1;
        
        params.set('min_lat', (minLat - latPadding).toFixed(6));
        params.set('max_lat', (maxLat + latPadding).toFixed(6));
        params.set('min_lng', (minLng - lngPadding).toFixed(6));
        params.set('max_lng', (maxLng + lngPadding).toFixed(6));
        params.set('center_lat', ((minLat + maxLat) / 2).toFixed(6));
        params.set('center_lng', ((minLng + maxLng) / 2).toFixed(6));
        params.set('show_all_zones', '1');
        params.set('zones_data', JSON.stringify(zones));
    }
    
    // Open in new window/tab
    const url = `../leaflet_map_picker.php?${params.toString()}`;
    const newWindow = window.open(url, 'allZonesMap', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no');
    
    if (newWindow) {
        newWindow.focus();
        console.log('All zones map opened in new window');
        
        // Close loading message
        setTimeout(() => {
            if (typeof Swal !== 'undefined') {
                Swal.close();
            }
        }, 1000);
    } else {
        console.warn('Failed to open new window - popup blocked?');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่สามารถเปิดหน้าใหม่ได้',
                text: 'กรุณาอนุญาต popup หรือเปิดหน้าใหม่ด้วยตนเอง',
                confirmButtonText: 'ตกลง',
                footer: '<a href="' + url + '" target="_blank" class="text-blue-600 underline">คลิกที่นี่เพื่อเปิดแผนที่</a>'
            });
        } else {
            alert('ไม่สามารถเปิดหน้าใหม่ได้ กรุณาอนุญาต popup');
        }
    }
}

// Test function to debug map issues
function testMapFunction() {
    console.log('=== MAP DEBUG TEST ===');
    console.log('1. Zones data:', zones);
    console.log('2. Zones length:', zones ? zones.length : 'undefined');
    console.log('3. Map object:', zonesMap);
    console.log('4. Map initialized:', !!zonesMap);
    console.log('5. Leaflet available:', typeof L !== 'undefined');
    console.log('6. SweetAlert available:', typeof Swal !== 'undefined');
    
    const mapContainer = document.getElementById('zones-map');
    console.log('7. Map container found:', !!mapContainer);
    console.log('8. Map container dimensions:', mapContainer ? `${mapContainer.offsetWidth}x${mapContainer.offsetHeight}` : 'N/A');
    
    if (typeof Swal !== 'undefined') {
        const zonesInfo = zones ? zones.map(z => `${z.zone_name} (${z.zone_code})`).join('\n') : 'ไม่มีข้อมูล';
        
        Swal.fire({
            title: 'ผลการทดสอบระบบ',
            html: `
                <div class="text-left text-sm space-y-2">
                    <div><strong>จำนวนโซน:</strong> ${zones ? zones.length : 0}</div>
                    <div><strong>แผนที่:</strong> ${zonesMap ? '✅ พร้อม' : '❌ ไม่พร้อม'}</div>
                    <div><strong>Leaflet:</strong> ${typeof L !== 'undefined' ? '✅ โหลดแล้ว' : '❌ ไม่โหลด'}</div>
                    <div><strong>SweetAlert:</strong> ${typeof Swal !== 'undefined' ? '✅ โหลดแล้ว' : '❌ ไม่โหลด'}</div>
                    <div><strong>Map Container:</strong> ${mapContainer ? '✅ พบ' : '❌ ไม่พบ'}</div>
                    ${mapContainer ? `<div><strong>ขนาด:</strong> ${mapContainer.offsetWidth}x${mapContainer.offsetHeight}</div>` : ''}
                </div>
                <hr class="my-3">
                <div class="text-left">
                    <strong>รายชื่อโซน:</strong><br>
                    <pre class="text-xs bg-gray-100 p-2 rounded mt-1">${zonesInfo}</pre>
                </div>
            `,
            width: '500px',
            confirmButtonText: 'ตกลง'
        });
    } else {
        alert(`ผลการทดสอบ:\nโซน: ${zones ? zones.length : 0}\nแผนที่: ${zonesMap ? 'พร้อม' : 'ไม่พร้อม'}`);
    }
    
    console.log('=== END DEBUG TEST ===');
}

// Force initialize map function
function forceInitializeMap() {
    console.log('Force initializing map...');
    
    // Clear existing map
    if (zonesMap) {
        zonesMap.remove();
        zonesMap = null;
    }
    
    // Clear arrays
    zoneMarkers = [];
    zonePolygons = [];
    
    // Clear map container
    const mapContainer = document.getElementById('zones-map');
    if (mapContainer) {
        mapContainer.innerHTML = '';
    }
    
    // Force re-initialize
    setTimeout(() => {
        initializeZonesMap();
        
        setTimeout(() => {
            if (zonesMap) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'รีเซ็ตแผนที่สำเร็จ',
                        text: 'แผนที่ถูกรีเซ็ตและเริ่มต้นใหม่แล้ว',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert('รีเซ็ตแผนที่สำเร็จ');
                }
                
                // Auto show all zones after reset
                setTimeout(() => {
    showAllZonesOnMap();
                }, 1000);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถรีเซ็ตแผนที่ได้',
                        text: 'กรุณาลองรีเฟรชหน้าเว็บ',
                        confirmButtonText: 'ตกลง'
                    });
                } else {
                    alert('ไม่สามารถรีเซ็ตแผนที่ได้');
                }
            }
        }, 1000);
    }, 500);
}

function showAllZonesOnMap() {
    console.log('showAllZonesOnMap called');
    console.log('zones data:', zones);
    console.log('zonesMap:', zonesMap);
    
    // Show loading message
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'กำลังโหลดแผนที่...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    } else {
        console.log('Loading map...');
    }
    
    // Initialize map if not already done
    if (!zonesMap) {
        console.log('Map not initialized, initializing now...');
        initializeZonesMap();
        // Wait a bit for map to initialize
        setTimeout(() => {
            showAllZonesOnMap();
        }, 1500);
        return;
    }
    
    // Clear existing markers and polygons
    clearMapElements();
    
    if (!zones || zones.length === 0) {
        console.warn('No zones data available, creating sample zones');
        
        // Create sample zones for demonstration
        const sampleZones = [
            {
                id: 'sample1',
                zone_name: 'โซนตัวอย่าง 1',
                zone_code: 'SAMPLE01',
                min_lat: 8.420,
                max_lat: 8.440,
                min_lng: 99.950,
                max_lng: 99.970,
                center_lat: 8.430,
                center_lng: 99.960,
                color_code: '#e74c3c',
                assigned_employees: 'พนักงานตัวอย่าง 1',
                employee_count: 1,
                delivery_count: 10,
                pending_count: 3,
                delivered_count: 7
            },
            {
                id: 'sample2',
                zone_name: 'โซนตัวอย่าง 2',
                zone_code: 'SAMPLE02',
                min_lat: 8.400,
                max_lat: 8.420,
                min_lng: 99.930,
                max_lng: 99.950,
                center_lat: 8.410,
                center_lng: 99.940,
                color_code: '#3498db',
                assigned_employees: 'พนักงานตัวอย่าง 2',
                employee_count: 1,
                delivery_count: 8,
                pending_count: 2,
                delivered_count: 6
            }
        ];
        
        // Process sample zones
        zones = sampleZones;
        console.log('Using sample zones:', zones);
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'แสดงโซนตัวอย่าง',
                text: 'ไม่พบข้อมูลโซนในระบบ กำลังแสดงโซนตัวอย่าง',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }
    
    const allMarkers = [];
    
    zones.forEach(function(zone) {
        // Debug: Log zone coordinates
        console.log(`Zone ${zone.zone_name} (${zone.zone_code}):`, {
            min_lat: zone.min_lat,
            max_lat: zone.max_lat,
            min_lng: zone.min_lng,
            max_lng: zone.max_lng,
            center_lat: zone.center_lat,
            center_lng: zone.center_lng,
            color_code: zone.color_code
        });
        
        // Validate coordinates
        const minLat = parseFloat(zone.min_lat);
        const maxLat = parseFloat(zone.max_lat);
        const minLng = parseFloat(zone.min_lng);
        const maxLng = parseFloat(zone.max_lng);
        const centerLat = parseFloat(zone.center_lat);
        const centerLng = parseFloat(zone.center_lng);
        
        // Enhanced validation
        if (isNaN(minLat) || isNaN(maxLat) || isNaN(minLng) || isNaN(maxLng)) {
            console.warn(`Invalid coordinates for zone ${zone.zone_name} - NaN values`);
            return;
        }
        
        // Check if coordinates are reasonable (Thailand area)
        if (minLat < 5 || maxLat > 21 || minLng < 97 || maxLng > 106) {
            console.warn(`Coordinates outside Thailand for zone ${zone.zone_name}:`, {minLat, maxLat, minLng, maxLng});
            // Don't return, just warn - might be test data
        }
        
        // Check if bounds make sense
        if (minLat >= maxLat || minLng >= maxLng) {
            console.warn(`Invalid bounds for zone ${zone.zone_name}: min >= max`);
            return;
        }
        
        // Calculate area to check if zone is too small
        const latDiff = maxLat - minLat;
        const lngDiff = maxLng - minLng;
        const area = latDiff * lngDiff;
        
        // Create zone polygon - check if complex polygon coordinates exist
        let polygon;
        
        if (zone.polygon_coordinates && zone.polygon_type === 'polygon') {
            try {
                // Parse complex polygon coordinates from JSON
                const polygonCoords = JSON.parse(zone.polygon_coordinates);
                console.log(`Using complex polygon for zone ${zone.zone_name}:`, polygonCoords);
                
                // Create polygon from complex coordinates
                polygon = L.polygon(polygonCoords, {
                    color: zone.color_code || '#e74c3c',
                    weight: 4,
                    opacity: 1.0,
                    fillColor: zone.color_code || '#e74c3c',
                    fillOpacity: 0.4,
                    dashArray: '8, 4'
                });
                
            } catch (e) {
                console.warn(`Invalid polygon coordinates for zone ${zone.zone_name}, falling back to rectangle`);
                // Fall back to rectangle
                polygon = createRectanglePolygon(minLat, maxLat, minLng, maxLng, zone, area);
            }
        } else {
            // Use simple rectangle bounds
            polygon = createRectanglePolygon(minLat, maxLat, minLng, maxLng, zone, area);
        }
        
        // Add to map and log success
        polygon.addTo(zonesMap);
        console.log(`Polygon added to map for zone ${zone.zone_name}`);
        
        // Helper function to create rectangle polygon
        function createRectanglePolygon(minLat, maxLat, minLng, maxLng, zone, area) {
            let zoneBounds;
            if (area < 0.000001) {
                console.warn(`Zone ${zone.zone_name} is very small (${area}), expanding bounds`);
                // Expand small zones to make them visible
                const padding = 0.001;
                const newMinLat = minLat - padding;
                const newMaxLat = maxLat + padding;
                const newMinLng = minLng - padding;
                const newMaxLng = maxLng + padding;
                
                zoneBounds = [
                    [newMinLat, newMinLng],
                    [newMinLat, newMaxLng],
                    [newMaxLat, newMaxLng],
                    [newMaxLat, newMinLng]
                ];
            } else {
                // Create zone polygon using Leaflet
                zoneBounds = [
                    [minLat, minLng],
                    [minLat, maxLng],
                    [maxLat, maxLng],
                    [maxLat, minLng]
                ];
            }
            
            console.log(`Creating rectangle polygon for zone ${zone.zone_name} with bounds:`, zoneBounds);
            
            return L.polygon(zoneBounds, {
                color: zone.color_code || '#e74c3c',
                weight: 4,
                opacity: 1.0,
                fillColor: zone.color_code || '#e74c3c',
                fillOpacity: 0.4,
                dashArray: '8, 4'
            });
        }
        
        // Add click event to polygon to show popup
        polygon.on('click', function(e) {
            marker.openPopup();
        });
        
        // Add hover effects
        polygon.on('mouseover', function(e) {
            this.setStyle({
                weight: 4,
                fillOpacity: 0.4
            });
        });
        
        polygon.on('mouseout', function(e) {
            this.setStyle({
                weight: 3,
                fillOpacity: 0.3
            });
        });
        
        // Create center marker with validation
        let markerLat = centerLat;
        let markerLng = centerLng;
        
        // If center coordinates are invalid, calculate from bounds
        if (isNaN(markerLat) || isNaN(markerLng)) {
            markerLat = (minLat + maxLat) / 2;
            markerLng = (minLng + maxLng) / 2;
            console.log(`Using calculated center for ${zone.zone_name}: [${markerLat}, ${markerLng}]`);
        }
        
        const marker = L.marker([markerLat, markerLng], {
            icon: L.divIcon({
                className: 'zone-marker',
                html: `<div style="background-color: ${zone.color_code || '#e74c3c'}; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.4);"></div>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            })
        }).addTo(zonesMap);
        
        // Popup content for Leaflet with employee information
        let employeeSection = '';
        if (zone.assigned_employees && zone.assigned_employees.trim()) {
            const employees = zone.assigned_employees.split(', ');
            employeeSection = `
                <div class="mb-3 p-2 bg-green-50 rounded">
                    <div class="text-xs font-medium text-green-700 mb-1">
                        <i class="fas fa-users mr-1"></i>พนักงานจัดส่ง (${zone.employee_count || 0} คน)
                    </div>
                    <div class="space-y-1">
                        ${employees.slice(0, 3).map(emp => `
                            <div class="text-xs text-green-600">
                                <i class="fas fa-user text-xs mr-1"></i>${emp.trim()}
                            </div>
                        `).join('')}
                        ${employees.length > 3 ? `
                            <div class="text-xs text-green-500 italic">
                                และอีก ${employees.length - 3} คน...
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        } else {
            employeeSection = `
                <div class="mb-3 p-2 bg-gray-50 rounded">
                    <div class="text-xs text-gray-500 text-center">
                        <i class="fas fa-user-slash mr-1"></i>ยังไม่มีพนักงานรับผิดชอบ
                    </div>
                </div>
            `;
        }
        
        const popupContent = `
            <div class="p-3 min-w-64">
                <div class="flex items-center space-x-2 mb-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: ${zone.color_code}"></div>
                    <h6 class="font-semibold text-gray-800 text-sm">${zone.zone_name}</h6>
                </div>
                <p class="text-xs text-gray-600 mb-2">${zone.zone_code}</p>
                
                ${zone.description ? `
                    <div class="mb-2 p-2 bg-blue-50 rounded">
                        <div class="text-xs text-blue-700">
                            <i class="fas fa-info-circle mr-1"></i>${zone.description}
                        </div>
                    </div>
                ` : ''}
                
                ${employeeSection}
                
                <div class="grid grid-cols-3 gap-1 mb-3 text-xs">
                    <div class="text-center p-2 bg-blue-50 rounded">
                        <div class="font-semibold text-blue-600">${zone.delivery_count || 0}</div>
                        <div class="text-gray-500">ทั้งหมด</div>
                    </div>
                    <div class="text-center p-2 bg-yellow-50 rounded">
                        <div class="font-semibold text-yellow-600">${zone.pending_count || 0}</div>
                        <div class="text-gray-500">รอส่ง</div>
                    </div>
                    <div class="text-center p-2 bg-green-50 rounded">
                        <div class="font-semibold text-green-600">${zone.delivered_count || 0}</div>
                        <div class="text-gray-500">ส่งแล้ว</div>
                    </div>
                </div>
                
                <div class="space-y-1">
                    <button onclick="editZone(${zone.id})" class="w-full bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600">
                        <i class="fas fa-edit mr-1"></i>แก้ไขโซน
                    </button>
                    <button onclick="showZoneAddresses(${zone.id})" class="w-full bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600">
                        <i class="fas fa-map-marker-alt mr-1"></i>ดูที่อยู่ (${zone.delivery_count || 0})
                    </button>
                    ${zone.assigned_employees ? `
                        <button onclick="showEmployeeDetails(${zone.id}, '${zone.assigned_employees.replace(/'/g, "\\'")}' )" class="w-full bg-purple-500 text-white px-2 py-1 rounded text-xs hover:bg-purple-600">
                            <i class="fas fa-users mr-1"></i>ดูพนักงานทั้งหมด
                        </button>
                    ` : `
                        <button onclick="assignEmployeeToZone(${zone.id})" class="w-full bg-orange-500 text-white px-2 py-1 rounded text-xs hover:bg-orange-600">
                            <i class="fas fa-user-plus mr-1"></i>เพิ่มพนักงาน
                        </button>
                    `}
                </div>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        zonePolygons.push(polygon);
        zoneMarkers.push(marker);
    });
    
    // Fit bounds to show all zones
    if (zoneMarkers.length > 0) {
        const group = new L.featureGroup(zoneMarkers);
        const bounds = group.getBounds();
        
        // Check if bounds are reasonable (within Thailand)
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();
        
        console.log('Calculated bounds:', {
            southwest: [sw.lat, sw.lng],
            northeast: [ne.lat, ne.lng]
        });
        
        // If bounds are outside Thailand or too large, use Thailand bounds
        if (sw.lat < 5 || ne.lat > 21 || sw.lng < 97 || ne.lng > 106 || 
            (ne.lat - sw.lat) > 20 || (ne.lng - sw.lng) > 15) {
            console.warn('Bounds outside Thailand or too large, using Thailand bounds');
            // Focus on Thailand/Nakhon Si Thammarat area
            zonesMap.setView([8.4304, 99.9631], 10);
        } else {
            zonesMap.fitBounds(bounds.pad(0.1));
        }
        
        // Close loading and show success message
        if (typeof Swal !== 'undefined') {
            Swal.close();
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'แสดงแผนที่ทั้งหมดสำเร็จ',
                    text: `แสดงโซนทั้งหมด ${zones.length} โซนบนแผนที่`,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }, 500);
        }
        
        console.log(`Successfully displayed ${zones.length} zones on map`);
        console.log(`Created ${zonePolygons.length} polygons and ${zoneMarkers.length} markers`);
        
        // Update debug info
        updateDebugInfo();
        
        // Scroll to map
        const mapContainer = document.getElementById('zones-map');
        if (mapContainer) {
            mapContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } else {
        console.warn('No markers created');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่สามารถแสดงแผนที่ได้',
                text: 'ไม่สามารถสร้าง markers บนแผนที่ได้',
                confirmButtonText: 'ตกลง'
            });
        } else {
            alert('ไม่สามารถสร้าง markers บนแผนที่ได้');
        }
    }
}

function showZoneOnMap(zoneId) {
    const zone = zones.find(z => z.id == zoneId);
    if (!zone || !zonesMap) return;
    
    clearMapElements();
    
    // Create polygon based on zone type
    let polygon;
    if (zone.polygon_coordinates && zone.polygon_type === 'polygon') {
        try {
            const polygonCoords = JSON.parse(zone.polygon_coordinates);
            polygon = L.polygon(polygonCoords, {
                color: zone.color_code,
                weight: 4,
                opacity: 1.0,
                fillColor: zone.color_code,
                fillOpacity: 0.4
            });
        } catch (e) {
            // Fall back to rectangle
    const zoneBounds = [
                [parseFloat(zone.min_lat), parseFloat(zone.min_lng)],
                [parseFloat(zone.min_lat), parseFloat(zone.max_lng)],
                [parseFloat(zone.max_lat), parseFloat(zone.max_lng)],
                [parseFloat(zone.max_lat), parseFloat(zone.min_lng)]
            ];
            polygon = L.polygon(zoneBounds, {
                color: zone.color_code,
                weight: 4,
                opacity: 1.0,
        fillColor: zone.color_code,
                fillOpacity: 0.4
            });
        }
    } else {
        // Use rectangle
        const zoneBounds = [
            [parseFloat(zone.min_lat), parseFloat(zone.min_lng)],
            [parseFloat(zone.min_lat), parseFloat(zone.max_lng)],
            [parseFloat(zone.max_lat), parseFloat(zone.max_lng)],
            [parseFloat(zone.max_lat), parseFloat(zone.min_lng)]
        ];
        polygon = L.polygon(zoneBounds, {
            color: zone.color_code,
            weight: 4,
            opacity: 1.0,
            fillColor: zone.color_code,
            fillOpacity: 0.4
        });
    }
    
    polygon.addTo(zonesMap);
    
    // Add center marker
    const marker = L.marker([parseFloat(zone.center_lat), parseFloat(zone.center_lng)])
        .addTo(zonesMap);
    
    const popupContent = `
        <div class="p-2">
            <h6 class="font-semibold text-gray-800 text-sm">${zone.zone_name}</h6>
            <p class="text-xs text-gray-600">${zone.zone_code}</p>
            <p class="text-xs text-gray-500">รายการจัดส่ง: ${zone.delivery_count || 0}</p>
            <div class="mt-2">
                <button onclick="editZone(${zone.id})" class="bg-blue-500 text-white px-2 py-1 rounded text-xs">
                    แก้ไข
                </button>
            </div>
        </div>
    `;
    
    marker.bindPopup(popupContent).openPopup();
    
    // Fit bounds to the zone
    zonesMap.fitBounds(polygon.getBounds().pad(0.1));
    
    zonePolygons.push(polygon);
    zoneMarkers.push(marker);
}

function clearMapElements() {
    if (zonesMap) {
        zoneMarkers.forEach(marker => zonesMap.removeLayer(marker));
        zonePolygons.forEach(polygon => zonesMap.removeLayer(polygon));
    }
    zoneMarkers = [];
    zonePolygons = [];
}

function editZone(zoneId) {
    window.location.href = `zone_edit.php?id=${zoneId}`;
}

// Toggle legend visibility
function toggleLegend() {
    const legend = document.getElementById('zone-legend');
    if (legend.classList.contains('hidden')) {
        legend.classList.remove('hidden');
    } else {
        legend.classList.add('hidden');
    }
}

// Show addresses for a specific zone
async function showZoneAddresses(zoneId) {
    try {
        const response = await fetch(`zone_addresses.php?zone_id=${zoneId}`);
        const data = await response.json();
        
        if (data.success) {
            const zone = zones.find(z => z.id == zoneId);
            const zoneName = zone ? zone.zone_name : 'โซน';
            
            let content = `
                <div class="max-h-96 overflow-y-auto">
                    <h3 class="text-lg font-bold mb-4 text-gray-800">
                        <i class="fas fa-map-marker-alt text-green-600"></i>
                        ที่อยู่ในโซน: ${zoneName}
                    </h3>
            `;
            
            if (data.addresses && data.addresses.length > 0) {
                content += '<div class="space-y-3">';
                data.addresses.forEach((addr, index) => {
                    const statusColor = {
                        'delivered': 'text-green-600 bg-green-100',
                        'pending': 'text-yellow-600 bg-yellow-100',
                        'in_transit': 'text-blue-600 bg-blue-100',
                        'failed': 'text-red-600 bg-red-100'
                    };
                    const statusText = {
                        'delivered': 'จัดส่งแล้ว',
                        'pending': 'รอจัดส่ง',
                        'in_transit': 'กำลังจัดส่ง',
                        'failed': 'จัดส่งไม่สำเร็จ'
                    };
                    
                    content += `
                        <div class="p-3 border border-gray-200 rounded-lg bg-white hover:bg-gray-50">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">${addr.recipient_name || 'ไม่ระบุชื่อ'}</div>
                                    <div class="text-sm text-gray-600">${addr.recipient_phone || 'ไม่ระบุเบอร์'}</div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full ${statusColor[addr.status] || 'text-gray-600 bg-gray-100'}">
                                    ${statusText[addr.status] || addr.status}
                                </span>
                            </div>
                            <div class="text-sm text-gray-700 mb-2">${addr.address || 'ไม่มีที่อยู่'}</div>
                            ${addr.lat && addr.lng ? `
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-map-pin mr-1"></i>
                                    ${parseFloat(addr.lat).toFixed(6)}, ${parseFloat(addr.lng).toFixed(6)}
                                    <button onclick="showAddressOnMap(${addr.lat}, ${addr.lng}, '${addr.recipient_name || 'ไม่ระบุชื่อ'}')" 
                                            class="ml-2 text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-external-link-alt"></i> ดูบนแผนที่
                                    </button>
                                </div>
                            ` : ''}
                            ${addr.awb_number ? `
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-barcode mr-1"></i>
                                    AWB: ${addr.awb_number}
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
                content += '</div>';
            } else {
                content += `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>ไม่มีที่อยู่ในโซนนี้</p>
                    </div>
                `;
            }
            
            content += '</div>';
            
            Swal.fire({
                title: '',
                html: content,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'zone-addresses-modal'
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: data.message || 'ไม่สามารถโหลดข้อมูลที่อยู่ได้'
            });
        }
    } catch (error) {
        console.error('Error fetching zone addresses:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
        });
    }
}

// Show specific address on map
function showAddressOnMap(lat, lng, name) {
    if (!zonesMap) return;
    
    // Close any open popups
    zonesMap.closePopup();
    
    // Create marker for the address
    const addressMarker = L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'address-marker',
            html: '<div style="background-color: #ef4444; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        })
    }).addTo(zonesMap);
    
    addressMarker.bindPopup(`
        <div class="p-2">
            <div class="font-semibold text-red-600 text-sm mb-1">
                <i class="fas fa-map-pin mr-1"></i>${name}
            </div>
            <div class="text-xs text-gray-600">
                ${lat.toFixed(6)}, ${lng.toFixed(6)}
            </div>
        </div>
    `).openPopup();
    
    // Center map on address
    zonesMap.setView([lat, lng], 16);
    
    // Remove marker after 10 seconds
    setTimeout(() => {
        zonesMap.removeLayer(addressMarker);
    }, 10000);
}

function refreshMap() {
    const statusEl = document.getElementById('map-status');
    const mapEl = document.getElementById('zones-map');
    
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>กำลังรีเฟรชแผนที่...';
    statusEl.classList.remove('hidden');
    
    // Clear current map completely
    if (zonesMap) {
        zonesMap.remove();
        zonesMap = null;
    }
    
    // Clear arrays
    zoneMarkers = [];
    zonePolygons = [];
    
    // Clear map element
    mapEl.innerHTML = '';
    
    // Reinitialize map
    setTimeout(() => {
        initializeZonesMap();
    }, 300);
}

function createZoneMarkerSVG(color) {
    return `<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="20" r="18" fill="${color}" stroke="white" stroke-width="2"/>
        <text x="20" y="26" font-family="Arial" font-size="12" font-weight="bold" text-anchor="middle" fill="white">Z</text>
    </svg>`;
}

function showCreateZoneModal() {
    document.getElementById('modal-title').textContent = 'สร้างโซนใหม่';
    document.getElementById('zone-form').reset();
    document.getElementById('edit_zone_id').value = '';
    document.getElementById('submit_btn').name = 'create_zone';
    document.getElementById('submit_btn').innerHTML = '<i class="fas fa-save mr-2"></i>บันทึก';
    
    // Hide employee section for create mode
    document.getElementById('employee-section').style.display = 'none';
    
    document.getElementById('zone-modal').classList.remove('hidden');
}

function hideZoneModal() {
    document.getElementById('zone-modal').classList.add('hidden');
}

// Auto-fill center coordinates when bounds change
document.addEventListener('DOMContentLoaded', function() {
    // Update debug info on page load
    updateDebugInfo();
    
    const minLat = document.getElementById('min_lat');
    const maxLat = document.getElementById('max_lat');
    const minLng = document.getElementById('min_lng');
    const maxLng = document.getElementById('max_lng');
    const centerLat = document.getElementById('center_lat');
    const centerLng = document.getElementById('center_lng');
    
    function updateCenter() {
        if (minLat.value && maxLat.value && minLng.value && maxLng.value) {
            centerLat.value = (parseFloat(minLat.value) + parseFloat(maxLat.value)) / 2;
            centerLng.value = (parseFloat(minLng.value) + parseFloat(maxLng.value)) / 2;
        }
    }
    
    [minLat, maxLat, minLng, maxLng].forEach(input => {
        input.addEventListener('input', updateCenter);
    });
    
    // Initialize map
    if (typeof google !== 'undefined') {
        initializeZonesMap();
    }
    
    // Debug zones data
    console.log('Zones data available:', zones);
    console.log('Number of zones:', zones ? zones.length : 0);
    
    // Add click handler for show all zones button
    const showAllBtn = document.getElementById('show-all-zones-btn');
    if (showAllBtn) {
        console.log('Show all zones button found');
        showAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Show all zones button clicked via event listener');
            showAllZonesOnMap();
        });
    } else {
        console.warn('Show all zones button not found');
    }
    
    // Pre-fill edit form if editing
    <?php if ($edit_zone): ?>
        document.getElementById('modal-title').textContent = 'แก้ไขโซน';
        document.getElementById('edit_zone_id').value = '<?php echo $edit_zone['id']; ?>';
        document.getElementById('zone_code').value = '<?php echo htmlspecialchars($edit_zone['zone_code']); ?>';
        document.getElementById('zone_name').value = '<?php echo htmlspecialchars($edit_zone['zone_name']); ?>';
        document.getElementById('description').value = '<?php echo htmlspecialchars($edit_zone['description']); ?>';
        document.getElementById('min_lat').value = '<?php echo $edit_zone['min_lat']; ?>';
        document.getElementById('max_lat').value = '<?php echo $edit_zone['max_lat']; ?>';
        document.getElementById('min_lng').value = '<?php echo $edit_zone['min_lng']; ?>';
        document.getElementById('max_lng').value = '<?php echo $edit_zone['max_lng']; ?>';
        document.getElementById('center_lat').value = '<?php echo $edit_zone['center_lat']; ?>';
        document.getElementById('center_lng').value = '<?php echo $edit_zone['center_lng']; ?>';
        document.getElementById('color_code').value = '<?php echo $edit_zone['color_code']; ?>';
        document.getElementById('submit_btn').name = 'update_zone';
        document.getElementById('submit_btn').innerHTML = '<i class="fas fa-save mr-2"></i>อัปเดต';
        
        // Show employee section for edit mode
        document.getElementById('employee-section').style.display = 'block';
        
        // Populate current employees
        const currentEmployees = <?php echo json_encode($zone_employees); ?>;
        populateCurrentEmployees(currentEmployees);
        
        // Populate all employees dropdown
        const allEmployees = <?php echo json_encode($all_employees); ?>;
        populateEmployeesDropdown(allEmployees, currentEmployees);
        
        document.getElementById('zone-modal').classList.remove('hidden');
        
        // Show this zone on map
        setTimeout(() => showZoneOnMap(<?php echo $edit_zone['id']; ?>), 100);
    <?php endif; ?>

    // Listen for coordinates coming back from the Leaflet picker
    window.addEventListener('message', function(event) {
        try {
            if (!event.data || event.data.type !== 'leaflet-coordinates') return;
            const d = event.data.data || {};
            if (d.min_lat && d.max_lat && d.min_lng && d.max_lng) {
                minLat.value = d.min_lat;
                maxLat.value = d.max_lat;
                minLng.value = d.min_lng;
                maxLng.value = d.max_lng;
                centerLat.value = d.center_lat || ((parseFloat(d.min_lat) + parseFloat(d.max_lat)) / 2).toFixed(6);
                centerLng.value = d.center_lng || ((parseFloat(d.min_lng) + parseFloat(d.max_lng)) / 2).toFixed(6);
                
                // Handle polygon data
                if (d.polygon_coordinates && d.polygon_type) {
                    // Create hidden inputs for polygon data if they don't exist
                    let polygonCoordsInput = document.getElementById('polygon_coordinates');
                    let polygonTypeInput = document.getElementById('polygon_type');
                    
                    if (!polygonCoordsInput) {
                        polygonCoordsInput = document.createElement('input');
                        polygonCoordsInput.type = 'hidden';
                        polygonCoordsInput.name = 'polygon_coordinates';
                        polygonCoordsInput.id = 'polygon_coordinates';
                        document.querySelector('form').appendChild(polygonCoordsInput);
                    }
                    
                    if (!polygonTypeInput) {
                        polygonTypeInput = document.createElement('input');
                        polygonTypeInput.type = 'hidden';
                        polygonTypeInput.name = 'polygon_type';
                        polygonTypeInput.id = 'polygon_type';
                        document.querySelector('form').appendChild(polygonTypeInput);
                    }
                    
                    polygonCoordsInput.value = d.polygon_coordinates;
                    polygonTypeInput.value = d.polygon_type;
                    
                    console.log('=== ZONES.PHP: Polygon Data Received ===');
                    console.log('Polygon data received:', {
                        coordinates: d.polygon_coordinates,
                        type: d.polygon_type
                    });
                    console.log('Hidden inputs created:', {
                        polygonCoordsInput: !!polygonCoordsInput,
                        polygonTypeInput: !!polygonTypeInput
                    });
                    console.log('Form elements:', {
                        form: document.querySelector('form'),
                        polygonCoordsValue: polygonCoordsInput.value,
                        polygonTypeValue: polygonTypeInput.value
                    });
                    console.log('=====================================');
                } else {
                    // Set as rectangle if no polygon data
                    let polygonTypeInput = document.getElementById('polygon_type');
                    if (!polygonTypeInput) {
                        polygonTypeInput = document.createElement('input');
                        polygonTypeInput.type = 'hidden';
                        polygonTypeInput.name = 'polygon_type';
                        polygonTypeInput.id = 'polygon_type';
                        document.querySelector('form').appendChild(polygonTypeInput);
                    }
                    polygonTypeInput.value = 'rectangle';
                }
            }
        } catch (e) {
            console.error('Failed to apply coordinates', e);
        }
    });
});

// Open Leaflet-based picker in a popup window, passing current values
function openLeafletPicker() {
    const params = new URLSearchParams();
    
    // Get zone info if editing existing zone
    const zoneIdEl = document.getElementById('zone_id');
    const zoneNameEl = document.getElementById('zone_name');
    const zoneCodeEl = document.getElementById('zone_code');
    const colorCodeEl = document.getElementById('color_code');
    const descriptionEl = document.getElementById('description');
    
    if (zoneIdEl && zoneIdEl.value) {
        params.set('zone_id', zoneIdEl.value);
    }
    if (zoneNameEl && zoneNameEl.value) {
        params.set('zone_name', zoneNameEl.value);
    }
    if (zoneCodeEl && zoneCodeEl.value) {
        params.set('zone_code', zoneCodeEl.value);
    }
    if (colorCodeEl && colorCodeEl.value) {
        params.set('zone_color', colorCodeEl.value);
    }
    if (descriptionEl && descriptionEl.value) {
        params.set('zone_description', descriptionEl.value);
    }
    
    // Get coordinates
    const minLat = document.getElementById('min_lat').value;
    const maxLat = document.getElementById('max_lat').value;
    const minLng = document.getElementById('min_lng').value;
    const maxLng = document.getElementById('max_lng').value;
    if (minLat) params.set('min_lat', minLat);
    if (maxLat) params.set('max_lat', maxLat);
    if (minLng) params.set('min_lng', minLng);
    if (maxLng) params.set('max_lng', maxLng);
    
    const w = 1100, h = 780;
    const y = window.top.outerHeight / 2 + window.top.screenY - (h / 2);
    const x = window.top.outerWidth / 2 + window.top.screenX - (w / 2);
    window.open(`../leaflet_map_picker.php?${params.toString()}`, 'leafletPicker', `toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=${w},height=${h},top=${y},left=${x}`);
}

// Employee details modal functions
function showEmployeeDetails(zoneId, employeesList) {
    Swal.fire({
        title: 'พนักงานรับผิดชอบโซน',
        html: `
            <div class="text-left space-y-2">
                ${employeesList.split(', ').map(employee => `
                    <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded">
                        <i class="fas fa-user text-blue-600"></i>
                        <span class="text-sm font-medium">${employee}</span>
                    </div>
                `).join('')}
            </div>
            <div class="mt-4 pt-3 border-t border-gray-200">
                <button onclick="manageZoneEmployees(${zoneId})" 
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                    <i class="fas fa-cog mr-2"></i>จัดการพนักงาน
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        width: '400px'
    });
}

function assignEmployeeToZone(zoneId) {
    // Show the employee management modal
    manageZoneEmployees(zoneId);
}

function manageZoneEmployees(zoneId) {
    // Close any existing modal first
    Swal.close();
    
    // Find the zone name
    const zoneRow = document.querySelector(`tr[data-zone-id="${zoneId}"]`);
    const zoneName = zoneRow ? zoneRow.querySelector('.zone-name').textContent : `โซน ${zoneId}`;
    
    // Show the existing employee management modal
    document.getElementById('current-zone-id').value = zoneId;
    document.getElementById('modal-zone-name').textContent = zoneName;
    
    // Load current employees
    loadZoneEmployees(zoneId);
    
    // Show modal
    document.getElementById('employee-modal').classList.remove('hidden');
}

// Employee management functions
function populateCurrentEmployees(employees) {
    const employeeList = document.getElementById('employee-list');
    employeeList.innerHTML = '';
    
    if (employees.length === 0) {
        employeeList.innerHTML = '<div class="text-gray-500 text-sm">ยังไม่มีพนักงานที่รับผิดชอบ</div>';
        return;
    }
    
    employees.forEach(employee => {
        const assignmentTypes = {
            'primary': { text: 'หลัก', color: 'bg-blue-100 text-blue-800' },
            'backup': { text: 'สำรอง', color: 'bg-green-100 text-green-800' },
            'support': { text: 'สนับสนุน', color: 'bg-yellow-100 text-yellow-800' }
        };
        
        const assignmentType = assignmentTypes[employee.assignment_type] || { text: 'ไม่ระบุ', color: 'bg-gray-100 text-gray-800' };
        
        const employeeDiv = document.createElement('div');
        employeeDiv.className = 'flex items-center justify-between bg-white p-2 rounded border';
        employeeDiv.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-blue-600 text-sm"></i>
                    </div>
                </div>
                <div>
                    <div class="font-medium text-sm">${employee.employee_name}</div>
                    <div class="text-xs text-gray-500">${employee.nickname} (${employee.position})</div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-2 py-1 text-xs rounded-full ${assignmentType.color}">
                    ${assignmentType.text}
                </span>
                <button type="button" onclick="removeEmployeeFromZone(${employee.id})" 
                        class="text-red-600 hover:text-red-800 text-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        employeeList.appendChild(employeeDiv);
    });
}

function populateEmployeesDropdown(allEmployees, currentEmployees) {
    const select = document.getElementById('new-employee-select');
    select.innerHTML = '<option value="">เลือกพนักงาน...</option>';
    
    // Get IDs of current employees to exclude them
    const currentEmployeeIds = currentEmployees.map(emp => emp.id);
    
    allEmployees
        .filter(emp => !currentEmployeeIds.includes(emp.id))
        .forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.id;
            option.textContent = `${employee.employee_name} (${employee.nickname}) - ${employee.position}`;
            select.appendChild(option);
        });
}

function addEmployeeToZone() {
    const employeeSelect = document.getElementById('new-employee-select');
    const assignmentTypeSelect = document.getElementById('assignment-type');
    const zoneId = document.getElementById('edit_zone_id').value;
    
    if (!employeeSelect.value || !zoneId) {
        alert('กรุณาเลือกพนักงาน');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'assign_employee');
    formData.append('zone_id', zoneId);
    formData.append('employee_id', employeeSelect.value);
    formData.append('assignment_type', assignmentTypeSelect.value);
    
    // Send AJAX request
    fetch('zone_employee_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to refresh employee data
            window.location.href = `?edit=${zoneId}`;
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    });
}

function removeEmployeeFromZone(employeeId) {
    if (!confirm('ต้องการเอาพนักงานคนนี้ออกจากโซนหรือไม่?')) {
        return;
    }
    
    const zoneId = document.getElementById('edit_zone_id').value;
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'remove_employee');
    formData.append('zone_id', zoneId);
    formData.append('employee_id', employeeId);
    
    // Send AJAX request
    fetch('zone_employee_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to refresh employee data
            window.location.href = `?edit=${zoneId}`;
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    });
}
</script>

<?php include '../includes/footer.php'; ?> 