<?php
$page_title = 'จับคู่โซนกับพนักงาน';
require_once '../config/config.php';

// Handle POST requests for zone-employee assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'assign_employee':
                $zone_id = intval($_POST['zone_id']);
                $employee_id = intval($_POST['employee_id']);
                $assignment_type = $_POST['assignment_type'] ?? 'primary';
                $workload_percentage = floatval($_POST['workload_percentage'] ?? 100);
                
                if (!$zone_id || !$employee_id) {
                    throw new Exception('กรุณาเลือกโซนและพนักงาน');
                }
                
                // Check if already assigned
                $checkStmt = $conn->prepare("
                    SELECT id FROM zone_employee_assignments 
                    WHERE zone_id = ? AND employee_id = ? AND is_active = TRUE
                ");
                $checkStmt->execute([$zone_id, $employee_id]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('พนักงานคนนี้ถูกมอบหมายให้โซนนี้แล้ว');
                }
                
                // If primary assignment, deactivate existing primary
                if ($assignment_type === 'primary') {
                    $deactivateStmt = $conn->prepare("
                        UPDATE zone_employee_assignments 
                        SET is_active = FALSE, end_date = CURDATE() 
                        WHERE zone_id = ? AND assignment_type = 'primary' AND is_active = TRUE
                    ");
                    $deactivateStmt->execute([$zone_id]);
                }
                
                // Create new assignment
                $assignStmt = $conn->prepare("
                    INSERT INTO zone_employee_assignments 
                    (zone_id, employee_id, assignment_type, start_date, workload_percentage, is_active) 
                    VALUES (?, ?, ?, CURDATE(), ?, TRUE)
                ");
                $assignStmt->execute([$zone_id, $employee_id, $assignment_type, $workload_percentage]);
                
                $message = "มอบหมายพนักงานให้โซนเรียบร้อยแล้ว";
                $messageType = "success";
                break;
                
            case 'remove_assignment':
                $assignment_id = intval($_POST['assignment_id']);
                
                if (!$assignment_id) {
                    throw new Exception('ไม่พบข้อมูลการมอบหมาย');
                }
                
                $removeStmt = $conn->prepare("
                    UPDATE zone_employee_assignments 
                    SET is_active = FALSE, end_date = CURDATE() 
                    WHERE id = ?
                ");
                $removeStmt->execute([$assignment_id]);
                
                $message = "ยกเลิกการมอบหมายเรียบร้อยแล้ว";
                $messageType = "success";
                break;
                
            case 'auto_assign':
                // Auto-assign employees to zones based on zone_code matching
                $autoAssignStmt = $conn->prepare("
                    INSERT INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active, workload_percentage)
                    SELECT za.id, dze.id, 'primary', CURDATE(), TRUE, 100.00
                    FROM delivery_zone_employees dze
                    JOIN zone_area za ON dze.zone_code = za.zone_code
                    WHERE dze.status = 'active'
                    AND NOT EXISTS (
                        SELECT 1 FROM zone_employee_assignments zea 
                        WHERE zea.zone_id = za.id AND zea.employee_id = dze.id AND zea.is_active = TRUE
                    )
                ");
                $autoAssignStmt->execute();
                $assigned = $autoAssignStmt->rowCount();
                
                $message = "จับคู่อัตโนมัติเรียบร้อย: {$assigned} การมอบหมาย";
                $messageType = "success";
                break;
                
            default:
                throw new Exception('การดำเนินการไม่ถูกต้อง');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Get zones with assignment statistics
$zones = [];
try {
    $zoneStmt = $conn->prepare("
        SELECT za.*, 
               COUNT(DISTINCT zea.id) as total_assignments,
               COUNT(DISTINCT CASE WHEN zea.assignment_type = 'primary' AND zea.is_active = TRUE THEN zea.id END) as primary_assignments,
               COUNT(DISTINCT CASE WHEN zea.assignment_type = 'backup' AND zea.is_active = TRUE THEN zea.id END) as backup_assignments,
               COUNT(DISTINCT CASE WHEN zea.assignment_type = 'support' AND zea.is_active = TRUE THEN zea.id END) as support_assignments,
               COUNT(DISTINCT da.id) as delivery_count,
               GROUP_CONCAT(
                   DISTINCT CASE WHEN zea.is_active = TRUE THEN 
                       CONCAT(dze.employee_name, ' (', zea.assignment_type, ')') 
                   END
                   ORDER BY zea.assignment_type, dze.employee_name
                   SEPARATOR ', '
               ) as assigned_employees
        FROM zone_area za 
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        LEFT JOIN delivery_address da ON za.id = da.zone_id
        WHERE za.is_active = 1
        GROUP BY za.id 
        ORDER BY za.zone_code
    ");
    $zoneStmt->execute();
    $zones = $zoneStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching zones: " . $e->getMessage());
}

// Get unassigned employees
$unassignedEmployees = [];
try {
    $unassignedStmt = $conn->prepare("
        SELECT dze.*, za.zone_name as matching_zone
        FROM delivery_zone_employees dze
        LEFT JOIN zone_area za ON dze.zone_code = za.zone_code
        WHERE dze.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM zone_employee_assignments zea 
            WHERE zea.employee_id = dze.id AND zea.is_active = TRUE
        )
        ORDER BY dze.employee_name
    ");
    $unassignedStmt->execute();
    $unassignedEmployees = $unassignedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching unassigned employees: " . $e->getMessage());
}

// Get all active employees for assignment form
$allEmployees = [];
try {
    $empStmt = $conn->prepare("
        SELECT id, employee_code, employee_name, nickname, position, zone_code, zone_area
        FROM delivery_zone_employees 
        WHERE status = 'active' 
        ORDER BY employee_name
    ");
    $empStmt->execute();
    $allEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching employees: " . $e->getMessage());
}

// Get current assignments for management
$currentAssignments = [];
try {
    $assignmentStmt = $conn->prepare("
        SELECT zea.*, za.zone_name, za.zone_code, dze.employee_name, dze.nickname, dze.position
        FROM zone_employee_assignments zea
        JOIN zone_area za ON zea.zone_id = za.id
        JOIN delivery_zone_employees dze ON zea.employee_id = dze.id
        WHERE zea.is_active = TRUE AND dze.status = 'active'
        ORDER BY za.zone_code, zea.assignment_type, dze.employee_name
    ");
    $assignmentStmt->execute();
    $currentAssignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching assignments: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">จับคู่โซนกับพนักงานจัดส่ง</h1>
                <p class="text-gray-600">จัดการการมอบหมายพนักงานให้โซนต่างๆ และติดตามสถิติการจัดส่ง</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fas fa-users-cog text-blue-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Message Display -->
    <?php if (isset($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-blue-600"><?php echo count($zones); ?></div>
            <div class="text-sm text-gray-600">โซนทั้งหมด</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-green-600"><?php echo count($allEmployees); ?></div>
            <div class="text-sm text-gray-600">พนักงานทั้งหมด</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-yellow-600"><?php echo count($unassignedEmployees); ?></div>
            <div class="text-sm text-gray-600">ยังไม่ได้มอบหมาย</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md text-center">
            <div class="text-2xl font-bold text-purple-600"><?php echo count($currentAssignments); ?></div>
            <div class="text-sm text-gray-600">การมอบหมายปัจจุบัน</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">🚀 การดำเนินการด่วน</h3>
        <div class="flex flex-wrap gap-4">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="auto_assign">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors" 
                        onclick="return confirm('คุณต้องการจับคู่อัตโนมัติตาม zone_code หรือไม่?')">
                    <i class="fas fa-magic mr-2"></i>จับคู่อัตโนมัติ
                </button>
            </form>
            <a href="zones.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                <i class="fas fa-map mr-2"></i>จัดการโซน
            </a>
            <a href="rider.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors">
                <i class="fas fa-users mr-2"></i>จัดการพนักงาน
            </a>
        </div>
    </div>

    <!-- Unassigned Employees Alert -->
    <?php if (!empty($unassignedEmployees)): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <h3 class="text-lg font-semibold text-yellow-800">พนักงานที่ยังไม่ได้มอบหมายโซน</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($unassignedEmployees as $emp): ?>
                    <div class="bg-white p-3 rounded border">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($emp['employee_name']); ?></div>
                        <div class="text-sm text-gray-600"><?php echo htmlspecialchars($emp['nickname'] . ' - ' . $emp['position']); ?></div>
                        <div class="text-xs text-gray-500">Zone Code: <?php echo htmlspecialchars($emp['zone_code']); ?></div>
                        <?php if ($emp['matching_zone']): ?>
                            <div class="text-xs text-blue-600">แนะนำ: <?php echo htmlspecialchars($emp['matching_zone']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Zone Assignment Form -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">➕ มอบหมายพนักงานให้โซน</h3>
        </div>
        <div class="p-6">
            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="action" value="assign_employee">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลือกโซน</label>
                    <select name="zone_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกโซน --</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?php echo $zone['id']; ?>">
                                <?php echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['zone_code'] . ')'); ?>
                                - <?php echo $zone['total_assignments']; ?> คน
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลือกพนักงาน</label>
                    <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกพนักงาน --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['employee_name'] . ' (' . $emp['nickname'] . ')'); ?>
                                - <?php echo htmlspecialchars($emp['zone_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทการมอบหมาย</label>
                    <select name="assignment_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="primary">หลัก (Primary)</option>
                        <option value="backup">สำรอง (Backup)</option>
                        <option value="support">สนับสนุน (Support)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">สัดส่วนงาน (%)</label>
                    <input type="number" name="workload_percentage" value="100" min="1" max="100" step="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>มอบหมาย
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Zone Overview Table -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">📊 ภาพรวมโซนและการมอบหมาย</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">โซน</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">จำนวนที่อยู่</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">การมอบหมาย</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">พนักงานที่ได้รับมอบหมาย</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">สถานะ</th>
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
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($zone['zone_code']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-lg font-bold text-gray-900"><?php echo $zone['delivery_count']; ?></div>
                                <div class="text-xs text-gray-500">รายการ</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex space-x-2 text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                        หลัก: <?php echo $zone['primary_assignments']; ?>
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                                        สำรอง: <?php echo $zone['backup_assignments']; ?>
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-800">
                                        สนับสนุน: <?php echo $zone['support_assignments']; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($zone['assigned_employees']): ?>
                                    <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($zone['assigned_employees']); ?>">
                                        <?php echo htmlspecialchars($zone['assigned_employees']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-red-500">ไม่มีการมอบหมาย</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($zone['primary_assignments'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>พร้อมใช้งาน
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>ต้องมอบหมาย
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Current Assignments Management -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">🔧 จัดการการมอบหมายปัจจุบัน</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">โซน</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">พนักงาน</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">ประเภท</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">สัดส่วนงาน</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">วันที่เริ่ม</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-900">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($currentAssignments as $assignment): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['zone_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($assignment['zone_code']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($assignment['employee_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($assignment['nickname'] . ' - ' . $assignment['position']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $typeColors = [
                                    'primary' => 'bg-blue-100 text-blue-800',
                                    'backup' => 'bg-yellow-100 text-yellow-800',
                                    'support' => 'bg-green-100 text-green-800'
                                ];
                                $typeTexts = [
                                    'primary' => 'หลัก',
                                    'backup' => 'สำรอง',
                                    'support' => 'สนับสนุน'
                                ];
                                $colorClass = $typeColors[$assignment['assignment_type']] ?? 'bg-gray-100 text-gray-800';
                                $typeText = $typeTexts[$assignment['assignment_type']] ?? $assignment['assignment_type'];
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $colorClass; ?>">
                                    <?php echo $typeText; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo number_format($assignment['workload_percentage'], 1); ?>%</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline" onsubmit="return confirm('คุณต้องการยกเลิกการมอบหมายนี้หรือไม่?')">
                                    <input type="hidden" name="action" value="remove_assignment">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs">
                                        <i class="fas fa-trash mr-1"></i>ยกเลิก
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
