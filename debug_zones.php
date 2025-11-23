<?php
require_once 'config/config.php';

echo "<h2>🔍 Debug Zone Data</h2>";

try {
    // Get zones data exactly like zones.php
    $stmt = $conn->prepare("
        SELECT za.*, 
               COUNT(DISTINCT da.id) as delivery_count,
               COUNT(CASE WHEN da.delivery_status = 'pending' THEN 1 END) as pending_count,
               COUNT(CASE WHEN da.delivery_status = 'delivered' THEN 1 END) as delivered_count,
               GROUP_CONCAT(
                   DISTINCT CONCAT(dze.employee_name, ' (', dze.nickname, ')')
                   ORDER BY zea.assignment_type, dze.employee_name
                   SEPARATOR ', '
               ) as assigned_employees,
               COUNT(DISTINCT dze.id) as employee_count
        FROM zone_area za 
        LEFT JOIN delivery_address da ON za.id = da.zone_id 
        LEFT JOIN zone_employee_assignments zea ON za.id = zea.zone_id AND zea.is_active = TRUE
        LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id AND dze.status = 'active'
        WHERE za.is_active = 1
        GROUP BY za.id 
        ORDER BY za.zone_code
    ");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Found " . count($zones) . " active zones</p>";
    
    if (!empty($zones)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Zone Name</th><th>Zone Code</th><th>Color</th>";
        echo "<th>Min Lat</th><th>Max Lat</th><th>Min Lng</th><th>Max Lng</th>";
        echo "<th>Center Lat</th><th>Center Lng</th><th>Employees</th><th>Deliveries</th>";
        echo "</tr>";
        
        foreach ($zones as $zone) {
            echo "<tr>";
            echo "<td>{$zone['id']}</td>";
            echo "<td>{$zone['zone_name']}</td>";
            echo "<td>{$zone['zone_code']}</td>";
            echo "<td style='background-color: {$zone['color_code']}; color: white;'>{$zone['color_code']}</td>";
            echo "<td>" . number_format($zone['min_lat'], 6) . "</td>";
            echo "<td>" . number_format($zone['max_lat'], 6) . "</td>";
            echo "<td>" . number_format($zone['min_lng'], 6) . "</td>";
            echo "<td>" . number_format($zone['max_lng'], 6) . "</td>";
            echo "<td>" . number_format($zone['center_lat'], 6) . "</td>";
            echo "<td>" . number_format($zone['center_lng'], 6) . "</td>";
            echo "<td>" . ($zone['assigned_employees'] ?: 'None') . "</td>";
            echo "<td>{$zone['delivery_count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show JavaScript data
        echo "<h3>JavaScript Data Preview:</h3>";
        echo "<pre style='background-color: #f5f5f5; padding: 10px; overflow-x: auto;'>";
        echo "const zones = " . json_encode($zones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";";
        echo "</pre>";
        
        // Check coordinate validity
        echo "<h3>Coordinate Validation:</h3>";
        $validZones = 0;
        $invalidZones = [];
        
        foreach ($zones as $zone) {
            $minLat = floatval($zone['min_lat']);
            $maxLat = floatval($zone['max_lat']);
            $minLng = floatval($zone['min_lng']);
            $maxLng = floatval($zone['max_lng']);
            
            if ($minLat && $maxLat && $minLng && $maxLng && 
                $minLat < $maxLat && $minLng < $maxLng &&
                $minLat >= -90 && $maxLat <= 90 && $minLng >= -180 && $maxLng <= 180) {
                $validZones++;
            } else {
                $invalidZones[] = $zone['zone_name'] . " (ID: {$zone['id']})";
            }
        }
        
        echo "<p>✅ Valid zones: $validZones</p>";
        if (!empty($invalidZones)) {
            echo "<p>❌ Invalid zones: " . implode(', ', $invalidZones) . "</p>";
        }
        
        // Test polygon bounds
        echo "<h3>Polygon Bounds Test:</h3>";
        echo "<div style='background-color: #f0f8ff; padding: 10px; margin: 10px 0;'>";
        echo "<p>Testing polygon creation for first zone:</p>";
        if (!empty($zones)) {
            $zone = $zones[0];
            $minLat = floatval($zone['min_lat']);
            $maxLat = floatval($zone['max_lat']);
            $minLng = floatval($zone['min_lng']);
            $maxLng = floatval($zone['max_lng']);
            
            echo "<p><strong>Zone:</strong> {$zone['zone_name']} ({$zone['zone_code']})</p>";
            echo "<p><strong>Bounds:</strong></p>";
            echo "<ul>";
            echo "<li>Southwest: [$minLat, $minLng]</li>";
            echo "<li>Southeast: [$minLat, $maxLng]</li>";
            echo "<li>Northeast: [$maxLat, $maxLng]</li>";
            echo "<li>Northwest: [$maxLat, $minLng]</li>";
            echo "</ul>";
            
            // Calculate area (rough estimate)
            $latDiff = abs($maxLat - $minLat);
            $lngDiff = abs($maxLng - $minLng);
            $area = $latDiff * $lngDiff;
            echo "<p><strong>Approximate area:</strong> " . number_format($area, 8) . " square degrees</p>";
            
            if ($area < 0.000001) {
                echo "<p style='color: red;'>⚠️ Warning: Zone area is very small, polygon might not be visible on map</p>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<p>❌ No zones found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>