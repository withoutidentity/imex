<?php
require_once 'config/config.php';

echo "<h2>🔍 Debug Employee Data</h2>";

try {
    // Check if table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'delivery_zone_employees'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p>✅ Table 'delivery_zone_employees' exists</p>";
        
        // Count total employees
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_zone_employees");
        $stmt->execute();
        $total = $stmt->fetch()['count'];
        echo "<p>📊 Total employees: $total</p>";
        
        // Count active employees
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_zone_employees WHERE status = 'active'");
        $stmt->execute();
        $active = $stmt->fetch()['count'];
        echo "<p>✅ Active employees: $active</p>";
        
        // Show all employees
        $stmt = $conn->prepare("SELECT id, employee_name, employee_code, nickname, position, status FROM delivery_zone_employees ORDER BY employee_name");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>👥 All employees:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Nickname</th><th>Position</th><th>Status</th></tr>";
        foreach ($employees as $emp) {
            echo "<tr>";
            echo "<td>{$emp['id']}</td>";
            echo "<td>{$emp['employee_name']}</td>";
            echo "<td>{$emp['employee_code']}</td>";
            echo "<td>{$emp['nickname']}</td>";
            echo "<td>{$emp['position']}</td>";
            echo "<td>{$emp['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check zone_employee_assignments table
        $stmt = $conn->prepare("SHOW TABLES LIKE 'zone_employee_assignments'");
        $stmt->execute();
        $assignTableExists = $stmt->fetch();
        
        if ($assignTableExists) {
            echo "<p>✅ Table 'zone_employee_assignments' exists</p>";
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM zone_employee_assignments WHERE is_active = TRUE");
            $stmt->execute();
            $assignments = $stmt->fetch()['count'];
            echo "<p>📋 Active assignments: $assignments</p>";
            
            // Show assignments
            $stmt = $conn->prepare("
                SELECT zea.*, dze.employee_name, za.zone_name 
                FROM zone_employee_assignments zea
                LEFT JOIN delivery_zone_employees dze ON zea.employee_id = dze.id
                LEFT JOIN zone_area za ON zea.zone_id = za.id
                WHERE zea.is_active = TRUE
                ORDER BY za.zone_name, dze.employee_name
            ");
            $stmt->execute();
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>📋 Current assignments:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Zone</th><th>Employee</th><th>Type</th><th>Start Date</th></tr>";
            foreach ($assignments as $assign) {
                echo "<tr>";
                echo "<td>{$assign['zone_name']}</td>";
                echo "<td>{$assign['employee_name']}</td>";
                echo "<td>{$assign['assignment_type']}</td>";
                echo "<td>{$assign['start_date']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ Table 'zone_employee_assignments' does not exist</p>";
        }
        
        // Test query for zone 8
        echo "<h3>🎯 Available employees for Zone 8:</h3>";
        $stmt = $conn->prepare("
            SELECT dze.* 
            FROM delivery_zone_employees dze
            WHERE dze.status = 'active'
            AND dze.id NOT IN (
                SELECT employee_id FROM zone_employee_assignments 
                WHERE zone_id = 8 AND is_active = TRUE
            )
            ORDER BY dze.employee_name
        ");
        $stmt->execute();
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Available employees: " . count($available) . "</p>";
        if (!empty($available)) {
            echo "<ul>";
            foreach ($available as $emp) {
                echo "<li>ID: {$emp['id']} - {$emp['employee_name']} ({$emp['employee_code']})</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<p>❌ Table 'delivery_zone_employees' does not exist</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
