<?php
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>ตรวจสอบโครงสร้างตาราง delivery_tracking</title>";
echo "<style>body{font-family: Arial, sans-serif; margin: 20px;} table{border-collapse: collapse; width: 100%;} th,td{border: 1px solid #ddd; padding: 8px; text-align: left;} th{background-color: #f2f2f2;} .code{background: #f8f9fa; padding: 10px; border: 1px solid #ddd; margin: 10px 0; font-family: monospace;}</style>";
echo "</head><body>";
echo "<h1>ตรวจสอบโครงสร้างตาราง delivery_tracking</h1>";

try {
    // ตรวจสอบโครงสร้างตาราง
    echo "<h2>โครงสร้างตาราง</h2>";
    $stmt = $conn->prepare("DESCRIBE delivery_tracking");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ลำดับ</th><th>ชื่อคอลัมน์</th><th>ประเภทข้อมูล</th><th>NULL</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $index => $column) {
        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // ข้อมูลตัวอย่าง
    echo "<h2>ข้อมูลตัวอย่าง (3 แถวแรก)</h2>";
    $stmt = $conn->prepare("SELECT * FROM delivery_tracking LIMIT 3");
    $stmt->execute();
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($samples)) {
        echo "<div style='overflow-x: auto;'>";
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($samples[0]) as $columnName) {
            echo "<th>" . htmlspecialchars($columnName) . "</th>";
        }
        echo "</tr>";
        
        foreach ($samples as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars(substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '')) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    // สร้างคำสั่ง SQL สำหรับดึงข้อมูล
    echo "<h2>คำสั่ง SQL ที่แนะนำ</h2>";
    echo "<div class='code'>";
    echo "SELECT<br>";
    $columnNames = array_column($columns, 'Field');
    foreach ($columnNames as $index => $columnName) {
        $escaped = "`" . str_replace("`", "``", $columnName) . "`";
        echo "&nbsp;&nbsp;" . $escaped;
        if ($index < count($columnNames) - 1) {
            echo ",";
        }
        echo "<br>";
    }
    echo "FROM delivery_tracking<br>";
    echo "ORDER BY ";
    
    // หา column ที่เหมาะสมสำหรับ ORDER BY
    $timeColumns = ['เวลาที่เซ็นรับพัสดุ', 'เวลาการบันทึกเซ็นรับพัสดุ', 'เวลาเกทเวย์นำส่ง'];
    $foundTimeColumn = null;
    foreach ($timeColumns as $timeCol) {
        if (in_array($timeCol, $columnNames)) {
            $foundTimeColumn = $timeCol;
            break;
        }
    }
    
    if ($foundTimeColumn) {
        echo "`" . str_replace("`", "``", $foundTimeColumn) . "` DESC";
    } else {
        // ใช้คอลัมน์แรกแทน
        echo "`" . str_replace("`", "``", $columnNames[0]) . "`";
    }
    echo "<br>LIMIT 50;";
    echo "</div>";
    
    // แสดงจำนวนข้อมูล
    echo "<h2>สถิติข้อมูล</h2>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM delivery_tracking");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p><strong>จำนวนแถวทั้งหมด:</strong> " . number_format($total) . " แถว</p>";
    
    // ตรวจสอบคอลัมน์ที่มี AWB
    $awbColumn = null;
    foreach ($columnNames as $col) {
        if (stripos($col, 'awb') !== false || $col === 'AWB') {
            $awbColumn = $col;
            break;
        }
    }
    
    if ($awbColumn) {
        echo "<p><strong>คอลัมน์ AWB:</strong> " . htmlspecialchars($awbColumn) . "</p>";
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT `" . str_replace("`", "``", $awbColumn) . "`) as unique_awb FROM delivery_tracking");
        $stmt->execute();
        $uniqueAwb = $stmt->fetch(PDO::FETCH_ASSOC)['unique_awb'];
        echo "<p><strong>จำนวน AWB ที่ไม่ซ้ำ:</strong> " . number_format($uniqueAwb) . " รายการ</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>


