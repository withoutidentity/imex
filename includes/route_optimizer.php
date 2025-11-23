<?php
/**
 * Route Optimization Library
 * ระบบคำนวณเส้นทางที่เหมาะสมสำหรับการจัดส่ง
 * ใช้ TSP (Traveling Salesman Problem) algorithms
 */

class RouteOptimizer {
    
    private $depot_lat;
    private $depot_lng;
    private $max_distance = 50; // กิโลเมตร
    private $max_stops = 30;
    private $vehicle_speed = 40; // กม./ชั่วโมง
    private $stop_time = 5; // นาทีต่อจุด
    
    public function __construct($depot_lat = 13.7563, $depot_lng = 100.5018) {
        $this->depot_lat = $depot_lat;
        $this->depot_lng = $depot_lng;
    }
    
    /**
     * คำนวณเส้นทางที่เหมาะสมสำหรับรายการจัดส่ง
     */
    public function optimizeRoute($deliveries, $algorithm = 'nearest_neighbor') {
        if (empty($deliveries)) {
            return ['success' => false, 'error' => 'ไม่มีข้อมูลการจัดส่ง'];
        }
        
        // เตรียมข้อมูลจุด
        $points = $this->preparePoints($deliveries);
        
        if (count($points) === 0) {
            return ['success' => false, 'error' => 'ไม่มีจุดที่มีพิกัดถูกต้อง'];
        }
        
        // คำนวณระยะทางระหว่างจุดทั้งหมด
        $distance_matrix = $this->calculateDistanceMatrix($points);
        
        // เลือกอัลกอริทึม
        switch ($algorithm) {
            case 'genetic':
                $route = $this->geneticAlgorithm($points, $distance_matrix);
                break;
            case 'simulated_annealing':
                $route = $this->simulatedAnnealing($points, $distance_matrix);
                break;
            case 'two_opt':
                $route = $this->twoOptAlgorithm($points, $distance_matrix);
                break;
            default:
                $route = $this->nearestNeighborAlgorithm($points, $distance_matrix);
        }
        
        // คำนวณสถิติเส้นทาง
        $stats = $this->calculateRouteStatistics($route, $distance_matrix);
        
        return [
            'success' => true,
            'route' => $route,
            'statistics' => $stats,
            'algorithm' => $algorithm,
            'distance_matrix' => $distance_matrix
        ];
    }
    
    /**
     * เตรียมข้อมูลจุดสำหรับการคำนวณ
     */
    private function preparePoints($deliveries) {
        $points = [];
        
        // เพิ่มจุดเริ่มต้น (depot)
        $points[] = [
            'id' => 'depot',
            'type' => 'depot',
            'lat' => $this->depot_lat,
            'lng' => $this->depot_lng,
            'name' => 'จุดเริ่มต้น',
            'priority' => 0
        ];
        
        // เพิ่มจุดจัดส่ง
        foreach ($deliveries as $delivery) {
            if (empty($delivery['latitude']) || empty($delivery['longitude'])) {
                continue;
            }
            
            $lat = floatval($delivery['latitude']);
            $lng = floatval($delivery['longitude']);
            
            // ตรวจสอบระยะทางจากจุดเริ่มต้น
            $distance_from_depot = $this->calculateDistance($this->depot_lat, $this->depot_lng, $lat, $lng);
            
            if ($distance_from_depot > $this->max_distance) {
                continue;
            }
            
            $points[] = [
                'id' => $delivery['id'],
                'type' => 'delivery',
                'lat' => $lat,
                'lng' => $lng,
                'name' => $delivery['recipient_name'],
                'address' => $delivery['address'],
                'awb_number' => $delivery['awb_number'],
                'phone' => $delivery['recipient_phone'] ?? '',
                'priority' => $this->calculatePriority($delivery),
                'delivery_data' => $delivery
            ];
        }
        
        // จำกัดจำนวนจุด
        if (count($points) > $this->max_stops + 1) {
            // เรียงตามความสำคัญและระยะทาง
            $delivery_points = array_slice($points, 1); // ไม่รวม depot
            usort($delivery_points, function($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
            
            $points = array_merge([$points[0]], array_slice($delivery_points, 0, $this->max_stops));
        }
        
        return $points;
    }
    
    /**
     * คำนวณความสำคัญของการจัดส่ง
     */
    private function calculatePriority($delivery) {
        $priority = 50; // ค่าเริ่มต้น
        
        // ปรับตามความเชื่อมั่นของพิกัด
        $confidence = floatval($delivery['geocoding_confidence'] ?? 0);
        $priority += $confidence * 0.2;
        
        // ปรับตามสถานะ
        switch ($delivery['delivery_status'] ?? 'pending') {
            case 'assigned':
                $priority += 20;
                break;
            case 'in_transit':
                $priority += 30;
                break;
        }
        
        // ปรับตามคุณภาพการแยกที่อยู่
        switch ($delivery['parsing_quality'] ?? 'fair') {
            case 'excellent':
                $priority += 15;
                break;
            case 'good':
                $priority += 10;
                break;
            case 'fair':
                $priority += 5;
                break;
        }
        
        return $priority;
    }
    
    /**
     * คำนวณระยะทางระหว่างจุดทั้งหมด
     */
    private function calculateDistanceMatrix($points) {
        $matrix = [];
        $count = count($points);
        
        for ($i = 0; $i < $count; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 0;
                } else {
                    $matrix[$i][$j] = $this->calculateDistance(
                        $points[$i]['lat'], $points[$i]['lng'],
                        $points[$j]['lat'], $points[$j]['lng']
                    );
                }
            }
        }
        
        return $matrix;
    }
    
