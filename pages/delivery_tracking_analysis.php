<?php
$page_title = 'วิเคราะห์ข้อมูล Delivery Tracking';
require_once '../config/config.php';

// Detect if we're in pages directory
$base_path = (basename(dirname(__FILE__)) == 'pages') ? '../' : '';
require_once $base_path . 'config/config.php';
include $base_path . 'includes/header.php';

// Handle filter request
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$filter_service = $_GET['service'] ?? 'all';
$filter_date = $_GET['date'] ?? date('Y-m-d');

// ดึงข้อมูลสรุปจาก delivery_tracking
function getTrackingStats() {
    global $conn;
    
    $stats = [];
    
    try {
        // สถิติรวม
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total_shipments,
            COUNT(CASE WHEN current_status = 'delivered' THEN 1 END) as delivered,
            COUNT(CASE WHEN current_status = 'in_transit' THEN 1 END) as in_transit,
            COUNT(CASE WHEN current_status = 'out_for_delivery' THEN 1 END) as out_for_delivery,
            COUNT(CASE WHEN current_status = 'failed' THEN 1 END) as failed,
            COUNT(CASE WHEN current_status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN current_status = 'returned' THEN 1 END) as returned,
            COUNT(CASE WHEN current_status = 'cancelled' THEN 1 END) as cancelled,
            AVG(package_weight) as avg_weight,
            SUM(cod_amount) as total_cod,
            AVG(delivery_attempts) as avg_attempts
            FROM delivery_tracking 
            WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // สถิติตามระดับความสำคัญ
        $stmt = $conn->prepare("SELECT 
            priority_level,
            COUNT(*) as count,
            COUNT(CASE WHEN current_status = 'delivered' THEN 1 END) as delivered_count,
            ROUND(COUNT(CASE WHEN current_status = 'delivered' THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
            FROM delivery_tracking 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY priority_level");
        $stmt->execute();
        $stats['priority'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // สถิติตามประเภทบริการ
        $stmt = $conn->prepare("SELECT 
            service_type,
            COUNT(*) as count,
            COUNT(CASE WHEN current_status = 'delivered' THEN 1 END) as delivered_count,
            AVG(package_weight) as avg_weight,
            SUM(cod_amount) as total_cod
            FROM delivery_tracking 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY service_type");
        $stmt->execute();
        $stats['service'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // สถิติการจัดส่งตามโซน
        $stmt = $conn->prepare("SELECT 
            za.zone_name,
            za.zone_code,
            COUNT(dt.id) as shipment_count,
            COUNT(CASE WHEN dt.current_status = 'delivered' THEN 1 END) as delivered_count,
            AVG(dt.delivery_attempts) as avg_attempts
            FROM delivery_tracking dt
            LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
            LEFT JOIN zone_area za ON da.zone_id = za.id
            WHERE DATE(dt.created_at) = CURDATE()
            GROUP BY za.id, za.zone_name, za.zone_code
            ORDER BY shipment_count DESC");
        $stmt->execute();
        $stats['zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // สถิติความล่าช้า
        $stmt = $conn->prepare("SELECT 
            COUNT(CASE WHEN current_status = 'delivered' AND actual_delivery_time <= estimated_delivery_time THEN 1 END) as on_time,
            COUNT(CASE WHEN current_status = 'delivered' AND actual_delivery_time > estimated_delivery_time THEN 1 END) as late,
            COUNT(CASE WHEN estimated_delivery_time < NOW() AND current_status NOT IN ('delivered', 'cancelled') THEN 1 END) as overdue,
            AVG(CASE WHEN current_status = 'delivered' AND actual_delivery_time IS NOT NULL AND estimated_delivery_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, estimated_delivery_time, actual_delivery_time) END) as avg_delay_minutes
            FROM delivery_tracking");
        $stmt->execute();
        $stats['performance'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getTrackingStats: " . $e->getMessage());
    }
    
    return $stats;
}

// ดึงข้อมูลรายละเอียดการติดตาม
function getTrackingDetails($status = 'all', $priority = 'all', $service = 'all', $date = null) {
    global $conn;
    
    $whereConditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $whereConditions[] = "dt.current_status = ?";
        $params[] = $status;
    }
    
    if ($priority !== 'all') {
        $whereConditions[] = "dt.priority_level = ?";
        $params[] = $priority;
    }
    
    if ($service !== 'all') {
        $whereConditions[] = "dt.service_type = ?";
        $params[] = $service;
    }
    
    if ($date) {
        $whereConditions[] = "DATE(dt.created_at) = ?";
        $params[] = $date;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT 
        dt.tracking_id,
        dt.awb_number,
        da.recipient_name,
        da.address,
        da.district,
        dt.current_status,
        dt.priority_level,
        dt.service_type,
        dt.package_weight,
        dt.cod_amount,
        dt.delivery_attempts,
        dt.estimated_delivery_time,
        dt.actual_delivery_time,
        dt.current_location_lat,
        dt.current_location_lng,
        dt.current_location_address,
        dt.failure_reason,
        r.name as rider_name,
        za.zone_name,
        CASE 
            WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time <= dt.estimated_delivery_time 
            THEN 'ตรงเวลา'
            WHEN dt.current_status = 'delivered' AND dt.actual_delivery_time > dt.estimated_delivery_time 
            THEN 'ล่าช้า'
            WHEN dt.estimated_delivery_time < NOW() AND dt.current_status NOT IN ('delivered', 'cancelled')
            THEN 'เกินกำหนด'
            ELSE 'ปกติ'
        END as performance_status
        FROM delivery_tracking dt
        LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
        LEFT JOIN rider r ON dt.rider_id = r.id
        LEFT JOIN zone_area za ON da.zone_id = za.id
        $whereClause
        ORDER BY dt.created_at DESC
        LIMIT 100";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getTrackingDetails: " . $e->getMessage());
        return [];
    }
}

// ดึงข้อมูลตำแหน่งปัจจุบันสำหรับแผนที่
function getCurrentLocations() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT 
            dt.tracking_id,
            dt.awb_number,
            dt.current_status,
            dt.current_location_lat,
            dt.current_location_lng,
            dt.current_location_address,
            da.recipient_name,
            da.address as destination_address,
            r.name as rider_name,
            dt.priority_level
            FROM delivery_tracking dt
            LEFT JOIN delivery_address da ON dt.delivery_address_id = da.id
            LEFT JOIN rider r ON dt.rider_id = r.id
            WHERE dt.current_location_lat IS NOT NULL 
            AND dt.current_location_lng IS NOT NULL
            AND dt.current_status IN ('in_transit', 'out_for_delivery')
            ORDER BY dt.updated_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCurrentLocations: " . $e->getMessage());
        return [];
    }
}

$stats = getTrackingStats();
$tracking_details = getTrackingDetails($filter_status, $filter_priority, $filter_service, $filter_date);
$current_locations = getCurrentLocations();
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">วิเคราะห์ข้อมูล Delivery Tracking</h1>
                <p class="text-gray-600 mt-2">วิเคราะห์และติดตามสถานะการจัดส่งแบบ Real-time</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-chart-line text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- สถิติรวมวันนี้ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-shipping-fast text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['summary']['total_shipments'] ?? 0); ?></div>
                    <div class="text-gray-600">พัสดุทั้งหมดวันนี้</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['summary']['delivered'] ?? 0); ?></div>
                    <div class="text-gray-600">จัดส่งสำเร็จ</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-truck text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format(($stats['summary']['in_transit'] ?? 0) + ($stats['summary']['out_for_delivery'] ?? 0)); ?></div>
                    <div class="text-gray-600">กำลังจัดส่ง</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['summary']['failed'] ?? 0); ?></div>
                    <div class="text-gray-600">จัดส่งไม่สำเร็จ</div>
                </div>
            </div>
        </div>
    </div>

    <!-- กราฟและสถิติ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- สถิติตามสถานะ -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">สถิติตามสถานะการจัดส่ง</h2>
            <canvas id="statusChart" width="400" height="200"></canvas>
        </div>

        <!-- สถิติตามความสำคัญ -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">ประสิทธิภาพตามระดับความสำคัญ</h2>
            <div class="space-y-3">
                <?php if (!empty($stats['priority'])): ?>
                    <?php foreach ($stats['priority'] as $priority): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold capitalize"><?php echo htmlspecialchars($priority['priority_level']); ?></span>
                                <span class="text-sm text-gray-600 ml-2">(<?php echo $priority['count']; ?> รายการ)</span>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-green-600"><?php echo $priority['success_rate']; ?>%</div>
                                <div class="text-sm text-gray-600">อัตราสำเร็จ</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">ไม่มีข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- แผนที่ตำแหน่งปัจจุบัน -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">ตำแหน่งปัจจุบันของพัสดุ</h2>
        <div id="tracking-map" class="w-full h-96 rounded-lg"></div>
    </div>

    <!-- ตัวกรองข้อมูล -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">กรองข้อมูล</h2>
        
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">สถานะ</label>
                <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>รอจัดส่ง</option>
                    <option value="picked_up" <?php echo $filter_status === 'picked_up' ? 'selected' : ''; ?>>เก็บของแล้ว</option>
                    <option value="in_transit" <?php echo $filter_status === 'in_transit' ? 'selected' : ''; ?>>ระหว่างทาง</option>
                    <option value="out_for_delivery" <?php echo $filter_status === 'out_for_delivery' ? 'selected' : ''; ?>>กำลังจัดส่ง</option>
                    <option value="delivered" <?php echo $filter_status === 'delivered' ? 'selected' : ''; ?>>จัดส่งสำเร็จ</option>
                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>จัดส่งไม่สำเร็จ</option>
                    <option value="returned" <?php echo $filter_status === 'returned' ? 'selected' : ''; ?>>ส่งคืน</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
            </div>
            
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">ความสำคัญ</label>
                <select name="priority" id="priority" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="normal" <?php echo $filter_priority === 'normal' ? 'selected' : ''; ?>>ปกติ</option>
                    <option value="urgent" <?php echo $filter_priority === 'urgent' ? 'selected' : ''; ?>>ด่วน</option>
                    <option value="express" <?php echo $filter_priority === 'express' ? 'selected' : ''; ?>>ด่วนพิเศษ</option>
                    <option value="standard" <?php echo $filter_priority === 'standard' ? 'selected' : ''; ?>>มาตรฐาน</option>
                </select>
            </div>
            
            <div>
                <label for="service" class="block text-sm font-medium text-gray-700 mb-2">ประเภทบริการ</label>
                <select name="service" id="service" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $filter_service === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="standard" <?php echo $filter_service === 'standard' ? 'selected' : ''; ?>>มาตรฐาน</option>
                    <option value="express" <?php echo $filter_service === 'express' ? 'selected' : ''; ?>>ด่วน</option>
                    <option value="same_day" <?php echo $filter_service === 'same_day' ? 'selected' : ''; ?>>ส่งวันเดียว</option>
                    <option value="next_day" <?php echo $filter_service === 'next_day' ? 'selected' : ''; ?>>ส่งวันถัดไป</option>
                </select>
            </div>
            
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-2">วันที่</label>
                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-filter mr-2"></i>กรองข้อมูล
                </button>
            </div>
        </form>
    </div>

    <!-- รายละเอียดการติดตาม -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">รายละเอียดการติดตาม</h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AWB</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้รับ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ความสำคัญ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">น้ำหนัก</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">COD</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ประสิทธิภาพ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($tracking_details)): ?>
                        <?php foreach ($tracking_details as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    <?php echo htmlspecialchars($item['tracking_id']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($item['awb_number']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($item['recipient_name']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-gray-100 text-gray-800',
                                        'picked_up' => 'bg-blue-100 text-blue-800',
                                        'in_transit' => 'bg-yellow-100 text-yellow-800',
                                        'out_for_delivery' => 'bg-orange-100 text-orange-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'returned' => 'bg-purple-100 text-purple-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $statusText = [
                                        'pending' => 'รอจัดส่ง',
                                        'picked_up' => 'เก็บของแล้ว',
                                        'in_transit' => 'ระหว่างทาง',
                                        'out_for_delivery' => 'กำลังจัดส่ง',
                                        'delivered' => 'จัดส่งสำเร็จ',
                                        'failed' => 'ไม่สำเร็จ',
                                        'returned' => 'ส่งคืน',
                                        'cancelled' => 'ยกเลิก'
                                    ];
                                    $color = $statusColors[$item['current_status']] ?? 'bg-gray-100 text-gray-800';
                                    $text = $statusText[$item['current_status']] ?? $item['current_status'];
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color; ?>">
                                        <?php echo $text; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 capitalize">
                                    <?php echo htmlspecialchars($item['priority_level']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($item['package_weight'], 1); ?> kg
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $item['cod_amount'] > 0 ? number_format($item['cod_amount'], 2) . ' ฿' : '-'; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php
                                    $performanceColors = [
                                        'ตรงเวลา' => 'bg-green-100 text-green-800',
                                        'ล่าช้า' => 'bg-red-100 text-red-800',
                                        'เกินกำหนด' => 'bg-red-100 text-red-800',
                                        'ปกติ' => 'bg-blue-100 text-blue-800'
                                    ];
                                    $perfColor = $performanceColors[$item['performance_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $perfColor; ?>">
                                        <?php echo $item['performance_status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <button onclick="showTrackingDetails('<?php echo $item['tracking_id']; ?>')" class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($item['current_location_lat'] && $item['current_location_lng']): ?>
                                        <button onclick="showOnMap(<?php echo $item['current_location_lat']; ?>, <?php echo $item['current_location_lng']; ?>, '<?php echo htmlspecialchars($item['tracking_id']); ?>')" class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>ไม่มีข้อมูลการติดตาม</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// สร้างกราฟสถิติ
document.addEventListener('DOMContentLoaded', function() {
    // กราฟสถานะการจัดส่ง
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = {
        labels: ['รอจัดส่ง', 'ระหว่างทาง', 'กำลังจัดส่ง', 'สำเร็จ', 'ไม่สำเร็จ', 'ส่งคืน', 'ยกเลิก'],
        datasets: [{
            data: [
                <?php echo $stats['summary']['pending'] ?? 0; ?>,
                <?php echo $stats['summary']['in_transit'] ?? 0; ?>,
                <?php echo $stats['summary']['out_for_delivery'] ?? 0; ?>,
                <?php echo $stats['summary']['delivered'] ?? 0; ?>,
                <?php echo $stats['summary']['failed'] ?? 0; ?>,
                <?php echo $stats['summary']['returned'] ?? 0; ?>,
                <?php echo $stats['summary']['cancelled'] ?? 0; ?>
            ],
            backgroundColor: [
                '#6B7280', '#FCD34D', '#FB923C', '#10B981', '#EF4444', '#8B5CF6', '#6B7280'
            ]
        }]
    };
    
    new Chart(statusCtx, {
        type: 'doughnut',
        data: statusData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});

// แผนที่
let trackingMap;
let trackingMarkers = [];

function initTrackingMap() {
    trackingMap = new google.maps.Map(document.getElementById('tracking-map'), {
        zoom: <?php echo DEFAULT_ZOOM_LEVEL; ?>,
        center: { lat: <?php echo DEFAULT_MAP_CENTER_LAT; ?>, lng: <?php echo DEFAULT_MAP_CENTER_LNG; ?> }
    });
    
    // แสดงตำแหน่งปัจจุบันของพัสดุ
    const locations = <?php echo json_encode($current_locations); ?>;
    
    locations.forEach(function(location) {
        if (location.current_location_lat && location.current_location_lng) {
            const marker = new google.maps.Marker({
                position: { 
                    lat: parseFloat(location.current_location_lat), 
                    lng: parseFloat(location.current_location_lng) 
                },
                map: trackingMap,
                title: location.tracking_id,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: location.current_status === 'in_transit' ? '#FCD34D' : '#FB923C',
                    fillOpacity: 0.8,
                    strokeColor: '#ffffff',
                    strokeWeight: 2
                }
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 10px; max-width: 300px;">
                        <h3 style="margin: 0 0 10px 0; color: #1F2937;">${location.tracking_id}</h3>
                        <p style="margin: 5px 0;"><strong>AWB:</strong> ${location.awb_number}</p>
                        <p style="margin: 5px 0;"><strong>ผู้รับ:</strong> ${location.recipient_name}</p>
                        <p style="margin: 5px 0;"><strong>ไรเดอร์:</strong> ${location.rider_name || 'ไม่ระบุ'}</p>
                        <p style="margin: 5px 0;"><strong>สถานะ:</strong> ${location.current_status}</p>
                        <p style="margin: 5px 0;"><strong>ตำแหน่ง:</strong> ${location.current_location_address}</p>
                    </div>
                `
            });
            
            marker.addListener('click', function() {
                infoWindow.open(trackingMap, marker);
            });
            
            trackingMarkers.push(marker);
        }
    });
}

function showOnMap(lat, lng, trackingId) {
    trackingMap.setCenter({ lat: parseFloat(lat), lng: parseFloat(lng) });
    trackingMap.setZoom(15);
    
    // หา marker ที่ตรงกัน
    trackingMarkers.forEach(function(marker) {
        if (marker.getTitle() === trackingId) {
            google.maps.event.trigger(marker, 'click');
        }
    });
}

function showTrackingDetails(trackingId) {
    // สามารถเพิ่ม modal หรือหน้าต่างแสดงรายละเอียดได้
    alert('แสดงรายละเอียด: ' + trackingId);
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof google !== 'undefined') {
        initTrackingMap();
    }
});
</script>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initTrackingMap"></script>

<?php include $base_path . 'includes/footer.php'; ?> 