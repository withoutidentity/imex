<?php
// Setup Zone Employee System
require_once 'config/config.php';

echo "<h1>Setup Zone Employee Management System</h1>\n";

try {
    // Apply zone employee schema
    echo "<h2>Applying Zone Employee Schema...</h2>\n";
    
    $schema_sql = file_get_contents('database/zone_employee_simple.sql');
    
    // Split and execute SQL statements
    $statements = explode(';', $schema_sql);
    $executed = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements, comments, and delimiter statements
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, '/*') === 0 ||
            strpos($statement, 'DELIMITER') !== false ||
            strpos($statement, 'BEGIN') !== false ||
            strpos($statement, 'END') !== false ||
            trim($statement) === 'END' ||
            trim($statement) === 'BEGIN') {
            continue;
        }
        
        try {
            $conn->exec($statement);
            $executed++;
            echo "<div style='color: green;'>✓ Executed: " . substr($statement, 0, 80) . "...</div>\n";
        } catch (PDOException $e) {
            // Check for ignorable errors
            $ignorable_errors = [
                'already exists',
                'Duplicate column name',
                'Duplicate key name',
                'Duplicate entry',
                'Duplicate index',
                'RESIGNAL when handler not active',
                'Undeclared variable'
            ];
            
            $should_skip = false;
            foreach ($ignorable_errors as $ignore_pattern) {
                if (strpos($e->getMessage(), $ignore_pattern) !== false) {
                    $should_skip = true;
                    $skipped++;
                    echo "<div style='color: orange;'>⚠ Skipped: " . $e->getMessage() . "</div>\n";
                    break;
                }
            }
            
            if (!$should_skip) {
                $errors[] = $e->getMessage();
                echo "<div style='color: red;'>✗ Error: " . $e->getMessage() . "</div>\n";
            }
        }
    }
    
    echo "<h3>Schema Application Summary:</h3>\n";
    echo "<p>Executed: {$executed} statements</p>\n";
    echo "<p>Skipped: {$skipped} statements</p>\n";
    echo "<p>Errors: " . count($errors) . " statements</p>\n";
    
    // Insert employee data
    echo "<h2>Inserting Employee Data...</h2>\n";
    
    $employees = [
        ['664921T000009', 'อริษา บัวเพชร', 'SPT', 'สาว', 'สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์', 'พัฒนา'],
        ['664921T000010', 'ธวัชชัย สัจจารักษ์', 'SPT', 'นุ๊ก', 'สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง', 'พัฒนา'],
        ['664921T000011', 'ธนวัต รัตนพันธ์', 'SPT', 'เกณฑ์', 'ในเมืองทอง -ปั้มปตท. เฉพาะฝั่งซ้าย', 'พัฒนา'],
        ['664921T000012', 'ศุภรัตน์ จักราพงษ์', 'SPT', 'เนส', 'ปตท. - ซ.ศรีธรรมโศก 2 ซ้าย+ขวา', 'พัฒนา'],
        ['664921T000013', 'อนาวิล ฮาลาบี', 'SPT', 'ยาส', 'ศรีธธรรมโศก 2 - คลองป่าเหล้า ซ้าย-ขวา', 'พัฒนา'],
        ['664921T000014', 'ปิยาวัฒน์ ชูเมฆา', 'SPT', 'อ้วน', 'คลองป่าเหล้า - โรงแรมแกรมายโฮม +คอนโดปภัสสร', 'พัฒนา'],
        ['664921T000015', 'ณัฐพล พลสังข์', 'SPT', 'กอล์ฟ', 'เคหะ+ศุภาลับรีม่า+ทวินโลตัส+โตโยต้า', 'พัฒนา'],
        ['664921T000016', 'ตุลา ดำเกิงลักษณ์', 'SPT', 'บังมีน', 'โลตัส +สะพานคูพาย-โฮมโปร ทั้งซ้าย-ขวา', 'พัฒนา'],
        ['664921T000017', 'อับดุลรอหีม เบ็ญโส๊ะ', 'SPT', 'ฮีม', 'เส้นศรีธรรมโศกทั้งเส้น', 'ราชดำเนิน'],
        ['664921T000018', 'วีรวุฒิ หมื่นยกพล', 'SPT', 'เอ็ม', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'ราชดำเนิน'],
        ['664921T000019', 'ณัฐพล ดาราวรรณ', 'SPT', 'นิด', 'เส้นราชดำเนิน เสมาเมือง -ประตูชัย', 'ราชดำเนิน'],
        ['664921T000020', 'นันทิยา สุพงษ์', 'SPT', 'นัน', 'ป่าขอม+ป้อมเพชร+หัวหลาง', 'ราชดำเนิน'],
        ['664921T000021', 'กษิดิศ ทิพย์สุราษฎร์', 'SPT', 'ฮัท', 'รพ.มหาราช', 'ราชดำเนิน'],
        ['664921T000022', 'ณัฐพงศ์ สุทธิพิทักษ์', 'SPT', 'เกมส์', 'ประตูชัย - พัฒนา 1', 'ราชดำเนิน'],
        ['664921T000023', 'อติกันต์ อ่อนทา', 'SPT', 'กอง', 'ปตทหัวถนน +ถนนนครศรีปากพนัง', 'ราชดำเนิน'],
        ['664921T000024', 'สุภาพร สมาธิ', 'SPT+C', 'ตั้ก', 'สะพานแสงจันทร์ - โฮมโปร ซ้าย+ ขวา', 'พัฒนา'],
        ['664921T000025', 'ปราโมทย์ พรหมดำ', 'SPT+C', 'เบียร์', 'พัฒนาการคูขวางไปถึงสำเพ็ง+สารีบุตร+พัฒนาการคลัง', 'พัฒนา'],
        ['664921T000030', 'ไพฑูรย์ สุวรรณปากแพรก', 'SPT+S', 'หนุ่ม', 'ศรีธธรรมโศกทั้งเส้น', 'ราชดำเนิน'],
        ['664921T000027', 'สมชาย ตำราเรียง', 'SPT+S', 'หมาน', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'ราชดำเนิน'],
        ['664921T000028', 'ณัฐฐากาญจน์ ล่องโลก', 'SPT+S', 'นิว', 'ราชดำเนินทั้งเส้น + นครศรีปากพนัง', 'ราชดำเนิน']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO delivery_zone_employees (employee_code, employee_name, position, nickname, zone_area, zone_code, status, hire_date) VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())");
    
    $inserted = 0;
    foreach ($employees as $emp) {
        try {
            $stmt->execute([
                $emp[0], // employee_code
                $emp[1], // employee_name
                $emp[2], // position
                $emp[3], // nickname
                $emp[4], // zone_area description
                $emp[5]  // zone_code
            ]);
            
            if ($stmt->rowCount() > 0) {
                $inserted++;
                echo "<div style='color: green;'>✓ Inserted: {$emp[1]} ({$emp[3]})</div>\n";
            } else {
                echo "<div style='color: orange;'>⚠ Already exists: {$emp[1]} ({$emp[3]})</div>\n";
            }
        } catch (PDOException $e) {
            echo "<div style='color: red;'>✗ Error inserting {$emp[1]}: " . $e->getMessage() . "</div>\n";
        }
    }
    
    echo "<h3>Employee Data Summary:</h3>\n";
    echo "<p>New employees inserted: {$inserted}</p>\n";
    echo "<p>Total employees in system: ";
    $stmt = $conn->prepare("SELECT COUNT(*) FROM delivery_zone_employees");
    $stmt->execute();
    echo $stmt->fetchColumn() . "</p>\n";
    
    // Check if tables exist before auto-assignment
    echo "<h2>Checking tables and auto-assigning employees...</h2>\n";
    
    try {
        // Check if both tables exist
        $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_zone_employees'");
        $stmt->execute();
        $employees_table_exists = $stmt->rowCount() > 0;
        
        $stmt = $conn->prepare("SHOW TABLES LIKE 'zone_employee_assignments'");
        $stmt->execute();
        $assignments_table_exists = $stmt->rowCount() > 0;
        
        if ($employees_table_exists && $assignments_table_exists) {
            // Auto-assign employees to zones based on zone_code
            $assignment_stmt = $conn->prepare("
                INSERT IGNORE INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active)
                SELECT za.id, dze.id, 'primary', CURDATE(), TRUE
                FROM delivery_zone_employees dze
                JOIN zone_area za ON dze.zone_code = za.zone_code
                WHERE dze.status = 'active'
            ");
            
            $assignment_stmt->execute();
            $assigned = $assignment_stmt->rowCount();
            echo "<div style='color: green;'>✓ Auto-assigned {$assigned} employees to zones</div>\n";
        } else {
            echo "<div style='color: orange;'>⚠ Tables not ready for auto-assignment yet</div>\n";
        }
    } catch (PDOException $e) {
        echo "<div style='color: red;'>✗ Error in auto-assignment: " . $e->getMessage() . "</div>\n";
    }
    
    // Show final statistics
    echo "<h2>Final Statistics:</h2>\n";
    
    // Zone statistics
    $stmt = $conn->prepare("
        SELECT 
            za.zone_code,
            za.zone_name,
            COUNT(DISTINCT zea.employee_id) as assigned_employees,
            COUNT(DISTINCT da.id) as total_deliveries
        FROM zone_area za
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_address da ON za.id = da.zone_id
        GROUP BY za.id, za.zone_code, za.zone_name
        ORDER BY za.zone_code
    ");
    $stmt->execute();
    $zone_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
    echo "<tr style='background-color: #f3f4f6;'><th>โซน</th><th>ชื่อโซน</th><th>พนักงาน</th><th>งานจัดส่ง</th></tr>\n";
    foreach ($zone_stats as $stat) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$stat['zone_code']}</td>";
        echo "<td style='padding: 8px;'>{$stat['zone_name']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$stat['assigned_employees']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$stat['total_deliveries']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Employee statistics
    $stmt = $conn->prepare("
        SELECT 
            position,
            COUNT(*) as count
        FROM delivery_zone_employees 
        WHERE status = 'active'
        GROUP BY position
        ORDER BY position
    ");
    $stmt->execute();
    $position_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Employee by Position:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f3f4f6;'><th>ตำแหน่ง</th><th>จำนวน</th></tr>\n";
    foreach ($position_stats as $stat) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$stat['position']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$stat['count']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h2 style='color: green;'>✅ Zone Employee System Setup Complete!</h2>\n";
    echo "<div style='background-color: #e6f7ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>Next Steps:</h3>\n";
    echo "<ul>\n";
    echo "<li><a href='pages/zones_enhanced.php'>เข้าสู่ระบบจัดการโซนและพนักงาน</a></li>\n";
    echo "<li><a href='pages/zones.php'>จัดการโซนแบบละเอียด</a></li>\n";
    echo "<li><a href='pages/leaflet_map.php'>ดูแผนที่การจัดส่ง</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?> 