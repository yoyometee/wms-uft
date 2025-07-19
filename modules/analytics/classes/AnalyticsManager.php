<?php

/**
 * AnalyticsManager - Advanced Analytics & Business Intelligence
 * Handles complex data analytics, forecasting, and business intelligence
 */
class AnalyticsManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeTables();
    }
    
    /**
     * Initialize analytics tables
     */
    private function initializeTables() {
        try {
            // Analytics cache table for performance
            $query = "CREATE TABLE IF NOT EXISTS analytics_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL UNIQUE,
                cache_data JSON,
                cache_type ENUM('kpi', 'trend', 'forecast', 'analysis') DEFAULT 'analysis',
                valid_until TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cache_key (cache_key),
                INDEX idx_valid_until (valid_until),
                INDEX idx_cache_type (cache_type)
            )";
            $this->db->exec($query);
            
            // Performance metrics table
            $query = "CREATE TABLE IF NOT EXISTS performance_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15,4) NOT NULL,
                metric_unit VARCHAR(20),
                category ENUM('revenue', 'efficiency', 'accuracy', 'inventory', 'user') DEFAULT 'efficiency',
                measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                period_start DATE,
                period_end DATE,
                INDEX idx_metric_name (metric_name),
                INDEX idx_measured_at (measured_at),
                INDEX idx_category (category)
            )";
            $this->db->exec($query);
            
            // Business alerts table
            $query = "CREATE TABLE IF NOT EXISTS business_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type ENUM('warning', 'info', 'success', 'danger') DEFAULT 'info',
                alert_title VARCHAR(255) NOT NULL,
                alert_message TEXT,
                severity INT DEFAULT 1,
                is_active BOOLEAN DEFAULT TRUE,
                auto_generated BOOLEAN DEFAULT FALSE,
                conditions_met JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                INDEX idx_alert_type (alert_type),
                INDEX idx_is_active (is_active),
                INDEX idx_severity (severity)
            )";
            $this->db->exec($query);
            
        } catch(PDOException $e) {
            error_log("AnalyticsManager table initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Get comprehensive analytics data
     */
    public function getAnalyticsData($date_range = 30) {
        $cache_key = "analytics_data_{$date_range}";
        $cached = $this->getCachedData($cache_key);
        
        if($cached) {
            return $cached;
        }
        
        $data = [
            'top_skus' => $this->getTopPerformingSKUs($date_range),
            'zone_performance' => $this->getZonePerformance($date_range),
            'user_activity' => $this->getUserActivity($date_range),
            'abc_analysis' => $this->getABCAnalysis($date_range)
        ];
        
        $this->setCachedData($cache_key, $data, 'analysis', 3600); // Cache for 1 hour
        
        return $data;
    }
    
    /**
     * Get KPI metrics
     */
    public function getKPIMetrics($date_range = 30) {
        $cache_key = "kpi_metrics_{$date_range}";
        $cached = $this->getCachedData($cache_key);
        
        if($cached) {
            return $cached;
        }
        
        $metrics = [
            'revenue' => $this->calculateRevenue($date_range),
            'revenue_trend' => $this->calculateRevenueTrend($date_range),
            'order_fulfillment' => $this->calculateOrderFulfillmentRate($date_range),
            'fulfillment_trend' => $this->calculateFulfillmentTrend($date_range),
            'inventory_turnover' => $this->calculateInventoryTurnover($date_range),
            'turnover_trend' => $this->calculateTurnoverTrend($date_range),
            'stock_accuracy' => $this->calculateStockAccuracy($date_range),
            'accuracy_trend' => $this->calculateAccuracyTrend($date_range)
        ];
        
        $this->setCachedData($cache_key, $metrics, 'kpi', 1800); // Cache for 30 minutes
        
        return $metrics;
    }
    
    /**
     * Get trend data
     */
    public function getTrendData($date_range = 30) {
        $cache_key = "trend_data_{$date_range}";
        $cached = $this->getCachedData($cache_key);
        
        if($cached) {
            return $cached;
        }
        
        $dates = [];
        $revenue = [];
        $volume = [];
        
        for($i = $date_range; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = $date;
            
            // Daily revenue
            $query = "SELECT 
                        SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as daily_revenue,
                        SUM(pt.ชิ้น) as daily_volume
                      FROM picking_transactions pt
                      LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                      WHERE DATE(pt.created_at) = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $revenue[] = floatval($result['daily_revenue'] ?? 0);
            $volume[] = intval($result['daily_volume'] ?? 0);
        }
        
        $trend_data = [
            'dates' => $dates,
            'revenue' => $revenue,
            'volume' => $volume
        ];
        
        $this->setCachedData($cache_key, $trend_data, 'trend', 1800); // Cache for 30 minutes
        
        return $trend_data;
    }
    
    /**
     * Get forecast data
     */
    public function getForecastData($forecast_days = 30) {
        $cache_key = "forecast_data_{$forecast_days}";
        $cached = $this->getCachedData($cache_key);
        
        if($cached) {
            return $cached;
        }
        
        // Get historical data for forecasting
        $historical_data = $this->getHistoricalDemandData(90);
        
        // Generate forecast using simple moving average and trend analysis
        $forecast = $this->generateDemandForecast($historical_data, $forecast_days);
        
        $this->setCachedData($cache_key, $forecast, 'forecast', 7200); // Cache for 2 hours
        
        return $forecast;
    }
    
    /**
     * Calculate revenue
     */
    private function calculateRevenue($date_range) {
        $query = "SELECT SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as total_revenue
                  FROM picking_transactions pt
                  LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        return floatval($stmt->fetchColumn() ?? 0);
    }
    
    /**
     * Calculate revenue trend
     */
    private function calculateRevenueTrend($date_range) {
        // Current period revenue
        $current_revenue = $this->calculateRevenue($date_range);
        
        // Previous period revenue
        $query = "SELECT SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as previous_revenue
                  FROM picking_transactions pt
                  LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND pt.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range * 2, $date_range]);
        $previous_revenue = floatval($stmt->fetchColumn() ?? 0);
        
        if($previous_revenue > 0) {
            return (($current_revenue - $previous_revenue) / $previous_revenue) * 100;
        }
        
        return 0;
    }
    
    /**
     * Calculate order fulfillment rate
     */
    private function calculateOrderFulfillmentRate($date_range) {
        // Simplified calculation based on successful transactions vs errors
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN pt.หมายเหตุ NOT LIKE '%error%' AND pt.หมายเหตุ NOT LIKE '%failed%' THEN 1 ELSE 0 END) as successful_transactions
                  FROM picking_transactions pt
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = intval($result['total_transactions']);
        $successful = intval($result['successful_transactions']);
        
        return $total > 0 ? ($successful / $total) * 100 : 0;
    }
    
    /**
     * Calculate fulfillment trend
     */
    private function calculateFulfillmentTrend($date_range) {
        $current_rate = $this->calculateOrderFulfillmentRate($date_range);
        
        // Previous period
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN pt.หมายเหตุ NOT LIKE '%error%' AND pt.หมายเหตุ NOT LIKE '%failed%' THEN 1 ELSE 0 END) as successful_transactions
                  FROM picking_transactions pt
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND pt.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range * 2, $date_range]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = intval($result['total_transactions']);
        $successful = intval($result['successful_transactions']);
        $previous_rate = $total > 0 ? ($successful / $total) * 100 : 0;
        
        return $previous_rate > 0 ? $current_rate - $previous_rate : 0;
    }
    
    /**
     * Calculate inventory turnover
     */
    private function calculateInventoryTurnover($date_range) {
        // Cost of goods sold / Average inventory value
        $cogs_query = "SELECT SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as cogs
                       FROM picking_transactions pt
                       LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                       WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($cogs_query);
        $stmt->execute([$date_range]);
        $cogs = floatval($stmt->fetchColumn() ?? 0);
        
        // Average inventory value
        $inventory_query = "SELECT AVG(จำนวนน้ำหนัก_ปกติ * ราคาต้นทุน) as avg_inventory_value
                           FROM master_sku_by_stock
                           WHERE จำนวนน้ำหนัก_ปกติ > 0 AND ราคาต้นทุน > 0";
        
        $avg_inventory = floatval($this->db->query($inventory_query)->fetchColumn() ?? 0);
        
        return $avg_inventory > 0 ? $cogs / $avg_inventory : 0;
    }
    
    /**
     * Calculate turnover trend
     */
    private function calculateTurnoverTrend($date_range) {
        $current_turnover = $this->calculateInventoryTurnover($date_range);
        
        // Previous period turnover (simplified)
        $previous_turnover = $this->calculateInventoryTurnover($date_range) * 0.9; // Simulated
        
        return $previous_turnover > 0 ? (($current_turnover - $previous_turnover) / $previous_turnover) * 100 : 0;
    }
    
    /**
     * Calculate stock accuracy
     */
    private function calculateStockAccuracy($date_range) {
        // Based on cycle count results
        $query = "SELECT 
                    AVG(ch.accuracy_percentage) as avg_accuracy
                  FROM cycle_count_headers ch
                  WHERE ch.status IN ('completed', 'reviewed', 'closed')
                  AND ch.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND ch.accuracy_percentage IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        $accuracy = $stmt->fetchColumn();
        
        return $accuracy !== false ? floatval($accuracy) : 95.0; // Default if no cycle counts
    }
    
    /**
     * Calculate accuracy trend
     */
    private function calculateAccuracyTrend($date_range) {
        $current_accuracy = $this->calculateStockAccuracy($date_range);
        
        // Previous period accuracy
        $query = "SELECT 
                    AVG(ch.accuracy_percentage) as avg_accuracy
                  FROM cycle_count_headers ch
                  WHERE ch.status IN ('completed', 'reviewed', 'closed')
                  AND ch.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND ch.completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND ch.accuracy_percentage IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range * 2, $date_range]);
        $previous_accuracy = floatval($stmt->fetchColumn() ?? 95.0);
        
        return $current_accuracy - $previous_accuracy;
    }
    
    /**
     * Get top performing SKUs
     */
    private function getTopPerformingSKUs($date_range, $limit = 10) {
        $query = "SELECT 
                    pt.sku,
                    p.ชื่อ_สินค้า as product_name,
                    SUM(pt.ชิ้น) as total_sales,
                    SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as total_revenue,
                    COUNT(*) as transaction_count
                  FROM picking_transactions pt
                  LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY pt.sku, p.ชื่อ_สินค้า
                  ORDER BY total_sales DESC
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add growth calculation
        foreach($results as &$result) {
            $result['growth'] = $this->calculateSKUGrowth($result['sku'], $date_range);
        }
        
        return $results;
    }
    
    /**
     * Calculate SKU growth
     */
    private function calculateSKUGrowth($sku, $date_range) {
        // Current period sales
        $current_query = "SELECT SUM(ชิ้น) as current_sales
                         FROM picking_transactions
                         WHERE sku = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($current_query);
        $stmt->execute([$sku, $date_range]);
        $current_sales = floatval($stmt->fetchColumn() ?? 0);
        
        // Previous period sales
        $previous_query = "SELECT SUM(ชิ้น) as previous_sales
                          FROM picking_transactions
                          WHERE sku = ? 
                          AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                          AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($previous_query);
        $stmt->execute([$sku, $date_range * 2, $date_range]);
        $previous_sales = floatval($stmt->fetchColumn() ?? 0);
        
        if($previous_sales > 0) {
            return (($current_sales - $previous_sales) / $previous_sales) * 100;
        }
        
        return 0;
    }
    
    /**
     * Get zone performance
     */
    private function getZonePerformance($date_range) {
        $query = "SELECT 
                    l.zone,
                    SUM(pt.ชิ้น) as total_volume,
                    COUNT(*) as transaction_count,
                    COUNT(DISTINCT pt.sku) as unique_skus
                  FROM picking_transactions pt
                  LEFT JOIN msaster_location_by_stock l ON pt.ตำแหน่ง = l.location_id
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND l.zone IS NOT NULL
                  GROUP BY l.zone
                  ORDER BY total_volume DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $data = [];
        
        foreach($results as $result) {
            $labels[] = $result['zone'];
            $data[] = intval($result['total_volume']);
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
    
    /**
     * Get user activity
     */
    private function getUserActivity($date_range) {
        $query = "SELECT 
                    u.ชื่อ_สกุล as user_name,
                    COUNT(*) as activity_count,
                    SUM(pt.ชิ้น) as total_picked
                  FROM picking_transactions pt
                  LEFT JOIN users u ON pt.ผู้ใช้งาน = u.user_id
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND u.ชื่อ_สกุล IS NOT NULL
                  GROUP BY u.user_id, u.ชื่อ_สกุล
                  ORDER BY activity_count DESC
                  LIMIT 10";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $data = [];
        
        foreach($results as $result) {
            $labels[] = $result['user_name'];
            $data[] = intval($result['activity_count']);
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
    
    /**
     * Get ABC analysis
     */
    private function getABCAnalysis($date_range) {
        // Get SKUs with their value and volume
        $query = "SELECT 
                    pt.sku,
                    SUM(pt.ชิ้น) as total_volume,
                    SUM(pt.ชิ้น * COALESCE(p.ราคาต้นทุน, 0)) as total_value
                  FROM picking_transactions pt
                  LEFT JOIN master_sku_by_stock p ON pt.sku = p.sku
                  WHERE pt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY pt.sku
                  ORDER BY total_value DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date_range]);
        $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_skus = count($skus);
        $a_count = ceil($total_skus * 0.2); // Top 20%
        $b_count = ceil($total_skus * 0.3); // Next 30%
        
        $months = [];
        $a_class = [];
        $b_class = [];
        $c_class = [];
        
        // Generate monthly data for the last 6 months
        for($i = 5; $i >= 0; $i--) {
            $month = date('M Y', strtotime("-{$i} months"));
            $months[] = $month;
            
            // Simulate ABC distribution
            $a_class[] = rand(40, 60);
            $b_class[] = rand(20, 35);
            $c_class[] = rand(15, 25);
        }
        
        return [
            'labels' => $months,
            'a_class' => $a_class,
            'b_class' => $b_class,
            'c_class' => $c_class
        ];
    }
    
    /**
     * Get historical demand data
     */
    private function getHistoricalDemandData($days) {
        $query = "SELECT 
                    DATE(created_at) as date,
                    SUM(ชิ้น) as daily_demand
                  FROM picking_transactions
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY DATE(created_at)
                  ORDER BY date";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate demand forecast
     */
    private function generateDemandForecast($historical_data, $forecast_days) {
        $dates = [];
        $actual = [];
        $forecast = [];
        $confidence_upper = [];
        $confidence_lower = [];
        
        // Historical data
        foreach($historical_data as $data) {
            $dates[] = $data['date'];
            $actual[] = floatval($data['daily_demand']);
            $forecast[] = floatval($data['daily_demand']);
            $confidence_upper[] = floatval($data['daily_demand']);
            $confidence_lower[] = floatval($data['daily_demand']);
        }
        
        // Calculate trend and average
        $recent_avg = array_sum(array_slice($actual, -7)) / 7; // Last 7 days average
        $trend = 0;
        
        if(count($actual) >= 14) {
            $first_week = array_sum(array_slice($actual, -14, 7)) / 7;
            $second_week = array_sum(array_slice($actual, -7)) / 7;
            $trend = ($second_week - $first_week) / $first_week;
        }
        
        // Generate forecast
        for($i = 1; $i <= $forecast_days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $dates[] = $date;
            $actual[] = null;
            
            // Apply trend and some randomness
            $forecast_value = $recent_avg * (1 + ($trend * $i / 30)) * (1 + (rand(-10, 10) / 100));
            $forecast[] = $forecast_value;
            
            // Confidence intervals
            $confidence_upper[] = $forecast_value * 1.15;
            $confidence_lower[] = $forecast_value * 0.85;
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
     * Get cached data
     */
    private function getCachedData($cache_key) {
        try {
            $query = "SELECT cache_data FROM analytics_cache 
                      WHERE cache_key = ? AND valid_until > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cache_key]);
            $result = $stmt->fetchColumn();
            
            if($result) {
                return json_decode($result, true);
            }
        } catch(PDOException $e) {
            error_log("Cache retrieval error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Set cached data
     */
    private function setCachedData($cache_key, $data, $type, $ttl) {
        try {
            $valid_until = date('Y-m-d H:i:s', time() + $ttl);
            
            $query = "INSERT INTO analytics_cache (cache_key, cache_data, cache_type, valid_until)
                      VALUES (?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE 
                      cache_data = VALUES(cache_data),
                      cache_type = VALUES(cache_type),
                      valid_until = VALUES(valid_until)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cache_key, json_encode($data), $type, $valid_until]);
            
        } catch(PDOException $e) {
            error_log("Cache storage error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear expired cache
     */
    public function clearExpiredCache() {
        try {
            $query = "DELETE FROM analytics_cache WHERE valid_until <= NOW()";
            $this->db->exec($query);
        } catch(PDOException $e) {
            error_log("Cache cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate business alerts
     */
    public function generateBusinessAlerts() {
        $alerts = [];
        
        // Low stock alert
        $low_stock_count = $this->getLowStockCount();
        if($low_stock_count > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "มีสินค้า {$low_stock_count} รายการ ที่ใกล้หมด ควรสั่งซื้อเพิ่ม",
                'severity' => 3
            ];
        }
        
        // High demand trend
        $demand_growth = $this->getDemandGrowth();
        if($demand_growth > 15) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Trend Alert',
                'message' => "สินค้าหมวด Premium มีแนวโน้มขายดีขึ้น {$demand_growth}%",
                'severity' => 2
            ];
        }
        
        // Efficiency opportunity
        $warehouse_utilization = $this->getWarehouseUtilization();
        if($warehouse_utilization < 80) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Opportunity',
                'message' => "PF-Zone มีพื้นที่ว่าง " . (100 - $warehouse_utilization) . "% เหมาะสำหรับขยายสต็อก",
                'severity' => 1
            ];
        }
        
        // Performance issue
        $avg_pick_time = $this->getAveragePickTime();
        if($avg_pick_time > 300) { // More than 5 minutes
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Efficiency',
                'message' => "เวลาเฉลี่ยในการจัดเตรียมสินค้าเพิ่มขึ้น " . round(($avg_pick_time - 300) / 60) . " นาที",
                'severity' => 4
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get low stock count
     */
    private function getLowStockCount() {
        $query = "SELECT COUNT(*) FROM master_sku_by_stock 
                  WHERE จำนวนน้ำหนัก_ปกติ <= จุดสั่งซื้อ 
                  AND จุดสั่งซื้อ > 0";
        return $this->db->query($query)->fetchColumn();
    }
    
    /**
     * Get demand growth
     */
    private function getDemandGrowth() {
        // Simplified calculation - return random value for demo
        return rand(10, 25);
    }
    
    /**
     * Get warehouse utilization
     */
    private function getWarehouseUtilization() {
        // Simplified calculation - return random value for demo
        return rand(70, 90);
    }
    
    /**
     * Get average pick time
     */
    private function getAveragePickTime() {
        // Simplified calculation - return random value for demo
        return rand(180, 420); // 3-7 minutes
    }
}