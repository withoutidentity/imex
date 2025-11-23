<?php
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Debug ข้อมูล</title></head><body>";
echo "<h1>ตรวจสอบข้อมูลในฐานข้อมูล</h1>";

try {
    // ตรวจสอบโซนที่มีอยู่
    echo "<h2>โซนที่มีอยู่:</h2>";
    $stmt = $conn->prepare("SELECT id, zone_code, zone_name FROM zone_area ORDER BY id");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Zone Code</th><th>Zone Name</th></tr>";
    foreach ($zones as $zone) {
        echo "<tr><td>" . $zone['id'] . "</td><td>" . htmlspecialchars($zone['zone_code']) . "</td><td>" . htmlspecialchars($zone['zone_name']) . "</td></tr>";
    }
    echo "</table>";
    
    // ตรวจสอบสถานะที่มีอยู่ใน delivery_address
    echo "<h2>สถานะการจัดส่งที่มีอยู่:</h2>";
    $stmt = $conn->prepare("SELECT DISTINCT delivery_status, COUNT(*) as count FROM delivery_address GROUP BY delivery_status");
    $stmt->execute();
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    foreach ($statuses as $status) {
        echo "<tr><td>" . htmlspecialchars($status['delivery_status']) . "</td><td>" . $status['count'] . "</td></tr>";
    }
    echo "</table>";
    
    // ตรวจสอบข้อมูลในโซน 21
    echo "<h2>ข้อมูลในโซน ID 21:</h2>";
    $stmt = $conn->prepare("SELECT COUNT(*) as count, delivery_status FROM delivery_address WHERE zone_id = 21 GROUP BY delivery_status");
    $stmt->execute();
    $zone21data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($zone21data)) {
        echo "ไม่มีข้อมูลในโซน ID 21";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Status</th><th>Count</th></tr>";
        foreach ($zone21data as $data) {
            echo "<tr><td>" . htmlspecialchars($data['delivery_status']) . "</td><td>" . $data['count'] . "</td></tr>";
        }
        echo "</table>";
    }
    
    // ตรวจสอบข้อมูลสถานะ in_transit
    echo "<h2>ข้อมูลสถานะ in_transit:</h2>";
    $stmt = $conn->prepare("SELECT COUNT(*) as count, zone_id FROM delivery_address WHERE delivery_status = 'in_transit' GROUP BY zone_id ORDER BY zone_id");
    $stmt->execute();
    $transitData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transitData)) {
        echo "ไม่มีข้อมูลสถานะ in_transit";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Zone ID</th><th>Count</th></tr>";
        foreach ($transitData as $data) {
            echo "<tr><td>" . $data['zone_id'] . "</td><td>" . $data['count'] . "</td></tr>";
        }
        echo "</table>";
    }
    
    // ตรวจสอบข้อมูลที่เชื่อมโยงระหว่าง delivery_tracking และ delivery_address
    echo "<h2>การเชื่อมโยง delivery_tracking กับ delivery_address:</h2>";
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT dt.AWB) as tracking_count,
            COUNT(DISTINCT da.awb_number) as address_count,
            COUNT(DISTINCT CASE WHEN dt.AWB = da.awb_number THEN dt.AWB END) as matched_count
        FROM delivery_tracking dt 
        LEFT JOIN delivery_address da ON dt.AWB = da.awb_number
    ");
    $stmt->execute();
    $linkData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Tracking Records</th><th>Address Records</th><th>Matched Records</th></tr>";
    echo "<tr><td>" . $linkData['tracking_count'] . "</td><td>" . $linkData['address_count'] . "</td><td>" . $linkData['matched_count'] . "</td></tr>";
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>


