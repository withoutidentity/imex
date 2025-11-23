<?php
// Load config first
require_once '../config/config.php';

/**
 * Geocode address using OpenStreetMap Nominatim API
 */
function geocodeAddressNominatim($address, $retryCount = 0) {
    if (empty($address)) {
        return ['success' => false, 'error' => 'กรุณากรอกที่อยู่'];
    }
    
    // Clean and prepare address
    $address = trim($address);
    
    // Try different address formats
    $addressVariants = [
        $address, // Original
        preg_replace('/\s+/', ' ', $address), // Remove extra spaces
        str_replace(['  ', '   '], ' ', $address), // Replace multiple spaces
    ];
    
    foreach ($addressVariants as $variant) {
        if (empty($variant)) continue;
        
        $encodedAddress = urlencode($variant);
        $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&countrycodes=th&limit=1&addressdetails=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: th,en-US;q=0.9,en;q=0.8'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Check for cURL errors
        if ($curlError) {
            if ($retryCount < 2) {
                // Retry after delay
                usleep(2000000); // 2 seconds
                return geocodeAddressNominatim($address, $retryCount + 1);
            }
            return ['success' => false, 'error' => 'cURL Error: ' . $curlError];
        }
        
        // Check HTTP status
        if ($httpCode === 429) {
            // Rate limited - wait longer and retry
            if ($retryCount < 3) {
                sleep(5); // Wait 5 seconds for rate limit
                return geocodeAddressNominatim($address, $retryCount + 1);
            }
            return ['success' => false, 'error' => 'Rate limited by Nominatim API'];
        }
        
        if ($httpCode !== 200) {
            if ($retryCount < 2) {
                usleep(2000000); // 2 seconds
                return geocodeAddressNominatim($address, $retryCount + 1);
            }
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }
        
        // Parse JSON response
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($retryCount < 2) {
                usleep(2000000);
                return geocodeAddressNominatim($address, $retryCount + 1);
            }
            return ['success' => false, 'error' => 'JSON parse error: ' . json_last_error_msg()];
        }
        
        if (!empty($data) && isset($data[0])) {
            $result = $data[0];
            
            // Validate coordinates
            $lat = (float) ($result['lat'] ?? 0);
            $lng = (float) ($result['lon'] ?? 0);
            
            if ($lat != 0 && $lng != 0 && abs($lat) <= 90 && abs($lng) <= 180) {
                return [
                    'success' => true,
                    'lat' => $lat,
                    'lng' => $lng,
                    'formatted_address' => $result['display_name'] ?? $address
                ];
            }
        }
    }
    
    // All variants failed
    return ['success' => false, 'error' => 'ไม่พบที่อยู่ที่ระบุ'];
}

/**
 * Geocode all pending addresses using Nominatim API
 */