    /**
     * คำนวณระยะทางระหว่าง 2 จุด (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371; // กิโลเมตร
        
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        
        $a = sin($dlat/2) * sin($dlat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng/2) * sin($dlng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earth_radius * $c;
    }
    
    /**
     * Nearest Neighbor Algorithm
     */
    private function nearestNeighborAlgorithm($points, $distance_matrix) {
        $num_points = count($points);
        $visited = array_fill(0, $num_points, false);
        $route = [0]; // เริ่มจาก depot
        $visited[0] = true;
        $current = 0;
        
        for ($i = 1; $i < $num_points; $i++) {
            $nearest = -1;
            $min_distance = PHP_FLOAT_MAX;
            
            for ($j = 0; $j < $num_points; $j++) {
                if (!$visited[$j] && $distance_matrix[$current][$j] < $min_distance) {
                    $min_distance = $distance_matrix[$current][$j];
                    $nearest = $j;
                }
            }
            
            if ($nearest !== -1) {
                $route[] = $nearest;
                $visited[$nearest] = true;
                $current = $nearest;
            }
        }
        
        // กลับไปที่ depot
        $route[] = 0;
        
        return $this->buildRouteResult($points, $route);
    }
    
    /**
     * 2-Opt Algorithm (Local Search Improvement)
     */
    private function twoOptAlgorithm($points, $distance_matrix) {
        // เริ่มด้วย nearest neighbor
        $initial_route = $this->nearestNeighborAlgorithm($points, $distance_matrix);
        $route_indices = array_map(function($point) use ($points) {
            return array_search($point, $points);
        }, $initial_route['points']);
        
        $improved = true;
        $iterations = 0;
        $max_iterations = 100;
        
        while ($improved && $iterations < $max_iterations) {
            $improved = false;
            $iterations++;
            
            for ($i = 1; $i < count($route_indices) - 2; $i++) {
                for ($j = $i + 1; $j < count($route_indices) - 1; $j++) {
                    $new_route = $this->twoOptSwap($route_indices, $i, $j);
                    
                    if ($this->calculateTotalDistance($new_route, $distance_matrix) < 
                        $this->calculateTotalDistance($route_indices, $distance_matrix)) {
                        $route_indices = $new_route;
                        $improved = true;
                    }
                }
            }
        }
        
        return $this->buildRouteResult($points, $route_indices);
    }
    
    /**
     * 2-Opt Swap operation
     */
    private function twoOptSwap($route, $i, $j) {
        $new_route = [];
        
        // Take route[0] to route[i]
        for ($k = 0; $k <= $i; $k++) {
            $new_route[] = $route[$k];
        }
        
        // Take route[i+1] to route[j] in reverse order
        for ($k = $j; $k >= $i + 1; $k--) {
            $new_route[] = $route[$k];
        }
        
        // Take route[j+1] to end
        for ($k = $j + 1; $k < count($route); $k++) {
            $new_route[] = $route[$k];
        }
        
        return $new_route;
    }
    
    /**
     * Simulated Annealing Algorithm
     */
    private function simulatedAnnealing($points, $distance_matrix) {
        // เริ่มด้วย random route
        $current_route = range(0, count($points) - 1);
        array_push($current_route, 0); // กลับไปที่ depot
        
        $current_distance = $this->calculateTotalDistance($current_route, $distance_matrix);
        $best_route = $current_route;
        $best_distance = $current_distance;
        
        $temperature = 10000;
        $cooling_rate = 0.95;
        $min_temperature = 1;
        
        while ($temperature > $min_temperature) {
            // สร้าง neighbor solution
            $new_route = $this->generateNeighbor($current_route);
            $new_distance = $this->calculateTotalDistance($new_route, $distance_matrix);
            
            // Accept or reject
            if ($new_distance < $current_distance || 
                mt_rand() / mt_getrandmax() < exp(($current_distance - $new_distance) / $temperature)) {
                $current_route = $new_route;
                $current_distance = $new_distance;
                
                if ($new_distance < $best_distance) {
                    $best_route = $new_route;
                    $best_distance = $new_distance;
                }
            }
            
            $temperature *= $cooling_rate;
        }
        
        return $this->buildRouteResult($points, $best_route);
    }
    
