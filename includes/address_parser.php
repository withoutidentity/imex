<?php
/**
 * Thai Address Parser Library
 * สำหรับแยกส่วนประกอบของที่อยู่ภาษาไทย
 */

class ThaiAddressParser {
    
    private $subdistrict_patterns = [
        'ตำบล', 'ต\.', 'อ\.', 'แขวง', 'เขต', 'อำเภอ'
    ];
    
    private $road_patterns = [
        'ถนน', 'ถ\.', 'Road', 'Rd\.', 'เส้น'
    ];
    
    private $soi_patterns = [
        'ซอย', 'ซ\.', 'Soi', 'ตรอก', 'ทางเข้า'
    ];
    
    private $moo_patterns = [
        'หมู่', 'หมู่ที่', 'ม\.', 'บ้านเลขที่', 'เลขที่'
    ];
    
    private $building_patterns = [
        'อาคาร', 'ตึก', 'โครงการ', 'คอนโด', 'หมู่บ้าน', 'วิลเลจ', 'ศูนย์', 'มอลล์', 'plaza', 'center'
    ];
    
    /**
     * แยกส่วนประกอบหลักของที่อยู่
     */
    public function parseAddress($address) {
        $address = $this->cleanAddress($address);
        
        $result = [
            'original_address' => $address,
            'house_number' => $this->extractHouseNumber($address),
            'building' => $this->extractBuilding($address),
            'soi' => $this->extractSoi($address),
            'road' => $this->extractRoad($address),
            'moo' => $this->extractMoo($address),
            'subdistrict' => $this->extractSubdistrict($address),
            'district' => $this->extractDistrict($address),
            'province' => $this->extractProvince($address),
            'postal_code' => $this->extractPostalCode($address),
            'keywords' => $this->extractKeywords($address),
            'formatted_search' => ''
        ];
        
        $result['formatted_search'] = $this->formatForSearch($result);
        
        return $result;
    }
    
    /**
     * ทำความสะอาดที่อยู่
     */
    private function cleanAddress($address) {
        // ลบอักขระพิเศษและช่องว่างซ้ำ
        $address = preg_replace('/\s+/', ' ', $address);
        $address = trim($address);
        
        // แปลงตัวเลขไทยเป็นอารบิก
        $thai_numbers = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
        $arabic_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $address = str_replace($thai_numbers, $arabic_numbers, $address);
        
        return $address;
    }
    
    /**
     * ดึงเลขที่บ้าน
     */
    private function extractHouseNumber($address) {
        // Pattern สำหรับเลขที่บ้าน
        $patterns = [
            '/^(\d+\/?\d*)\s/',
            '/เลขที่\s*(\d+\/?\d*)/',
            '/บ้านเลขที่\s*(\d+\/?\d*)/',
            '/ที่อยู่\s*(\d+\/?\d*)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return '';
    }
    
    /**
     * ดึงชื่ออาคาร/โครงการ
     */
    private function extractBuilding($address) {
        $building_pattern = '(' . implode('|', $this->building_patterns) . ')[\s\u{0E00}-\u{0E7F}\w\s]*';
        
        if (preg_match('/' . $building_pattern . '/iu', $address, $matches)) {
            return trim($matches[0]);
        }
        
        return '';
    }
    
    /**
     * ดึงซอย
     */
    private function extractSoi($address) {
        $soi_pattern = '(' . implode('|', $this->soi_patterns) . ')[\s]*([^\s\u{0E00}-\u{0E7F}\w\s]*[\u{0E00}-\u{0E7F}\w\s]*)';
        
        if (preg_match('/' . $soi_pattern . '/iu', $address, $matches)) {
            return trim($matches[0]);
        }
        
        return '';
    }
    
    /**
     * ดึงถนน
     */
    private function extractRoad($address) {
        $road_pattern = '(' . implode('|', $this->road_patterns) . ')[\s]*([^\s]*[\u{0E00}-\u{0E7F}\w\s]*)';
        
        if (preg_match('/' . $road_pattern . '/iu', $address, $matches)) {
            // ตัดส่วนที่อาจเป็น แขวง/เขต ออก
            $road = trim($matches[0]);
            $road = preg_replace('/\s*(แขวง|เขต|ตำบล|อำเภอ|จังหวัด).*$/iu', '', $road);
            return trim($road);
        }
        
        return '';
    }
    
    /**
     * ดึงหมู่
     */
    private function extractMoo($address) {
        $moo_pattern = '(' . implode('|', $this->moo_patterns) . ')[\s]*(\d+)';
        
        if (preg_match('/' . $moo_pattern . '/iu', $address, $matches)) {
            return trim($matches[0]);
        }
        
        return '';
    }
    
    /**
     * ดึงตำบล/แขวง
     */
    private function extractSubdistrict($address) {
        $patterns = [
            '/(แขวง|ตำบล|ต\.)[\s]*([^\s]*[\u{0E00}-\u{0E7F}\w]*)/iu',
            '/\s([^\s]*[\u{0E00}-\u{0E7F}]+)\s*(เขต|อำเภอ|อ\.)/iu'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                return trim($matches[2] ?? $matches[1]);
            }
        }
        
        return '';
    }
    
    /**
     * ดึงอำเภอ/เขต
     */
    private function extractDistrict($address) {
        $patterns = [
            '/(เขต|อำเภอ|อ\.)[\s]*([^\s]*[\u{0E00}-\u{0E7F}\w]*)/iu',
            '/\s([^\s]*[\u{0E00}-\u{0E7F}]+)\s*(จังหวัด|กรุงเทพ)/iu'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                return trim($matches[2] ?? $matches[1]);
            }
        }
        
