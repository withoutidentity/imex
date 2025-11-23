<?php
/**
 * OpenStreetMap Nominatim Geocoder
 * ทางเลือกของ Google Maps API สำหรับ geocoding
 */

require_once 'address_parser.php';

class NominatimGeocoder {
    
    private $base_url = 'https://nominatim.openstreetmap.org';
    private $user_agent = 'IMEX-Delivery-System/1.0';
    private $rate_limit_delay = 1000000; // 1 second (microseconds)
    private $timeout = 30;
    
    /**
     * ค้นหาพิกัดจากที่อยู่
     */
    public function geocode($address, $options = []) {
        $default_options = [
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => 1,
            'countrycodes' => 'th', // จำกัดเฉพาะประเทศไทย
            'accept-language' => 'th,en'
        ];
        
        $options = array_merge($default_options, $options);
        
        // ใช้ Thai Address Parser เพื่อปรับปรุงการค้นหา
        $prepared = prepareAddressForGeocoding($address);
        $search_results = [];
        
        // ลองค้นหาหลายรูปแบบ
        foreach ($prepared['search_queries'] as $query_type => $query) {
            $result = $this->performGeocoding($query, $options);
            
            if ($result['success']) {
                $result['query_type'] = $query_type;
                $result['parsed_address'] = $prepared['parsed'];
                $result['validation'] = $prepared['validation'];
                return $result;
            }
            
            $search_results[] = $result;
            
            // Rate limiting
            usleep($this->rate_limit_delay);
        }
        
        // ถ้าไม่พบผลลัพธ์ ให้ลองค้นหาแบบ fallback
        return $this->fallbackGeocoding($address, $prepared, $options);
    }
    
