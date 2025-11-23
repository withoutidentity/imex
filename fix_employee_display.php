<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขการแสดงพนักงานในโซน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<div class="min-h-screen py-6 px-4">
    <div class="max-w-4xl mx-auto">
        
        <!-- Header -->
        <div class="bg-red-600 text-white p-6 rounded-lg shadow-lg mb-6">
            <h1 class="text-2xl font-bold mb-2">
                <i class="fas fa-tools mr-3"></i>แก้ไขการแสดงพนักงานในโซน
            </h1>
            <p class="text-red-100">แก้ปัญหา zones.php ไม่แสดงข้อมูลพนักงาน</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])) {
            
            echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6'>";
            echo "<h2 class='text-xl font-bold text-blue-800 mb-4'>🔧 กำลังดำเนินการแก้ไข...</h2>";
            
            try {
                require_once 'config/config.php';
                
                if (!$conn) {
                    throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ - กรุณาเปิด MySQL ใน XAMPP ก่อน");
                }
                
                echo "<div class='text-green-600 mb-3'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</div>";
                
                // Check if tables exist
                echo "<div class='my-4 text-blue-800 font-medium'>📋 ตรวจสอบตาราง...</div>";
                
                $required_tables = [
                    'zone_area' => 'ตารางโซน',
                    'delivery_zone_employees' => 'ตารางพนักงาน', 
                    'zone_employee_assignments' => 'ตารางการมอบหมาย'
                ];
                
                $missing_tables = [];
                foreach ($required_tables as $table => $description) {
                    try {
                        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                        $stmt->execute([$table]);
                        
                        if ($stmt->rowCount() > 0) {
                            echo "<div class='text-green-600 text-sm'>✓ มี{$description}</div>";
                        } else {
                            echo "<div class='text-red-600 text-sm'>✗ ไม่มี{$description}</div>";
                            $missing_tables[] = $table;
                        }
                    } catch (PDOException $e) {
                        echo "<div class='text-red-600 text-sm'>✗ ข้อผิดพลาด {$description}: {$e->getMessage()}</div>";
                        $missing_tables[] = $table;
                    }
                }
                
                if (!empty($missing_tables)) {
                    echo "<div class='my-4 text-red-800 font-medium'>❌ ต้องสร้างตารางก่อน</div>";
                    echo "<div class='bg-yellow-100 border border-yellow-200 rounded p-4'>";
                    echo "<div class='font-semibold text-yellow-800'>วิธีแก้:</div>";
                    echo "<div class='text-yellow-700 mt-2'>1. รัน <a href='instant_setup.php' class='underline font-semibold'>instant_setup.php</a> เพื่อสร้างตารางพื้นฐาน</div>";
                    echo "<div class='text-yellow-700'>2. จากนั้นรัน <a href='update_zones_employees.php' class='underline font-semibold'>update_zones_employees.php</a> เพื่อเพิ่มข้อมูลพนักงาน</div>";
                    echo "</div>";
                } else {
                    // Check data
                    echo "<div class='my-4 text-blue-800 font-medium'>📊 ตรวจสอบข้อมูล...</div>";
                    
                    $emp_count = $conn->query("SELECT COUNT(*) FROM delivery_zone_employees WHERE status='active'")->fetchColumn();
                    $assign_count = $conn->query("SELECT COUNT(*) FROM zone_employee_assignments WHERE is_active=TRUE")->fetchColumn();
                    $zone_count = $conn->query("SELECT COUNT(*) FROM zone_area WHERE is_active=1")->fetchColumn();
                    
                    echo "<div class='grid grid-cols-3 gap-4 text-sm mb-4'>";
                    echo "<div class='text-center p-3 bg-white rounded'>";
                    echo "<div class='text-2xl font-bold text-blue-600'>{$zone_count}</div>";
                    echo "<div class='text-gray-600'>โซน</div>";
                    echo "</div>";
                    echo "<div class='text-center p-3 bg-white rounded'>";
                    echo "<div class='text-2xl font-bold text-green-600'>{$emp_count}</div>";
                    echo "<div class='text-gray-600'>พนักงาน</div>";
                    echo "</div>";
                    echo "<div class='text-center p-3 bg-white rounded'>";
                    echo "<div class='text-2xl font-bold text-purple-600'>{$assign_count}</div>";
                    echo "<div class='text-gray-600'>การมอบหมาย</div>";
                    echo "</div>";
                    echo "</div>";
                    
                    if ($emp_count == 0) {
                        echo "<div class='bg-orange-100 border border-orange-200 rounded p-4 mb-4'>";
                        echo "<div class='text-orange-800 font-bold'>⚠ ไม่มีข้อมูลพนักงาน</div>";
                        echo "<div class='text-orange-700 mt-2'>วิธีแก้:</div>";
                        echo "<ul class='list-disc list-inside text-orange-700 mt-1'>";
                        echo "<li>รัน <a href='update_zones_employees.php' class='underline font-semibold'>update_zones_employees.php</a> เพื่อเพิ่มข้อมูลพนักงาน 20 คน</li>";
                        echo "</ul>";
                        echo "</div>";
                    } elseif ($assign_count == 0) {
                        echo "<div class='bg-yellow-100 border border-yellow-200 rounded p-4 mb-4'>";
                        echo "<div class='text-yellow-800 font-bold'>⚠ มีพนักงานแต่ไม่ได้ผูกกับโซน</div>";
                        echo "<div class='text-yellow-700 mt-2'>กำลังแก้ไข...</div>";
                        echo "</div>";
                        
                        // Auto-fix assignments
                        echo "<div class='my-4 text-blue-800 font-medium'>🔗 กำลังผูกพนักงานกับโซน...</div>";
                        
                        try {
                            $fix_stmt = $conn->prepare("
                                INSERT INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active, workload_percentage)
                                SELECT za.id, dze.id, 'primary', CURDATE(), TRUE, 100.00
                                FROM delivery_zone_employees dze
                                JOIN zone_area za ON dze.zone_area = za.zone_code
                                WHERE dze.status = 'active' AND za.is_active = 1
                                AND NOT EXISTS (
                                    SELECT 1 FROM zone_employee_assignments zea2 
                                    WHERE zea2.zone_id = za.id AND zea2.employee_id = dze.id AND zea2.is_active = TRUE
                                )
                            ");
                            $fix_stmt->execute();
                            $fixed = $fix_stmt->rowCount();
                            echo "<div class='text-green-600'>✓ ผูกพนักงานกับโซนแล้ว: {$fixed} รายการ</div>";
                            
                            // Update assignment count
                            $assign_count = $conn->query("SELECT COUNT(*) FROM zone_employee_assignments WHERE is_active=TRUE")->fetchColumn();
                        } catch (PDOException $e) {
                            echo "<div class='text-red-600'>✗ ข้อผิดพลาดในการผูกโซน: " . $e->getMessage() . "</div>";
                        }
                    }
                    
                    if ($emp_count > 0 && $assign_count > 0) {
                        echo "<div class='my-4 text-blue-800 font-medium'>📈 ตัวอย่างข้อมูลโซนและพนักงาน...</div>";
                        
                        $sample_stmt = $conn->prepare("
                            SELECT za.zone_code, za.zone_name,
                                   COUNT(DISTINCT dze.id) as employee_count,
                                   GROUP_CONCAT(DISTINCT CONCAT(dze.employee_name, ' (', dze.nickname, ')') SEPARATOR ', ') as employees
                            FROM zone_area za
                            LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
                            LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
                            WHERE za.is_active = 1
                            GROUP BY za.id
                            ORDER BY za.zone_code
                            LIMIT 5
                        ");
                        $sample_stmt->execute();
                        $samples = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<div class='bg-gray-50 rounded p-4'>";
                        foreach ($samples as $sample) {
                            $employees_display = $sample['employees'] ?: '<span class="text-gray-400 italic">ไม่มี</span>';
                            echo "<div class='mb-2'>";
                            echo "<div class='font-medium'>{$sample['zone_code']}: {$sample['zone_name']}</div>";
                            echo "<div class='text-sm text-gray-600'>พนักงาน ({$sample['employee_count']} คน): {$employees_display}</div>";
                            echo "</div>";
                        }
                        echo "</div>";
                        
                        echo "<div class='mt-6 p-6 bg-green-100 border border-green-200 rounded-lg'>";
                        echo "<div class='text-green-800 font-bold text-lg mb-3'>🎉 แก้ไขเสร็จสิ้น!</div>";
                        echo "<div class='text-green-700'>ตอนนี้ระบบพร้อมแสดงข้อมูลพนักงานในโซนแล้ว</div>";
                        echo "<div class='mt-3'>";
                        echo "<a href='pages/zones.php' class='bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors'>";
                        echo "<i class='fas fa-external-link-alt mr-2'></i>ไปที่หน้าจัดการโซน";
                        echo "</a>";
                        echo "</div>";
                        echo "</div>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div class='p-4 bg-red-100 border border-red-200 rounded-lg'>";
                echo "<div class='text-red-800 font-bold'>❌ เกิดข้อผิดพลาด:</div>";
                echo "<div class='text-red-600 mt-2'>" . $e->getMessage() . "</div>";
                
                if (strpos($e->getMessage(), 'No such file or directory') !== false) {
                    echo "<div class='mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded'>";
                    echo "<div class='text-yellow-800 font-semibold'>💡 วิธีแก้:</div>";
                    echo "<div class='text-yellow-700 text-sm mt-1'>";
                    echo "1. เปิด XAMPP Control Panel<br>";
                    echo "2. กดปุ่ม Start ที่ Apache และ MySQL<br>";
                    echo "3. รอจนสถานะเป็นสีเขียว<br>";
                    echo "4. รีเฟรชหน้านี้";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            // Show diagnosis and fix options
            ?>
            
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-search text-blue-600 mr-2"></i>วินิจฉัยปัญหา
                </h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium text-red-800">ปัญหา: zones.php แสดง "0 คน"</div>
                            <div class="text-gray-600 mt-1">ระบบแสดงส่วนพนักงานแล้ว แต่ไม่มีข้อมูลพนักงานในฐานข้อมูล</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <i class="fas fa-lightbulb text-yellow-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium text-yellow-800">สาเหตุที่เป็นไปได้:</div>
                            <ul class="text-gray-600 mt-1 list-disc list-inside ml-4">
                                <li>MySQL ไม่ได้เปิด</li>
                                <li>ไม่มีข้อมูลพนักงานในฐานข้อมูล</li>
                                <li>พนักงานไม่ได้ถูกผูกกับโซน</li>
                                <li>ตารางที่จำเป็นยังไม่ได้สร้าง</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-wrench text-green-600 mr-2"></i>เครื่องมือแก้ไข
                </h3>
                
                <div class="space-y-4">
                    <!-- Auto Fix Button -->
                    <div class="border border-green-200 rounded-lg p-4">
                        <h4 class="font-semibold text-green-800 mb-2">
                            <i class="fas fa-magic mr-2"></i>แก้ไขอัตโนมัติ (แนะนำ)
                        </h4>
                        <p class="text-sm text-gray-600 mb-3">
                            ตรวจสอบและแก้ไขปัญหาทั้งหมดโดยอัตโนมัติ
                        </p>
                        <form method="POST">
                            <button type="submit" name="run_fix" 
                                    class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-semibold">
                                <i class="fas fa-play mr-2"></i>🚀 เริ่มแก้ไข
                            </button>
                        </form>
                    </div>
                    
                    <!-- Manual Options -->
                    <div class="border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-hand-paper mr-2"></i>แก้ไขด้วยตนเอง
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <a href="instant_setup.php" 
                               class="flex items-center p-3 bg-blue-50 text-blue-700 rounded hover:bg-blue-100 transition-colors">
                                <i class="fas fa-database mr-3"></i>
                                <div>
                                    <div class="font-medium">Instant Setup</div>
                                    <div class="text-xs">สร้างตารางพื้นฐาน</div>
                                </div>
                            </a>
                            
                            <a href="update_zones_employees.php" 
                               class="flex items-center p-3 bg-green-50 text-green-700 rounded hover:bg-green-100 transition-colors">
                                <i class="fas fa-users mr-3"></i>
                                <div>
                                    <div class="font-medium">อัปเดตพนักงาน</div>
                                    <div class="text-xs">เพิ่มข้อมูล 20 คน</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
        }
        ?>
        
        <!-- Quick Access -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-external-link-alt mr-2"></i>Quick Access
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="pages/zones.php" 
                   class="flex flex-col items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-map-marked-alt text-xl mb-2"></i>
                    <span class="text-sm">จัดการโซน</span>
                </a>
                
                <a href="test_zone_employees.php" 
                   class="flex flex-col items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-clipboard-check text-xl mb-2"></i>
                    <span class="text-sm">ทดสอบระบบ</span>
                </a>
                
                <a href="debug_zones.php" 
                   class="flex flex-col items-center p-3 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition-colors">
                    <i class="fas fa-bug text-xl mb-2"></i>
                    <span class="text-sm">Debug</span>
                </a>
                
                <a href="http://localhost/phpmyadmin" target="_blank"
                   class="flex flex-col items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-database text-xl mb-2"></i>
                    <span class="text-sm">phpMyAdmin</span>
                </a>
            </div>
        </div>
        
    </div>
</div>

</body>
</html> 