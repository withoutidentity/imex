<?php
$page_title = 'แผนที่จัดส่งด้วย Leaflet.js (Simple)';
require_once '../config/config.php';
include '../includes/header.php';

// Get filter parameters
$province_filter = $_GET['province'] ?? '';
$zone_filter = $_GET['zone_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get delivery data with existing columns only
function getDeliveryDataSimple($province_filter = '', $zone_filter = '', $status_filter = '') {
    global $conn;
    
    $where_conditions = ["da.latitude IS NOT NULL", "da.longitude IS NOT NULL"];
    $params = [];
    
    if (!empty($province_filter)) {
        $where_conditions[] = "da.province = ?";
        $params[] = $province_filter;
    }
    
    if (!empty($zone_filter)) {
        $where_conditions[] = "da.zone_id = ?";
        $params[] = $zone_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "da.delivery_status = ?";
        $params[] = $status_filter;
    }
    
    $sql = "SELECT 
                da.id,
                da.awb_number,
                da.recipient_name,
                da.recipient_phone,
                da.address,
                da.subdistrict,
                da.district,
                da.province,
                da.postal_code,
                da.latitude,
                da.longitude,
                da.zone_id,
                da.geocoded_at,
                da.geocoding_status,
                da.delivery_status,
                da.created_at,
                za.zone_code,
                za.zone_name,
                za.color_code as zone_color
            FROM delivery_address da
            LEFT JOIN zone_area za ON da.zone_id = za.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY da.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
function getSimpleStats() {
    global $conn;
    
    $stats = [];
    
    // Total counts by status
    $stmt = $conn->prepare("
        SELECT 
            geocoding_status,
            COUNT(*) as total,
            COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) as geocoded
        FROM delivery_address 
        GROUP BY geocoding_status
    ");
    $stmt->execute();
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Provincial summary
    $stmt = $conn->prepare("
        SELECT 
            province,
            COUNT(*) as total_addresses,
            COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) as geocoded_count
        FROM delivery_address 
        GROUP BY province
        ORDER BY geocoded_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['by_province'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

$deliveries = getDeliveryDataSimple($province_filter, $zone_filter, $status_filter);
$stats = getSimpleStats();

// Get filter options
$stmt = $conn->prepare("SELECT DISTINCT province FROM delivery_address WHERE province IS NOT NULL ORDER BY province");
$stmt->execute();
$provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("SELECT id, zone_code, zone_name FROM zone_area ORDER BY zone_code");
$stmt->execute();
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<div class="fadeIn">
    <!-- Header -->
    <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6 rounded-lg shadow-lg mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold mb-2">แผนที่จัดส่งด้วย OpenStreetMap</h1>
                <p class="text-green-100">แสดงจุดจัดส่งทั้งหมดบนแผนที่แบบ Interactive</p>
            </div>
            <div class="hidden lg:block">
                <i class="fas fa-map text-5xl opacity-20"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        
        <!-- Control Panel -->
        <div class="xl:col-span-1 space-y-6">
            
            <!-- Filters -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">
                    <i class="fas fa-filter text-blue-600 mr-2"></i>ตัวกรอง
                </h2>
                
                <form method="GET" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">จังหวัด</label>
                        <select name="province" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo htmlspecialchars($province); ?>" 
                                        <?php echo $province_filter === $province ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($province); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">โซน</label>
                        <select name="zone_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>" 
                                        <?php echo $zone_filter == $zone['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($zone['zone_code'] . ' - ' . $zone['zone_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">สถานะการจัดส่ง</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">ทั้งหมด</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                            <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                            <option value="in_transit" <?php echo $status_filter === 'in_transit' ? 'selected' : ''; ?>>กำลังจัดส่ง</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>จัดส่งแล้ว</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>กรอง
                    </button>
                </form>
            </div>

            <!-- Statistics -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">
                    <i class="fas fa-chart-bar text-green-600 mr-2"></i>สถิติ
                </h2>
                
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span>จุดทั้งหมด:</span>
                        <span class="font-bold text-blue-600"><?php echo number_format(count($deliveries)); ?></span>
                    </div>
                    
                    <?php foreach ($stats['by_status'] as $status): ?>
                    <div class="border-t pt-2">
                        <div class="flex justify-between">
                            <span><?php echo ucfirst($status['geocoding_status']); ?>:</span>
                            <span class="font-bold"><?php echo number_format($status['geocoded']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Map Controls -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">
                    <i class="fas fa-layer-group text-indigo-600 mr-2"></i>ตัวควบคุมแผนที่
                </h2>
                
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="showClusters" checked class="mr-2">
                        <span class="text-sm">จัดกลุ่มจุด (Clustering)</span>
                    </label>
                    
                    <div class="pt-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทแผนที่</label>
                        <select id="mapLayer" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="osm">OpenStreetMap</option>
                            <option value="satellite">ภาพถ่ายดาวเทียม</option>
                            <option value="terrain">ภูมิประเทศ</option>
                        </select>
                    </div>
                    
                    <div class="pt-2">
                        <a href="../demo_thai_geocoding.php" class="block w-full text-center bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition-colors text-sm">
                            <i class="fas fa-rocket mr-2"></i>ทดสอบ Demo
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Container -->
        <div class="xl:col-span-3">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-4 bg-gray-50 border-b">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-800">แผนที่จุดจัดส่ง</h2>
                        <div class="flex items-center space-x-2">
                            <button onclick="fitAllMarkers()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                <i class="fas fa-expand-arrows-alt mr-1"></i>ดูทั้งหมด
                            </button>
                            <button onclick="locateUser()" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                <i class="fas fa-location-arrow mr-1"></i>ตำแหน่งฉัน
                            </button>
                        </div>
                    </div>
                </div>
                <div id="leaflet-map" style="height: 600px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
// Delivery data from PHP
const deliveryData = <?php echo json_encode($deliveries); ?>;

// Map initialization
let map;
let markers = [];
let markerClusterGroup;

// Initialize map
function initializeMap() {
    // Create map centered on Bangkok
    map = L.map('leaflet-map').setView([13.7563, 100.5018], 10);
    
    // Add tile layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });
    
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    
    const terrainLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a>'
    });
    
    // Add default layer
    osmLayer.addTo(map);
    
    // Layer change handler
    document.getElementById('mapLayer').addEventListener('change', function(e) {
        const selectedLayer = e.target.value;
        map.eachLayer(function(layer) {
            if (layer instanceof L.TileLayer) {
                map.removeLayer(layer);
            }
        });
        
        switch(selectedLayer) {
            case 'satellite':
                satelliteLayer.addTo(map);
                break;
            case 'terrain':
                terrainLayer.addTo(map);
                break;
            default:
                osmLayer.addTo(map);
        }
    });
    
    // Initialize marker cluster group
    markerClusterGroup = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50
    });
    
    // Add delivery markers
    addDeliveryMarkers();
    
    // Control handlers
    document.getElementById('showClusters').addEventListener('change', toggleClustering);
}

// Add delivery markers
function addDeliveryMarkers() {
    markers = [];
    
    deliveryData.forEach(function(delivery) {
        const lat = parseFloat(delivery.latitude);
        const lng = parseFloat(delivery.longitude);
        
        if (isNaN(lat) || isNaN(lng)) return;
        
        // Create marker icon based on status
        const icon = createMarkerIcon(delivery);
        
        // Create marker
        const marker = L.marker([lat, lng], { icon: icon });
        
        // Create popup content
        const popupContent = createPopupContent(delivery);
        marker.bindPopup(popupContent);
        
        // Store delivery data with marker
        marker.deliveryData = delivery;
        
        markers.push(marker);
        markerClusterGroup.addLayer(marker);
    });
    
    // Add to map
    map.addLayer(markerClusterGroup);
    
    // Fit bounds if we have markers
    if (markers.length > 0) {
        fitAllMarkers();
    }
}

// Create marker icon based on delivery properties
function createMarkerIcon(delivery) {
    let color = '#3B82F6'; // Default blue
    
    // Color by status
    switch(delivery.delivery_status) {
        case 'pending':
            color = '#EF4444'; // Red
            break;
        case 'assigned':
            color = '#F59E0B'; // Yellow
            break;
        case 'in_transit':
            color = '#3B82F6'; // Blue
            break;
        case 'delivered':
            color = '#10B981'; // Green
            break;
    }
    
    return L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="
            background-color: ${color};
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        "></div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10]
    });
}

