<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตข้อมูลโซนและพนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<div class="min-h-screen py-6 px-4">
    <div class="max-w-6xl mx-auto">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-green-600 text-white p-6 rounded-lg shadow-lg mb-6">
            <h1 class="text-2xl font-bold mb-2">
                <i class="fas fa-sync-alt mr-3"></i>อัปเดตข้อมูลโซนและพนักงาน
            </h1>
            <p class="text-blue-100">เพิ่มโซนย่อยรายละเอียดและผูกพนักงานกับโซนตามข้อมูลจริง</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_data'])) {
            
            echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6'>";
            echo "<h2 class='text-xl font-bold text-blue-800 mb-4'>🔄 กำลังอัปเดตข้อมูล...</h2>";
            
            try {
                require_once 'config/config.php';
                
                if (!$conn) {
                    throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ - กรุณาเปิด MySQL ก่อน");
                }
                
                echo "<div class='text-green-600 mb-3'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</div>";
                
                // Detailed zones data
                $zones = [
                    ['พัฒนา1', 'สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์', 'urban', '#3B82F6'],
                    ['พัฒนา2', 'สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง', 'urban', '#1E40AF'],
                    ['พัฒนา3', 'ในเมืองทอง -ปั้มปตท. เฉพาะฝั่งซ้าย', 'urban', '#2563EB'],
                    ['พัฒนา4', 'ปตท. - ซ.ศรีธรรมโศก 2 ฝั่ง+ขวา', 'urban', '#3B82F6'],
                    ['พัฒนา5', 'ศรีธรรมโศก 2 - คลองป่าเหล้า ฝั่ง-ขวา', 'urban', '#1E40AF'],
                    ['พัฒนา6', 'คลองป่าเหล้า - โรงแรมแกรมายโฮม +คอนโดปภัสสร', 'urban', '#2563EB'],
                    ['พัฒนา7', 'เคหะ+ศุภาลับรีม่า+ทวินโลตัส+โตโยต้า', 'urban', '#3B82F6'],
                    ['พัฒนา8', 'โลตัส +สะพานคูพาย-โฮมโปร ทั้งฝั่ง-ขวา', 'urban', '#1E40AF'],
                    ['พัฒนา9', 'สะพานแสงจันทร์ - โฮมโปร ฝั่ง+ ขวา', 'urban', '#2563EB'],
                    ['พัฒนา10', 'พัฒนาการคูขวางไปถึงสำเพ็ง+สารีบุตร+พัฒนาการคลัง', 'urban', '#3B82F6'],
                    
                    ['ราชดำเนิน1', 'เส้นศรีธรรมโศกทั้งเส้น', 'urban', '#10B981'],
                    ['ราชดำเนิน2', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'urban', '#059669'],
                    ['ราชดำเนิน3', 'เส้นราชดำเนิน เสมาเมือง -ประตูชัย', 'urban', '#047857'],
                    ['ราชดำเนิน4', 'ป่าขอม+ป้อมเพชร+หัวหลาง', 'urban', '#10B981'],
                    ['ราชดำเนิน5', 'รพ.มหาราช', 'urban', '#059669'],
                    ['ราชดำเนิน6', 'ประตูชัย - พัฒนา 1', 'urban', '#047857'],
                    ['ราชดำเนิน7', 'ปตทหัวถนน +ถนนนครศรีปากพนัง', 'urban', '#10B981'],
                    ['ราชดำเนิน8', 'ศรีธรรมโศกทั้งเส้น', 'urban', '#059669'],
                    ['ราชดำเนิน9', 'เส้นศรีธรรมราชทั้งเส้น+พระธาตุ', 'urban', '#047857'],
                    ['ราชดำเนิน10', 'ราชดำเนินทั้งเส้น + นครศรีปากพนัง', 'urban', '#10B981']
                ];
                
                // Insert zones
                echo "<div class='my-4 text-blue-800 font-medium'>🗺️ กำลังเพิ่มโซนย่อยรายละเอียด...</div>";
                
                $zone_stmt = $conn->prepare("INSERT IGNORE INTO zone_area (zone_code, zone_name, zone_type, color_code, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                
                $zone_count = 0;
                foreach ($zones as $zone) {
                    try {
                        $zone_stmt->execute([$zone[0], $zone[1], $zone[2], $zone[3], $zone[1]]);
                        if ($zone_stmt->rowCount() > 0) {
                            $zone_count++;
                        }
                        echo "<div class='text-green-600 text-xs'>✓ {$zone[0]}: {$zone[1]}</div>";
                    } catch (PDOException $e) {
                        echo "<div class='text-orange-600 text-xs'>⚠ {$zone[0]}: มีอยู่แล้ว</div>";
                    }
                }
                echo "<div class='text-green-600 mt-2'>✓ เพิ่มโซนใหม่: {$zone_count} โซน</div>";
                
                // Clear old employee data
                echo "<div class='my-4 text-blue-800 font-medium'>🧹 ลบข้อมูลพนักงานเก่า...</div>";
                $conn->exec("DELETE FROM zone_employee_assignments");
                $conn->exec("DELETE FROM delivery_zone_employees");
                echo "<div class='text-green-600'>✓ ลบข้อมูลเก่าเสร็จ</div>";
                
                // Detailed employees data with zone mapping
                $employees = [
                    ['664921T000009', 'อริษา บัวเพชร', 'SPT', 'สาว', '001A', 'พัฒนา1'],
                    ['664921T000010', 'ธวัชชัย สัจจารักษ์', 'SPT', 'นุ๊ก', '001B', 'พัฒนา2'],
                    ['664921T000011', 'ธนวัต รัตนพันธ์', 'SPT', 'เกณฑ์', '001C', 'พัฒนา3'],
                    ['664921T000012', 'ศุภรัตน์ จักราพงษ์', 'SPT', 'เนส', '002A', 'พัฒนา4'],
                    ['664921T000013', 'อนาวิล ฮาลาบี', 'SPT', 'ยาส', '002B', 'พัฒนา5'],
                    ['664921T000014', 'ปิยาวัฒน์ ชูเมฆา', 'SPT', 'อ้วน', '003A', 'พัฒนา6'],
                    ['664921T000015', 'ณัฐพล พลสังข์', 'SPT', 'กอล์ฟ', '003B', 'พัฒนา7'],
                    ['664921T000016', 'ตุลา ดำเกิงลักษณ์', 'SPT', 'บังมีน', '003C', 'พัฒนา8'],
                    ['664921T000017', 'อับดุลรอหีม เบ็ญโส๊ะ', 'SPT', 'ฮีม', '004A', 'ราชดำเนิน1'],
                    ['664921T000018', 'วีรวุฒิ หมื่นยกพล', 'SPT', 'เอ็ม', '004B', 'ราชดำเนิน2'],
                    ['664921T000019', 'ณัฐพล ดาราวรรณ', 'SPT', 'นิด', '004C', 'ราชดำเนิน3'],
                    ['664921T000020', 'นันทิยา สุพงษ์', 'SPT', 'นัน', '004D', 'ราชดำเนิน4'],
                    ['664921T000021', 'กษิดิศ ทิพย์สุราษฎร์', 'SPT', 'ฮัท', '005A', 'ราชดำเนิน5'],
                    ['664921T000022', 'ณัฐพงศ์ สุทธิพิทักษ์', 'SPT', 'เกมส์', '005B', 'ราชดำเนิน6'],
                    ['664921T000023', 'อติกันต์ อ่อนทา', 'SPT', 'กอง', '005C', 'ราชดำเนิน7'],
                    ['664921T000024', 'สุภาพร สมาธิ', 'SPT+C', 'ตั้ก', '888A', 'พัฒนา9'],
                    ['664921T000025', 'ปราโมทย์ พรหมดำ', 'SPT+C', 'เบียร์', '888B', 'พัฒนา10'],
                    ['664921T000030', 'ไพฑูรย์ สุวรรณปากแพรก', 'SPT+S', 'หนุ่ม', '888C', 'ราชดำเนิน8'],
                    ['664921T000027', 'สมชาย ตำราเรียง', 'SPT+S', 'หมาน', '888D', 'ราชดำเนิน9'],
                    ['664921T000028', 'ณัฐฐากาญจน์ ล่องโลก', 'SPT+S', 'นิว', '888E', 'ราชดำเนิน10']
                ];
                
                echo "<div class='my-4 text-blue-800 font-medium'>👥 กำลังเพิ่มข้อมูลพนักงานใหม่...</div>";
                
                $emp_stmt = $conn->prepare("INSERT INTO delivery_zone_employees (employee_code, employee_name, position, nickname, zone_area, zone_code, status, hire_date) VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE())");
                
                $emp_count = 0;
                foreach ($employees as $emp) {
                    try {
                        // Get zone area from zone code (first part before number)
                        $zone_area = preg_replace('/\d+$/', '', $emp[5]); // Remove trailing number
                        $emp_stmt->execute([$emp[0], $emp[1], $emp[2], $emp[3], $emp[5], $zone_area]);
                        $emp_count++;
                        echo "<div class='text-green-600 text-xs'>✓ {$emp[1]} ({$emp[3]}) → {$emp[5]}</div>";
                    } catch (PDOException $e) {
                        echo "<div class='text-red-600 text-xs'>✗ {$emp[1]}: {$e->getMessage()}</div>";
                    }
                }
                echo "<div class='text-green-600 mt-2'>✓ เพิ่มพนักงาน: {$emp_count} คน</div>";
                
                // Auto-assign employees to zones
                echo "<div class='my-4 text-blue-800 font-medium'>🔗 กำลังผูกพนักงานกับโซน...</div>";
                
                try {
                    $assign_stmt = $conn->prepare("
                        INSERT INTO zone_employee_assignments (zone_id, employee_id, assignment_type, start_date, is_active, workload_percentage)
                        SELECT za.id, dze.id, 'primary', CURDATE(), TRUE, 100.00
                        FROM delivery_zone_employees dze
                        JOIN zone_area za ON dze.zone_area = za.zone_code
                        WHERE dze.status = 'active'
                    ");
                    $assign_stmt->execute();
                    $assigned = $assign_stmt->rowCount();
                    echo "<div class='text-green-600'>✓ ผูกพนักงานกับโซน: {$assigned} การมอบหมาย</div>";
                } catch (PDOException $e) {
                    echo "<div class='text-orange-600'>⚠ การผูกโซน: " . $e->getMessage() . "</div>";
                }
                
                // Final verification
                echo "<div class='my-4 text-blue-800 font-medium'>📊 ตรวจสอบผลลัพธ์...</div>";
                
                $total_zones = $conn->query("SELECT COUNT(*) FROM zone_area")->fetchColumn();
                $total_employees = $conn->query("SELECT COUNT(*) FROM delivery_zone_employees")->fetchColumn();
                $total_assignments = $conn->query("SELECT COUNT(*) FROM zone_employee_assignments WHERE is_active = TRUE")->fetchColumn();
                
                echo "<div class='mt-6 p-6 bg-green-100 border border-green-200 rounded-lg'>";
                echo "<div class='text-green-800 font-bold text-lg mb-3'>🎉 อัปเดตข้อมูลสำเร็จ!</div>";
                echo "<div class='grid grid-cols-3 gap-4 text-sm'>";
                echo "<div class='text-center'><div class='text-3xl font-bold text-blue-600'>{$total_zones}</div><div class='text-gray-600'>โซนทั้งหมด</div></div>";
                echo "<div class='text-center'><div class='text-3xl font-bold text-green-600'>{$total_employees}</div><div class='text-gray-600'>พนักงาน</div></div>";
                echo "<div class='text-center'><div class='text-3xl font-bold text-purple-600'>{$total_assignments}</div><div class='text-gray-600'>การมอบหมาย</div></div>";
                echo "</div>";
                
                // Show zone breakdown
                echo "<div class='mt-4'>";
                echo "<h4 class='font-semibold mb-2'>สรุปโซนและพนักงาน:</h4>";
                $zone_summary = $conn->query("
                    SELECT za.zone_code, za.zone_name, COUNT(dze.id) as employee_count
                    FROM zone_area za
                    LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
                    LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id
                    GROUP BY za.id
                    ORDER BY za.zone_code
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<div class='grid grid-cols-2 gap-2 text-xs'>";
                foreach ($zone_summary as $zone) {
                    $color = strpos($zone['zone_code'], 'พัฒนา') !== false ? 'bg-blue-50 text-blue-700' : 'bg-green-50 text-green-700';
                    echo "<div class='p-2 {$color} rounded'>";
                    echo "<div class='font-semibold'>{$zone['zone_code']}</div>";
                    echo "<div class='text-xs'>{$zone['employee_count']} พนักงาน</div>";
                    echo "</div>";
                }
                echo "</div>";
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
            // Show update form
            ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Preview Zones -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-map-marked-alt text-blue-600 mr-2"></i>โซนที่จะเพิ่ม (20 โซน)
                    </h3>
                    
                    <div class="space-y-2 text-sm max-h-64 overflow-y-auto">
                        <div class="font-semibold text-blue-600">โซนพัฒนา (10 โซน):</div>
                        <div class="pl-4 space-y-1 text-xs">
                            <div>พัฒนา1: สีแยกคูขวางฝั่งซ้าย - จนสะพานไดโนเสาร์</div>
                            <div>พัฒนา2: สะพานไดโนเสาร์ ฝั่งขวา+ซ้ายไปถึงเมืองทอง</div>
                            <div>พัฒนา3: ในเมืองทอง -ปั้มปตท. เฉพาะฝั่งซ้าย</div>
                            <div>พัฒนา4: ปตท. - ซ.ศรีธรรมโศก 2 ฝั่ง+ขวา</div>
                            <div>พัฒนา5: ศรีธรรมโศก 2 - คลองป่าเหล้า ฝั่ง-ขวา</div>
                            <div>พัฒนา6: คลองป่าเหล้า - โรงแรมแกรมายโฮม +คอนโดปภัสสร</div>
                            <div>พัฒนา7: เคหะ+ศุภาลับรีม่า+ทวินโลตัส+โตโยต้า</div>
                            <div>พัฒนา8: โลตัส +สะพานคูพาย-โฮมโปร ทั้งฝั่ง-ขวา</div>
                            <div>พัฒนา9: สะพานแสงจันทร์ - โฮมโปร ฝั่ง+ ขวา</div>
                            <div>พัฒนา10: พัฒนาการคูขวางไปถึงสำเพ็ง+สารีบุตร+พัฒนาการคลัง</div>
                        </div>
                        
                        <div class="font-semibold text-green-600 mt-3">โซนราชดำเนิน (10 โซน):</div>
                        <div class="pl-4 space-y-1 text-xs">
                            <div>ราชดำเนิน1: เส้นศรีธรรมโศกทั้งเส้น</div>
                            <div>ราชดำเนิน2: เส้นศรีธรรมราชทั้งเส้น+พระธาตุ</div>
                            <div>ราชดำเนิน3: เส้นราชดำเนิน เสมาเมือง -ประตูชัย</div>
                            <div>ราชดำเนิน4: ป่าขอม+ป้อมเพชร+หัวหลาง</div>
                            <div>ราชดำเนิน5: รพ.มหาราช</div>
                            <div>ราชดำเนิน6: ประตูชัย - พัฒนา 1</div>
                            <div>ราชดำเนิน7: ปตทหัวถนน +ถนนนครศรีปากพนัง</div>
                            <div>ราชดำเนิน8: ศรีธรรมโศกทั้งเส้น</div>
                            <div>ราชดำเนิน9: เส้นศรีธรรมราชทั้งเส้น+พระธาตุ</div>
                            <div>ราชดำเนิน10: ราชดำเนินทั้งเส้น + นครศรีปากพนัง</div>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Employees -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-users text-green-600 mr-2"></i>พนักงานที่จะเพิ่ม (20 คน)
                    </h3>
                    
                    <div class="space-y-2 text-sm max-h-64 overflow-y-auto">
                        <div class="space-y-1 text-xs">
                            <div><strong>SPT (15 คน):</strong></div>
                            <div class="pl-4">อริษา บัวเพชร (สาว) → พัฒนา1</div>
                            <div class="pl-4">ธวัชชัย สัจจารักษ์ (นุ๊ก) → พัฒนา2</div>
                            <div class="pl-4">ธนวัต รัตนพันธ์ (เกณฑ์) → พัฒนา3</div>
                            <div class="pl-4">ศุภรัตน์ จักราพงษ์ (เนส) → พัฒนา4</div>
                            <div class="pl-4">อนาวิล ฮาลาบี (ยาส) → พัฒนา5</div>
                            <div class="pl-4">ปิยาวัฒน์ ชูเมฆา (อ้วน) → พัฒนา6</div>
                            <div class="pl-4">ณัฐพล พลสังข์ (กอล์ฟ) → พัฒนา7</div>
                            <div class="pl-4">ตุลา ดำเกิงลักษณ์ (บังมีน) → พัฒนา8</div>
                            <div class="pl-4">อับดุลรอหีม เบ็ญโส๊ะ (ฮีม) → ราชดำเนิน1</div>
                            <div class="pl-4">วีรวุฒิ หมื่นยกพล (เอ็ม) → ราชดำเนิน2</div>
                            <div class="pl-4">และอีก 5 คน...</div>
                            
                            <div class="mt-2"><strong>SPT+C (2 คน):</strong></div>
                            <div class="pl-4">สุภาพร สมาธิ (ตั้ก) → พัฒนา9</div>
                            <div class="pl-4">ปราโมทย์ พรหมดำ (เบียร์) → พัฒนา10</div>
                            
                            <div class="mt-2"><strong>SPT+S (3 คน):</strong></div>
                            <div class="pl-4">ไพฑูรย์ สุวรรณปากแพรก (หนุ่ม) → ราชดำเนิน8</div>
                            <div class="pl-4">สมชาย ตำราเรียง (หมาน) → ราชดำเนิน9</div>
                            <div class="pl-4">ณัฐฐากาญจน์ ล่องโลก (นิว) → ราชดำเนิน10</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Update Action -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-play text-orange-600 mr-2"></i>เริ่มอัปเดตข้อมูล
                </h3>
                
                <div class="mb-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                            <div class="text-yellow-800">
                                <div class="font-semibold">คำเตือน:</div>
                                <div class="text-sm mt-1">การอัปเดตนี้จะ <strong>ลบข้อมูลพนักงานเก่าทั้งหมด</strong> และเพิ่มข้อมูลใหม่ตามรายละเอียดที่คุณส่งมา</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-3">
                        การอัปเดตจะดำเนินการ:
                    </div>
                    <ul class="text-sm space-y-1 text-gray-700">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>เพิ่มโซนย่อย 20 โซน</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>ลบข้อมูลพนักงานเก่า</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>เพิ่มพนักงานใหม่ 20 คน</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>ผูกพนักงานกับโซนตามข้อมูลจริง</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>อัปเดตตำแหน่งและรหัสโซน</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <button type="submit" name="update_data" 
                            class="w-full bg-gradient-to-r from-orange-600 to-red-600 text-white py-4 px-6 rounded-lg hover:from-orange-700 hover:to-red-700 transition-all transform hover:scale-105 font-semibold text-lg">
                        <i class="fas fa-sync-alt mr-2"></i>🚀 เริ่มอัปเดตข้อมูล
                    </button>
                </form>
            </div>
            
            <?php
        }
        ?>
        
        <!-- Quick Links -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-external-link-alt mr-2"></i>ลิงก์ด่วน
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="pages/zones_enhanced.php" 
                   class="flex flex-col items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-users-cog text-xl mb-2"></i>
                    <span class="text-sm">จัดการโซน</span>
                </a>
                
                <a href="demo_zone_management.php" 
                   class="flex flex-col items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-eye text-xl mb-2"></i>
                    <span class="text-sm">Demo</span>
                </a>
                
                <a href="pages/leaflet_map.php" 
                   class="flex flex-col items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-map text-xl mb-2"></i>
                    <span class="text-sm">แผนที่</span>
                </a>
                
                <a href="debug_zones.php" 
                   class="flex flex-col items-center p-3 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition-colors">
                    <i class="fas fa-bug text-xl mb-2"></i>
                    <span class="text-sm">Debug</span>
                </a>
            </div>
        </div>
        
    </div>
</div>

</body>
</html> 