        return '';
    }
    
    /**
     * ดึงจังหวัด
     */
    private function extractProvince($address) {
        $patterns = [
            '/(จังหวัด|จ\.)[\s]*([^\s]*[\u{0E00}-\u{0E7F}\w]*)/iu',
            '/(กรุงเทพมหานคร|กรุงเทพ|Bangkok)/iu',
            '/\s([^\s]*[\u{0E00}-\u{0E7F}]+)\s*(\d{5})/iu'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                $province = trim($matches[2] ?? $matches[1]);
                // กรณีพิเศษสำหรับกรุงเทพ
                if (in_array(strtolower($province), ['กรุงเทพมหานคร', 'กรุงเทพ', 'bangkok'])) {
                    return 'กรุงเทพมหานคร';
                }
                return $province;
            }
        }
        
        return '';
    }
    
    /**
     * ดึงรหัสไปรษณีย์
     */
    private function extractPostalCode($address) {
        if (preg_match('/(\d{5})/', $address, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * ดึงคำสำคัญเพิ่มเติม
     */
    private function extractKeywords($address) {
        $keywords = [];
        
        // สถานที่สำคัญ
        $landmarks = [
            'สถานีรถไฟฟ้า', 'BTS', 'MRT', 'สถานี', 'โรงพยาบาล', 'โรงเรียน', 
            'มหาวิทยาลัย', 'ตลาด', 'ห้างสรรพสินค้า', 'เซ็นทรัล', 'บิ๊กซี', 
            'เทสโก้', 'แม็คโคร', 'วัด', 'โบสถ์', 'มัสยิด'
        ];
        
        foreach ($landmarks as $landmark) {
            if (stripos($address, $landmark) !== false) {
                $keywords[] = $landmark;
            }
        }
        
        return $keywords;
    }
    
    /**
     * จัดรูปแบบสำหรับการค้นหา
     */
    private function formatForSearch($parsed) {
        $search_parts = [];
        
        // เรียงลำดับตามความสำคัญ
        if (!empty($parsed['road'])) $search_parts[] = $parsed['road'];
        if (!empty($parsed['soi'])) $search_parts[] = $parsed['soi'];
        if (!empty($parsed['subdistrict'])) $search_parts[] = $parsed['subdistrict'];
        if (!empty($parsed['district'])) $search_parts[] = $parsed['district'];
        if (!empty($parsed['province'])) $search_parts[] = $parsed['province'];
        
        return implode(' ', $search_parts);
    }
    
    /**
     * แยกที่อยู่หลายรูปแบบ
     */
    public function parseMultipleFormats($address) {
        $formats = [
            'standard' => $this->parseAddress($address),
            'keywords_only' => $this->extractMainKeywords($address),
            'reverse_order' => $this->parseReverseOrder($address)
        ];
        
        return $formats;
    }
    
    /**
     * ดึงคำสำคัญหลักเท่านั้น
     */
    private function extractMainKeywords($address) {
        $road = $this->extractRoad($address);
        $district = $this->extractDistrict($address);
        $province = $this->extractProvince($address);
        
        return implode(' ', array_filter([$road, $district, $province]));
    }
    
    /**
     * แยกที่อยู่แบบย้อนกลับ (จากจังหวัดมาหาที่อยู่)
     */
    private function parseReverseOrder($address) {
        $province = $this->extractProvince($address);
        $district = $this->extractDistrict($address);
        $subdistrict = $this->extractSubdistrict($address);
        $road = $this->extractRoad($address);
        
        return implode(' ', array_filter([$province, $district, $subdistrict, $road]));
    }
    
    /**
     * ตรวจสอบคุณภาพการแยกที่อยู่
     */
    public function validateParsing($parsed) {
        $score = 0;
        $max_score = 6;
        
        if (!empty($parsed['province'])) $score++;
        if (!empty($parsed['district'])) $score++;
        if (!empty($parsed['subdistrict'])) $score++;
        if (!empty($parsed['road'])) $score++;
        if (!empty($parsed['house_number'])) $score++;
        if (!empty($parsed['postal_code'])) $score++;
        
        return [
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => ($score / $max_score) * 100,
            'quality' => $this->getQualityLabel($score / $max_score)
        ];
    }
    
    private function getQualityLabel($ratio) {
        if ($ratio >= 0.8) return 'excellent';
        if ($ratio >= 0.6) return 'good';
        if ($ratio >= 0.4) return 'fair';
        return 'poor';
    }
}

/**
 * Helper function สำหรับใช้งานง่าย
 */
function parseThaiAddress($address) {
    $parser = new ThaiAddressParser();
    return $parser->parseAddress($address);
}

/**
 * ฟังก์ชันตรวจสอบและทำความสะอาดที่อยู่ก่อน geocoding
 */
function prepareAddressForGeocoding($address) {
    $parser = new ThaiAddressParser();
    $parsed = $parser->parseAddress($address);
    
    // สร้างที่อยู่ที่เหมาะสำหรับ geocoding
    $search_queries = [
        'full' => $parsed['formatted_search'] . ' ' . $parsed['province'],
        'main' => $parsed['road'] . ' ' . $parsed['district'] . ' ' . $parsed['province'],
        'minimal' => $parsed['district'] . ' ' . $parsed['province'],
        'keywords' => implode(' ', $parsed['keywords']) . ' ' . $parsed['province']
    ];
    
    return [
        'parsed' => $parsed,
        'search_queries' => array_filter($search_queries),
        'validation' => $parser->validateParsing($parsed)
    ];
}
?> 