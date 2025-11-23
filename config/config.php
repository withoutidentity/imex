<?php
// Application configuration
define('APP_NAME', 'Smart Delivery Zone Planner');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/imex/');

// Google Maps API Key (แทนที่ด้วย API Key จริงของคุณ)
// ตัวอย่าง: define('GOOGLE_MAPS_API_KEY', 'AIzaSyBkNaAGdwmwZiemCA2uqiSHI-rNxKgJOQU');
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY_HERE');

// Default location settings (นครศรีธรรมราช)
define('DEFAULT_MAP_CENTER_LAT', 8.4304);
define('DEFAULT_MAP_CENTER_LNG', 99.9631);
define('DEFAULT_ZOOM_LEVEL', 13);

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (!session_id()) {
    session_start();
}

// Include database connection
require_once 'database.php';

// Helper functions
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function formatDistance($distance) {
    return number_format($distance, 2) . ' km';
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateTrackingCode() {
    return 'TRK' . date('ymd') . sprintf('%04d', rand(1000, 9999));
}
?> 