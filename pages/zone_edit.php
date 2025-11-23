<?php
$page_title = 'แก้ไขโซนการจัดส่ง';
require_once '../config/config.php';

// Handle form submissions
$action_result = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log("POST data received: " . json_encode($_POST));
    
    if (isset($_POST['update_zone'])) {
        // Validate required fields
        $required_fields = ['zone_name', 'zone_id', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'center_lat', 'center_lng', 'color_code'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $action_error = "ข้อมูลไม่ครบถ้วน: " . implode(', ', $missing_fields);
        } else {
            $result = updateZone($_POST);
            if ($result['success']) {
                $action_result = "อัปเดตโซนสำเร็จ";
                // Don't redirect immediately, show success message first
                // header("Location: zones.php");
                // exit;
            } else {
                $action_error = $result['error'];
            }
        }
    } elseif (isset($_POST['assign_employee'])) {
        // Debug: Log employee assignment data
        error_log("Assign employee POST data: " . json_encode($_POST));
        
        // Validate required fields for employee assignment
        if (empty($_POST['employee_id']) || empty($_POST['zone_id'])) {
            $action_error = "กรุณาเลือกพนักงานและตรวจสอบข้อมูลโซน";
        } else {
            $result = assignEmployeeToZone($_POST);
            if ($result['success']) {
                $action_result = "มอบหมายพนักงานสำเร็จ";
                // Refresh the page to show updated data
                header("Location: zone_edit.php?id=" . $_POST['zone_id']);
                exit;
            } else {
                $action_error = $result['error'];
            }
        }
    }
}

