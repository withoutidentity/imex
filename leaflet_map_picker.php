<?php
// Include database connection
require_once __DIR__ . '/config/config.php';

// Get zone information from URL parameters
$zone_id = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : null;
$zone_name = isset($_GET['zone_name']) ? htmlspecialchars($_GET['zone_name'], ENT_QUOTES, 'UTF-8') : null;
$zone_code = isset($_GET['zone_code']) ? htmlspecialchars($_GET['zone_code'], ENT_QUOTES, 'UTF-8') : null;
$zone_color = isset($_GET['zone_color']) ? htmlspecialchars($_GET['zone_color'], ENT_QUOTES, 'UTF-8') : '#3b82f6';
$zone_description = isset($_GET['zone_description']) ? htmlspecialchars($_GET['zone_description'], ENT_QUOTES, 'UTF-8') : null;

// Check if this is for showing all zones
$show_all_zones = isset($_GET['show_all_zones']) && $_GET['show_all_zones'] == '1';
$zones_data = null;

if ($show_all_zones && isset($_GET['zones_data'])) {
    $zones_data = json_decode($_GET['zones_data'], true);
}

// If zone_id is provided, try to get zone info from database
if ($zone_id && !$zone_name) {
    try {
        $stmt = $conn->prepare("SELECT zone_name, zone_code, color_code, description, polygon_coordinates, polygon_type, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng FROM zone_area WHERE id = ?");
        $stmt->execute([$zone_id]);
        $zone_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($zone_data) {
            $zone_name = $zone_data['zone_name'];
            $zone_code = $zone_data['zone_code'];
            $zone_color = $zone_data['color_code'] ?: '#3b82f6';
            $zone_description = $zone_data['description'];
            $zone_polygon_coordinates = $zone_data['polygon_coordinates'];
            $zone_polygon_type = $zone_data['polygon_type'];
            $zone_min_lat = $zone_data['min_lat'];
            $zone_max_lat = $zone_data['max_lat'];
            $zone_min_lng = $zone_data['min_lng'];
            $zone_max_lng = $zone_data['max_lng'];
            $zone_center_lat = $zone_data['center_lat'];
            $zone_center_lng = $zone_data['center_lng'];
        }
    } catch (Exception $e) {
        // If database query fails, continue without zone info
        error_log("Zone picker database error: " . $e->getMessage());
    }
}

// This will be updated after zone lookup
$zone_display_name = 'โซนใหม่';
$zone_display_code = 'NEW';
$page_title = "🗺️ Zone Picker - เลือกพื้นที่โซนอัจฉริยะ";

// Update display info if showing all zones
if ($show_all_zones) {
    $zone_display_name = 'แผนที่โซนทั้งหมด';
    $zone_display_code = 'ALL';
    $page_title = "🗺️ All Zones Map - แผนที่โซนทั้งหมด";
}

