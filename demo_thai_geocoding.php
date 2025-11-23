<?php
$page_title = 'Demo ระบบ Thai Geocoding & Route Optimization';
require_once 'config/config.php';
require_once 'includes/address_parser.php';
require_once 'includes/nominatim_geocoder.php';
require_once 'includes/route_optimizer.php';
include 'includes/header.php';

// ตัวอย่างที่อยู่ภาษาไทย
$sample_addresses = [
    '123 ถนนสุขุมวิท ซอยอโศก แขวงคลองเตย เขตคลองเตย กรุงเทพมหานคร 10110',
    '456 ถนนพหลโยธิน แขวงสามเสนใน เขตพญาไท กรุงเทพมหานคร 10400',
    '789 ถนนราชดำเนิน แขวงบวรนิเวศ เขตพระนคร กรุงเทพมหานคร 10200',
    '321 ถนนรัชดาภิเษก แขวงห้วยขวาง เขตห้วยขวาง กรุงเทพมหานคร 10310',
    '654 ถนนบางนา แขวงบางนา เขตบางนา กรุงเทพมหานคร 10260'
];

$demo_results = [];
$route_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['demo_parsing'])) {
        // Demo address parsing
        $parser = new ThaiAddressParser();
        
        foreach ($sample_addresses as $index => $address) {
            $parsed = $parser->parseAddress($address);
            $validation = $parser->validateParsing($parsed);
            
            $demo_results[] = [
                'original' => $address,
                'parsed' => $parsed,
                'validation' => $validation
            ];
        }
    } elseif (isset($_POST['demo_geocoding'])) {
        // Demo geocoding
        $geocoder = new NominatimGeocoder();
        $geocoder->setRateLimit(500000); // 0.5 seconds for demo
        
        foreach ($sample_addresses as $address) {
            $result = $geocoder->geocode($address);
            $demo_results[] = [
                'address' => $address,
                'geocoding' => $result
            ];
        }
    } elseif (isset($_POST['demo_route_optimization'])) {
        // Demo route optimization
        $sample_deliveries = [
            ['id' => 1, 'latitude' => 13.7363, 'longitude' => 100.5619, 'recipient_name' => 'สมชาย ใจดี', 'address' => $sample_addresses[0], 'geocoding_confidence' => 85],
            ['id' => 2, 'latitude' => 13.7650, 'longitude' => 100.5350, 'recipient_name' => 'สมหญิง รักดี', 'address' => $sample_addresses[1], 'geocoding_confidence' => 82],
            ['id' => 3, 'latitude' => 13.7594, 'longitude' => 100.5014, 'recipient_name' => 'สมศักดิ์ ขยันดี', 'address' => $sample_addresses[2], 'geocoding_confidence' => 78],
            ['id' => 4, 'latitude' => 13.7844, 'longitude' => 100.5794, 'recipient_name' => 'สมปอง แจ่มใส', 'address' => $sample_addresses[3], 'geocoding_confidence' => 90],
            ['id' => 5, 'latitude' => 13.6500, 'longitude' => 100.6000, 'recipient_name' => 'สมใจ น่ารัก', 'address' => $sample_addresses[4], 'geocoding_confidence' => 75]
        ];
        
        $optimizer = new RouteOptimizer();
        $algorithm = $_POST['algorithm'] ?? 'nearest_neighbor';
        $route_result = $optimizer->optimizeRoute($sample_deliveries, $algorithm);
    }
}
?>

