<?php
// ทดสอบการติดตั้งตาราง delivery_tracking
require_once 'config/config.php';

echo "=== ทดสอบการติดตั้งตาราง delivery_tracking ===\n";

try {
    // ตรวจสอบการเชื่อมต่อฐานข้อมูล
    echo "1. ตรวจสอบการเชื่อมต่อฐานข้อมูล...\n";
    $stmt = $conn->prepare("SELECT DATABASE() as db_name");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ เชื่อมต่อฐานข้อมูลสำเร็จ: " . $result['db_name'] . "\n";
    
    // อ่านไฟล์ SQL
    echo "2. อ่านไฟล์ SQL Schema...\n";
    $sql_content = file_get_contents('database/delivery_tracking_schema.sql');
    
    if ($sql_content === false) {
        throw new Exception("ไม่สามารถอ่านไฟล์ database/delivery_tracking_schema.sql ได้");
    }
    
    echo "   ✓ อ่านไฟล์ SQL สำเร็จ (" . strlen($sql_content) . " ตัวอักษร)\n";
    
    // แยก SQL statements
    $statements = explode(';', $sql_content);
    
    echo "3. ดำเนินการติดตั้ง...\n";
    
    $executed = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // ข้าม statement ว่างและ comment
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, '/*') === 0) {
            continue;
        }
        
        // แสดงข้อมูลการดำเนินการ
        $short_statement = substr($statement, 0, 50) . (strlen($statement) > 50 ? '...' : '');
        echo "   กำลังดำเนินการ: " . $short_statement . "\n";
        
        try {
            $conn->exec($statement);
            $executed++;
            echo "   ✓ สำเร็จ\n";
        } catch (PDOException $e) {
            // ตรวจสอบข้อผิดพลาดที่สามารถข้ามได้
            $skip_errors = [
                'already exists',
                'Duplicate entry',
                'Duplicate key name',
                "Can't DROP",
                "doesn't exist",
                'Unknown table',
                'Duplicate column name'
            ];
            
            $should_skip = false;
            foreach ($skip_errors as $error_pattern) {
                if (strpos($e->getMessage(), $error_pattern) !== false) {
                    $should_skip = true;
                    $skipped++;
                    echo "   ⚠ ข้าม: " . $e->getMessage() . "\n";
                    break;
                }
            }
            
            if (!$should_skip) {
                $errors[] = $e->getMessage();
                echo "   ✗ ข้อผิดพลาด: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== สรุปผลการติดตั้ง ===\n";
    echo "คำสั่งที่ดำเนินการสำเร็จ: " . $executed . " คำสั่ง\n";
    echo "คำสั่งที่ข้าม: " . $skipped . " คำสั่ง\n";
    
    if (!empty($errors)) {
        echo "ข้อผิดพลาดที่ร้ายแรง: " . count($errors) . " รายการ\n";
        foreach ($errors as $error) {
            echo "- " . $error . "\n";
        }
    } else {
        echo "สถานะ: ติดตั้งเสร็จสมบูรณ์!\n";
    }
    
    // ตรวจสอบว่าตารางสร้างสำเร็จหรือไม่
    echo "\n=== ตรวจสอบโครงสร้างตาราง ===\n";
    
    try {
        $stmt = $conn->prepare("DESCRIBE delivery_tracking");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✓ ตาราง delivery_tracking สร้างสำเร็จ\n";
        echo "จำนวนคอลัมน์: " . count($columns) . " คอลัมน์\n";
        
        // แสดงรายละเอียดคอลัมน์สำคัญ
        echo "คอลัมน์หลัก:\n";
        foreach ($columns as $column) {
            if (in_array($column['Field'], ['id', 'tracking_id', 'awb_number', 'current_status', 'priority_level', 'cod_status'])) {
                echo "- " . $column['Field'] . " - " . $column['Type'] . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ ไม่สามารถตรวจสอบโครงสร้างตารางได้: " . $e->getMessage() . "\n";
    }
    
    // ตรวจสอบข้อมูลที่ติดตั้ง
    echo "\n=== ตรวจสอบข้อมูลตัวอย่าง ===\n";
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_tracking");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "✓ จำนวนข้อมูลตัวอย่าง: " . $count . " รายการ\n";
        
        if ($count > 0) {
            $stmt = $conn->prepare("SELECT current_status, COUNT(*) as count FROM delivery_tracking GROUP BY current_status ORDER BY count DESC");
            $stmt->execute();
            $status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "สถิติตามสถานะ:\n";
            foreach ($status_stats as $stat) {
                echo "- " . $stat['current_status'] . ": " . $stat['count'] . " รายการ\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ ไม่สามารถตรวจสอบข้อมูลได้: " . $e->getMessage() . "\n";
    }
    
    // ตรวจสอบ View
    echo "\n=== ตรวจสอบ View ===\n";
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM view_delivery_tracking_analysis");
        $stmt->execute();
        $view_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "✓ View view_delivery_tracking_analysis สร้างสำเร็จ\n";
        echo "จำนวนข้อมูลใน View: " . $view_count . " รายการ\n";
        
    } catch (Exception $e) {
        echo "✗ View ไม่สามารถใช้งานได้: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== เสร็จสิ้นการทดสอบ ===\n";
    
} catch (Exception $e) {
    echo "ข้อผิดพลาดร้ายแรง: " . $e->getMessage() . "\n";
}
?> 