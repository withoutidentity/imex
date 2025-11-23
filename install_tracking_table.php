<?php
// Install delivery_tracking table and sample data
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Install Delivery Tracking Table</title>";
echo "<style>body{font-family: Arial, sans-serif; margin: 20px;} .success{color: green;} .error{color: red;} .warning{color: orange;} .info{color: blue;} .box{background: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin: 10px 0; border-radius: 5px;}</style>";
echo "</head><body>";
echo "<h1>การติดตั้งตาราง delivery_tracking</h1>";

try {
    // ตรวจสอบการเชื่อมต่อฐานข้อมูล
    echo "<div class='box'>";
    echo "<h2>ตรวจสอบการเชื่อมต่อฐานข้อมูล</h2>";
    $stmt = $conn->prepare("SELECT DATABASE() as db_name");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='success'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ: " . $result['db_name'] . "</p>";
    echo "</div>";
    
    // อ่านไฟล์ SQL
    echo "<div class='box'>";
    echo "<h2>อ่านไฟล์ SQL Schema</h2>";
    $sql_content = file_get_contents('database/delivery_tracking_schema.sql');
    
    if ($sql_content === false) {
        throw new Exception("ไม่สามารถอ่านไฟล์ database/delivery_tracking_schema.sql ได้");
    }
    
    echo "<p class='success'>✓ อ่านไฟล์ SQL สำเร็จ (" . strlen($sql_content) . " ตัวอักษร)</p>";
    echo "</div>";
    
    // แยก SQL statements
    $statements = explode(';', $sql_content);
    
    echo "<div class='box'>";
    echo "<h2>ดำเนินการติดตั้ง</h2>";
    
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
        $short_statement = substr($statement, 0, 60) . (strlen($statement) > 60 ? '...' : '');
        echo "<p class='info'>กำลังดำเนินการ: " . htmlspecialchars($short_statement) . "</p>";
        
        try {
            $conn->exec($statement);
            $executed++;
            echo "<p class='success'>✓ สำเร็จ</p>";
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
                    echo "<p class='warning'>⚠ ข้าม: " . htmlspecialchars($e->getMessage()) . "</p>";
                    break;
                }
            }
            
            if (!$should_skip) {
                $errors[] = $e->getMessage();
                echo "<p class='error'>✗ ข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "</div>";
    
    // สรุปผลการติดตั้ง
    echo "<div class='box'>";
    echo "<h2>สรุปผลการติดตั้ง</h2>";
    echo "<p><strong>คำสั่งที่ดำเนินการสำเร็จ:</strong> " . $executed . " คำสั่ง</p>";
    echo "<p><strong>คำสั่งที่ข้าม:</strong> " . $skipped . " คำสั่ง</p>";
    
    if (!empty($errors)) {
        echo "<p><strong>ข้อผิดพลาดที่ร้ายแรง:</strong> " . count($errors) . " รายการ</p>";
        foreach ($errors as $error) {
            echo "<p class='error'>- " . htmlspecialchars($error) . "</p>";
        }
    } else {
        echo "<p class='success'><strong>สถานะ:</strong> ติดตั้งเสร็จสมบูรณ์!</p>";
    }
    echo "</div>";
    
    // ตรวจสอบว่าตารางสร้างสำเร็จหรือไม่
    echo "<div class='box'>";
    echo "<h2>ตรวจสอบโครงสร้างตาราง</h2>";
    
    try {
        $stmt = $conn->prepare("DESCRIBE delivery_tracking");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✓ ตาราง delivery_tracking สร้างสำเร็จ</p>";
        echo "<p><strong>จำนวนคอลัมน์:</strong> " . count($columns) . " คอลัมน์</p>";
        
        // แสดงรายละเอียดคอลัมน์สำคัญ
        echo "<h3>คอลัมน์หลัก:</h3>";
        echo "<ul>";
        foreach ($columns as $column) {
            if (in_array($column['Field'], ['id', 'tracking_id', 'awb_number', 'current_status', 'priority_level', 'cod_status'])) {
                echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
            }
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ ไม่สามารถตรวจสอบโครงสร้างตารางได้: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ตรวจสอบข้อมูลที่ติดตั้ง
    echo "<div class='box'>";
    echo "<h2>ตรวจสอบข้อมูลตัวอย่าง</h2>";
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_tracking");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p class='success'>✓ จำนวนข้อมูลตัวอย่าง: <strong>" . $count . "</strong> รายการ</p>";
        
        if ($count > 0) {
            $stmt = $conn->prepare("SELECT current_status, COUNT(*) as count FROM delivery_tracking GROUP BY current_status ORDER BY count DESC");
            $stmt->execute();
            $status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>สถิติตามสถานะ:</h3>";
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>สถานะ</th><th>จำนวน</th></tr>";
            foreach ($status_stats as $stat) {
                echo "<tr><td>" . htmlspecialchars($stat['current_status']) . "</td><td>" . $stat['count'] . "</td></tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ ไม่สามารถตรวจสอบข้อมูลได้: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ตรวจสอบ View
    echo "<div class='box'>";
    echo "<h2>ตรวจสอบ View</h2>";
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM view_delivery_tracking_analysis");
        $stmt->execute();
        $view_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p class='success'>✓ View view_delivery_tracking_analysis สร้างสำเร็จ</p>";
        echo "<p>จำนวนข้อมูลใน View: <strong>" . $view_count . "</strong> รายการ</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ View ไม่สามารถใช้งานได้: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ลิงก์ไปยังหน้าต่างๆ
    echo "<div class='box'>";
    echo "<h2>ลิงก์ต่างๆ</h2>";
    echo "<a href='pages/delivery_tracking_analysis.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>ไปที่หน้าวิเคราะห์ข้อมูล</a>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>กลับหน้าหลัก</a>";
    echo "<a href='install_tracking_table.php' style='display: inline-block; padding: 10px 20px; background: #ffc107; color: black; text-decoration: none; border-radius: 5px;'>ติดตั้งใหม่</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box'>";
    echo "<h2>ข้อผิดพลาดร้ายแรง</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?> 