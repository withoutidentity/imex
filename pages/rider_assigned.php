<?php
$page_title = 'มอบหมายงาน Rider';
require_once '../config/config.php';
include '../includes/header.php';

// Initialize variables
$riders_list = [];
$pending_deliveries = [];
$assignment_result = '';
$processing_error = '';
$selected_rider = '';
$selected_deliveries = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'fetch_data':
                $riders_result = fetchRiders();
                $deliveries_result = fetchPendingDeliveries();
                
                if ($riders_result['success']) {
                    $riders_list = $riders_result['data'];
                }
                if ($deliveries_result['success']) {
                    $pending_deliveries = $deliveries_result['data'];
                }
                break;
                
            case 'manual_assign':
                $selected_rider = $_POST['rider_id'] ?? '';
                $selected_deliveries = $_POST['deliveries'] ?? [];
                
                if (!empty($selected_rider) && !empty($selected_deliveries)) {
                    $result = assignDeliveriesToRider($selected_rider, $selected_deliveries);
                    if ($result['success']) {
                        $assignment_result = $result['message'];
                        // Refresh data
                        $deliveries_result = fetchPendingDeliveries();
                        if ($deliveries_result['success']) {
                            $pending_deliveries = $deliveries_result['data'];
                        }
                    } else {
                        $processing_error = $result['error'];
                    }
                } else {
                    $processing_error = 'กรุณาเลือก Rider และรายการจัดส่ง';
                }
                break;
                
            case 'auto_assign':
                $result = autoAssignDeliveries();
                if ($result['success']) {
                    $assignment_result = $result['message'];
                    // Refresh data
                    $deliveries_result = fetchPendingDeliveries();
                    if ($deliveries_result['success']) {
                        $pending_deliveries = $deliveries_result['data'];
                    }
                } else {
                    $processing_error = $result['error'];
                }
                break;
        }
    }
}

// Function to fetch riders from employees table
function fetchRiders() {
    global $conn;
    
    try {
        // Get all employees as potential riders with their delivery history stats
        $sql = "SELECT 
                    e.id as rider_id,
                    CONCAT(e.first_name, ' ', e.last_name) as rider_name,
                    e.first_name,
                    e.last_name,
                    e.nickname,
                    e.position,
                    e.group_code,
                    COALESCE(stats.total_deliveries, 0) as total_deliveries,
                    COALESCE(stats.unique_addresses, 0) as unique_addresses,
                    COALESCE(stats.last_delivery, NULL) as last_delivery,
                    CASE 
                        WHEN stats.total_deliveries > 0 THEN 'มีประวัติ'
                        ELSE 'ไม่มีประวัติ'
                    END as status
                FROM employees e
                LEFT JOIN (
                    SELECT 
                        delivery_staff_code,
                        COUNT(*) as total_deliveries,
                        COUNT(DISTINCT receiver_address) as unique_addresses,
                        MAX(original_created_at) as last_delivery
                    FROM log_delivery_tracking 
                    WHERE delivery_staff_code IS NOT NULL 
                    AND delivery_staff_code != ''
                    GROUP BY delivery_staff_code
                ) stats ON e.id COLLATE utf8mb4_unicode_ci = stats.delivery_staff_code COLLATE utf8mb4_unicode_ci
                WHERE e.id IS NOT NULL 
                AND e.id != ''
                AND e.first_name IS NOT NULL
                AND e.first_name != ''
                ORDER BY stats.total_deliveries DESC, e.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $data];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการดึงข้อมูล Rider จากตาราง employees: ' . $e->getMessage()];
    }
}

// Function to fetch pending deliveries
function fetchPendingDeliveries() {
    global $conn;
    
    try {
        // Get deliveries that don't have assigned rider or are pending
        $sql = "SELECT 
                    id,
                    awb,
                    receiver_name,
                    receiver_phone,
                    receiver_address,
                    district_name,
                    created_at,
                    delivery_staff_code,
                    delivery_staff_name
                FROM delivery_tracking 
                WHERE (delivery_staff_code IS NULL OR delivery_staff_code = '')
                ORDER BY created_at ASC
                LIMIT 200";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $data];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการดึงข้อมูลรายการจัดส่ง: ' . $e->getMessage()];
    }
}

