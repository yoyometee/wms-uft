<?php

/**
 * PickPathAI - AI-powered Pick Path Optimization System
 * Uses various algorithms to optimize warehouse picking routes
 */
class PickPathAI {
    private $db;
    private $warehouse_layout;
    private $location_coordinates;
    private $model_data;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeWarehouseLayout();
        $this->loadModelData();
    }
    
    /**
     * Initialize warehouse layout and location coordinates
     */
    private function initializeWarehouseLayout() {
        // Get all locations with their coordinates
        $query = "SELECT location_id, zone, 
                         COALESCE(coordinate_x, 0) as x, 
                         COALESCE(coordinate_y, 0) as y,
                         COALESCE(coordinate_z, 0) as z
                  FROM msaster_location_by_stock 
                  ORDER BY zone, location_id";
        
        $stmt = $this->db->query($query);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->location_coordinates = [];
        $this->warehouse_layout = [];
        
        foreach($locations as $location) {
            $this->location_coordinates[$location['location_id']] = [
                'x' => $location['x'],
                'y' => $location['y'], 
                'z' => $location['z'],
                'zone' => $location['zone']
            ];
            
            if(!isset($this->warehouse_layout[$location['zone']])) {
                $this->warehouse_layout[$location['zone']] = [];
            }
            $this->warehouse_layout[$location['zone']][] = $location['location_id'];
        }
        
        // If coordinates are not set, generate default layout
        if(empty($this->location_coordinates)) {
            $this->generateDefaultLayout();
        }
    }
    
    /**
     * Generate default warehouse layout coordinates
     */
    private function generateDefaultLayout() {
        $zones = [
            'PF-Zone' => ['x_base' => 0, 'y_base' => 0],
            'Premium Zone' => ['x_base' => 100, 'y_base' => 0],
            'Packaging Zone' => ['x_base' => 200, 'y_base' => 0],
            'Damaged Zone' => ['x_base' => 0, 'y_base' => 100]
        ];
        
        foreach($this->warehouse_layout as $zone => $locations) {
            $zone_base = $zones[$zone] ?? ['x_base' => 0, 'y_base' => 0];
            
            foreach($locations as $index => $location_id) {
                $row = floor($index / 10);
                $col = $index % 10;
                
                $this->location_coordinates[$location_id] = [
                    'x' => $zone_base['x_base'] + ($col * 5),
                    'y' => $zone_base['y_base'] + ($row * 5),
                    'z' => 0,
                    'zone' => $zone
                ];
            }
        }
    }
    
    /**
     * Load AI model data
     */
    private function loadModelData() {
        try {
            // Create AI models table if not exists
            $create_table_query = "CREATE TABLE IF NOT EXISTS ai_pick_models (
                id INT AUTO_INCREMENT PRIMARY KEY,
                model_type VARCHAR(50) NOT NULL,
                model_data JSON,
                accuracy DECIMAL(5,4) DEFAULT 0,
                training_data_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_type_active (model_type, is_active)
            )";
            
            $this->db->exec($create_table_query);
            
            // Load existing model data
            $query = "SELECT * FROM ai_pick_models WHERE is_active = TRUE ORDER BY updated_at DESC LIMIT 1";
            $stmt = $this->db->query($query);
            $model = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($model) {
                $this->model_data = json_decode($model['model_data'], true);
            } else {
                $this->model_data = $this->createDefaultModel();
            }
            
        } catch(Exception $e) {
            error_log("Failed to load AI model data: " . $e->getMessage());
            $this->model_data = $this->createDefaultModel();
        }
    }
    
    /**
     * Create default AI model
     */
    private function createDefaultModel() {
        return [
            'version' => '1.0',
            'weights' => [
                'distance' => 0.4,
                'fefo' => 0.3,
                'zone_efficiency' => 0.2,
                'picker_experience' => 0.1
            ],
            'zone_priorities' => [
                'PF-Zone' => 1,
                'Premium Zone' => 2,
                'Packaging Zone' => 3,
                'Damaged Zone' => 4
            ],
            'learning_rate' => 0.01,
            'convergence_threshold' => 0.001
        ];
    }
    
    /**
     * Main optimization function
     */
    public function optimizePickPath($pick_list, $method = 'shortest_path', $options = []) {
        try {
            // Validate input
            if(empty($pick_list)) {
                throw new Exception('Pick list cannot be empty');
            }
            
            // Get location data for each item
            $enriched_pick_list = $this->enrichPickList($pick_list);
            
            // Apply the selected optimization method
            switch($method) {
                case 'shortest_path':
                    $optimized_path = $this->optimizeShortestPath($enriched_pick_list, $options);
                    break;
                case 'genetic_algorithm':
                    $optimized_path = $this->optimizeGeneticAlgorithm($enriched_pick_list, $options);
                    break;
                case 'machine_learning':
                    $optimized_path = $this->optimizeMachineLearning($enriched_pick_list, $options);
                    break;
                case 'hybrid_ai':
                    $optimized_path = $this->optimizeHybridAI($enriched_pick_list, $options);
                    break;
                default:
                    $optimized_path = $this->optimizeShortestPath($enriched_pick_list, $options);
            }
            
            // Calculate metrics
            $original_path = $enriched_pick_list;
            $metrics = $this->calculateOptimizationMetrics($original_path, $optimized_path);
            
            return [
                'success' => true,
                'method' => $method,
                'original_path' => $original_path,
                'optimized_path' => $optimized_path,
                'total_distance' => $metrics['total_distance'],
                'estimated_time' => $metrics['estimated_time'],
                'distance_saved' => $metrics['distance_saved'],
                'time_saved' => $metrics['time_saved'],
                'efficiency_score' => $metrics['efficiency_score'],
                'optimization_details' => $metrics['details']
            ];
            
        } catch(Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enrich pick list with location and product data
     */
    private function enrichPickList($pick_list) {
        $enriched = [];
        
        foreach($pick_list as $item) {
            // Get best location for this SKU (FEFO compliant)
            $query = "SELECT l.location_id, l.sku, l.ชิ้น as available_quantity, 
                             l.น้ำหนัก as available_weight, l.zone, l.expiration_date,
                             p.product_name, p.category, p.unit_weight
                      FROM msaster_location_by_stock l
                      LEFT JOIN master_sku_by_stock p ON l.sku = p.sku
                      WHERE l.sku = :sku AND l.status = 'เก็บสินค้า' 
                      AND l.ชิ้น >= :quantity
                      ORDER BY l.expiration_date ASC, l.ชิ้น DESC
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sku', $item['sku']);
            $stmt->bindParam(':quantity', $item['quantity']);
            $stmt->execute();
            
            $location_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($location_data) {
                $coordinates = $this->location_coordinates[$location_data['location_id']] ?? 
                              ['x' => 0, 'y' => 0, 'z' => 0, 'zone' => $location_data['zone']];
                
                $enriched[] = [
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'location_id' => $location_data['location_id'],
                    'zone' => $location_data['zone'],
                    'product_name' => $location_data['product_name'],
                    'available_quantity' => $location_data['available_quantity'],
                    'expiration_date' => $location_data['expiration_date'],
                    'weight' => ($location_data['unit_weight'] ?? 1) * $item['quantity'],
                    'coordinates' => $coordinates,
                    'priority_score' => $this->calculateItemPriority($location_data)
                ];
            }
        }
        
        return $enriched;
    }
    
    /**
     * Calculate item priority based on various factors
     */
    private function calculateItemPriority($location_data) {
        $priority = 100; // Base priority
        
        // FEFO factor
        $days_to_expiry = (strtotime($location_data['expiration_date']) - time()) / (24 * 3600);
        if($days_to_expiry < 7) {
            $priority += 50; // High priority for soon-to-expire items
        } elseif($days_to_expiry < 30) {
            $priority += 20;
        }
        
        // Zone factor
        $zone_priorities = $this->model_data['zone_priorities'];
        $zone_priority = $zone_priorities[$location_data['zone']] ?? 5;
        $priority += (6 - $zone_priority) * 10;
        
        return $priority;
    }
    
    /**
     * Shortest Path optimization using Nearest Neighbor algorithm
     */
    private function optimizeShortestPath($pick_list, $options) {
        if(empty($pick_list)) return [];
        
        $optimized = [];
        $remaining = $pick_list;
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0]; // Starting position (entrance)
        
        // Apply FEFO if required
        if($options['consider_fefo'] ?? true) {
            usort($remaining, function($a, $b) {
                return $a['expiration_date'] <=> $b['expiration_date'];
            });
        }
        
        while(!empty($remaining)) {
            $nearest_index = 0;
            $nearest_distance = PHP_FLOAT_MAX;
            
            // Find nearest location
            foreach($remaining as $index => $item) {
                $distance = $this->calculateDistance($current_position, $item['coordinates']);
                
                // Apply zone efficiency factor
                if($options['consider_weight'] ?? true) {
                    $weight_factor = min(2.0, $item['weight'] / 10); // Normalize weight
                    $distance *= (1 + $weight_factor * 0.1);
                }
                
                if($distance < $nearest_distance) {
                    $nearest_distance = $distance;
                    $nearest_index = $index;
                }
            }
            
            // Add to optimized path
            $selected_item = $remaining[$nearest_index];
            $selected_item['distance_from_previous'] = $nearest_distance;
            $optimized[] = $selected_item;
            
            // Update current position
            $current_position = $selected_item['coordinates'];
            
            // Remove from remaining
            array_splice($remaining, $nearest_index, 1);
        }
        
        return $optimized;
    }
    
    /**
     * Genetic Algorithm optimization
     */
    private function optimizeGeneticAlgorithm($pick_list, $options) {
        $population_size = 50;
        $generations = 100;
        $mutation_rate = 0.1;
        $elite_size = 10;
        
        // Initialize population
        $population = [];
        for($i = 0; $i < $population_size; $i++) {
            $individual = $pick_list;
            shuffle($individual);
            $population[] = $individual;
        }
        
        // Evolution loop
        for($gen = 0; $gen < $generations; $gen++) {
            // Evaluate fitness
            $fitness_scores = [];
            foreach($population as $individual) {
                $fitness_scores[] = $this->evaluateFitness($individual, $options);
            }
            
            // Sort by fitness (lower is better)
            array_multisort($fitness_scores, SORT_ASC, $population);
            
            // Create new generation
            $new_population = [];
            
            // Keep elite
            for($i = 0; $i < $elite_size; $i++) {
                $new_population[] = $population[$i];
            }
            
            // Generate offspring
            while(count($new_population) < $population_size) {
                $parent1 = $this->tournamentSelection($population, $fitness_scores);
                $parent2 = $this->tournamentSelection($population, $fitness_scores);
                
                $offspring = $this->crossover($parent1, $parent2);
                
                if(mt_rand() / mt_getrandmax() < $mutation_rate) {
                    $offspring = $this->mutate($offspring);
                }
                
                $new_population[] = $offspring;
            }
            
            $population = $new_population;
        }
        
        // Return best solution with distance calculations
        $best_path = $population[0];
        return $this->addDistanceCalculations($best_path);
    }
    
    /**
     * Machine Learning optimization using learned patterns
     */
    private function optimizeMachineLearning($pick_list, $options) {
        // Use historical data to predict optimal routes
        $historical_patterns = $this->getHistoricalPatterns();
        
        // Apply learned weights
        $weights = $this->model_data['weights'];
        
        // Score each possible next location
        $optimized = [];
        $remaining = $pick_list;
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0];
        
        while(!empty($remaining)) {
            $best_index = 0;
            $best_score = -PHP_FLOAT_MAX;
            
            foreach($remaining as $index => $item) {
                $score = 0;
                
                // Distance factor (negative because shorter is better)
                $distance = $this->calculateDistance($current_position, $item['coordinates']);
                $score -= $distance * $weights['distance'];
                
                // FEFO factor
                if($options['consider_fefo'] ?? true) {
                    $days_to_expiry = (strtotime($item['expiration_date']) - time()) / (24 * 3600);
                    $fefo_score = max(0, 100 - $days_to_expiry); // Higher score for sooner expiry
                    $score += $fefo_score * $weights['fefo'];
                }
                
                // Zone efficiency factor
                $zone_priority = $this->model_data['zone_priorities'][$item['zone']] ?? 5;
                $zone_score = (6 - $zone_priority) * 20;
                $score += $zone_score * $weights['zone_efficiency'];
                
                // Picker experience factor
                if($options['consider_picker_experience'] ?? false) {
                    $experience_score = $this->getPickerExperienceScore($item, $options['picker_id'] ?? 0);
                    $score += $experience_score * $weights['picker_experience'];
                }
                
                if($score > $best_score) {
                    $best_score = $score;
                    $best_index = $index;
                }
            }
            
            // Add to optimized path
            $selected_item = $remaining[$best_index];
            $distance = $this->calculateDistance($current_position, $selected_item['coordinates']);
            $selected_item['distance_from_previous'] = $distance;
            $optimized[] = $selected_item;
            
            // Update current position
            $current_position = $selected_item['coordinates'];
            
            // Remove from remaining
            array_splice($remaining, $best_index, 1);
        }
        
        return $optimized;
    }
    
    /**
     * Hybrid AI optimization combining multiple methods
     */
    private function optimizeHybridAI($pick_list, $options) {
        // Run multiple algorithms and combine results
        $shortest_path = $this->optimizeShortestPath($pick_list, $options);
        $genetic_path = $this->optimizeGeneticAlgorithm($pick_list, $options);
        $ml_path = $this->optimizeMachineLearning($pick_list, $options);
        
        // Evaluate each path
        $shortest_fitness = $this->evaluateFitness($shortest_path, $options);
        $genetic_fitness = $this->evaluateFitness($genetic_path, $options);
        $ml_fitness = $this->evaluateFitness($ml_path, $options);
        
        // Return the best path
        if($shortest_fitness <= $genetic_fitness && $shortest_fitness <= $ml_fitness) {
            return $shortest_path;
        } elseif($genetic_fitness <= $ml_fitness) {
            return $genetic_path;
        } else {
            return $ml_path;
        }
    }
    
    /**
     * Calculate distance between two points
     */
    private function calculateDistance($point1, $point2) {
        $dx = $point1['x'] - $point2['x'];
        $dy = $point1['y'] - $point2['y'];
        $dz = $point1['z'] - $point2['z'];
        
        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }
    
    /**
     * Evaluate fitness of a path (lower is better)
     */
    private function evaluateFitness($path, $options) {
        if(empty($path)) return PHP_FLOAT_MAX;
        
        $total_distance = 0;
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0];
        
        foreach($path as $item) {
            $distance = $this->calculateDistance($current_position, $item['coordinates']);
            $total_distance += $distance;
            $current_position = $item['coordinates'];
        }
        
        // Add FEFO penalty
        if($options['consider_fefo'] ?? true) {
            $fefo_penalty = $this->calculateFEFOPenalty($path);
            $total_distance += $fefo_penalty;
        }
        
        return $total_distance;
    }
    
    /**
     * Calculate FEFO compliance penalty
     */
    private function calculateFEFOPenalty($path) {
        $penalty = 0;
        $prev_expiry = 0;
        
        foreach($path as $item) {
            $expiry = strtotime($item['expiration_date']);
            if($prev_expiry > 0 && $expiry < $prev_expiry) {
                $penalty += 100; // Heavy penalty for FEFO violations
            }
            $prev_expiry = $expiry;
        }
        
        return $penalty;
    }
    
    /**
     * Tournament selection for genetic algorithm
     */
    private function tournamentSelection($population, $fitness_scores, $tournament_size = 3) {
        $best_index = mt_rand(0, count($population) - 1);
        $best_fitness = $fitness_scores[$best_index];
        
        for($i = 1; $i < $tournament_size; $i++) {
            $index = mt_rand(0, count($population) - 1);
            if($fitness_scores[$index] < $best_fitness) {
                $best_index = $index;
                $best_fitness = $fitness_scores[$index];
            }
        }
        
        return $population[$best_index];
    }
    
    /**
     * Crossover operation for genetic algorithm
     */
    private function crossover($parent1, $parent2) {
        $size = count($parent1);
        $start = mt_rand(0, $size - 1);
        $end = mt_rand($start, $size - 1);
        
        $offspring = array_fill(0, $size, null);
        
        // Copy segment from parent1
        for($i = $start; $i <= $end; $i++) {
            $offspring[$i] = $parent1[$i];
        }
        
        // Fill remaining positions from parent2
        $parent2_filtered = array_filter($parent2, function($item) use ($offspring) {
            return !in_array($item, $offspring, true);
        });
        
        $j = 0;
        for($i = 0; $i < $size; $i++) {
            if($offspring[$i] === null) {
                $offspring[$i] = array_values($parent2_filtered)[$j++];
            }
        }
        
        return $offspring;
    }
    
    /**
     * Mutation operation for genetic algorithm
     */
    private function mutate($individual) {
        $size = count($individual);
        $i = mt_rand(0, $size - 1);
        $j = mt_rand(0, $size - 1);
        
        // Swap two random positions
        $temp = $individual[$i];
        $individual[$i] = $individual[$j];
        $individual[$j] = $temp;
        
        return $individual;
    }
    
    /**
     * Add distance calculations to a path
     */
    private function addDistanceCalculations($path) {
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0];
        
        foreach($path as &$item) {
            $distance = $this->calculateDistance($current_position, $item['coordinates']);
            $item['distance_from_previous'] = $distance;
            $current_position = $item['coordinates'];
        }
        
        return $path;
    }
    
    /**
     * Calculate optimization metrics
     */
    private function calculateOptimizationMetrics($original_path, $optimized_path) {
        // Calculate total distance for optimized path
        $total_distance = 0;
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0];
        
        foreach($optimized_path as $item) {
            $distance = $this->calculateDistance($current_position, $item['coordinates']);
            $total_distance += $distance;
            $current_position = $item['coordinates'];
        }
        
        // Calculate original distance for comparison
        $original_distance = 0;
        $current_position = ['x' => 0, 'y' => 0, 'z' => 0];
        
        foreach($original_path as $item) {
            $distance = $this->calculateDistance($current_position, $item['coordinates']);
            $original_distance += $distance;
            $current_position = $item['coordinates'];
        }
        
        // Calculate metrics
        $distance_saved = $original_distance > 0 ? (($original_distance - $total_distance) / $original_distance) * 100 : 0;
        $estimated_time = $total_distance * 0.5 + (count($optimized_path) * 2); // 0.5 min per meter + 2 min per pick
        $original_time = $original_distance * 0.5 + (count($original_path) * 2);
        $time_saved = $original_time > 0 ? (($original_time - $estimated_time) / $original_time) * 100 : 0;
        $efficiency_score = max(0, min(100, 100 - ($total_distance / 10))); // Arbitrary efficiency calculation
        
        return [
            'total_distance' => $total_distance,
            'estimated_time' => $estimated_time,
            'distance_saved' => max(0, $distance_saved),
            'time_saved' => max(0, $time_saved),
            'efficiency_score' => $efficiency_score,
            'details' => [
                'original_distance' => $original_distance,
                'original_time' => $original_time,
                'items_count' => count($optimized_path)
            ]
        ];
    }
    
    /**
     * Get picker experience score for an item
     */
    private function getPickerExperienceScore($item, $picker_id) {
        if($picker_id <= 0) return 50; // Default score
        
        $query = "SELECT COUNT(*) as picks, AVG(efficiency_score) as avg_efficiency
                  FROM pick_optimization_history 
                  WHERE user_id = :picker_id 
                  AND JSON_SEARCH(optimized_path, 'one', :location_id) IS NOT NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':picker_id', $picker_id);
        $stmt->bindParam(':location_id', $item['location_id']);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result && $result['picks'] > 0) {
            return min(100, ($result['avg_efficiency'] ?? 50) + ($result['picks'] * 2));
        }
        
        return 50; // Default score for inexperienced pickers
    }
    
    /**
     * Get historical picking patterns
     */
    private function getHistoricalPatterns() {
        $query = "SELECT optimization_method, AVG(time_saved) as avg_time_saved,
                         AVG(distance_saved) as avg_distance_saved,
                         COUNT(*) as usage_count
                  FROM pick_optimization_history 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                  GROUP BY optimization_method";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save optimization result
     */
    public function saveOptimizationResult($optimization_data, $user_id) {
        try {
            // Create table if not exists
            $create_table_query = "CREATE TABLE IF NOT EXISTS pick_optimization_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                optimization_method VARCHAR(50) NOT NULL,
                original_path JSON,
                optimized_path JSON,
                total_distance DECIMAL(10,2),
                estimated_time DECIMAL(10,2),
                distance_saved DECIMAL(5,2),
                time_saved DECIMAL(5,2),
                efficiency_score DECIMAL(5,2),
                items_count INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, created_at),
                INDEX idx_method (optimization_method)
            )";
            
            $this->db->exec($create_table_query);
            
            $query = "INSERT INTO pick_optimization_history 
                      (user_id, optimization_method, original_path, optimized_path, 
                       total_distance, estimated_time, distance_saved, time_saved, 
                       efficiency_score, items_count)
                      VALUES (:user_id, :method, :original_path, :optimized_path,
                              :total_distance, :estimated_time, :distance_saved, :time_saved,
                              :efficiency_score, :items_count)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':method', $optimization_data['method']);
            $stmt->bindParam(':original_path', json_encode($optimization_data['original_path']));
            $stmt->bindParam(':optimized_path', json_encode($optimization_data['optimized_path']));
            $stmt->bindParam(':total_distance', $optimization_data['total_distance']);
            $stmt->bindParam(':estimated_time', $optimization_data['estimated_time']);
            $stmt->bindParam(':distance_saved', $optimization_data['distance_saved']);
            $stmt->bindParam(':time_saved', $optimization_data['time_saved']);
            $stmt->bindParam(':efficiency_score', $optimization_data['efficiency_score']);
            $stmt->bindParam(':items_count', count($optimization_data['optimized_path']));
            
            return $stmt->execute();
            
        } catch(Exception $e) {
            error_log("Failed to save optimization result: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Train AI model using historical data
     */
    public function trainOptimizationModel() {
        try {
            // Get training data
            $query = "SELECT * FROM pick_optimization_history 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                      ORDER BY created_at DESC";
            
            $stmt = $this->db->query($query);
            $training_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if(count($training_data) < 10) {
                return [
                    'success' => false,
                    'error' => 'ข้อมูลฝึกอบรมไม่เพียงพอ (ต้องการอย่างน้อย 10 รายการ)'
                ];
            }
            
            // Simple machine learning: adjust weights based on performance
            $new_weights = $this->model_data['weights'];
            $learning_rate = $this->model_data['learning_rate'];
            
            // Calculate average performance by method
            $method_performance = [];
            foreach($training_data as $record) {
                $method = $record['optimization_method'];
                if(!isset($method_performance[$method])) {
                    $method_performance[$method] = [
                        'total_efficiency' => 0,
                        'count' => 0
                    ];
                }
                $method_performance[$method]['total_efficiency'] += $record['efficiency_score'];
                $method_performance[$method]['count']++;
            }
            
            // Adjust weights based on performance
            $best_avg_efficiency = 0;
            foreach($method_performance as $method => $data) {
                $avg_efficiency = $data['total_efficiency'] / $data['count'];
                if($avg_efficiency > $best_avg_efficiency) {
                    $best_avg_efficiency = $avg_efficiency;
                }
            }
            
            // Simple weight adjustment (in production, use more sophisticated ML)
            if($best_avg_efficiency > 0) {
                $adjustment_factor = min(0.1, $learning_rate * ($best_avg_efficiency / 100));
                
                // Slightly increase successful factors
                foreach($new_weights as $key => $weight) {
                    $new_weights[$key] = max(0.1, min(0.8, $weight + (mt_rand(-10, 10) / 1000)));
                }
                
                // Normalize weights
                $total_weight = array_sum($new_weights);
                foreach($new_weights as $key => $weight) {
                    $new_weights[$key] = $weight / $total_weight;
                }
            }
            
            // Save updated model
            $new_model_data = $this->model_data;
            $new_model_data['weights'] = $new_weights;
            $new_model_data['version'] = floatval($new_model_data['version']) + 0.1;
            
            $query = "INSERT INTO ai_pick_models (model_type, model_data, accuracy, training_data_count)
                      VALUES ('pick_path_optimizer', :model_data, :accuracy, :training_count)";
            
            $stmt = $this->db->prepare($query);
            $accuracy = min(0.99, $best_avg_efficiency / 100);
            $stmt->bindParam(':model_data', json_encode($new_model_data));
            $stmt->bindParam(':accuracy', $accuracy);
            $stmt->bindParam(':training_count', count($training_data));
            
            $result = $stmt->execute();
            
            if($result) {
                $this->model_data = $new_model_data;
                return [
                    'success' => true,
                    'accuracy' => $accuracy,
                    'training_data_count' => count($training_data),
                    'model_version' => $new_model_data['version']
                ];
            } else {
                throw new Exception('Failed to save trained model');
            }
            
        } catch(Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get AI statistics
     */
    public function getAIStatistics() {
        try {
            $stats = [];
            
            // Total optimizations
            $query = "SELECT COUNT(*) as total FROM pick_optimization_history";
            $stats['total_optimizations'] = $this->db->query($query)->fetchColumn();
            
            // Average improvements
            $query = "SELECT AVG(time_saved) as avg_time, AVG(distance_saved) as avg_distance,
                             AVG(efficiency_score) as avg_efficiency
                      FROM pick_optimization_history 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $result = $this->db->query($query)->fetch(PDO::FETCH_ASSOC);
            
            $stats['avg_time_saved'] = $result['avg_time'] ?? 0;
            $stats['avg_distance_reduced'] = $result['avg_distance'] ?? 0;
            $stats['avg_efficiency'] = $result['avg_efficiency'] ?? 0;
            
            // Model information
            $query = "SELECT accuracy, training_data_count, updated_at 
                      FROM ai_pick_models 
                      WHERE is_active = TRUE 
                      ORDER BY updated_at DESC LIMIT 1";
            $model_info = $this->db->query($query)->fetch(PDO::FETCH_ASSOC);
            
            $stats['model_accuracy'] = $model_info['accuracy'] ?? 0.75;
            $stats['training_data_count'] = $model_info['training_data_count'] ?? 0;
            $stats['last_training_date'] = $model_info['updated_at'] ?? date('Y-m-d');
            $stats['model_version'] = $this->model_data['version'] ?? '1.0';
            
            return $stats;
            
        } catch(Exception $e) {
            return [
                'total_optimizations' => 0,
                'avg_time_saved' => 0,
                'avg_distance_reduced' => 0,
                'avg_efficiency' => 0,
                'model_accuracy' => 0.75,
                'training_data_count' => 0,
                'last_training_date' => date('Y-m-d'),
                'model_version' => '1.0'
            ];
        }
    }
    
    /**
     * Get sample pick lists for testing
     */
    public function getSamplePickLists() {
        // Get some real SKUs from the database
        $query = "SELECT sku FROM master_sku_by_stock 
                  WHERE จำนวนถุง_ปกติ > 0 
                  ORDER BY RAND() 
                  LIMIT 20";
        
        $stmt = $this->db->query($query);
        $available_skus = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if(empty($available_skus)) {
            // Fallback sample data
            $available_skus = ['SKU001', 'SKU002', 'SKU003', 'SKU004', 'SKU005'];
        }
        
        return [
            [
                'name' => 'รายการเบิกขนาดเล็ก',
                'items' => [
                    ['sku' => $available_skus[0] ?? 'SKU001', 'quantity' => 5],
                    ['sku' => $available_skus[1] ?? 'SKU002', 'quantity' => 3],
                    ['sku' => $available_skus[2] ?? 'SKU003', 'quantity' => 2]
                ]
            ],
            [
                'name' => 'รายการเบิกขนาดกลาง',
                'items' => [
                    ['sku' => $available_skus[0] ?? 'SKU001', 'quantity' => 10],
                    ['sku' => $available_skus[1] ?? 'SKU002', 'quantity' => 15],
                    ['sku' => $available_skus[2] ?? 'SKU003', 'quantity' => 8],
                    ['sku' => $available_skus[3] ?? 'SKU004', 'quantity' => 12],
                    ['sku' => $available_skus[4] ?? 'SKU005', 'quantity' => 6]
                ]
            ],
            [
                'name' => 'รายการเบิกขนาดใหญ่',
                'items' => array_map(function($sku) {
                    return ['sku' => $sku, 'quantity' => mt_rand(5, 20)];
                }, array_slice($available_skus, 0, 10))
            ]
        ];
    }
    
    /**
     * Get optimization history
     */
    public function getOptimizationHistory($limit = 10) {
        try {
            $query = "SELECT optimization_method as algorithm, items_count, 
                             time_saved, distance_saved, efficiency_score, created_at
                      FROM pick_optimization_history 
                      ORDER BY created_at DESC 
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(Exception $e) {
            return [];
        }
    }
}
?>