<?php
require_once 'config/config.php';

echo "<h2>🔧 Adding Sample Polygon Data</h2>";

try {
    // Check if "พัฒนา2" zone exists
    $stmt = $conn->prepare("SELECT * FROM zone_area WHERE zone_name LIKE '%พัฒนา%' OR zone_code LIKE '%DEV%' ORDER BY id LIMIT 5");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Found zones:</h3>";
    if (empty($zones)) {
        echo "<p>No zones found. Creating sample zone 'พัฒนา2'...</p>";
        
        // Create sample polygon coordinates (complex shape like in the image)
        $polygonCoords = [
            [8.4280, 99.9550],  // Point 1
            [8.4320, 99.9580],  // Point 2
            [8.4350, 99.9620],  // Point 3
            [8.4380, 99.9650],  // Point 4
            [8.4370, 99.9680],  // Point 5
            [8.4340, 99.9700],  // Point 6
            [8.4300, 99.9690],  // Point 7
            [8.4270, 99.9660],  // Point 8
            [8.4250, 99.9620],  // Point 9
            [8.4260, 99.9580]   // Point 10 (back to start area)
        ];
        
        $polygonJson = json_encode($polygonCoords);
        
        // Calculate bounding box
        $lats = array_column($polygonCoords, 0);
        $lngs = array_column($polygonCoords, 1);
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);
        $centerLat = ($minLat + $maxLat) / 2;
        $centerLng = ($minLng + $maxLng) / 2;
        
        $stmt = $conn->prepare("
            INSERT INTO zone_area 
            (zone_code, zone_name, description, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng, color_code, polygon_coordinates, polygon_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'DEV02',
            'พัฒนา2',
            'โซนพัฒนาที่ 2 - รูปทรงซับซ้อน',
            $minLat,
            $maxLat,
            $minLng,
            $maxLng,
            $centerLat,
            $centerLng,
            '#9b59b6', // Purple color
            $polygonJson,
            'polygon'
        ]);
        
        echo "<p>✅ Created sample zone 'พัฒนา2' with complex polygon</p>";
        
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Type</th><th>Has Polygon</th><th>Action</th></tr>";
        
        foreach ($zones as $zone) {
            echo "<tr>";
            echo "<td>{$zone['id']}</td>";
            echo "<td>{$zone['zone_code']}</td>";
            echo "<td>{$zone['zone_name']}</td>";
            echo "<td>" . ($zone['polygon_type'] ?? 'rectangle') . "</td>";
            echo "<td>" . ($zone['polygon_coordinates'] ? 'Yes' : 'No') . "</td>";
            echo "<td>";
            
            if (!$zone['polygon_coordinates'] && strpos($zone['zone_name'], 'พัฒนา') !== false) {
                echo "<form method='POST' style='display: inline;'>";
                echo "<input type='hidden' name='zone_id' value='{$zone['id']}'>";
                echo "<button type='submit' name='add_polygon' style='background: #3498db; color: white; border: none; padding: 5px 10px; border-radius: 3px;'>Add Polygon</button>";
                echo "</form>";
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Handle form submission to add polygon to existing zone
    if (isset($_POST['add_polygon']) && isset($_POST['zone_id'])) {
        $zoneId = intval($_POST['zone_id']);
        
        // Create sample polygon coordinates (complex shape)
        $polygonCoords = [
            [8.4280, 99.9550],  // Point 1
            [8.4320, 99.9580],  // Point 2
            [8.4350, 99.9620],  // Point 3
            [8.4380, 99.9650],  // Point 4
            [8.4370, 99.9680],  // Point 5
            [8.4340, 99.9700],  // Point 6
            [8.4300, 99.9690],  // Point 7
            [8.4270, 99.9660],  // Point 8
            [8.4250, 99.9620],  // Point 9
            [8.4260, 99.9580]   // Point 10
        ];
        
        $polygonJson = json_encode($polygonCoords);
        
        $stmt = $conn->prepare("UPDATE zone_area SET polygon_coordinates = ?, polygon_type = 'polygon' WHERE id = ?");
        $stmt->execute([$polygonJson, $zoneId]);
        
        echo "<p>✅ Added complex polygon coordinates to zone ID: $zoneId</p>";
        echo "<p>🔄 <a href=''>Refresh page</a> to see updated data</p>";
    }
    
    // Show current polygon data
    echo "<h3>🗺️ Current polygon coordinates:</h3>";
    $stmt = $conn->prepare("SELECT id, zone_name, zone_code, polygon_type, polygon_coordinates FROM zone_area WHERE polygon_coordinates IS NOT NULL");
    $stmt->execute();
    $polygonZones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($polygonZones)) {
        foreach ($polygonZones as $zone) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<h4>{$zone['zone_name']} ({$zone['zone_code']})</h4>";
            echo "<p><strong>Type:</strong> {$zone['polygon_type']}</p>";
            echo "<p><strong>Coordinates:</strong></p>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px; font-size: 12px; overflow-x: auto;'>";
            
            $coords = json_decode($zone['polygon_coordinates'], true);
            if ($coords) {
                echo "[\n";
                foreach ($coords as $i => $coord) {
                    echo "  [" . number_format($coord[0], 6) . ", " . number_format($coord[1], 6) . "]";
                    if ($i < count($coords) - 1) echo ",";
                    echo "\n";
                }
                echo "]";
            } else {
                echo $zone['polygon_coordinates'];
            }
            echo "</pre>";
            echo "</div>";
        }
    } else {
        echo "<p>No zones with polygon coordinates found.</p>";
    }
    
    echo "<h3>🔗 Next Steps:</h3>";
    echo "<ul>";
    echo "<li><a href='pages/zones.php' target='_blank'>Test zones.php</a> - Check if complex polygons are displayed</li>";
    echo "<li><a href='debug_zones.php' target='_blank'>Debug zones</a> - View all zone data</li>";
    echo "<li><a href='leaflet_map_picker.php' target='_blank'>Map picker</a> - Create new complex polygons</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
