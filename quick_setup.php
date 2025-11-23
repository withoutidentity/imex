<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Setup - Zone Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<div class="min-h-screen py-6 px-4">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6">
            <h1 class="text-2xl font-bold mb-2">
                <i class="fas fa-rocket mr-3"></i>Quick Setup - Zone Management System
            </h1>
            <p class="text-blue-100">ติดตั้งระบบจัดการโซนและพนักงานอย่างง่าย</p>
        </div>

        <?php
        // Check database connection
        $db_connected = false;
        $conn = null;
        
        try {
            require_once 'config/config.php';
            if ($conn) {
                $db_connected = true;
            }
        } catch (Exception $e) {
            $db_error = $e->getMessage();
        }
        ?>

        <?php if (!$db_connected): ?>
        <!-- MySQL Not Running -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-red-800">MySQL/MariaDB ยังไม่ได้เปิด</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>กรุณาเริ่มต้น XAMPP และ MySQL ก่อนติดตั้งระบบ</p>
                    </div>
                    
                    <div class="mt-4">
                        <h4 class="font-medium text-red-800 mb-2">วิธีแก้ไข:</h4>
                        <div class="bg-red-100 rounded-lg p-4">
                            <h5 class="font-medium mb-2">Option 1: ใช้ XAMPP Control Panel</h5>
                            <ol class="list-decimal list-inside text-sm space-y-1">
                                <li>เปิด XAMPP Control Panel</li>
                                <li>กดปุ่ม "Start" ที่ Apache</li>
                                <li>กดปุ่ม "Start" ที่ MySQL</li>
                                <li>รอสักครู่แล้วรีเฟรชหน้านี้</li>
                            </ol>
                            
                            <h5 class="font-medium mb-2 mt-4">Option 2: ใช้ Terminal</h5>
                            <div class="bg-gray-800 text-green-400 p-3 rounded text-sm font-mono">
