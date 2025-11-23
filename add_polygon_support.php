<?php
require_once 'config/config.php';

echo "<h2>🔧 Adding Polygon Support to Zone Area Table</h2>";

try {
    // Check if columns already exist
    $stmt = $conn->prepare("SHOW COLUMNS FROM zone_area LIKE 'polygon_coordinates'");
    $stmt->execute();
    $polygonColumnExists = $stmt->fetch();
    
    $stmt = $conn->prepare("SHOW COLUMNS FROM zone_area LIKE 'polygon_type'");
    $stmt->execute();
    $typeColumnExists = $stmt->fetch();
    
    if (!$polygonColumnExists) {
        echo "<p>Adding polygon_coordinates column...</p>";
        $stmt = $conn->prepare("ALTER TABLE zone_area ADD COLUMN polygon_coordinates TEXT AFTER color_code");
        $stmt->execute();
        echo "<p>✅ Added polygon_coordinates column</p>";
    } else {
        echo "<p>✅ polygon_coordinates column already exists</p>";
    }
    
    if (!$typeColumnExists) {
        echo "<p>Adding polygon_type column...</p>";
        $stmt = $conn->prepare("ALTER TABLE zone_area ADD COLUMN polygon_type ENUM('rectangle', 'polygon') DEFAULT 'rectangle' AFTER polygon_coordinates");
        $stmt->execute();
        echo "<p>✅ Added polygon_type column</p>";
    } else {
        echo "<p>✅ polygon_type column already exists</p>";
    }
    
    // Update existing zones to have polygon_type = 'rectangle'
    echo "<p>Updating existing zones to rectangle type...</p>";
    $stmt = $conn->prepare("UPDATE zone_area SET polygon_type = 'rectangle' WHERE polygon_coordinates IS NULL OR polygon_coordinates = ''");
    $stmt->execute();
    $affectedRows = $stmt->rowCount();
    echo "<p>✅ Updated $affectedRows zones to rectangle type</p>";
    
    // Add index for polygon_type if it doesn't exist
    try {
        $stmt = $conn->prepare("ALTER TABLE zone_area ADD INDEX idx_polygon_type (polygon_type)");
        $stmt->execute();
        echo "<p>✅ Added index for polygon_type</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✅ Index for polygon_type already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Show current table structure
    echo "<h3>📋 Current zone_area table structure:</h3>";
    $stmt = $conn->prepare("SHOW COLUMNS FROM zone_area");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>✅ Polygon support added successfully!</h3>";
    echo "<p>Now you can store complex polygon coordinates in the polygon_coordinates column as JSON.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
