<?php
// Update zone_area lat/lng bounds and centers from delivery_address
require_once __DIR__ . '/config/config.php';

echo "<!DOCTYPE html><html lang='th'><head><meta charset='utf-8'><title>อัปเดตพิกัดโซน (zone_area)</title>";
echo "<style>body{font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans Thai','Noto Sans',Arial,sans-serif;padding:20px} table{border-collapse:collapse;width:100%;margin-top:16px} th,td{border:1px solid #e5e7eb;padding:8px;font-size:14px} th{background:#f8fafc;text-align:left} .ok{color:#166534} .warn{color:#7c2d12} .muted{color:#6b7280}</style>";
echo "</head><body>";
echo "<h1>อัปเดตพิกัดโซน (zone_area)</h1>";

try {
    // Get all zones
    $zonesStmt = $conn->prepare("SELECT id, zone_code, zone_name, min_lat, max_lat, min_lng, max_lng, center_lat, center_lng FROM zone_area WHERE is_active = 1 ORDER BY zone_code");
    $zonesStmt->execute();
    $zones = $zonesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($zones)) {
        echo "<p class='muted'>ยังไม่มีโซนในระบบ</p>";
        exit;
    }

    $stats = ['updated' => 0, 'skipped' => 0, 'no_points' => 0];

    echo "<table><thead><tr><th>โซน</th><th>จำนวนพิกัด</th><th>Bounds (lat)</th><th>Bounds (lng)</th><th>Center</th><th>ผลลัพธ์</th></tr></thead><tbody>";

    $boundsStmt = $conn->prepare(
        "SELECT COUNT(*) as cnt,
                MIN(latitude)  as min_lat,
                MAX(latitude)  as max_lat,
                MIN(longitude) as min_lng,
                MAX(longitude) as max_lng,
                AVG(latitude)  as avg_lat,
                AVG(longitude) as avg_lng
         FROM delivery_address
         WHERE zone_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL"
    );

    $updateStmt = $conn->prepare(
        "UPDATE zone_area
         SET min_lat = ?, max_lat = ?, min_lng = ?, max_lng = ?, center_lat = ?, center_lng = ?, updated_at = NOW()
         WHERE id = ?"
    );

    foreach ($zones as $z) {
        $boundsStmt->execute([$z['id']]);
        $b = $boundsStmt->fetch(PDO::FETCH_ASSOC);
        $cnt = (int)$b['cnt'];

        if ($cnt === 0) {
            $stats['no_points']++;
            echo "<tr><td>{$z['zone_code']} - " . htmlspecialchars($z['zone_name']) . "</td>";
            echo "<td class='muted'>0</td><td class='muted'>-</td><td class='muted'>-</td><td class='muted'>-</td><td class='warn'>ไม่มีพิกัดใน delivery_address</td></tr>";
            continue;
        }

        // Calculate bounds and center
        $minLat = round((float)$b['min_lat'], 6);
        $maxLat = round((float)$b['max_lat'], 6);
        $minLng = round((float)$b['min_lng'], 6);
        $maxLng = round((float)$b['max_lng'], 6);
        // Prefer average as center
        $centerLat = round((float)$b['avg_lat'], 6);
        $centerLng = round((float)$b['avg_lng'], 6);

        $updateStmt->execute([$minLat, $maxLat, $minLng, $maxLng, $centerLat, $centerLng, $z['id']]);
        $stats['updated']++;

        echo "<tr><td>{$z['zone_code']} - " . htmlspecialchars($z['zone_name']) . "</td>";
        echo "<td>{$cnt}</td>";
        echo "<td>{$minLat} .. {$maxLat}</td>";
        echo "<td>{$minLng} .. {$maxLng}</td>";
        echo "<td>{$centerLat}, {$centerLng}</td>";
        echo "<td class='ok'>อัปเดตแล้ว</td></tr>";
    }

    echo "</tbody></table>";
    echo "<p><strong>สรุป:</strong> อัปเดต {$stats['updated']} โซน, ไม่มีพิกัด {$stats['no_points']} โซน</p>";
    echo "<p><a href='pages/zones.php'>กลับไปหน้าโซน</a></p>";

} catch (Exception $e) {
    echo "<p class='warn'>เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>"; 