<?php
$page_title = 'นำเข้าข้อมูลการจัดส่ง';
require_once '../config/config.php';
include '../includes/header.php';

// Initialize variables
$upload_result = '';
$upload_error = '';
$preview_data = [];
$import_stats = [];

// Persist selected date in session throughout the import flow
if (!isset($_SESSION['import_delivery_date'])) {
    $_SESSION['import_delivery_date'] = '';
}

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'set_date') {
            // Save selected date
            $date = trim($_POST['delivery_date'] ?? '');
            $_SESSION['import_delivery_date'] = $date;
            if (!$date) {
                $upload_error = 'กรุณาเลือกวันที่จัดส่งก่อนอัพโหลดไฟล์';
            }
        } elseif ($_POST['action'] === 'upload' && isset($_FILES['csv_file'])) {
            // Require date before allowing upload
            if (empty($_SESSION['import_delivery_date'])) {
                $upload_error = 'กรุณาเลือกวันที่จัดส่งก่อนอัพโหลดไฟล์';
            } else {
                $result = handleFileUpload($_FILES['csv_file']);
                if ($result['success']) {
                    $preview_data = $result['data'];
                    $upload_result = "อัพโหลดสำเร็จ: พบข้อมูล " . count($preview_data) . " รายการ";
                } else {
                    $upload_error = $result['error'];
                }
            }
        } elseif ($_POST['action'] === 'import' && isset($_POST['import_data'])) {
            $import_data = json_decode($_POST['import_data'], true);
            $result = importToDatabase($import_data);
            $import_stats = $result;
        }
    }
}

