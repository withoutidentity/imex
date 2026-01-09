<?php
$page_title = 'หน้าหลัก';
require_once 'config/config.php';
include 'includes/header.php';

// Get dashboard statistics
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total_addresses FROM delivery_address");
    $stmt->execute();
    $total_addresses = $stmt->fetch(PDO::FETCH_ASSOC)['total_addresses'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_zones FROM zone_area");
    $stmt->execute();
    $total_zones = $stmt->fetch(PDO::FETCH_ASSOC)['total_zones'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_riders FROM delivery_zone_employees WHERE status = 'active'");
    $stmt->execute();
    $total_riders = $stmt->fetch(PDO::FETCH_ASSOC)['total_riders'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_deliveries FROM delivery_history WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $today_deliveries = $stmt->fetch(PDO::FETCH_ASSOC)['total_deliveries'];
    
} catch (PDOException $e) {
    $total_addresses = 0;
    $total_zones = 0;
    $total_riders = 0;
    $today_deliveries = 0;
}
?>

<div class="fadeIn">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold mb-2">ยินดีต้อนรับสู่ระบบจัดการพัสดุ</h1>
                <p class="text-blue-100">Smart Delivery Zone Planner - จัดเส้นทางการจัดส่งอัตโนมัติ</p>
            </div>
            <div class="hidden lg:block">
                <i class="fas fa-route text-5xl opacity-20"></i>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md dashboard-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">จำนวนที่อยู่ทั้งหมด</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo number_format($total_addresses); ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-map-pin text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md dashboard-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">จำนวนโซนทั้งหมด</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo number_format($total_zones); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-map-marked-alt text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md dashboard-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Rider ที่ใช้งาน</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo number_format($total_riders); ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md dashboard-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">จัดส่งวันนี้</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo number_format($today_deliveries); ?></p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <i class="fas fa-truck text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <!--<div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold mb-4">การทำงานด่วน</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <a href="pages/import.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg quick-action-card">
                <div class="bg-blue-600 text-white p-3 rounded-lg mr-4">
                    <i class="fas fa-file-import text-xl"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-800">นำเข้าข้อมูล</h3>
                    <p class="text-sm text-blue-600">อัพโหลด Excel/CSV</p>
                </div>
            </a>
            
            <a href="pages/excel_processor.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg quick-action-card">
                <div class="bg-green-600 text-white p-3 rounded-lg mr-4">
                    <i class="fas fa-file-excel text-xl"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-green-800">ประมวลผล Excel</h3>
                    <p class="text-sm text-green-600">แปลงพิกัด + จัดโซน</p>
                </div>
            </a>
            
            <a href="pages/zones.php" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg quick-action-card">
                <div class="bg-purple-600 text-white p-3 rounded-lg mr-4">
                    <i class="fas fa-layer-group text-xl"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-purple-800">จัดโซน</h3>
                    <p class="text-sm text-purple-600">จัดกลุ่มพื้นที่</p>
                </div>
            </a>
            
            <a href="pages/route_planner.php" class="flex items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg quick-action-card">
                <div class="bg-orange-600 text-white p-3 rounded-lg mr-4">
                    <i class="fas fa-route text-xl"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-orange-800">วางแผนเส้นทาง</h3>
                    <p class="text-sm text-orange-600">เส้นทางประหยัด</p>
                </div>
            </a>
        </div>
    </div>-->

    <!-- Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">กิจกรรมล่าสุด</h2>
            <div class="space-y-4">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5");
                    $stmt->execute();
                    $recent_imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($recent_imports) > 0) {
                        foreach ($recent_imports as $import) {
                            echo '<div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">';
                            echo '<div class="bg-blue-100 p-2 rounded-full">';
                            echo '<i class="fas fa-file-import text-blue-600"></i>';
                            echo '</div>';
                            echo '<div class="flex-1">';
                            echo '<p class="font-medium">' . htmlspecialchars($import['filename']) . '</p>';
                            echo '<p class="text-sm text-gray-600">' . formatDate($import['created_at']) . '</p>';
                            echo '</div>';
                            echo '<span class="text-sm text-green-600">' . $import['total_records'] . ' รายการ</span>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="text-center py-8 text-gray-500">';
                        echo '<i class="fas fa-inbox text-4xl mb-4"></i>';
                        echo '<p>ยังไม่มีกิจกรรมล่าสุด</p>';
                        echo '</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="text-center py-8 text-gray-500">';
                    echo '<i class="fas fa-exclamation-triangle text-4xl mb-4"></i>';
                    echo '<p>ไม่สามารถโหลดข้อมูลได้</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">สถานะระบบ</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="bg-green-100 p-2 rounded-full">
                            <i class="fas fa-database text-green-600"></i>
                        </div>
                        <span class="font-medium">ฐานข้อมูล</span>
                    </div>
                    <span class="text-sm text-green-600 font-medium">เชื่อมต่อแล้ว</span>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="bg-purple-100 p-2 rounded-full">
                            <i class="fas fa-server text-purple-600"></i>
                        </div>
                        <span class="font-medium">เซิร์ฟเวอร์</span>
                    </div>
                    <span class="text-sm text-purple-600 font-medium">ทำงานปกติ</span>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="bg-orange-100 p-2 rounded-full">
                            <i class="fas fa-shield-alt text-orange-600"></i>
                        </div>
                        <span class="font-medium">ระบบความปลอดภัย</span>
                    </div>
                    <span class="text-sm text-orange-600 font-medium">ปกติ</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 