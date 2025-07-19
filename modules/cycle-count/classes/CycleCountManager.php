<?php

/**
 * CycleCountManager - Cycle Counting System
 * Handles automated and manual cycle counting processes
 */
class CycleCountManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeTables();
    }
    
    /**
     * Initialize required database tables
     */
    private function initializeTables() {
        try {
            // Cycle count headers table
            $query = "CREATE TABLE IF NOT EXISTS cycle_count_headers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                count_type ENUM('manual', 'abc_analysis', 'random', 'location_based', 'product_based', 'auto') DEFAULT 'manual',
                schedule_date DATE NOT NULL,
                status ENUM('new', 'scheduled', 'in_progress', 'completed', 'reviewed', 'closed', 'cancelled') DEFAULT 'new',
                priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
                total_items INT DEFAULT 0,
                counted_items INT DEFAULT 0,
                variance_items INT DEFAULT 0,
                accuracy_percentage DECIMAL(5,2) DEFAULT 0,
                notes TEXT,
                created_by INT NOT NULL,
                assigned_to INT NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                reviewed_by INT NULL,
                reviewed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_schedule_date (schedule_date),
                INDEX idx_created_by (created_by),
                INDEX idx_assigned_to (assigned_to)
            )";
            $this->db->exec($query);
            
            // Cycle count items table
            $query = "CREATE TABLE IF NOT EXISTS cycle_count_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cycle_count_id INT NOT NULL,
                sku VARCHAR(50) NOT NULL,
                location_id VARCHAR(50) NOT NULL,
                expected_quantity DECIMAL(10,2) DEFAULT 0,
                counted_quantity DECIMAL(10,2) NULL,
                variance_quantity DECIMAL(10,2) DEFAULT 0,
                variance_percentage DECIMAL(5,2) DEFAULT 0,
                unit_cost DECIMAL(10,2) DEFAULT 0,
                variance_value DECIMAL(12,2) DEFAULT 0,
                count_status ENUM('pending', 'counted', 'variance', 'adjusted') DEFAULT 'pending',
                notes TEXT,
                counted_by INT NULL,
                counted_at TIMESTAMP NULL,
                adjusted_by INT NULL,
                adjusted_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cycle_count_id) REFERENCES cycle_count_headers(id) ON DELETE CASCADE,
                INDEX idx_cycle_count_id (cycle_count_id),
                INDEX idx_sku (sku),
                INDEX idx_location_id (location_id),
                INDEX idx_count_status (count_status)
            )";
            $this->db->exec($query);
            
            // Cycle count history table
            $query = "CREATE TABLE IF NOT EXISTS cycle_count_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cycle_count_id INT NOT NULL,
                item_id INT NOT NULL,
                action ENUM('created', 'counted', 'adjusted', 'approved', 'rejected') NOT NULL,
                old_value DECIMAL(10,2) NULL,
                new_value DECIMAL(10,2) NULL,
                reason TEXT,
                performed_by INT NOT NULL,
                performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cycle_count_id) REFERENCES cycle_count_headers(id) ON DELETE CASCADE,
                INDEX idx_cycle_count_id (cycle_count_id),
                INDEX idx_item_id (item_id),
                INDEX idx_performed_by (performed_by)
            )";
            $this->db->exec($query);
            
            // Cycle count adjustments table
            $query = "CREATE TABLE IF NOT EXISTS cycle_count_adjustments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cycle_count_id INT NOT NULL,
                item_id INT NOT NULL,
                sku VARCHAR(50) NOT NULL,
                location_id VARCHAR(50) NOT NULL,
                adjustment_quantity DECIMAL(10,2) NOT NULL,
                adjustment_value DECIMAL(12,2) NOT NULL,
                reason TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                created_by INT NOT NULL,
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cycle_count_id) REFERENCES cycle_count_headers(id) ON DELETE CASCADE,
                INDEX idx_cycle_count_id (cycle_count_id),
                INDEX idx_status (status),
                INDEX idx_sku (sku)
            )";
            $this->db->exec($query);
            
        } catch(PDOException $e) {
            error_log("CycleCountManager table initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Create a new cycle count
     */
    public function createCycleCount($params) {
        try {
            $this->db->beginTransaction();
            
            // Insert cycle count header
            $query = "INSERT INTO cycle_count_headers 
                      (count_type, schedule_date, priority, notes, created_by, status) 
                      VALUES (?, ?, ?, ?, ?, 'scheduled')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $params['count_type'],
                $params['schedule_date'],
                $params['priority'],
                $params['notes'],
                $params['created_by']
            ]);
            
            $cycle_count_id = $this->db->lastInsertId();
            
            // Generate items based on count type
            $items = $this->generateCycleCountItems($cycle_count_id, $params);
            
            // Update total items count
            $query = "UPDATE cycle_count_headers SET total_items = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([count($items), $cycle_count_id]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'cycle_id' => $cycle_count_id,
                'items_count' => count($items)
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
     * Generate cycle count items based on type
     */
    private function generateCycleCountItems($cycle_count_id, $params) {
        $items = [];
        
        switch($params['count_type']) {
            case 'manual':
                $items = $this->generateManualItems($cycle_count_id, $params);
                break;
                
            case 'abc_analysis':
                $items = $this->generateABCAnalysisItems($cycle_count_id);
                break;
                
            case 'random':
                $items = $this->generateRandomItems($cycle_count_id);
                break;
                
            case 'location_based':
                $items = $this->generateLocationBasedItems($cycle_count_id, $params['locations']);
                break;
                
            case 'product_based':
                $items = $this->generateProductBasedItems($cycle_count_id, $params['skus']);
                break;
        }
        
        return $items;
    }
    
    /**
     * Generate manual items
     */
    private function generateManualItems($cycle_count_id, $params) {
        $items = [];
        
        // If specific locations provided
        if(!empty($params['locations'])) {
            $placeholders = str_repeat('?,', count($params['locations']) - 1) . '?';
            $location_condition = "AND p.location_id IN ($placeholders)";
            $bind_params = $params['locations'];
        } else {
            $location_condition = "";
            $bind_params = [];
        }
        
        // If specific SKUs provided
        if(!empty($params['skus'])) {
            $skus = array_map('trim', explode(',', $params['skus']));
            $sku_placeholders = str_repeat('?,', count($skus) - 1) . '?';
            $sku_condition = "AND p.sku IN ($sku_placeholders)";
            $bind_params = array_merge($bind_params, $skus);
        } else {
            $sku_condition = "";
        }
        
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  $location_condition $sku_condition
                  ORDER BY p.sku, p.location_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($bind_params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($results as $row) {
            $this->insertCycleCountItem($cycle_count_id, $row);
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Generate ABC analysis items (A-class items)
     */
    private function generateABCAnalysisItems($cycle_count_id) {
        $items = [];
        
        // Get high-value items (top 20% by value)
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    (p.จำนวนน้ำหนัก_ปกติ * p.ราคาต้นทุน) as total_value,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND p.ราคาต้นทุน > 0
                  ORDER BY total_value DESC
                  LIMIT (SELECT CEILING(COUNT(*) * 0.2) FROM msaster_location_by_stock WHERE จำนวนน้ำหนัก_ปกติ > 0)";
        
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($results as $row) {
            $this->insertCycleCountItem($cycle_count_id, $row);
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Generate random items
     */
    private function generateRandomItems($cycle_count_id, $percentage = 10) {
        $items = [];
        
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  ORDER BY RAND()
                  LIMIT (SELECT CEILING(COUNT(*) * ?) FROM msaster_location_by_stock WHERE จำนวนน้ำหนัก_ปกติ > 0)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$percentage / 100]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($results as $row) {
            $this->insertCycleCountItem($cycle_count_id, $row);
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Generate location-based items
     */
    private function generateLocationBasedItems($cycle_count_id, $locations) {
        $items = [];
        
        if(empty($locations)) return $items;
        
        $placeholders = str_repeat('?,', count($locations) - 1) . '?';
        
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND p.location_id IN ($placeholders)
                  ORDER BY p.location_id, p.sku";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($locations);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($results as $row) {
            $this->insertCycleCountItem($cycle_count_id, $row);
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Generate product-based items
     */
    private function generateProductBasedItems($cycle_count_id, $skus_string) {
        $items = [];
        
        if(empty($skus_string)) return $items;
        
        $skus = array_map('trim', explode(',', $skus_string));
        $placeholders = str_repeat('?,', count($skus) - 1) . '?';
        
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND p.sku IN ($placeholders)
                  ORDER BY p.sku, p.location_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($skus);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($results as $row) {
            $this->insertCycleCountItem($cycle_count_id, $row);
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Insert cycle count item
     */
    private function insertCycleCountItem($cycle_count_id, $item_data) {
        $query = "INSERT INTO cycle_count_items 
                  (cycle_count_id, sku, location_id, expected_quantity, unit_cost) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $cycle_count_id,
            $item_data['sku'],
            $item_data['location_id'],
            $item_data['current_quantity'],
            $item_data['unit_cost'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Start cycle count
     */
    public function startCycleCount($cycle_count_id, $user_id) {
        try {
            $query = "UPDATE cycle_count_headers 
                      SET status = 'in_progress', assigned_to = ?, started_at = NOW() 
                      WHERE id = ? AND status IN ('new', 'scheduled')";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$user_id, $cycle_count_id]);
            
        } catch(PDOException $e) {
            error_log("Start cycle count error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Submit counts for items
     */
    public function submitCounts($cycle_count_id, $count_data, $user_id) {
        try {
            $this->db->beginTransaction();
            
            $variance_count = 0;
            
            foreach($count_data as $item) {
                $item_id = $item['item_id'];
                $counted_quantity = floatval($item['counted_quantity']);
                $notes = $item['notes'] ?? '';
                
                // Get expected quantity
                $query = "SELECT expected_quantity, unit_cost FROM cycle_count_items WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$item_id]);
                $expected_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($expected_data) {
                    $expected_quantity = floatval($expected_data['expected_quantity']);
                    $unit_cost = floatval($expected_data['unit_cost']);
                    
                    // Calculate variance
                    $variance_quantity = $counted_quantity - $expected_quantity;
                    $variance_percentage = $expected_quantity > 0 ? ($variance_quantity / $expected_quantity) * 100 : 0;
                    $variance_value = $variance_quantity * $unit_cost;
                    
                    if(abs($variance_quantity) > 0.01) { // Has variance
                        $variance_count++;
                        $count_status = 'variance';
                    } else {
                        $count_status = 'counted';
                    }
                    
                    // Update cycle count item
                    $query = "UPDATE cycle_count_items 
                              SET counted_quantity = ?, 
                                  variance_quantity = ?, 
                                  variance_percentage = ?, 
                                  variance_value = ?,
                                  count_status = ?, 
                                  notes = ?, 
                                  counted_by = ?, 
                                  counted_at = NOW() 
                              WHERE id = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        $counted_quantity,
                        $variance_quantity,
                        $variance_percentage,
                        $variance_value,
                        $count_status,
                        $notes,
                        $user_id,
                        $item_id
                    ]);
                    
                    // Log history
                    $this->logCycleCountHistory($cycle_count_id, $item_id, 'counted', $expected_quantity, $counted_quantity, $user_id);
                }
            }
            
            // Update cycle count header
            $query = "UPDATE cycle_count_headers h 
                      SET counted_items = (
                          SELECT COUNT(*) FROM cycle_count_items 
                          WHERE cycle_count_id = h.id AND counted_quantity IS NOT NULL
                      ),
                      variance_items = (
                          SELECT COUNT(*) FROM cycle_count_items 
                          WHERE cycle_count_id = h.id AND count_status = 'variance'
                      ),
                      accuracy_percentage = (
                          SELECT (COUNT(*) - SUM(CASE WHEN count_status = 'variance' THEN 1 ELSE 0 END)) * 100.0 / COUNT(*)
                          FROM cycle_count_items 
                          WHERE cycle_count_id = h.id AND counted_quantity IS NOT NULL
                      )
                      WHERE h.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cycle_count_id]);
            
            // Check if all items are counted
            $query = "SELECT total_items, counted_items FROM cycle_count_headers WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cycle_count_id]);
            $header_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($header_data && $header_data['counted_items'] >= $header_data['total_items']) {
                // Mark as completed
                $query = "UPDATE cycle_count_headers 
                          SET status = 'completed', completed_at = NOW() 
                          WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$cycle_count_id]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'variances' => $variance_count
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
     * Log cycle count history
     */
    private function logCycleCountHistory($cycle_count_id, $item_id, $action, $old_value, $new_value, $user_id, $reason = null) {
        $query = "INSERT INTO cycle_count_history 
                  (cycle_count_id, item_id, action, old_value, new_value, reason, performed_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $cycle_count_id,
            $item_id,
            $action,
            $old_value,
            $new_value,
            $reason,
            $user_id
        ]);
    }
    
    /**
     * Approve adjustments
     */
    public function approveAdjustments($cycle_count_id, $adjustments, $user_id) {
        try {
            $this->db->beginTransaction();
            
            foreach($adjustments as $adjustment) {
                $item_id = $adjustment['item_id'];
                $approve = $adjustment['approve'];
                
                if($approve) {
                    // Get item details
                    $query = "SELECT sku, location_id, variance_quantity FROM cycle_count_items WHERE id = ?";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([$item_id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if($item) {
                        // Update actual stock in master table
                        $query = "UPDATE msaster_location_by_stock 
                                  SET จำนวนน้ำหนัก_ปกติ = จำนวนน้ำหนัก_ปกติ + ? 
                                  WHERE sku = ? AND location_id = ?";
                        
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([
                            $item['variance_quantity'],
                            $item['sku'],
                            $item['location_id']
                        ]);
                        
                        // Update item status
                        $query = "UPDATE cycle_count_items 
                                  SET count_status = 'adjusted', adjusted_by = ?, adjusted_at = NOW() 
                                  WHERE id = ?";
                        $stmt = $this->db->prepare($query);
                        $stmt->execute([$user_id, $item_id]);
                        
                        // Log history
                        $this->logCycleCountHistory($cycle_count_id, $item_id, 'adjusted', null, $item['variance_quantity'], $user_id, 'Approved adjustment');
                    }
                }
            }
            
            // Update cycle count status
            $query = "UPDATE cycle_count_headers 
                      SET status = 'reviewed', reviewed_by = ?, reviewed_at = NOW() 
                      WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $cycle_count_id]);
            
            $this->db->commit();
            return true;
            
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Approve adjustments error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate automatic cycle count
     */
    public function generateAutoCycleCount($method, $percentage, $user_id) {
        try {
            $items = [];
            
            switch($method) {
                case 'abc_analysis':
                    $items = $this->getABCItems($percentage);
                    break;
                    
                case 'high_value':
                    $items = $this->getHighValueItems($percentage);
                    break;
                    
                case 'fast_moving':
                    $items = $this->getFastMovingItems($percentage);
                    break;
                    
                case 'random':
                    $items = $this->getRandomItems($percentage);
                    break;
                    
                case 'overdue':
                    $items = $this->getOverdueItems();
                    break;
            }
            
            if(empty($items)) {
                return [
                    'success' => false,
                    'error' => 'ไม่พบรายการสินค้าที่ตรงตามเงื่อนไข'
                ];
            }
            
            // Create cycle count
            $params = [
                'count_type' => 'auto',
                'schedule_date' => date('Y-m-d'),
                'priority' => 'medium',
                'notes' => "Auto-generated cycle count using {$method} method",
                'created_by' => $user_id
            ];
            
            $result = $this->createCycleCount($params);
            
            if($result['success']) {
                // Add selected items
                foreach($items as $item) {
                    $this->insertCycleCountItem($result['cycle_id'], $item);
                }
                
                // Update total items
                $query = "UPDATE cycle_count_headers SET total_items = ? WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([count($items), $result['cycle_id']]);
                
                return [
                    'success' => true,
                    'cycle_id' => $result['cycle_id'],
                    'count' => count($items)
                ];
            }
            
            return $result;
            
        } catch(Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get ABC analysis items
     */
    private function getABCItems($percentage) {
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    (p.จำนวนน้ำหนัก_ปกติ * p.ราคาต้นทุน) as total_value,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND p.ราคาต้นทุน > 0
                  ORDER BY total_value DESC
                  LIMIT ?";
        
        $limit = ceil($this->getTotalActiveItems() * ($percentage / 100));
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get high value items
     */
    private function getHighValueItems($percentage) {
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND p.ราคาต้นทุน > (
                      SELECT AVG(ราคาต้นทุน) * 2 FROM msaster_location_by_stock WHERE จำนวนน้ำหนัก_ปกติ > 0
                  )
                  ORDER BY p.ราคาต้นทุน DESC
                  LIMIT ?";
        
        $limit = ceil($this->getTotalActiveItems() * ($percentage / 100));
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get fast moving items
     */
    private function getFastMovingItems($percentage) {
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name,
                    COALESCE(t.movement_count, 0) as movement_count
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  LEFT JOIN (
                      SELECT sku, COUNT(*) as movement_count
                      FROM picking_transactions
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      GROUP BY sku
                  ) t ON p.sku = t.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  ORDER BY movement_count DESC
                  LIMIT ?";
        
        $limit = ceil($this->getTotalActiveItems() * ($percentage / 100));
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get random items
     */
    private function getRandomItems($percentage) {
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  ORDER BY RAND()
                  LIMIT ?";
        
        $limit = ceil($this->getTotalActiveItems() * ($percentage / 100));
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get overdue items (not counted in last 90 days)
     */
    private function getOverdueItems() {
        $query = "SELECT 
                    p.sku,
                    p.location_id,
                    p.จำนวนน้ำหนัก_ปกติ as current_quantity,
                    p.ราคาต้นทุน as unit_cost,
                    prod.ชื่อ_สินค้า as product_name
                  FROM msaster_location_by_stock p
                  LEFT JOIN master_sku_by_stock prod ON p.sku = prod.sku
                  LEFT JOIN (
                      SELECT DISTINCT sku, location_id
                      FROM cycle_count_items ci
                      JOIN cycle_count_headers ch ON ci.cycle_count_id = ch.id
                      WHERE ch.completed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                  ) recent ON p.sku = recent.sku AND p.location_id = recent.location_id
                  WHERE p.จำนวนน้ำหนัก_ปกติ > 0 
                  AND recent.sku IS NULL
                  ORDER BY p.sku, p.location_id";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total active items count
     */
    private function getTotalActiveItems() {
        $query = "SELECT COUNT(*) FROM msaster_location_by_stock WHERE จำนวนน้ำหนัก_ปกติ > 0";
        return $this->db->query($query)->fetchColumn();
    }
    
    /**
     * Get cycle counts
     */
    public function getCycleCounts($status = null) {
        $where_clause = $status ? "WHERE h.status = ?" : "";
        
        $query = "SELECT 
                    h.*,
                    u.ชื่อ_สกุล as created_by_name,
                    a.ชื่อ_สกุล as assigned_to_name,
                    COALESCE(h.counted_items / h.total_items * 100, 0) as progress_percentage
                  FROM cycle_count_headers h
                  LEFT JOIN users u ON h.created_by = u.user_id
                  LEFT JOIN users a ON h.assigned_to = a.user_id
                  $where_clause
                  ORDER BY h.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        if($status) {
            $stmt->execute([$status]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cycle count statistics
     */
    public function getCycleCountStatistics() {
        $stats = [];
        
        // In progress counts
        $query = "SELECT COUNT(*) FROM cycle_count_headers WHERE status = 'in_progress'";
        $stats['in_progress'] = $this->db->query($query)->fetchColumn();
        
        // Scheduled counts
        $query = "SELECT COUNT(*) FROM cycle_count_headers WHERE status = 'scheduled'";
        $stats['scheduled'] = $this->db->query($query)->fetchColumn();
        
        // Completed this month
        $query = "SELECT COUNT(*) FROM cycle_count_headers 
                  WHERE status = 'completed' 
                  AND YEAR(completed_at) = YEAR(NOW()) 
                  AND MONTH(completed_at) = MONTH(NOW())";
        $stats['completed_this_month'] = $this->db->query($query)->fetchColumn();
        
        // Overall accuracy
        $query = "SELECT AVG(accuracy_percentage) FROM cycle_count_headers 
                  WHERE status IN ('completed', 'reviewed', 'closed') 
                  AND accuracy_percentage IS NOT NULL";
        $stats['accuracy'] = $this->db->query($query)->fetchColumn() ?: 0;
        
        return $stats;
    }
    
    /**
     * Get pending counts for user
     */
    public function getPendingCountsForUser($user_id) {
        $query = "SELECT * FROM cycle_count_headers 
                  WHERE (assigned_to = ? OR assigned_to IS NULL) 
                  AND status IN ('scheduled', 'in_progress')
                  ORDER BY priority DESC, schedule_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cycle count details
     */
    public function getCycleCountDetails($cycle_count_id) {
        $query = "SELECT 
                    h.*,
                    u.ชื่อ_สกุล as created_by_name,
                    a.ชื่อ_สกุล as assigned_to_name
                  FROM cycle_count_headers h
                  LEFT JOIN users u ON h.created_by = u.user_id
                  LEFT JOIN users a ON h.assigned_to = a.user_id
                  WHERE h.id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$cycle_count_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cycle count items
     */
    public function getCycleCountItems($cycle_count_id) {
        $query = "SELECT 
                    ci.*,
                    p.ชื่อ_สินค้า as product_name,
                    u.ชื่อ_สกุล as counted_by_name
                  FROM cycle_count_items ci
                  LEFT JOIN master_sku_by_stock p ON ci.sku = p.sku
                  LEFT JOIN users u ON ci.counted_by = u.user_id
                  WHERE ci.cycle_count_id = ?
                  ORDER BY ci.location_id, ci.sku";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$cycle_count_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}