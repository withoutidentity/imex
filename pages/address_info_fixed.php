<?php
$page_title = 'ข้อมูลที่อยู่จาก Tracking';
require_once '../config/config.php';
include '../includes/header.php';

$rows = [];
$debugInfo = [];

// Check if delivery_tracking table exists first
$tableExists = false;
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_tracking'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

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
        
        // ตรวจหาคอลัมน์ที่สำคัญ
        $awbColumn = null;
        $recipientColumn = null;
        $addressColumn = null;
        $zoneCodeColumn = null;
        $zoneNameColumn = null;
        
        foreach ($columns as $col) {
            if (strtolower($col) === 'awb' || stripos($col, 'awb') !== false) {
                $awbColumn = $col;
            }
            if (stripos($col, 'ผู้รับ') !== false) {
                $recipientColumn = $col;
            }
            if (stripos($col, 'ที่อยู่ผู้รับ') !== false) {
                $addressColumn = $col;
            }
            if (stripos($col, 'รหัสเขต') !== false) {
                $zoneCodeColumn = $col;
            }
            if (stripos($col, 'ชื่อเขต') !== false) {
                $zoneNameColumn = $col;
            }
        }
        
        $debugInfo[] = "คอลัมน์ AWB: " . ($awbColumn ?: 'ไม่พบ');
        $debugInfo[] = "คอลัมน์ผู้รับ: " . ($recipientColumn ?: 'ไม่พบ');
        $debugInfo[] = "คอลัมน์ที่อยู่: " . ($addressColumn ?: 'ไม่พบ');
        
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
    
    // Try to read data with simple query first
    try {
        $selectFields = [];
        
        // เลือกคอลัมน์ที่มีอยู่
        if ($awbColumn) $selectFields[] = "`{$awbColumn}` AS awb_number";
        if ($recipientColumn) $selectFields[] = "`{$recipientColumn}` AS recipient_name";
        if ($addressColumn) $selectFields[] = "`{$addressColumn}` AS address";
        if ($zoneCodeColumn) $selectFields[] = "`{$zoneCodeColumn}` AS zone_code";
        if ($zoneNameColumn) $selectFields[] = "`{$zoneNameColumn}` AS zone_name";
        
        // เพิ่มคอลัมน์พื้นฐาน
        $selectFields[] = "NULL AS lat";
        $selectFields[] = "NULL AS lng";
        
        if (empty($selectFields)) {
            // ถ้าไม่พบคอลัมน์ที่ต้องการ ให้ใช้คอลัมน์ทั้งหมด
            $sql = "SELECT * FROM delivery_tracking LIMIT 100";
        } else {
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM delivery_tracking LIMIT 100";
        }
        
        $debugInfo[] = "SQL ที่ใช้: " . $sql;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo[] = "ดึงข้อมูลสำเร็จ: " . count($rows) . " แถว";
        
        // ตรวจสอบโครงสร้างข้อมูลที่ได้
        if (!empty($rows)) {
            $debugInfo[] = "คีย์ข้อมูลที่ได้: " . implode(', ', array_keys($rows[0]));
        }
        
    } catch (Exception $e) {
        $debugInfo[] = "ข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
        
        // Simple fallback query
        try {
            $simple_sql = "SELECT * FROM delivery_tracking LIMIT 20";
            $stmt = $conn->prepare($simple_sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $debugInfo[] = "Fallback ดึงข้อมูลสำเร็จ: " . count($rows) . " แถว";
            
            if (!empty($rows)) {
                $debugInfo[] = "คีย์ข้อมูล Fallback: " . implode(', ', array_keys($rows[0]));
            }
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
                <h1 class="text-2xl font-bold text-gray-800">ข้อมูลที่อยู่จาก Tracking</h1>
                <p class="text-gray-600 mt-2">แสดงข้อมูลจากตาราง <span class="font-mono bg-gray-100 px-2 py-0.5 rounded">delivery_tracking</span></p>
            </div>
            <div class="flex space-x-2">
                <a href="../check_table_structure.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-search mr-2"></i>ตรวจสอบโครงสร้าง
                </a>
                <a href="../view_delivery_tracking.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-table mr-2"></i>ดูข้อมูลแบบง่าย
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

    <div class="bg-white p-4 rounded-lg shadow-md overflow-x-auto">
        <?php if (empty($rows)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-exclamation-triangle text-4xl mb-4 text-yellow-500"></i>
                <h3 class="text-lg font-medium mb-2">ไม่สามารถดึงข้อมูลได้</h3>
                <p>ตารางมีโครงสร้างที่แตกต่างจากที่คาดหวัง</p>
                <div class="mt-4">
                    <a href="../check_table_structure.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        ตรวจสอบโครงสร้างตาราง
                    </a>
                </div>
            </div>
        <?php else: ?>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">ข้อมูลจากตาราง delivery_tracking</h2>
            
            <table class="min-w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <?php if (isset($rows[0])): ?>
                            <?php foreach (array_keys($rows[0]) as $header): ?>
                                <th class="text-left px-3 py-2 text-sm text-gray-700"><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <?php foreach ($r as $key => $value): ?>
                                <td class="px-3 py-2 text-sm text-gray-800">
                                    <?php 
                                    $displayValue = $value;
                                    if (strlen($displayValue) > 50) {
                                        $displayValue = substr($displayValue, 0, 50) . '...';
                                    }
                                    echo htmlspecialchars($displayValue ?: '-'); 
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