// Try to find zone by coordinates if no zone info provided
if (!$zone_id && !$zone_name) {
    $min_lat = isset($_GET['min_lat']) ? floatval($_GET['min_lat']) : null;
    $max_lat = isset($_GET['max_lat']) ? floatval($_GET['max_lat']) : null;
    $min_lng = isset($_GET['min_lng']) ? floatval($_GET['min_lng']) : null;
    $max_lng = isset($_GET['max_lng']) ? floatval($_GET['max_lng']) : null;
    
    if ($min_lat && $max_lat && $min_lng && $max_lng) {
        try {
            // Find zone that contains these coordinates
            $stmt = $conn->prepare("
                SELECT id, zone_name, zone_code, color_code, description 
                FROM zone_area 
                WHERE min_lat <= ? AND max_lat >= ? 
                AND min_lng <= ? AND max_lng >= ?
                AND is_active = TRUE
                ORDER BY (
                    (max_lat - min_lat) * (max_lng - min_lng)
                ) ASC
                LIMIT 1
            ");
            $stmt->execute([$max_lat, $min_lat, $max_lng, $min_lng]);
            $zone_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($zone_data) {
                $zone_id = $zone_data['id'];
                $zone_name = $zone_data['zone_name'];
                $zone_code = $zone_data['zone_code'];
                $zone_color = $zone_data['color_code'] ?: '#3b82f6';
                $zone_description = $zone_data['description'];
            } else {
                // Try to find nearby zones
                $stmt = $conn->prepare("
                    SELECT id, zone_name, zone_code, color_code, description,
                           ABS(center_lat - ?) + ABS(center_lng - ?) as distance
                    FROM zone_area 
                    WHERE is_active = TRUE
                    ORDER BY distance ASC
                    LIMIT 1
                ");
                $center_lat = ($min_lat + $max_lat) / 2;
                $center_lng = ($min_lng + $max_lng) / 2;
                $stmt->execute([$center_lat, $center_lng]);
                $zone_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($zone_data) {
                    $zone_id = $zone_data['id'];
                    $zone_name = $zone_data['zone_name'] . ' (ใกล้เคียง)';
                    $zone_code = $zone_data['zone_code'];
                    $zone_color = $zone_data['color_code'] ?: '#3b82f6';
                    $zone_description = $zone_data['description'];
                }
            }
        } catch (Exception $e) {
            error_log("Zone coordinate lookup error: " . $e->getMessage());
        }
    }
}

// Get delivery count for this zone
$delivery_count = 0;
$delivery_stats = [];

if ($zone_id || $zone_code) {
    try {
        // Check if delivery_tracking table has data and what columns it has
        $check_tracking_stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_tracking");
        $check_tracking_stmt->execute();
        $tracking_count = $check_tracking_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($tracking_count > 0) {
            // Get column names from delivery_tracking
            $columns_stmt = $conn->prepare("SHOW COLUMNS FROM delivery_tracking");
            $columns_stmt->execute();
            $tracking_columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create dynamic column mapping
            $columnMap = [];
            $possibleMappings = [
                'id' => ['id'],
                'awb_number' => ['awb_number', 'AWB', 'เลขพัสดุ'],
                'recipient_name' => ['recipient_name', 'ชื่อผู้รับ', '收件人姓名'],
                'status' => ['status', 'สถานะ', '状态'],
                'zone_code' => ['zone_code', 'รหัสโซน', '区域代码']
            ];
            
            foreach ($tracking_columns as $column) {
                $columnName = $column['Field'];
                foreach ($possibleMappings as $key => $possibleNames) {
                    if (in_array($columnName, $possibleNames)) {
                        $columnMap[$key] = $columnName;
                        break;
                    }
                }
            }
            
            // Check delivery_address table first
            $check_address_stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_address WHERE zone_id = ?");
            $check_address_stmt->execute([$zone_id ?: 0]);
            $address_count = $check_address_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($address_count > 0 && $zone_id) {
                // Use delivery_address table
                $count_stmt = $conn->prepare("
                    SELECT COUNT(*) as total_count,
                           SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                           SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                           SUM(CASE WHEN delivery_status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_count,
                           SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed_count
                    FROM delivery_address 
                    WHERE zone_id = ?
                ");
                $count_stmt->execute([$zone_id]);
                $stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
                
                $delivery_count = $stats['total_count'];
                $delivery_stats = [
                    'total' => $stats['total_count'],
                    'delivered' => $stats['delivered_count'],
                    'pending' => $stats['pending_count'],
                    'in_transit' => $stats['in_transit_count'],
                    'failed' => $stats['failed_count']
                ];
            } else if (isset($columnMap['zone_code']) && $zone_code) {
                // Fallback to delivery_tracking table using zone_code
                $statusColumn = $columnMap['status'] ?? 'NULL';
                
                $count_stmt = $conn->prepare("
                    SELECT COUNT(*) as total_count,
                           SUM(CASE WHEN {$statusColumn} = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                           SUM(CASE WHEN {$statusColumn} = 'pending' THEN 1 ELSE 0 END) as pending_count,
                           SUM(CASE WHEN {$statusColumn} = 'in_transit' THEN 1 ELSE 0 END) as in_transit_count,
                           SUM(CASE WHEN {$statusColumn} = 'failed' THEN 1 ELSE 0 END) as failed_count
                    FROM delivery_tracking 
                    WHERE {$columnMap['zone_code']} = ?
                ");
                $count_stmt->execute([$zone_code]);
                $stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
                
                $delivery_count = $stats['total_count'];
                $delivery_stats = [
                    'total' => $stats['total_count'],
                    'delivered' => $stats['delivered_count'],
                    'pending' => $stats['pending_count'],
                    'in_transit' => $stats['in_transit_count'],
                    'failed' => $stats['failed_count']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Zone delivery count error: " . $e->getMessage());
    }
}

// Update display values after all lookups
$zone_display_name = $zone_name ?: 'โซนใหม่';
$zone_display_code = $zone_code ?: 'NEW';
$page_title = $zone_name ? "🗺️ แก้ไขโซน: {$zone_name}" : "🗺️ Zone Picker - เลือกพื้นที่โซนอัจฉริยะ";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        body { 
            margin: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .glass-dark {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .map-container { 
            height: 70vh;
            min-height: 500px;
            border-radius: 20px; 
            overflow: hidden;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .floating-panel {
            position: absolute;
            z-index: 1000;
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .tutorial-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: none;
        }
        
        .tutorial-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            margin: 5% auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        
        .coordinate-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 16px;
            transition: all 0.3s ease;
        }
        
        .coordinate-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
        }
        
        .coordinate-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 13px;
            background: white;
            transition: all 0.2s ease;
        }
        
        .coordinate-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .floating-help {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .help-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 24px;
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
            transition: all 0.3s ease;
            animation: pulse-help 2s infinite;
        }
        
        .help-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(139, 92, 246, 0.6);
        }
        
        @keyframes pulse-help {
            0%, 100% { box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4); }
            50% { box-shadow: 0 8px 25px rgba(139, 92, 246, 0.8); }
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            animation: status-glow 2s ease-in-out infinite alternate;
        }
        
        .status-ready {
            background: rgba(16, 185, 129, 0.2);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-drawing {
            background: rgba(59, 130, 246, 0.2);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .status-complete {
            background: rgba(16, 185, 129, 0.2);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        @keyframes status-glow {
            from { box-shadow: 0 0 5px rgba(16, 185, 129, 0.3); }
            to { box-shadow: 0 0 20px rgba(16, 185, 129, 0.6); }
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #047857);
            border-radius: 2px;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        /* Custom Leaflet styles */
        .leaflet-draw-toolbar a {
            background-color: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
            border-radius: 12px !important;
            margin: 3px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        
        .leaflet-draw-toolbar a:hover {
            background-color: rgba(59, 130, 246, 0.1) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(0,0,0,0.2) !important;
        }
        
        .leaflet-control-container .leaflet-top.leaflet-left {
            margin-top: 20px;
            margin-left: 20px;
        }
        
        .drawing-hint {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 1000;
            max-width: 300px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .shortcuts-panel {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 12px;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .shortcut-key {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            margin: 0 2px;
        }
        
        .tutorial-step {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        
        .tutorial-step.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        .bounce {
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .map-container {
                height: 60vh;
                border-radius: 16px;
            }
            
            .floating-help {
                bottom: 20px;
                right: 20px;
            }
            
            .help-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .coordinate-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .shortcuts-panel {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Tutorial Overlay -->
    <div id="tutorial-overlay" class="tutorial-overlay">
        <div class="tutorial-card">
            <div id="tutorial-content">
                <!-- Tutorial steps will be inserted here -->
            </div>
        </div>
        </div>

    <div class="min-h-screen p-4 md:p-6">
        <!-- Header -->
        <div class="glass-card rounded-2xl p-6 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="text-center md:text-left">
                    <div class="flex items-center justify-center md:justify-start gap-3 mb-3">
                        <div class="w-6 h-6 rounded-full border-2 border-white shadow-lg" style="background-color: <?php echo $zone_color; ?>"></div>
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-800">
                            🗺️ <?php echo $zone_display_name; ?>
                        </h1>
        </div>
                    <?php if ($zone_code): ?>
                        <div class="inline-flex items-center gap-2 bg-white/70 px-3 py-1 rounded-full text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tag"></i>
                            <span>รหัสโซน: <?php echo $zone_display_code; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($zone_description): ?>
                        <div class="bg-amber-100/80 border border-amber-300 rounded-lg p-3 mb-3 max-w-md">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-map-marked-alt text-amber-600 mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <div class="text-sm font-semibold text-amber-800 mb-1">พื้นที่ครอบคลุม:</div>
                                    <div class="text-sm text-amber-700"><?php echo nl2br($zone_description); ?></div>
        </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($delivery_count > 0): ?>
                        <div class="bg-blue-100/80 border border-blue-300 rounded-lg p-3 mb-3 max-w-md">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-box text-blue-600 mt-0.5 flex-shrink-0"></i>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-blue-800 mb-2">ข้อมูลในโซนนี้:</div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                            <span class="text-blue-700">ทั้งหมด: <strong><?php echo number_format($delivery_stats['total']); ?></strong> หลัง</span>
                                        </div>
                                        <?php if ($delivery_stats['delivered'] > 0): ?>
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                            <span class="text-green-700">จัดส่งแล้ว: <?php echo number_format($delivery_stats['delivered']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($delivery_stats['pending'] > 0): ?>
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                            <span class="text-yellow-700">รอจัดส่ง: <?php echo number_format($delivery_stats['pending']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($delivery_stats['in_transit'] > 0): ?>
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                            <span class="text-blue-700">กำลังจัดส่ง: <?php echo number_format($delivery_stats['in_transit']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($delivery_stats['failed'] > 0): ?>
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                                            <span class="text-red-700">ไม่สำเร็จ: <?php echo number_format($delivery_stats['failed']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-blue-200">
                                        <a href="../pages/address_info.php?zone_filter=<?php echo $zone_id; ?>" target="_blank" 
                                           class="text-xs text-blue-600 hover:text-blue-800 underline flex items-center gap-1">
                                            <i class="fas fa-external-link-alt"></i>
                                            ดูรายละเอียดทั้งหมด
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-gray-600 text-sm md:text-base">
                        <?php echo $zone_name ? 'แก้ไขขอบเขตพื้นที่โซนการจัดส่ง' : 'เลือกพื้นที่โซนการจัดส่งด้วยเครื่องมือที่ทันสมัย'; ?>
                    </p>
                    <div class="progress-bar">
                        <div id="progress-fill" class="progress-fill"></div>
            </div>
            </div>
                <div class="flex flex-col items-center gap-3">
                    <div id="status-indicator" class="status-indicator status-ready">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        พร้อมใช้งาน
            </div>
                    <div class="flex gap-2">
                        <button onclick="showTutorial()" class="btn btn-secondary text-sm">
                            <i class="fas fa-graduation-cap"></i>
                            วิธีใช้งาน
                        </button>
                        <?php if ($zone_name): ?>
                            <button onclick="window.close()" class="btn btn-danger text-sm">
                                <i class="fas fa-times"></i>
                                ปิด
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
            <!-- Map Section -->
            <div class="xl:col-span-3">
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-map text-blue-600"></i>
                            แผนที่เชิงโต้ตอบ
                        </h2>
                        <div class="text-sm text-gray-500" id="zoom-level">
                            Zoom: 14
        </div>
        </div>

                    <div class="map-container relative">
                        <div id="map" class="w-full h-full"></div>
                        
                        <!-- Shortcuts Panel -->
                        <div class="shortcuts-panel">
                            <span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">C</span> คัดลอก
                            <span class="mx-2">•</span>
                            <span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">Enter</span> บันทึก
                            <span class="mx-2">•</span>
                            <span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">⌫</span> ลบ
                        </div>
                        
                        <!-- Drawing Hint -->
                        <div id="drawing-hint" class="drawing-hint hidden">
                            <div class="font-bold mb-2">💡 เคล็ดลับ:</div>
                            <div id="rectangle-hint">ลากเส้นทแยงจากมุมหนึ่งไปยังอีกมุมหนึ่งเพื่อสร้างสี่เหลี่ยม</div>
                            <div id="polygon-hint" style="display:none;">คลิกจุดต่างๆ เพื่อสร้างรูปหลายเหลี่ยม แล้วดับเบิลคลิกเพื่อจบ</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-bolt text-yellow-500"></i>
                        การดำเนินการ
                    </h3>
                    <div class="space-y-3">
                        <button onclick="clearAll()" class="btn btn-danger w-full">
                            <i class="fas fa-trash-alt"></i>
                            ลบทั้งหมด
                        </button>
                        <button onclick="copyCoordinates()" class="btn btn-secondary w-full">
                            <i class="fas fa-copy"></i>
                            คัดลอกพิกัด
                        </button>
                        <button onclick="centerMap()" class="btn btn-primary w-full">
                            <i class="fas fa-crosshairs"></i>
                            กลับจุดกึ่งกลาง
                        </button>
                        <button onclick="applyCoordinates()" id="apply-btn" class="btn btn-success w-full">
                            <i class="fas fa-save"></i>
                            บันทึกพิกัด
                        </button>
                    </div>
                </div>

                <!-- Zone Info Display -->
                <?php if ($zone_name): ?>
                <div class="glass-card rounded-2xl p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        ข้อมูลโซน
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-3 bg-white/50 rounded-lg">
                            <div class="w-4 h-4 rounded-full flex-shrink-0 mt-1" style="background-color: <?php echo $zone_color; ?>"></div>
                            <div class="flex-1">
                                <div class="font-semibold text-gray-800"><?php echo $zone_display_name; ?></div>
                                <div class="text-sm text-gray-600 mb-1"><?php echo $zone_display_code; ?></div>
                                <?php if ($zone_description): ?>
                                    <div class="text-xs text-gray-700 bg-amber-50 border border-amber-200 p-2 rounded-md mb-2">
                                        <div class="font-medium text-amber-800 mb-1">
                                            <i class="fas fa-map-marked-alt mr-1"></i>
                                            พื้นที่ครอบคลุม:
                                        </div>
                                        <div class="text-amber-700"><?php echo nl2br($zone_description); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($delivery_count > 0): ?>
                                    <div class="text-xs bg-blue-50 border border-blue-200 p-2 rounded-md mb-2">
                                        <div class="font-medium text-blue-800 mb-2 flex items-center gap-1">
                                            <i class="fas fa-chart-bar mr-1"></i>
                                            สถิติการจัดส่ง:
                                        </div>
                                        <div class="space-y-1">
                                            <div class="flex justify-between items-center">
                                                <span class="text-blue-700">ทั้งหมด:</span>
                                                <span class="font-semibold text-blue-800"><?php echo number_format($delivery_stats['total']); ?> หลัง</span>
                                            </div>
                                            <?php if ($delivery_stats['delivered'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <span class="text-green-600">จัดส่งแล้ว:</span>
                                                <span class="text-green-700"><?php echo number_format($delivery_stats['delivered']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($delivery_stats['pending'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <span class="text-yellow-600">รอจัดส่ง:</span>
                                                <span class="text-yellow-700"><?php echo number_format($delivery_stats['pending']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($delivery_stats['in_transit'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <span class="text-blue-600">กำลังจัดส่ง:</span>
                                                <span class="text-blue-700"><?php echo number_format($delivery_stats['in_transit']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($delivery_stats['failed'] > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <span class="text-red-600">ไม่สำเร็จ:</span>
                                                <span class="text-red-700"><?php echo number_format($delivery_stats['failed']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Coordinate Info -->
                                <?php 
                                $min_lat = isset($_GET['min_lat']) ? floatval($_GET['min_lat']) : null;
                                $max_lat = isset($_GET['max_lat']) ? floatval($_GET['max_lat']) : null;
                                $min_lng = isset($_GET['min_lng']) ? floatval($_GET['min_lng']) : null;
                                $max_lng = isset($_GET['max_lng']) ? floatval($_GET['max_lng']) : null;
                                if ($min_lat && $max_lat && $min_lng && $max_lng): 
                                ?>
                                    <div class="text-xs bg-gray-50 border border-gray-200 p-2 rounded-md">
                                        <div class="font-medium text-gray-800 mb-2 flex items-center gap-1">
                                            <i class="fas fa-map-pin mr-1"></i>
                                            ข้อมูลพิกัด:
                                        </div>
                                        <div class="space-y-1 font-mono text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">จุดกึ่งกลาง:</span>
                                                <span class="text-gray-800"><?php echo number_format(($min_lat + $max_lat) / 2, 6); ?>, <?php echo number_format(($min_lng + $max_lng) / 2, 6); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">ขนาดพื้นที่:</span>
                                                <span class="text-gray-800">
                                                    <?php 
                                                    $lat_diff = abs($max_lat - $min_lat);
                                                    $lng_diff = abs($max_lng - $min_lng);
                                                    $area_km2 = ($lat_diff * 111) * ($lng_diff * 111 * cos(deg2rad(($min_lat + $max_lat) / 2)));
                                                    echo number_format($area_km2, 4); 
                                                    ?> ตร.กม.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 bg-blue-50 p-2 rounded">
                            <i class="fas fa-lightbulb mr-1"></i>
                            กำลังแก้ไขขอบเขตของโซนนี้
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Coordinates Display -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-crosshairs text-green-500"></i>
                        พิกัดขอบเขต
                    </h3>
                    
                    <div class="space-y-4" id="coordinate-grid">
                        <div class="coordinate-card">
                            <label class="block text-sm font-semibold text-red-600 mb-2">
                                <i class="fas fa-arrow-down"></i> ละติจูดต่ำสุด (South)
                            </label>
                            <input type="text" id="min-lat" class="coordinate-input" readonly placeholder="ยังไม่ได้เลือกพื้นที่">
                        </div>
                        
                        <div class="coordinate-card">
                            <label class="block text-sm font-semibold text-blue-600 mb-2">
                                <i class="fas fa-arrow-up"></i> ละติจูดสูงสุด (North)
                            </label>
                            <input type="text" id="max-lat" class="coordinate-input" readonly placeholder="ยังไม่ได้เลือกพื้นที่">
            </div>
                        
                        <div class="coordinate-card">
                            <label class="block text-sm font-semibold text-orange-600 mb-2">
                                <i class="fas fa-arrow-left"></i> ลองจิจูดต่ำสุด (West)
                            </label>
                            <input type="text" id="min-lng" class="coordinate-input" readonly placeholder="ยังไม่ได้เลือกพื้นที่">
            </div>
                        
                        <div class="coordinate-card">
                            <label class="block text-sm font-semibold text-green-600 mb-2">
                                <i class="fas fa-arrow-right"></i> ลองจิจูดสูงสุด (East)
                            </label>
                            <input type="text" id="max-lng" class="coordinate-input" readonly placeholder="ยังไม่ได้เลือกพื้นที่">
            </div>
        </div>

                    <div id="area-info" class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200 hidden">
                        <div class="text-sm text-blue-800">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <span class="font-semibold">ขนาดพื้นที่:</span>
                            </div>
                            <div id="area-size" class="ml-6"></div>
                            <div class="flex items-center gap-2 mt-2 mb-1">
                                <i class="fas fa-map-pin"></i>
                                <span class="font-semibold">จุดกึ่งกลาง:</span>
                            </div>
                            <div id="center-point" class="ml-6 font-mono text-xs"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Help Button -->
    <div class="floating-help">
        <button onclick="showTutorial()" class="help-btn" title="ช่วยเหลือ">
            <i class="fas fa-question"></i>
        </button>
    </div>

    <script>
        // Global variables
        let map, drawnItems, drawControl;
        let currentBounds = null;
        let tutorialStep = 0;
        let isFirstTime = !localStorage.getItem('zonePicker_visited');
        
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const initialBounds = {
            minLat: parseFloat(urlParams.get('min_lat')) || null,
            maxLat: parseFloat(urlParams.get('max_lat')) || null,
            minLng: parseFloat(urlParams.get('min_lng')) || null,
            maxLng: parseFloat(urlParams.get('max_lng')) || null
        };
        
        // Get polygon data from PHP
        const existingPolygonData = <?php echo json_encode([
            'coordinates' => $zone_polygon_coordinates ?? null,
            'type' => $zone_polygon_type ?? 'rectangle',
            'bounds' => [
                'min_lat' => $zone_min_lat ?? null,
                'max_lat' => $zone_max_lat ?? null,
                'min_lng' => $zone_min_lng ?? null,
                'max_lng' => $zone_max_lng ?? null
            ],
            'center' => [
                'lat' => $zone_center_lat ?? null,
                'lng' => $zone_center_lng ?? null
            ]
        ]); ?>;
        
        console.log('URL Parameters:', {
            min_lat: urlParams.get('min_lat'),
            max_lat: urlParams.get('max_lat'),
            min_lng: urlParams.get('min_lng'),
            max_lng: urlParams.get('max_lng')
        });
        console.log('Parsed Initial Bounds:', initialBounds);
        
        // Calculate center
        let centerLat = 8.4304;
        let centerLng = 99.9631;
        
        if (initialBounds.minLat && initialBounds.maxLat && initialBounds.minLng && initialBounds.maxLng) {
            centerLat = (initialBounds.minLat + initialBounds.maxLat) / 2;
            centerLng = (initialBounds.minLng + initialBounds.maxLng) / 2;
        }
        
        // Initialize map
        function initializeMap() {
            map = L.map('map').setView([centerLat, centerLng], 14);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

            // Initialize drawing
            drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);

            drawControl = new L.Control.Draw({
                position: 'topleft',
                draw: {
                    polygon: {
                        allowIntersection: false,
                        drawError: {
                            color: '#e74c3c',
                            message: '<strong>เกิดข้อผิดพลาด!</strong> รูปร่างต้องไม่ตัดกัน'
                        },
                        shapeOptions: {
                            color: '<?php echo $zone_color; ?>',
                            weight: 3,
                            opacity: 0.8,
                            fillColor: '<?php echo $zone_color; ?>',
                            fillOpacity: 0.2
                        }
                    },
                    polyline: false,
                    circle: false,
                    marker: false,
                    circlemarker: false,
                    rectangle: {
                        shapeOptions: {
                            color: '<?php echo $zone_color; ?>',
                            weight: 3,
                            opacity: 0.8,
                            fillColor: '<?php echo $zone_color; ?>',
                            fillOpacity: 0.2
                        }
                    }
                },
                edit: {
                    featureGroup: drawnItems,
                    remove: true
                }
            });
            map.addControl(drawControl);

            // Event listeners
            setupMapEvents();
            updateProgress(25);
        }
        
        function setupMapEvents() {
            // Drawing events
            map.on(L.Draw.Event.CREATED, function(event) {
                const layer = event.layer;
                drawnItems.clearLayers();
                drawnItems.addLayer(layer);
                updateBoundsFromLayer(layer);
                updateStatus('complete', 'เสร็จสิ้น');
                updateProgress(100);
                document.getElementById('drawing-hint').classList.add('hidden');
            });

            map.on(L.Draw.Event.EDITED, function(event) {
                const layers = event.layers;
                layers.eachLayer(function(layer) {
                    updateBoundsFromLayer(layer);
                });
                updateStatus('complete', 'เสร็จสิ้น');
            });

            map.on(L.Draw.Event.DELETED, function(event) {
                currentBounds = null;
                updateDisplay();
                updateStatus('ready', 'พร้อมใช้งาน');
                updateProgress(25);
                document.getElementById('apply-btn').disabled = true;
            });

            // Drawing hints
            map.on('draw:drawstart', function(e) {
                const hint = document.getElementById('drawing-hint');
                const rectangleHint = document.getElementById('rectangle-hint');
                const polygonHint = document.getElementById('polygon-hint');
                
                hint.classList.remove('hidden');
                updateStatus('drawing', 'กำลังวาด...');
                updateProgress(50);
                
                if (e.layerType === 'rectangle') {
                    rectangleHint.style.display = 'block';
                    polygonHint.style.display = 'none';
                } else if (e.layerType === 'polygon') {
                    rectangleHint.style.display = 'none';
                    polygonHint.style.display = 'block';
                }
            });

            map.on('draw:drawstop', function() {
                document.getElementById('drawing-hint').classList.add('hidden');
            });

            // Zoom level display
            map.on('zoomend', function() {
                document.getElementById('zoom-level').textContent = `Zoom: ${map.getZoom()}`;
            });
        }
        
        function updateBoundsFromLayer(layer) {
            let bounds;
            
            if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
                bounds = layer.getBounds();
            } else {
                return;
            }
            
            currentBounds = {
                minLat: bounds.getSouth(),
                maxLat: bounds.getNorth(),
                minLng: bounds.getWest(),
                maxLng: bounds.getEast()
            };
            
            updateDisplay();
            
            // Enable apply button
            const applyBtn = document.getElementById('apply-btn');
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                applyBtn.classList.add('hover:bg-green-600');
            }
        }

        function updateDisplay() {
            const coords = ['min-lat', 'max-lat', 'min-lng', 'max-lng'];
            const areaInfo = document.getElementById('area-info');
            
            if (currentBounds) {
                console.log('Updating display with bounds:', currentBounds);
                
                // Update coordinate inputs
                const minLatEl = document.getElementById('min-lat');
                const maxLatEl = document.getElementById('max-lat');
                const minLngEl = document.getElementById('min-lng');
                const maxLngEl = document.getElementById('max-lng');
                
                if (minLatEl) minLatEl.value = currentBounds.minLat.toFixed(8);
                if (maxLatEl) maxLatEl.value = currentBounds.maxLat.toFixed(8);
                if (minLngEl) minLngEl.value = currentBounds.minLng.toFixed(8);
                if (maxLngEl) maxLngEl.value = currentBounds.maxLng.toFixed(8);
                
                // Calculate area size (approximate)
                const latDiff = currentBounds.maxLat - currentBounds.minLat;
                const lngDiff = currentBounds.maxLng - currentBounds.minLng;
                const centerLatCalc = (currentBounds.minLat + currentBounds.maxLat) / 2;
                const areaKm = (latDiff * 111) * (lngDiff * 111 * Math.cos(centerLatCalc * Math.PI / 180));
                
                const areaSizeEl = document.getElementById('area-size');
                const centerPointEl = document.getElementById('center-point');
                
                if (areaSizeEl) areaSizeEl.textContent = `${Math.abs(areaKm).toFixed(4)} ตร.กม.`;
                if (centerPointEl) centerPointEl.textContent = 
                    `${centerLatCalc.toFixed(8)}, ${((currentBounds.minLng + currentBounds.maxLng) / 2).toFixed(8)}`;
                
                if (areaInfo) areaInfo.classList.remove('hidden');
                
                // Animate coordinate cards with validation
                coords.forEach((id, index) => {
                    const element = document.getElementById(id);
                    if (element) {
                        const card = element.closest('.coordinate-card');
                        if (card) {
                            setTimeout(() => {
                                card.style.transform = 'scale(1.05)';
                                card.style.transition = 'transform 0.2s ease';
                                setTimeout(() => {
                                    card.style.transform = 'scale(1)';
                                }, 200);
                            }, index * 100);
                        }
                    }
                });
                
                console.log('Display updated successfully');
            } else {
                console.log('No bounds to display, clearing inputs');
                coords.forEach(id => {
                    const element = document.getElementById(id);
                    if (element) element.value = '';
                });
                if (areaInfo) areaInfo.classList.add('hidden');
            }
        }

        function updateStatus(status, text) {
            const indicator = document.getElementById('status-indicator');
            indicator.className = `status-indicator status-${status}`;
            
            const dot = indicator.querySelector('div');
            const textSpan = indicator.childNodes[2] || indicator.appendChild(document.createTextNode(''));
            
            if (status === 'ready') {
                dot.className = 'w-3 h-3 bg-green-500 rounded-full animate-pulse';
                textSpan.textContent = text;
            } else if (status === 'drawing') {
                dot.className = 'w-3 h-3 bg-blue-500 rounded-full animate-spin';
                textSpan.textContent = text;
            } else if (status === 'complete') {
                dot.className = 'w-3 h-3 bg-green-500 rounded-full';
                textSpan.textContent = text;
            }
        }

        function updateProgress(percent) {
            document.getElementById('progress-fill').style.width = percent + '%';
        }

        // Action functions
        function clearAll() {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: 'คุณต้องการลบขอบเขตทั้งหมดหรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    drawnItems.clearLayers();
                    currentBounds = null;
            updateDisplay();
                    updateStatus('ready', 'พร้อมใช้งาน');
                    updateProgress(25);
                    document.getElementById('apply-btn').disabled = true;
                    
                    showToast('success', 'ลบสำเร็จ!', 'ลบขอบเขตทั้งหมดแล้ว');
                }
            });
        }

        function centerMap() {
            if (currentBounds) {
                const centerLat = (currentBounds.minLat + currentBounds.maxLat) / 2;
                const centerLng = (currentBounds.minLng + currentBounds.maxLng) / 2;
            map.setView([centerLat, centerLng], 15);
            } else {
                map.setView([centerLat, centerLng], 14);
            }
            showToast('info', 'กลับจุดกึ่งกลาง', 'ย้ายแผนที่ไปยังจุดกึ่งกลางแล้ว');
        }

        function copyCoordinates() {
            if (!currentBounds) {
                showToast('warning', 'ยังไม่ได้เลือกพื้นที่', 'กรุณาวาดขอบเขตก่อนคัดลอกพิกัด');
                return;
            }
            
            const coords = `Min Lat: ${currentBounds.minLat.toFixed(8)}
Max Lat: ${currentBounds.maxLat.toFixed(8)}
Min Lng: ${currentBounds.minLng.toFixed(8)}
Max Lng: ${currentBounds.maxLng.toFixed(8)}
Center: ${((currentBounds.minLat + currentBounds.maxLat) / 2).toFixed(8)}, ${((currentBounds.minLng + currentBounds.maxLng) / 2).toFixed(8)}`;
            
            navigator.clipboard.writeText(coords).then(() => {
                showToast('success', 'คัดลอกสำเร็จ!', 'คัดลอกพิกัดลงคลิปบอร์ดแล้ว');
            }).catch(() => {
                Swal.fire({
                    title: 'คัดลอกพิกัดนี้:',
                    input: 'textarea',
                    inputValue: coords,
                    inputAttributes: { readonly: true },
                    showCancelButton: true,
                    confirmButtonText: 'ปิด',
                    cancelButtonText: 'คัดลอกด้วยตนเอง'
                });
            });
        }

        function applyCoordinates() {
            // Check if there are any drawn items
            if (drawnItems.getLayers().length === 0) {
                showToast('warning', 'ยังไม่ได้เลือกพื้นที่', 'กรุณาวาดขอบเขตก่อนบันทึก');
                return;
            }
            
            // Get the drawn layers to determine if it's a polygon or rectangle
            let polygonCoordinates = null;
            let polygonType = 'rectangle';
            let bounds = null;
            
            drawnItems.eachLayer(function(layer) {
                if (layer instanceof L.Polygon && !(layer instanceof L.Rectangle)) {
                    // It's a polygon (not rectangle)
                    const coords = layer.getLatLngs()[0]; // Get outer ring
                    polygonCoordinates = coords.map(coord => [coord.lat, coord.lng]);
                    polygonType = 'polygon';
                    bounds = layer.getBounds();
                    console.log('Detected polygon with coordinates:', polygonCoordinates);
                    console.log('Polygon bounds:', bounds);
                } else if (layer instanceof L.Rectangle) {
                    polygonType = 'rectangle';
                    bounds = layer.getBounds();
                    console.log('Detected rectangle');
                }
            });
            
            if (!bounds) {
                showToast('error', 'ข้อผิดพลาด', 'ไม่สามารถกำหนดขอบเขตได้');
                return;
            }
            
            const result = {
                min_lat: bounds.getSouthWest().lat.toFixed(8),
                max_lat: bounds.getNorthEast().lat.toFixed(8),
                min_lng: bounds.getSouthWest().lng.toFixed(8),
                max_lng: bounds.getNorthEast().lng.toFixed(8),
                center_lat: bounds.getCenter().lat.toFixed(8),
                center_lng: bounds.getCenter().lng.toFixed(8),
                polygon_coordinates: polygonCoordinates ? JSON.stringify(polygonCoordinates) : null,
                polygon_type: polygonType
            };
            
            console.log('=== DEBUG: Final Result ===');
            console.log('Sending coordinates:', result);
            console.log('Polygon details:', {
                hasPolygonCoords: !!polygonCoordinates,
                polygonType: polygonType,
                coordinatesLength: polygonCoordinates ? polygonCoordinates.length : 0,
                bounds: bounds,
                drawnLayersCount: drawnItems.getLayers().length
            });
            console.log('Drawn layers:', drawnItems.getLayers());
            console.log('========================');
            
            if (window.opener) {
                Swal.fire({
                    icon: 'question',
                    title: 'ยืนยันการบันทึก?',
                    html: `
                        <div class="text-left bg-gray-50 p-4 rounded-lg">
                            <div class="mb-3 p-2 rounded ${result.polygon_type === 'polygon' ? 'bg-green-100 border border-green-300' : 'bg-blue-100 border border-blue-300'}">
                                <strong>ประเภทพื้นที่:</strong> 
                                <span class="font-semibold ${result.polygon_type === 'polygon' ? 'text-green-700' : 'text-blue-700'}">
                                    ${result.polygon_type === 'polygon' ? '🔷 Polygon (หลายเหลี่ยม)' : '⬜ Rectangle (สี่เหลี่ยม)'}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm font-mono">
                                <div><strong>Min Lat:</strong> ${result.min_lat}</div>
                                <div><strong>Max Lat:</strong> ${result.max_lat}</div>
                                <div><strong>Min Lng:</strong> ${result.min_lng}</div>
                                <div><strong>Max Lng:</strong> ${result.max_lng}</div>
                            </div>
                            <div class="mt-3 pt-3 border-t text-center">
                                <strong>จุดกึ่งกลาง:</strong><br>
                                <span class="font-mono text-blue-600">${result.center_lat}, ${result.center_lng}</span>
                            </div>
                            ${result.polygon_type === 'polygon' && result.polygon_coordinates ? 
                                `<div class="mt-3 pt-3 border-t text-center">
                                    <strong>จำนวนจุด Polygon:</strong><br>
                                    <span class="font-mono text-green-600">${JSON.parse(result.polygon_coordinates).length} จุด</span>
                                </div>` : ''
                            }
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check"></i> ใช่, บันทึกเลย',
                    cancelButtonText: '<i class="fas fa-times"></i> ยกเลิก',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#ef4444',
                    customClass: {
                        confirmButton: 'btn-success',
                        cancelButton: 'btn-danger'
                    }
                }).then((result_confirm) => {
                    if (result_confirm.isConfirmed) {
                        try {
                window.opener.postMessage({
                    type: 'leaflet-coordinates',
                    data: result
                }, '*');
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'บันทึกสำเร็จ! 🎉',
                                text: 'ส่งพิกัดกลับไปยังฟอร์มแล้ว',
                                timer: 2000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            }).then(() => {
                window.close();
                            });
                            
                            setTimeout(() => window.close(), 2000);
                            
                        } catch (error) {
                            showToast('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถส่งข้อมูลกลับได้');
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'พิกัดที่เลือก',
                    html: `
                        <div class="text-left bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-2 gap-2 text-sm font-mono">
                                <div><strong>Min Lat:</strong> ${result.min_lat}</div>
                                <div><strong>Max Lat:</strong> ${result.max_lat}</div>
                                <div><strong>Min Lng:</strong> ${result.min_lng}</div>
                                <div><strong>Max Lng:</strong> ${result.max_lng}</div>
                            </div>
                            <div class="mt-3 pt-3 border-t text-center">
                                <strong>จุดกึ่งกลาง:</strong><br>
                                <span class="font-mono text-blue-600">${result.center_lat}, ${result.center_lng}</span>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'ตกลง'
                });
            }
        }

        // Tutorial system
        let tutorialSteps = [];
        
        // Load all zones function
        function loadAllZones(zonesData) {
            console.log('Loading all zones:', zonesData);
            
            if (!map || !zonesData || zonesData.length === 0) {
                console.warn('Cannot load zones: map or data not available');
                return;
            }
            
            const allMarkers = [];
            const colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
            
            zonesData.forEach((zone, index) => {
                const color = zone.color_code || colors[index % colors.length];
                
                // Create zone polygon
                const zoneBounds = [
                    [parseFloat(zone.min_lat), parseFloat(zone.min_lng)],
                    [parseFloat(zone.min_lat), parseFloat(zone.max_lng)],
                    [parseFloat(zone.max_lat), parseFloat(zone.max_lng)],
                    [parseFloat(zone.max_lat), parseFloat(zone.min_lng)]
                ];
                
                const polygon = L.polygon(zoneBounds, {
                    color: color,
                    weight: 2,
                    opacity: 0.8,
                    fillColor: color,
                    fillOpacity: 0.2
                }).addTo(map);
                
                // Add center marker
                const centerLat = parseFloat(zone.center_lat);
                const centerLng = parseFloat(zone.center_lng);
                const marker = L.marker([centerLat, centerLng]).addTo(map);
                
                // Popup content
                const popupContent = `
                    <div class="p-3 min-w-64">
                        <div class="flex items-center space-x-2 mb-2">
                            <div class="w-3 h-3 rounded-full" style="background-color: ${color}"></div>
                            <h6 class="font-semibold text-gray-800 text-sm">${zone.zone_name}</h6>
                        </div>
                        <p class="text-xs text-gray-600 mb-1">${zone.zone_code}</p>
                        ${zone.description ? `<p class="text-xs text-gray-500 mb-2">${zone.description}</p>` : ''}
                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="text-center p-2 bg-blue-50 rounded">
                                <div class="font-semibold text-blue-600">${zone.delivery_count || 0}</div>
                                <div class="text-gray-500">ทั้งหมด</div>
                            </div>
                            <div class="text-center p-2 bg-yellow-50 rounded">
                                <div class="font-semibold text-yellow-600">${zone.pending_count || 0}</div>
                                <div class="text-gray-500">รอจัดส่ง</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 font-mono">
                            Center: ${centerLat.toFixed(4)}, ${centerLng.toFixed(4)}
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                allMarkers.push(marker);
            });
            
            // Fit bounds to show all zones
            if (allMarkers.length > 0) {
                const group = new L.featureGroup(allMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
                
                // Show success message
                showToast('success', `แสดงโซนทั้งหมด ${zonesData.length} โซนสำเร็จ`);
                updateProgress(100);
            }
        }

        function showTutorial() {
            tutorialSteps = [
                {
                    title: '🎯 ยินดีต้อนรับสู่ Zone Picker!',
                    content: 'เครื่องมือนี้จะช่วยให้คุณเลือกพื้นที่โซนการจัดส่งได้อย่างง่ายดาย'
                },
                {
                    title: '🖱️ การวาดสี่เหลี่ยม',
                    content: 'คลิกที่ไอคอน □ สีน้ำเงินในแผนที่ แล้วลากเส้นทแยงเพื่อสร้างสี่เหลี่ยม'
                },
                {
                    title: '🔺 การวาดรูปหลายเหลี่ยม',
                    content: 'คลิกที่ไอคอน ▲ สีแดง แล้วคลิกจุดต่างๆ เพื่อสร้างรูปคางหมูหรือรูปร่างอื่นๆ'
                },
                {
                    title: '✏️ การแก้ไข',
                    content: 'ใช้ไอคอน ✏️ เพื่อแก้ไขขอบเขตที่วาดแล้ว ลากจุดต่างๆ เพื่อปรับรูปร่าง'
                },
                {
                    title: '⌨️ Keyboard Shortcuts',
                    content: 'Ctrl+C = คัดลอก, Ctrl+Enter = บันทึก, Ctrl+Backspace = ลบทั้งหมด'
                },
                {
                    title: '💾 การบันทึก',
                    content: 'เมื่อเสร็จแล้ว กดปุ่ม "ใช้พิกัดนี้" เพื่อส่งข้อมูลกลับไปยังฟอร์ม'
                }
            ];
            
            tutorialStep = 0;
            showTutorialStep();
        }
        
        function showTutorialStep() {
            if (tutorialStep >= tutorialSteps.length) {
                document.getElementById('tutorial-overlay').style.display = 'none';
                localStorage.setItem('zonePicker_visited', 'true');
                showToast('success', 'พร้อมใช้งาน! 🚀', 'เริ่มวาดขอบเขตโซนได้เลย');
                return;
            }
            
            const step = tutorialSteps[tutorialStep];
            document.getElementById('tutorial-content').innerHTML = `
                <div class="tutorial-step active">
                    <div class="text-center mb-6">
                        <div class="text-3xl mb-4">${step.title}</div>
                        <p class="text-gray-600 text-lg">${step.content}</p>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">ขั้นตอน ${tutorialStep + 1} จาก ${tutorialSteps.length}</span>
                        <div class="space-x-3">
                            ${tutorialStep > 0 ? '<button onclick="previousTutorialStep()" class="btn btn-secondary">ย้อนกลับ</button>' : ''}
                            <button onclick="nextTutorialStep()" class="btn btn-primary">
                                ${tutorialStep === tutorialSteps.length - 1 ? 'เริ่มใช้งาน' : 'ถัดไป'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('tutorial-overlay').style.display = 'block';
        }
        
        function nextTutorialStep() {
            tutorialStep++;
            showTutorialStep();
        }
        
        function previousTutorialStep() {
            tutorialStep--;
            showTutorialStep();
        }

        // Helper functions
        function showToast(icon, title, text = '') {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                toast: true,
                position: 'top-end',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            updateProgress(50);
            
            <?php if ($show_all_zones && $zones_data): ?>
            // Show all zones mode
            console.log('Show all zones mode enabled');
            console.log('Zones data:', <?php echo json_encode($zones_data); ?>);
            
            // Hide drawing tools for view-only mode
            const drawingTools = document.querySelector('.drawing-tools');
            const saveBtn = document.getElementById('apply-btn');
            if (drawingTools) drawingTools.style.display = 'none';
            if (saveBtn) saveBtn.style.display = 'none';
            
            // Load all zones
            setTimeout(() => {
                loadAllZones(<?php echo json_encode($zones_data); ?>);
            }, 1000);
            
            return; // Skip individual zone loading
            <?php endif; ?>
            
                        // Initialize with existing polygon or bounds
            console.log('Existing polygon data:', existingPolygonData);
            
            if (existingPolygonData.coordinates && existingPolygonData.type === 'polygon') {
                try {
                    // Load complex polygon
                    const polygonCoords = JSON.parse(existingPolygonData.coordinates);
                    console.log('Loading existing polygon:', polygonCoords);
                    
                    const polygon = L.polygon(polygonCoords, {
                        color: '<?php echo $zone_color; ?>',
                        weight: 3,
                        opacity: 0.8,
                        fillColor: '<?php echo $zone_color; ?>',
                        fillOpacity: 0.2
                    }).addTo(map);
                    
                    drawnItems.addLayer(polygon);
                    
                    // Set current bounds based on polygon
                    const bounds = polygon.getBounds();
                    currentBounds = {
                        minLat: bounds.getSouth(),
                        maxLat: bounds.getNorth(),
                        minLng: bounds.getWest(),
                        maxLng: bounds.getEast()
                    };
                    
                    // Fit map to show the polygon
                    map.fitBounds(polygon.getBounds(), { padding: [20, 20] });
                    
                    updateProgress(75);
                    showToast('success', 'โหลด Polygon เรียบร้อย', `โซน: <?php echo $zone_display_name; ?>`);
                    
                } catch (e) {
                    console.error('Error parsing polygon coordinates:', e);
                    // Fallback to rectangle bounds
                    loadRectangleBounds();
                }
            } else if (initialBounds.minLat && initialBounds.maxLat && initialBounds.minLng && initialBounds.maxLng) {
                loadRectangleBounds();
            }
            
            function loadRectangleBounds() {
                console.log('Loading rectangle bounds:', initialBounds);
                
                // Check if coordinates form a valid rectangle
                const latDiff = Math.abs(initialBounds.maxLat - initialBounds.minLat);
                const lngDiff = Math.abs(initialBounds.maxLng - initialBounds.minLng);
                const isVerySmall = latDiff < 0.0001 || lngDiff < 0.0001;
                
                let finalBounds = { ...initialBounds };
                
                if (isVerySmall) {
                    // Expand very small or single-point coordinates
                    const offset = 0.002;
                    const centerLat = (initialBounds.minLat + initialBounds.maxLat) / 2;
                    const centerLng = (initialBounds.minLng + initialBounds.maxLng) / 2;
                    
                    finalBounds = {
                        minLat: centerLat - offset,
                        maxLat: centerLat + offset,
                        minLng: centerLng - offset,
                        maxLng: centerLng + offset
                    };
                    
                    console.log('Expanded small bounds to:', finalBounds);
                }
                
                // Create rectangle with final bounds
                const rectangle = L.rectangle([
                    [finalBounds.minLat, finalBounds.minLng],
                    [finalBounds.maxLat, finalBounds.maxLng]
                ], {
                    color: '<?php echo $zone_color; ?>',
                    weight: 3,
                    opacity: 0.8,
                    fillColor: '<?php echo $zone_color; ?>',
                    fillOpacity: 0.2
                });
                
                drawnItems.addLayer(rectangle);
                currentBounds = finalBounds;
                
                // Update display immediately
                updateDisplay();
                updateStatus('complete', 'โหลดพิกัดเสร็จสิ้น');
                updateProgress(100);
                
                // Enable apply button
                const applyBtn = document.getElementById('apply-btn');
                if (applyBtn) {
                    applyBtn.disabled = false;
                    applyBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    applyBtn.classList.add('hover:bg-green-600');
                }
                
                // Fit map to bounds with padding
            const boundsRect = L.latLngBounds(
                    [finalBounds.minLat, finalBounds.minLng],
                    [finalBounds.maxLat, finalBounds.maxLng]
                );
                
                setTimeout(() => {
                    map.fitBounds(boundsRect, { 
                        padding: [50, 50],
                        maxZoom: 16
                    });
                    console.log('Map fitted to bounds:', finalBounds);
                    
                    // Show success message
                    showToast('success', 'โหลดพิกัดสำเร็จ', 'พิกัดเดิมถูกโหลดมาแล้ว');
                }, 500);
            }
            
            // Show tutorial for first-time users
            if (isFirstTime) {
                setTimeout(() => {
                    showTutorial();
                }, 1000);
            } else {
                setTimeout(() => {
                    showToast('info', 'ยินดีต้อนรับกลับ! 👋', 'เลือกเครื่องมือเพื่อเริ่มวาดขอบเขต');
                }, 500);
            }
            
            updateProgress(100);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'c':
                        e.preventDefault();
                        copyCoordinates();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        applyCoordinates();
                        break;
                    case 'Backspace':
                        e.preventDefault();
                        clearAll();
                        break;
                }
            }
        });

        // Close tutorial overlay when clicking outside
        document.getElementById('tutorial-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html> 