function geocodeAllPending() {
    global $conn;
    
    try {
        // First, reset geocoding_status to 'pending' for addresses without coordinates
        // This ensures we can process them
        try {
            $resetStmt = $conn->prepare("
                UPDATE delivery_address 
                SET geocoding_status = 'pending'
                WHERE address IS NOT NULL 
                AND address != ''
                AND (
                    latitude IS NULL 
                    OR longitude IS NULL
                    OR latitude = 0
                    OR longitude = 0
                )
                AND geocoding_status != 'pending'
            ");
            $resetStmt->execute();
        } catch (Exception $e) {
            // Ignore reset errors, continue with query
        }
        
        // Get addresses that need geocoding
        // Priority: addresses with pending status, then addresses without coordinates
        $stmt = $conn->prepare("
            SELECT id, address, province, district 
            FROM delivery_address 
            WHERE address IS NOT NULL 
            AND address != ''
            AND (
                geocoding_status = 'pending' 
                OR geocoding_status IS NULL
                OR latitude IS NULL 
                OR longitude IS NULL
                OR latitude = 0
                OR longitude = 0
            )
            ORDER BY 
                CASE 
                    WHEN geocoding_status = 'pending' THEN 1
                    WHEN geocoding_status IS NULL THEN 2
                    ELSE 3
                END,
                id ASC
            LIMIT 500
        ");
        $stmt->execute();
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($addresses)) {
            // Try to get count of total addresses for better error message
            try {
                $countStmt = $conn->query("SELECT COUNT(*) as total FROM delivery_address WHERE address IS NOT NULL AND address != ''");
                $totalCount = $countStmt ? $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0 : 0;
                
                $countWithCoords = $conn->query("SELECT COUNT(*) as total FROM delivery_address WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0");
                $withCoords = $countWithCoords ? $countWithCoords->fetch(PDO::FETCH_ASSOC)['total'] ?? 0 : 0;
                
                $countPending = $conn->query("SELECT COUNT(*) as total FROM delivery_address WHERE geocoding_status = 'pending'");
                $pending = $countPending ? $countPending->fetch(PDO::FETCH_ASSOC)['total'] ?? 0 : 0;
                
                $errorMsg = 'ไม่พบที่อยู่ที่ต้องแปลงพิกัด';
                $errorMsg .= ' (ทั้งหมด: ' . $totalCount . ' รายการ';
                $errorMsg .= ', มีพิกัดแล้ว: ' . $withCoords . ' รายการ';
                $errorMsg .= ', สถานะ pending: ' . $pending . ' รายการ)';
                
                return [
                    'success' => false, 
                    'error' => $errorMsg
                ];
            } catch (Exception $e) {
                return [
                    'success' => false, 
                    'error' => 'ไม่พบที่อยู่ที่ต้องแปลงพิกัด (ไม่สามารถตรวจสอบจำนวนข้อมูลได้: ' . $e->getMessage() . ')'
                ];
            }
        }
        
        $successful = 0;
        $failed = 0;
        $total = count($addresses);
        
        foreach ($addresses as $index => $address) {
            // Build full address - try multiple formats
            $base_address = trim($address['address']);
            
            // Try different address combinations
            $addressFormats = [];
            
            // Format 1: Full address with all components
            $full1 = $base_address;
            if (!empty($address['district'])) {
                $full1 .= ' ' . trim($address['district']);
            }
            if (!empty($address['province'])) {
                $full1 .= ' ' . trim($address['province']);
            }
            $full1 .= ' ประเทศไทย';
            $addressFormats[] = $full1;
            
            // Format 2: Without country
            $full2 = $base_address;
            if (!empty($address['district'])) {
                $full2 .= ' ' . trim($address['district']);
            }
            if (!empty($address['province'])) {
                $full2 .= ' ' . trim($address['province']);
            }
            $addressFormats[] = $full2;
            
            // Format 3: Just base address
            $addressFormats[] = $base_address;
            
            // Try each format until one succeeds
            $geocodeResult = null;
            foreach ($addressFormats as $format) {
                if (empty(trim($format))) continue;
                
                $result = geocodeAddressNominatim($format);
                if ($result['success']) {
                    $geocodeResult = $result;
                    break;
                }
                
                // Small delay between format attempts
                usleep(500000); // 0.5 seconds
            }
            
            if ($geocodeResult && $geocodeResult['success']) {
                // Update database - use latitude/longitude (standard column names)
                try {
                    $updateStmt = $conn->prepare("
                        UPDATE delivery_address 
                        SET latitude = ?, 
                            longitude = ?, 
                            geocoded_at = NOW(), 
                            geocoding_status = 'success'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$geocodeResult['lat'], $geocodeResult['lng'], $address['id']]);
                    $successful++;
                } catch (Exception $e) {
                    // If update fails, mark as failed
                    try {
                        $updateStmt = $conn->prepare("
                            UPDATE delivery_address 
                            SET geocoding_status = 'failed', 
                                geocoded_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$address['id']]);
                        $failed++;
                    } catch (Exception $e2) {
                        // Ignore
                    }
                }
            } else {
                // Mark as failed with error message
                try {
                    $errorMsg = $geocodeResult['error'] ?? 'ไม่พบที่อยู่';
                    // Store error in a comment or log it
                    $updateStmt = $conn->prepare("
                        UPDATE delivery_address 
                        SET geocoding_status = 'failed', 
                            geocoded_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$address['id']]);
                    $failed++;
                } catch (Exception $e) {
                    // Ignore update errors for failed status
                }
            }
            
            // Rate limiting: 1 request per second for Nominatim (strict requirement)
            // Nominatim requires max 1 request per second
            usleep(1100000); // 1.1 seconds to be safe
            
            // Show progress every 10 items
            if (($index + 1) % 10 == 0 && ob_get_level() > 0) {
                ob_flush();
                flush();
            }
        }
        
        return [
            'success' => true,
            'successful' => $successful,
            'failed' => $failed,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Handle AJAX request FIRST - before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'geocode_all_ajax') {
    // Disable error display for clean JSON output
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Start output buffering to catch any unexpected output
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Increase timeout for long-running script
    set_time_limit(0);
    ini_set('max_execution_time', 0);
    
    try {
        $result = geocodeAllPending();
        
        // Clear any output that might have been generated
        ob_clean();
        
        // Remove any BOM or whitespace
        // Send JSON response with proper headers
        header('Content-Type: application/json; charset=utf-8', true);
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new Exception('JSON encoding failed: ' . json_last_error_msg());
        }
        
        echo $json;
        
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8', true);
        header('Cache-Control: no-cache, must-revalidate', true);
        
        $errorJson = json_encode([
            'success' => false,
            'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        echo $errorJson;
    }
    
    ob_end_flush();
    exit;
}

// Normal page load - continue with includes
$page_title = 'แปลงพิกัดที่อยู่';
include '../includes/header.php';

// Handle manual geocoding
$geocoding_result = '';
$geocoding_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['geocode_single'])) {
        // Single address geocoding
        $address = trim($_POST['address']);
        if (!empty($address)) {
            $result = geocodeAddressNominatim($address);
            if ($result['success']) {
                $geocoding_result = "แปลงพิกัดสำเร็จ: " . $result['formatted_address'] . 
                                  " (" . $result['lat'] . ", " . $result['lng'] . ")";
            } else {
                $geocoding_error = "ไม่สามารถแปลงพิกัดได้: " . $result['error'];
            }
        }
    }
}


