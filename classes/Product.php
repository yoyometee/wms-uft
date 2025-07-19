<?php
class Product {
    private $conn;
    private $table = 'master_sku_by_stock';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAllProducts() {
        try {
            $query = "SELECT * FROM " . $this->table . " ORDER BY product_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get all products error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getProductBySKU($sku) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE sku = :sku";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get product by SKU error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getProductsByBarcode($barcode) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE barcode = :barcode";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':barcode', $barcode);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get products by barcode error: " . $e->getMessage());
            return [];
        }
    }
    
    public function searchProducts($search) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE sku LIKE :search 
                     OR product_name LIKE :search 
                     OR barcode LIKE :search 
                     ORDER BY product_name 
                     LIMIT 50";
            $stmt = $this->conn->prepare($query);
            $searchTerm = "%$search%";
            $stmt->bindParam(':search', $searchTerm);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Search products error: " . $e->getMessage());
            return [];
        }
    }
    
    public function createProduct($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                     (sku, product_name, type, barcode, unit, น้ำหนัก_ต่อ_ถุง, 
                      จำนวนถุง_ต่อ_แพ็ค, จำนวนแพ็ค_ต่อ_พาเลท, ti, hi, 
                      min_stock, max_stock, remark)
                     VALUES 
                     (:sku, :product_name, :type, :barcode, :unit, :น้ำหนัก_ต่อ_ถุง,
                      :จำนวนถุง_ต่อ_แพ็ค, :จำนวนแพ็ค_ต่อ_พาเลท, :ti, :hi,
                      :min_stock, :max_stock, :remark)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $data['sku']);
            $stmt->bindParam(':product_name', $data['product_name']);
            $stmt->bindParam(':type', $data['type']);
            $stmt->bindParam(':barcode', $data['barcode']);
            $stmt->bindParam(':unit', $data['unit']);
            $stmt->bindParam(':น้ำหนัก_ต่อ_ถุง', $data['น้ำหนัก_ต่อ_ถุง']);
            $stmt->bindParam(':จำนวนถุง_ต่อ_แพ็ค', $data['จำนวนถุง_ต่อ_แพ็ค']);
            $stmt->bindParam(':จำนวนแพ็ค_ต่อ_พาเลท', $data['จำนวนแพ็ค_ต่อ_พาเลท']);
            $stmt->bindParam(':ti', $data['ti']);
            $stmt->bindParam(':hi', $data['hi']);
            $stmt->bindParam(':min_stock', $data['min_stock']);
            $stmt->bindParam(':max_stock', $data['max_stock']);
            $stmt->bindParam(':remark', $data['remark']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Create product error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProduct($sku, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET product_name = :product_name,
                         type = :type,
                         barcode = :barcode,
                         unit = :unit,
                         น้ำหนัก_ต่อ_ถุง = :น้ำหนัก_ต่อ_ถุง,
                         จำนวนถุง_ต่อ_แพ็ค = :จำนวนถุง_ต่อ_แพ็ค,
                         จำนวนแพ็ค_ต่อ_พาเลท = :จำนวนแพ็ค_ต่อ_พาเลท,
                         ti = :ti,
                         hi = :hi,
                         min_stock = :min_stock,
                         max_stock = :max_stock,
                         remark = :remark,
                         updated_at = NOW()
                     WHERE sku = :sku";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':product_name', $data['product_name']);
            $stmt->bindParam(':type', $data['type']);
            $stmt->bindParam(':barcode', $data['barcode']);
            $stmt->bindParam(':unit', $data['unit']);
            $stmt->bindParam(':น้ำหนัก_ต่อ_ถุง', $data['น้ำหนัก_ต่อ_ถุง']);
            $stmt->bindParam(':จำนวนถุง_ต่อ_แพ็ค', $data['จำนวนถุง_ต่อ_แพ็ค']);
            $stmt->bindParam(':จำนวนแพ็ค_ต่อ_พาเลท', $data['จำนวนแพ็ค_ต่อ_พาเลท']);
            $stmt->bindParam(':ti', $data['ti']);
            $stmt->bindParam(':hi', $data['hi']);
            $stmt->bindParam(':min_stock', $data['min_stock']);
            $stmt->bindParam(':max_stock', $data['max_stock']);
            $stmt->bindParam(':remark', $data['remark']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Update product error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateStock($sku, $normal_weight, $normal_bags, $damaged_weight = 0, $damaged_bags = 0) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET จำนวนน้ำหนัก_ปกติ = :normal_weight,
                         จำนวนถุง_ปกติ = :normal_bags,
                         จำนวนน้ำหนัก_เสีย = :damaged_weight,
                         จำนวนถุง_เสีย = :damaged_bags,
                         updated_at = NOW()
                     WHERE sku = :sku";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':normal_weight', $normal_weight);
            $stmt->bindParam(':normal_bags', $normal_bags);
            $stmt->bindParam(':damaged_weight', $damaged_weight);
            $stmt->bindParam(':damaged_bags', $damaged_bags);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Update stock error: " . $e->getMessage());
            return false;
        }
    }
    
    public function adjustStock($sku, $quantity_change, $weight_change, $type = 'ปกติ') {
        try {
            $product = $this->getProductBySKU($sku);
            if(!$product) {
                throw new Exception("Product not found: $sku");
            }
            
            if($type === 'ปกติ') {
                $new_bags = max(0, $product['จำนวนถุง_ปกติ'] + $quantity_change);
                $new_weight = max(0, $product['จำนวนน้ำหนัก_ปกติ'] + $weight_change);
                
                return $this->updateStock($sku, $new_weight, $new_bags, 
                                        $product['จำนวนน้ำหนัก_เสีย'], 
                                        $product['จำนวนถุง_เสีย']);
            } else {
                $new_bags = max(0, $product['จำนวนถุง_เสีย'] + $quantity_change);
                $new_weight = max(0, $product['จำนวนน้ำหนัก_เสีย'] + $weight_change);
                
                return $this->updateStock($sku, $product['จำนวนน้ำหนัก_ปกติ'], 
                                        $product['จำนวนถุง_ปกติ'], 
                                        $new_weight, $new_bags);
            }
        } catch(Exception $e) {
            error_log("Adjust stock error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteProduct($sku) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE sku = :sku";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':sku', $sku);
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Delete product error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStockSummary() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(จำนวนถุง_ปกติ) as total_normal_bags,
                        SUM(จำนวนน้ำหนัก_ปกติ) as total_normal_weight,
                        SUM(จำนวนถุง_เสีย) as total_damaged_bags,
                        SUM(จำนวนน้ำหนัก_เสีย) as total_damaged_weight,
                        SUM(CASE WHEN จำนวนถุง_ปกติ <= min_stock THEN 1 ELSE 0 END) as low_stock_products,
                        SUM(CASE WHEN จำนวนถุง_ปกติ >= max_stock THEN 1 ELSE 0 END) as high_stock_products
                     FROM " . $this->table;
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get stock summary error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLowStockProducts() {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE จำนวนถุง_ปกติ <= min_stock 
                     ORDER BY (จำนวนถุง_ปกติ / min_stock) ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get low stock products error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getHighStockProducts() {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE จำนวนถุง_ปกติ >= max_stock 
                     ORDER BY (จำนวนถุง_ปกติ / max_stock) DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get high stock products error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getProductsByType($type) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE type = :type 
                     ORDER BY product_name";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':type', $type);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get products by type error: " . $e->getMessage());
            return [];
        }
    }
    
    public function validateSKU($sku) {
        return !empty($sku) && preg_match('/^[A-Z0-9]{3,}$/', $sku);
    }
    
    public function validateBarcode($barcode) {
        return !empty($barcode) && preg_match('/^[0-9]{8,13}$/', $barcode);
    }
    
    public function calculateTotalWeight($sku, $pieces) {
        try {
            $product = $this->getProductBySKU($sku);
            if(!$product) {
                return 0;
            }
            
            return $pieces * $product['น้ำหนัก_ต่อ_ถุง'];
        } catch(Exception $e) {
            error_log("Calculate total weight error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function calculatePiecesFromWeight($sku, $weight) {
        try {
            $product = $this->getProductBySKU($sku);
            if(!$product || $product['น้ำหนัก_ต่อ_ถุง'] <= 0) {
                return 0;
            }
            
            return round($weight / $product['น้ำหนัก_ต่อ_ถุง']);
        } catch(Exception $e) {
            error_log("Calculate pieces from weight error: " . $e->getMessage());
            return 0;
        }
    }
}
?>