sudo /Applications/XAMPP/xamppfiles/xampp start
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Database Connected - Setup Options -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-green-800">เชื่อมต่อฐานข้อมูลสำเร็จ!</h3>
                    <p class="mt-2 text-sm text-green-700">พร้อมสำหรับการติดตั้งระบบ</p>
                </div>
            </div>
        </div>

        <!-- Setup Process -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            <!-- Manual SQL Setup -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-database text-blue-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Manual SQL Setup</h3>
                </div>
                
                <p class="text-gray-600 text-sm mb-4">คัดลอกและรัน SQL Commands ผ่าน phpMyAdmin</p>
                
                <div class="space-y-3">
                    <a href="http://localhost/phpmyadmin" target="_blank" 
                       class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-external-link-alt mr-2"></i>เปิด phpMyAdmin
                    </a>
                    
                    <button onclick="showSQLCommands()" 
                            class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                        <i class="fas fa-code mr-2"></i>แสดง SQL Commands
                    </button>
                </div>
            </div>

            <!-- Auto Setup -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-magic text-purple-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Auto Setup</h3>
                </div>
                
                <p class="text-gray-600 text-sm mb-4">รันสคริปต์ติดตั้งอัตโนมัติ</p>
                
                <div class="space-y-3">
                    <form method="POST" action="">
                        <button type="submit" name="auto_setup" 
                                class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition-colors">
                            <i class="fas fa-play mr-2"></i>เริ่มติดตั้งอัตโนมัติ
                        </button>
                    </form>
                    
                    <a href="setup_zone_employees.php" 
                       class="block w-full bg-gray-600 text-white text-center py-2 px-4 rounded-md hover:bg-gray-700 transition-colors">
                        <i class="fas fa-cogs mr-2"></i>Setup แบบละเอียด
                    </a>
                </div>
            </div>
        </div>

        <?php
        // Handle auto setup
        if (isset($_POST['auto_setup'])) {
            echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6'>";
            echo "<h3 class='text-lg font-semibold text-blue-800 mb-4'>🚀 กำลังติดตั้งระบบ...</h3>";
            
            try {
                // Read and execute simplified schema
                $schema_sql = file_get_contents('database/zone_employee_simple.sql');
                $statements = explode(';', $schema_sql);
                
                $success_count = 0;
                $skip_count = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement)) continue;
                    
                    try {
                        $conn->exec($statement);
                        $success_count++;
                        echo "<div class='text-green-600 text-sm'>✓ " . substr($statement, 0, 60) . "...</div>";
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') !== false || 
                            strpos($e->getMessage(), 'Duplicate') !== false) {
                            $skip_count++;
                            echo "<div class='text-orange-600 text-sm'>⚠ ข้าม: " . substr($statement, 0, 40) . "...</div>";
                        } else {
                            echo "<div class='text-red-600 text-sm'>✗ Error: " . $e->getMessage() . "</div>";
                        }
                    }
                }
                
                // Insert employee data
                echo "<div class='mt-4 mb-2 font-medium text-blue-800'>กำลังเพิ่มข้อมูลพนักงาน...</div>";
                
                $employees = [
                    ['664921T000009', 'อริษา บัวเพชร', 'SPT', 'สาว', 'สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์', 'พัฒนา'],
                    ['664921T000010', 'ธวัชชัย สัจจารักษ์', 'SPT', 'นุ๊ก', 'สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง', 'พัฒนา'],
                    ['664921T000011', 'ธนวัต รัตนพันธ์', 'SPT', 'เกณฑ์', 'ในเมืองทอง -ปั้มปตท. เฉพาะฝั่งซ้าย', 'พัฒนา'],
                    ['664921T000012', 'ศุภรัตน์ จักราพงษ์', 'SPT', 'เนส', 'ปตท. - ซ.ศรีธรรมโศก 2 ซ้าย+ขวา', 'พัฒนา'],
                    ['664921T000013', 'อนาวิล ฮาลาบี', 'SPT', 'ยาส', 'ศรีธรรมโศก 2 - คลองป่าเหล้า ซ้าย-ขวา', 'พัฒนา'],
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
                    ['664921T000030', 'ไพฑูรย์ สุวรรณปากแพรก', 'SPT+S', 'หนุ่ม', 'ศรีธรรมโศกทั้งเส้น', 'ราชดำเนิน'],
                    ['664921T000027', 'สมชาย ตำราเรียง', 'SPT+S', 'หมาน', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'ราชดำเนิน'],
                    ['664921T000028', 'ณัฐฐากาญจน์ ล่องโลก', 'SPT+S', 'นิว', 'ราชดำเนินทั้งเส้น + นครศรีปากพนัง', 'ราชดำเนิน']
                ];
                
                $stmt = $conn->prepare("INSERT IGNORE INTO delivery_zone_employees (employee_code, employee_name, position, nickname, zone_area, zone_code, status, hire_date) VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())");
                
                $inserted = 0;
                foreach ($employees as $emp) {
                    try {
                        $stmt->execute([$emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5]]);
                        if ($stmt->rowCount() > 0) {
                            $inserted++;
                            echo "<div class='text-green-600 text-sm'>✓ เพิ่ม: {$emp[1]} ({$emp[3]})</div>";
                        }
                    } catch (PDOException $e) {
                        // Skip duplicate entries silently
                    }
                }
                
                // Auto-assign employees
                echo "<div class='mt-4 mb-2 font-medium text-blue-800'>กำลังมอบหมายพนักงานให้โซน...</div>";
                try {
                    $assign_stmt = $conn->prepare("
                        INSERT IGNORE INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active)
                        SELECT za.id, dze.id, 'primary', CURDATE(), TRUE
                        FROM delivery_zone_employees dze
                        JOIN zone_area za ON dze.zone_code = za.zone_code
                        WHERE dze.status = 'active'
                    ");
                    $assign_stmt->execute();
                    $assigned = $assign_stmt->rowCount();
                    echo "<div class='text-green-600 text-sm'>✓ มอบหมาย {$assigned} คน</div>";
                } catch (PDOException $e) {
                    echo "<div class='text-orange-600 text-sm'>⚠ บางการมอบหมายอาจซ้ำ</div>";
                }
                
                echo "<div class='mt-4 p-4 bg-green-100 rounded-lg'>";
                echo "<div class='font-semibold text-green-800'>🎉 ติดตั้งสำเร็จ!</div>";
                echo "<div class='text-sm text-green-700 mt-2'>";
                echo "• SQL Statements: {$success_count} สำเร็จ, {$skip_count} ข้าม<br>";
                echo "• พนักงานใหม่: {$inserted} คน<br>";
                echo "• มอบหมายงาน: {$assigned} การมอบหมาย";
                echo "</div>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='text-red-600'>❌ Error: " . $e->getMessage() . "</div>";
            }
            
            echo "</div>";
        }
        ?>

        <!-- Quick Access Links -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-rocket mr-2"></i>Quick Access
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <a href="demo_zone_management.php" 
                   class="flex items-center justify-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-eye mr-2"></i>ดู Demo
                </a>
                
                <a href="pages/zones_enhanced.php" 
                   class="flex items-center justify-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-users-cog mr-2"></i>เข้าระบบ
                </a>
                
                <a href="pages/leaflet_map.php" 
                   class="flex items-center justify-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-map mr-2"></i>แผนที่
                </a>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- SQL Commands Modal -->
<div id="sqlModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">SQL Commands สำหรับ phpMyAdmin</h3>
                <button onclick="hideSQLCommands()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">คัดลอกโค้ดด้านล่างและวางใน phpMyAdmin:</p>
            </div>
            
            <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
<pre id="sqlCode"><?php echo htmlspecialchars(file_get_contents('database/zone_employee_simple.sql')); ?>