// Function to assign deliveries to rider manually
function assignDeliveriesToRider($rider_id, $delivery_ids) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Get rider info from employees table
        $rider_sql = "SELECT CONCAT(first_name, ' ', last_name) as rider_name, group_code FROM employees WHERE id = ?";
        $rider_stmt = $conn->prepare($rider_sql);
        $rider_stmt->execute([$rider_id]);
        $rider_info = $rider_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rider_info) {
            $conn->rollBack();
            return ['success' => false, 'error' => 'ไม่พบข้อมูล Rider'];
        }
        
        // Create assignment table if not exists
        $create_assignment_table = "CREATE TABLE IF NOT EXISTS rider_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rider_id VARCHAR(50),
            rider_name VARCHAR(255),
            delivery_tracking_id INT,
            awb VARCHAR(100),
            receiver_address TEXT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by VARCHAR(100) DEFAULT 'SYSTEM',
            assignment_type ENUM('MANUAL', 'AUTO') DEFAULT 'MANUAL',
            INDEX idx_rider_id (rider_id),
            INDEX idx_assigned_at (assigned_at),
            INDEX idx_delivery_id (delivery_tracking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($create_assignment_table);
        
        // Get delivery details for logging
        $placeholders = str_repeat('?,', count($delivery_ids) - 1) . '?';
        $delivery_details_sql = "SELECT id, awb, receiver_address FROM delivery_tracking WHERE id IN ({$placeholders})";
        $delivery_details_stmt = $conn->prepare($delivery_details_sql);
        $delivery_details_stmt->execute($delivery_ids);
        $delivery_details = $delivery_details_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update delivery_tracking with assigned rider
        $update_sql = "UPDATE delivery_tracking 
                       SET delivery_staff_code = ?, delivery_staff_name = ? 
                       WHERE id IN ({$placeholders})";
        
        $params = array_merge([$rider_id, $rider_info['rider_name']], $delivery_ids);
        $update_stmt = $conn->prepare($update_sql);
        $update_result = $update_stmt->execute($params);
        
        if (!$update_result) {
            $conn->rollBack();
            return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล'];
        }
        
        // Log assignments
        $insert_assignment_sql = "INSERT INTO rider_assignments (rider_id, rider_name, delivery_tracking_id, awb, receiver_address, assignment_type) VALUES (?, ?, ?, ?, ?, 'MANUAL')";
        $assignment_stmt = $conn->prepare($insert_assignment_sql);
        
        foreach ($delivery_details as $delivery) {
            $assignment_stmt->execute([
                $rider_id,
                $rider_info['rider_name'],
                $delivery['id'],
                $delivery['awb'],
                $delivery['receiver_address']
            ]);
        }
        
        $updated_count = $update_stmt->rowCount();
        $conn->commit();
        
        return [
            'success' => true, 
            'message' => "มอบหมายงาน {$updated_count} รายการให้ {$rider_info['rider_name']} เรียบร้อยแล้ว"
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

// Function to auto assign deliveries based on rider history
function autoAssignDeliveries() {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Create assignment table if not exists
        $create_assignment_table = "CREATE TABLE IF NOT EXISTS rider_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rider_id VARCHAR(50),
            rider_name VARCHAR(255),
            delivery_tracking_id INT,
            awb VARCHAR(100),
            receiver_address TEXT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by VARCHAR(255) DEFAULT 'SYSTEM',
            assignment_type ENUM('MANUAL', 'AUTO') DEFAULT 'AUTO',
            INDEX idx_rider_id (rider_id),
            INDEX idx_assigned_at (assigned_at),
            INDEX idx_delivery_id (delivery_tracking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($create_assignment_table);
        
        // Get pending deliveries
        $pending_sql = "SELECT id, awb, receiver_address, district_name FROM delivery_tracking 
                        WHERE (delivery_staff_code IS NULL OR delivery_staff_code = '' OR delivery_staff_code = '0')
                        ORDER BY created_at ASC
                        LIMIT 50";
        
        $pending_stmt = $conn->prepare($pending_sql);
        $pending_stmt->execute();
        $pending_deliveries = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pending_deliveries)) {
            $conn->rollBack();
            return ['success' => false, 'error' => 'ไม่มีรายการที่ต้องมอบหมาย (ไม่พบรายการที่ delivery_staff_code ว่าง)'];
        }
        
        $total_assigned = 0;
        $assignments = [];
        $errors = [];
        
        // Prepare assignment logging statement
        $insert_assignment_sql = "INSERT INTO rider_assignments (rider_id, rider_name, delivery_tracking_id, awb, receiver_address, assignment_type, assigned_by) VALUES (?, ?, ?, ?, ?, 'AUTO', ?)";
        $assignment_stmt = $conn->prepare($insert_assignment_sql);
        
        foreach ($pending_deliveries as $delivery) {
            try {
                // Find best rider for this address based on history
                $best_rider = findBestRiderForAddress($delivery['receiver_address'], $delivery['district_name']);
                
                if ($best_rider) {
                    // Update delivery with assigned rider
                    $update_sql = "UPDATE delivery_tracking 
                                   SET delivery_staff_code = ?, delivery_staff_name = ? 
                                   WHERE id = ?";
                    
                    $update_stmt = $conn->prepare($update_sql);
                    $update_result = $update_stmt->execute([
                        $best_rider['rider_id'], 
                        $best_rider['rider_name'], 
                        $delivery['id']
                    ]);
                    
                    if ($update_result && $update_stmt->rowCount() > 0) {
                        // Log the assignment with match type
                        $assignment_reason = isset($best_rider['match_type']) ? $best_rider['match_type'] : 'auto_assigned';
                        try {
                            $assignment_stmt->execute([
                                $best_rider['rider_id'],
                                $best_rider['rider_name'],
                                $delivery['id'],
                                $delivery['awb'],
                                $delivery['receiver_address'],
                                $assignment_reason
                            ]);
                        } catch (Exception $log_e) {
                            // Log assignment failed but update succeeded, continue
                            $errors[] = "ไม่สามารถบันทึก log สำหรับ AWB: {$delivery['awb']} - " . $log_e->getMessage();
                        }
                        
                        $total_assigned++;
                        if (!isset($assignments[$best_rider['rider_id']])) {
                            $assignments[$best_rider['rider_id']] = [
                                'name' => $best_rider['rider_name'],
                                'count' => 0,
                                'reasons' => []
                            ];
                        }
                        $assignments[$best_rider['rider_id']]['count']++;
                        
                        // Track assignment reasons
                        $reason = $assignment_reason;
                        if (!isset($assignments[$best_rider['rider_id']]['reasons'][$reason])) {
                            $assignments[$best_rider['rider_id']]['reasons'][$reason] = 0;
                        }
                        $assignments[$best_rider['rider_id']]['reasons'][$reason]++;
                    } else {
                        $errors[] = "ไม่สามารถอัปเดต delivery ID: {$delivery['id']} AWB: {$delivery['awb']}";
                    }
                } else {
                    $errors[] = "ไม่พบ rider ที่เหมาะสมสำหรับ AWB: {$delivery['awb']}";
                }
            } catch (Exception $delivery_e) {
                $errors[] = "เกิดข้อผิดพลาดกับรายการ AWB: {$delivery['awb']} - " . $delivery_e->getMessage();
            }
        }
        
        $conn->commit();
        
        // Create result message
        $message = "มอบหมายงานอัตโนมัติเสร็จสิ้น: {$total_assigned}/{" . count($pending_deliveries) . "} รายการ";
        
        if (!empty($assignments)) {
            $message .= "\n\nรายละเอียดการมอบหมาย:\n";
            foreach ($assignments as $rider_id => $assignment) {
                $message .= "- {$assignment['name']} (ID: {$rider_id}): {$assignment['count']} รายการ";
                
                // Show assignment reasons
                if (!empty($assignment['reasons'])) {
                    $reasons = [];
                    foreach ($assignment['reasons'] as $reason => $count) {
                        switch ($reason) {
                            case 'exact_address':
                                $reasons[] = "เคยส่งที่อยู่เดียวกัน ({$count})";
                                break;
                            case 'similar_address':
                                $reasons[] = "เคยส่งที่อยู่ใกล้เคียง ({$count})";
                                break;
                            case 'same_area':
                                $reasons[] = "รับผิดชอบพื้นที่เดียวกัน ({$count})";
                                break;
                            case 'same_district':
                                $reasons[] = "เคยส่งในเขตเดียวกัน ({$count})";
                                break;
                            case 'most_active':
                                $reasons[] = "ผู้ส่งที่ active ที่สุด ({$count})";
                                break;
                            case 'most_experienced':
                                $reasons[] = "ผู้ส่งที่มีประสบการณ์ที่สุด ({$count})";
                                break;
                            case 'no_history':
                                $reasons[] = "ไม่มีประวัติการส่ง ({$count})";
                                break;
                            case 'error_fallback':
                                $reasons[] = "มอบหมายฉุกเฉิน ({$count})";
                                break;
                            case 'fallback':
                                $reasons[] = "มอบหมายตามลำดับ ({$count})";
                                break;
                            default:
                                $reasons[] = "{$reason} ({$count})";
                                break;
                        }
                    }
                    $message .= " [" . implode(", ", $reasons) . "]";
                }
                $message .= "\n";
            }
        }
        
        if (!empty($errors)) {
            $message .= "\n\nข้อผิดพลาด:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... และอีก " . (count($errors) - 5) . " ข้อผิดพลาด";
            }
        }
        
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการมอบหมายอัตโนมัติ: ' . $e->getMessage()];
    }
}

