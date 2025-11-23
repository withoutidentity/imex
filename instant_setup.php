<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Setup - Zone Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<div class="min-h-screen py-6 px-4">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6 rounded-lg shadow-lg mb-6">
            <h1 class="text-2xl font-bold mb-2">
                <i class="fas fa-zap mr-3"></i>Instant Setup - Zone Management
            </h1>
            <p class="text-green-100">ติดตั้งแบบฟ้าผ่า - รันครั้งเดียวเสร็จ!</p>
        </div>

        <?php
        // Check if setup was requested
        if (isset($_POST['instant_setup']) || isset($_GET['run'])) {
            
            echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6'>";
            echo "<h2 class='text-xl font-bold text-blue-800 mb-4'>⚡ กำลังติดตั้งแบบ Instant...</h2>";
            
            try {
                require_once 'config/config.php';
                
                if (!$conn) {
                    throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ - กรุณาเปิด MySQL ก่อน");
                }
                
                echo "<div class='text-green-600 mb-3'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</div>";
                
                // Create tables with direct SQL
                $sql_commands = [
                    // Create employees table
                    "CREATE TABLE IF NOT EXISTS delivery_zone_employees (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        employee_code VARCHAR(20) UNIQUE NOT NULL,
                        employee_name VARCHAR(100) NOT NULL,
                        position ENUM('SPT', 'SPT+C', 'SPT+S', 'Manager', 'Supervisor') DEFAULT 'SPT',
                        zone_area VARCHAR(100) NOT NULL,
                        zone_code VARCHAR(100) NOT NULL,
                        nickname VARCHAR(50),
                        phone VARCHAR(20),
                        email VARCHAR(100),
                        hire_date DATE,
                        status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_employee_code (employee_code),
                        INDEX idx_zone_code_simple (zone_code),
                        INDEX idx_status (status),
                        INDEX idx_position (position)
                    )",
                    
                    // Create assignments table
                    "CREATE TABLE IF NOT EXISTS zone_employee_assignments (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        zone_id INT,
                        employee_id INT,
                        assignment_type ENUM('primary', 'backup', 'support') DEFAULT 'primary',
                        start_date DATE NOT NULL,
                        end_date DATE NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        workload_percentage DECIMAL(5,2) DEFAULT 100.00,
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_zone_assignment (zone_id, is_active),
                        INDEX idx_employee_assignment (employee_id, is_active),
                        INDEX idx_assignment_type (assignment_type)
                    )",
                    
                    // Ensure zones exist
                    "INSERT IGNORE INTO zone_area (zone_code, zone_name, zone_type, color_code, description) VALUES
                    ('พัฒนา', 'โซนพัฒนาการ', 'urban', '#3B82F6', 'พื้นที่ถนนพัฒนาการและโดยรอบ'),
                    ('ราชดำเนิน', 'โซนราชดำเนิน', 'urban', '#10B981', 'พื้นที่ถนนราชดำเนินและโดยรอบ'),
                    ('เมืองทอง', 'โซนเมืองทองธานี', 'urban', '#F59E0B', 'พื้นที่เมืองทองธานีและโดยรอบ'),
                    ('ศรีธรรมโศก', 'โซนศรีธรรมโศก', 'urban', '#EF4444', 'พื้นที่ถนนศรีธรรมโศกและโดยรอบ')"
                ];
                
                // Execute table creation
                foreach ($sql_commands as $index => $sql) {
                    try {
                        $conn->exec($sql);
                        echo "<div class='text-green-600 text-sm'>✓ SQL Command " . ($index + 1) . " executed</div>";
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') !== false) {
                            echo "<div class='text-orange-600 text-sm'>⚠ Table already exists (SQL " . ($index + 1) . ")</div>";
                        } else {
                            echo "<div class='text-red-600 text-sm'>✗ Error in SQL " . ($index + 1) . ": " . $e->getMessage() . "</div>";
                        }
                    }
                }
                
                echo "<div class='my-4 text-blue-800 font-medium'>📊 กำลังเพิ่มข้อมูลพนักงาน 20 คน...</div>";
                
                // Insert employees data
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
                        }
                        echo "<div class='text-green-600 text-xs'>✓ {$emp[1]} ({$emp[3]})</div>";
                    } catch (PDOException $e) {
                        echo "<div class='text-orange-600 text-xs'>⚠ {$emp[1]}: " . $e->getMessage() . "</div>";
                    }
                }
                
                echo "<div class='my-4 text-blue-800 font-medium'>🔗 กำลังมอบหมายพนักงานให้โซน...</div>";
                
                // Auto-assign employees to zones
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
                    echo "<div class='text-green-600'>✓ มอบหมาย {$assigned} การงาน</div>";
                } catch (PDOException $e) {
                    echo "<div class='text-orange-600'>⚠ การมอบหมาย: " . $e->getMessage() . "</div>";
                }
                
                // Final verification
                echo "<div class='my-4 text-blue-800 font-medium'>📋 ตรวจสอบผลลัพธ์...</div>";
                
                $emp_count = $conn->query("SELECT COUNT(*) FROM delivery_zone_employees")->fetchColumn();
                $assign_count = $conn->query("SELECT COUNT(*) FROM zone_employee_assignments WHERE is_active = TRUE")->fetchColumn();
                $zone_count = $conn->query("SELECT COUNT(*) FROM zone_area")->fetchColumn();
                
                echo "<div class='mt-6 p-6 bg-green-100 border border-green-200 rounded-lg'>";
                echo "<div class='text-green-800 font-bold text-lg mb-3'>🎉 ติดตั้งสำเร็จ!</div>";
                echo "<div class='grid grid-cols-3 gap-4 text-sm'>";
                echo "<div class='text-center'><div class='text-2xl font-bold text-blue-600'>{$zone_count}</div><div class='text-gray-600'>โซน</div></div>";
                echo "<div class='text-center'><div class='text-2xl font-bold text-green-600'>{$emp_count}</div><div class='text-gray-600'>พนักงาน</div></div>";
                echo "<div class='text-center'><div class='text-2xl font-bold text-purple-600'>{$assign_count}</div><div class='text-gray-600'>การมอบหมาย</div></div>";
                echo "</div>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='p-4 bg-red-100 border border-red-200 rounded-lg'>";
                echo "<div class='text-red-800 font-bold'>❌ เกิดข้อผิดพลาด:</div>";
                echo "<div class='text-red-600 mt-2'>" . $e->getMessage() . "</div>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            // Show setup form
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Instructions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>ขั้นตอนการติดตั้ง
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start">
                            <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">1</div>
                            <div>
                                <div class="font-medium">เปิด XAMPP</div>
                                <div class="text-gray-600">เริ่ม Apache และ MySQL</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">2</div>
                            <div>
                                <div class="font-medium">กดปุ่ม "Instant Setup"</div>
                                <div class="text-gray-600">รอสักครู่ ระบบจะติดตั้งให้อัตโนมัติ</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">3</div>
                            <div>
                                <div class="font-medium">เริ่มใช้งาน</div>
                                <div class="text-gray-600">เข้าสู่ระบบจัดการโซนและพนักงาน</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Setup Action -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-rocket text-green-600 mr-2"></i>พร้อมติดตั้ง?
                    </h3>
                    
                    <div class="mb-4">
                        <div class="text-sm text-gray-600 mb-3">
                            ระบบจะสร้าง:
                        </div>
                        <ul class="text-sm space-y-1">
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>ตาราง delivery_zone_employees</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>ตาราง zone_employee_assignments</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>ข้อมูลพนักงาน 20 คน</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>โซนการจัดส่ง 4 โซน</li>
                            <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>การมอบหมายงานอัตโนมัติ</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <button type="submit" name="instant_setup" 
                                class="w-full bg-gradient-to-r from-green-600 to-blue-600 text-white py-3 px-6 rounded-lg hover:from-green-700 hover:to-blue-700 transition-all transform hover:scale-105 font-semibold">
                            <i class="fas fa-zap mr-2"></i>⚡ Instant Setup
                        </button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <a href="?run=1" class="text-blue-600 hover:text-blue-800 text-sm">
                            หรือคลิกลิงก์นี้เพื่อรันทันที
                        </a>
                    </div>
                </div>
            </div>
            
            <?php
        }
        ?>
        
        <!-- Quick Links -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-external-link-alt mr-2"></i>Quick Links
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="demo_zone_management.php" 
                   class="flex flex-col items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-eye text-xl mb-2"></i>
                    <span class="text-sm">Demo</span>
                </a>
                
                <a href="pages/zones_enhanced.php" 
                   class="flex flex-col items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-users-cog text-xl mb-2"></i>
                    <span class="text-sm">จัดการ</span>
                </a>
                
                <a href="pages/leaflet_map.php" 
                   class="flex flex-col items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-map text-xl mb-2"></i>
                    <span class="text-sm">แผนที่</span>
                </a>
                
                <a href="http://localhost/phpmyadmin" target="_blank"
                   class="flex flex-col items-center p-3 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition-colors">
                    <i class="fas fa-database text-xl mb-2"></i>
                    <span class="text-sm">phpMyAdmin</span>
                </a>
            </div>
        </div>
        
    </div>
</div>

</body>
</html> 