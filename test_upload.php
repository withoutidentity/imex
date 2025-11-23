<?php
// Simple test to verify uploads directory is writable
$upload_dir = "uploads/";
$test_file = $upload_dir . "test_" . time() . ".txt";
$test_content = "Test file created at " . date('Y-m-d H:i:s');

echo "<h2>Upload Directory Test</h2>";

// Check if directory exists
if (!file_exists($upload_dir)) {
    echo "<p style='color: red;'>❌ Upload directory does not exist</p>";
    exit;
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    echo "<p style='color: red;'>❌ Upload directory is not writable</p>";
    echo "<p>Current permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</p>";
    exit;
}

// Try to create a test file
if (file_put_contents($test_file, $test_content)) {
    echo "<p style='color: green;'>✅ Successfully created test file: " . $test_file . "</p>";
    
    // Clean up test file
    if (unlink($test_file)) {
        echo "<p style='color: green;'>✅ Successfully deleted test file</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Could not delete test file</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Could not create test file in uploads directory</p>";
}

echo "<p><strong>Upload directory info:</strong></p>";
echo "<ul>";
echo "<li>Path: " . realpath($upload_dir) . "</li>";
echo "<li>Exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "</li>";
echo "<li>Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</li>";
echo "<li>Permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</li>";
echo "</ul>";

echo "<p><a href='pages/import.php'>Go to Import Page</a></p>";
?> 