function updateZone($data) {
    global $conn;
    
    try {
        // Debug: Log the data being received
        error_log("UpdateZone data: " . json_encode($data));
        
        $stmt = $conn->prepare("UPDATE zone_area SET zone_name = ?, description = ?, min_lat = ?, max_lat = ?, min_lng = ?, max_lng = ?, center_lat = ?, center_lng = ?, color_code = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['zone_name'],
            $data['description'] ?? '',
            floatval($data['min_lat']),
            floatval($data['max_lat']),
            floatval($data['min_lng']),
            floatval($data['max_lng']),
            floatval($data['center_lat']),
            floatval($data['center_lng']),
            $data['color_code'],
            intval($data['zone_id'])
        ]);
        
        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'ไม่สามารถอัปเดตข้อมูลได้'];
        }
    } catch (Exception $e) {
        error_log("UpdateZone error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function assignEmployeeToZone($data) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Check if already assigned
        $stmt = $conn->prepare("SELECT id FROM zone_employee_assignments WHERE zone_id = ? AND employee_id = ? AND is_active = TRUE");
        $stmt->execute([$data['zone_id'], $data['employee_id']]);
        if ($stmt->fetch()) {
            throw new Exception("พนักงานคนนี้ถูกมอบหมายให้โซนนี้แล้ว");
        }
        
        // Insert new assignment
        $stmt = $conn->prepare("INSERT INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active, workload_percentage) VALUES (?, ?, ?, CURDATE(), TRUE, 100.00)");
        $stmt->execute([$data['zone_id'], $data['employee_id'], $data['assignment_type']]);
        
        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get zone data
$zone = null;
$zone_employees = [];
$all_employees = [];
$zone_stats = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // Get zone data
        $stmt = $conn->prepare("SELECT * FROM zone_area WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($zone) {
            // Get zone statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_deliveries,
                    COUNT(CASE WHEN delivery_status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN delivery_status = 'delivered' THEN 1 END) as delivered_count,
                    COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as failed_count
                FROM delivery_address 
                WHERE zone_id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $zone_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get assigned employees
            $stmt = $conn->prepare("
                SELECT dze.*, zea.assignment_type, zea.start_date, zea.workload_percentage
                FROM delivery_zone_employees dze
                JOIN zone_employee_assignments zea ON dze.id = zea.employee_id
                WHERE zea.zone_id = ? AND zea.is_active = TRUE AND dze.status = 'active'
                ORDER BY zea.assignment_type, dze.employee_name
            ");
            $stmt->execute([$_GET['id']]);
            $zone_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all available employees
            $stmt = $conn->prepare("
                SELECT dze.* 
                FROM delivery_zone_employees dze
                WHERE dze.status = 'active'
                AND dze.id NOT IN (
                    SELECT employee_id FROM zone_employee_assignments 
                    WHERE zone_id = ? AND is_active = TRUE
                )
                ORDER BY dze.employee_name
            ");
            $stmt->execute([$_GET['id']]);
            $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $action_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

if (!$zone) {
    header("Location: zones.php");
    exit;
}

include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="zones.php" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">แก้ไขโซน</h1>
                        <p class="text-sm text-gray-500">จัดการข้อมูลโซนและพนักงาน</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="w-4 h-4 rounded-full" style="background-color: <?php echo htmlspecialchars($zone['color_code']); ?>"></div>
                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($zone['zone_code']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alert Messages -->
        <?php if ($action_result): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-800"><?php echo htmlspecialchars($action_result); ?></p>
                    </div>
                    <a href="zones.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm">
                        <i class="fas fa-arrow-left mr-2"></i>กลับไปยังรายการโซน
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($action_error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <p class="text-red-800"><?php echo htmlspecialchars($action_error); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Zone Information Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-map-marked-alt mr-3 text-blue-600"></i>
                            ข้อมูลโซน
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="zone_id" value="<?php echo $zone['id']; ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">รหัสโซน</label>
                                    <input type="text" name="zone_code" value="<?php echo htmlspecialchars($zone['zone_code']); ?>" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                                           readonly>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อโซน</label>
                                    <input type="text" name="zone_name" value="<?php echo htmlspecialchars($zone['zone_name']); ?>" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                                           required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">คำอธิบาย</label>
                                <textarea name="description" rows="3" 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"><?php echo htmlspecialchars($zone['description']); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">สีโซน</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="color_code" value="<?php echo htmlspecialchars($zone['color_code']); ?>" 
                                           class="w-16 h-12 border border-gray-300 rounded-lg cursor-pointer">
                                    <input type="text" value="<?php echo htmlspecialchars($zone['color_code']); ?>" 
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                                </div>
                            </div>
                            
                            <!-- Coordinates Section -->
                            <div class="border-t pt-6">
                                <h3 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-crosshairs mr-2 text-green-600"></i>
                                    พิกัดขอบเขต
                                </h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ละติจูดต่ำสุด</label>
                                        <input type="number" name="min_lat" value="<?php echo number_format($zone['min_lat'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ละติจูดสูงสุด</label>
                                        <input type="number" name="max_lat" value="<?php echo number_format($zone['max_lat'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ลองจิจูดต่ำสุด</label>
                                        <input type="number" name="min_lng" value="<?php echo number_format($zone['min_lng'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ลองจิจูดสูงสุด</label>
                                        <input type="number" name="max_lng" value="<?php echo number_format($zone['max_lng'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ละติจูดจุดกลาง</label>
                                        <input type="number" name="center_lat" value="<?php echo number_format($zone['center_lat'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">ลองจิจูดจุดกลาง</label>
                                        <input type="number" name="center_lng" value="<?php echo number_format($zone['center_lng'], 8, '.', ''); ?>" 
                                               step="any" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="button" onclick="openMapPicker()" 
                                            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                        <i class="fas fa-map-marked-alt mr-2"></i>
                                        เปิดแผนที่เพื่อปักพิกัด
                                    </button>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-4 pt-6 border-t">
                                <a href="zones.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                    ยกเลิก
                                </a>
                                <button type="submit" name="update_zone" 
                                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                                    <i class="fas fa-save mr-2"></i>
                                    บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Employee Management Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-users mr-3 text-green-600"></i>
                            พนักงานที่รับผิดชอบ
                            <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                <?php echo count($zone_employees); ?> คน
                            </span>
                        </h2>
                    </div>
                    <div class="p-6">
                        <!-- Current Employees -->
                        <?php if (!empty($zone_employees)): ?>
                            <div class="space-y-3 mb-6">
                                <?php foreach ($zone_employees as $employee): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['employee_name']); ?></h4>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($employee['employee_code']); ?> • 
                                                    <?php echo htmlspecialchars($employee['position']); ?>
                                                    <?php if ($employee['nickname']): ?>
                                                        • ชื่อเล่น: <?php echo htmlspecialchars($employee['nickname']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <span class="px-3 py-1 text-xs rounded-full
                                                <?php echo $employee['assignment_type'] === 'primary' ? 'bg-blue-100 text-blue-800' : 
                                                         ($employee['assignment_type'] === 'backup' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                <?php echo $employee['assignment_type'] === 'primary' ? 'หลัก' : 
                                                          ($employee['assignment_type'] === 'backup' ? 'สำรอง' : 'สนับสนุน'); ?>
                                            </span>
                                            <button onclick="removeEmployee(<?php echo $employee['id']; ?>)" 
                                                    class="text-red-500 hover:text-red-700 transition-colors">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">ยังไม่มีพนักงานที่รับผิดชอบโซนนี้</p>
                            </div>
                        <?php endif; ?>

                        <!-- Add New Employee -->
                        <!-- Debug: Show employee count -->
                        <div class="border-t pt-6">
                            <div class="bg-gray-100 p-3 rounded mb-4 text-sm">
                                <strong>🔍 Debug Info:</strong><br>
                                - พนักงานที่มีอยู่ทั้งหมด: <?php echo count($all_employees); ?> คน<br>
                                - พนักงานที่ได้รับมอบหมายแล้ว: <?php echo count($zone_employees); ?> คน<br>
                                <?php if (!empty($all_employees)): ?>
                                    - พนักงานที่สามารถเลือกได้: 
                                    <?php foreach ($all_employees as $emp): ?>
                                        <?php echo htmlspecialchars($emp['employee_name']); ?> (ID: <?php echo $emp['id']; ?>), 
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($all_employees)): ?>
                            <div class="border-t pt-6">
                                <h3 class="text-md font-medium text-gray-900 mb-4">เพิ่มพนักงานใหม่</h3>
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="zone_id" value="<?php echo $zone['id']; ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <select name="employee_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                                                <option value="">เลือกพนักงาน...</option>
                                                <?php foreach ($all_employees as $employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>">
                                                        <?php echo htmlspecialchars($employee['employee_name']); ?> 
                                                        (<?php echo htmlspecialchars($employee['employee_code']); ?>)
                                                        <?php if ($employee['nickname']): ?>
                                                            - <?php echo htmlspecialchars($employee['nickname']); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <select name="assignment_type" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                                <option value="primary">หลัก</option>
                                                <option value="backup">สำรอง</option>
                                                <option value="support">สนับสนุน</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" name="assign_employee" 
                                            class="w-full md:w-auto px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                                        <i class="fas fa-plus mr-2"></i>
                                        เพิ่มพนักงาน
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="border-t pt-6">
                                <div class="text-center py-6">
                                    <i class="fas fa-user-times text-gray-300 text-4xl mb-3"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่มีพนักงานที่สามารถเลือกได้</h3>
                                    <p class="text-gray-500 mb-4">พนักงานทั้งหมดถูกมอบหมายแล้ว หรือไม่มีพนักงานในระบบ</p>
                                    <div class="flex justify-center space-x-3">
                                        <a href="../pages/rider.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-user-plus mr-2"></i>เพิ่มพนักงานใหม่
                                        </a>
                                        <a href="../pages/zones.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                            <i class="fas fa-arrow-left mr-2"></i>กลับไปรายการโซน
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Zone Statistics -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-bar mr-3 text-purple-600"></i>
                            สถิติการจัดส่ง
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">รวมทั้งหมด</span>
                                <span class="text-2xl font-bold text-gray-900"><?php echo number_format($zone_stats['total_deliveries'] ?? 0); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-yellow-600">รอดำเนินการ</span>
                                <span class="text-xl font-semibold text-yellow-600"><?php echo number_format($zone_stats['pending_count'] ?? 0); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-green-600">จัดส่งแล้ว</span>
                                <span class="text-xl font-semibold text-green-600"><?php echo number_format($zone_stats['delivered_count'] ?? 0); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-red-600">ไม่สำเร็จ</span>
                                <span class="text-xl font-semibold text-red-600"><?php echo number_format($zone_stats['failed_count'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">การดำเนินการ</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="address_info.php?zone_filter=<?php echo $zone['id']; ?>" 
                           class="w-full flex items-center justify-center px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class="fas fa-list mr-2"></i>
                            ดูรายการจัดส่ง
                        </a>
                        <a href="zones.php" 
                           class="w-full flex items-center justify-center px-4 py-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                            <i class="fas fa-map-marked-alt mr-2"></i>
                            ดูแผนที่โซน
                        </a>
                        <a href="zones_enhanced.php?zone_id=<?php echo $zone['id']; ?>" 
                           class="w-full flex items-center justify-center px-4 py-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                            <i class="fas fa-users-cog mr-2"></i>
                            จัดการพนักงาน
                        </a>
                    </div>
                </div>

                <!-- Zone Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">ข้อมูลโซน</h3>
                    </div>
                    <div class="p-6 space-y-3 text-sm">
                        <div>
                            <span class="text-gray-500">สถานะ:</span>
                            <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                <?php echo $zone['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">สร้างเมื่อ:</span>
                            <span class="ml-2 text-gray-900"><?php echo date('d/m/Y H:i', strtotime($zone['created_at'])); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">แก้ไขล่าสุด:</span>
                            <span class="ml-2 text-gray-900"><?php echo date('d/m/Y H:i', strtotime($zone['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openMapPicker() {
    const params = new URLSearchParams();
    params.set('min_lat', document.querySelector('input[name="min_lat"]').value);
    params.set('max_lat', document.querySelector('input[name="max_lat"]').value);
    params.set('min_lng', document.querySelector('input[name="min_lng"]').value);
    params.set('max_lng', document.querySelector('input[name="max_lng"]').value);
    
    const w = 1100, h = 780;
    const y = window.top.outerHeight / 2 + window.top.screenY - (h / 2);
    const x = window.top.outerWidth / 2 + window.top.screenX - (w / 2);
    window.open(`../leaflet_map_picker.php?${params.toString()}`, 'mapPicker', `toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=${w},height=${h},top=${y},left=${x}`);
}

function removeEmployee(employeeId) {
    if (!confirm('ต้องการเอาพนักงานคนนี้ออกจากโซนหรือไม่?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_employee');
    formData.append('zone_id', <?php echo $zone['id']; ?>);
    formData.append('employee_id', employeeId);
    
    fetch('zone_employee_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    });
}

// Listen for coordinates from map picker
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'leaflet-coordinates') {
        const data = event.data.data;
        if (data.min_lat) document.querySelector('input[name="min_lat"]').value = data.min_lat;
        if (data.max_lat) document.querySelector('input[name="max_lat"]').value = data.max_lat;
        if (data.min_lng) document.querySelector('input[name="min_lng"]').value = data.min_lng;
        if (data.max_lng) document.querySelector('input[name="max_lng"]').value = data.max_lng;
        if (data.center_lat) document.querySelector('input[name="center_lat"]').value = data.center_lat;
        if (data.center_lng) document.querySelector('input[name="center_lng"]').value = data.center_lng;
    }
});

// Auto-update color input
document.querySelector('input[type="color"]').addEventListener('change', function() {
    document.querySelector('input[type="text"][readonly]').value = this.value;
});
</script>

<?php include '../includes/footer.php'; ?>