// Get geocoding statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT geocoding_status, COUNT(*) as count FROM delivery_address GROUP BY geocoding_status");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $stats[$row['geocoding_status']] = $row['count'];
    }
} catch (Exception $e) {
    // Error handled silently
}

// Get recent geocoded addresses
$recent_geocoded = [];
try {
    $stmt = $conn->prepare("SELECT * FROM delivery_address WHERE geocoding_status = 'success' ORDER BY geocoded_at DESC LIMIT 10");
    $stmt->execute();
    $recent_geocoded = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Error handled silently
}
?>

<div class="fadeIn">
    <!-- Page Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">แปลงพิกัดที่อยู่</h1>
                <p class="text-gray-600 mt-2">แปลงที่อยู่เป็นพิกัด Latitude/Longitude โดยใช้ OpenStreetMap Nominatim API</p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-map-marker-alt text-4xl text-blue-600"></i>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if (!empty($geocoding_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($geocoding_result); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($geocoding_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($geocoding_error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">รอแปลงพิกัด</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['pending'] ?? 0); ?></p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <i class="fas fa-clock text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">แปลงสำเร็จ</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['success'] ?? 0); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">ล้มเหลว</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['failed'] ?? 0); ?></p>
                </div>
                <div class="bg-red-100 p-3 rounded-full">
                    <i class="fas fa-times text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Single Address Geocoding -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">แปลงพิกัดรายการเดียว</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">ที่อยู่</label>
                    <textarea name="address" id="address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="กรอกที่อยู่ที่ต้องการแปลงพิกัด..." required></textarea>
                </div>
                
                <button type="submit" name="geocode_single" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search-location mr-2"></i>แปลงพิกัด
                </button>
            </form>
            
            <!-- Map for single address -->
            <div id="single-map" class="map-container mt-4 hidden"></div>
        </div>

        <!-- Batch Geocoding -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-800 mb-4">แปลงพิกัดทั้งหมด</h2>
            
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2">ขั้นตอนการแปลงพิกัด</h3>
                    <ol class="text-sm text-gray-600 space-y-1">
                        <li>1. ระบบจะเลือกที่อยู่ที่มี geocoding_status = 'pending'</li>
                        <li>2. เรียกใช้ OpenStreetMap Nominatim API เพื่อแปลงพิกัด</li>
                        <li>3. บันทึก lat, lng, geocoded_at และ geocoding_status</li>
                        <li>4. ใช้เวลา 1 วินาทีต่อรายการเพื่อหลีกเลี่ยง rate limiting</li>
                    </ol>
                </div>
                
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>ข้อดี
                    </h4>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• ฟรี - ใช้ OpenStreetMap Nominatim API</li>
                        <li>• ไม่มีค่าใช้จ่าย</li>
                        <li>• ไม่ต้องขอ API Key</li>
                    </ul>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>ข้อควรระวัง
                    </h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• ประมวลผลทั้งหมดที่มีสถานะ 'pending'</li>
                        <li>• ใช้เวลา 1 วินาทีต่อรายการ (เพื่อหลีกเลี่ยง rate limiting)</li>
                        <li>• อาจใช้เวลานานถ้ามีข้อมูลจำนวนมาก</li>
                        <li>• บางที่อยู่อาจไม่พบพิกัด (จะถูกทำเครื่องหมายว่า failed)</li>
                    </ul>
                </div>
                
                <button type="button" id="geocodeAllBtn" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>เริ่มแปลงพิกัดทั้งหมด
                </button>
                
                <div id="geocodingProgress" class="hidden mt-4">
                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-blue-800" id="progressText">กำลังแปลงพิกัด... กรุณารอสักครู่</span>
                        </div>
                    </div>
                </div>
                
                <div id="geocodingResult" class="hidden mt-4"></div>
            </div>
        </div>
    </div>

    <!-- Recent Geocoded Addresses -->
    <div class="bg-white p-6 rounded-lg shadow-md mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">ที่อยู่ที่แปลงพิกัดล่าสุด</h2>
        
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>AWB</th>
                        <th>ชื่อผู้รับ</th>
                        <th>ที่อยู่</th>
                        <th>พิกัด</th>
                        <th>วันที่แปลง</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_geocoded)): ?>
                        <?php foreach ($recent_geocoded as $address): ?>
                            <tr>
                                <td class="font-medium"><?php echo htmlspecialchars($address['awb_number']); ?></td>
                                <td><?php echo htmlspecialchars($address['recipient_name']); ?></td>
                                <td class="max-w-xs truncate"><?php echo htmlspecialchars($address['address']); ?></td>
                                <td class="text-sm">
                                    <?php 
                                    $lat = isset($address['lat']) ? $address['lat'] : (isset($address['latitude']) ? $address['latitude'] : null);
                                    $lng = isset($address['lng']) ? $address['lng'] : (isset($address['longitude']) ? $address['longitude'] : null);
                                    if ($lat && $lng): ?>
                                        <div><?php echo number_format($lat, 6); ?></div>
                                        <div><?php echo number_format($lng, 6); ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($address['geocoded_at']); ?></td>
                                <td>
                                    <?php 
                                    $lat = isset($address['lat']) ? $address['lat'] : (isset($address['latitude']) ? $address['latitude'] : null);
                                    $lng = isset($address['lng']) ? $address['lng'] : (isset($address['longitude']) ? $address['longitude'] : null);
                                    if ($lat && $lng): ?>
                                        <button onclick="showOnMap(<?php echo $lat; ?>, <?php echo $lng; ?>, '<?php echo htmlspecialchars($address['address']); ?>')" 
                                                class="text-blue-600 hover:text-blue-800 mr-2">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <button onclick="copyCoordinates(<?php echo $lat; ?>, <?php echo $lng; ?>)" 
                                                class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
                                <p>ยังไม่มีที่อยู่ที่แปลงพิกัดแล้ว</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Map for showing locations -->
    <div id="location-map" class="map-container mt-6 hidden"></div>
