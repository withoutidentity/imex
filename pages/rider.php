<?php
ob_start();
$page_title = 'จัดการ Rider';
require_once '../config/config.php';

// Fetch employees from delivery_zone_employees table
$employees = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            id,
            employee_code,
            employee_name,
            nickname,
            position,
            zone_code,
            phone,
            email,
            status,
            hire_date,
            created_at,
            updated_at
        FROM delivery_zone_employees 
        ORDER BY employee_name ASC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "เกิดข้อผิดพลาดในการโหลดข้อมูลพนักงาน: " . $e->getMessage();
}

// Fetch zones for dropdown
$zones = [];
try {
    $stmt = $conn->prepare("SELECT zone_code, zone_name FROM zone_area WHERE is_active = 1 ORDER BY zone_code ASC");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_employee'])) {
        // Add new employee
        try {
            // Generate employee_code automatically
            $latestStmt = $conn->query("SELECT employee_code FROM delivery_zone_employees ORDER BY id DESC LIMIT 1");
            $latestCode = $latestStmt->fetchColumn();
            
            if ($latestCode) {
                // Extract numeric part and increment (e.g., 664921T000028 -> 664921T000029)
                if (preg_match('/^(.+?)(\d+)$/', $latestCode, $matches)) {
                    $prefix = $matches[1];
                    $number = $matches[2];
                    $newNumber = str_pad((int)$number + 1, strlen($number), '0', STR_PAD_LEFT);
                    $newEmployeeCode = $prefix . $newNumber;
                } else {
                    $newEmployeeCode = $latestCode . '1'; // Fallback
                }
            } else {
                $newEmployeeCode = '664921T000001'; // Default start
            }

            // Fetch description from zone_area based on zone_code
            $zoneArea = null;
            if (!empty($_POST['zone_code'])) {
                $zStmt = $conn->prepare("SELECT description FROM zone_area WHERE zone_code = ?");
                $zStmt->execute([$_POST['zone_code']]);
                $zoneArea = $zStmt->fetchColumn();
                if ($zoneArea === false) $zoneArea = null;
            }

            $stmt = $conn->prepare("
                INSERT INTO delivery_zone_employees 
                (employee_code, employee_name, nickname, position, zone_code, zone_area, phone, email, status, hire_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newEmployeeCode,
                $_POST['employee_name'],
                $_POST['nickname'],
                $_POST['position'],
                $_POST['zone_code'],
                $zoneArea,
                $_POST['phone'],
                $_POST['email'],
                $_POST['status'],
                $_POST['hire_date']
            ]);
            $success_message = "เพิ่มพนักงานใหม่สำเร็จ";
            // Refresh data
            header("Location: rider.php");
            exit;
        } catch (Exception $e) {
            $error_message = "เกิดข้อผิดพลาดในการเพิ่มพนักงาน: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_employee'])) {
        // Update employee
        try {
            $stmt = $conn->prepare("
                UPDATE delivery_zone_employees 
                SET employee_name = ?, nickname = ?, position = ?, zone_code = ?, phone = ?, email = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['employee_name'],
                $_POST['nickname'],
                $_POST['position'],
                $_POST['zone_code'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['status'],
                $_POST['employee_id']
            ]);
            $success_message = "อัปเดตข้อมูลพนักงานสำเร็จ";
            // Refresh data
            header("Location: rider.php");
            exit;
        } catch (Exception $e) {
            $error_message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_employee'])) {
        // Delete employee (soft delete by changing status)
        try {
            $stmt = $conn->prepare("UPDATE delivery_zone_employees SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$_POST['employee_id']]);
            $success_message = "ลบพนักงานสำเร็จ";
            // Refresh data
            header("Location: rider.php");
            exit;
        } catch (Exception $e) {
            $error_message = "เกิดข้อผิดพลาดในการลบพนักงาน: " . $e->getMessage();
        }
    }
}
include '../includes/header.php';
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">จัดการ Rider</h1>
                <p class="text-gray-600">ระบบจัดการพนักงานขนส่งและมอบหมายงาน</p>
                <div class="flex flex-wrap items-center gap-4 mt-2">
                    <div class="flex items-center gap-1 text-sm text-gray-600">
                        <i class="fas fa-users text-blue-600"></i>
                        <span>พนักงานทั้งหมด: <strong><?php echo count($employees); ?></strong> คน</span>
                    </div>
                    <div class="flex items-center gap-1 text-sm text-gray-600">
                        <i class="fas fa-user-check text-green-600"></i>
                        <span>ใช้งาน: <strong><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'active')); ?></strong> คน</span>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <button onclick="showAddEmployeeModal()" class="w-full md:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                    <i class="fas fa-user-plus mr-2"></i>เพิ่มพนักงาน
                </button>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Employees Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-blue-600"></i>
                รายชื่อพนักงาน
            </h2>
        </div>

        <?php if (empty($employees)): ?>
            <div class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                <div>ยังไม่มีข้อมูลพนักงาน</div>
                <button onclick="showAddEmployeeModal()" class="mt-2 text-blue-600 hover:text-blue-800">
                    คลิกเพื่อเพิ่มพนักงานคนแรก
                </button>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อพนักงาน</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อเล่น</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ตำแหน่ง</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">โซน</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">เบอร์โทร</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อีเมล</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่เริ่มงาน</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $employee): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                                <div class="text-xs text-gray-500">Code: <?php echo htmlspecialchars($employee['employee_code'] ?? $employee['id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($employee['nickname']); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($employee['zone_code'] ?? '-'); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <?php if ($employee['phone']): ?>
                                            <a href="tel:<?php echo $employee['phone']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($employee['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <?php if ($employee['email']): ?>
                                            <a href="mailto:<?php echo $employee['email']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($employee['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if ($employee['status'] === 'active'): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>ใช้งาน
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle mr-1"></i>ไม่ใช้งาน
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center text-sm text-gray-900">
                                        <?php if ($employee['hire_date']): ?>
                                            <?php echo date('d/m/Y', strtotime($employee['hire_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col space-y-1">
                                            <button onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)" class="w-full bg-yellow-500 text-white px-2 py-1 rounded text-xs hover:bg-yellow-600 transition-colors">
                                                <i class="fas fa-edit mr-1"></i>แก้ไข
                                            </button>
                                            <?php if ($employee['status'] === 'active'): ?>
                                                <form method="POST" class="w-full" onsubmit="return confirm('ต้องการปิดการใช้งานพนักงานคนนี้หรือไม่?')">
                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                    <button type="submit" name="delete_employee" class="w-full bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 transition-colors">
                                                        <i class="fas fa-user-times mr-1"></i>ปิดใช้งาน
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="block lg:hidden p-4 space-y-4">
                <?php foreach ($employees as $employee): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md border-l-4 <?php echo $employee['status'] === 'active' ? 'border-green-500' : 'border-red-500'; ?>">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <div class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                <div class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($employee['nickname']); ?> - <?php echo htmlspecialchars($employee['position']); ?>
                                    <?php if (!empty($employee['zone_code'])): ?>
                                        <span class="ml-1 bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs"><?php echo htmlspecialchars($employee['zone_code']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($employee['status'] === 'active'): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>ใช้งาน
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">
                                    <i class="fas fa-times-circle mr-1"></i>ไม่ใช้งาน
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="border-t pt-3 space-y-2 text-sm">
                            <?php if ($employee['phone']): ?>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-phone w-4 text-center text-gray-400 mr-2"></i>
                                    <a href="tel:<?php echo $employee['phone']; ?>" class="text-blue-600 hover:text-blue-800"><?php echo htmlspecialchars($employee['phone']); ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if ($employee['email']): ?>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-envelope w-4 text-center text-gray-400 mr-2"></i>
                                    <a href="mailto:<?php echo $employee['email']; ?>" class="text-blue-600 hover:text-blue-800 truncate"><?php echo htmlspecialchars($employee['email']); ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if ($employee['hire_date']): ?>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-calendar-alt w-4 text-center text-gray-400 mr-2"></i>
                                    <span>เริ่มงาน: <?php echo date('d/m/Y', strtotime($employee['hire_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex flex-col space-y-2">
                            <button onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)" class="w-full bg-yellow-500 text-white px-2 py-2 rounded-lg text-sm hover:bg-yellow-600 transition-colors flex items-center justify-center">
                                <i class="fas fa-edit mr-2"></i>แก้ไข
                            </button>
                            <?php if ($employee['status'] === 'active'): ?>
                                <form method="POST" class="w-full" onsubmit="return confirm('ต้องการปิดการใช้งานพนักงานคนนี้หรือไม่?')">
                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                    <button type="submit" name="delete_employee" class="w-full bg-red-500 text-white px-2 py-2 rounded-lg text-sm hover:bg-red-600 transition-colors flex items-center justify-center">
                                        <i class="fas fa-user-times mr-2"></i>ปิดใช้งาน
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-bold text-gray-800">เพิ่มพนักงานใหม่</h3>
                </div>
                
                <form id="employeeForm" method="POST" class="px-6 py-4">
                    <input type="hidden" id="employeeId" name="employee_id" value="">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อพนักงาน *</label>
                            <input type="text" id="employeeName" name="employee_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อเล่น</label>
                            <input type="text" id="nickname" name="nickname" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ตำแหน่ง</label>
                            <select id="position" name="position" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">เลือกตำแหน่ง</option>
                                <option value="SPT">SPT</option>
                                <option value="SPT+C">SPT+C</option>
                                <option value="SPT+S">SPT+S</option>
                                <option value="Manager">Manager</option>
                                <option value="Supervisor">Supervisor</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">โซน (Zone Code)</label>
                            <select id="zoneCode" name="zone_code" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- เลือกโซน --</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo htmlspecialchars($zone['zone_code']); ?>">
                                        <?php echo htmlspecialchars($zone['zone_code'] . ' - ' . $zone['zone_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">เบอร์โทร</label>
                            <input type="tel" id="phone" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">อีเมล</label>
                            <input type="email" id="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">สถานะ</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="active">ใช้งาน</option>
                                <option value="inactive">ไม่ใช้งาน</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">วันที่เริ่มงาน</label>
                            <input type="date" id="hireDate" name="hire_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 mt-6">
                        <button type="submit" id="submitBtn" name="add_employee" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>บันทึก
                        </button>
                        <button type="button" onclick="hideEmployeeModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition-colors">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showAddEmployeeModal() {
    document.getElementById('modalTitle').textContent = 'เพิ่มพนักงานใหม่';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    document.getElementById('submitBtn').name = 'add_employee';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>บันทึก';
    document.getElementById('employeeModal').classList.remove('hidden');
}

function editEmployee(employee) {
    document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลพนักงาน';
    document.getElementById('employeeId').value = employee.id;
    document.getElementById('employeeName').value = employee.employee_name;
    document.getElementById('nickname').value = employee.nickname || '';
    document.getElementById('position').value = employee.position || '';
    document.getElementById('zoneCode').value = employee.zone_code || '';
    document.getElementById('phone').value = employee.phone || '';
    document.getElementById('email').value = employee.email || '';
    document.getElementById('status').value = employee.status;
    document.getElementById('hireDate').value = employee.hire_date || '';
    document.getElementById('submitBtn').name = 'update_employee';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>อัปเดต';
    document.getElementById('employeeModal').classList.remove('hidden');
}

function hideEmployeeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('employeeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideEmployeeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideEmployeeModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?> 