// Function to find best rider for specific address
function findBestRiderForAddress($address, $district) {
    global $conn;
    
    try {
        // Check if we have any data in log_delivery_tracking
        $check_log_sql = "SELECT COUNT(*) as count FROM log_delivery_tracking WHERE delivery_staff_code IS NOT NULL AND delivery_staff_code != ''";
        $check_log_stmt = $conn->prepare($check_log_sql);
        $check_log_stmt->execute();
        $log_count = $check_log_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($log_count == 0) {
            // No delivery history, get first available employee
            $fallback_sql = "SELECT 
                                e.id as rider_id,
                                CONCAT(e.first_name, ' ', e.last_name) as rider_name,
                                0 as delivery_count,
                                NULL as last_delivery,
                                'no_history' as match_type
                             FROM employees e
                             WHERE e.id IS NOT NULL AND e.id != ''
                             AND e.first_name IS NOT NULL AND e.first_name != ''
                             ORDER BY e.id
                             LIMIT 1";
            
            $fallback_stmt = $conn->prepare($fallback_sql);
            $fallback_stmt->execute();
            return $fallback_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        
        // Priority 1: Find rider who delivered to exact same address
        $exact_sql = "SELECT 
                        l.delivery_staff_code as rider_id,
                        l.delivery_staff_name as rider_name,
                        COUNT(*) as delivery_count,
                        MAX(l.original_created_at) as last_delivery,
                        'exact_address' as match_type
                      FROM log_delivery_tracking l
                      INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                      WHERE l.receiver_address COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                      AND l.delivery_staff_code IS NOT NULL 
                      AND l.delivery_staff_code != ''
                      GROUP BY l.delivery_staff_code, l.delivery_staff_name
                      ORDER BY delivery_count DESC, last_delivery DESC
                      LIMIT 1";
        
        $exact_stmt = $conn->prepare($exact_sql);
        $exact_stmt->execute([$address]);
        $exact_result = $exact_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exact_result) {
            return $exact_result;
        }
        
        // Priority 2: Find rider who delivered to similar addresses
        if (!empty($address) && strlen($address) > 10) {
            $address_parts = preg_split('/[\s,\-\/]+/', $address, -1, PREG_SPLIT_NO_EMPTY);
            
            if (count($address_parts) >= 2) {
                $search_patterns = [];
                foreach ($address_parts as $part) {
                    if (strlen($part) > 3) {
                        $search_patterns[] = '%' . $part . '%';
                    }
                }
                
                if (count($search_patterns) > 0) {
                    $similar_sql = "SELECT 
                                    l.delivery_staff_code as rider_id,
                                    l.delivery_staff_name as rider_name,
                                    COUNT(*) as delivery_count,
                                    MAX(l.original_created_at) as last_delivery,
                                    'similar_address' as match_type
                                  FROM log_delivery_tracking l
                                  INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                                  WHERE (";
                    
                    $where_conditions = [];
                    $params = [];
                    foreach (array_slice($search_patterns, 0, 3) as $pattern) {
                        $where_conditions[] = "l.receiver_address LIKE ?";
                        $params[] = $pattern;
                    }
                    
                    $similar_sql .= implode(' OR ', $where_conditions);
                    $similar_sql .= ") AND l.delivery_staff_code IS NOT NULL 
                                      AND l.delivery_staff_code != ''
                                      GROUP BY l.delivery_staff_code, l.delivery_staff_name
                                      HAVING COUNT(*) > 0
                                      ORDER BY delivery_count DESC, last_delivery DESC
                                      LIMIT 1";
                    
                    $similar_stmt = $conn->prepare($similar_sql);
                    $similar_stmt->execute($params);
                    $similar_result = $similar_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($similar_result) {
                        return $similar_result;
                    }
                }
            }
        }
        
        // Priority 3: Get most active rider (fallback)
        $active_sql = "SELECT 
                          l.delivery_staff_code as rider_id,
                          l.delivery_staff_name as rider_name,
                          COUNT(*) as delivery_count,
                          MAX(l.original_created_at) as last_delivery,
                          'most_active' as match_type
                        FROM log_delivery_tracking l
                        INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                        WHERE l.delivery_staff_code IS NOT NULL 
                        AND l.delivery_staff_code != ''
                        GROUP BY l.delivery_staff_code, l.delivery_staff_name
                        ORDER BY delivery_count DESC, last_delivery DESC
                        LIMIT 1";
        
        $active_stmt = $conn->prepare($active_sql);
        $active_stmt->execute();
        $active_result = $active_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($active_result) {
            return $active_result;
        }
        
        // Final fallback: get first available employee
        $fallback_sql = "SELECT 
                            e.id as rider_id,
                            CONCAT(e.first_name, ' ', e.last_name) as rider_name,
                            0 as delivery_count,
                            NULL as last_delivery,
                            'fallback' as match_type
                         FROM employees e
                         WHERE e.id IS NOT NULL AND e.id != ''
                         AND e.first_name IS NOT NULL AND e.first_name != ''
                         ORDER BY e.id
                         LIMIT 1";
        
        $fallback_stmt = $conn->prepare($fallback_sql);
        $fallback_stmt->execute();
        return $fallback_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (Exception $e) {
        error_log("Error in findBestRiderForAddress: " . $e->getMessage());
        // Return fallback rider if error occurs
        try {
            $fallback_sql = "SELECT 
                                e.id as rider_id,
                                CONCAT(e.first_name, ' ', e.last_name) as rider_name,
                                0 as delivery_count,
                                NULL as last_delivery,
                                'error_fallback' as match_type
                             FROM employees e
                             WHERE e.id IS NOT NULL AND e.id != ''
                             AND e.first_name IS NOT NULL AND e.first_name != ''
                             ORDER BY e.id
                             LIMIT 1";
            
            $fallback_stmt = $conn->prepare($fallback_sql);
            $fallback_stmt->execute();
            return $fallback_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $fallback_e) {
            return null;
        }
    }
}3));
        $similar_result = $similar_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($similar_result) {
            return $similar_result;
        }
        
        // Priority 3: Find rider with most deliveries in same area_zone/group_code
        $area_sql = "SELECT 
                        l.delivery_staff_code as rider_id,
                        l.delivery_staff_name as rider_name,
                        COUNT(*) as delivery_count,
                        MAX(l.original_created_at) as last_delivery,
                        'same_area' as match_type
                     FROM log_delivery_tracking l
                     INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                     WHERE l.area_zone IS NOT NULL 
                     AND l.area_zone != 'ไม่ระบุ'
                     AND l.area_zone = e.group_code
                     AND l.delivery_staff_code IS NOT NULL 
                     AND l.delivery_staff_code != ''
                     GROUP BY l.delivery_staff_code, l.delivery_staff_name
                     HAVING COUNT(*) > 0
                     ORDER BY delivery_count DESC, last_delivery DESC
                     LIMIT 1";
        
        $area_stmt = $conn->prepare($area_sql);
        $area_stmt->execute();
        $area_result = $area_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($area_result) {
            return $area_result;
        }
        
        // Priority 4: Find rider based on district/area keywords
        if (!empty($district)) {
            $district_sql = "SELECT 
                              l.delivery_staff_code as rider_id,
                              l.delivery_staff_name as rider_name,
                              COUNT(*) as delivery_count,
                              MAX(l.original_created_at) as last_delivery,
                              'same_district' as match_type
                            FROM log_delivery_tracking l
                            INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                            WHERE (
                                l.receiver_address LIKE ? 
                                OR l.area_zone LIKE ?
                            )
                            AND l.delivery_staff_code IS NOT NULL 
                            AND l.delivery_staff_code != ''
                            GROUP BY l.delivery_staff_code, l.delivery_staff_name
                            HAVING COUNT(*) > 0
                            ORDER BY delivery_count DESC, last_delivery DESC
                            LIMIT 1";
            
            $district_pattern = '%' . $district . '%';
            $district_stmt = $conn->prepare($district_sql);
            $district_stmt->execute([$district_pattern, $district_pattern]);
            $district_result = $district_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($district_result) {
                return $district_result;
            }
        }
        
        // Priority 5: Get most active rider with recent deliveries
        $active_sql = "SELECT 
                          l.delivery_staff_code as rider_id,
                          l.delivery_staff_name as rider_name,
                          COUNT(*) as delivery_count,
                          MAX(l.original_created_at) as last_delivery,
                          'most_active' as match_type
                        FROM log_delivery_tracking l
                        INNER JOIN employees e ON l.delivery_staff_code COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
                        WHERE l.original_created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND l.delivery_staff_code IS NOT NULL 
                        AND l.delivery_staff_code != ''
                        GROUP BY l.delivery_staff_code, l.delivery_staff_name
                        ORDER BY delivery_count DESC, last_delivery DESC
                        LIMIT 1";
        
        $active_stmt = $conn->prepare($active_sql);
        $active_stmt->execute();
        $active_result = $active_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($active_result) {
            return $active_result;
        }
        
        // Priority 6: If no log history found, get first available employee
        $fallback_sql = "SELECT 
                            e.id as rider_id,
                            CONCAT(e.first_name, ' ', e.last_name) as rider_name,
                            0 as delivery_count,
                            NULL as last_delivery,
                            'fallback' as match_type
                         FROM employees e
                         WHERE e.id IS NOT NULL AND e.id != ''
                         AND e.first_name IS NOT NULL AND e.first_name != ''
                         ORDER BY e.id
                         LIMIT 1";
        
        $fallback_stmt = $conn->prepare($fallback_sql);
        $fallback_stmt->execute();
        $fallback_result = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $fallback_result ?: null;
        
    } catch (Exception $e) {
        error_log("Error in findBestRiderForAddress: " . $e->getMessage());
        return null;
    }
}

