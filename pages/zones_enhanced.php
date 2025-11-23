<?php
$page_title = 'จัดการโซนและพนักงาน';
require_once '../config/config.php';
include '../includes/header.php';

// Handle form submissions
$action_result = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_employee'])) {
        $result = createEmployee($_POST);
        if ($result['success']) {
            $action_result = "เพิ่มพนักงานใหม่สำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['assign_employee'])) {
        $result = assignEmployeeToZone($_POST);
        if ($result['success']) {
            $action_result = "มอบหมายพนักงานสำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['update_employee'])) {
        $result = updateEmployee($_POST);
        if ($result['success']) {
            $action_result = "อัปเดตข้อมูลพนักงานสำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    } elseif (isset($_POST['remove_assignment'])) {
        $result = removeAssignment($_POST['assignment_id']);
        if ($result['success']) {
            $action_result = "ยกเลิกการมอบหมายสำเร็จ";
        } else {
            $action_error = $result['error'];
        }
    }
}

// Helper functions
function createEmployee($data) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO delivery_zone_employees (employee_code, employee_name, position, zone_area, zone_code, nickname, phone, email, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['employee_code'],
            $data['employee_name'],
            $data['position'],
            $data['zone_area'],
            $data['zone_code'],
            $data['nickname'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['hire_date'] ?? date('Y-m-d'),
            $data['status'] ?? 'active'
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function assignEmployeeToZone($data) {
    global $conn;
    try {
        $conn->beginTransaction();
        
        // Check if zone exists
        $stmt = $conn->prepare("SELECT id FROM zone_area WHERE id = ?");
        $stmt->execute([$data['zone_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("ไม่พบโซนที่ระบุ");
        }
        
        // If primary assignment, deactivate existing primary
        if ($data['assignment_type'] === 'primary') {
            $stmt = $conn->prepare("UPDATE zone_employee_assignments SET is_active = FALSE, end_date = CURDATE() WHERE zone_id = ? AND assignment_type = 'primary' AND is_active = TRUE");
            $stmt->execute([$data['zone_id']]);
        }
        
        // Create new assignment
        $stmt = $conn->prepare("INSERT INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, workload_percentage, is_active) VALUES (?, ?, ?, ?, ?, TRUE)");
        $stmt->execute([
            $data['zone_id'],
            $data['employee_id'],
            $data['assignment_type'],
            $data['start_date'] ?? date('Y-m-d'),
            $data['workload_percentage'] ?? 100
        ]);
        
        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateEmployee($data) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE delivery_zone_employees SET employee_name = ?, position = ?, zone_area = ?, zone_code = ?, nickname = ?, phone = ?, email = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $data['employee_name'],
            $data['position'],
            $data['zone_area'],
            $data['zone_code'],
            $data['nickname'],
            $data['phone'],
            $data['email'],
            $data['status'],
            $data['employee_id']
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function removeAssignment($assignment_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE zone_employee_assignments SET is_active = FALSE, end_date = CURDATE() WHERE id = ?");
        $stmt->execute([$assignment_id]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get zones with employee information
function getZonesWithEmployees() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 
            za.*,
            COUNT(DISTINCT da.id) as total_deliveries,
            COUNT(CASE WHEN da.delivery_status = 'pending' THEN 1 END) as pending_deliveries,
            COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as completed_deliveries,
            GROUP_CONCAT(
                DISTINCT CONCAT(dze.employee_name, ' (', dze.nickname, ') - ', zea.assignment_type)
                ORDER BY zea.assignment_type, dze.employee_name
                SEPARATOR '; '
            ) as assigned_employees
        FROM zone_area za
        LEFT JOIN delivery_address da ON za.id = da.zone_id
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        GROUP BY za.id
        ORDER BY za.zone_code
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all employees
function getAllEmployees() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 
            dze.*,
            COUNT(zea.zone_id) as assigned_zones,
            COALESCE(NULLIF(GROUP_CONCAT(DISTINCT za.zone_code ORDER BY za.zone_code), ''), dze.zone_area) as zone_codes
        FROM delivery_zone_employees dze
        LEFT JOIN zone_employee_assignments zea ON dze.id = zea.employee_id AND zea.is_active = TRUE
        LEFT JOIN zone_area za ON zea.zone_id = za.id
        GROUP BY dze.id
        ORDER BY dze.employee_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get zone details with assignments
function getZoneDetails($zone_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 
            za.*,
            dze.id as employee_id,
            dze.employee_code,
            dze.employee_name,
            dze.position,
            dze.nickname,
            dze.phone,
            dze.status as employee_status,
            zea.id as assignment_id,
            zea.assignment_type,
            zea.start_date,
            zea.workload_percentage
        FROM zone_area za
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id
        WHERE za.id = ?
        ORDER BY zea.assignment_type, dze.employee_name
    ");
    $stmt->execute([$zone_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$zones = getZonesWithEmployees();
$employees = getAllEmployees();

// Get selected zone details if zone_id is provided
$selected_zone = null;
$zone_assignments = [];
if (isset($_GET['zone_id'])) {
    $zone_assignments = getZoneDetails($_GET['zone_id']);
    if (!empty($zone_assignments)) {
        $selected_zone = $zone_assignments[0];
    }
}
?>

<div class="fadeIn">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">จัดการโซนและพนักงาน</h1>
                <p class="text-indigo-100">บริหารโซนการจัดส่งและมอบหมายพนักงานรับผิดชอบ</p>
            </div>
            <div class="hidden lg:block">
                <i class="fas fa-users-cog text-6xl opacity-20"></i>
            </div>
        </div>
    </div>

    <!-- Action Messages -->
    <?php if ($action_result): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($action_result); ?>
        </div>
    <?php endif; ?>

    <?php if ($action_error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($action_error); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-map-marked-alt text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800"><?php echo count($zones); ?></h3>
                    <p class="text-gray-600">โซนทั้งหมด</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800"><?php echo count($employees); ?></h3>
                    <p class="text-gray-600">พนักงานทั้งหมด</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-tasks text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                        <?php echo array_sum(array_column($zones, 'pending_deliveries')); ?>
                    </h3>
                    <p class="text-gray-600">งานรอดำเนินการ</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="bg-white rounded-lg shadow-md">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 px-6">
                <button onclick="showTab('zones')" id="zones-tab" class="tab-button border-b-2 border-blue-500 text-blue-600 py-4 px-1 text-sm font-medium">
                    <i class="fas fa-map-marked-alt mr-2"></i>จัดการโซน
                </button>
                <button onclick="showTab('employees')" id="employees-tab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 text-sm font-medium">
                    <i class="fas fa-users mr-2"></i>จัดการพนักงาน
                </button>
                <button onclick="showTab('assignments')" id="assignments-tab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 text-sm font-medium">
                    <i class="fas fa-user-check mr-2"></i>มอบหมายงาน
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            
            <!-- Zones Management Tab -->
            <div id="zones-content" class="tab-content">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">รายการโซนการจัดส่ง</h2>
                    <a href="zones.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-cog mr-2"></i>จัดการโซนแบบละเอียด
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="min-w-full table-custom">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">โซน</th>
                                <th class="px-4 py-2 text-center">ทั้งหมด</th>
                                <th class="px-4 py-2 text-center">รอดำเนินการ</th>
                                <th class="px-4 py-2 text-center">เสร็จแล้ว</th>
                                <th class="px-4 py-2 text-center">พนักงาน</th>
                                <th class="px-4 py-2">พนักงานที่รับผิดชอบ</th>
                                <th class="px-4 py-2 text-right">การทำงาน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-block w-3 h-3 rounded-full" style="background-color: <?php echo $zone['color_code'] ?? '#6B7280'; ?>"></span>
                                        <div>
                                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($zone['zone_code']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($zone['zone_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center font-semibold text-blue-600"><?php echo (int)$zone['total_deliveries']; ?></td>
                                <td class="px-4 py-2 text-center font-semibold text-orange-600"><?php echo (int)$zone['pending_deliveries']; ?></td>
                                <td class="px-4 py-2 text-center font-semibold text-green-600"><?php echo (int)$zone['completed_deliveries']; ?></td>
                                <td class="px-4 py-2 text-center">
                                    <?php $empCount = !empty($zone['assigned_employees']) ? count(explode('; ', $zone['assigned_employees'])) : 0; ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-medium"><?php echo $empCount; ?> คน</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-700 max-w-xs truncate">
                                    <?php if (!empty($zone['assigned_employees'])): ?>
                                        <?php echo htmlspecialchars($zone['assigned_employees']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="?zone_id=<?php echo $zone['id']; ?>" class="px-2 py-1.5 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200">
                                            <i class="fas fa-eye mr-1"></i>ดู
                                        </a>
                                        <a href="route_planner.php?zone_id=<?php echo $zone['id']; ?>" class="px-2 py-1.5 text-xs rounded bg-green-100 text-green-700 hover:bg-green-200">
                                            <i class="fas fa-route mr-1"></i>วางแผน
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Employees Management Tab -->
            <div id="employees-content" class="tab-content hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">รายการพนักงาน</h2>
                    <button onclick="showAddEmployeeModal()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>เพิ่มพนักงาน
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัส/ชื่อ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ตำแหน่ง</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">พื้นที่รับผิดชอบ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โซนที่มอบหมาย</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $employee): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['employee_code']); ?></div>
                                        <?php if ($employee['nickname']): ?>
                                            <div class="text-xs text-blue-600">ชื่อเล่น: <?php echo htmlspecialchars($employee['nickname']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $employee['position'] === 'SPT+S' ? 'bg-purple-100 text-purple-800' : 
                                                  ($employee['position'] === 'SPT+C' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo htmlspecialchars($employee['position']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($employee['zone_area']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($employee['zone_codes']): ?>
                                        <span class="text-green-600"><?php echo htmlspecialchars($employee['zone_codes']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">ยังไม่มีการมอบหมาย</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $employee['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $employee['status'] === 'active' ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editEmployee(<?php echo $employee['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="assignEmployee(<?php echo $employee['id']; ?>)" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Zone Assignments Tab -->
            <div id="assignments-content" class="tab-content hidden">
                <?php if ($selected_zone): ?>
                <div class="mb-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h2 class="text-xl font-bold text-gray-800 mb-2">
                            <span class="w-4 h-4 inline-block rounded-full mr-2" style="background-color: <?php echo $selected_zone['color_code']; ?>"></span>
                            <?php echo htmlspecialchars($selected_zone['zone_code']) . ' - ' . htmlspecialchars($selected_zone['zone_name']); ?>
                        </h2>
                        <p class="text-gray-600"><?php echo htmlspecialchars($selected_zone['description']); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Current Assignments -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">พนักงานที่มอบหมายแล้ว</h3>
                            <button onclick="showAssignModal(<?php echo $selected_zone['id']; ?>)" class="bg-blue-600 text-white px-3 py-1 rounded-md text-sm hover:bg-blue-700">
                                <i class="fas fa-plus mr-1"></i>มอบหมาย
                            </button>
                        </div>

                        <div class="space-y-3">
                            <?php 
                            $has_assignments = false;
                            foreach ($zone_assignments as $assignment): 
                                if ($assignment['employee_id']):
                                    $has_assignments = true;
                            ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['employee_name']); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($assignment['employee_code']); ?>
                                        <?php if ($assignment['nickname']): ?>
                                            (<?php echo htmlspecialchars($assignment['nickname']); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full mr-2
                                            <?php echo $assignment['assignment_type'] === 'primary' ? 'bg-green-100 text-green-800' : 
                                                      ($assignment['assignment_type'] === 'backup' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo $assignment['assignment_type'] === 'primary' ? 'หลัก' : ($assignment['assignment_type'] === 'backup' ? 'สำรอง' : 'สนับสนุน'); ?>
                                        </span>
                                        <span class="text-xs text-gray-500"><?php echo $assignment['workload_percentage']; ?>%</span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($assignment['phone']): ?>
                                    <a href="tel:<?php echo $assignment['phone']; ?>" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('ยืนยันการยกเลิกมอบหมาย?')">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                        <button type="submit" name="remove_assignment" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$has_assignments):
                            ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-user-slash text-4xl mb-3 opacity-30"></i>
                                <p>ยังไม่มีการมอบหมายพนักงาน</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Zone Statistics -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">สถิติโซน</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-blue-700 font-medium">งานทั้งหมด</span>
                                <span class="text-2xl font-bold text-blue-600">
                                    <?php 
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM delivery_address WHERE zone_id = ?");
                                    $stmt->execute([$selected_zone['id']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                                <span class="text-orange-700 font-medium">รอดำเนินการ</span>
                                <span class="text-2xl font-bold text-orange-600">
                                    <?php 
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM delivery_address WHERE zone_id = ? AND delivery_status = 'pending'");
                                    $stmt->execute([$selected_zone['id']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                <span class="text-green-700 font-medium">เสร็จแล้ว</span>
                                <span class="text-2xl font-bold text-green-600">
                                    <?php 
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM delivery_address WHERE zone_id = ? AND delivery_status = 'delivered'");
                                    $stmt->execute([$selected_zone['id']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t">
                            <div class="flex space-x-3">
                                <a href="route_planner.php?zone_id=<?php echo $selected_zone['id']; ?>" 
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-route mr-2"></i>วางแผนเส้นทาง
                                </a>
                                <a href="leaflet_map.php?zone_id=<?php echo $selected_zone['id']; ?>" 
                                   class="flex-1 bg-green-600 text-white text-center py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                                    <i class="fas fa-map mr-2"></i>ดูแผนที่
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-hand-point-left text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-500 mb-2">เลือกโซนเพื่อจัดการมอบหมายงาน</h3>
                    <p class="text-gray-400">คลิกที่ไอคอนตาในรายการโซนเพื่อดูรายละเอียดและจัดการพนักงาน</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Employee -->
<div id="addEmployeeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">เพิ่มพนักงานใหม่</h3>
                <button onclick="hideAddEmployeeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รหัสพนักงาน</label>
                    <input type="text" name="employee_code" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                    <input type="text" name="employee_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อเล่น</label>
                    <input type="text" name="nickname" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง</label>
                    <select name="position" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="SPT">SPT</option>
                        <option value="SPT+C">SPT+C</option>
                        <option value="SPT+S">SPT+S</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Manager">Manager</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">พื้นที่รับผิดชอบ</label>
                    <input type="text" name="zone_area" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รหัสโซน</label>
                    <input type="text" name="zone_code" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทร</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex space-x-3 pt-4">
                    <button type="button" onclick="hideAddEmployeeModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition-colors">
                        ยกเลิก
                    </button>
                    <button type="submit" name="create_employee" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                        เพิ่มพนักงาน
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Assign Employee -->
<div id="assignModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">มอบหมายพนักงาน</h3>
                <button onclick="hideAssignModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="zone_id" id="assign_zone_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลือกพนักงาน</label>
                    <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">เลือกพนักงาน</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>">
                            <?php echo htmlspecialchars($employee['employee_name']) . ' (' . htmlspecialchars($employee['nickname']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทการมอบหมาย</label>
                    <select name="assignment_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="primary">พนักงานหลัก</option>
                        <option value="backup">พนักงานสำรอง</option>
                        <option value="support">พนักงานสนับสนุน</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เปอร์เซ็นต์ภาระงาน</label>
                    <input type="number" name="workload_percentage" value="100" min="1" max="100" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex space-x-3 pt-4">
                    <button type="button" onclick="hideAssignModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition-colors">
                        ยกเลิก
                    </button>
                    <button type="submit" name="assign_employee" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                        มอบหมาย
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab management
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active styles from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-content').classList.remove('hidden');
    
    // Add active styles to selected tab
    const activeTab = document.getElementById(tabName + '-tab');
    activeTab.classList.remove('border-transparent', 'text-gray-500');
    activeTab.classList.add('border-blue-500', 'text-blue-600');
}

// Modal functions
function showAddEmployeeModal() {
    document.getElementById('addEmployeeModal').classList.remove('hidden');
}

function hideAddEmployeeModal() {
    document.getElementById('addEmployeeModal').classList.add('hidden');
}

function showAssignModal(zoneId) {
    document.getElementById('assign_zone_id').value = zoneId;
    document.getElementById('assignModal').classList.remove('hidden');
}

function hideAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

// Employee management functions
function editEmployee(employeeId) {
    // You can implement edit functionality here
    alert('ฟีเจอร์แก้ไขพนักงานอยู่ในระหว่างการพัฒนา');
}

function assignEmployee(employeeId) {
    // You can implement quick assignment here
    alert('เลือกโซนที่ต้องการมอบหมายจากแท็บ "มอบหมายงาน"');
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['zone_id'])): ?>
    showTab('assignments');
    <?php else: ?>
    showTab('zones');
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?> 