-- Insert Employee Data
INSERT IGNORE INTO delivery_zone_employees (employee_code, employee_name, position, nickname, zone_area, zone_code, status, hire_date) VALUES
('664921T000009', 'อริษา บัวเพชร', 'SPT', 'สาว', 'สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์', 'พัฒนา', 'active', CURDATE()),
('664921T000010', 'ธวัชชัย สัจจารักษ์', 'SPT', 'นุ๊ก', 'สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง', 'พัฒนา', 'active', CURDATE()),
('664921T000011', 'ธนวัต รัตนพันธ์', 'SPT', 'เกณฑ์', 'ในเมืองทอง -ปั้มปตท. เฉพาะฝั่งซ้าย', 'พัฒนา', 'active', CURDATE()),
('664921T000012', 'ศุภรัตน์ จักราพงษ์', 'SPT', 'เนส', 'ปตท. - ซ.ศรีธรรมโศก 2 ซ้าย+ขวา', 'พัฒนา', 'active', CURDATE()),
('664921T000013', 'อนาวิล ฮาลาบี', 'SPT', 'ยาส', 'ศรีธรรมโศก 2 - คลองป่าเหล้า ซ้าย-ขวา', 'พัฒนา', 'active', CURDATE()),
('664921T000014', 'ปิยาวัฒน์ ชูเมฆา', 'SPT', 'อ้วน', 'คลองป่าเหล้า - โรงแรมแกรมายโฮม +คอนโดปภัสสร', 'พัฒนา', 'active', CURDATE()),
('664921T000015', 'ณัฐพล พลสังข์', 'SPT', 'กอล์ฟ', 'เคหะ+ศุภาลับรีม่า+ทวินโลตัส+โตโยต้า', 'พัฒนา', 'active', CURDATE()),
('664921T000016', 'ตุลา ดำเกิงลักษณ์', 'SPT', 'บังมีน', 'โลตัส +สะพานคูพาย-โฮมโปร ทั้งซ้าย-ขวา', 'พัฒนา', 'active', CURDATE()),
('664921T000017', 'อับดุลรอหีม เบ็ญโส๊ะ', 'SPT', 'ฮีม', 'เส้นศรีธรรมโศกทั้งเส้น', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000018', 'วีรวุฒิ หมื่นยกพล', 'SPT', 'เอ็ม', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000019', 'ณัฐพล ดาราวรรณ', 'SPT', 'นิด', 'เส้นราชดำเนิน เสมาเมือง -ประตูชัย', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000020', 'นันทิยา สุพงษ์', 'SPT', 'นัน', 'ป่าขอม+ป้อมเพชร+หัวหลาง', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000021', 'กษิดิศ ทิพย์สุราษฎร์', 'SPT', 'ฮัท', 'รพ.มหาราช', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000022', 'ณัฐพงศ์ สุทธิพิทักษ์', 'SPT', 'เกมส์', 'ประตูชัย - พัฒนา 1', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000023', 'อติกันต์ อ่อนทา', 'SPT', 'กอง', 'ปตทหัวถนน +ถนนนครศรีปากพนัง', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000024', 'สุภาพร สมาธิ', 'SPT+C', 'ตั้ก', 'สะพานแสงจันทร์ - โฮมโปร ซ้าย+ ขวา', 'พัฒนา', 'active', CURDATE()),
('664921T000025', 'ปราโมทย์ พรหมดำ', 'SPT+C', 'เบียร์', 'พัฒนาการคูขวางไปถึงสำเพ็ง+สารีบุตร+พัฒนาการคลัง', 'พัฒนา', 'active', CURDATE()),
('664921T000030', 'ไพฑูรย์ สุวรรณปากแพรก', 'SPT+S', 'หนุ่ม', 'ศรีธรรมโศกทั้งเส้น', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000027', 'สมชาย ตำราเรียง', 'SPT+S', 'หมาน', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'ราชดำเนิน', 'active', CURDATE()),
('664921T000028', 'ณัฐฐากาญจน์ ล่องโลก', 'SPT+S', 'นิว', 'ราชดำเนินทั้งเส้น + นครศรีปากพนัง', 'ราชดำเนิน', 'active', CURDATE());

-- Auto-assign employees to zones
INSERT IGNORE INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active)
SELECT za.id, dze.id, 'primary', CURDATE(), TRUE
FROM delivery_zone_employees dze
JOIN zone_area za ON dze.zone_code = za.zone_code
WHERE dze.status = 'active';</pre>
            </div>
            
            <div class="mt-4 flex space-x-3">
                <button onclick="copySQL()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-copy mr-2"></i>คัดลอก
                </button>
                <button onclick="hideSQLCommands()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors">
                    ปิด
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showSQLCommands() {
    document.getElementById('sqlModal').classList.remove('hidden');
}

function hideSQLCommands() {
    document.getElementById('sqlModal').classList.add('hidden');
}

function copySQL() {
    const sqlCode = document.getElementById('sqlCode').textContent;
    navigator.clipboard.writeText(sqlCode).then(function() {
        alert('คัดลอก SQL Commands แล้ว!');
    });
}
</script>

</body>
</html> 