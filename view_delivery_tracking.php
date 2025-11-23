<?php
$page_title = 'ข้อมูลตาราง delivery_tracking';
require_once 'config/config.php';
include 'includes/header.php';

try {
    // ตรวจสอบว่าตาราง delivery_tracking มีอยู่หรือไม่
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_tracking'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        throw new Exception("ตาราง delivery_tracking ไม่มีในฐานข้อมูล กรุณาติดตั้งก่อน");
    }

    // ดึงข้อมูลจากตาราง delivery_tracking
    $query = "SELECT 
        id,
        tracking_id,
        awb_number,
        current_status,
        current_location_lat,
        current_location_lng,
        current_location_address,
        estimated_delivery_time,
        actual_delivery_time,
        delivery_attempts,
        priority_level,
        service_type,
        package_weight,
        cod_amount,
        cod_status,
        special_instructions,
        failure_reason,
        created_at,
        updated_at
    FROM delivery_tracking 
    ORDER BY created_at DESC
    LIMIT 50";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // นับจำนวนตามสถานะ
    $statusQuery = "SELECT current_status, COUNT(*) as count FROM delivery_tracking GROUP BY current_status";
    $statusStmt = $conn->prepare($statusQuery);
    $statusStmt->execute();
    $statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // ข้อมูลสถิติ
    $totalQuery = "SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN current_status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN current_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN cod_amount > 0 THEN cod_amount ELSE 0 END) as total_cod_amount,
        AVG(package_weight) as avg_weight
    FROM delivery_tracking";
    
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->execute();
    $stats = $totalStmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusText($status) {
    $statusMap = [
        'pending' => 'รอดำเนินการ',
        'picked_up' => 'เก็บของแล้ว',
        'in_transit' => 'อยู่ระหว่างทาง',
        'out_for_delivery' => 'ออกจัดส่ง',
        'delivered' => 'จัดส่งสำเร็จ',
        'failed' => 'จัดส่งไม่สำเร็จ',
        'returned' => 'ส่งคืน',
        'cancelled' => 'ยกเลิก'
    ];
    return $statusMap[$status] ?? $status;
}

// ฟังก์ชันแปลงระดับความสำคัญ
function getPriorityText($priority) {
    $priorityMap = [
        'normal' => 'ปกติ',
        'urgent' => 'ด่วน',
        'express' => 'ด่วนพิเศษ',
        'standard' => 'มาตรฐาน'
    ];
    return $priorityMap[$priority] ?? $priority;
}

// ฟังก์ชันแปลงประเภทบริการ
function getServiceTypeText($service) {
    $serviceMap = [
        'standard' => 'มาตรฐาน',
        'express' => 'ด่วนพิเศษ',
        'same_day' => 'ส่งวันเดียว',
        'next_day' => 'ส่งวันถัดไป'
    ];
    return $serviceMap[$service] ?? $service;
}

// ฟังก์ชันกำหนดสีสถานะ
function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'picked_up' => 'bg-blue-100 text-blue-800',
        'in_transit' => 'bg-purple-100 text-purple-800',
        'out_for_delivery' => 'bg-indigo-100 text-indigo-800',
        'delivered' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'returned' => 'bg-orange-100 text-orange-800',
        'cancelled' => 'bg-gray-100 text-gray-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}
?>

<div class="fadeIn">
    <!-- Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">ข้อมูลตาราง delivery_tracking</h1>
                <p class="text-gray-600 mt-2">แสดงข้อมูลการติดตามการจัดส่งทั้งหมด</p>
            </div>
            <div class="flex space-x-2">
                <a href="install_tracking_table.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-download mr-2"></i>ติดตั้งตาราง
                </a>
                <a href="pages/delivery_tracking_analysis.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-chart-line mr-2"></i>วิเคราะห์ข้อมูล
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <strong>ข้อผิดพลาด:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>

    <!-- สถิติ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md">
            <div class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['total_deliveries']); ?></div>
            <div class="text-sm text-gray-600">รายการทั้งหมด</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md">
            <div class="text-2xl font-bold text-green-600"><?php echo number_format($stats['delivered_count']); ?></div>
            <div class="text-sm text-gray-600">จัดส่งสำเร็จ</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md">
            <div class="text-2xl font-bold text-red-600"><?php echo number_format($stats['failed_count']); ?></div>
            <div class="text-sm text-gray-600">จัดส่งไม่สำเร็จ</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md">
            <div class="text-2xl font-bold text-purple-600">฿<?php echo number_format($stats['total_cod_amount'], 2); ?></div>
            <div class="text-sm text-gray-600">ยอด COD รวม</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md">
            <div class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['avg_weight'], 1); ?> กก.</div>
            <div class="text-sm text-gray-600">น้ำหนักเฉลี่ย</div>
        </div>
    </div>

    <!-- สถิติตามสถานะ -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">สถิติตามสถานะ</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            <?php foreach ($statusStats as $stat): ?>
                <div class="text-center p-3 rounded-lg <?php echo getStatusColor($stat['current_status']); ?>">
                    <div class="font-bold text-lg"><?php echo $stat['count']; ?></div>
                    <div class="text-xs"><?php echo getStatusText($stat['current_status']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ตารางข้อมูล -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">รายการการจัดส่ง (50 รายการล่าสุด)</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking ID</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AWB</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ที่อยู่ปัจจุบัน</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">พิกัด</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">น้ำหนัก</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">COD</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ความสำคัญ</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">อัพเดท</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                <?php echo htmlspecialchars($delivery['tracking_id']); ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($delivery['awb_number']); ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo getStatusColor($delivery['current_status']); ?>">
                                    <?php echo getStatusText($delivery['current_status']); ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-900 max-w-xs truncate">
                                <?php echo htmlspecialchars($delivery['current_location_address'] ?? '-'); ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                <?php if ($delivery['current_location_lat'] && $delivery['current_location_lng']): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $delivery['current_location_lat']; ?>,<?php echo $delivery['current_location_lng']; ?>" 
                                       target="_blank" class="text-blue-600 hover:text-blue-800">
                                        <?php echo number_format($delivery['current_location_lat'], 4); ?>, 
                                        <?php echo number_format($delivery['current_location_lng'], 4); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $delivery['package_weight'] ? number_format($delivery['package_weight'], 1) . ' กก.' : '-'; ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($delivery['cod_amount'] > 0): ?>
                                    <span class="text-green-600 font-medium">฿<?php echo number_format($delivery['cod_amount'], 2); ?></span>
                                    <br><span class="text-xs text-gray-500"><?php echo ucfirst($delivery['cod_status']); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm">
                                <div><?php echo getPriorityText($delivery['priority_level']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo getServiceTypeText($delivery['service_type']); ?></div>
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y H:i', strtotime($delivery['updated_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($deliveries)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-4"></i>
                <p>ไม่มีข้อมูลในตาราง delivery_tracking</p>
                <p class="text-sm mt-2">กรุณาติดตั้งข้อมูลตัวอย่างก่อน</p>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>


