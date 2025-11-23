<?php
$page_title = 'วิเคราะห์และจัดโซน';
require_once '../config/config.php';

// Detect if we're in pages directory
$base_path = (basename(dirname(__FILE__)) == 'pages') ? '../' : '';
require_once $base_path . 'config/config.php';
include $base_path . 'includes/header.php';

// Handle zone analysis request
$analysis_result = '';
$analysis_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['analyze_zones'])) {
        $result = analyzeAndCreateZones($_POST['zone_method'], $_POST['zone_count']);
        if ($result['success']) {
            $analysis_result = $result['message'];
        } else {
            $analysis_error = $result['error'];
        }
    }
}

function analyzeAndCreateZones($method, $zone_count) {
    global $conn;
    
    try {
        // Get all addresses with coordinates
        $stmt = $conn->prepare("SELECT * FROM delivery_address WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
        $stmt->execute();
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($addresses)) {
            return ['success' => false, 'error' => 'ไม่พบข้อมูลที่มีพิกัดสำหรับการวิเคราะห์'];
        }
        
        // Clear existing zones
        $stmt = $conn->prepare("DELETE FROM zone_area WHERE zone_code LIKE 'AUTO%'");
        $stmt->execute();
        
        // Reset zone assignments
        $stmt = $conn->prepare("UPDATE delivery_address SET zone_id = NULL");
        $stmt->execute();
        
        $zones = [];
        
        switch ($method) {
            case 'grid':
                $zones = createGridZones($addresses, $zone_count);
                break;
            case 'kmeans':
                $zones = createKMeansZones($addresses, $zone_count);
                break;
            case 'density':
                $zones = createDensityZones($addresses);
                break;
            default:
                return ['success' => false, 'error' => 'วิธีการจัดโซนไม่ถูกต้อง'];
        }
        
        $created_zones = 0;
        $assigned_addresses = 0;
        
        // Create zones in database
        foreach ($zones as $zone) {
            $stmt = $conn->prepare("INSERT INTO zone_area (zone_code, zone_name, description, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $zone['zone_code'],
                $zone['zone_name'],
                $zone['description'],
                $zone['min_lat'],
                $zone['max_lat'],
                $zone['min_lng'],
                $zone['max_lng'],
                $zone['center_lat'],
                $zone['center_lng'],
                $zone['color_code']
            ]);
            
            $zone_id = $conn->lastInsertId();
            $created_zones++;
            
            // Assign addresses to zones
            foreach ($zone['addresses'] as $address) {
                $stmt = $conn->prepare("UPDATE delivery_address SET zone_id = ? WHERE id = ?");
                $stmt->execute([$zone_id, $address['id']]);
                $assigned_addresses++;
            }
        }
        
        return [
            'success' => true,
            'message' => "สร้างโซนสำเร็จ: {$created_zones} โซน, จัดกลุ่มที่อยู่: {$assigned_addresses} รายการ"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createGridZones($addresses, $zone_count) {
    // Find bounds
    $min_lat = min(array_column($addresses, 'latitude'));
    $max_lat = max(array_column($addresses, 'latitude'));
    $min_lng = min(array_column($addresses, 'longitude'));
    $max_lng = max(array_column($addresses, 'longitude'));
    
    // Calculate grid size
    $grid_size = ceil(sqrt($zone_count));
    $lat_step = ($max_lat - $min_lat) / $grid_size;
    $lng_step = ($max_lng - $min_lng) / $grid_size;
    
    $zones = [];
    $zone_index = 1;
    
    for ($row = 0; $row < $grid_size; $row++) {
        for ($col = 0; $col < $grid_size; $col++) {
            if ($zone_index > $zone_count) break;
            
            $zone_min_lat = $min_lat + ($row * $lat_step);
            $zone_max_lat = $min_lat + (($row + 1) * $lat_step);
            $zone_min_lng = $min_lng + ($col * $lng_step);
            $zone_max_lng = $min_lng + (($col + 1) * $lng_step);
            
            $zone_addresses = [];
            foreach ($addresses as $address) {
                if ($address['latitude'] >= $zone_min_lat && $address['latitude'] < $zone_max_lat &&
                    $address['longitude'] >= $zone_min_lng && $address['longitude'] < $zone_max_lng) {
                    $zone_addresses[] = $address;
                }
            }
            
            if (!empty($zone_addresses)) {
                $zones[] = [
                    'zone_code' => "AUTO" . str_pad($zone_index, 3, '0', STR_PAD_LEFT),
                    'zone_name' => "โซนอัตโนมัติ " . $zone_index,
                    'description' => "โซนที่สร้างด้วยระบบ Grid - " . count($zone_addresses) . " รายการ",
                    'min_lat' => $zone_min_lat,
                    'max_lat' => $zone_max_lat,
                    'min_lng' => $zone_min_lng,
                    'max_lng' => $zone_max_lng,
                    'center_lat' => ($zone_min_lat + $zone_max_lat) / 2,
                    'center_lng' => ($zone_min_lng + $zone_max_lng) / 2,
                    'color_code' => getRandomColor($zone_index),
                    'addresses' => $zone_addresses
                ];
            }
            
            $zone_index++;
        }
    }
    
    return $zones;
}

function createKMeansZones($addresses, $zone_count) {
    // Simple K-means clustering
    $points = [];
    foreach ($addresses as $address) {
        $points[] = [
            'lat' => $address['latitude'],
            'lng' => $address['longitude'],
            'address' => $address
        ];
    }
    
    // Initialize centroids randomly
    $centroids = [];
    for ($i = 0; $i < $zone_count; $i++) {
        $random_point = $points[array_rand($points)];
        $centroids[] = [
            'lat' => $random_point['lat'],
            'lng' => $random_point['lng']
        ];
    }
    
    // K-means iterations
    for ($iteration = 0; $iteration < 10; $iteration++) {
        $clusters = array_fill(0, $zone_count, []);
        
        // Assign points to nearest centroid
        foreach ($points as $point) {
            $min_distance = PHP_FLOAT_MAX;
            $nearest_cluster = 0;
            
            for ($i = 0; $i < $zone_count; $i++) {
                $distance = calculateDistance($point['lat'], $point['lng'], $centroids[$i]['lat'], $centroids[$i]['lng']);
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $nearest_cluster = $i;
                }
            }
            
            $clusters[$nearest_cluster][] = $point;
        }
        
        // Update centroids
        for ($i = 0; $i < $zone_count; $i++) {
            if (!empty($clusters[$i])) {
                $centroids[$i]['lat'] = array_sum(array_column($clusters[$i], 'lat')) / count($clusters[$i]);
                $centroids[$i]['lng'] = array_sum(array_column($clusters[$i], 'lng')) / count($clusters[$i]);
            }
        }
    }
    
    // Convert clusters to zones
    $zones = [];
    for ($i = 0; $i < $zone_count; $i++) {
        if (!empty($clusters[$i])) {
            $zone_addresses = array_column($clusters[$i], 'address');
            $lats = array_column($clusters[$i], 'lat');
            $lngs = array_column($clusters[$i], 'lng');
            
            $zones[] = [
                'zone_code' => "AUTO" . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'zone_name' => "โซนอัตโนมัติ " . ($i + 1),
                'description' => "โซนที่สร้างด้วยระบบ K-means - " . count($zone_addresses) . " รายการ",
                'min_lat' => min($lats),
                'max_lat' => max($lats),
                'min_lng' => min($lngs),
                'max_lng' => max($lngs),
                'center_lat' => $centroids[$i]['lat'],
                'center_lng' => $centroids[$i]['lng'],
                'color_code' => getRandomColor($i + 1),
                'addresses' => $zone_addresses
            ];
        }
    }
    
    return $zones;
}

function createDensityZones($addresses) {
    // Density-based clustering
    $radius = 0.01; // ~1km radius
    $min_points = 5;
    
    $zones = [];
    $processed = [];
    $zone_index = 1;
    
    foreach ($addresses as $address) {
        if (in_array($address['id'], $processed)) continue;
        
        // Find all points within radius
        $cluster = [];
        foreach ($addresses as $other_address) {
            if (in_array($other_address['id'], $processed)) continue;
            
            $distance = calculateDistance($address['latitude'], $address['longitude'], 
                                        $other_address['latitude'], $other_address['longitude']);
            
            if ($distance <= $radius) {
                $cluster[] = $other_address;
                $processed[] = $other_address['id'];
            }
        }
        
        if (count($cluster) >= $min_points) {
            $lats = array_column($cluster, 'latitude');
            $lngs = array_column($cluster, 'longitude');
            
            $zones[] = [
                'zone_code' => "AUTO" . str_pad($zone_index, 3, '0', STR_PAD_LEFT),
                'zone_name' => "โซนอัตโนมัติ " . $zone_index,
                'description' => "โซนที่สร้างด้วยระบบ Density - " . count($cluster) . " รายการ",
                'min_lat' => min($lats),
                'max_lat' => max($lats),
                'min_lng' => min($lngs),
                'max_lng' => max($lngs),
                'center_lat' => array_sum($lats) / count($lats),
                'center_lng' => array_sum($lngs) / count($lngs),
                'color_code' => getRandomColor($zone_index),
                'addresses' => $cluster
            ];
            
            $zone_index++;
        }
    }
    
    return $zones;
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // km
    
    $lat1_rad = deg2rad($lat1);
    $lng1_rad = deg2rad($lng1);
    $lat2_rad = deg2rad($lat2);
    $lng2_rad = deg2rad($lng2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lng = $lng2_rad - $lng1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lng / 2) * sin($delta_lng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

function getRandomColor($index) {
    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'];
    return $colors[$index % count($colors)];
}

// Get current statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_addresses,
        COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as geocoded_addresses,
        COUNT(CASE WHEN zone_id IS NOT NULL THEN 1 END) as zoned_addresses
        FROM delivery_address");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_zones FROM zone_area");
    $stmt->execute();
    $stats['total_zones'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_zones'];
} catch (Exception $e) {
    $stats = ['total_addresses' => 0, 'geocoded_addresses' => 0, 'zoned_addresses' => 0, 'total_zones' => 0];
}

// Get zone analysis data
$zone_analysis = [];
try {
    $stmt = $conn->prepare("SELECT 
        za.zone_name,
        za.zone_code,
        za.color_code,
        COUNT(da.id) as address_count,
        za.center_lat,
        za.center_lng
        FROM zone_area za
        LEFT JOIN delivery_address da ON za.id = da.zone_id
        GROUP BY za.id
        ORDER BY address_count DESC");
    $stmt->execute();
    $zone_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $zone_analysis = [];
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">วิเคราะห์และจัดโซน</h1>
                <p class="text-gray-600 mt-2">วิเคราะห์ข้อมูลพิกัดจากตาราง delivery_address และจัดโซนอัตโนมัติ</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-analytics text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if (!empty($analysis_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($analysis_result); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($analysis_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($analysis_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-map-marker-alt text-3xl text-blue-600 mr-4"></i>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_addresses']); ?></div>
                    <div class="text-gray-600">ที่อยู่ทั้งหมด</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-crosshairs text-3xl text-green-600 mr-4"></i>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['geocoded_addresses']); ?></div>
                    <div class="text-gray-600">มีพิกัดแล้ว</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-layer-group text-3xl text-yellow-600 mr-4"></i>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['zoned_addresses']); ?></div>
                    <div class="text-gray-600">จัดโซนแล้ว</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-th-large text-3xl text-purple-600 mr-4"></i>
                <div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_zones']); ?></div>
                    <div class="text-gray-600">โซนทั้งหมด</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zone Analysis Control -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">วิเคราะห์และสร้างโซนอัตโนมัติ</h2>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="zone_method" class="block text-sm font-medium text-gray-700 mb-2">วิธีการจัดโซน</label>
                    <select id="zone_method" name="zone_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="grid">Grid System - จัดโซนแบบตาราง</option>
                        <option value="kmeans">K-means Clustering - จัดกลุ่มด้วย AI</option>
                        <option value="density">Density-based - จัดตามความหนาแน่น</option>
                    </select>
                </div>
                
                <div>
                    <label for="zone_count" class="block text-sm font-medium text-gray-700 mb-2">จำนวนโซน</label>
                    <input type="number" id="zone_count" name="zone_count" value="5" min="2" max="20" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="analyze_zones" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-cogs mr-2"></i>วิเคราะห์และสร้างโซน
                </button>
            </div>
        </form>

        <!-- Method Descriptions -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-700 mb-2">Grid System</h3>
                <p class="text-sm text-gray-600">จัดโซนแบบตาราง เหมาะสำหรับพื้นที่ที่มีการกระจายตัวสม่ำเสมอ</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-700 mb-2">K-means Clustering</h3>
                <p class="text-sm text-gray-600">ใช้ AI จัดกลุ่มตามความใกล้เคียง เหมาะสำหรับการกระจายตัวไม่สม่ำเสมอ</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-gray-700 mb-2">Density-based</h3>
                <p class="text-sm text-gray-600">จัดตามความหนาแน่น เหมาะสำหรับพื้นที่ที่มีการจับกลุ่มตามธรรมชาติ</p>
            </div>
        </div>
    </div>

    <!-- Current Zone Analysis -->
    <?php if (!empty($zone_analysis)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">วิเคราะห์โซนปัจจุบัน</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($zone_analysis as $zone): ?>
                    <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo $zone['color_code']; ?>"></div>
                            <div>
                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($zone['zone_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($zone['zone_code']); ?></p>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">จำนวนที่อยู่:</span>
                                <span class="font-medium"><?php echo number_format($zone['address_count']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">พิกัดกลาง:</span>
                                <span class="text-sm font-mono"><?php echo number_format($zone['center_lat'], 4); ?>, <?php echo number_format($zone['center_lng'], 4); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Map Visualization -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">แผนที่แสดงโซน</h2>
        <div id="map" class="w-full h-96 rounded-lg"></div>
    </div>
</div>

<script>
function initMap() {
    const map = new google.maps.Map(document.getElementById('map'), {
        zoom: <?php echo DEFAULT_ZOOM_LEVEL; ?>,
        center: {lat: <?php echo DEFAULT_MAP_CENTER_LAT; ?>, lng: <?php echo DEFAULT_MAP_CENTER_LNG; ?>} // นครศรีธรรมราช
    });

    <?php if (!empty($zone_analysis)): ?>
        const zones = <?php echo json_encode($zone_analysis); ?>;
        
        zones.forEach(zone => {
            if (zone.center_lat && zone.center_lng) {
                const marker = new google.maps.Marker({
                    position: {lat: parseFloat(zone.center_lat), lng: parseFloat(zone.center_lng)},
                    map: map,
                    title: zone.zone_name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8,
                        fillColor: zone.color_code,
                        fillOpacity: 0.8,
                        strokeColor: '#ffffff',
                        strokeWeight: 2
                    }
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px;">
                            <h3 style="margin: 0 0 5px 0; color: ${zone.color_code};">${zone.zone_name}</h3>
                            <p style="margin: 0; color: #666;">รหัสโซน: ${zone.zone_code}</p>
                            <p style="margin: 0; color: #666;">จำนวนที่อยู่: ${zone.address_count} รายการ</p>
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
            }
        });
    <?php endif; ?>
}
</script>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap"></script>

<?php include $base_path . 'includes/footer.php'; ?> 