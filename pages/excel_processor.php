<?php
$page_title = 'ประมวลผล Excel และจัดกลุ่ม Zone';
require_once '../config/config.php';
include '../includes/header.php';

// Initialize variables
$processing_result = '';
$processing_error = '';
$excel_data = [];
$zone_data = [];
$sql_statements = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $result = processExcelFile($_FILES['excel_file']);
        if ($result['success']) {
            $excel_data = $result['data'];
            $zone_data = $result['zones'];
            $sql_statements = $result['sql'];
            $processing_result = "ประมวลผลสำเร็จ: " . count($excel_data) . " รายการ, " . count($zone_data) . " โซน";
        } else {
            $processing_error = $result['error'];
        }
    }
}

function processExcelFile($file) {
    try {
        $file_path = $file['tmp_name'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Read Excel/CSV file
        if ($file_extension === 'csv') {
            $data = readCSVAdvanced($file_path);
        } else {
            $data = readExcelSimple($file_path); // Simplified Excel reader
        }
        
        if (empty($data)) {
            return ['success' => false, 'error' => 'ไม่พบข้อมูลในไฟล์'];
        }
        
        // Process geocoding
        $geocoded_data = processGeocodingBatch($data);
        
        // Create zones based on coordinates
        $zones = createZonesFromCoordinates($geocoded_data);
        
        // Assign zone_id to each record
        $final_data = assignZonesToRecords($geocoded_data, $zones);
        
        // Generate SQL statements
        $sql = generateSQLStatements($final_data, $zones);
        
        return [
            'success' => true,
            'data' => $final_data,
            'zones' => $zones,
            'sql' => $sql
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function readCSVAdvanced($file_path) {
    $data = [];
    $headers = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $row_index = 0;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($row_index === 0) {
                $headers = $row;
            } else {
                $record = [];
                foreach ($headers as $col_index => $header) {
                    $record[$header] = isset($row[$col_index]) ? trim($row[$col_index]) : '';
                }
                $data[] = $record;
            }
            $row_index++;
        }
        fclose($handle);
    }
    
    return $data;
}

function readExcelSimple($file_path) {
    // For demonstration purposes - in production use PHPSpreadsheet
    $data = [
        [
            'AWB' => 'TRK001',
            'ชื่อผู้รับ' => 'สมชาย ใจดี',
            'เบอร์โทร' => '081-234-5678',
            'ที่อยู่ผู้รับ' => '123 ถนนสุขุมวิท แขวงคลองตัน เขตคลองเตย กรุงเทพมหานคร',
            'จังหวัด' => 'กรุงเทพมหานคร',
            'อำเภอ' => 'คลองเตย'
        ],
        [
            'AWB' => 'TRK002',
            'ชื่อผู้รับ' => 'สมหญิง รักดี',
            'เบอร์โทร' => '082-345-6789',
            'ที่อยู่ผู้รับ' => '456 ถนนพหลโยธิน แขวงสามเสนใน เขตพญาไท กรุงเทพมหานคร',
            'จังหวัด' => 'กรุงเทพมหานคร',
            'อำเภอ' => 'พญาไท'
        ],
        [
            'AWB' => 'TRK003',
            'ชื่อผู้รับ' => 'สมศักดิ์ ขยันดี',
            'เบอร์โทร' => '083-456-7890',
            'ที่อยู่ผู้รับ' => '789 ถนนราชดำเนิน แขวงบวรนิเวศ เขตพระนคร กรุงเทพมหานคร',
            'จังหวัด' => 'กรุงเทพมหานคร',
            'อำเภอ' => 'พระนคร'
        ],
        [
            'AWB' => 'TRK004',
            'ชื่อผู้รับ' => 'สมปอง แจ่มใส',
            'เบอร์โทร' => '084-567-8901',
            'ที่อยู่ผู้รับ' => '321 ถนนรัชดาภิเษก แขวงห้วยขวาง เขตห้วยขวาง กรุงเทพมหานคร',
            'จังหวัด' => 'กรุงเทพมหานคร',
            'อำเภอ' => 'ห้วยขวาง'
        ],
        [
            'AWB' => 'TRK005',
            'ชื่อผู้รับ' => 'สมใจ น่ารัก',
            'เบอร์โทร' => '085-678-9012',
            'ที่อยู่ผู้รับ' => '654 ถนนบางนา แขวงบางนา เขตบางนา กรุงเทพมหานคร',
            'จังหวัด' => 'กรุงเทพมหานคร',
            'อำเภอ' => 'บางนา'
        ]
    ];
    
    return $data;
}

function processGeocodingBatch($data) {
    $geocoded_data = [];
    
    foreach ($data as $index => $row) {
        $address = $row['ที่อยู่ผู้รับ'] ?? $row['address'] ?? '';
        
        if (!empty($address)) {
            $coords = mockGeocode($address, $index); // Mock geocoding for demo
            
            $row['latitude'] = $coords['lat'];
            $row['longitude'] = $coords['lng'];
            $row['geocoding_status'] = 'success';
        } else {
            $row['latitude'] = null;
            $row['longitude'] = null;
            $row['geocoding_status'] = 'failed';
        }
        
        $geocoded_data[] = $row;
    }
    
    return $geocoded_data;
}

function mockGeocode($address, $index) {
    // Mock coordinates for demo - in production use Google Maps API
    $mock_coords = [
        ['lat' => 8.4304, 'lng' => 99.9631], // นครศรีธรรมราช - ในเมือง
        ['lat' => 8.4254, 'lng' => 99.9681], // นครศรีธรรมราช - ปากนคร
        ['lat' => 8.4404, 'lng' => 99.9531], // นครศรีธรรมราช - คลัง
        ['lat' => 8.4154, 'lng' => 99.9781], // นครศรีธรรมราช - ท่าวัง
        ['lat' => 8.4504, 'lng' => 99.9831], // นครศรีธรรมราช - โพธิ์เสด็จ
    ];
    
    return $mock_coords[$index % count($mock_coords)];
}

function createZonesFromCoordinates($data) {
    $zones = [];
    $valid_coords = array_filter($data, function($row) {
        return !empty($row['latitude']) && !empty($row['longitude']);
    });
    
    if (empty($valid_coords)) {
        return $zones;
    }
    
    // Simple zone creation based on coordinate clusters
    $zone_centers = [
        ['lat' => 8.4304, 'lng' => 99.9631, 'name' => 'นครศรีธรรมราช เขตกลาง', 'color' => '#3B82F6'],
        ['lat' => 8.4454, 'lng' => 99.9781, 'name' => 'นครศรีธรรมราช เขตเหนือ', 'color' => '#10B981'],
        ['lat' => 8.4154, 'lng' => 99.9481, 'name' => 'นครศรีธรรมราช เขตตะวันตก', 'color' => '#F59E0B'],
        ['lat' => 8.4504, 'lng' => 99.9831, 'name' => 'นครศรีธรรมราช เขตตะวันออก', 'color' => '#EF4444'],
        ['lat' => 8.4104, 'lng' => 99.9881, 'name' => 'นครศรีธรรมราช เขตใต้', 'color' => '#8B5CF6'],
    ];
    
    foreach ($zone_centers as $index => $center) {
        $zone_id = $index + 1;
        $zones[] = [
            'zone_id' => $zone_id,
            'zone_code' => 'NST' . sprintf('%02d', $zone_id),
            'zone_name' => $center['name'],
            'center_lat' => $center['lat'],
            'center_lng' => $center['lng'],
            'color' => $center['color'],
            'radius' => 5.0, // km
            'min_lat' => $center['lat'] - 0.045,
            'max_lat' => $center['lat'] + 0.045,
            'min_lng' => $center['lng'] - 0.045,
            'max_lng' => $center['lng'] + 0.045
        ];
    }
    
    return $zones;
}

function assignZonesToRecords($data, $zones) {
    foreach ($data as &$row) {
        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $closest_zone = findClosestZone($row['latitude'], $row['longitude'], $zones);
            $row['zone_id'] = $closest_zone['zone_id'];
            $row['zone_name'] = $closest_zone['zone_name'];
            $row['zone_color'] = $closest_zone['color'];
        } else {
            $row['zone_id'] = null;
            $row['zone_name'] = 'ไม่ระบุ';
            $row['zone_color'] = '#6B7280';
        }
    }
    
    return $data;
}

function findClosestZone($lat, $lng, $zones) {
    $closest_zone = null;
    $min_distance = PHP_FLOAT_MAX;
    
    foreach ($zones as $zone) {
        $distance = calculateDistance($lat, $lng, $zone['center_lat'], $zone['center_lng']);
        if ($distance < $min_distance) {
            $min_distance = $distance;
            $closest_zone = $zone;
        }
    }
    
    return $closest_zone ?? $zones[0];
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

function generateSQLStatements($data, $zones) {
    $sql = [];
    
    // Zone creation SQL
    $sql['zones'] = "-- Insert Zone Data\n";
    foreach ($zones as $zone) {
        $sql['zones'] .= sprintf(
            "INSERT INTO zone_area (zone_code, zone_name, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code) VALUES ('%s', '%s', %.8f, %.8f, %.8f, %.8f, %.8f, %.8f, '%s');\n",
            $zone['zone_code'],
            addslashes($zone['zone_name']),
            $zone['min_lat'],
            $zone['max_lat'],
            $zone['min_lng'],
            $zone['max_lng'],
            $zone['center_lat'],
            $zone['center_lng'],
            $zone['color']
        );
    }
    
    // Delivery address SQL
    $sql['addresses'] = "\n-- Insert Delivery Address Data\n";
    foreach ($data as $row) {
        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $sql['addresses'] .= sprintf(
                "INSERT INTO delivery_address (awb_number, recipient_name, recipient_phone, address, province, district, latitude, longitude, zone_id, geocoding_status) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %.8f, %.8f, %d, 'success');\n",
                addslashes($row['AWB'] ?? ''),
                addslashes($row['ชื่อผู้รับ'] ?? ''),
                addslashes($row['เบอร์โทร'] ?? ''),
                addslashes($row['ที่อยู่ผู้รับ'] ?? ''),
                addslashes($row['จังหวัด'] ?? ''),
                addslashes($row['อำเภอ'] ?? ''),
                $row['latitude'],
                $row['longitude'],
                $row['zone_id']
            );
        }
    }
    
    return $sql;
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">ประมวลผล Excel และจัดกลุ่ม Zone</h1>
                <p class="text-gray-600 mt-2">อัพโหลดไฟล์ Excel เพื่อแปลงพิกัด จัดกลุ่มโซน และสร้าง SQL อัตโนมัติ</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-cogs text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if (!empty($processing_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($processing_result); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($processing_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($processing_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($excel_data)): ?>
        <!-- Upload Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">อัพโหลดไฟล์ Excel</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <i class="fas fa-file-excel text-4xl text-green-500 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">เลือกไฟล์ Excel หรือ CSV</h3>
                    <p class="text-gray-500 mb-4">รองรับไฟล์ .xlsx, .xls, .csv</p>
                    
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold mb-2">คอลัมน์ที่ต้องมีในไฟล์:</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• <strong>AWB</strong> - หมายเลขพัสดุ</li>
                        <li>• <strong>ชื่อผู้รับ</strong> - ชื่อผู้รับพัสดุ</li>
                        <li>• <strong>เบอร์โทร</strong> - เบอร์โทรศัพท์ผู้รับ</li>
                        <li>• <strong>ที่อยู่ผู้รับ</strong> - ที่อยู่สำหรับจัดส่ง</li>
                        <li>• <strong>จังหวัด</strong> - จังหวัด</li>
                        <li>• <strong>อำเภอ</strong> - อำเภอ/เขต</li>
                    </ul>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>เริ่มประมวลผล
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- Processing Results -->
        <div class="space-y-6">
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">จำนวนรายการ</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo count($excel_data); ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">จำนวนโซน</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo count($zone_data); ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-map-marked-alt text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">แปลงพิกัดสำเร็จ</p>
                            <p class="text-2xl font-bold text-purple-600">
                                <?php echo count(array_filter($excel_data, function($row) { return !empty($row['latitude']); })); ?>
                            </p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-map-pin text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Display -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">แผนที่แสดงตำแหน่งพัสดุแบ่งตาม Zone</h2>
                <div id="zone-map" class="map-container"></div>
                
                <!-- Zone Legend -->
                <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-3">
                    <?php foreach ($zone_data as $zone): ?>
                        <div class="flex items-center space-x-2 text-sm">
                            <div class="w-4 h-4 rounded-full" style="background-color: <?php echo $zone['color']; ?>"></div>
                            <span><?php echo htmlspecialchars($zone['zone_name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Zone Details and Route Optimization -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($zone_data as $zone): ?>
                    <?php
                    $zone_deliveries = array_filter($excel_data, function($row) use ($zone) {
                        return $row['zone_id'] == $zone['zone_id'];
                    });
                    ?>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800">
                                <span class="w-4 h-4 rounded-full inline-block mr-2" style="background-color: <?php echo $zone['color']; ?>"></span>
                                <?php echo htmlspecialchars($zone['zone_name']); ?>
                            </h3>
                            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                                <?php echo count($zone_deliveries); ?> รายการ
                            </span>
                        </div>
                        
                        <div class="space-y-3 mb-4">
                            <?php foreach (array_slice($zone_deliveries, 0, 3) as $delivery): ?>
                                <div class="text-sm border-l-4 pl-3" style="border-color: <?php echo $zone['color']; ?>">
                                    <div class="font-medium"><?php echo htmlspecialchars($delivery['AWB'] ?? ''); ?></div>
                                    <div class="text-gray-600"><?php echo htmlspecialchars($delivery['ชื่อผู้รับ'] ?? ''); ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($zone_deliveries) > 3): ?>
                                <div class="text-sm text-gray-500">และอีก <?php echo count($zone_deliveries) - 3; ?> รายการ...</div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($zone_deliveries)): ?>
                            <a href="<?php echo generateGoogleMapsRouteURL($zone_deliveries); ?>" target="_blank" 
                               class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-route mr-2"></i>ดูเส้นทางใน Google Maps
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- SQL Statements -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold text-gray-800 mb-4">SQL Statements สำหรับ Insert ข้อมูล</h2>
                
                <!-- Zone SQL -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">SQL สำหรับสร้าง Zone</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap overflow-x-auto"><?php echo htmlspecialchars($sql_statements['zones']); ?></pre>
                    </div>
                    <button onclick="copyToClipboard('zones')" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-copy mr-2"></i>คัดลอก SQL
                    </button>
                </div>

                <!-- Address SQL -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">SQL สำหรับ Insert ข้อมูลพัสดุ</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap overflow-x-auto max-h-64"><?php echo htmlspecialchars($sql_statements['addresses']); ?></pre>
                    </div>
                    <button onclick="copyToClipboard('addresses')" class="mt-2 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
                        <i class="fas fa-copy mr-2"></i>คัดลอก SQL
                    </button>
                </div>
            </div>

            <!-- Process Again Button -->
            <div class="text-center">
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-redo mr-2"></i>ประมวลผลไฟล์ใหม่
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
let zoneMap;
let markers = [];
const zoneData = <?php echo json_encode($zone_data); ?>;
const deliveryData = <?php echo json_encode($excel_data); ?>;

function initializeZoneMap() {
    if (deliveryData.length === 0) return;
    
    zoneMap = new google.maps.Map(document.getElementById('zone-map'), {
        zoom: 12,
        center: { lat: <?php echo DEFAULT_MAP_CENTER_LAT; ?>, lng: <?php echo DEFAULT_MAP_CENTER_LNG; ?> }
    });
    
    // Add markers for each delivery
    deliveryData.forEach(function(delivery) {
        if (delivery.latitude && delivery.longitude) {
            const marker = new google.maps.Marker({
                position: {
                    lat: parseFloat(delivery.latitude),
                    lng: parseFloat(delivery.longitude)
                },
                map: zoneMap,
                title: delivery['ชื่อผู้รับ'] || delivery.recipient_name,
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(createMarkerSVG(delivery.zone_color)),
                    scaledSize: new google.maps.Size(30, 30),
                    anchor: new google.maps.Point(15, 30)
                }
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div class="p-3">
                        <h6 class="font-semibold text-gray-800">${delivery['AWB'] || delivery.awb_number}</h6>
                        <p class="text-sm text-gray-600">${delivery['ชื่อผู้รับ'] || delivery.recipient_name}</p>
                        <p class="text-xs text-gray-500">${delivery.zone_name}</p>
                        <p class="text-xs text-gray-400">${delivery['ที่อยู่ผู้รับ'] || delivery.address}</p>
                    </div>
                `
            });
            
            marker.addListener('click', function() {
                infoWindow.open(zoneMap, marker);
            });
            
            markers.push(marker);
        }
    });
    
    // Fit map to show all markers
    if (markers.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        markers.forEach(marker => bounds.extend(marker.getPosition()));
        zoneMap.fitBounds(bounds);
    }
}

function createMarkerSVG(color) {
    return `<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
        <circle cx="15" cy="15" r="12" fill="${color}" stroke="white" stroke-width="2"/>
        <circle cx="15" cy="15" r="6" fill="white"/>
    </svg>`;
}

function copyToClipboard(type) {
    const sql = <?php echo json_encode($sql_statements); ?>;
    const text = sql[type];
    
    navigator.clipboard.writeText(text).then(function() {
        showAlert('คัดลอก SQL เรียบร้อย', 'success');
    }, function(err) {
        showAlert('ไม่สามารถคัดลอกได้', 'error');
    });
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof google !== 'undefined' && deliveryData.length > 0) {
        initializeZoneMap();
    }
});
</script>

<?php 
function generateGoogleMapsRouteURL($deliveries) {
    if (empty($deliveries)) return '#';
    
    $waypoints = [];
    foreach ($deliveries as $delivery) {
        if (!empty($delivery['latitude']) && !empty($delivery['longitude'])) {
            $waypoints[] = $delivery['latitude'] . ',' . $delivery['longitude'];
        }
    }
    
    if (count($waypoints) < 2) return '#';
    
    $origin = array_shift($waypoints);
    $destination = array_pop($waypoints);
    
    $url = 'https://www.google.com/maps/dir/' . $origin;
    
    if (!empty($waypoints)) {
        $url .= '/' . implode('/', $waypoints);
    }
    
    $url .= '/' . $destination;
    
    return $url;
}

include '../includes/footer.php'; 
?> 