function handleFileUpload($file)
{
    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกินที่กำหนดใน php.ini',
                UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินที่กำหนดในฟอร์ม',
                UPLOAD_ERR_PARTIAL => 'อัพโหลดไฟล์ไม่สมบูรณ์',
                UPLOAD_ERR_NO_FILE => 'ไม่ได้เลือกไฟล์',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
                UPLOAD_ERR_CANT_WRITE => 'เขียนไฟล์ไม่ได้',
                UPLOAD_ERR_EXTENSION => 'Extension ห้ามไฟล์นี้'
            ];
            $error_msg = $error_messages[$file['error']] ?? 'ข้อผิดพลาดไม่ทราบสาเหตุ (Code: ' . $file['error'] . ')';
            return ['success' => false, 'error' => 'ข้อผิดพลาดในการอัพโหลดไฟล์: ' . $error_msg];
        }

        $file_path = $file['tmp_name'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            return ['success' => false, 'error' => 'รองรับเฉพาะไฟล์ CSV, XLSX, XLS เท่านั้น'];
        }

        $data = [];

        if ($file_extension === 'csv') {
            $data = readCSVFile($file_path);
        } else {
            $data = readExcelFile($file_path);
        }

        if (empty($data)) {
            // More detailed debugging information
            $debug_info = [];
            $debug_info[] = "ไฟล์ต้นฉบับ: " . $file['name'];
            $debug_info[] = "ขนาดไฟล์: " . number_format($file['size']) . " bytes";
            $debug_info[] = "ประเภทไฟล์: " . $file['type'];
            $debug_info[] = "นามสกุล: " . $file_extension;

            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                $debug_info[] = "ขนาดจริง: " . strlen($content) . " bytes";
                $debug_info[] = "เนื้อหาตัวอย่าง: " . substr($content, 0, 100) . "...";
            } else {
                $debug_info[] = "ไม่พบไฟล์ชั่วคราว: " . $file_path;
            }

            return ['success' => false, 'error' => 'ไม่พบข้อมูลในไฟล์ หรือรูปแบบไฟล์ไม่ถูกต้อง<br><br>ข้อมูลการวิเคราะห์:<br>' . implode('<br>', $debug_info) . '<br><br><a href="../test_csv_upload.php" class="text-blue-600 underline">ทดสอบการอัพโหลดแบบละเอียด</a>'];
        }

        // Validate and clean data
        $cleaned_data = validateAndCleanData($data);

        return ['success' => true, 'data' => $cleaned_data];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'ข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function readCSVFile($file_path)
{
    $data = [];

    // Set proper encoding for Thai characters
    setlocale(LC_ALL, 'th_TH.UTF-8');

    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Skip BOM if present
        if (fgets($handle, 4) !== "\xef\xbb\xbf") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 1000, ",", '"', "\\");

        if ($header) {
            // Clean header - remove BOM and trim
            $header = array_map(function ($col) {
                return trim(str_replace("\xef\xbb\xbf", "", $col));
            }, $header);

            while (($row = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
                if (count($row) >= count($header)) {
                    // Ensure proper UTF-8 encoding
                    $row = array_map(function ($cell) {
                        if (!mb_check_encoding($cell, 'UTF-8')) {
                            $cell = mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1');
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

function readExcelFile($file_path)
{
    $data = [];

    try {
        if (!class_exists('ZipArchive')) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($file_path) !== TRUE) {
            return [];
        }

        // Load shared strings (if exist)
        $sharedStrings = [];
        $sst = $zip->getFromName('xl/sharedStrings.xml');
        if ($sst !== false) {
            $sstXml = @simplexml_load_string($sst);
            if ($sstXml && isset($sstXml->si)) {
                foreach ($sstXml->si as $si) {
                    // Concatenate all text nodes within <si>
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            if (isset($r->t)) {
                                $text .= (string) $r->t;
                            }
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // Find first worksheet if sheet1.xml not present
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#', $name)) {
                    $sheetXmlContent = $zip->getFromName($name);
                    if ($sheetXmlContent !== false) {
                        break;
                    }
                }
            }
        }

        if ($sheetXmlContent === false) {
            $zip->close();
            return [];
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml) {
            $zip->close();
            return [];
        }

        // Helpers
        $colToIndex = function ($ref) {
            // Extract letters from ref (e.g., A1 -> A)
            if (!preg_match('/([A-Z]+)/', $ref, $m))
                return 1;
            $letters = $m[1];
            $num = 0;
            for ($i = 0; $i < strlen($letters); $i++) {
                $num = $num * 26 + (ord($letters[$i]) - 64);
            }
            return $num; // 1-based index
        };

        $getCellValue = function ($c) use ($sharedStrings) {
            $type = (string) ($c['t'] ?? '');
            // inline string
            if ($type === 'inlineStr' && isset($c->is->t)) {
                return (string) $c->is->t;
            }
            // shared string
            if ($type === 's' && isset($c->v)) {
                $idx = (int) $c->v;
                return $sharedStrings[$idx] ?? '';
            }
            // boolean
            if ($type === 'b' && isset($c->v)) {
                return ((string) $c->v === '1') ? 'TRUE' : 'FALSE';
            }
            // number/date/general or plain text
            return isset($c->v) ? (string) $c->v : '';
        };

        $rows = [];
        if (isset($sheetXml->sheetData->row)) {
            foreach ($sheetXml->sheetData->row as $row) {
                $cells = [];
                $maxIndex = 0;
                foreach ($row->c as $c) {
                    $ref = (string) ($c['r'] ?? '');
                    $idx = $ref ? $colToIndex($ref) : ($maxIndex + 1);
                    $value = $getCellValue($c);
                    $cells[$idx] = $value;
                    if ($idx > $maxIndex)
                        $maxIndex = $idx;
                }
                // Normalize to continuous array from 1..maxIndex
                $rowValues = [];
                for ($i = 1; $i <= $maxIndex; $i++) {
                    $rowValues[] = $cells[$i] ?? '';
                }
                $rows[] = $rowValues;
            }
        }

        $zip->close();

        if (empty($rows)) {
            return [];
        }

        // Build associative array using first row as header
        $headers = array_map(function ($h) {
            return trim((string) $h);
        }, $rows[0]);

        // Remove empty trailing headers
        while (!empty($headers) && end($headers) === '') {
            array_pop($headers);
        }

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $assoc = [];
            for ($c = 0; $c < count($headers); $c++) {
                $assoc[$headers[$c]] = isset($row[$c]) ? trim((string) $row[$c]) : '';
            }
            // Skip completely empty rows
            if (implode('', array_values($assoc)) !== '') {
                $data[] = $assoc;
            }
        }

        return $data;

    } catch (Exception $e) {
        return [];
    }
}

function validateAndCleanData($data)
{
    $cleaned = [];

    // First, detect actual column names in the data
    if (empty($data)) {
        return $cleaned;
    }

    $sample_row = reset($data);
    $actual_columns = array_keys($sample_row);

    // Dynamic column mapping to handle different column names
    $column_mappings = [
        'awb_number' => ['AWB', 'awb', 'เลขพัสดุ', 'awb_number', '单号', '運單號'],
        'recipient_name' => ['ชื่อผู้รับ', 'recipient_name', 'ผู้รับ', '收件人姓名', '收件人', '收货人'],
        'recipient_phone' => ['เบอร์โทร', 'เบอร์โทรผู้รับ', 'เบอร์มือถือผู้รับ', 'recipient_phone', 'phone', '收件人电话', '電話'],
        'recipient_address' => ['ที่อยู่ผู้รับ', 'ที่อยู่', 'address', 'recipient_address', '收件人地址', '地址'],
        'province' => ['จังหวัด', 'province', '省份'],
        'district' => ['อำเภอ', 'district', '区县'],
        'sub_district' => ['ตำบล', 'sub_district', 'subdistrict', '街道'],
        'postal_code' => ['รหัสไปรษณีย์', 'postal_code', 'zip_code', '邮编'],
        'cod_amount' => ['COD', 'cod', 'cod_amount', '代收金额'],
        'service_type' => ['ประเภทบริการ', 'service_type', 'service', '服务类型'],
        'priority_level' => ['ความสำคัญ', 'priority_level', 'priority', '优先级'],
        'zone_name' => ['ชื่อเขต', 'zone_name', '区域名称'],
        'zone_code' => ['รหัสเขต', 'รหัสโซน', 'zone_code', '区域代码'],
        'delivery_branch' => ['สาขาจัดส่ง', 'สาขานำจ่าย', 'delivery_branch', '配送分店'],
        'sign_branch' => ['สาขาเซ็นรับ', 'sign_branch', '签收分店'],
        'franchise_name' => ['ชื่อแฟรนไชส์'],
        'franchise_code' => ['รหัสแฟรนไชส์'],
        'gateway_time' => ['เวลาเกทเวย์นำส่ง'],
        'earliest_time' => ['เวลาที่เร็วที่สุด'],
        'arrival_at_branch' => ['เวลาที่พัสดุถึงสาขา'],
        'delivery_branch_code' => ['รหัสสาขานำจ่าย'],
        'delivery_branch_name' => ['ชื่อสาขานำจ่าย'],
        'delivery_time' => ['เวลาที่นำจ่ายพัสดุ'],
        'delivery_staff_code' => ['รหัสพนักงานนำจ่าย'],
        'delivery_staff_name' => ['ชื่อของพนักงานนำจ่าย'],
        'delivery_staff_position' => ['派件员岗位'],
        'delivery_staff_phone' => ['派件员手机'],
        'helper_required' => ['ช่วยนำจ่ายหรือไม่'],
        'problem_time' => ['เวลาพัสดุมีปัญหา'],
        'problem_reason' => ['สาเหตุของพัสดุมีปัญหา'],
        'sign_received_time' => ['เวลาที่เซ็นรับพัสดุ'],
        'sign_received_branch' => ['สาขาเซ็นรับ'],
        'sign_record_time' => ['เวลาการบันทึกเซ็นรับพัสดุ'],
        'sign_received_by_staff' => ['(พนักงานนำจ่ายพัสดุ)เซ็นรับพัสดุ'],
        'sign_received_by' => ['ผู้เซ็นรับ'],
        'return_register_time' => ['เวลาลงทะเบียนพัสดุตีกลับ'],
        'return_register_branch' => ['สาขาลงทะเบียนตีกลับ'],
        'branch_type' => ['ประเภทสาขา'],
        'order_source' => ['แหล่งที่มาของออเดอร์'],
        'parcel_type' => ['ประเภทพัสดุ'],
        'weight_charged' => ['น้ำหนักที่ใช้คิดเงิน'],
        'payment_type' => ['ประภทการชำระเงิน'],
        'total_shipping_fee' => ['ค่าขนส่งทั้งหมด'],
        'sender_name' => ['ผู้ส่ง'],
        'sender_customer' => ['ลูกค้าผู้ส่ง'],
        'sender_phone' => ['เบอร์โทรผู้ส่ง'],
        'return_symbol' => ['สัญลักษณ์พัสดุตีกลับ'],
        'parcel_type_1' => ['ประเภทพัสดุ_1'],
        'service_type_addon' => ['ประเภทค่าบริการเสริม']
    ];

    // Create reverse mapping from actual columns to our fields
    $field_map = [];
    foreach ($column_mappings as $field => $possible_names) {
        foreach ($actual_columns as $col) {
            foreach ($possible_names as $possible) {
                if (
                    strcasecmp(trim($col), trim($possible)) === 0 ||
                    stripos($col, $possible) !== false
                ) {
                    $field_map[$field] = $col;
                    break 2; // Break both loops
                }
            }
        }
    }

    // Debug: log detected columns
    error_log("Detected column mapping: " . json_encode($field_map));
    error_log("Available columns: " . json_encode($actual_columns));

    foreach ($data as $index => $row) {
        $clean_row = [];

        // Map CSV columns to database fields using detected mapping
        $clean_row['awb_number'] = isset($field_map['awb_number']) && isset($row[$field_map['awb_number']]) ? trim($row[$field_map['awb_number']]) : '';
        $clean_row['recipient_name'] = isset($field_map['recipient_name']) && isset($row[$field_map['recipient_name']]) ? trim($row[$field_map['recipient_name']]) : '';
        $clean_row['recipient_phone'] = isset($field_map['recipient_phone']) && isset($row[$field_map['recipient_phone']]) ? trim($row[$field_map['recipient_phone']]) : '';
        $clean_row['recipient_address'] = isset($field_map['recipient_address']) && isset($row[$field_map['recipient_address']]) ? trim($row[$field_map['recipient_address']]) : '';
        $clean_row['province'] = isset($field_map['province']) && isset($row[$field_map['province']]) ? trim($row[$field_map['province']]) : '';
        $clean_row['district'] = isset($field_map['district']) && isset($row[$field_map['district']]) ? trim($row[$field_map['district']]) : '';
        $clean_row['sub_district'] = isset($field_map['sub_district']) && isset($row[$field_map['sub_district']]) ? trim($row[$field_map['sub_district']]) : '';
        $clean_row['postal_code'] = isset($field_map['postal_code']) && isset($row[$field_map['postal_code']]) ? trim($row[$field_map['postal_code']]) : '';
        $clean_row['cod_amount'] = isset($field_map['cod_amount']) && isset($row[$field_map['cod_amount']]) ? floatval($row[$field_map['cod_amount']]) : 0;
        $clean_row['service_type'] = isset($field_map['service_type']) && isset($row[$field_map['service_type']]) ? trim($row[$field_map['service_type']]) : 'standard';
        $clean_row['priority_level'] = isset($field_map['priority_level']) && isset($row[$field_map['priority_level']]) ? trim($row[$field_map['priority_level']]) : 'normal';
        $clean_row['zone_name'] = isset($field_map['zone_name']) && isset($row[$field_map['zone_name']]) ? trim($row[$field_map['zone_name']]) : '';
        $clean_row['zone_code'] = isset($field_map['zone_code']) && isset($row[$field_map['zone_code']]) ? trim($row[$field_map['zone_code']]) : '';
        $clean_row['delivery_branch'] = isset($field_map['delivery_branch']) && isset($row[$field_map['delivery_branch']]) ? trim($row[$field_map['delivery_branch']]) : '';
        $clean_row['sign_branch'] = isset($field_map['sign_branch']) && isset($row[$field_map['sign_branch']]) ? trim($row[$field_map['sign_branch']]) : '';
        
        // New fields for delivery_tracking table
        $clean_row['franchise_name'] = isset($field_map['franchise_name']) && isset($row[$field_map['franchise_name']]) ? trim($row[$field_map['franchise_name']]) : '';
        $clean_row['franchise_code'] = isset($field_map['franchise_code']) && isset($row[$field_map['franchise_code']]) ? trim($row[$field_map['franchise_code']]) : '';
        $clean_row['gateway_time'] = isset($field_map['gateway_time']) && isset($row[$field_map['gateway_time']]) ? trim($row[$field_map['gateway_time']]) : '';
        $clean_row['earliest_time'] = isset($field_map['earliest_time']) && isset($row[$field_map['earliest_time']]) ? trim($row[$field_map['earliest_time']]) : '';
        $clean_row['arrival_at_branch'] = isset($field_map['arrival_at_branch']) && isset($row[$field_map['arrival_at_branch']]) ? trim($row[$field_map['arrival_at_branch']]) : '';
        $clean_row['delivery_branch_code'] = isset($field_map['delivery_branch_code']) && isset($row[$field_map['delivery_branch_code']]) ? trim($row[$field_map['delivery_branch_code']]) : '';
        $clean_row['delivery_branch_name'] = isset($field_map['delivery_branch_name']) && isset($row[$field_map['delivery_branch_name']]) ? trim($row[$field_map['delivery_branch_name']]) : '';
        $clean_row['delivery_time'] = isset($field_map['delivery_time']) && isset($row[$field_map['delivery_time']]) ? trim($row[$field_map['delivery_time']]) : '';
        $clean_row['delivery_staff_code'] = isset($field_map['delivery_staff_code']) && isset($row[$field_map['delivery_staff_code']]) ? trim($row[$field_map['delivery_staff_code']]) : '';
        $clean_row['delivery_staff_name'] = isset($field_map['delivery_staff_name']) && isset($row[$field_map['delivery_staff_name']]) ? trim($row[$field_map['delivery_staff_name']]) : '';
        $clean_row['delivery_staff_position'] = isset($field_map['delivery_staff_position']) && isset($row[$field_map['delivery_staff_position']]) ? trim($row[$field_map['delivery_staff_position']]) : '';
        $clean_row['delivery_staff_phone'] = isset($field_map['delivery_staff_phone']) && isset($row[$field_map['delivery_staff_phone']]) ? trim($row[$field_map['delivery_staff_phone']]) : '';
        $clean_row['helper_required'] = isset($field_map['helper_required']) && isset($row[$field_map['helper_required']]) ? trim($row[$field_map['helper_required']]) : '';
        $clean_row['problem_time'] = isset($field_map['problem_time']) && isset($row[$field_map['problem_time']]) ? trim($row[$field_map['problem_time']]) : '';
        $clean_row['problem_reason'] = isset($field_map['problem_reason']) && isset($row[$field_map['problem_reason']]) ? trim($row[$field_map['problem_reason']]) : '';
        $clean_row['sign_received_time'] = isset($field_map['sign_received_time']) && isset($row[$field_map['sign_received_time']]) ? trim($row[$field_map['sign_received_time']]) : '';
        $clean_row['sign_received_branch'] = isset($field_map['sign_received_branch']) && isset($row[$field_map['sign_received_branch']]) ? trim($row[$field_map['sign_received_branch']]) : '';
        $clean_row['sign_record_time'] = isset($field_map['sign_record_time']) && isset($row[$field_map['sign_record_time']]) ? trim($row[$field_map['sign_record_time']]) : '';
        $clean_row['sign_received_by_staff'] = isset($field_map['sign_received_by_staff']) && isset($row[$field_map['sign_received_by_staff']]) ? trim($row[$field_map['sign_received_by_staff']]) : '';
        $clean_row['sign_received_by'] = isset($field_map['sign_received_by']) && isset($row[$field_map['sign_received_by']]) ? trim($row[$field_map['sign_received_by']]) : '';
        $clean_row['return_register_time'] = isset($field_map['return_register_time']) && isset($row[$field_map['return_register_time']]) ? trim($row[$field_map['return_register_time']]) : '';
        $clean_row['return_register_branch'] = isset($field_map['return_register_branch']) && isset($row[$field_map['return_register_branch']]) ? trim($row[$field_map['return_register_branch']]) : '';
        $clean_row['branch_type'] = isset($field_map['branch_type']) && isset($row[$field_map['branch_type']]) ? trim($row[$field_map['branch_type']]) : '';
        $clean_row['order_source'] = isset($field_map['order_source']) && isset($row[$field_map['order_source']]) ? trim($row[$field_map['order_source']]) : '';
        $clean_row['parcel_type'] = isset($field_map['parcel_type']) && isset($row[$field_map['parcel_type']]) ? trim($row[$field_map['parcel_type']]) : '';
        $clean_row['weight_charged'] = isset($field_map['weight_charged']) && isset($row[$field_map['weight_charged']]) ? floatval($row[$field_map['weight_charged']]) : 0;
        $clean_row['payment_type'] = isset($field_map['payment_type']) && isset($row[$field_map['payment_type']]) ? trim($row[$field_map['payment_type']]) : '';
        $clean_row['total_shipping_fee'] = isset($field_map['total_shipping_fee']) && isset($row[$field_map['total_shipping_fee']]) ? floatval($row[$field_map['total_shipping_fee']]) : 0;
        $clean_row['sender_name'] = isset($field_map['sender_name']) && isset($row[$field_map['sender_name']]) ? trim($row[$field_map['sender_name']]) : '';
        $clean_row['sender_customer'] = isset($field_map['sender_customer']) && isset($row[$field_map['sender_customer']]) ? trim($row[$field_map['sender_customer']]) : '';
        $clean_row['sender_phone'] = isset($field_map['sender_phone']) && isset($row[$field_map['sender_phone']]) ? trim($row[$field_map['sender_phone']]) : '';
        $clean_row['return_symbol'] = isset($field_map['return_symbol']) && isset($row[$field_map['return_symbol']]) ? trim($row[$field_map['return_symbol']]) : '';
        $clean_row['parcel_type_1'] = isset($field_map['parcel_type_1']) && isset($row[$field_map['parcel_type_1']]) ? trim($row[$field_map['parcel_type_1']]) : '';
        $clean_row['service_type_addon'] = isset($field_map['service_type_addon']) && isset($row[$field_map['service_type_addon']]) ? trim($row[$field_map['service_type_addon']]) : '';

        // Validate required fields (only AWB and recipient_name are truly required)
        $is_valid = true;
        $missing_fields = [];

        if (empty($clean_row['awb_number'])) {
            $is_valid = false;
            $missing_fields[] = 'AWB';
        }
        if (empty($clean_row['recipient_name'])) {
            $is_valid = false;
            $missing_fields[] = 'ชื่อผู้รับ';
        }

        // Store original row data for debugging
        $clean_row['original_data'] = $row;
        $clean_row['detected_columns'] = $field_map;

        if ($is_valid) {
            $clean_row['row_number'] = $index + 2; // +2 because index starts at 0 and we have header
            $clean_row['status'] = 'valid';
        } else {
            $clean_row['row_number'] = $index + 2;
            $clean_row['status'] = 'invalid';
            $clean_row['error'] = 'ข้อมูลไม่ครบถ้วน: ' . implode(', ', $missing_fields);
        }

        $cleaned[] = $clean_row;
    }

    return $cleaned;
}

function formatDateTime($value)
{
    if (empty($value)) {
        return null;
    }

    // Handle Excel Serial Date (numeric)
    if (is_numeric($value) && $value > 25569) {
        $unix_date = ($value - 25569) * 86400;
        return gmdate("Y-m-d H:i:s", (int)$unix_date);
    }

    $value = trim($value);
    // Normalize spaces (convert multiple spaces to single space)
    $value = preg_replace('/\s+/', ' ', $value);

    // Try explicit formats (Thai d/m/Y preference)
    $formats = [
        'd/m/Y g:i:s A', // 07/05/2025 09:06:42 AM
        'j/n/Y g:i:s A', // 7/5/2025 9:06:42 AM
        'd/m/Y H:i:s',   // 07/05/2025 13:00:00
        'j/n/Y H:i:s',   // 7/5/2025 13:00:00
        'd/m/Y H:i',
        'j/n/Y H:i',
        'd/m/Y',
        'j/n/Y'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt !== false) {
            $errors = DateTime::getLastErrors();
            if ($errors['error_count'] === 0 && $errors['warning_count'] === 0) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }

    // Fallback: Regex for d/m/Y (handling various separators and loose time)
    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})\s*(.*)$/', $value, $matches)) {
        $day = $matches[1];
        $month = $matches[2];
        $year = $matches[3];
        $time = trim($matches[4]);

        // Handle 2 digit year (assume 20xx)
        if (strlen($year) == 2) {
            $year = '20' . $year;
        }

        $dt_string = "$year-$month-$day" . ($time ? " $time" : "");
        $timestamp = strtotime($dt_string);
        if ($timestamp) {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }

    // Final fallback
    $timestamp = strtotime($value);
    if ($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function importToDatabase($data)
{
    global $conn;

    $imported = 0;
    $imported_details = []; // To track which table was inserted into
    $skipped = 0;
    $errors = [];

    // Use the selected delivery date for imported records
    $selectedDate = $_SESSION['import_delivery_date'] ?? '';
    // Default to today if not set (should be set earlier)
    $selectedDate = $selectedDate ?: date('Y-m-d');

    try {
        $conn->beginTransaction();

        foreach ($data as $row) {
            if ($row['status'] !== 'valid') {
                $skipped++;
                continue;
            }

            try {
                // Check which tables exist and have appropriate structure
                $tables_to_try = ['delivery_tracking', 'delivery_address'];
                $inserted = false;

                foreach ($tables_to_try as $table) {
                    try {
                        // Check if table exists and get its structure
                        $check_stmt = $conn->prepare("SHOW TABLES LIKE ?");
                        $check_stmt->execute([$table]);

                        if (!$check_stmt->fetch()) {
                            continue; // Table doesn't exist
                        }

                        // Get table columns
                        $columns_stmt = $conn->prepare("SHOW COLUMNS FROM `$table`");
                        $columns_stmt->execute();
                        $available_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                        if ($table === 'delivery_tracking') {
                            // Check if AWB already exists in delivery_tracking
                            $stmt = $conn->prepare("SELECT id_address FROM delivery_tracking WHERE AWB = ?");
                            $stmt->execute([$row['awb_number']]);

                            if ($stmt->fetch()) {
                                $skipped++;
                                $errors[] = "AWB {$row['awb_number']} มีอยู่แล้วในตาราง 'delivery_tracking'";
                                $inserted = true; // Skip but don't try other tables
                                break;
                            }

                            // Prepare dynamic SQL for delivery_tracking
                            $insert_columns = [];
                            $insert_values = [];
                            $insert_params = [];

                            // Optional fields - only add if column exists
                            // This now maps our standardized fields (from validateAndCleanData) to the database columns
                            $field_mappings = [
                                'AWB' => $row['awb_number'],
                                'ผู้รับ' => $row['recipient_name'],
                                'เบอร์มือถือผู้รับ' => $row['recipient_phone'],
                                'ที่อยู่ผู้รับ' => $row['recipient_address'],
                                'ชื่อเขต' => $row['zone_name'],
                                'รหัสเขต' => $row['zone_code'],
                                'ชื่อสาขานำจ่าย' => $row['delivery_branch'],
                                'COD' => floatval($row['cod_amount'] ?? 0),
                                'ชื่อแฟรนไชส์' => $row['franchise_name'],
                                'รหัสแฟรนไชส์' => $row['franchise_code'],
                                'เวลาเกทเวย์นำส่ง' => formatDateTime($row['gateway_time']),
                                'เวลาที่เร็วที่สุด' => formatDateTime($row['earliest_time']),
                                'เวลาที่พัสดุถึงสาขา' => formatDateTime($row['arrival_at_branch']),
                                'รหัสสาขานำจ่าย' => $row['delivery_branch_code'],
                                'เวลาที่นำจ่ายพัสดุ' => formatDateTime($row['delivery_time']),
                                'รหัสพนักงานนำจ่าย' => $row['delivery_staff_code'],
                                'ชื่อของพนักงานนำจ่าย' => $row['delivery_staff_name'],
                                '派件员岗位' => $row['delivery_staff_position'],
                                '派件员手机' => $row['delivery_staff_phone'],
                                'ช่วยนำจ่ายหรือไม่' => $row['helper_required'],
                                'เวลาพัสดุมีปัญหา' => formatDateTime($row['problem_time']),
                                'สาเหตุของพัสดุมีปัญหา' => $row['problem_reason'],
                                'เวลาที่เซ็นรับพัสดุ' => formatDateTime($row['sign_received_time']),
                                'สาขาเซ็นรับ' => $row['sign_received_branch'],
                                'เวลาการบันทึกเซ็นรับพัสดุ' => formatDateTime($row['sign_record_time']),
                                '(พนักงานนำจ่ายพัสดุ)เซ็นรับพัสดุ' => $row['sign_received_by_staff'],
                                'ผู้เซ็นรับ' => $row['sign_received_by'],
                                'เวลาลงทะเบียนพัสดุตีกลับ' => formatDateTime($row['return_register_time']),
                                'สาขาลงทะเบียนตีกลับ' => $row['return_register_branch'],
                                'ประเภทสาขา' => $row['branch_type'],
                                'แหล่งที่มาของออเดอร์' => $row['order_source'],
                                'ประเภทพัสดุ' => $row['parcel_type'],
                                'น้ำหนักที่ใช้คิดเงิน' => $row['weight_charged'],
                                'ประภทการชำระเงิน' => $row['payment_type'],
                                'ค่าขนส่งทั้งหมด' => $row['total_shipping_fee'],
                                'ผู้ส่ง' => $row['sender_name'],
                                'ลูกค้าผู้ส่ง' => $row['sender_customer'],
                                'เบอร์โทรผู้ส่ง' => $row['sender_phone'],
                                'สัญลักษณ์พัสดุตีกลับ' => $row['return_symbol'],
                                'ประเภทพัสดุ_1' => $row['parcel_type_1'],
                                'ประเภทค่าบริการเสริม' => $row['service_type_addon']
                            ];

                            foreach ($field_mappings as $column => $value) {
                                if (in_array($column, $available_columns)) {
                                    $insert_columns[] = $column;
                                    $insert_values[] = '?';
                                    $insert_params[] = $value;
                                }
                            }

                            // Add timestamps
                            // if (in_array('estimated_delivery_time', $available_columns)) {
                            //     $insert_columns[] = 'estimated_delivery_time';
                            //     $insert_values[] = 'CONCAT(?, \' 12:00:00\')';
                            //     $insert_params[] = $selectedDate;
                            // }

                            if (in_array('created_at', $available_columns)) {
                                $insert_columns[] = 'created_at';
                                $insert_values[] = 'NOW()';
                            }

                            if (!empty($insert_columns)) {
                                $sql = "INSERT INTO delivery_tracking (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
                            } else {
                                $sql = null; // Ensure no insert is attempted if no columns matched
                            }

                        } elseif ($table === 'delivery_address') {
                            // Check if AWB already exists in delivery_address
                            $stmt = $conn->prepare("SELECT id FROM delivery_address WHERE awb_number = ?");
                            $stmt->execute([$row['awb_number']]);

                            if ($stmt->fetch()) {
                                $skipped++;
                                $errors[] = "AWB {$row['awb_number']} มีอยู่แล้วในระบบ (delivery_address)";
                                $inserted = true;
                                break;
                            }

                            // Prepare dynamic SQL for delivery_address
                            $insert_columns = [];
                            $insert_values = [];
                            $insert_params = [];

                            $field_mappings = [
                                'awb_number' => $row['awb_number'],
                                'recipient_name' => $row['recipient_name'],
                                'recipient_phone' => $row['recipient_phone'],
                                'address' => $row['recipient_address'],
                                'province' => $row['province'],
                                'district' => $row['district'],
                                'subdistrict' => $row['sub_district'],
                                'postal_code' => $row['postal_code'],
                                'delivery_status' => 'pending',
                                'ชื่อของพนักงานนำจ่าย' => $row['delivery_staff_name']
                            ];

                            foreach ($field_mappings as $column => $value) {
                                if (in_array($column, $available_columns) && !empty($value)) {
                                    $insert_columns[] = $column;
                                    $insert_values[] = '?';
                                    $insert_params[] = $value;
                                }
                            }

                            if (in_array('created_at', $available_columns)) {
                                $insert_columns[] = 'created_at';
                                $insert_values[] = 'NOW()';
                            }

                            if (!empty($insert_columns)) {
                                $sql = "INSERT INTO delivery_address (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
                            } else {
                                $sql = null; // Ensure no insert is attempted if no columns matched
                            }
                        }

                        // Execute the insert
                        if (!empty($sql)) {
                            $stmt = $conn->prepare($sql);
                            $stmt->execute($insert_params);
                            $imported++;
                            if (!isset($imported_details[$table])) {
                                $imported_details[$table] = 0;
                            }
                            $imported_details[$table]++;
                            $inserted = true;
                            // break; // Successfully inserted, don't try other tables ถ้าอยากให้ลองทุกตาราง ให้เอา break ออก
                        }

                    } catch (PDOException $e) {
                        // Log the error but continue to try next table
                        error_log("Error inserting into $table: " . $e->getMessage());
                        continue;
                    }
                }

                if (!$inserted) {
                    $errors[] = "ไม่สามารถนำเข้า AWB {$row['awb_number']}: ไม่พบตารางที่เหมาะสม";
                    $skipped++;
                }

            } catch (Exception $e) {
                $errors[] = "AWB {$row['awb_number']}: " . $e->getMessage();
                $skipped++;
            }
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "ข้อผิดพลาดในการนำเข้า: " . $e->getMessage();
    }

    return [
        'imported' => $imported,
        'imported_details' => $imported_details,
        'skipped' => $skipped,
        'errors' => $errors,
        'total_processed' => count($data),
        'valid_count' => count(array_filter($data, function ($row) {
            return $row['status'] === 'valid'; })),
        'invalid_count' => count(array_filter($data, function ($row) {
            return $row['status'] === 'invalid'; }))
    ];
}
?>

<div class="fadeIn">
    <!-- Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-3xl font-bold mb-2 flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-import text-xl"></i>
                    </div>
                    นำเข้าข้อมูลการจัดส่ง
                </h1>
                <p class="text-gray-600 ml-4">อัพโหลดไฟล์ CSV/Excel เพื่อนำเข้าข้อมูลการจัดส่งเข้าสู่ระบบ</p>
            </div>
            <div class="hidden lg:block">
                <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-cloud-upload-alt text-4xl text-white/40"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($upload_result)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $upload_result; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($upload_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $upload_error; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($import_stats)): ?>
        <div class="bg-white border rounded-lg shadow-lg mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-4">
                <h3 class="font-bold text-lg">📊 ผลการนำเข้าข้อมูล</h3>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div class="text-center p-4 bg-red-50 rounded-xl border border-red-100 hover-lift">
                        <div class="text-2xl font-bold text-red-600"><?php echo $import_stats['total_processed'] ?? 0; ?>
                        </div>
                        <div class="text-sm text-red-600 font-medium">ประมวลผลทั้งหมด</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600"><?php echo $import_stats['imported']; ?></div>
                        <div class="text-sm text-green-600">นำเข้าสำเร็จ</div>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $import_stats['valid_count'] ?? 0; ?>
                        </div>
                        <div class="text-sm text-yellow-600">ข้อมูลถูกต้อง</div>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600"><?php echo $import_stats['skipped']; ?></div>
                        <div class="text-sm text-red-600">ข้ามไป/ผิดพลาด</div>
                    </div>
                </div>

                <!-- Summary Messages -->
                <?php if ($import_stats['imported'] > 0): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-3">
                        <i class="fas fa-check-circle mr-2"></i>
                        นำเข้าสำเร็จ: <strong><?php echo $import_stats['imported']; ?> รายการ</strong>
                    </div>
                <?php endif; ?>

                <?php if ($import_stats['skipped'] > 0): ?>
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        ข้ามไป: <strong><?php echo $import_stats['skipped']; ?> รายการ</strong>
                        <?php if (isset($import_stats['valid_count']) && $import_stats['valid_count'] == 0): ?>
                            <br><span class="text-sm">⚠️ ไม่มีข้อมูลที่ถูกต้อง - อาจเป็นปัญหาการตรวจจับคอลัมน์</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Error Details -->
                <?php if (!empty($import_stats['errors'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <details class="cursor-pointer">
                            <summary class="font-semibold">
                                <i class="fas fa-bug mr-2"></i>รายละเอียดข้อผิดพลาด
                                (<?php echo count($import_stats['errors']); ?> รายการ)
                            </summary>
                            <div class="mt-3 max-h-60 overflow-y-auto">
                                <ul class="space-y-1">
                                    <?php foreach (array_slice($import_stats['errors'], 0, 50) as $error): ?>
                                        <li class="text-sm p-2 bg-white rounded border-l-4 border-red-400">
                                            • <?php echo htmlspecialchars($error); ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (count($import_stats['errors']) > 50): ?>
                                        <li class="text-sm text-gray-600 italic">
                                            ... และอีก <?php echo count($import_stats['errors']) - 50; ?> รายการ
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>

                <!-- Debug Information -->
                <div class="mt-4 p-3 bg-gray-100 rounded text-sm">
                    <strong>ข้อมูลการประมวลผล:</strong>
                    <ul class="mt-2 space-y-1">
                        <li>• ข้อมูลทั้งหมด: <?php echo $import_stats['total_processed'] ?? 0; ?> รายการ</li>
                        <li>• ข้อมูลถูกต้อง: <?php echo $import_stats['valid_count'] ?? 0; ?> รายการ</li>
                        <li>• ข้อมูลไม่ถูกต้อง: <?php echo $import_stats['invalid_count'] ?? 0; ?> รายการ</li>
                        <li>• นำเข้าสำเร็จ: <?php echo $import_stats['imported']; ?> รายการ</li>
                        <li>• ข้ามไป: <?php echo $import_stats['skipped']; ?> รายการ</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upload Section -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-upload text-blue-600 mr-2"></i>อัพโหลดไฟล์
            </h2>

            <!-- Select delivery date first -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="set_date">
                <label class="block text-sm font-medium text-gray-700 mb-2">เลือกวันที่จัดส่ง</label>
                <div class="flex gap-3">
                    <input type="date" name="delivery_date"
                        value="<?php echo htmlspecialchars($_SESSION['import_delivery_date'] ?: ''); ?>" required
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit"
                        class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-900 whitespace-nowrap">บันทึกวันที่</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">ต้องเลือกวันที่ก่อนจึงจะอัพโหลดไฟล์ได้</p>
            </form>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลือกไฟล์ CSV/Excel</label>
                    <input type="file" name="csv_file" accept=".csv,.xlsx,.xls" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">รองรับไฟล์: CSV, XLSX, XLS (แนะนำ CSV)</p>
                </div>

                <button type="submit" class="bg-gray-800 text-white rounded-md hover:bg-gray-900 px-4 py-2 w-full">
                    อัพโหลดและตรวจสอบข้อมูล
                </button>
            </form>
        </div>

        <!-- Instructions -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-info-circle text-green-600 mr-2"></i>รูปแบบไฟล์ที่ต้องการ
            </h2>

            <div class="space-y-3">
                <p class="text-sm text-gray-600">ไฟล์ CSV ควรมีคอลัมน์ดังนี้:</p>

                <div class="bg-gray-50 p-3 rounded text-sm font-mono">
                    <div class="grid grid-cols-2 gap-2">
                        <div><strong>AWB</strong> (บังคับ)</div>
                        <div><strong>ชื่อผู้รับ</strong> (บังคับ)</div>
                        <div><strong>เบอร์โทร</strong> (บังคับ)</div>
                        <div><strong>ที่อยู่ผู้รับ</strong> (บังคับ)</div>
                        <div>จังหวัด</div>
                        <div>อำเภอ</div>
                        <div>ตำบล</div>
                        <div>รหัสไปรษณีย์</div>
                        <div>COD</div>
                        <div>ประเภทบริการ</div>
                        <div>ความสำคัญ</div>
                    </div>
                </div>

                <div class="border-l-4 border-yellow-400 pl-4">
                    <p class="text-sm text-yellow-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        คอลัมน์ที่มี <strong>(บังคับ)</strong> จำเป็นต้องมีข้อมูล
                    </p>
                </div>

                <div class="mt-4">
                    <a href="../sample_data/sample_deliveries.csv"
                        class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm"
                        download="sample_deliveries.csv">
                        <i class="fas fa-download mr-2"></i>ดาวน์โหลดไฟล์ตัวอย่าง
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section -->
    <?php if (!empty($preview_data)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mt-6">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-eye text-purple-600 mr-2"></i>ตรวจสอบข้อมูลก่อนนำเข้า
            </h2>

            <?php
            $valid_count = count(array_filter($preview_data, function ($row) {
                return $row['status'] === 'valid'; }));
            $invalid_count = count($preview_data) - $valid_count;

            // Get column mapping info from first row
            $column_mapping = [];
            if (!empty($preview_data)) {
                $column_mapping = $preview_data[0]['detected_columns'] ?? [];
            }
            ?>

            <!-- Column Detection Info -->
            <?php if (!empty($column_mapping)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                    <h3 class="text-red-800 font-semibold mb-2">
                        <i class="fas fa-search mr-2"></i>การตรวจจับคอลัมน์อัตโนมัติ
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
                        <?php foreach ($column_mapping as $field => $detected_column): ?>
                            <div class="bg-white p-2 rounded border">
                                <strong><?php echo $field; ?>:</strong>
                                <span class="text-blue-600"><?php echo htmlspecialchars($detected_column); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($column_mapping['awb_number']) || empty($column_mapping['recipient_name'])): ?>
                        <div class="mt-3 p-3 bg-yellow-100 border border-yellow-300 rounded">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <strong>คำเตือน:</strong> ไม่พบคอลัมน์ที่จำเป็น (AWB หรือ ชื่อผู้รับ) ข้อมูลอาจไม่ถูกต้อง
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-red-50 p-4 rounded-xl border border-red-100">
                    <div class="text-red-600 font-bold text-2xl"><?php echo count($preview_data); ?></div>
                    <div class="text-red-600 text-sm font-medium">รายการทั้งหมด</div>
                </div>
                <div class="bg-green-50 p-4 rounded">
                    <div class="text-green-600 font-bold text-2xl"><?php echo $valid_count; ?></div>
                    <div class="text-green-600 text-sm">ข้อมูลถูกต้อง</div>
                </div>
                <div class="bg-red-50 p-4 rounded">
                    <div class="text-red-600 font-bold text-2xl"><?php echo $invalid_count; ?></div>
                    <div class="text-red-600 text-sm">ข้อมูลผิดพลาด</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">แถว
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AWB
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ชื่อผู้รับ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                เบอร์โทร</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                จังหวัด</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($preview_data, 0, 10) as $row): ?>
                            <tr class="<?php echo $row['status'] === 'valid' ? 'bg-green-50' : 'bg-red-50'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['row_number']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['awb_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['recipient_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['recipient_phone']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['province']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($row['status'] === 'valid'): ?>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>ถูกต้อง
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i>ผิดพลาด
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($preview_data) > 10): ?>
                <p class="text-sm text-gray-500 mt-2">แสดง 10 รายการแรก จากทั้งหมด <?php echo count($preview_data); ?> รายการ
                </p>
            <?php endif; ?>

            <?php if ($valid_count > 0): ?>
                <form method="POST" class="mt-6">
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="import_data" value="<?php echo htmlspecialchars(json_encode($preview_data)); ?>">

                    <button type="submit"
                        class="bg-green-600 text-white py-2 px-6 rounded-md hover:bg-green-700 transition-colors"
                        onclick="return confirm('คุณต้องการนำเข้าข้อมูล <?php echo $valid_count; ?> รายการในวันที่ <?php echo htmlspecialchars($_SESSION['import_delivery_date'] ?: date('Y-m-d')); ?> ใช่หรือไม่?')">
                        <i class="fas fa-database mr-2"></i>นำเข้าข้อมูลทั้งหมด (<?php echo $valid_count; ?> รายการ)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed top-4 right-4 p-4 rounded-md z-50 ${type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' :
                'bg-red-100 text-red-800 border border-red-400'
            }`;
        alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-2"></i>
            ${message}
        </div>
    `;

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Show success message if import was successful
    <?php if (!empty($import_stats) && $import_stats['imported'] > 0): ?>
        showAlert('นำเข้าข้อมูลสำเร็จ: <?php echo $import_stats['imported']; ?> รายการ', 'success');
    <?php endif; ?>

    // --- Console Logging ---
    (function() {
        console.log("--- Import Page Diagnostics ---");

        <?php if (!empty($upload_error)): ?>
        console.error("Upload Error:", <?php echo json_encode($upload_error, JSON_UNESCAPED_UNICODE); ?>);
        <?php elseif (!empty($upload_result)): ?>
        console.log("Upload Success:", <?php echo json_encode($upload_result, JSON_UNESCAPED_UNICODE); ?>);
        <?php endif; ?>

        <?php if (!empty($preview_data)): ?>
        console.log("--- Data Preview ---");
        console.log("Data preview generated. Total rows: <?php echo count($preview_data); ?>");
        const validRows = <?php echo json_encode(array_filter($preview_data, function($row) { return $row['status'] === 'valid'; }), JSON_UNESCAPED_UNICODE); ?>;
        const invalidRows = <?php echo json_encode(array_filter($preview_data, function($row) { return $row['status'] === 'invalid'; }), JSON_UNESCAPED_UNICODE); ?>;
        console.log(`Valid rows: ${validRows.length}`);
        console.log(`Invalid rows: ${invalidRows.length}`);
        if (invalidRows.length > 0) {
            console.warn("Invalid rows found:", invalidRows);
        }
        console.log("--- End Data Preview ---");
        <?php endif; ?>

        <?php if (!empty($import_stats)): ?>
        console.log("--- Import Process Finished ---");
        const stats = <?php echo json_encode($import_stats, JSON_UNESCAPED_UNICODE); ?>;
        console.log("Import statistics:", stats);
        if (stats.imported_details) {
            for (const [table, count] of Object.entries(stats.imported_details)) {
                console.log(` -> Successfully imported ${count} rows into '${table}' table.`);
            }
        }
        if (stats.errors && stats.errors.length > 0) {
            console.error("Import errors occurred:", stats.errors);
        }
        console.log("--- End Import Process ---");
        <?php endif; ?>
    })();
</script>

<?php include '../includes/footer.php'; ?>