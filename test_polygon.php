<?php
require_once 'config/config.php';

// Check current polygon data in database
$stmt = $conn->prepare("SELECT id, zone_name, zone_code, polygon_type, polygon_coordinates FROM zone_area WHERE polygon_coordinates IS NOT NULL LIMIT 5");
$stmt->execute();
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Polygon Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .zone { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .coordinates { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .test-section { background: #f0f8ff; padding: 20px; margin: 20px 0; border-radius: 10px; border: 2px solid #4a90e2; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #4a90e2; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #357abd; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
    </style>
</head>
<body>
    <h1>🗺️ Test Polygon System - ทดสอบระบบ Polygon</h1>
    
    <div class="test-section">
        <h2>🧪 การทดสอบการวาด Polygon</h2>
        <p><strong>ขั้นตอนการทดสอบ:</strong></p>
        <ol>
            <li>เปิด Map Picker ด้วยลิงก์ด้านล่าง</li>
            <li>ใช้เครื่องมือ <strong>Polygon</strong> (ไม่ใช่ Rectangle) วาดรูปหลายเหลี่ยม</li>
            <li>กด "บันทึกพิกัด" และดูว่าแสดง "🔷 Polygon (หลายเหลี่ยม)" หรือไม่</li>
            <li>กลับมาที่หน้า Zones และบันทึกโซน</li>
            <li>ตรวจสอบข้อมูลในฐานข้อมูล</li>
        </ol>
        
        <div style="margin: 20px 0;">
            <a href="leaflet_map_picker.php?min_lat=8.42824606&max_lat=8.43771744&min_lng=99.96077584&max_lng=99.97011313" target="_blank" class="btn btn-success">
                🗺️ เปิด Map Picker (ทดสอบ Polygon)
            </a>
            
            <a href="pages/zones.php" target="_blank" class="btn btn-warning">
                📍 ไปหน้า Zones Management
            </a>
        </div>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <h4>⚠️ ข้อควรระวัง:</h4>
            <ul>
                <li>ต้องใช้เครื่องมือ <strong>Polygon</strong> (🔷) ไม่ใช่ Rectangle (⬜)</li>
                <li>วาดรูปหลายเหลี่ยมโดยคลิกหลายจุดแล้วดับเบิลคลิกเพื่อปิด</li>
                <li>ตรวจสอบ Console (F12) เพื่อดูข้อมูล Debug</li>
                <li>หากยังเป็น Rectangle ให้ตรวจสอบว่าใช้เครื่องมือถูกต้อง</li>
            </ul>
        </div>
    </div>
    
    <h2>📊 Zones with Polygon Data:</h2>
    <?php if (empty($zones)): ?>
        <p style="color: orange;">⚠️ No zones with polygon data found</p>
        <p>ลองวาด polygon ใน map picker แล้วบันทึกโซนใหม่</p>
    <?php else: ?>
        <?php foreach ($zones as $zone): ?>
            <div class="zone">
                <h3><?php echo htmlspecialchars($zone['zone_name']); ?> (<?php echo htmlspecialchars($zone['zone_code']); ?>)</h3>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($zone['polygon_type']); ?></p>
                <p><strong>Zone ID:</strong> <?php echo $zone['id']; ?></p>
                
                <?php if ($zone['polygon_coordinates']): ?>
                    <div class="coordinates">
                        <strong>Coordinates:</strong><br>
                        <?php 
                        $coords = json_decode($zone['polygon_coordinates'], true);
                        if ($coords) {
                            echo "Points: " . count($coords) . "<br>";
                            foreach (array_slice($coords, 0, 3) as $i => $coord) {
                                echo "Point " . ($i+1) . ": [" . $coord[0] . ", " . $coord[1] . "]<br>";
                            }
                            if (count($coords) > 3) {
                                echo "... และอีก " . (count($coords) - 3) . " จุด<br>";
                            }
                        } else {
                            echo "Invalid JSON: " . htmlspecialchars($zone['polygon_coordinates']);
                        }
                        ?>
                    </div>
                    
                    <p>
                        <a href="leaflet_map_picker.php?zone_id=<?php echo $zone['id']; ?>" target="_blank" style="color: blue; text-decoration: underline;">
                            🗺️ View/Edit in Map Picker
                        </a>
                    </p>
                <?php else: ?>
                    <p style="color: red;">No polygon coordinates</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <hr style="margin: 30px 0;">
    
    <h2>🔗 Test Links:</h2>
    <ul>
        <li><a href="leaflet_map_picker.php?min_lat=8.42824606&max_lat=8.43771744&min_lng=99.96077584&max_lng=99.97011313" target="_blank">🗺️ Open Map Picker (Test Coordinates)</a></li>
        <li><a href="pages/zones.php" target="_blank">📍 Go to Zones Management</a></li>
        <li><a href="test_polygon.php">🔄 Refresh this page</a></li>
    </ul>
    
    <hr style="margin: 30px 0;">
    
    <h2>📈 Database Info:</h2>
    <p>
        <?php 
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM zone_area");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as polygon_count FROM zone_area WHERE polygon_coordinates IS NOT NULL");
        $stmt->execute();
        $polygon_count = $stmt->fetch(PDO::FETCH_ASSOC)['polygon_count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as polygon_type_count FROM zone_area WHERE polygon_type = 'polygon'");
        $stmt->execute();
        $polygon_type_count = $stmt->fetch(PDO::FETCH_ASSOC)['polygon_type_count'];
        
        echo "Total zones: {$total}<br>";
        echo "Zones with polygon coordinates: {$polygon_count}<br>";
        echo "Zones with polygon_type = 'polygon': {$polygon_type_count}<br>";
        echo "Zones with rectangle only: " . ($total - $polygon_count);
        ?>
    </p>
    
    <div class="test-section">
        <h2>🔍 Debug Information</h2>
        <p><strong>หากยังมีปัญหา:</strong></p>
        <ol>
            <li>เปิด Developer Tools (F12)</li>
            <li>ไปที่ Console tab</li>
            <li>วาด polygon ใน map picker</li>
            <li>ดูข้อความ debug ที่ขึ้นต้นด้วย "=== DEBUG:"</li>
            <li>ตรวจสอบว่า polygonType เป็น "polygon" หรือไม่</li>
        </ol>
        
        <p><strong>ข้อมูลที่ควรเห็นใน Console:</strong></p>
        <ul>
            <li><code>Detected polygon with coordinates: [...]</code></li>
            <li><code>polygonType: "polygon"</code></li>
            <li><code>hasPolygonCoords: true</code></li>
        </ul>
    </div>
</body>
</html>
