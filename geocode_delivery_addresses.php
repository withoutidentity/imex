<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/nominatim_geocoder.php';

echo "<!DOCTYPE html><html lang='th'><head><meta charset='utf-8'><title>Geocode ที่อยู่ (delivery_address)</title>";
echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans Thai','Noto Sans',Arial,sans-serif;padding:20px} table{border-collapse:collapse;width:100%;margin-top:16px} th,td{border:1px solid #e5e7eb;padding:8px;font-size:14px} th{background:#f8fafc;text-align:left} .ok{color:#166534} .err{color:#991b1b} .muted{color:#6b7280}</style>";
echo "</head><body>";
echo "<h1>เติมพิกัดให้ตาราง delivery_address</h1>";

$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 200;

try {
    // Fetch addresses without coordinates
    $stmt = $conn->prepare("SELECT id, recipient_name, address, province, district, subdistrict, postal_code FROM delivery_address WHERE (latitude IS NULL OR longitude IS NULL) AND (address IS NOT NULL AND address <> '') LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<p class='muted'>ไม่มีแถวที่ต้องเติมพิกัดแล้ว</p>";
        echo "<p><a href='update_zone_area_bounds.php'>อัปเดตพิกัดโซน (จากข้อมูลที่อยู่)</a></p>";
        exit;
    }

    echo "<p>กำลังประมวลผล: <strong>" . count($rows) . "</strong> แถว (limit=$limit)</p>";
    echo "<table><thead><tr><th>ID</th><th>ชื่อผู้รับ</th><th>ที่อยู่</th><th>ผลลัพธ์</th></tr></thead><tbody>";

    $ok = 0; $fail = 0;
    $upd = $conn->prepare("UPDATE delivery_address SET latitude = ?, longitude = ?, geocoded_at = NOW(), geocoding_status = ? WHERE id = ?");

    foreach ($rows as $r) {
        $full = trim(implode(' ', array_filter([
            $r['address'], $r['subdistrict'], $r['district'], $r['province'], $r['postal_code']
        ])));

        $res = hybridGeocode($full);
        if ($res['success']) {
            $lat = $res['lat'];
            $lng = $res['lng'];
            $upd->execute([$lat, $lng, 'success', $r['id']]);
            $ok++;
            echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['recipient_name']) . "</td><td>" . htmlspecialchars($full) . "</td><td class='ok'>{$lat}, {$lng}</td></tr>";
        } else {
            $upd->execute([null, null, 'failed', $r['id']]);
            $fail++;
            $err = htmlspecialchars($res['error'] ?? 'unknown');
            echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['recipient_name']) . "</td><td>" . htmlspecialchars($full) . "</td><td class='err'>ล้มเหลว: {$err}</td></tr>";
        }
        // Be nice to Nominatim rate limit
        usleep(1100000);
    }

    echo "</tbody></table>";
    echo "<p><strong>สรุป:</strong> สำเร็จ {$ok} แถว, ล้มเหลว {$fail} แถว</p>";
    echo "<p><a href='update_zone_area_bounds.php'>ถัดไป: อัปเดตพิกัดโซนจากข้อมูลที่อยู่</a></p>";

} catch (Exception $e) {
    echo "<p class='err'>ผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>"; 