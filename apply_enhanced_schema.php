<?php
// Apply Enhanced Database Schema
require_once 'config/config.php';

echo "<h1>Enhanced Database Schema Application</h1>\n";

try {
    // Check current table structure
    echo "<h2>Current delivery_address table structure:</h2>\n";
    $stmt = $conn->prepare("DESCRIBE delivery_address");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check if enhanced columns exist
    $existing_columns = array_column($columns, 'Field');
    $required_columns = [
        'address_components', 'house_number', 'building_name', 'soi', 'road', 'moo',
        'keywords', 'parsing_quality', 'geocoding_source', 'geocoding_confidence', 'geocoding_accuracy'
    ];
    
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (!empty($missing_columns)) {
        echo "<h2>Missing columns detected. Applying enhanced schema...</h2>\n";
        echo "<p>Missing columns: " . implode(', ', $missing_columns) . "</p>\n";
        
        // Read and execute enhanced schema
        $schema_file = 'database/enhanced_geocoding_schema.sql';
        if (file_exists($schema_file)) {
            $sql_content = file_get_contents($schema_file);
            
            // Split SQL statements
            $statements = explode(';', $sql_content);
            
            $executed = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                
                // Skip empty statements and comments
                if (empty($statement) || 
                    strpos($statement, '--') === 0 || 
                    strpos($statement, '/*') === 0 ||
                    strpos($statement, 'DELIMITER') !== false) {
                    continue;
                }
                
                try {
                    $conn->exec($statement);
                    $executed++;
                    echo "<div style='color: green;'>✓ Executed: " . substr($statement, 0, 100) . "...</div>\n";
                } catch (PDOException $e) {
                    // Check if it's an ignorable error
                    $ignorable_errors = [
                        'Duplicate column name',
                        'already exists',
                        'Duplicate entry',
                        'Duplicate key name'
                    ];
                    
                    $should_skip = false;
                    foreach ($ignorable_errors as $ignore_pattern) {
                        if (strpos($e->getMessage(), $ignore_pattern) !== false) {
                            $should_skip = true;
                            $skipped++;
                            echo "<div style='color: orange;'>⚠ Skipped: " . $e->getMessage() . "</div>\n";
                            break;
                        }
                    }
                    
                    if (!$should_skip) {
                        $errors[] = $e->getMessage();
                        echo "<div style='color: red;'>✗ Error: " . $e->getMessage() . "</div>\n";
                    }
                }
            }
            
            echo "<h3>Schema Application Summary:</h3>\n";
            echo "<p>Executed: {$executed} statements</p>\n";
            echo "<p>Skipped: {$skipped} statements</p>\n";
            echo "<p>Errors: " . count($errors) . " statements</p>\n";
            
            if (!empty($errors)) {
                echo "<h4>Error Details:</h4>\n";
                foreach ($errors as $error) {
                    echo "<p style='color: red;'>- {$error}</p>\n";
                }
            }
            
        } else {
            echo "<p style='color: red;'>Schema file not found: {$schema_file}</p>\n";
        }
        
    } else {
        echo "<h2 style='color: green;'>✓ All enhanced columns already exist!</h2>\n";
    }
    
    // Check table structure after changes
    echo "<h2>Updated delivery_address table structure:</h2>\n";
    $stmt = $conn->prepare("DESCRIBE delivery_address");
    $stmt->execute();
    $updated_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    foreach ($updated_columns as $column) {
        $is_new = !in_array($column['Field'], $existing_columns);
        $style = $is_new ? 'background-color: lightgreen;' : '';
        echo "<tr style='{$style}'>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Test the enhanced features
    echo "<h2>Testing Enhanced Features:</h2>\n";
    
    // Test address parsing
    echo "<h3>1. Testing Address Parser:</h3>\n";
    require_once 'includes/address_parser.php';
    
    $test_address = "123 ถนนสุขุมวิท ซอยอโศก แขวงคลองเตย เขตคลองเตย กรุงเทพมหานคร 10110";
    $parser = new ThaiAddressParser();
    $parsed = $parser->parseAddress($test_address);
    $validation = $parser->validateParsing($parsed);
    
    echo "<p><strong>Test Address:</strong> {$test_address}</p>\n";
    echo "<p><strong>Parsed Results:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>House Number: {$parsed['house_number']}</li>\n";
    echo "<li>Road: {$parsed['road']}</li>\n";
    echo "<li>Soi: {$parsed['soi']}</li>\n";
    echo "<li>District: {$parsed['district']}</li>\n";
    echo "<li>Province: {$parsed['province']}</li>\n";
    echo "<li>Quality: {$validation['quality']} ({$validation['percentage']}%)</li>\n";
    echo "</ul>\n";
    
    // Test geocoding
    echo "<h3>2. Testing Nominatim Geocoder:</h3>\n";
    if (file_exists('includes/nominatim_geocoder.php')) {
        require_once 'includes/nominatim_geocoder.php';
        
        $geocoder = new NominatimGeocoder();
        $geocoding_result = $geocoder->geocode($test_address);
        
        if ($geocoding_result['success']) {
            echo "<p style='color: green;'>✓ Geocoding successful!</p>\n";
            echo "<ul>\n";
            echo "<li>Latitude: {$geocoding_result['lat']}</li>\n";
            echo "<li>Longitude: {$geocoding_result['lng']}</li>\n";
            echo "<li>Confidence: {$geocoding_result['confidence']}%</li>\n";
            echo "<li>Accuracy: {$geocoding_result['accuracy']}</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p style='color: red;'>✗ Geocoding failed: {$geocoding_result['error']}</p>\n";
        }
    } else {
        echo "<p style='color: orange;'>⚠ Nominatim geocoder not found</p>\n";
    }
    
    echo "<h2 style='color: green;'>✅ Enhanced schema application completed!</h2>\n";
    echo "<p><a href='pages/leaflet_map.php'>Try accessing Leaflet Map page now</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?> 