</div>

<script>
let singleMap, locationMap;
let markers = [];

function initMaps() {
    // Initialize single geocoding map
    if (document.getElementById('single-map')) {
        singleMap = new google.maps.Map(document.getElementById('single-map'), {
            zoom: 12,
            center: { lat: <?php echo DEFAULT_MAP_CENTER_LAT; ?>, lng: <?php echo DEFAULT_MAP_CENTER_LNG; ?> }
        });
    }
    
    // Initialize location display map
    if (document.getElementById('location-map')) {
        locationMap = new google.maps.Map(document.getElementById('location-map'), {
            zoom: 12,
            center: { lat: <?php echo DEFAULT_MAP_CENTER_LAT; ?>, lng: <?php echo DEFAULT_MAP_CENTER_LNG; ?> }
        });
    }
}

function showOnMap(lat, lng, address) {
    const mapContainer = document.getElementById('location-map');
    mapContainer.classList.remove('hidden');
    
    // Clear existing markers
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    
    const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
    
    // Center map on location
    locationMap.setCenter(position);
    locationMap.setZoom(15);
    
    // Add marker
    const marker = new google.maps.Marker({
        position: position,
        map: locationMap,
        title: address,
        animation: google.maps.Animation.DROP
    });
    
    // Add info window
    const infoWindow = new google.maps.InfoWindow({
        content: `
            <div class="p-2">
                <h6 class="font-semibold">${address}</h6>
                <p class="text-sm text-gray-600">
                    Lat: ${lat}<br>
                    Lng: ${lng}
                </p>
            </div>
        `
    });
    
    marker.addListener('click', function() {
        infoWindow.open(locationMap, marker);
    });
    
    markers.push(marker);
    
    // Scroll to map
    mapContainer.scrollIntoView({ behavior: 'smooth' });
}

function copyCoordinates(lat, lng) {
    const text = `${lat}, ${lng}`;
    navigator.clipboard.writeText(text).then(function() {
        showAlert('คัดลอกพิกัดเรียบร้อย', 'success');
    }, function(err) {
        showAlert('ไม่สามารถคัดลอกพิกัดได้', 'error');
    });
}