    /**
     * Generate neighbor solution for simulated annealing
     */
    private function generateNeighbor($route) {
        $new_route = $route;
        $len = count($route) - 2; // ไม่รวม depot ท้าย
        
        // Swap two random cities (ไม่รวม depot)
        $i = mt_rand(1, $len - 1);
        $j = mt_rand(1, $len - 1);
        
        $temp = $new_route[$i];
        $new_route[$i] = $new_route[$j];
        $new_route[$j] = $temp;
        
        return $new_route;
    }
    
    /**
     * Genetic Algorithm (simplified version)
     */
    private function geneticAlgorithm($points, $distance_matrix) {
        $population_size = 50;
        $generations = 100;
        $mutation_rate = 0.01;
        $elite_size = 5;
        
        // สร้าง population เริ่มต้น
        $population = [];
        for ($i = 0; $i < $population_size; $i++) {
            $route = range(1, count($points) - 1); // ไม่รวม depot
            shuffle($route);
            array_unshift($route, 0); // เพิ่ม depot ที่เริ่มต้น
            array_push($route, 0); // เพิ่ม depot ที่สิ้นสุด
            $population[] = $route;
        }
        
        for ($generation = 0; $generation < $generations; $generation++) {
            // Evaluate fitness
            $fitness = [];
            foreach ($population as $route) {
                $distance = $this->calculateTotalDistance($route, $distance_matrix);
                $fitness[] = 1 / (1 + $distance); // เยอะกว่า = ดีกว่า
            }
            
            // Selection (tournament selection)
            $new_population = [];
            
            // Keep elite
            $elite_indices = array_keys($fitness);
            arsort($fitness);
            $elite_indices = array_slice(array_keys($fitness), 0, $elite_size);
            
            foreach ($elite_indices as $index) {
                $new_population[] = $population[$index];
            }
            
            // Fill rest of population
            while (count($new_population) < $population_size) {
                $parent1 = $this->tournamentSelection($population, $fitness);
                $parent2 = $this->tournamentSelection($population, $fitness);
                
                $child = $this->orderCrossover($parent1, $parent2);
                
                if (mt_rand() / mt_getrandmax() < $mutation_rate) {
                    $child = $this->mutateRoute($child);
                }
                
                $new_population[] = $child;
            }
            
            $population = $new_population;
        }
        
        // Find best route
        $best_route = $population[0];
        $best_distance = $this->calculateTotalDistance($best_route, $distance_matrix);
        
        foreach ($population as $route) {
            $distance = $this->calculateTotalDistance($route, $distance_matrix);
            if ($distance < $best_distance) {
                $best_route = $route;
                $best_distance = $distance;
            }
        }
        
        return $this->buildRouteResult($points, $best_route);
    }
    
    /**
     * Tournament selection for genetic algorithm
     */
    private function tournamentSelection($population, $fitness, $tournament_size = 3) {
        $best_index = mt_rand(0, count($population) - 1);
        
        for ($i = 1; $i < $tournament_size; $i++) {
            $index = mt_rand(0, count($population) - 1);
            if ($fitness[$index] > $fitness[$best_index]) {
                $best_index = $index;
            }
        }
        
        return $population[$best_index];
    }
    
    /**
     * Order crossover for genetic algorithm
     */
    private function orderCrossover($parent1, $parent2) {
        $size = count($parent1) - 2; // ไม่รวม depot ปลาย
        $start = mt_rand(1, $size - 1);
        $end = mt_rand($start, $size - 1);
        
        $child = array_fill(0, count($parent1), null);
        $child[0] = 0; // depot เริ่มต้น
        $child[count($child) - 1] = 0; // depot สิ้นสุด
        
        // Copy section from parent1
        for ($i = $start; $i <= $end; $i++) {
            $child[$i] = $parent1[$i];
        }
        
        // Fill remaining from parent2
        $parent2_index = 1;
        for ($i = 1; $i < count($child) - 1; $i++) {
            if ($child[$i] === null) {
                while (in_array($parent2[$parent2_index], $child)) {
                    $parent2_index++;
                    if ($parent2_index >= count($parent2) - 1) {
                        $parent2_index = 1;
                        break;
                    }
                }
                $child[$i] = $parent2[$parent2_index];
                $parent2_index++;
            }
        }
        
        return $child;
    }
    
