<?php
$page_title = 'ทดสอบการอัพโหลด CSV';
require_once 'config/config.php';
include 'includes/header.php';

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    echo "<div class='bg-white p-6 rounded-lg shadow-md mb-6'>";
    echo "<h2 class='text-xl font-bold mb-4'>ข้อมูลการอัพโหลด</h2>";
    
    echo "<h3 class='font-bold text-lg mb-2'>ข้อมูลไฟล์:</h3>";
    echo "<ul class='list-disc pl-6 space-y-1'>";
    echo "<li>ชื่อไฟล์: " . htmlspecialchars($file['name']) . "</li>";
    echo "<li>ประเภท: " . htmlspecialchars($file['type']) . "</li>";
    echo "<li>ขนาด: " . number_format($file['size']) . " bytes</li>";
    echo "<li>Error Code: " . $file['error'] . "</li>";
    echo "<li>ไฟล์ชั่วคราว: " . htmlspecialchars($file['tmp_name']) . "</li>";
    echo "</ul>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<div class='mt-4 p-4 bg-green-50 border border-green-200 rounded'>";
        echo "<h3 class='font-bold text-green-800'>ผลการวิเคราะห์ไฟล์:</h3>";
        
        $file_path = $file['tmp_name'];
        echo "<p>ไฟล์ชั่วคราวอยู่ที่: " . $file_path . "</p>";
        echo "<p>ไฟล์มีอยู่จริง: " . (file_exists($file_path) ? 'ใช่' : 'ไม่') . "</p>";
        echo "<p>สามารถอ่านได้: " . (is_readable($file_path) ? 'ใช่' : 'ไม่') . "</p>";
        
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            echo "<p>ขนาดจริง: " . strlen($content) . " bytes</p>";
            
            // Show first 200 characters
            echo "<h4 class='font-bold mt-3'>เนื้อหา 200 ตัวอักษรแรก:</h4>";
            echo "<pre class='bg-gray-100 p-2 text-sm overflow-x-auto'>" . htmlspecialchars(substr($content, 0, 200)) . "</pre>";
            
            // Test CSV parsing
            echo "<h4 class='font-bold mt-3'>ผลการแยก CSV:</h4>";
            $csv_data = testCSVParsing($file_path);
            echo "<p>จำนวนแถวที่อ่านได้: " . count($csv_data) . "</p>";
            
            if (!empty($csv_data)) {
                echo "<h5 class='font-bold mt-2'>Header:</h5>";
                echo "<pre class='bg-gray-100 p-2 text-sm'>" . print_r(array_keys($csv_data[0]), true) . "</pre>";
                
                echo "<h5 class='font-bold mt-2'>แถวแรก:</h5>";
                echo "<pre class='bg-gray-100 p-2 text-sm'>" . print_r($csv_data[0], true) . "</pre>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<div class='mt-4 p-4 bg-red-50 border border-red-200 rounded'>";
        echo "<h3 class='font-bold text-red-800'>ข้อผิดพลาดการอัพโหลด:</h3>";
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกินที่กำหนดใน php.ini',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'อัพโหลดไฟล์ไม่สมบูรณ์',
            UPLOAD_ERR_NO_FILE => 'ไม่ได้เลือกไฟล์',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'เขียนไฟล์ไม่ได้',
            UPLOAD_ERR_EXTENSION => 'Extension ห้ามไฟล์นี้'
        ];
        echo "<p>" . ($error_messages[$file['error']] ?? 'ข้อผิดพลาดไม่ทราบสาเหตุ') . "</p>";
        echo "</div>";
    }
    
    echo "</div>";
}

function testCSVParsing($file_path) {
    $data = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Check for BOM
        $first_bytes = fread($handle, 3);
        if ($first_bytes !== "\xef\xbb\xbf") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 1000, ",", '"', "\\");
        
        if ($header) {
            // Clean header
            $header = array_map(function($col) {
                return trim(str_replace("\xef\xbb\xbf", "", $col));
            }, $header);
            
            while (($row = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
                if (count($row) >= count($header)) {
                    // Clean row data
                    $row = array_map(function($cell) {
                        if (!mb_check_encoding($cell, 'UTF-8')) {
                            $cell = utf8_encode($cell);
                        }
                        return trim($cell);
                    }, $row);
                    
                    $data[] = array_combine($header, $row);
                }
            }
        }
        fclose($handle);
    }
    
    return $data;
}
?>

<div class="fadeIn">
    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 rounded-lg shadow-lg mb-6">
        <h1 class="text-2xl font-bold mb-2">ทดสอบการอัพโหลดไฟล์ CSV</h1>
        <p class="text-purple-100">วิเคราะห์ปัญหาการอัพโหลดและอ่านไฟล์ CSV</p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold mb-4">อัพโหลดไฟล์เพื่อทดสอบ</h2>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกไฟล์ CSV</label>
                <input type="file" name="test_file" accept=".csv" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-md hover:bg-purple-700 transition-colors">
                <i class="fas fa-upload mr-2"></i>ทดสอบอัพโหลด
            </button>
        </form>
    </div>

    <!-- Test with sample file -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-4">ทดสอบกับไฟล์ตัวอย่าง</h2>
        
        <div class="space-y-3">
            <p>ทดสอบการอ่านไฟล์ตัวอย่างที่มีอยู่ในระบบ:</p>
            
            <?php
            $sample_file = 'sample_data/sample_deliveries.csv';
            if (file_exists($sample_file)) {
                echo "<div class='p-4 bg-green-50 border border-green-200 rounded'>";
                echo "<h3 class='font-bold text-green-800'>ไฟล์ตัวอย่าง: $sample_file</h3>";
                
                $sample_data = testCSVParsing($sample_file);
                echo "<p>จำนวนแถวที่อ่านได้: " . count($sample_data) . "</p>";
                
                if (!empty($sample_data)) {
                    echo "<p class='text-green-600'>✓ อ่านไฟล์ตัวอย่างได้ปกติ</p>";
                    echo "<p>Header: " . implode(', ', array_keys($sample_data[0])) . "</p>";
                } else {
                    echo "<p class='text-red-600'>✗ ไม่สามารถอ่านไฟล์ตัวอย่างได้</p>";
                }
                echo "</div>";
            } else {
                echo "<div class='p-4 bg-red-50 border border-red-200 rounded'>";
                echo "<p class='text-red-600'>ไม่พบไฟล์ตัวอย่าง: $sample_file</p>";
                echo "</div>";
            }
            ?>
            
            <div class="mt-4">
                <a href="sample_data/sample_deliveries.csv" 
                   class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm"
                   download="sample_deliveries.csv">
                    <i class="fas fa-download mr-2"></i>ดาวน์โหลดไฟล์ตัวอย่าง
                </a>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <a href="pages/import.php" class="inline-flex items-center bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>กลับไปหน้าอิมพอร์ต
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 