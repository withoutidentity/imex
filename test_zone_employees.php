<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบระบบจัดการโซนและพนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<div class="min-h-screen py-6 px-4">
    <div class="max-w-6xl mx-auto">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-green-600 text-white p-6 rounded-lg shadow-lg mb-6">
            <h1 class="text-2xl font-bold mb-2">
                <i class="fas fa-clipboard-check mr-3"></i>ทดสอบระบบจัดการโซนและพนักงาน
            </h1>
            <p class="text-blue-100">ระบบพร้อมใช้งาน - ทดสอบฟีเจอร์ใหม่ที่เพิ่มแล้ว</p>
        </div>

        <?php
        require_once 'config/config.php';
        
        // Test database connection
        echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>";
        echo "<h3 class='text-lg font-semibold text-gray-800 mb-4'>";
        echo "<i class='fas fa-database mr-2 text-blue-600'></i>การทดสอบระบบ";
        echo "</h3>";
        
        try {
            if (!$conn) {
                throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
            }
            
            echo "<div class='space-y-3'>";
            
            // Test 1: Check database connection
            echo "<div class='flex items-center p-3 bg-green-50 rounded'>";
            echo "<i class='fas fa-check-circle text-green-600 mr-3'></i>";
            echo "<span class='text-green-800'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</span>";
            echo "</div>";
            
            // Test 2: Check required tables
            $required_tables = [
                'zone_area' => 'ตารางโซน',
                'delivery_zone_employees' => 'ตารางพนักงาน',
                'zone_employee_assignments' => 'ตารางการมอบหมาย'
            ];
            
            $all_tables_exist = true;
            foreach ($required_tables as $table => $description) {
                $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                
                if ($stmt->rowCount() > 0) {
                    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table}");
                    $count_stmt->execute();
                    $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    echo "<div class='flex items-center p-3 bg-green-50 rounded'>";
                    echo "<i class='fas fa-table text-green-600 mr-3'></i>";
                    echo "<span class='text-green-800'>✓ {$description}: {$count} รายการ</span>";
                    echo "</div>";
                } else {
                    echo "<div class='flex items-center p-3 bg-red-50 rounded'>";
                    echo "<i class='fas fa-times-circle text-red-600 mr-3'></i>";
                    echo "<span class='text-red-800'>✗ ไม่พบ{$description}</span>";
                    echo "</div>";
                    $all_tables_exist = false;
                }
            }
            
            // Test 3: Sample data query
            if ($all_tables_exist) {
                echo "<div class='mt-4 p-4 bg-blue-50 rounded-lg'>";
                echo "<div class='font-semibold text-blue-800 mb-3'>ตัวอย่างข้อมูลโซนและพนักงาน:</div>";
                
                $stmt = $conn->prepare("
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
                $stmt->execute();
                $sample_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($sample_zones)) {
                    echo "<div class='grid gap-3'>";
                    foreach ($sample_zones as $zone) {
                        echo "<div class='bg-white p-3 rounded border-l-4 border-blue-400'>";
                        echo "<div class='font-medium'>{$zone['zone_code']}: {$zone['zone_name']}</div>";
                        echo "<div class='text-sm text-gray-600'>";
                        echo "พนักงาน ({$zone['employee_count']} คน): ";
                        echo $zone['employees'] ?: '<span class="italic text-gray-400">ยังไม่มีพนักงานรับผิดชอบ</span>';
                        echo "</div>";
                        echo "</div>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='text-gray-600'>ยังไม่มีข้อมูลตัวอย่าง</div>";
                }
                echo "</div>";
            }
            
            echo "</div>"; // Close space-y-3
            
        } catch (Exception $e) {
            echo "<div class='flex items-center p-3 bg-red-50 rounded'>";
            echo "<i class='fas fa-exclamation-triangle text-red-600 mr-3'></i>";
            echo "<span class='text-red-800'>ข้อผิดพลาด: " . $e->getMessage() . "</span>";
            echo "</div>";
        }
        
        echo "</div>"; // Close test section
        ?>
        
        <!-- Features Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- What's New -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-star text-yellow-500 mr-2"></i>ฟีเจอร์ใหม่ที่เพิ่ม
                </h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start">
                        <i class="fas fa-users text-blue-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium">แสดงพนักงานในรายการโซน</div>
                            <div class="text-gray-600">เห็นทันทีว่าแต่ละโซนมีพนักงานคนไหนรับผิดชอบ</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <i class="fas fa-edit text-green-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium">จัดการพนักงานในโซน</div>
                            <div class="text-gray-600">เพิ่ม/ลบ พนักงานในโซนได้ผ่านหน้าแก้ไขโซน</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <i class="fas fa-tags text-purple-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium">ประเภทการมอบหมาย</div>
                            <div class="text-gray-600">หลัก, สำรอง, สนับสนุน พร้อมสีแยกแยะ</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <i class="fas fa-sync-alt text-orange-600 mr-3 mt-1"></i>
                        <div>
                            <div class="font-medium">อัปเดตแบบ Real-time</div>
                            <div class="text-gray-600">เปลี่ยนแปลงข้อมูลและเห็นผลทันที</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- How to Use -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-question-circle text-blue-600 mr-2"></i>วิธีใช้งาน
                </h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">1</div>
                        <div>
                            <div class="font-medium">เข้าหน้าจัดการโซน</div>
                            <div class="text-gray-600">ดูรายการโซนและพนักงานที่รับผิดชอบ</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">2</div>
                        <div>
                            <div class="font-medium">กดปุ่ม "แก้ไข" โซน</div>
                            <div class="text-gray-600">เข้าสู่โหมดแก้ไขโซนที่ต้องการ</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">3</div>
                        <div>
                            <div class="font-medium">จัดการพนักงาน</div>
                            <div class="text-gray-600">เพิ่ม/ลบ พนักงานในส่วน "พนักงานที่รับผิดชอบ"</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">4</div>
                        <div>
                            <div class="font-medium">บันทึกการเปลี่ยนแปลง</div>
                            <div class="text-gray-600">ระบบจะรีเฟรชและแสดงข้อมูลใหม่</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Links -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-external-link-alt mr-2"></i>ลิงก์ทดสอบ
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="pages/zones.php" 
                   class="flex flex-col items-center p-4 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-center">
                    <i class="fas fa-map-marked-alt text-2xl mb-2"></i>
                    <span class="font-medium">จัดการโซน</span>
                    <span class="text-xs mt-1">ระบบหลัก</span>
                </a>
                
                <a href="pages/zones_enhanced.php" 
                   class="flex flex-col items-center p-4 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors text-center">
                    <i class="fas fa-users-cog text-2xl mb-2"></i>
                    <span class="font-medium">โซนขั้นสูง</span>
                    <span class="text-xs mt-1">ระบบครบครัน</span>
                </a>
                
                <a href="update_zones_employees.php" 
                   class="flex flex-col items-center p-4 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors text-center">
                    <i class="fas fa-sync-alt text-2xl mb-2"></i>
                    <span class="font-medium">อัปเดตข้อมูล</span>
                    <span class="text-xs mt-1">ข้อมูลล่าสุด</span>
                </a>
                
                <a href="demo_zone_management.php" 
                   class="flex flex-col items-center p-4 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition-colors text-center">
                    <i class="fas fa-eye text-2xl mb-2"></i>
                    <span class="font-medium">Demo</span>
                    <span class="text-xs mt-1">ตัวอย่าง</span>
                </a>
            </div>
            
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-lightbulb text-yellow-600 mr-3 mt-1"></i>
                    <div class="text-yellow-800">
                        <div class="font-semibold mb-1">เทคนิคการใช้งาน:</div>
                        <div class="text-sm">
                            หากต้องการทดสอบ กดไปที่ "จัดการโซน" → เลือกโซนใดก็ได้ → กด "แก้ไข" → 
                            ลงไปด้านล่างจะเห็นส่วน "พนักงานที่รับผิดชอบ" ลองเพิ่ม/ลบ พนักงานดู!
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

</body>
</html> 