// Load initial data
if (empty($riders_list) && empty($pending_deliveries)) {
    $riders_result = fetchRiders();
    $deliveries_result = fetchPendingDeliveries();
    
    if ($riders_result['success']) {
        $riders_list = $riders_result['data'];
    }
    if ($deliveries_result['success']) {
        $pending_deliveries = $deliveries_result['data'];
    }
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">มอบหมายงาน Rider</h1>
                <p class="text-gray-600 mt-2">มอบหมายงานจัดส่งให้ Rider ด้วยตนเองหรือใช้ระบบอัตโนมัติ</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-user-cog text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-blue-50 p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">Rider ทั้งหมด</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo count($riders_list); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">รอมอบหมาย</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo count($pending_deliveries); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <i class="fas fa-robot text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">มอบหมายอัตโนมัติ</h3>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="auto_assign">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
                            <i class="fas fa-magic mr-2"></i>เริ่มมอบหมาย
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex flex-wrap gap-4">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="fetch_data">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sync mr-2"></i>รีเฟรชข้อมูล
                </button>
            </form>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($assignment_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars($assignment_result); ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($processing_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span><?php echo htmlspecialchars($processing_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Manual Assignment -->
    <?php if (!empty($pending_deliveries)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-hand-point-right mr-2"></i>มอบหมายด้วยตนเอง
            </h2>
            
            <form method="POST" id="manualAssignForm">
                <input type="hidden" name="action" value="manual_assign">
                
                <!-- Rider Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลือก Rider:</label>
                    <select name="rider_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <option value="">-- เลือก Rider --</option>
                        <?php foreach ($riders_list as $rider): ?>
                            <option value="<?php echo htmlspecialchars($rider['rider_id']); ?>">
                                <?php echo htmlspecialchars($rider['rider_name']); ?>
                                <?php if (!empty($rider['nickname'])): ?>
                                    (<?php echo htmlspecialchars($rider['nickname']); ?>)
                                <?php endif; ?>
                                [ID: <?php echo htmlspecialchars($rider['rider_id']); ?>]
                                <?php if (!empty($rider['position'])): ?>
                                    - <?php echo htmlspecialchars($rider['position']); ?>
                                <?php endif; ?>
                                <?php if (!empty($rider['group_code'])): ?>
                                    - กลุ่ม <?php echo htmlspecialchars($rider['group_code']); ?>
                                <?php endif; ?>
                                - ประวัติ: <?php echo number_format($rider['total_deliveries']); ?> รายการ
                                <?php if ($rider['total_deliveries'] == 0): ?>
                                    (ยังไม่เคยส่ง)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Delivery Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        เลือกรายการจัดส่ง:
                        <button type="button" onclick="selectAll()" class="ml-2 text-blue-600 hover:text-blue-800">เลือกทั้งหมด</button>
                        <button type="button" onclick="clearAll()" class="ml-2 text-red-600 hover:text-red-800">ยกเลิกทั้งหมด</button>
                    </label>
                    
                    <div class="max-h-96 overflow-y-auto border border-gray-300 rounded-lg">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                    </th>
                                    <th class="px-4 py-2 text-left">AWB</th>
                                    <th class="px-4 py-2 text-left">ผู้รับ</th>
                                    <th class="px-4 py-2 text-left">เบอร์</th>
                                    <th class="px-4 py-2 text-left">ที่อยู่</th>
                                    <th class="px-4 py-2 text-left">วันที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_deliveries as $delivery): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" name="deliveries[]" value="<?php echo $delivery['id']; ?>" class="delivery-checkbox">
                                        </td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($delivery['awb'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($delivery['receiver_name'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($delivery['receiver_phone'] ?? ''); ?></td>
                                        <td class="px-4 py-2 text-sm max-w-xs truncate"><?php echo htmlspecialchars(substr($delivery['receiver_address'] ?? '', 0, 50)); ?></td>
                                        <td class="px-4 py-2 text-sm"><?php echo date('d/m/Y', strtotime($delivery['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>มอบหมายงาน
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <i class="fas fa-check-circle text-green-600 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">ไม่มีรายการที่ต้องมอบหมาย</h3>
            <p class="text-gray-600">รายการจัดส่งทั้งหมดได้รับการมอบหมายแล้ว</p>
        </div>
    <?php endif; ?>

    <!-- Riders List -->
    <?php if (!empty($riders_list)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-users mr-2"></i>รายชื่อ Rider และประวัติ
            </h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">รหัส</th>
                            <th class="px-4 py-3 text-left">ชื่อ</th>
                            <th class="px-4 py-3 text-left">ชื่อเล่น</th>
                            <th class="px-4 py-3 text-left">ตำแหน่ง</th>
                            <th class="px-4 py-3 text-left">กลุ่ม</th>
                            <th class="px-4 py-3 text-left">รายการทั้งหมด</th>
                            <th class="px-4 py-3 text-left">ที่อยู่ไม่ซ้ำ</th>
                            <th class="px-4 py-3 text-left">จัดส่งล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riders_list as $rider): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-blue-600"><?php echo htmlspecialchars($rider['rider_id']); ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <?php echo htmlspecialchars($rider['rider_name']); ?>
                                    <?php if ($rider['total_deliveries'] == 0): ?>
                                        <span class="ml-2 bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">ใหม่</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($rider['nickname'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($rider['position'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if (!empty($rider['group_code'])): ?>
                                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs">
                                            <?php echo htmlspecialchars($rider['group_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-500">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo number_format($rider['total_deliveries']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo number_format($rider['unique_addresses']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php 
                                    if (!empty($rider['last_delivery'])) {
                                        echo date('d/m/Y', strtotime($rider['last_delivery']));
                                    } else {
                                        echo '<span class="text-gray-500">ยังไม่เคยส่ง</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function selectAll() {
    const checkboxes = document.querySelectorAll('.delivery-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = true);
    updateSelectAllCheckbox();
}

function clearAll() {
    const checkboxes = document.querySelectorAll('.delivery-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = false);
    updateSelectAllCheckbox();
}

function toggleAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.delivery-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.delivery-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkedCount = document.querySelectorAll('.delivery-checkbox:checked').length;
    
    selectAllCheckbox.checked = checkedCount === checkboxes.length;
    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
}

// Add event listeners to individual checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.delivery-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });
    
    console.log('Rider assignment page loaded');
});
</script>

<?php include '../includes/footer.php'; ?>