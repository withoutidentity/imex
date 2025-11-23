<?php
$page_title = 'วางแผนเส้นทางการจัดส่ง';
require_once '../config/config.php';

// Get zone ID from URL
$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;

if (!$zone_id) {
    header('Location: zones.php');
    exit;
}

// Validate that zone exists
try {
    $stmt = $conn->prepare("SELECT * FROM zone_area WHERE id = ?");
    $stmt->execute([$zone_id]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$zone) {
        header('Location: zones.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: zones.php');
    exit;
}

include '../includes/header.php';

// Handle route planning
$route_result = '';
$route_error = '';
$optimized_route = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_route'])) {
    $result = calculateOptimalRoute($zone_id, $_POST);
    if ($result['success']) {
        $optimized_route = $result['route'];
        $route_result = "คำนวณเส้นทางสำเร็จ: ระยะทางรวม " . number_format($result['total_distance'], 2) . " กม. ใช้เวลาโดยประมาณ " . $result['estimated_time'];
    } else {
        $route_error = $result['error'];
    }
}

function calculateOptimalRoute($zone_id, $params) {
    global $conn;
    
    try {
        // Get deliveries in zone
        $stmt = $conn->prepare("
            SELECT da.*, za.zone_name, za.color_code 
            FROM delivery_address da 
            JOIN zone_area za ON da.zone_id = za.id 
            WHERE da.zone_id = ? AND da.delivery_status = 'pending' AND da.latitude IS NOT NULL AND da.longitude IS NOT NULL
            ORDER BY da.created_at
        ");
        $stmt->execute([$zone_id]);
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($deliveries)) {
            return ['success' => false, 'error' => 'ไม่พบรายการจัดส่งในโซนนี้'];
        }
        
        // Start point (depot or first delivery)
        $start_point = [
            'lat' => isset($params['start_lat']) ? floatval($params['start_lat']) : floatval($deliveries[0]['latitude']),
            'lng' => isset($params['start_lng']) ? floatval($params['start_lng']) : floatval($deliveries[0]['longitude']),
            'name' => isset($params['start_name']) ? $params['start_name'] : 'จุดเริ่มต้น'
        ];
        
        // Optimize route using nearest neighbor algorithm
        $optimized = optimizeRouteNearestNeighbor($deliveries, $start_point);
        
        return [
            'success' => true,
            'route' => $optimized['route'],
            'total_distance' => $optimized['total_distance'],
            'estimated_time' => $optimized['estimated_time']
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function optimizeRouteNearestNeighbor($deliveries, $start_point) {
    $route = [];
    $unvisited = $deliveries;
    $current_point = $start_point;
    $total_distance = 0;
    
    // Add start point
    $route[] = [
        'type' => 'start',
        'point' => $start_point,
        'distance_from_previous' => 0,
        'cumulative_distance' => 0
    ];
    
    while (!empty($unvisited)) {
        $nearest_index = -1;
        $nearest_distance = PHP_FLOAT_MAX;
        
        // Find nearest unvisited delivery
        foreach ($unvisited as $index => $delivery) {
            $distance = calculateDistance(
                $current_point['lat'], $current_point['lng'],
                $delivery['latitude'], $delivery['longitude']
            );
            
            if ($distance < $nearest_distance) {
                $nearest_distance = $distance;
                $nearest_index = $index;
            }
        }
        
        // Add nearest delivery to route
        if ($nearest_index >= 0) {
            $delivery = $unvisited[$nearest_index];
            $total_distance += $nearest_distance;
            
            $route[] = [
                'type' => 'delivery',
                'delivery' => $delivery,
                'point' => [
                    'lat' => $delivery['latitude'],
                    'lng' => $delivery['longitude'],
                    'name' => $delivery['recipient_name']
                ],
                'distance_from_previous' => $nearest_distance,
                'cumulative_distance' => $total_distance
            ];
            
            $current_point = [
                'lat' => $delivery['latitude'],
                'lng' => $delivery['longitude'],
                'name' => $delivery['recipient_name']
            ];
            
            // Remove from unvisited
            unset($unvisited[$nearest_index]);
            $unvisited = array_values($unvisited);
        }
    }
    
    // Calculate estimated time (assuming 30 km/h average speed + 5 minutes per delivery)
    $driving_time = ($total_distance / 30) * 60; // minutes
    $delivery_time = count($deliveries) * 5; // 5 minutes per delivery
    $estimated_time = $driving_time + $delivery_time;
    
    return [
        'route' => $route,
        'total_distance' => $total_distance,
        'estimated_time' => formatTime($estimated_time)
    ];
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // km
    
    $lat_delta = deg2rad($lat2 - $lat1);
    $lng_delta = deg2rad($lng2 - $lng1);
    
    $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lng_delta / 2) * sin($lng_delta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

function formatTime($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return sprintf('%d ชั่วโมง %d นาที', $hours, $mins);
    } else {
        return sprintf('%d นาที', $mins);
    }
}

function generateGoogleMapsURL($route) {
    if (empty($route)) return '#';
    
    $waypoints = [];
    foreach ($route as $stop) {
        $waypoints[] = $stop['point']['lat'] . ',' . $stop['point']['lng'];
    }
    
    if (count($waypoints) < 2) return '#';
    
    $origin = array_shift($waypoints);
    $destination = array_pop($waypoints);
    
    $url = 'https://www.google.com/maps/dir/' . $origin;
    
    if (!empty($waypoints)) {
        $url .= '/' . implode('/', array_slice($waypoints, 0, 8)); // Google Maps limit
    }
    
    $url .= '/' . $destination;
    
    return $url;
}

// Get deliveries in zone  
$deliveries = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM delivery_address 
        WHERE zone_id = ? AND delivery_status = 'pending' AND latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY created_at
    ");
    $stmt->execute([$zone_id]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $route_error = 'ไม่สามารถโหลดข้อมูลโซนได้';
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3 mb-2">
                    <a href="zones.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-2"></i>กลับไปยังโซน
                    </a>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">วางแผนเส้นทางการจัดส่ง</h1>
                <p class="text-gray-600 mt-2">
                    <span class="w-4 h-4 rounded-full inline-block mr-2" style="background-color: <?php echo $zone['color_code']; ?>"></span>
                    <?php echo htmlspecialchars($zone['zone_name']); ?> (<?php echo htmlspecialchars($zone['zone_code']); ?>)
                </p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-route text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if (!empty($route_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($route_result); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($route_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($route_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($deliveries)): ?>
        <!-- No Deliveries -->
        <div class="bg-white p-8 rounded-lg shadow-md text-center">
            <i class="fas fa-inbox text-6xl text-gray-400 mb-4"></i>
            <h2 class="text-xl font-bold text-gray-600 mb-2">ไม่มีรายการจัดส่งในโซนนี้</h2>
            <p class="text-gray-500 mb-4">ไม่พบพัสดุที่รอการจัดส่งในโซนนี้</p>
            <a href="zones.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                กลับไปยังโซน
            </a>
        </div>

    <?php else: ?>
        <!-- Route Planning Form -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">ตั้งค่าการวางแผนเส้นทาง</h2>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">จุดเริ่มต้น (ลาติจูด)</label>
                            <input type="number" name="start_lat" step="0.000001" value="<?php echo $zone['center_lat']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">จุดเริ่มต้น (ลองจิจูด)</label>
                            <input type="number" name="start_lng" step="0.000001" value="<?php echo $zone['center_lng']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อจุดเริ่มต้น</label>
                            <input type="text" name="start_name" value="ศูนย์กระจายสินค้า" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <button type="submit" name="calculate_route" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            <i class="fas fa-calculator mr-2"></i>คำนวณเส้นทางประหยัด
                        </button>
                    </form>
                    
                    <!-- Zone Statistics -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-semibold text-gray-800 mb-3">สถิติโซน</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>รายการจัดส่ง:</span>
                                <span class="font-medium"><?php echo count($deliveries); ?> รายการ</span>
                            </div>
                            <div class="flex justify-between">
                                <span>พื้นที่โซน:</span>
                                <span class="font-medium">
                                    <?php 
                                    $area = calculateZoneArea($zone);
                                    echo number_format($area, 2); 
                                    ?> ตร.กม.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">แผนที่โซนและจุดจัดส่ง</h2>
                    <div id="route-map" class="map-container"></div>
                </div>
            </div>
        </div>

        <?php if (!empty($optimized_route)): ?>
            <!-- Optimized Route Results -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">เส้นทางที่คำนวณได้</h2>
                    
                    <!-- Route Summary -->
                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">ระยะทางรวม:</span>
                                <div class="text-lg font-bold text-blue-600">
                                    <?php echo number_format(end($optimized_route)['cumulative_distance'], 2); ?> กม.
                                </div>
                            </div>
                            <div>
                                <span class="text-gray-600">เวลาโดยประมาณ:</span>
                                <div class="text-lg font-bold text-green-600">
                                    <?php echo $route_result ? preg_replace('/.*ใช้เวลาโดยประมาณ (.+)/', '$1', $route_result) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Google Maps Link -->
                    <a href="<?php echo generateGoogleMapsURL($optimized_route); ?>" target="_blank" 
                       class="block w-full bg-green-600 text-white text-center py-3 px-4 rounded-lg mb-4 hover:bg-green-700 transition-colors">
                        <i class="fas fa-external-link-alt mr-2"></i>เปิดใน Google Maps
                    </a>
                    
                    <!-- Route Steps -->
                    <div class="space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                        <?php foreach ($optimized_route as $index => $stop): ?>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        <?php if ($stop['type'] === 'start'): ?>
                                            <i class="fas fa-play-circle text-green-500 mr-2"></i>
                                            <?php echo htmlspecialchars($stop['point']['name']); ?>
                                        <?php else: ?>
                                            <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                                            <?php echo htmlspecialchars($stop['delivery']['recipient_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($stop['type'] === 'delivery'): ?>
                                        <div class="text-sm text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($stop['delivery']['awb_number']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars(substr($stop['delivery']['address'], 0, 50)) . '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php if ($index > 0): ?>
                                            +<?php echo number_format($stop['distance_from_previous'], 2); ?> กม. | 
                                        <?php endif; ?>
                                        รวม: <?php echo number_format($stop['cumulative_distance'], 2); ?> กม.
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Delivery List -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">รายการจัดส่งทั้งหมด</h2>
                    
                    <div class="space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                        <?php foreach ($deliveries as $delivery): ?>
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($delivery['recipient_name']); ?>
                                        </div>
                                        <div class="text-sm text-blue-600">
                                            <?php echo htmlspecialchars($delivery['awb_number']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo htmlspecialchars($delivery['address']); ?>
                                        </div>
                                        <?php if ($delivery['recipient_phone']): ?>
                                            <div class="text-xs text-gray-500">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($delivery['recipient_phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button onclick="showDeliveryOnMap(<?php echo $delivery['latitude']; ?>, <?php echo $delivery['longitude']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800 ml-2">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Delivery List (when no route calculated) -->
        <?php if (empty($optimized_route)): ?>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">รายการจัดส่งในโซน</h2>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>AWB</th>
                                <th>ชื่อผู้รับ</th>
                                <th>ที่อยู่</th>
                                <th>เบอร์โทร</th>
                                <th>พิกัด</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $delivery): ?>
                                <tr>
                                    <td class="font-medium"><?php echo htmlspecialchars($delivery['awb_number']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['recipient_name']); ?></td>
                                    <td class="max-w-xs truncate"><?php echo htmlspecialchars($delivery['address']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['recipient_phone']); ?></td>
                                    <td class="text-xs">
                                        <?php echo number_format($delivery['latitude'], 4); ?>,<br>
                                        <?php echo number_format($delivery['longitude'], 4); ?>
                                    </td>
                                    <td>
                                        <button onclick="showDeliveryOnMap(<?php echo $delivery['latitude']; ?>, <?php echo $delivery['longitude']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
let routeMap;
let markers = [];
let routePath = null;
const zone = <?php echo json_encode($zone); ?>;
const deliveries = <?php echo json_encode($deliveries); ?>;
const optimizedRoute = <?php echo json_encode($optimized_route); ?>;

function initializeRouteMap() {
    routeMap = new google.maps.Map(document.getElementById('route-map'), {
        zoom: 14,
        center: { lat: parseFloat(zone.center_lat), lng: parseFloat(zone.center_lng) }
    });
    
    // Show zone boundary
    showZoneBoundary();
    
    // Show deliveries
    showDeliveries();
    
    // Show optimized route if available
    if (optimizedRoute.length > 0) {
        showOptimizedRoute();
    }
}

function showZoneBoundary() {
    const zoneBounds = [
        { lat: parseFloat(zone.min_lat), lng: parseFloat(zone.min_lng) },
        { lat: parseFloat(zone.min_lat), lng: parseFloat(zone.max_lng) },
        { lat: parseFloat(zone.max_lat), lng: parseFloat(zone.max_lng) },
        { lat: parseFloat(zone.max_lat), lng: parseFloat(zone.min_lng) }
    ];
    
    new google.maps.Polygon({
        paths: zoneBounds,
        strokeColor: zone.color_code,
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: zone.color_code,
        fillOpacity: 0.1,
        map: routeMap
    });
}

function showDeliveries() {
    deliveries.forEach(function(delivery, index) {
        const marker = new google.maps.Marker({
            position: {
                lat: parseFloat(delivery.latitude),
                lng: parseFloat(delivery.longitude)
            },
            map: routeMap,
            title: delivery.recipient_name,
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(createDeliveryMarkerSVG(zone.color_code, index + 1)),
                scaledSize: new google.maps.Size(30, 30),
                anchor: new google.maps.Point(15, 30)
            }
        });
        
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div class="p-3">
                    <h6 class="font-semibold text-gray-800">${delivery.awb_number}</h6>
                    <p class="text-sm text-gray-600">${delivery.recipient_name}</p>
                    <p class="text-xs text-gray-500">${delivery.address}</p>
                    ${delivery.recipient_phone ? `<p class="text-xs text-gray-500"><i class="fas fa-phone mr-1"></i>${delivery.recipient_phone}</p>` : ''}
                </div>
            `
        });
        
        marker.addListener('click', function() {
            infoWindow.open(routeMap, marker);
        });
        
        markers.push(marker);
    });
}

function showOptimizedRoute() {
    const routeCoordinates = optimizedRoute.map(stop => ({
        lat: parseFloat(stop.point.lat),
        lng: parseFloat(stop.point.lng)
    }));
    
    // Clear existing markers
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    
    // Create route path
    routePath = new google.maps.Polyline({
        path: routeCoordinates,
        geodesic: true,
        strokeColor: '#3B82F6',
        strokeOpacity: 1.0,
        strokeWeight: 3,
        map: routeMap
    });
    
    // Add numbered markers for route
    optimizedRoute.forEach(function(stop, index) {
        const isStart = stop.type === 'start';
        
        const marker = new google.maps.Marker({
            position: {
                lat: parseFloat(stop.point.lat),
                lng: parseFloat(stop.point.lng)
            },
            map: routeMap,
            title: stop.point.name,
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                    isStart ? createStartMarkerSVG() : createRouteMarkerSVG(index, zone.color_code)
                ),
                scaledSize: new google.maps.Size(40, 40),
                anchor: new google.maps.Point(20, 40)
            }
        });
        
        const infoContent = isStart ? 
            `<div class="p-3">
                <h6 class="font-semibold text-gray-800">จุดเริ่มต้น</h6>
                <p class="text-sm text-gray-600">${stop.point.name}</p>
            </div>` :
            `<div class="p-3">
                <h6 class="font-semibold text-gray-800">จุดที่ ${index} - ${stop.delivery.awb_number}</h6>
                <p class="text-sm text-gray-600">${stop.delivery.recipient_name}</p>
                <p class="text-xs text-gray-500">ระยะทาง: +${stop.distance_from_previous.toFixed(2)} กม.</p>
                <p class="text-xs text-gray-500">รวม: ${stop.cumulative_distance.toFixed(2)} กม.</p>
            </div>`;
        
        const infoWindow = new google.maps.InfoWindow({
            content: infoContent
        });
        
        marker.addListener('click', function() {
            infoWindow.open(routeMap, marker);
        });
        
        markers.push(marker);
    });
    
    // Fit bounds to show entire route
    const bounds = new google.maps.LatLngBounds();
    routeCoordinates.forEach(coord => bounds.extend(coord));
    routeMap.fitBounds(bounds);
}

function showDeliveryOnMap(lat, lng) {
    const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
    routeMap.setCenter(position);
    routeMap.setZoom(16);
}

function createDeliveryMarkerSVG(color, number) {
    return `<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
        <circle cx="15" cy="15" r="12" fill="${color}" stroke="white" stroke-width="2"/>
        <text x="15" y="20" font-family="Arial" font-size="10" font-weight="bold" text-anchor="middle" fill="white">${number}</text>
    </svg>`;
}

function createRouteMarkerSVG(number, color) {
    return `<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 5 C15 5, 10 10, 10 17 C10 25, 20 35, 20 35 C20 35, 30 25, 30 17 C30 10, 25 5, 20 5 Z" fill="${color}" stroke="white" stroke-width="2"/>
        <circle cx="20" cy="17" r="8" fill="white"/>
        <text x="20" y="22" font-family="Arial" font-size="10" font-weight="bold" text-anchor="middle" fill="${color}">${number}</text>
    </svg>`;
}

function createStartMarkerSVG() {
    return `<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 5 C15 5, 10 10, 10 17 C10 25, 20 35, 20 35 C20 35, 30 25, 30 17 C30 10, 25 5, 20 5 Z" fill="#10B981" stroke="white" stroke-width="2"/>
        <circle cx="20" cy="17" r="8" fill="white"/>
        <text x="20" y="22" font-family="Arial" font-size="8" font-weight="bold" text-anchor="middle" fill="#10B981">START</text>
    </svg>`;
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof google !== 'undefined') {
        initializeRouteMap();
    }
});
</script>

<?php 
function calculateZoneArea($zone) {
    $lat_diff = $zone['max_lat'] - $zone['min_lat'];
    $lng_diff = $zone['max_lng'] - $zone['min_lng'];
    
    // Rough calculation in square kilometers
    $lat_km = $lat_diff * 111; // 1 degree latitude ≈ 111 km
    $lng_km = $lng_diff * 111 * cos(deg2rad($zone['center_lat'])); // Adjust for longitude
    
    return $lat_km * $lng_km;
}

include '../includes/footer.php'; 
?> 