    /**
     * ทำการ geocoding จริง
     */
    private function performGeocoding($query, $options) {
        try {
            $url = $this->buildUrl($query, $options);
            $response = $this->makeRequest($url);
            
            if (!$response) {
                return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อ Nominatim API ได้'];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'ข้อมูลที่ได้รับไม่ถูกต้อง'];
            }
            
            if (empty($data)) {
                return ['success' => false, 'error' => 'ไม่พบที่อยู่ที่ระบุ'];
            }
            
            return $this->processResult($data[0], $query);
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    
    /**
     * สร้าง URL สำหรับ Nominatim API
     */
    private function buildUrl($query, $options) {
        $params = array_merge($options, ['q' => $query]);
        return $this->base_url . '/search?' . http_build_query($params);
    }
    
    /**
     * ส่ง HTTP Request
     */
    private function makeRequest($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . $this->user_agent,
                    'Accept: application/json'
                ],
                'timeout' => $this->timeout
            ]
        ]);
        
        return file_get_contents($url, false, $context);
    }
    
    /**
     * ประมวลผลผลลัพธ์
     */
    private function processResult($result, $query) {
        $address_parts = $result['address'] ?? [];
        
        return [
            'success' => true,
            'lat' => (float) $result['lat'],
            'lng' => (float) $result['lon'],
            'formatted_address' => $result['display_name'],
            'accuracy' => $this->calculateAccuracy($result),
            'confidence' => $this->calculateConfidence($result, $query),
            'osm_id' => $result['osm_id'] ?? null,
            'osm_type' => $result['osm_type'] ?? null,
            'place_id' => $result['place_id'] ?? null,
            'category' => $result['category'] ?? null,
            'type' => $result['type'] ?? null,
            'address_components' => [
                'house_number' => $address_parts['house_number'] ?? '',
                'road' => $address_parts['road'] ?? '',
                'suburb' => $address_parts['suburb'] ?? '',
                'subdistrict' => $address_parts['suburb'] ?? $address_parts['village'] ?? '',
                'district' => $address_parts['city_district'] ?? $address_parts['county'] ?? '',
                'city' => $address_parts['city'] ?? $address_parts['town'] ?? '',
                'province' => $address_parts['state'] ?? '',
                'country' => $address_parts['country'] ?? '',
                'postcode' => $address_parts['postcode'] ?? ''
            ],
            'bounding_box' => [
                'min_lat' => (float) $result['boundingbox'][0],
                'max_lat' => (float) $result['boundingbox'][1], 
                'min_lng' => (float) $result['boundingbox'][2],
                'max_lng' => (float) $result['boundingbox'][3]
            ]
        ];
    }
    
    /**
     * คำนวณความแม่นยำ
     */
    private function calculateAccuracy($result) {
        $type = $result['type'] ?? '';
        
        $accuracy_map = [
            'house' => 'high',
            'building' => 'high', 
            'residential' => 'medium',
            'road' => 'medium',
            'suburb' => 'low',
            'city_district' => 'low',
            'city' => 'very_low',
            'administrative' => 'very_low'
        ];
        
        return $accuracy_map[$type] ?? 'unknown';
    }
    
    /**
     * คำนวณความเชื่อมั่น
     */
    private function calculateConfidence($result, $query) {
        $importance = $result['importance'] ?? 0;
        $score = $importance * 100;
        
        // ปรับคะแนนตามการจับคู่คำสำคัญ
        $query_words = explode(' ', strtolower($query));
        $display_name = strtolower($result['display_name']);
        
        $matches = 0;
        foreach ($query_words as $word) {
            if (stripos($display_name, $word) !== false) {
                $matches++;
            }
        }
        
        $match_ratio = count($query_words) > 0 ? $matches / count($query_words) : 0;
        $score += $match_ratio * 20; // เพิ่มคะแนนจากการจับคู่
        
        return min(100, max(0, $score));
    }
    
    /**
     * การค้นหา fallback เมื่อไม่พบผลลัพธ์
     */
    private function fallbackGeocoding($original_address, $prepared, $options) {
        // ลองค้นหาเฉพาะชื่อเมือง/จังหวัด
        $province = $prepared['parsed']['province'];
        $district = $prepared['parsed']['district'];
        
        if (!empty($province)) {
            $fallback_query = !empty($district) ? "$district $province" : $province;
            
            $result = $this->performGeocoding($fallback_query, $options);
            
            if ($result['success']) {
                $result['is_fallback'] = true;
                $result['fallback_type'] = 'province_level';
                $result['accuracy'] = 'very_low';
                $result['confidence'] = max(0, $result['confidence'] - 30);
                return $result;
            }
        }
        
        return [
            'success' => false,
            'error' => 'ไม่สามารถหาพิกัดของที่อยู่ได้',
            'attempted_queries' => $prepared['search_queries'],
            'parsed_address' => $prepared['parsed']
        ];
    }
    
    /**
     * ค้นหาพิกัดแบบ batch
     */
    public function batchGeocode($addresses, $callback = null) {
        $results = [];
        $total = count($addresses);
        
        foreach ($addresses as $index => $address) {
            $result = $this->geocode($address);
            $results[] = $result;
            
            // เรียก callback function ถ้ามี
            if ($callback && is_callable($callback)) {
                $callback($index + 1, $total, $result);
            }
            
            // Rate limiting
            usleep($this->rate_limit_delay);
        }
        
        return $results;
    }
    
    /**
     * Reverse geocoding - หาที่อยู่จากพิกัด
     */
    public function reverseGeocode($lat, $lng, $options = []) {
        $default_options = [
            'format' => 'json',
            'addressdetails' => 1,
            'zoom' => 18,
            'accept-language' => 'th,en'
        ];
        
        $options = array_merge($default_options, $options);
        
        try {
            $url = $this->base_url . '/reverse?' . http_build_query(array_merge($options, [
                'lat' => $lat,
                'lon' => $lng
            ]));
            
            $response = $this->makeRequest($url);
            
            if (!$response) {
                return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อ Nominatim API ได้'];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
                return ['success' => false, 'error' => 'ไม่พบข้อมูลที่อยู่สำหรับพิกัดนี้'];
            }
            
            return $this->processResult($data, "Reverse geocoding: $lat, $lng");
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    
    /**
     * ค้นหาสถานที่ใกล้เคียง
     */
    public function findNearby($lat, $lng, $radius = 1000, $options = []) {
        // Nominatim ไม่มี nearby search โดยตรง
        // ใช้ bounding box แทน
        $lat_offset = $radius / 111000; // แปลงเมตรเป็นองศา (ประมาณ)
        $lng_offset = $radius / (111000 * cos(deg2rad($lat)));
        
        $bbox = [
            $lng - $lng_offset, // left
            $lat - $lat_offset, // bottom  
            $lng + $lng_offset, // right
            $lat + $lat_offset  // top
        ];
        
        $default_options = [
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => 10,
            'bounded' => 1,
            'viewbox' => implode(',', $bbox)
        ];
        
        $options = array_merge($default_options, $options);
        
        return $this->performGeocoding('*', $options);
    }
    
    /**
     * ตั้งค่า User Agent
     */
    public function setUserAgent($user_agent) {
        $this->user_agent = $user_agent;
    }
    
    /**
     * ตั้งค่า Rate Limit Delay
     */
    public function setRateLimit($microseconds) {
        $this->rate_limit_delay = $microseconds;
    }
    
    /**
     * ตั้งค่า Timeout
     */
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }
}

/**
 * Helper function สำหรับใช้งานง่าย
 */
function nominatimGeocode($address) {
    $geocoder = new NominatimGeocoder();
    return $geocoder->geocode($address);
}

/**
 * ฟังก์ชัน geocoding แบบ hybrid (ลอง Nominatim ก่อน แล้วค่อย Google Maps)
 */
function hybridGeocode($address) {
    // ลอง Nominatim ก่อน (ฟรี)
    $nominatim_result = nominatimGeocode($address);
    
    if ($nominatim_result['success'] && $nominatim_result['confidence'] > 70) {
        $nominatim_result['source'] = 'nominatim';
        return $nominatim_result;
    }
    
    // ถ้า Nominatim ไม่ได้ผล หรือมีความเชื่อมั่นต่ำ ให้ลอง Google Maps
    if (function_exists('geocodeAddress')) {
        $google_result = geocodeAddress($address);
        
        if ($google_result['success']) {
            $google_result['source'] = 'google_maps';
            return $google_result;
        }
    }
    
    // ถ้าทั้งคู่ไม่ได้ผล ให้ return Nominatim result (มีข้อมูล debug มากกว่า)
    $nominatim_result['source'] = 'nominatim_fallback';
    return $nominatim_result;
}
?> 