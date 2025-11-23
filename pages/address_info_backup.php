<?php
$page_title = 'ข้อมูลที่อยู่และโซน';
require_once '../config/config.php';

// Helper to check if a column exists on a table (robust to permissions)
function columnExists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `" . $table . "``");
        $stmt->execute();
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return in_array($column, $cols, true);
    } catch (Exception $e) {
        return false;
    }
}

// Function to get zone statistics like zones.php
function getZoneStatistics(PDO $conn, $zone_id) {
    try {
        $stmt = $conn->prepare("
            SELECT 
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
            WHERE za.id = ?
            GROUP BY za.id
        ");
        $stmt->execute([$zone_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// Get actual column structure first
$actualColumns = [];
try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM delivery_tracking");
    $stmt->execute();
    $actualColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Exception $e) {
    $actualColumns = [];
}

// Map actual column names to our expected fields
$columnMap = [
    'awb_number' => null,
    'recipient_name' => null,
    'address' => null,
    'zone_code' => null,
    'zone_name' => null,
    'delivery_employee_name' => null,
    'recipient_phone' => null,
    'delivery_branch_name' => null,
    'sign_branch' => null
];

foreach ($actualColumns as $col) {
    if (strtolower($col) === 'awb' || stripos($col, 'awb') !== false) {
        $columnMap['awb_number'] = $col;
    }
    if (stripos($col, 'ผู้รับ') !== false && stripos($col, 'ที่อยู่') === false) {
        $columnMap['recipient_name'] = $col;
    }
    if (stripos($col, 'ที่อยู่ผู้รับ') !== false) {
        $columnMap['address'] = $col;
    }
    if (stripos($col, 'รหัสเขต') !== false) {
        $columnMap['zone_code'] = $col;
    }
    if (stripos($col, 'ชื่อเขต') !== false) {
        $columnMap['zone_name'] = $col;
    }
    if (stripos($col, 'ชื่อของพนักงานนำจ่าย') !== false || stripos($col, 'พนักงานนำจ่าย') !== false) {
        $columnMap['delivery_employee_name'] = $col;
    }
    if (stripos($col, 'เบอร์มือถือผู้รับ') !== false || stripos($col, 'เบอร์โทรผู้รับ') !== false) {
        $columnMap['recipient_phone'] = $col;
    }
    if (stripos($col, 'ชื่อสาขานำจ่าย') !== false) {
        $columnMap['delivery_branch_name'] = $col;
    }
    if (stripos($col, 'สาขาเซ็นรับ') !== false) {
        $columnMap['sign_branch'] = $col;
    }
}

// Define allowed branches at the top level
$allowedBranches = [
    '64Mueang NakhonSiThammarat03',
    '64Mueang NakhonSiThammarat071',
    '64Mueang NakhonSiThammarat072', 
    '64Mueang NakhonSiThammarat073',
    '64Mueang NakhonSiThammarat074'
];

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

// Build enhanced query with zone and coordinate data
$selects = [];
foreach ($columnMap as $alias => $actualCol) {
    if ($actualCol) {
        $selects[] = "dt.`{$actualCol}` AS {$alias}";
    } else {
        $selects[] = "NULL AS {$alias}";
    }
}

// Add coordinates and zone data from delivery_address table
$selects[] = "da.latitude AS lat";
$selects[] = "da.longitude AS lng";
$selects[] = "za.id AS zone_id";
$selects[] = "za.zone_name AS enhanced_zone_name";
$selects[] = "za.zone_code AS enhanced_zone_code";
$selects[] = "za.color_code AS zone_color";
$selects[] = "za.description AS zone_description";
$selects[] = "za.min_lat AS zone_min_lat";
$selects[] = "za.max_lat AS zone_max_lat";
$selects[] = "za.min_lng AS zone_min_lng";
$selects[] = "za.max_lng AS zone_max_lng";
$selects[] = "za.center_lat AS zone_center_lat";
$selects[] = "za.center_lng AS zone_center_lng";
$selects[] = "za.is_active AS zone_is_active";
$selects[] = "da.address AS full_address";
$selects[] = "da.recipient_name AS enhanced_recipient_name";
$selects[] = "da.recipient_phone AS enhanced_recipient_phone";
$selects[] = "da.province";
$selects[] = "da.district";
$selects[] = "da.subdistrict";

// Add zone employee data
$selects[] = "GROUP_CONCAT(DISTINCT CONCAT(dze.employee_name, ' (', dze.nickname, ')') ORDER BY zea.assignment_type, dze.employee_name SEPARATOR ', ') AS zone_employees";
$selects[] = "GROUP_CONCAT(DISTINCT dze.employee_code ORDER BY zea.assignment_type SEPARATOR ', ') AS employee_codes";
$selects[] = "GROUP_CONCAT(DISTINCT dze.position ORDER BY zea.assignment_type SEPARATOR ', ') AS employee_positions";
$selects[] = "GROUP_CONCAT(DISTINCT dze.phone ORDER BY zea.assignment_type SEPARATOR ', ') AS employee_phones";
$selects[] = "COUNT(DISTINCT dze.id) AS employee_count";

// Add zone delivery statistics
$selects[] = "(SELECT COUNT(*) FROM delivery_address da2 WHERE da2.zone_id = za.id) AS zone_total_deliveries";
$selects[] = "(SELECT COUNT(*) FROM delivery_address da2 WHERE da2.zone_id = za.id AND da2.delivery_status = 'pending') AS zone_pending_deliveries";
$selects[] = "(SELECT COUNT(*) FROM delivery_address da2 WHERE da2.zone_id = za.id AND da2.delivery_status = 'delivered') AS zone_delivered_count";
$selects[] = "(SELECT COUNT(*) FROM delivery_address da2 WHERE da2.zone_id = za.id AND da2.delivery_status = 'failed') AS zone_failed_count";

// Check if delivery_address table has data
$hasDeliveryAddressData = false;
try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM delivery_address LIMIT 1");
    $checkStmt->execute();
    $hasDeliveryAddressData = $checkStmt->fetchColumn() > 0;
} catch (Exception $e) {
    $hasDeliveryAddressData = false;
}

if ($hasDeliveryAddressData) {
    // Enhanced SQL with JOINs to get zone and coordinate data
    $sql = 'SELECT ' . implode(', ', $selects) . ' 
            FROM delivery_tracking dt 
            LEFT JOIN delivery_address da ON ';

    // Join condition based on AWB column availability
    if ($columnMap['awb_number']) {
        $sql .= "dt.`{$columnMap['awb_number']}` = da.awb_number";
    } else {
        $sql .= "1=0"; // No join possible without AWB
    }

    $sql .= ' LEFT JOIN zone_area za ON da.zone_id = za.id
             LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
             LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = \'active\'';

    // Add filtering
    $whereConditions = [];
    $params = [];

    // เพิ่มเงื่อนไขให้แสดงเฉพาะข้อมูลที่มี delivery_address
    $whereConditions[] = "da.id IS NOT NULL";

    // Branch filter - use the branches defined at the top

    // Add branch filter using delivery_tracking table data
    if ($columnMap['delivery_branch_name']) {
        // Create placeholders for IN clause
        $branchPlaceholders = str_repeat('?,', count($allowedBranches) - 1) . '?';
        $whereConditions[] = "dt.{$columnMap['delivery_branch_name']} IN ($branchPlaceholders)";
        $params = array_merge($params, $allowedBranches);
    }

    if (isset($_GET['zone_filter']) && !empty($_GET['zone_filter'])) {
        $whereConditions[] = "za.id = ?";
        $params[] = $_GET['zone_filter'];
    }

    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $whereConditions[] = "da.delivery_status = ?";
        $params[] = $_GET['status_filter'];
    }

    if (isset($_GET['employee_filter']) && !empty($_GET['employee_filter'])) {
        $whereConditions[] = "dze.id = ?";
        $params[] = $_GET['employee_filter'];
    }

    if (!empty($whereConditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
    }

    $sql .= ' GROUP BY da.id, za.id
             ORDER BY da.created_at DESC 
             LIMIT 1000';
} else {
    // Fallback: แสดงข้อมูลจาก delivery_tracking โดยตรง
    $fallbackSelects = [];
    foreach ($columnMap as $alias => $actualCol) {
        if ($actualCol) {
            $fallbackSelects[] = "dt.`{$actualCol}` AS {$alias}";
        } else {
            $fallbackSelects[] = "NULL AS {$alias}";
        }
    }
    
    // Add null values for zone and coordinate data
    $fallbackSelects[] = "NULL AS lat";
    $fallbackSelects[] = "NULL AS lng";
    $fallbackSelects[] = "NULL AS zone_id";
    $fallbackSelects[] = "NULL AS enhanced_zone_name";
    $fallbackSelects[] = "NULL AS enhanced_zone_code";
    $fallbackSelects[] = "NULL AS zone_color";
    $fallbackSelects[] = "NULL AS zone_description";
    $fallbackSelects[] = "NULL AS zone_employees";
    $fallbackSelects[] = "NULL AS employee_codes";
    $fallbackSelects[] = "NULL AS employee_positions";
    $fallbackSelects[] = "NULL AS employee_phones";
    $fallbackSelects[] = "0 AS employee_count";
    $fallbackSelects[] = "0 AS zone_total_deliveries";
    $fallbackSelects[] = "0 AS zone_pending_deliveries";
    $fallbackSelects[] = "0 AS zone_delivered_count";
    $fallbackSelects[] = "0 AS zone_failed_count";
    $fallbackSelects[] = "NULL AS full_address";
    $fallbackSelects[] = "NULL AS enhanced_recipient_name";
    $fallbackSelects[] = "NULL AS enhanced_recipient_phone";
    $fallbackSelects[] = "NULL AS province";
    $fallbackSelects[] = "NULL AS district";
    $fallbackSelects[] = "NULL AS subdistrict";
    
    $sql = 'SELECT ' . implode(', ', $fallbackSelects) . ' 
            FROM delivery_tracking dt';
    
    // Add branch filter for fallback query too
    $fallbackParams = [];
    if ($columnMap['delivery_branch_name']) {
        $branchPlaceholders = str_repeat('?,', count($allowedBranches) - 1) . '?';
        $sql .= " WHERE {$columnMap['delivery_branch_name']} IN ($branchPlaceholders)";
        $fallbackParams = $allowedBranches;
    }
    
    $sql .= ' ORDER BY RAND() LIMIT 1000';
    $params = $fallbackParams;
}

// Check if delivery_tracking table exists first
$tableExists = false;
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_tracking'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

$rows = [];
$debugInfo = [];

if (!$tableExists) {
    $debugInfo[] = "ตาราง delivery_tracking ไม่มีในฐานข้อมูล";
} else {
    $debugInfo[] = "ตาราง delivery_tracking มีอยู่";
    
    // Get table structure for debugging
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM delivery_tracking");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $debugInfo[] = "คอลัมน์ที่มี: " . implode(', ', $columns);
        
        // แสดงการแมปคอลัมน์
        $mappingInfo = [];
        foreach ($columnMap as $alias => $actualCol) {
            if ($actualCol) {
                $mappingInfo[] = "$alias => $actualCol";
            } else {
                $mappingInfo[] = "$alias => ไม่พบ";
            }
        }
        $debugInfo[] = "การแมปคอลัมน์: " . implode(', ', $mappingInfo);
        
        // แสดงการแมปคอลัมน์ใหม่
        $newMappingInfo = [];
        $newFields = ['delivery_employee_name', 'recipient_phone', 'delivery_branch_name', 'sign_branch'];
        foreach ($newFields as $field) {
            if ($columnMap[$field]) {
                $newMappingInfo[] = "$field => {$columnMap[$field]}";
            } else {
                $newMappingInfo[] = "$field => ไม่พบ";
            }
        }
        $debugInfo[] = "คอลัมน์เพิ่มเติม: " . implode(', ', $newMappingInfo);
        
        // Check zone and coordinate availability
        try {
            $zoneQuery = "SELECT 
                COUNT(*) as total_addresses,
                COUNT(da.zone_id) as with_zone,
                COUNT(da.latitude) as with_coordinates,
                COUNT(DISTINCT za.zone_code) as unique_zones,
                COUNT(da.recipient_name) as with_recipient_name,
                COUNT(da.recipient_phone) as with_recipient_phone
            FROM delivery_address da 
            LEFT JOIN zone_area za ON da.zone_id = za.id";
            $zoneStmt = $conn->prepare($zoneQuery);
            $zoneStmt->execute();
            $zoneStats = $zoneStmt->fetch(PDO::FETCH_ASSOC);
            
            $debugInfo[] = "สถิติข้อมูล: ที่อยู่ทั้งหมด " . $zoneStats['total_addresses'] . " รายการ, มีโซน " . $zoneStats['with_zone'] . " รายการ, มีพิกัด " . $zoneStats['with_coordinates'] . " รายการ, โซนที่แตกต่าง " . $zoneStats['unique_zones'] . " โซน";
                    $debugInfo[] = "ข้อมูลผู้รับ: มีชื่อ " . $zoneStats['with_recipient_name'] . " รายการ, มีเบอร์โทร " . $zoneStats['with_recipient_phone'] . " รายการ";
        
        // Zone area statistics
        try {
            $zoneAreaQuery = "SELECT 
                COUNT(*) as total_zones,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_zones,
                COUNT(CASE WHEN description IS NOT NULL AND description != '' THEN 1 END) as zones_with_description
            FROM zone_area";
            $zoneAreaStmt = $conn->prepare($zoneAreaQuery);
            $zoneAreaStmt->execute();
            $zoneAreaStats = $zoneAreaStmt->fetch(PDO::FETCH_ASSOC);
            
            $debugInfo[] = "โซน Area: ทั้งหมด " . $zoneAreaStats['total_zones'] . " โซน, ใช้งาน " . $zoneAreaStats['active_zones'] . " โซน, มีคำอธิบาย " . $zoneAreaStats['zones_with_description'] . " โซน";
        } catch (Exception $e) {
            $debugInfo[] = "ไม่สามารถดูสถิติ Zone Area ได้: " . $e->getMessage();
        }
        
        // Check delivery_address data availability
        if (!$hasDeliveryAddressData) {
            $debugInfo[] = "⚠️ ตาราง delivery_address ไม่มีข้อมูล - ใช้ข้อมูลจาก delivery_tracking โดยตรง";
            $debugInfo[] = "💡 ควรนำเข้าข้อมูลที่อยู่เพื่อให้มีข้อมูลโซนและพิกัด";
        }
    } catch (Exception $e) {
        $debugInfo[] = "ไม่สามารถดูสถิติโซนได้: " . $e->getMessage();
    }
    } catch (Exception $e) {
        $debugInfo[] = "ไม่สามารถดูโครงสร้างตารางได้: " . $e->getMessage();
    }
    
    // Count rows
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM delivery_tracking");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        $debugInfo[] = "จำนวนแถวในตาราง: " . $count;
    } catch (Exception $e) {
        $debugInfo[] = "ไม่สามารถนับแถวได้: " . $e->getMessage();
    }
    
    // Try to read data
    try {
        $debugInfo[] = "SQL ที่ใช้: " . $sql;
        $debugInfo[] = "Parameters: " . json_encode($params);
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo[] = "ดึงข้อมูลสำเร็จ: " . count($rows) . " แถว";
        
        // Debug: ตรวจสอบข้อมูลโซนและสถานะที่มีอยู่
        if (isset($_GET['zone_filter']) && !empty($_GET['zone_filter'])) {
            try {
                $zoneCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_address WHERE zone_id = ?");
                $zoneCheckStmt->execute([$_GET['zone_filter']]);
                $zoneCheck = $zoneCheckStmt->fetch(PDO::FETCH_ASSOC);
                $debugInfo[] = "ข้อมูลในโซน " . $_GET['zone_filter'] . ": " . $zoneCheck['count'] . " รายการ";
            } catch (Exception $e) {
                $debugInfo[] = "ไม่สามารถตรวจสอบข้อมูลโซนได้: " . $e->getMessage();
            }
        }
        
        if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
            try {
                $statusCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_address WHERE delivery_status = ?");
                $statusCheckStmt->execute([$_GET['status_filter']]);
                $statusCheck = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);
                $debugInfo[] = "ข้อมูลสถานะ " . $_GET['status_filter'] . ": " . $statusCheck['count'] . " รายการ";
            } catch (Exception $e) {
                $debugInfo[] = "ไม่สามารถตรวจสอบข้อมูลสถานะได้: " . $e->getMessage();
            }
        }
        
        if (isset($_GET['employee_filter']) && !empty($_GET['employee_filter'])) {
            try {
                $empCheckStmt = $conn->prepare("
                    SELECT 
                        COUNT(DISTINCT da.id) as delivery_count,
                        COUNT(DISTINCT zea.zone_id) as zone_count,
                        GROUP_CONCAT(DISTINCT za.zone_code) as zones
                    FROM delivery_zone_employees dze
                    LEFT JOIN zone_employee_assignments zea ON dze.id = zea.employee_id AND zea.is_active = TRUE
                    LEFT JOIN zone_area za ON zea.zone_id = za.id
                    LEFT JOIN delivery_address da ON za.id = da.zone_id
                    WHERE dze.id = ?
                ");
                $empCheckStmt->execute([$_GET['employee_filter']]);
                $empCheck = $empCheckStmt->fetch(PDO::FETCH_ASSOC);
                $debugInfo[] = "พนักงาน ID " . $_GET['employee_filter'] . ": รับผิดชอบ " . $empCheck['zone_count'] . " โซน (" . ($empCheck['zones'] ?: 'ไม่มี') . "), มีการจัดส่ง " . $empCheck['delivery_count'] . " รายการ";
            } catch (Exception $e) {
                $debugInfo[] = "ไม่สามารถตรวจสอบข้อมูลพนักงานได้: " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $debugInfo[] = "ข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
        
        // Simple fallback query
        try {
            $simple_sql = "SELECT * FROM delivery_tracking LIMIT 100";
            $stmt = $conn->prepare($simple_sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $debugInfo[] = "Fallback ดึงข้อมูลสำเร็จ: " . count($rows) . " แถว";
        } catch (Exception $e2) {
            $debugInfo[] = "Fallback ล้มเหลว: " . $e2->getMessage();
        }
    }
}
?>

<div class="fadeIn">
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">ข้อมูลที่อยู่และโซน</h1>
                <p class="text-gray-600 mt-2">แสดงข้อมูลจากตาราง <span class="font-mono bg-gray-100 px-2 py-0.5 rounded">delivery_tracking</span> พร้อมข้อมูลโซนและการจับคู่พนักงาน</p>
                
                <!-- Branch Filter Info -->
                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-filter text-blue-600"></i>
                        <span class="text-blue-800 font-medium">กรองเฉพาะสาขา:</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1 text-sm">
                        <?php foreach ($allowedBranches as $branch): ?>
                            <div class="flex items-center gap-1 text-blue-700">
                                <i class="fas fa-building text-xs"></i>
                                <span class="font-mono text-xs"><?php echo htmlspecialchars($branch); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 text-xs text-blue-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        แสดงเฉพาะข้อมูลจาก <?php echo count($allowedBranches); ?> สาขาใน NakhonSiThammarat
                    </div>
                </div>
                
                <?php if (!$hasDeliveryAddressData): ?>
                    <div class="mt-2 p-2 bg-amber-100 border border-amber-200 rounded-md">
                        <p class="text-amber-800 text-sm">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            ตาราง delivery_address ไม่มีข้อมูล - แสดงเฉพาะข้อมูลจาก delivery_tracking
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex space-x-2">
                <a href="../check_table_structure.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-search mr-2"></i>ตรวจสอบโครงสร้าง
                </a>
                <a href="../view_delivery_tracking.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-table mr-2"></i>ดูข้อมูลแบบง่าย
                </a>
                <a href="zones.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-map-marked-alt mr-2"></i>จัดการโซน
                </a>
                <a href="zone_employee_matching.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-users-cog mr-2"></i>จับคู่โซน-พนักงาน
                </a>
                <a href="../debug_data.php" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-bug mr-2"></i>Debug ข้อมูล
                </a>
                <a href="zones_enhanced.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-users-cog mr-2"></i>จัดการพนักงาน
                </a>
            </div>
        </div>
        
        <!-- Debug Information -->
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">ข้อมูลดีบัก:</h3>
            <?php foreach ($debugInfo as $info): ?>
                <p class="text-xs text-gray-600">• <?php echo htmlspecialchars($info); ?></p>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filter Form -->
    <?php if ($hasDeliveryAddressData): ?>
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-filter mr-2"></i>กรองข้อมูล
        </h3>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกโซน</label>
                <select name="zone_filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกโซน</option>
                    <?php
                    try {
                        $zoneStmt = $conn->prepare("
                            SELECT za.id, za.zone_code, za.zone_name, za.color_code,
                                   COUNT(da.id) as delivery_count
                            FROM zone_area za 
                            LEFT JOIN delivery_address da ON za.id = da.zone_id 
                            WHERE za.is_active = 1
                            GROUP BY za.id 
                            ORDER BY za.zone_code
                        ");
                        $zoneStmt->execute();
                        $zones = $zoneStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($zones as $zone) {
                            $selected = (isset($_GET['zone_filter']) && $_GET['zone_filter'] == $zone['id']) ? 'selected' : '';
                            echo '<option value="' . $zone['id'] . '" ' . $selected . '>';
                            echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['zone_code'] . ')');
                            echo ' - ' . $zone['delivery_count'] . ' รายการ';
                            echo '</option>';
                        }
                    } catch (Exception $e) {
                        // Error handled silently
                    }
                    ?>
                </select>
            </div>
            
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-2">สถานะการจัดส่ง</label>
                <select name="status_filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="assigned" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'assigned') ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                    <option value="in_transit" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'in_transit') ? 'selected' : ''; ?>>กำลังขนส่ง</option>
                    <option value="delivered" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'delivered') ? 'selected' : ''; ?>>จัดส่งแล้ว</option>
                    <option value="failed" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'failed') ? 'selected' : ''; ?>>จัดส่งไม่สำเร็จ</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-2">พนักงานจัดส่ง</label>
                <select name="employee_filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">ทุกคน</option>
                    <?php
                    try {
                        $empStmt = $conn->prepare("
                            SELECT 
                                dze.id, 
                                dze.employee_code, 
                                dze.employee_name, 
                                dze.nickname,
                                dze.position,
                                COUNT(DISTINCT zea.zone_id) as zone_count,
                                GROUP_CONCAT(DISTINCT za.zone_code ORDER BY za.zone_code) as zone_codes
                            FROM delivery_zone_employees dze 
                            LEFT JOIN zone_employee_assignments zea ON dze.id = zea.employee_id AND zea.is_active = TRUE
                            LEFT JOIN zone_area za ON zea.zone_id = za.id
                            WHERE dze.status = 'active'
                            GROUP BY dze.id 
                            ORDER BY dze.employee_name
                        ");
                        $empStmt->execute();
                        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($employees as $employee) {
                            $selected = (isset($_GET['employee_filter']) && $_GET['employee_filter'] == $employee['id']) ? 'selected' : '';
                            $displayName = $employee['employee_name'];
                            if ($employee['nickname']) {
                                $displayName .= ' (' . $employee['nickname'] . ')';
                            }
                            $displayName .= ' [' . $employee['employee_code'] . ']';
                            if ($employee['zone_codes']) {
                                $displayName .= ' - โซน: ' . $employee['zone_codes'];
                            }
                            echo '<option value="' . $employee['id'] . '" ' . $selected . '>';
                            echo htmlspecialchars($displayName);
                            echo '</option>';
                        }
                    } catch (Exception $e) {
                        // Error handled silently
                    }
                    ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm">
                    <i class="fas fa-search mr-2"></i>กรอง
                </button>
                <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm">
                    <i class="fas fa-times mr-2"></i>ล้าง
                </a>
            </div>
        </form>
        
        <?php if (isset($_GET['zone_filter']) || isset($_GET['status_filter'])): ?>
            <div class="mt-4 p-3 bg-blue-50 rounded-md">
                <div class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    กำลังแสดงผลการกรอง: 
                    <?php if (isset($_GET['zone_filter']) && !empty($_GET['zone_filter'])): ?>
                        <span class="font-medium">โซน ID: <?php echo htmlspecialchars($_GET['zone_filter']); ?></span>
                        <?php
                        // หาชื่อโซน
                        try {
                            $zoneNameStmt = $conn->prepare("SELECT zone_code, zone_name FROM zone_area WHERE id = ?");
                            $zoneNameStmt->execute([$_GET['zone_filter']]);
                            $zoneInfo = $zoneNameStmt->fetch(PDO::FETCH_ASSOC);
                            if ($zoneInfo) {
                                echo " (" . htmlspecialchars($zoneInfo['zone_code'] . " - " . $zoneInfo['zone_name']) . ")";
                            }
                        } catch (Exception $e) {}
                        ?>
                    <?php endif; ?>
                    <?php if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])): ?>
                        <span class="font-medium">สถานะ: <?php echo htmlspecialchars($_GET['status_filter']); ?></span>
                    <?php endif; ?>
                    <?php if (isset($_GET['employee_filter']) && !empty($_GET['employee_filter'])): ?>
                        <span class="font-medium">พนักงาน ID: <?php echo htmlspecialchars($_GET['employee_filter']); ?></span>
                        <?php
                        // หาชื่อพนักงาน
                        try {
                            $empNameStmt = $conn->prepare("SELECT employee_name, nickname, employee_code FROM delivery_zone_employees WHERE id = ?");
                            $empNameStmt->execute([$_GET['employee_filter']]);
                            $empInfo = $empNameStmt->fetch(PDO::FETCH_ASSOC);
                            if ($empInfo) {
                                $empDisplay = $empInfo['employee_name'];
                                if ($empInfo['nickname']) {
                                    $empDisplay .= ' (' . $empInfo['nickname'] . ')';
                                }
                                $empDisplay .= ' [' . $empInfo['employee_code'] . ']';
                                echo " (" . htmlspecialchars($empDisplay) . ")";
                            }
                        } catch (Exception $e) {}
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-4 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="border-b bg-gray-50">
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-map-marked-alt mr-1"></i>โซนจัดส่ง
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-user mr-1"></i>ผู้รับ
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-phone mr-1"></i>เบอร์ผู้รับ
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-map-marker-alt mr-1"></i>ที่อยู่ผู้รับ
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-user-tie mr-1"></i>พนักงานนำจ่าย
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-building mr-1"></i>สาขา
                    </th>
                    <th class="text-left px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-crosshairs mr-1"></i>พิกัด (Lat, Lng)
                    </th>
                    <th class="text-right px-3 py-2 text-sm text-gray-700">
                        <i class="fas fa-external-link-alt mr-1"></i>แผนที่
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                            <div class="space-y-2">
                                <i class="fas fa-search text-4xl text-gray-400"></i>
                                <p class="text-lg">ไม่พบข้อมูลตามเงื่อนไขที่กรอง</p>
                                <?php if (isset($_GET['zone_filter']) || isset($_GET['status_filter'])): ?>
                                    <p class="text-sm">
                                        ลองปรับเงื่อนไขการกรอง หรือ 
                                        <a href="?" class="text-blue-600 hover:text-blue-800">ดูข้อมูลทั้งหมด</a>
                                    </p>
                                    <p class="text-sm">
                                        <a href="../debug_data.php" class="text-orange-600 hover:text-orange-800">
                                            <i class="fas fa-bug mr-1"></i>ตรวจสอบข้อมูลในฐานข้อมูล
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="text-sm">ไม่มีข้อมูลในระบบ</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php 
                            $lat = isset($r['lat']) ? (float)$r['lat'] : null; 
                            $lng = isset($r['lng']) ? (float)$r['lng'] : null; 
                            $hasCoord = $lat !== 0.0 && $lng !== 0.0 && !is_null($lat) && !is_null($lng);
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm text-gray-800 whitespace-nowrap">
                                <?php 
                                // Use enhanced zone data if available, fallback to tracking data
                                $displayZoneCode = $r['enhanced_zone_code'] ?? $r['zone_code'] ?? '';
                                $displayZoneName = $r['enhanced_zone_name'] ?? $r['zone_name'] ?? '';
                                $zoneColor = $r['zone_color'] ?? '';
                                $zoneDescription = $r['zone_description'] ?? '';
                                $zoneIsActive = $r['zone_is_active'] ?? 1;
                                $zoneId = $r['zone_id'] ?? null;
                                
                                if ($displayZoneCode || $displayZoneName) {
                                    echo '<div class="space-y-1">';
                                    
                                    // Main zone info
                                    echo '<div class="flex items-center">';
                                    if ($zoneColor) {
                                        echo '<div class="w-3 h-3 rounded-full mr-2 flex-shrink-0" style="background-color: ' . htmlspecialchars($zoneColor) . '"></div>';
                                    }
                                    echo '<span class="font-medium">' . htmlspecialchars(($displayZoneCode ? $displayZoneCode.' - ' : '') . ($displayZoneName ?: '-')) . '</span>';
                                    
                                    // Zone status
                                    if (!$zoneIsActive) {
                                        echo '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ไม่ใช้งาน</span>';
                                    }
                                    echo '</div>';
                                    
                                    // Zone description
                                    if ($zoneDescription) {
                                        echo '<div class="text-xs text-gray-500">';
                                        echo '<i class="fas fa-info-circle mr-1"></i>' . htmlspecialchars($zoneDescription);
                                        echo '</div>';
                                    }
                                    
                                    // Zone employees from JOIN (direct from current query)
                                    $zoneEmployees = $r['zone_employees'] ?? '';
                                    $employeeCodes = $r['employee_codes'] ?? '';
                                    $employeePositions = $r['employee_positions'] ?? '';
                                    $employeePhones = $r['employee_phones'] ?? '';
                                    $employeeCount = $r['employee_count'] ?? 0;
                                    
                                    // Zone delivery statistics
                                    $zoneTotalDeliveries = $r['zone_total_deliveries'] ?? 0;
                                    $zonePendingDeliveries = $r['zone_pending_deliveries'] ?? 0;
                                    $zoneDeliveredCount = $r['zone_delivered_count'] ?? 0;
                                    $zoneFailedCount = $r['zone_failed_count'] ?? 0;
                                    
                                    if ($zoneTotalDeliveries > 0) {
                                        echo '<div class="text-xs text-gray-500 mb-2">';
                                        echo '<div class="flex items-center space-x-2">';
                                        echo '<span><i class="fas fa-box mr-1 text-gray-400"></i>' . $zoneTotalDeliveries . ' รายการ</span>';
                                        if ($zonePendingDeliveries > 0) {
                                            echo '<span class="text-orange-600"><i class="fas fa-clock mr-1"></i>' . $zonePendingDeliveries . ' รอ</span>';
                                        }
                                        if ($zoneDeliveredCount > 0) {
                                            echo '<span class="text-green-600"><i class="fas fa-check mr-1"></i>' . $zoneDeliveredCount . ' สำเร็จ</span>';
                                        }
                                        if ($zoneFailedCount > 0) {
                                            echo '<span class="text-red-600"><i class="fas fa-times mr-1"></i>' . $zoneFailedCount . ' ล้มเหลว</span>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    
                                    if ($employeeCount > 0 && $zoneEmployees) {
                                        echo '<div class="text-xs text-gray-500 space-y-1">';
                                        
                                        // Employee count and names
                                        echo '<div class="flex items-start">';
                                        echo '<i class="fas fa-users mr-1 mt-0.5 text-blue-500"></i>';
                                        echo '<div>';
                                        echo '<span class="font-medium text-blue-600">' . $employeeCount . ' พนักงานจัดส่ง:</span><br>';
                                        
                                        // Split employees for better display
                                        $employees = explode(', ', $zoneEmployees);
                                        $codes = explode(', ', $employeeCodes);
                                        $positions = explode(', ', $employeePositions);
                                        $phones = explode(', ', $employeePhones);
                                        
                                        foreach ($employees as $index => $employee) {
                                            echo '<div class="ml-2 mb-1">';
                                            echo '<span class="text-gray-700">' . htmlspecialchars($employee) . '</span>';
                                            
                                            if (isset($codes[$index]) && $codes[$index]) {
                                                echo ' <span class="text-gray-400 font-mono text-xs">[' . htmlspecialchars($codes[$index]) . ']</span>';
                                            }
                                            
                                            if (isset($positions[$index]) && $positions[$index]) {
                                                echo ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">' . htmlspecialchars($positions[$index]) . '</span>';
                                            }
                                            
                                            if (isset($phones[$index]) && $phones[$index]) {
                                                echo '<br><a href="tel:' . htmlspecialchars($phones[$index]) . '" class="text-blue-600 hover:text-blue-800 text-xs">';
                                                echo '<i class="fas fa-phone mr-1"></i>' . htmlspecialchars($phones[$index]);
                                                echo '</a>';
                                            }
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    
                                    // Zone statistics (like zones.php) - if needed
                                    if ($zoneId && !$zoneEmployees) {
                                        $zoneStats = getZoneStatistics($conn, $zoneId);
                                        if ($zoneStats && $zoneStats['employee_count'] > 0) {
                                            echo '<div class="text-xs text-gray-500">';
                                            echo '<i class="fas fa-users mr-1"></i>' . $zoneStats['employee_count'] . ' พนักงาน';
                                            if ($zoneStats['assigned_employees']) {
                                                echo '<br><small class="text-gray-400">' . htmlspecialchars(substr($zoneStats['assigned_employees'], 0, 50)) . (strlen($zoneStats['assigned_employees']) > 50 ? '...' : '') . '</small>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // Zone area bounds
                                    if (isset($r['zone_center_lat']) && isset($r['zone_center_lng']) && $r['zone_center_lat'] && $r['zone_center_lng']) {
                                        echo '<div class="text-xs text-gray-500">';
                                        echo '<i class="fas fa-crosshairs mr-1"></i>ศูนย์กลาง: ';
                                        echo number_format($r['zone_center_lat'], 4) . ', ' . number_format($r['zone_center_lng'], 4);
                                        echo '</div>';
                                    }
                                    
                                                        // Link to zone management
                    if ($displayZoneCode) {
                        echo '<div class="mt-1 space-x-2">';
                        echo '<a href="zones.php?edit=' . ($zoneId ?: '') . '" class="text-xs text-blue-600 hover:text-blue-800">';
                        echo '<i class="fas fa-external-link-alt mr-1"></i>จัดการโซน';
                        echo '</a>';
                        
                        // Link to zone employees if filtered by employee
                        if (isset($_GET['employee_filter']) && !empty($_GET['employee_filter'])) {
                            echo '<a href="zones_enhanced.php?zone_id=' . ($zoneId ?: '') . '" class="text-xs text-indigo-600 hover:text-indigo-800">';
                            echo '<i class="fas fa-users mr-1"></i>ดูพนักงาน';
                            echo '</a>';
                        }
                        echo '</div>';
                    }
                                    
                                    echo '</div>';
                                } else {
                                    echo '<div class="text-gray-400 text-center">';
                                    echo '<i class="fas fa-map-marked-alt mb-1"></i><br>';
                                    echo '<span class="text-xs">ไม่มีโซน</span>';
                                    echo '</div>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-800 whitespace-nowrap">
                                <div>
                                    <?php 
                                    // Use enhanced recipient name if available, fallback to tracking data
                                    $displayRecipientName = $r['enhanced_recipient_name'] ?? $r['recipient_name'] ?? '';
                                    if ($displayRecipientName) {
                                        echo '<i class="fas fa-user mr-1 text-blue-500"></i>';
                                        echo htmlspecialchars($displayRecipientName);
                                    } else {
                                        echo '<span class="text-gray-400">ไม่ระบุชื่อ</span>';
                                    }
                                    ?>
                                </div>
                                <?php if (!empty($r['awb_number'])): ?>
                                    <small class="text-gray-500 font-mono">
                                        <i class="fas fa-barcode mr-1"></i><?php echo htmlspecialchars($r['awb_number']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-800 whitespace-nowrap">
                                <?php 
                                // Use enhanced phone if available, fallback to tracking data
                                $phone = $r['enhanced_recipient_phone'] ?? $r['recipient_phone'] ?? '';
                                if ($phone) {
                                    echo '<a href="tel:' . htmlspecialchars($phone) . '" class="text-blue-600 hover:text-blue-800 flex items-center">';
                                    echo '<i class="fas fa-phone mr-1"></i>' . htmlspecialchars($phone);
                                    echo '</a>';
                                } else {
                                    echo '<span class="text-gray-400">ไม่มีเบอร์</span>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-700">
                                <?php 
                                // Use full address if available, fallback to tracking address
                                $displayAddress = $r['full_address'] ?? $r['address'] ?? '';
                                $location_parts = [];
                                if ($r['subdistrict']) $location_parts[] = $r['subdistrict'];
                                if ($r['district']) $location_parts[] = $r['district'];
                                if ($r['province']) $location_parts[] = $r['province'];
                                
                                echo htmlspecialchars($displayAddress ?: '-');
                                if (!empty($location_parts)) {
                                    echo '<br><small class="text-gray-500">' . htmlspecialchars(implode(', ', $location_parts)) . '</small>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-800 whitespace-nowrap">
                                <?php 
                                $deliveryEmployee = $r['delivery_employee_name'] ?? '';
                                if ($deliveryEmployee) {
                                    echo '<div class="flex items-center">';
                                    echo '<i class="fas fa-user-tie mr-2 text-blue-500"></i>';
                                    echo '<span>' . htmlspecialchars($deliveryEmployee) . '</span>';
                                    echo '</div>';
                                } else {
                                    echo '<span class="text-gray-400">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-800">
                                <?php 
                                $deliveryBranch = $r['delivery_branch_name'] ?? '';
                                $signBranch = $r['sign_branch'] ?? '';
                                
                                if ($deliveryBranch || $signBranch) {
                                    echo '<div class="space-y-1">';
                                    if ($deliveryBranch) {
                                        echo '<div class="flex items-center text-xs">';
                                        echo '<i class="fas fa-shipping-fast mr-1 text-orange-500"></i>';
                                        echo '<span class="font-medium">นำจ่าย:</span> ' . htmlspecialchars($deliveryBranch);
                                        echo '</div>';
                                    }
                                    if ($signBranch) {
                                        echo '<div class="flex items-center text-xs">';
                                        echo '<i class="fas fa-pen-fancy mr-1 text-green-500"></i>';
                                        echo '<span class="font-medium">เซ็นรับ:</span> ' . htmlspecialchars($signBranch);
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<span class="text-gray-400">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-sm font-mono text-gray-800 whitespace-nowrap">
                                <?php if ($hasCoord): ?>
                                    <div class="text-blue-600">
                                        <?php echo number_format($lat, 6).', '.number_format($lng, 6); ?>
                                    </div>
                                    <small class="text-gray-500">
                                        <?php echo abs($lat) . '°' . ($lat >= 0 ? 'N' : 'S') . ', ' . abs($lng) . '°' . ($lng >= 0 ? 'E' : 'W'); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-gray-400">ไม่มีพิกัด</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <?php if ($hasCoord): ?>
                                    <div class="space-y-1">
                                        <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?php echo $lat; ?>,<?php echo $lng; ?>" class="inline-flex items-center px-2 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 mb-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i> Google Maps
                                        </a>
                                        <br>
                                        <a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?php echo $lat; ?>&mlon=<?php echo $lng; ?>&zoom=16" class="inline-flex items-center px-2 py-1 rounded bg-green-600 text-white text-xs hover:bg-green-700">
                                            <i class="fas fa-globe mr-1"></i> OSM
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">ไม่มีพิกัด</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