// Create popup content
function createPopupContent(delivery) {
    return `
        <div class="p-2" style="min-width: 250px;">
            <div class="font-bold text-blue-600 mb-2">${delivery.awb_number}</div>
            <div class="mb-2">
                <strong>ผู้รับ:</strong> ${delivery.recipient_name}<br>
                <strong>โทร:</strong> ${delivery.recipient_phone || 'ไม่ระบุ'}
            </div>
            <div class="mb-2 text-sm">
                <strong>ที่อยู่:</strong><br>
                ${delivery.address}
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                ${delivery.subdistrict ? `<div><span class="text-gray-600">ตำบล:</span> ${delivery.subdistrict}</div>` : ''}
                ${delivery.district ? `<div><span class="text-gray-600">เขต:</span> ${delivery.district}</div>` : ''}
                ${delivery.province ? `<div><span class="text-gray-600">จังหวัด:</span> ${delivery.province}</div>` : ''}
                ${delivery.postal_code ? `<div><span class="text-gray-600">รหัสไปรษณีย์:</span> ${delivery.postal_code}</div>` : ''}
            </div>
            <div class="flex justify-between text-xs text-gray-600">
                <span>สถานะ: ${delivery.delivery_status}</span>
                <span>Geocoded: ${delivery.geocoding_status}</span>
            </div>
            ${delivery.zone_name ? `<div class="text-xs text-purple-600 mt-1">โซน: ${delivery.zone_code} - ${delivery.zone_name}</div>` : ''}
        </div>
    `;
}

// Toggle clustering
function toggleClustering() {
    const showClusters = document.getElementById('showClusters').checked;
    
    if (showClusters) {
        // Remove individual markers
        markers.forEach(marker => {
            if (map.hasLayer(marker)) {
                map.removeLayer(marker);
            }
        });
        
        // Add cluster group
        if (!map.hasLayer(markerClusterGroup)) {
            map.addLayer(markerClusterGroup);
        }
    } else {
        // Remove cluster group
        if (map.hasLayer(markerClusterGroup)) {
            map.removeLayer(markerClusterGroup);
        }
        
        // Add individual markers
        markers.forEach(marker => {
            marker.addTo(map);
        });
    }
}

// Fit all markers in view
function fitAllMarkers() {
    if (markers.length > 0) {
        const group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

// Locate user
function locateUser() {
    map.locate({setView: true, maxZoom: 16});
}

// Handle location found
map.on('locationfound', function(e) {
    const radius = e.accuracy;
    const location = e.latlng;
    
    L.marker(location).addTo(map)
        .bindPopup(`คุณอยู่ที่นี่<br>ความแม่นยำ: ${Math.round(radius)} เมตร`).openPopup();
    
    L.circle(location, radius).addTo(map);
});

// Handle location error
map.on('locationerror', function(e) {
    alert("ไม่สามารถหาตำแหน่งของคุณได้: " + e.message);
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
});
</script>

<?php include '../includes/footer.php'; ?> 