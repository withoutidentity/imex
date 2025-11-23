<?php
header('Content-Type: application/json');
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'assign_employee':
            $result = assignEmployeeToZone($_POST);
            break;
            
        case 'remove_employee':
            $result = removeEmployeeFromZone($_POST);
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Invalid action'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function assignEmployeeToZone($data) {
    global $conn;
    
    $zone_id = intval($data['zone_id']);
    $employee_id = intval($data['employee_id']);
    $assignment_type = $data['assignment_type'] ?? 'primary';
    
    if (!$zone_id || !$employee_id) {
        return ['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน'];
    }
    
    // Check if zone exists
    $stmt = $conn->prepare("SELECT id FROM zone_area WHERE id = ?");
    $stmt->execute([$zone_id]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'ไม่พบโซนที่ระบุ'];
    }
    
    // Check if employee exists
    $stmt = $conn->prepare("SELECT id FROM delivery_zone_employees WHERE id = ? AND status = 'active'");
    $stmt->execute([$employee_id]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'ไม่พบพนักงานที่ระบุ'];
    }
    
    // Check if already assigned
    $stmt = $conn->prepare("
        SELECT id FROM zone_employee_assignments 
        WHERE zone_id = ? AND employee_id = ? AND is_active = TRUE
    ");
    $stmt->execute([$zone_id, $employee_id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'พนักงานคนนี้ถูกมอบหมายให้โซนนี้แล้ว'];
    }
    
    // Insert assignment
    $stmt = $conn->prepare("
        INSERT INTO zone_employee_assignments 
        (zone_id, employee_id, assignment_type, start_date, is_active, workload_percentage) 
        VALUES (?, ?, ?, CURDATE(), TRUE, 100.00)
    ");
    
    if ($stmt->execute([$zone_id, $employee_id, $assignment_type])) {
        return ['success' => true, 'message' => 'มอบหมายพนักงานสำเร็จ'];
    } else {
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการมอบหมายพนักงาน'];
    }
}

function removeEmployeeFromZone($data) {
    global $conn;
    
    $zone_id = intval($data['zone_id']);
    $employee_id = intval($data['employee_id']);
    
    if (!$zone_id || !$employee_id) {
        return ['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน'];
    }
    
    // Check if assignment exists
    $stmt = $conn->prepare("
        SELECT id FROM zone_employee_assignments 
        WHERE zone_id = ? AND employee_id = ? AND is_active = TRUE
    ");
    $stmt->execute([$zone_id, $employee_id]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'ไม่พบการมอบหมายที่ระบุ'];
    }
    
    // Deactivate assignment (soft delete)
    $stmt = $conn->prepare("
        UPDATE zone_employee_assignments 
        SET is_active = FALSE, end_date = CURDATE() 
        WHERE zone_id = ? AND employee_id = ? AND is_active = TRUE
    ");
    
    if ($stmt->execute([$zone_id, $employee_id])) {
        return ['success' => true, 'message' => 'ยกเลิกการมอบหมายพนักงานสำเร็จ'];
    } else {
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการยกเลิกการมอบหมาย'];
    }
}
?> 