    /**
     * Mutate route for genetic algorithm
     */
    private function mutateRoute($route) {
        $size = count($route) - 2;
        $i = mt_rand(1, $size - 1);
        $j = mt_rand(1, $size - 1);
        
        $temp = $route[$i];
        $route[$i] = $route[$j];
        $route[$j] = $temp;
        
        return $route;
    }
    
    /**
     * คำนวณระยะทางรวมของเส้นทาง
     */
    private function calculateTotalDistance($route, $distance_matrix) {
        $total = 0;
        for ($i = 0; $i < count($route) - 1; $i++) {
            $total += $distance_matrix[$route[$i]][$route[$i + 1]];
        }
        return $total;
    }
    
    /**
     * สร้างผลลัพธ์เส้นทาง
     */
    private function buildRouteResult($points, $route_indices) {
        $route_points = [];
        foreach ($route_indices as $index) {
            $route_points[] = $points[$index];
        }
        
        return [
            'points' => $route_points,
            'indices' => $route_indices
        ];
    }
    
    /**
     * คำนวณสถิติเส้นทาง
     */
    private function calculateRouteStatistics($route, $distance_matrix) {
        $total_distance = $this->calculateTotalDistance($route['indices'], $distance_matrix);
        $num_stops = count($route['points']) - 2; // ไม่รวม depot 2 จุด
        
        // คำนวณเวลา
        $travel_time = ($total_distance / $this->vehicle_speed) * 60; // นาที
        $stop_time = $num_stops * $this->stop_time; // นาที
        $total_time = $travel_time + $stop_time;
        
        // คำนวณค่าใช้จ่าย (ประมาณ)
        $fuel_cost_per_km = 8; // บาท/กม.
        $estimated_cost = $total_distance * $fuel_cost_per_km;
        
        return [
            'total_distance' => round($total_distance, 2),
            'num_stops' => $num_stops,
            'travel_time' => round($travel_time, 1),
            'stop_time' => $stop_time,
            'total_time' => round($total_time, 1),
            'estimated_cost' => round($estimated_cost, 2),
            'average_distance_per_stop' => $num_stops > 0 ? round($total_distance / $num_stops, 2) : 0
        ];
    }
    
    /**
     * บันทึกเส้นทางลงฐานข้อมูล
     */
    public function saveRoute($zone_id, $route_name, $route_result, $algorithm_used) {
        global $conn;
        
        try {
            $delivery_ids = [];
            foreach ($route_result['route']['points'] as $point) {
                if ($point['type'] === 'delivery') {
                    $delivery_ids[] = $point['id'];
                }
            }
            
            $sql = "INSERT INTO route_optimization (
                zone_id, route_name, delivery_ids, total_distance, 
                estimated_time, algorithm_used, optimization_score, 
                route_points, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $zone_id,
                $route_name,
                json_encode($delivery_ids),
                $route_result['statistics']['total_distance'],
                $route_result['statistics']['total_time'],
                $algorithm_used,
                $this->calculateOptimizationScore($route_result),
                json_encode($route_result['route']['points'])
            ]);
            
            return ['success' => true, 'route_id' => $conn->lastInsertId()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * คำนวณคะแนนการเพิ่มประสิทธิภาพ
     */
    private function calculateOptimizationScore($route_result) {
        $stats = $route_result['statistics'];
        
        // คะแนนพื้นฐานจากจำนวนจุด
        $base_score = min(100, $stats['num_stops'] * 2);
        
        // ปรับตามประสิทธิภาพระยะทาง
        $efficiency = $stats['num_stops'] > 0 ? (50 / $stats['average_distance_per_stop']) : 0;
        $efficiency_score = min(30, $efficiency * 10);
        
        // ปรับตามเวลา
        $time_efficiency = $stats['num_stops'] > 0 ? (300 / $stats['total_time']) : 0;
        $time_score = min(20, $time_efficiency * 10);
        
        return round($base_score + $efficiency_score + $time_score, 2);
    }
    
    // Setters
    public function setDepot($lat, $lng) {
        $this->depot_lat = $lat;
        $this->depot_lng = $lng;
    }
    
    public function setMaxDistance($distance) {
        $this->max_distance = $distance;
    }
    
    public function setMaxStops($stops) {
        $this->max_stops = $stops;
    }
    
    public function setVehicleSpeed($speed) {
        $this->vehicle_speed = $speed;
    }
    
    public function setStopTime($time) {
        $this->stop_time = $time;
    }
}

/**
 * Helper function สำหรับใช้งานง่าย
 */
function optimizeDeliveryRoute($deliveries, $algorithm = 'nearest_neighbor') {
    $optimizer = new RouteOptimizer();
    return $optimizer->optimizeRoute($deliveries, $algorithm);
}
?> 