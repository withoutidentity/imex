<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$trackingId = $_GET['id'] ?? '';

if (empty($trackingId)) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุ Tracking ID'
    ]);
    exit;
}

try {
    // Try to find tracking data by tracking_id or by database ID
    $stmt = $conn->prepare("
        SELECT 
            dt.*,
            da.recipient_name as da_recipient_name,
            da.address as da_address,
            da.recipient_phone as da_phone,
            za.zone_name,
            za.zone_code,
            dze.employee_name,
            dze.nickname as employee_nickname
        FROM delivery_tracking dt
        LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
        LEFT JOIN zone_area za ON da.zone_id = za.id
        LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        WHERE dt.id = ? OR dt.tracking_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$trackingId, $trackingId]);
    $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tracking) {
        // Try to find by AWB number as fallback
        $stmt = $conn->prepare("
            SELECT 
                dt.*,
                da.recipient_name as da_recipient_name,
                da.address as da_address,
                da.recipient_phone as da_phone,
                za.zone_name,
                za.zone_code,
                dze.employee_name,
                dze.nickname as employee_nickname
            FROM delivery_tracking dt
            LEFT JOIN delivery_address da ON dt.awb_number = da.awb_number
            LEFT JOIN zone_area za ON da.zone_id = za.id
            LEFT JOIN zone_employee_assignments zea ON da.zone_id = zea.zone_id AND zea.is_active = TRUE
            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
            WHERE dt.awb_number = ?
            LIMIT 1
        ");
        
        $stmt->execute([$trackingId]);
        $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$tracking) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลการติดตาม'
        ]);
        exit;
    }
    
    // Prepare response data
    $responseData = [
        'tracking_id' => $tracking['tracking_id'],
        'awb_number' => $tracking['awb_number'],
        'current_status' => $tracking['current_status'],
        'priority_level' => $tracking['priority_level'],
        'service_type' => $tracking['service_type'],
        'package_weight' => $tracking['package_weight'],
        'cod_amount' => $tracking['cod_amount'],
        'delivery_attempts' => $tracking['delivery_attempts'],
        'estimated_delivery_time' => $tracking['estimated_delivery_time'],
        'actual_delivery_time' => $tracking['actual_delivery_time'],
        'failure_reason' => $tracking['failure_reason'],
        'delivery_notes' => $tracking['delivery_notes'],
        'current_location_address' => $tracking['current_location_address'],
        'recipient_name' => $tracking['recipient_name'] ?? $tracking['da_recipient_name'],
        'recipient_phone' => $tracking['recipient_phone'] ?? $tracking['da_phone'],
        'recipient_address' => $tracking['recipient_address'] ?? $tracking['da_address'],
        'zone_name' => $tracking['zone_name'],
        'zone_code' => $tracking['zone_code'],
        'employee_name' => $tracking['employee_name'],
        'employee_nickname' => $tracking['employee_nickname'],
        'created_at' => $tracking['created_at'],
        'updated_at' => $tracking['updated_at']
    ];
    
    // Add status translations
    $statusTranslations = [
        'pending' => 'รอจัดส่ง',
        'picked_up' => 'เก็บแล้ว',
        'in_transit' => 'กำลังขนส่ง',
        'out_for_delivery' => 'กำลังจัดส่ง',
        'delivered' => 'จัดส่งแล้ว',
        'failed' => 'ไม่สำเร็จ',
        'returned' => 'ส่งคืน',
        'cancelled' => 'ยกเลิก'
    ];
    
    $failureReasonTranslations = [
        'address_not_found' => 'หาที่อยู่ไม่เจอ',
        'recipient_not_available' => 'ผู้รับไม่อยู่',
        'refused_delivery' => 'ปฏิเสธรับ',
        'damaged_package' => 'พัสดุเสียหาย',
        'weather' => 'สภาพอากาศ',
        'vehicle_breakdown' => 'รถเสีย',
        'other' => 'อื่นๆ'
    ];
    
    $responseData['current_status_text'] = $statusTranslations[$tracking['current_status']] ?? $tracking['current_status'];
    $responseData['failure_reason_text'] = $failureReasonTranslations[$tracking['failure_reason']] ?? $tracking['failure_reason'];
    
    echo json_encode([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching tracking details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}
?>