<div class="fadeIn">
    <!-- Header -->
    <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6 rounded-lg shadow-lg mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold mb-2">🇹🇭 Thai Geocoding & Route Optimization Demo</h1>
                <p class="text-purple-100">สาธิตระบบแยกที่อยู่ภาษาไทย, Geocoding, และคำนวณเส้นทางที่เหมาะสม</p>
            </div>
            <div class="hidden lg:block">
                <i class="fas fa-route text-5xl opacity-20"></i>
            </div>
        </div>
    </div>

    <!-- Quick Start Guide -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-rocket text-blue-600 mr-2"></i>Quick Start Guide
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-3xl mb-2">🏠</div>
                <h3 class="font-bold text-blue-800">1. แยกที่อยู่</h3>
                <p class="text-sm text-blue-600">แยกตำบล, ถนน, ซอย, หมู่</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-3xl mb-2">🌍</div>
                <h3 class="font-bold text-green-800">2. หาพิกัด</h3>
                <p class="text-sm text-green-600">ใช้ OpenStreetMap API</p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-3xl mb-2">🗺️</div>
                <h3 class="font-bold text-purple-800">3. แสดงแผนที่</h3>
                <p class="text-sm text-purple-600">Leaflet.js + Clustering</p>
            </div>
            <div class="text-center p-4 bg-orange-50 rounded-lg">
                <div class="text-3xl mb-2">🚛</div>
                <h3 class="font-bold text-orange-800">4. วางเส้นทาง</h3>
                <p class="text-sm text-orange-600">TSP Algorithm</p>
            </div>
        </div>
    </div>

    <!-- Demo Controls -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        
        <!-- Address Parsing Demo -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4 text-blue-600">
                <i class="fas fa-edit mr-2"></i>1. Address Parsing Demo
            </h2>
            
            <p class="text-sm text-gray-600 mb-4">
                แยกส่วนประกอบของที่อยู่ภาษาไทย เช่น เลขที่, ถนน, ซอย, ตำบล, อำเภอ, จังหวัด
            </p>
            
            <form method="POST">
                <button type="submit" name="demo_parsing" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>ทดสอบการแยกที่อยู่
                </button>
            </form>
        </div>

        <!-- Geocoding Demo -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4 text-green-600">
                <i class="fas fa-map-marker-alt mr-2"></i>2. Geocoding Demo
            </h2>
            
            <p class="text-sm text-gray-600 mb-4">
                หาพิกัด latitude/longitude จากที่อยู่โดยใช้ OpenStreetMap Nominatim API
            </p>
            
            <form method="POST">
                <button type="submit" name="demo_geocoding" 
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>ทดสอบ Geocoding
                </button>
            </form>
        </div>

        <!-- Route Optimization Demo -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4 text-purple-600">
                <i class="fas fa-route mr-2"></i>3. Route Optimization Demo
            </h2>
            
            <p class="text-sm text-gray-600 mb-4">
                คำนวณเส้นทางการจัดส่งที่เหมาะสมด้วย TSP Algorithm
            </p>
            
            <form method="POST" class="space-y-3">
                <select name="algorithm" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="nearest_neighbor">Nearest Neighbor</option>
                    <option value="two_opt">2-Opt Improvement</option>
                    <option value="simulated_annealing">Simulated Annealing</option>
                    <option value="genetic">Genetic Algorithm</option>
                </select>
                
                <button type="submit" name="demo_route_optimization" 
                        class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>ทดสอบ Route Optimization
                </button>
            </form>
        </div>
    </div>

    <!-- Results Display -->
    <?php if (!empty($demo_results)): ?>
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-chart-line text-indigo-600 mr-2"></i>ผลลัพธ์การทดสอบ
        </h2>
        
        <?php if (isset($_POST['demo_parsing'])): ?>
        <!-- Address Parsing Results -->
        <div class="space-y-4">
            <?php foreach ($demo_results as $index => $result): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="mb-3">
                    <h3 class="font-bold text-gray-800">ที่อยู่ที่ <?php echo $index + 1; ?></h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($result['original']); ?></p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold text-blue-600 mb-2">ส่วนประกอบที่แยกได้:</h4>
                        <div class="space-y-1 text-sm">
                            <?php if ($result['parsed']['house_number']): ?>
                            <div><strong>เลขที่:</strong> <?php echo htmlspecialchars($result['parsed']['house_number']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['building']): ?>
                            <div><strong>อาคาร:</strong> <?php echo htmlspecialchars($result['parsed']['building']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['soi']): ?>
                            <div><strong>ซอย:</strong> <?php echo htmlspecialchars($result['parsed']['soi']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['road']): ?>
                            <div><strong>ถนน:</strong> <?php echo htmlspecialchars($result['parsed']['road']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['subdistrict']): ?>
                            <div><strong>ตำบล/แขวง:</strong> <?php echo htmlspecialchars($result['parsed']['subdistrict']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['district']): ?>
                            <div><strong>อำเภอ/เขต:</strong> <?php echo htmlspecialchars($result['parsed']['district']); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($result['parsed']['province']): ?>
                            <div><strong>จังหวัด:</strong> <?php echo htmlspecialchars($result['parsed']['province']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-green-600 mb-2">คุณภาพการแยก:</h4>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <span class="text-sm mr-2">คะแนน:</span>
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full <?php echo $result['validation']['quality'] === 'excellent' ? 'bg-green-500' : ($result['validation']['quality'] === 'good' ? 'bg-blue-500' : ($result['validation']['quality'] === 'fair' ? 'bg-yellow-500' : 'bg-red-500')); ?>" 
                                         style="width: <?php echo $result['validation']['percentage']; ?>%"></div>
                                </div>
                                <span class="text-sm ml-2"><?php echo round($result['validation']['percentage']); ?>%</span>
                            </div>
                            <div class="text-sm">
                                <span class="px-2 py-1 rounded text-white text-xs
                                    <?php echo $result['validation']['quality'] === 'excellent' ? 'bg-green-500' : ($result['validation']['quality'] === 'good' ? 'bg-blue-500' : ($result['validation']['quality'] === 'fair' ? 'bg-yellow-500' : 'bg-red-500')); ?>">
                                    <?php echo ucfirst($result['validation']['quality']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php elseif (isset($_POST['demo_geocoding'])): ?>
        <!-- Geocoding Results -->
        <div class="space-y-4">
            <?php foreach ($demo_results as $index => $result): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="mb-3">
                    <h3 class="font-bold text-gray-800">ที่อยู่ที่ <?php echo $index + 1; ?></h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($result['address']); ?></p>
                </div>
                
                <?php if ($result['geocoding']['success']): ?>
                <div class="bg-green-50 p-3 rounded">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-semibold text-green-600 mb-2">พิกัดที่พบ:</h4>
                            <div class="space-y-1 text-sm">
                                <div><strong>Latitude:</strong> <?php echo $result['geocoding']['lat']; ?></div>
                                <div><strong>Longitude:</strong> <?php echo $result['geocoding']['lng']; ?></div>
                                <div><strong>ที่อยู่ที่จัดรูปแบบ:</strong> <?php echo htmlspecialchars($result['geocoding']['formatted_address']); ?></div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-blue-600 mb-2">คุณภาพผลลัพธ์:</h4>
                            <div class="space-y-1 text-sm">
                                <div><strong>ความเชื่อมั่น:</strong> <?php echo round($result['geocoding']['confidence']); ?>%</div>
                                <div><strong>ความแม่นยำ:</strong> <?php echo ucfirst($result['geocoding']['accuracy']); ?></div>
                                <?php if (isset($result['geocoding']['query_type'])): ?>
                                <div><strong>ประเภทค้นหา:</strong> <?php echo $result['geocoding']['query_type']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-red-50 p-3 rounded">
                    <p class="text-red-600 text-sm">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php echo htmlspecialchars($result['geocoding']['error']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Route Optimization Results -->
    <?php if ($route_result && $route_result['success']): ?>
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-route text-purple-600 mr-2"></i>ผลลัพธ์การคำนวณเส้นทาง
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Route Statistics -->
            <div>
                <h3 class="font-bold text-gray-800 mb-3">สถิติเส้นทาง</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>จำนวนจุดจัดส่ง:</span>
                        <span class="font-bold"><?php echo $route_result['statistics']['num_stops']; ?> จุด</span>
                    </div>
                    <div class="flex justify-between">
                        <span>ระยะทางรวม:</span>
                        <span class="font-bold text-blue-600"><?php echo $route_result['statistics']['total_distance']; ?> กม.</span>
                    </div>
                    <div class="flex justify-between">
                        <span>เวลาการเดินทาง:</span>
                        <span class="font-bold"><?php echo $route_result['statistics']['travel_time']; ?> นาที</span>
                    </div>
                    <div class="flex justify-between">
                        <span>เวลาจัดส่ง:</span>
                        <span class="font-bold"><?php echo $route_result['statistics']['stop_time']; ?> นาที</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span>เวลารวม:</span>
                        <span class="font-bold text-green-600"><?php echo $route_result['statistics']['total_time']; ?> นาที</span>
                    </div>
                    <div class="flex justify-between">
                        <span>ค่าใช้จ่ายประมาณ:</span>
                        <span class="font-bold text-orange-600"><?php echo $route_result['statistics']['estimated_cost']; ?> บาท</span>
                    </div>
                </div>
            </div>
            
            <!-- Route Sequence -->
            <div>
                <h3 class="font-bold text-gray-800 mb-3">ลำดับการจัดส่ง</h3>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <?php foreach ($route_result['route']['points'] as $index => $point): ?>
                    <div class="flex items-center p-2 bg-gray-50 rounded text-sm">
                        <div class="w-6 h-6 rounded-full <?php echo $point['type'] === 'depot' ? 'bg-red-500' : 'bg-blue-500'; ?> text-white text-xs flex items-center justify-center mr-3">
                            <?php echo $index; ?>
                        </div>
                        <div class="flex-1">
                            <?php if ($point['type'] === 'depot'): ?>
                                <strong class="text-red-600">จุดเริ่มต้น/สิ้นสุด</strong>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($point['name']); ?></strong>
                                <div class="text-xs text-gray-600"><?php echo htmlspecialchars($point['awb_number']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Technology Stack -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-code text-gray-600 mr-2"></i>Technology Stack
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <i class="fab fa-php text-3xl text-red-600 mb-2"></i>
                <h3 class="font-bold">PHP 7.4+</h3>
                <p class="text-sm text-gray-600">Backend Processing</p>
            </div>
            
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <i class="fas fa-database text-3xl text-blue-600 mb-2"></i>
                <h3 class="font-bold">MySQL</h3>
                <p class="text-sm text-gray-600">Data Storage</p>
            </div>
            
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <i class="fas fa-map text-3xl text-green-600 mb-2"></i>
                <h3 class="font-bold">Leaflet.js</h3>
                <p class="text-sm text-gray-600">Interactive Maps</p>
            </div>
            
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <i class="fas fa-globe text-3xl text-purple-600 mb-2"></i>
                <h3 class="font-bold">OpenStreetMap</h3>
                <p class="text-sm text-gray-600">Map Data & Geocoding</p>
            </div>
        </div>
        
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <h4 class="font-bold text-gray-700 mb-2">📍 Geocoding APIs:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li>• OpenStreetMap Nominatim</li>
                    <li>• Google Maps (Fallback)</li>
                    <li>• Thai Address Parser</li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold text-gray-700 mb-2">🚛 Route Algorithms:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li>• Nearest Neighbor</li>
                    <li>• 2-Opt Improvement</li>
                    <li>• Simulated Annealing</li>
                    <li>• Genetic Algorithm</li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold text-gray-700 mb-2">🗺️ Map Features:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li>• Marker Clustering</li>
                    <li>• Zone Boundaries</li>
                    <li>• Route Visualization</li>
                    <li>• Multiple Tile Layers</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Links to Other Pages -->
    <div class="bg-gray-50 p-6 rounded-lg">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-link text-indigo-600 mr-2"></i>ลิงก์ที่เกี่ยวข้อง
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="pages/leaflet_map.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                <i class="fas fa-map text-green-600 text-2xl mb-2"></i>
                <h3 class="font-bold">Leaflet Map</h3>
                <p class="text-sm text-gray-600">แผนที่แบบ Interactive</p>
            </a>
            
            <a href="pages/import.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                <i class="fas fa-file-import text-blue-600 text-2xl mb-2"></i>
                <h3 class="font-bold">Data Import</h3>
                <p class="text-sm text-gray-600">นำเข้าข้อมูลการจัดส่ง</p>
            </a>
            
            <a href="pages/geocoding.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                <i class="fas fa-map-marker-alt text-red-600 text-2xl mb-2"></i>
                <h3 class="font-bold">Geocoding</h3>
                <p class="text-sm text-gray-600">แปลงพิกัดที่อยู่</p>
            </a>
            
            <a href="pages/route_planner.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                <i class="fas fa-route text-purple-600 text-2xl mb-2"></i>
                <h3 class="font-bold">Route Planning</h3>
                <p class="text-sm text-gray-600">วางแผนเส้นทางจัดส่ง</p>
            </a>
        </div>
    </div>
</div>

<style>
.grid > div {
    transition: transform 0.2s ease-in-out;
}

.grid > div:hover {
    transform: translateY(-2px);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<?php include 'includes/footer.php'; ?> 