// Initialize maps when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof google !== 'undefined') {
        initMaps();
    }
    
    // Handle geocoding button click
    const geocodeAllBtn = document.getElementById('geocodeAllBtn');
    const geocodingProgress = document.getElementById('geocodingProgress');
    const progressText = document.getElementById('progressText');
    const geocodingResult = document.getElementById('geocodingResult');
    
    if (geocodeAllBtn) {
        geocodeAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Confirm before proceeding
            if (!confirm('คุณต้องการแปลงพิกัดทั้งหมดหรือไม่? กระบวนการนี้อาจใช้เวลานาน')) {
                return;
            }
            
            // Disable button and show progress
            geocodeAllBtn.disabled = true;
            geocodeAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังประมวลผล...';
            geocodingProgress.classList.remove('hidden');
            geocodingResult.classList.add('hidden');
            progressText.textContent = 'กำลังแปลงพิกัด... กรุณารอสักครู่';
            
            // Make AJAX request
            const formData = new FormData();
            formData.append('action', 'geocode_all_ajax');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is OK
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                
                // Try to parse as JSON regardless of content-type
                // Sometimes server doesn't set content-type correctly
                return response.text().then(text => {
                    // Trim whitespace and BOM
                    text = text.trim();
                    if (text.charCodeAt(0) === 0xFEFF) {
                        text = text.slice(1);
                    }
                    
                    // Try to parse as JSON
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text.substring(0, 500));
                        throw new Error('ไม่สามารถแปลงข้อมูลเป็น JSON ได้: ' + e.message);
                    }
                });
            })
            .then(data => {
                // Hide progress
                geocodingProgress.classList.add('hidden');
                
                // Show result
                if (data.success) {
                    let message = '';
                    let bgColor = 'bg-green-100';
                    let borderColor = 'border-green-400';
                    let textColor = 'text-green-700';
                    let icon = 'fa-check-circle';
                    
                    if (data.successful > 0) {
                        message = 'แปลงพิกัดสำเร็จ: ' + data.successful + ' รายการ';
                        if (data.failed > 0) {
                            message += ', ล้มเหลว: ' + data.failed + ' รายการ';
                            bgColor = 'bg-yellow-100';
                            borderColor = 'border-yellow-400';
                            textColor = 'text-yellow-700';
                            icon = 'fa-exclamation-triangle';
                        }
                    } else if (data.failed > 0) {
                        message = 'ล้มเหลวทั้งหมด: ' + data.failed + ' รายการ';
                        bgColor = 'bg-red-100';
                        borderColor = 'border-red-400';
                        textColor = 'text-red-700';
                        icon = 'fa-times-circle';
                    }
                    
                    if (data.total) {
                        message += ' (ทั้งหมด: ' + data.total + ' รายการ)';
                    }
                    
                    geocodingResult.innerHTML = '<div class="' + bgColor + ' border ' + borderColor + ' ' + textColor + ' px-4 py-3 rounded"><div class="flex"><i class="fas ' + icon + ' mr-2"></i><span>' + message + '</span></div></div>';
                    geocodingResult.classList.remove('hidden');
                    
                    // Reload page after 2 seconds to show updated data (only if some succeeded)
                    if (data.successful > 0) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        // Re-enable button if all failed
                        geocodeAllBtn.disabled = false;
                        geocodeAllBtn.innerHTML = '<i class="fas fa-play mr-2"></i>เริ่มแปลงพิกัดทั้งหมด';
                    }
                } else {
                    geocodingResult.innerHTML = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"><div class="flex"><i class="fas fa-exclamation-circle mr-2"></i><span>เกิดข้อผิดพลาด: ' + (data.error || 'ไม่ทราบสาเหตุ') + '</span></div></div>';
                    geocodingResult.classList.remove('hidden');
                    
                    // Re-enable button
                    geocodeAllBtn.disabled = false;
                    geocodeAllBtn.innerHTML = '<i class="fas fa-play mr-2"></i>เริ่มแปลงพิกัดทั้งหมด';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                geocodingProgress.classList.add('hidden');
                geocodingResult.innerHTML = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"><div class="flex"><i class="fas fa-exclamation-circle mr-2"></i><span>เกิดข้อผิดพลาด: ' + error.message + '</span></div></div>';
                geocodingResult.classList.remove('hidden');
                
                // Re-enable button
                geocodeAllBtn.disabled = false;
                geocodeAllBtn.innerHTML = '<i class="fas fa-play mr-2"></i>เริ่มแปลงพิกัดทั้งหมด';
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?> 