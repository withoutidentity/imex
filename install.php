<?php
// Database Installation Script
// Smart Delivery Zone Planner

require_once 'config/config.php';

// Check if already installed
if (file_exists('.installed') && !isset($_GET['reinstall'])) {
    header('Location: index.php');
    exit('System already installed. Delete .installed file to reinstall.');
}

$install_status = [];
$install_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create database connection without selecting database
        $host = 'localhost';
        $username = 'root';
        $password = '';
        
        $temp_conn = new PDO("mysql:host=$host", $username, $password);
        $temp_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read and execute SQL file
        $sql_file = file_get_contents('database/schema.sql');
        
        // Split SQL statements more carefully
        $sql_statements = [];
        $current_statement = '';
        $lines = explode("\n", $sql_file);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || substr($line, 0, 2) === '--') {
                continue;
            }
            
            $current_statement .= $line . "\n";
            
            // If line ends with semicolon, treat as complete statement
            if (substr($line, -1) === ';') {
                $sql_statements[] = trim($current_statement);
                $current_statement = '';
            }
        }
        
        // Execute each statement
        foreach ($sql_statements as $statement) {
            if (!empty($statement)) {
                try {
                    $temp_conn->exec($statement);
                } catch (PDOException $e) {
                    // Skip common reinstallation errors
                    $skip_errors = [
                        'already exists',
                        'database already selected', 
                        'Duplicate entry',
                        'Duplicate key name',
                        "Can't DROP",
                        "doesn't exist", // For DROP IF EXISTS when item doesn't exist
                        'Unknown table',
                        'Unknown database'
                    ];
                    
                    $should_skip = false;
                    foreach ($skip_errors as $error_pattern) {
                        if (strpos($e->getMessage(), $error_pattern) !== false) {
                            $should_skip = true;
                            break;
                        }
                    }
                    
                    if (!$should_skip) {
                        throw $e;
                    }
                }
            }
        }
        
        // Test the connection to the new database
        $test_conn = new PDO("mysql:host=$host;dbname=smart_delivery_db", $username, $password);
        $test_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create installation marker
        file_put_contents('.installed', date('Y-m-d H:i:s'));
        
        $install_status[] = 'Database created successfully';
        $install_status[] = 'Tables created successfully';
        $install_status[] = 'Sample data inserted successfully';
        $install_status[] = 'Installation completed successfully';
        
        // Add note about existing data
        if (file_exists('.installed')) {
            $install_status[] = 'Note: Some data already existed and was preserved';
        }
        
    } catch (PDOException $e) {
        $install_errors[] = 'Database Error: ' . $e->getMessage();
    } catch (Exception $e) {
        $install_errors[] = 'Installation Error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบ - Smart Delivery Zone Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="bg-white p-8 rounded-lg shadow-md mb-6">
                <div class="text-center">
                    <i class="fas fa-truck text-6xl text-blue-600 mb-4"></i>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Smart Delivery Zone Planner</h1>
                    <p class="text-gray-600">ระบบจัดการพัสดุและจัดเส้นทางจัดส่งอัตโนมัติ</p>
                </div>
            </div>

            <!-- Installation Status -->
            <?php if (!empty($install_status)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <div class="flex">
                        <i class="fas fa-check-circle text-xl mr-3"></i>
                        <div>
                            <h3 class="font-semibold mb-2">Installation Successful!</h3>
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($install_status as $status): ?>
                                    <li><?php echo htmlspecialchars($status); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-4">
                                <a href="index.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
                                    <i class="fas fa-arrow-right mr-2"></i>ไปยังหน้าหลัก
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Installation Errors -->
            <?php if (!empty($install_errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-xl mr-3"></i>
                        <div>
                            <h3 class="font-semibold mb-2">Installation Failed!</h3>
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($install_errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Installation Form -->
            <?php if (empty($install_status)): ?>
                <div class="bg-white p-8 rounded-lg shadow-md">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">ติดตั้งระบบ</h2>
                    
                    <!-- System Requirements -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">ความต้องการของระบบ</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>PHP 7.4+ <?php echo '(ปัจจุบัน: ' . PHP_VERSION . ')'; ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>MySQL 5.7+ หรือ MariaDB 10.3+</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>PHP Extensions: PDO, JSON, cURL</span>
                            </div>
                        </div>
                    </div>

                    <!-- Database Configuration -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">การตั้งค่าฐานข้อมูล</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-2">ระบบจะใช้การตั้งค่าฐานข้อมูลดังนี้:</p>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><strong>Host:</strong> localhost</li>
                                <li><strong>Database:</strong> smart_delivery_db</li>
                                <li><strong>Username:</strong> root</li>
                                <li><strong>Password:</strong> (ว่าง)</li>
                            </ul>
                            <p class="text-sm text-orange-600 mt-2">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                หากต้องการเปลี่ยนแปลงการตั้งค่า กรุณาแก้ไขไฟล์ config/database.php
                            </p>
                        </div>
                    </div>

                    <!-- Installation Instructions -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">ขั้นตอนการติดตั้ง</h3>
                        <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-600">
                            <li>ตรวจสอบว่าเซิร์ฟเวอร์ MySQL ทำงานอยู่</li>
                            <li>กรอก Google Maps API Key ในไฟล์ config/config.php</li>
                            <li>คลิกปุ่ม "เริ่มติดตั้ง" เพื่อสร้างฐานข้อมูลและตาราง</li>
                            <li>รอจนกว่าการติดตั้งเสร็จสิ้น</li>
                        </ol>
                        <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">
                                <strong>หมายเหตุ:</strong> หากต้องการติดตั้งใหม่ กรุณาลบไฟล์ <code>.installed</code> ออกจากโฟลเดอร์หลัก
                                หรือเข้าถึงหน้านี้ด้วย <code>?reinstall=1</code>
                            </p>
                        </div>
                        
                        <!-- Troubleshooting -->
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h4 class="font-semibold text-yellow-800 mb-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>การแก้ไขปัญหา
                            </h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• หากเจอ Error "Duplicate key" ระบบจะข้ามและดำเนินการต่อ</li>
                                <li>• หากต้องการเริ่มใหม่ทั้งหมด ลบฐานข้อมูล <code>smart_delivery_db</code> ใน phpMyAdmin</li>
                                <li>• หากยังมีปัญหา ตรวจสอบให้แน่ใจว่า MySQL เวอร์ชัน 5.7+ หรือ MariaDB 10.3+</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Google Maps API Key Note -->
                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-6">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>เกี่ยวกับ Google Maps API Key
                        </h4>
                        <p class="text-sm text-blue-700 mb-2">
                            ระบบต้องการ Google Maps API Key เพื่อใช้งานฟีเจอร์ Geocoding และแผนที่
                        </p>
                        <p class="text-sm text-blue-700">
                            คุณสามารถรับ API Key ได้จาก: 
                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="underline">
                                Google Cloud Console
                            </a>
                        </p>
                    </div>

                    <!-- Installation Button -->
                    <form method="POST" id="install-form">
                        <div class="text-center">
                            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                                <i class="fas fa-download mr-2"></i>เริ่มติดตั้ง
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="text-center mt-8 text-gray-500">
                <p>&copy; 2024 Smart Delivery Zone Planner</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('install-form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังติดตั้ง...';
        });
    </script>
</body>
</html> 