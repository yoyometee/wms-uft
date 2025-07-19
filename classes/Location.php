<?php
class Location {
    private $conn;
    private $table = 'msaster_location_by_stock';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAllLocations() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY location_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get all locations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLocationById($location_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE location_id = :location_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get location by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAvailableLocations($zone = null) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE status = 'ว่าง'";
            if($zone) {
                $query .= " AND zone = :zone";
            }
            $query .= " ORDER BY location_id";
            
            $stmt = $this->conn->prepare($query);
            if($zone) {
                $stmt->bindParam(':zone', $zone);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get available locations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getOccupiedLocations($zone = null) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE status = 'เก็บสินค้า'";
            if($zone) {
                $query .= " AND zone = :zone";
            }
            $query .= " ORDER BY location_id";
            
            $stmt = $this->conn->prepare($query);
            if($zone) {
                $stmt->bindParam(':zone', $zone);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get occupied locations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLocationsBySKU($sku) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE sku = :sku AND status = 'เก็บสินค้า' 
                     ORDER BY expiration_date ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get locations by SKU error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLocationsByZone($zone) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE zone = :zone 
                     ORDER BY location_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':zone', $zone);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get locations by zone error: " . $e->getMessage());
            return [];
        }
    }
    
    public function assignPalletToLocation($location_id, $pallet_id, $sku, $product_data) {
        try {
            // Check FEFO validation first
            if(FEFO_ENABLED && !$this->checkFEFO($sku, $product_data['expiration_date'])) {
                throw new Exception("❌ จำเป็นต้องเบิก FEFO Location ก่อน");
            }
            
            // Check if location is available
            $location = $this->getLocationById($location_id);
            if(!$location || $location['status'] !== 'ว่าง') {
                throw new Exception("❌ Location นี้ไม่ว่าง หรือย้ายไป PF-Zone แล้ว");
            }
            
            // Check capacity
            if(isset($product_data['weight']) && $product_data['weight'] > $location['max_weight']) {
                throw new Exception("❌ น้ำหนักเกินกำหนด Location นี้");
            }
            
            $query = "UPDATE " . $this->table . " 
                     SET status = 'เก็บสินค้า',
                         pallet_id = :pallet_id,
                         sku = :sku,
                         product_name = :product_name,
                         แพ็ค = :packs,
                         ชิ้น = :pieces,
                         น้ำหนัก = :weight,
                         lot = :lot,
                         received_date = :received_date,
                         expiration_date = :expiration_date,
                         สีพาเลท = :pallet_color,
                         หมายเหตุ = :remark,
                         name_edit = :name_edit,
                         last_updated = :timestamp,
                         updated_at = NOW()
                     WHERE location_id = :location_id AND status = 'ว่าง'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->bindParam(':pallet_id', $pallet_id);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':product_name', $product_data['product_name'] ?? '');
            $stmt->bindParam(':packs', $product_data['packs'] ?? 0);
            $stmt->bindParam(':pieces', $product_data['pieces'] ?? 0);
            $stmt->bindParam(':weight', $product_data['weight'] ?? 0);
            $stmt->bindParam(':lot', $product_data['lot'] ?? '');
            $stmt->bindParam(':received_date', $product_data['received_date'] ?? time());
            $stmt->bindParam(':expiration_date', $product_data['expiration_date'] ?? 0);
            $stmt->bindParam(':pallet_color', $product_data['pallet_color'] ?? '');
            $stmt->bindParam(':remark', $product_data['remark'] ?? '');
            $stmt->bindParam(':name_edit', $_SESSION['user_name'] ?? '');
            $stmt->bindParam(':timestamp', time());
            
            if(!$stmt->execute()) {
                throw new Exception("❌ ไม่สามารถบันทึกข้อมูลได้");
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Assign pallet to location error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function removePalletFromLocation($location_id, $pallet_id = null) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET status = 'ว่าง',
                         pallet_id = NULL,
                         sku = NULL,
                         product_name = NULL,
                         แพ็ค = 0,
                         ชิ้น = 0,
                         น้ำหนัก = 0,
                         lot = NULL,
                         received_date = NULL,
                         expiration_date = NULL,
                         สีพาเลท = NULL,
                         หมายเหตุ = NULL,
                         name_edit = :name_edit,
                         last_updated = :timestamp,
                         updated_at = NOW()
                     WHERE location_id = :location_id";
            
            if($pallet_id) {
                $query .= " AND pallet_id = :pallet_id";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->bindParam(':name_edit', $_SESSION['user_name'] ?? '');
            $stmt->bindParam(':timestamp', time());
            
            if($pallet_id) {
                $stmt->bindParam(':pallet_id', $pallet_id);
            }
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Remove pallet from location error: " . $e->getMessage());
            return false;
        }
    }
    
    public function movePallet($from_location_id, $to_location_id, $pallet_id) {
        try {
            $this->conn->beginTransaction();
            
            // Get current pallet data
            $from_location = $this->getLocationById($from_location_id);
            if(!$from_location || $from_location['pallet_id'] !== $pallet_id) {
                throw new Exception("❌ ไม่พบ Pallet ใน Location ต้นทาง");
            }
            
            // Check destination location
            $to_location = $this->getLocationById($to_location_id);
            if(!$to_location || $to_location['status'] !== 'ว่าง') {
                throw new Exception("❌ Location ปลายทางไม่ว่าง");
            }
            
            // Prepare product data for new location
            $product_data = [
                'product_name' => $from_location['product_name'],
                'packs' => $from_location['แพ็ค'],
                'pieces' => $from_location['ชิ้น'],
                'weight' => $from_location['น้ำหนัก'],
                'lot' => $from_location['lot'],
                'received_date' => $from_location['received_date'],
                'expiration_date' => $from_location['expiration_date'],
                'pallet_color' => $from_location['สีพาเลท'],
                'remark' => $from_location['หมายเหตุ']
            ];
            
            // Remove from source location
            $this->removePalletFromLocation($from_location_id, $pallet_id);
            
            // Add to destination location
            $this->assignPalletToLocation($to_location_id, $pallet_id, $from_location['sku'], $product_data);
            
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            $this->conn->rollback();
            error_log("Move pallet error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function pickFromLocation($location_id, $pallet_id, $quantity_picked, $weight_picked) {
        try {
            $location = $this->getLocationById($location_id);
            if(!$location || $location['pallet_id'] !== $pallet_id) {
                throw new Exception("❌ ไม่พบ Pallet ใน Location นี้");
            }
            
            $remaining_pieces = $location['ชิ้น'] - $quantity_picked;
            $remaining_weight = $location['น้ำหนัก'] - $weight_picked;
            
            if($remaining_pieces <= 0 || $remaining_weight <= 0) {
                // Remove completely
                return $this->removePalletFromLocation($location_id, $pallet_id);
            } else {
                // Update remaining quantity
                $query = "UPDATE " . $this->table . " 
                         SET ชิ้น = :remaining_pieces,
                             น้ำหนัก = :remaining_weight,
                             name_edit = :name_edit,
                             last_updated = :timestamp,
                             updated_at = NOW()
                         WHERE location_id = :location_id AND pallet_id = :pallet_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':remaining_pieces', $remaining_pieces);
                $stmt->bindParam(':remaining_weight', $remaining_weight);
                $stmt->bindParam(':name_edit', $_SESSION['user_name'] ?? '');
                $stmt->bindParam(':timestamp', time());
                $stmt->bindParam(':location_id', $location_id);
                $stmt->bindParam(':pallet_id', $pallet_id);
                
                return $stmt->execute();
            }
        } catch(Exception $e) {
            error_log("Pick from location error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function checkFEFO($sku, $new_expiry_date) {
        try {
            // Check FEFO (First Expired First Out) for PF-Zone + Selective Rack
            $query = "SELECT location_id, expiration_date, pallet_id 
                     FROM " . $this->table . " 
                     WHERE sku = :sku 
                     AND status = 'เก็บสินค้า' 
                     AND zone LIKE '%PF-Zone%' 
                     AND zone LIKE '%Selective Rack%'
                     AND expiration_date < :new_expiry_date
                     AND expiration_date > 0
                     ORDER BY expiration_date ASC
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':new_expiry_date', $new_expiry_date);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                return false; // Has older pallet that should be picked first
            }
            
            return true;
        } catch(Exception $e) {
            error_log("Check FEFO error: " . $e->getMessage());
            return true; // Allow if check fails
        }
    }
    
    public function getLocationUtilization() {
        try {
            $query = "SELECT 
                        zone,
                        COUNT(*) as total_locations,
                        SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) as occupied,
                        SUM(CASE WHEN status = 'ว่าง' THEN 1 ELSE 0 END) as available,
                        ROUND((SUM(CASE WHEN status = 'เก็บสินค้า' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as utilization_percent
                     FROM " . $this->table . "
                     GROUP BY zone
                     ORDER BY utilization_percent DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get location utilization error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getExpiringSoon($days = 30) {
        try {
            $expiry_threshold = time() + ($days * 24 * 60 * 60);
            
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE status = 'เก็บสินค้า' 
                     AND expiration_date > 0 
                     AND expiration_date <= :expiry_threshold
                     ORDER BY expiration_date ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':expiry_threshold', $expiry_threshold);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get expiring soon error: " . $e->getMessage());
            return [];
        }
    }
    
    public function searchLocations($search) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE location_id LIKE :search 
                     OR zone LIKE :search 
                     OR sku LIKE :search 
                     OR product_name LIKE :search
                     OR pallet_id LIKE :search
                     ORDER BY location_id 
                     LIMIT 50";
            
            $stmt = $this->conn->prepare($query);
            $searchTerm = "%$search%";
            $stmt->bindParam(':search', $searchTerm);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Search locations error: " . $e->getMessage());
            return [];
        }
    }
    
    public function createLocation($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                     (location_id, zone, row_name, level_num, loc_num, max_weight, max_pallet, max_height, status)
                     VALUES (:location_id, :zone, :row_name, :level_num, :loc_num, :max_weight, :max_pallet, :max_height, :status)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $data['location_id']);
            $stmt->bindParam(':zone', $data['zone']);
            $stmt->bindParam(':row_name', $data['row_name']);
            $stmt->bindParam(':level_num', $data['level_num']);
            $stmt->bindParam(':loc_num', $data['loc_num']);
            $stmt->bindParam(':max_weight', $data['max_weight']);
            $stmt->bindParam(':max_pallet', $data['max_pallet']);
            $stmt->bindParam(':max_height', $data['max_height']);
            $stmt->bindParam(':status', $data['status']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Create location error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateLocation($location_id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET zone = :zone,
                         row_name = :row_name,
                         level_num = :level_num,
                         loc_num = :loc_num,
                         max_weight = :max_weight,
                         max_pallet = :max_pallet,
                         max_height = :max_height,
                         updated_at = NOW()
                     WHERE location_id = :location_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->bindParam(':zone', $data['zone']);
            $stmt->bindParam(':row_name', $data['row_name']);
            $stmt->bindParam(':level_num', $data['level_num']);
            $stmt->bindParam(':loc_num', $data['loc_num']);
            $stmt->bindParam(':max_weight', $data['max_weight']);
            $stmt->bindParam(':max_pallet', $data['max_pallet']);
            $stmt->bindParam(':max_height', $data['max_height']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Update location error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteLocation($location_id) {
        try {
            $location = $this->getLocationById($location_id);
            if($location && $location['status'] === 'เก็บสินค้า') {
                throw new Exception("❌ ไม่สามารถลบ Location ที่มีสินค้าอยู่");
            }
            
            $query = "DELETE FROM " . $this->table . " WHERE location_id = :location_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':location_id', $location_id);
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Delete location error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getZones() {
        try {
            $query = "SELECT DISTINCT zone FROM " . $this->table . " ORDER BY zone";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            error_log("Get zones error: " . $e->getMessage());
            return [];
        }
    }
}
?>