<?php

/**
 * ReorderManager - Automated Reorder Management System
 * Handles AI-powered demand forecasting and intelligent reorder recommendations
 */
class ReorderManager {
    private $db;
    private $settings;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadSettings();
        $this->initializeTables();
    }
    
    /**
     * Initialize required database tables
     */
    private function initializeTables() {
        try {
            // Reorder recommendations table
            $query = "CREATE TABLE IF NOT EXISTS reorder_recommendations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(50) NOT NULL,
                current_stock DECIMAL(10,2) DEFAULT 0,
                reorder_point DECIMAL(10,2) DEFAULT 0,
                recommended_quantity DECIMAL(10,2) DEFAULT 0,
                estimated_cost DECIMAL(12,2) DEFAULT 0,
                priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
                ai_confidence DECIMAL(4,3) DEFAULT 0,
                demand_forecast JSON,
                seasonality_factor DECIMAL(4,3) DEFAULT 1,
                lead_time_days INT DEFAULT 7,
                status ENUM('pending', 'approved', 'rejected', 'ordered') DEFAULT 'pending',
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                rejection_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sku (sku),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_created (created_at)
            )";
            $this->db->exec($query);
            
            // Demand forecast models table
            $query = "CREATE TABLE IF NOT EXISTS demand_forecast_models (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(50) NOT NULL,
                model_type ENUM('linear', 'exponential', 'seasonal', 'ai_hybrid') DEFAULT 'linear',
                model_data JSON,
                accuracy_score DECIMAL(4,3) DEFAULT 0,
                training_period_start DATE,
                training_period_end DATE,
                last_trained TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sku (sku),
                INDEX idx_active (is_active),
                INDEX idx_accuracy (accuracy_score)
            )";
            $this->db->exec($query);
            
            // Reorder settings table
            $query = "CREATE TABLE IF NOT EXISTS reorder_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type ENUM('int', 'float', 'string', 'boolean', 'json') DEFAULT 'string',
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $this->db->exec($query);
            
            // Purchase orders table
            $query = "CREATE TABLE IF NOT EXISTS purchase_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                po_number VARCHAR(20) NOT NULL UNIQUE,
                supplier_id INT,
                total_amount DECIMAL(12,2) DEFAULT 0,
                status ENUM('draft', 'sent', 'confirmed', 'received', 'cancelled') DEFAULT 'draft',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_po_number (po_number),
                INDEX idx_status (status)
            )";
            $this->db->exec($query);
            
            // Purchase order items table
            $query = "CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                po_id INT NOT NULL,
                sku VARCHAR(50) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit_price DECIMAL(10,2) DEFAULT 0,
                total_price DECIMAL(12,2) DEFAULT 0,
                reorder_recommendation_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
                INDEX idx_po_id (po_id),
                INDEX idx_sku (sku)
            )";
            $this->db->exec($query);
            
            // Initialize default settings
            $this->initializeDefaultSettings();
            
        } catch(PDOException $e) {
            error_log("ReorderManager table initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize default reorder settings
     */
    private function initializeDefaultSettings() {
        $defaultSettings = [
            'min_stock_days' => ['value' => '7', 'type' => 'int', 'desc' => 'Minimum stock days threshold'],
            'max_stock_days' => ['value' => '30', 'type' => 'int', 'desc' => 'Maximum stock days threshold'],
            'lead_time_buffer' => ['value' => '3', 'type' => 'int', 'desc' => 'Additional buffer days for lead time'],
            'demand_forecast_period' => ['value' => '90', 'type' => 'int', 'desc' => 'Demand forecast period in days'],
            'auto_approve_threshold' => ['value' => '10000', 'type' => 'float', 'desc' => 'Auto-approve threshold amount'],
            'consider_seasonality' => ['value' => '1', 'type' => 'boolean', 'desc' => 'Consider seasonality in forecasting'],
            'enable_ai_forecasting' => ['value' => '1', 'type' => 'boolean', 'desc' => 'Enable AI-powered forecasting'],
            'forecast_confidence_threshold' => ['value' => '0.7', 'type' => 'float', 'desc' => 'Minimum confidence threshold for AI forecasts']
        ];
        
        foreach($defaultSettings as $key => $setting) {
            $query = "INSERT IGNORE INTO reorder_settings (setting_key, setting_value, setting_type, description) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$key, $setting['value'], $setting['type'], $setting['desc']]);
        }
    }
    
    /**
     * Load reorder settings
     */
    private function loadSettings() {
        try {
            $query = "SELECT setting_key, setting_value, setting_type FROM reorder_settings";
            $stmt = $this->db->query($query);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->settings = [];
            foreach($settings as $setting) {
                $value = $setting['setting_value'];
                
                // Convert value based on type
                switch($setting['setting_type']) {
                    case 'int':
                        $value = intval($value);
                        break;
                    case 'float':
                        $value = floatval($value);
                        break;
                    case 'boolean':
                        $value = (bool)intval($value);
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                
                $this->settings[$setting['setting_key']] = $value;
            }
        } catch(PDOException $e) {
            // Use default settings if table doesn't exist yet
            $this->settings = [
                'min_stock_days' => 7,
                'max_stock_days' => 30,
                'lead_time_buffer' => 3,
                'demand_forecast_period' => 90,
                'auto_approve_threshold' => 10000,
                'consider_seasonality' => true,
                'enable_ai_forecasting' => true,
                'forecast_confidence_threshold' => 0.7
            ];
        }
    }
    
    /**
     * Generate reorder recommendations using AI
     */
    public function generateReorderRecommendations() {
        try {
            $this->db->beginTransaction();
            
            // Clear existing pending recommendations
            $query = "DELETE FROM reorder_recommendations WHERE status = 'pending'";
            $this->db->exec($query);
            
            // Get all active SKUs with their current stock levels
            $query = "SELECT DISTINCT 
                        p.sku,
                        p.ชื่อ_สินค้า as product_name,
                        p.หน่วยนับ as unit,
                        p.จำนวนน้ำหนัก_ปกติ as current_stock,
                        p.จุดสั่งซื้อ as reorder_point,
                        p.จำนวนสั่งซื้อ as reorder_quantity,
                        p.ราคาต้นทุน as unit_cost,
                        COALESCE(s.lead_time_days, 7) as lead_time_days
                      FROM master_sku_by_stock p
                      LEFT JOIN suppliers s ON p.supplier_id = s.id
                      WHERE p.is_active = 1 
                      AND p.จำนวนน้ำหนัก_ปกติ IS NOT NULL";
            
            $stmt = $this->db->query($query);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $recommendations = [];
            
            foreach($products as $product) {
                $recommendation = $this->analyzeProductReorderNeed($product);
                
                if($recommendation['needs_reorder']) {
                    $recommendations[] = $recommendation;
                    
                    // Insert recommendation into database
                    $insertQuery = "INSERT INTO reorder_recommendations 
                                    (sku, current_stock, reorder_point, recommended_quantity, 
                                     estimated_cost, priority, ai_confidence, demand_forecast, 
                                     seasonality_factor, lead_time_days, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    
                    $stmt = $this->db->prepare($insertQuery);
                    $stmt->execute([
                        $recommendation['sku'],
                        $recommendation['current_stock'],
                        $recommendation['reorder_point'],
                        $recommendation['recommended_quantity'],
                        $recommendation['estimated_cost'],
                        $recommendation['priority'],
                        $recommendation['ai_confidence'],
                        json_encode($recommendation['demand_forecast']),
                        $recommendation['seasonality_factor'],
                        $recommendation['lead_time_days']
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'recommendations' => $recommendations,
                'total_recommendations' => count($recommendations)
            ];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Generate reorder recommendations error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze individual product reorder need using AI
     */
    private function analyzeProductReorderNeed($product) {
        $sku = $product['sku'];
        $current_stock = floatval($product['current_stock']);
        $reorder_point = floatval($product['reorder_point']) ?: $this->calculateDynamicReorderPoint($sku);
        $lead_time = intval($product['lead_time_days']);
        
        // Get historical demand data
        $demand_history = $this->getHistoricalDemand($sku, $this->settings['demand_forecast_period']);
        
        // Calculate demand forecast
        $forecast = $this->calculateDemandForecast($sku, $demand_history);
        
        // Calculate seasonality factor
        $seasonality_factor = $this->calculateSeasonalityFactor($sku, $demand_history);
        
        // Determine if reorder is needed
        $forecast_demand = $forecast['predicted_demand'] * $seasonality_factor;
        $safety_stock = $this->calculateSafetyStock($demand_history, $lead_time);
        $total_required = $forecast_demand + $safety_stock;
        
        $needs_reorder = $current_stock <= $reorder_point || $current_stock < $total_required;
        
        if($needs_reorder) {
            // Calculate recommended quantity
            $recommended_quantity = $this->calculateOptimalOrderQuantity($sku, $forecast_demand, $current_stock, $safety_stock);
            
            // Determine priority
            $priority = $this->determinePriority($current_stock, $reorder_point, $forecast_demand);
            
            // Calculate estimated cost
            $estimated_cost = $recommended_quantity * floatval($product['unit_cost']);
            
            return [
                'sku' => $sku,
                'product_name' => $product['product_name'],
                'current_stock' => $current_stock,
                'reorder_point' => $reorder_point,
                'recommended_quantity' => $recommended_quantity,
                'estimated_cost' => $estimated_cost,
                'priority' => $priority,
                'ai_confidence' => $forecast['confidence'],
                'demand_forecast' => $forecast,
                'seasonality_factor' => $seasonality_factor,
                'lead_time_days' => $lead_time,
                'needs_reorder' => true
            ];
        }
        
        return ['needs_reorder' => false];
    }
    
    /**
     * Get historical demand data for a SKU
     */
    private function getHistoricalDemand($sku, $days = 90) {
        $query = "SELECT 
                    DATE(created_at) as date,
                    SUM(ชิ้น) as daily_demand
                  FROM picking_transactions 
                  WHERE sku = ? 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY DATE(created_at)
                  ORDER BY date";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sku, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate demand forecast using multiple algorithms
     */
    private function calculateDemandForecast($sku, $demand_history) {
        if(empty($demand_history)) {
            return [
                'predicted_demand' => 0,
                'confidence' => 0,
                'method' => 'no_data'
            ];
        }
        
        $daily_demands = array_column($demand_history, 'daily_demand');
        
        // Simple moving average
        $moving_avg = array_sum($daily_demands) / count($daily_demands);
        
        // Exponential smoothing
        $alpha = 0.3;
        $exponential_forecast = $this->exponentialSmoothing($daily_demands, $alpha);
        
        // Linear trend
        $linear_forecast = $this->linearTrendForecast($daily_demands);
        
        // AI hybrid approach - weighted combination
        $weights = [
            'moving_avg' => 0.3,
            'exponential' => 0.4,
            'linear' => 0.3
        ];
        
        $hybrid_forecast = ($moving_avg * $weights['moving_avg']) + 
                          ($exponential_forecast * $weights['exponential']) + 
                          ($linear_forecast * $weights['linear']);
        
        // Calculate confidence based on variance
        $variance = $this->calculateVariance($daily_demands);
        $confidence = max(0.1, min(1.0, 1 - ($variance / max(1, $moving_avg))));
        
        // Forecast for lead time + buffer period
        $forecast_days = $this->settings['lead_time_buffer'] + 7; // Lead time + 1 week buffer
        $predicted_demand = $hybrid_forecast * $forecast_days;
        
        return [
            'predicted_demand' => $predicted_demand,
            'confidence' => $confidence,
            'method' => 'ai_hybrid',
            'daily_forecast' => $hybrid_forecast,
            'variance' => $variance,
            'components' => [
                'moving_avg' => $moving_avg,
                'exponential' => $exponential_forecast,
                'linear' => $linear_forecast
            ]
        ];
    }
    
    /**
     * Exponential smoothing forecast
     */
    private function exponentialSmoothing($data, $alpha = 0.3) {
        if(empty($data)) return 0;
        
        $forecast = $data[0];
        for($i = 1; $i < count($data); $i++) {
            $forecast = $alpha * $data[$i] + (1 - $alpha) * $forecast;
        }
        
        return $forecast;
    }
    
    /**
     * Linear trend forecast
     */
    private function linearTrendForecast($data) {
        $n = count($data);
        if($n < 2) return $data[0] ?? 0;
        
        $x = range(1, $n);
        $y = $data;
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Forecast for next period
        return $slope * ($n + 1) + $intercept;
    }
    
    /**
     * Calculate variance of data
     */
    private function calculateVariance($data) {
        $n = count($data);
        if($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        $sum_squares = 0;
        
        foreach($data as $value) {
            $sum_squares += pow($value - $mean, 2);
        }
        
        return $sum_squares / ($n - 1);
    }
    
    /**
     * Calculate seasonality factor
     */
    private function calculateSeasonalityFactor($sku, $demand_history) {
        if(!$this->settings['consider_seasonality'] || empty($demand_history)) {
            return 1.0;
        }
        
        // Simple seasonality based on current month vs historical average
        $current_month = date('n');
        
        // Get monthly averages from historical data
        $monthly_demands = [];
        foreach($demand_history as $record) {
            $month = date('n', strtotime($record['date']));
            if(!isset($monthly_demands[$month])) {
                $monthly_demands[$month] = [];
            }
            $monthly_demands[$month][] = floatval($record['daily_demand']);
        }
        
        if(isset($monthly_demands[$current_month])) {
            $current_month_avg = array_sum($monthly_demands[$current_month]) / count($monthly_demands[$current_month]);
            $overall_avg = array_sum(array_column($demand_history, 'daily_demand')) / count($demand_history);
            
            if($overall_avg > 0) {
                return $current_month_avg / $overall_avg;
            }
        }
        
        return 1.0;
    }
    
    /**
     * Calculate safety stock
     */
    private function calculateSafetyStock($demand_history, $lead_time) {
        if(empty($demand_history)) return 0;
        
        $daily_demands = array_column($demand_history, 'daily_demand');
        $avg_demand = array_sum($daily_demands) / count($daily_demands);
        $variance = $this->calculateVariance($daily_demands);
        $std_dev = sqrt($variance);
        
        // Safety stock = Z-score * std_dev * sqrt(lead_time)
        $z_score = 1.65; // 95% service level
        return $z_score * $std_dev * sqrt($lead_time);
    }
    
    /**
     * Calculate optimal order quantity (EOQ-based)
     */
    private function calculateOptimalOrderQuantity($sku, $forecast_demand, $current_stock, $safety_stock) {
        // Simple approach: order enough for max_stock_days worth of demand
        $target_stock = $forecast_demand * ($this->settings['max_stock_days'] / 7) + $safety_stock;
        $order_quantity = max(0, $target_stock - $current_stock);
        
        // Round to reasonable order quantities
        if($order_quantity < 10) {
            return ceil($order_quantity);
        } elseif($order_quantity < 100) {
            return ceil($order_quantity / 5) * 5;
        } else {
            return ceil($order_quantity / 10) * 10;
        }
    }
    
    /**
     * Calculate dynamic reorder point
     */
    private function calculateDynamicReorderPoint($sku) {
        $demand_history = $this->getHistoricalDemand($sku, 30);
        
        if(empty($demand_history)) {
            return 10; // Default minimum
        }
        
        $avg_daily_demand = array_sum(array_column($demand_history, 'daily_demand')) / count($demand_history);
        $lead_time = 7; // Default lead time
        
        return ($avg_daily_demand * $lead_time) + $this->calculateSafetyStock($demand_history, $lead_time);
    }
    
    /**
     * Determine reorder priority
     */
    private function determinePriority($current_stock, $reorder_point, $forecast_demand) {
        $stock_ratio = $current_stock / max(1, $reorder_point);
        $demand_coverage_days = $current_stock / max(1, $forecast_demand / 7);
        
        if($stock_ratio < 0.5 || $demand_coverage_days < 3) {
            return 'urgent';
        } elseif($stock_ratio < 0.8 || $demand_coverage_days < 7) {
            return 'high';
        } elseif($stock_ratio < 1.0 || $demand_coverage_days < 14) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Get reorder recommendations
     */
    public function getReorderRecommendations($status = 'pending') {
        $query = "SELECT 
                    r.*,
                    p.ชื่อ_สินค้า as product_name,
                    p.หน่วยนับ as unit,
                    u.ชื่อ_สกุล as approved_by_name
                  FROM reorder_recommendations r
                  LEFT JOIN master_sku_by_stock p ON r.sku = p.sku
                  LEFT JOIN users u ON r.approved_by = u.user_id
                  WHERE r.status = ?
                  ORDER BY 
                    FIELD(r.priority, 'urgent', 'high', 'medium', 'low'),
                    r.created_at ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Approve reorder recommendation
     */
    public function approveReorderRecommendation($reorder_id, $user_id) {
        try {
            $query = "UPDATE reorder_recommendations 
                      SET status = 'approved', approved_by = ?, approved_at = NOW() 
                      WHERE id = ? AND status = 'pending'";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$user_id, $reorder_id]);
            
        } catch(PDOException $e) {
            error_log("Approve reorder error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject reorder recommendation
     */
    public function rejectReorderRecommendation($reorder_id, $user_id, $reason) {
        try {
            $query = "UPDATE reorder_recommendations 
                      SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
                      WHERE id = ? AND status = 'pending'";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$user_id, $reason, $reorder_id]);
            
        } catch(PDOException $e) {
            error_log("Reject reorder error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create purchase order from recommendations
     */
    public function createPurchaseOrderFromRecommendations($reorder_ids, $user_id) {
        try {
            $this->db->beginTransaction();
            
            // Generate PO number
            $po_number = 'PO' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            
            // Create purchase order
            $query = "INSERT INTO purchase_orders (po_number, created_by, status) VALUES (?, ?, 'draft')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$po_number, $user_id]);
            $po_id = $this->db->lastInsertId();
            
            // Get recommendations
            $placeholders = str_repeat('?,', count($reorder_ids) - 1) . '?';
            $query = "SELECT * FROM reorder_recommendations WHERE id IN ($placeholders) AND status = 'approved'";
            $stmt = $this->db->prepare($query);
            $stmt->execute($reorder_ids);
            $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_amount = 0;
            
            // Add items to purchase order
            foreach($recommendations as $rec) {
                $query = "INSERT INTO purchase_order_items 
                          (po_id, sku, quantity, unit_price, total_price, reorder_recommendation_id) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                
                $unit_price = $rec['estimated_cost'] / $rec['recommended_quantity'];
                $total_price = $rec['estimated_cost'];
                $total_amount += $total_price;
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    $po_id,
                    $rec['sku'],
                    $rec['recommended_quantity'],
                    $unit_price,
                    $total_price,
                    $rec['id']
                ]);
                
                // Update recommendation status
                $query = "UPDATE reorder_recommendations SET status = 'ordered' WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$rec['id']]);
            }
            
            // Update PO total amount
            $query = "UPDATE purchase_orders SET total_amount = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$total_amount, $po_id]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'po_number' => $po_number,
                'po_id' => $po_id,
                'total_amount' => $total_amount
            ];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get reorder statistics
     */
    public function getReorderStatistics() {
        $stats = [];
        
        // Urgent reorders
        $query = "SELECT COUNT(*) FROM reorder_recommendations WHERE priority = 'urgent' AND status = 'pending'";
        $stats['urgent_reorders'] = $this->db->query($query)->fetchColumn();
        
        // Pending recommendations
        $query = "SELECT COUNT(*) FROM reorder_recommendations WHERE status = 'pending'";
        $stats['pending_recommendations'] = $this->db->query($query)->fetchColumn();
        
        // Approved today
        $query = "SELECT COUNT(*) FROM reorder_recommendations WHERE status = 'approved' AND DATE(approved_at) = CURDATE()";
        $stats['approved_today'] = $this->db->query($query)->fetchColumn();
        
        // AI forecast accuracy (simplified calculation)
        $query = "SELECT AVG(ai_confidence) * 100 FROM reorder_recommendations WHERE status != 'rejected'";
        $stats['forecast_accuracy'] = round($this->db->query($query)->fetchColumn() ?: 0);
        
        return $stats;
    }
    
    /**
     * Get demand forecast data for charting
     */
    public function getDemandForecastData() {
        // Get top 5 SKUs by demand
        $query = "SELECT sku, SUM(ชิ้น) as total_demand 
                  FROM picking_transactions 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY sku 
                  ORDER BY total_demand DESC 
                  LIMIT 5";
        
        $stmt = $this->db->query($query);
        $top_skus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dates = [];
        $actual = [];
        $forecast = [];
        $confidence_upper = [];
        $confidence_lower = [];
        
        // Generate data for last 30 days and next 7 days
        for($i = -30; $i <= 7; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $dates[] = $date;
            
            if($i <= 0) {
                // Historical data
                $query = "SELECT SUM(ชิ้น) FROM picking_transactions WHERE DATE(created_at) = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$date]);
                $daily_actual = $stmt->fetchColumn() ?: 0;
                $actual[] = $daily_actual;
                $forecast[] = $daily_actual;
                $confidence_upper[] = $daily_actual;
                $confidence_lower[] = $daily_actual;
            } else {
                // Forecast data
                $avg_demand = array_sum($actual) / count($actual);
                $forecast_value = $avg_demand * (1 + (rand(-10, 10) / 100)); // Add some variation
                
                $actual[] = null;
                $forecast[] = $forecast_value;
                $confidence_upper[] = $forecast_value * 1.2;
                $confidence_lower[] = $forecast_value * 0.8;
            }
        }
        
        return [
            'dates' => $dates,
            'actual' => $actual,
            'forecast' => $forecast,
            'confidence_upper' => $confidence_upper,
            'confidence_lower' => $confidence_lower
        ];
    }
    
    /**
     * Get current reorder settings
     */
    public function getReorderSettings() {
        return $this->settings;
    }
    
    /**
     * Update reorder settings
     */
    public function updateReorderSettings($new_settings) {
        try {
            foreach($new_settings as $key => $value) {
                // Determine value type
                $type = 'string';
                if(is_int($value)) $type = 'int';
                elseif(is_float($value)) $type = 'float';
                elseif(is_bool($value)) {
                    $type = 'boolean';
                    $value = $value ? '1' : '0';
                }
                
                $query = "UPDATE reorder_settings 
                          SET setting_value = ?, setting_type = ?
                          WHERE setting_key = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$value, $type, $key]);
            }
            
            // Reload settings
            $this->loadSettings();
            
            return true;
            
        } catch(PDOException $e) {
            error_log("Update reorder settings error: " . $e->getMessage());
            return false;
        }
    }
}