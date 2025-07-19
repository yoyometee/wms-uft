<?php
class Transaction {
    private $conn;
    private $transaction_tables = [
        'receive' => 'receive_transactions',
        'picking' => 'picking_transactions',
        'movement' => 'movement_transactions',
        'online' => 'online_transactions',
        'premium' => 'premium_transactions',
        'conversion' => 'conversion_transactions',
        'adjust_pf' => 'adjust_by_pf_transactions',
        'adjust_location' => 'adjust_by_location_transactions',
        'rp' => 'rp_transactions'
    ];
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function createTransaction($type, $data) {
        try {
            if(!isset($this->transaction_tables[$type])) {
                throw new Exception("Invalid transaction type: $type");
            }
            
            $table = $this->transaction_tables[$type];
            $tags_id = $this->generateTagsID($type);
            
            // Generate pallet_id if not provided
            if(!isset($data['pallet_id']) || empty($data['pallet_id'])) {
                $data['pallet_id'] = $this->generatePalletID();
            }
            
            $query = "INSERT INTO $table 
                     (tags_id, ประเภทหลัก, ประเภทย่อย, sku, product_name, barcode, pallet_id, 
                      zone_location, status_location, location_id, สีพาเลท, แพ็ค, ชิ้น, น้ำหนัก, 
                      lot, รหัสลูกค้า, ชื่อร้านค้า, received_date, expiration_date, คันที่, 
                      transaction_status, remark, number_pallet, name_edit, last_updated)
                     VALUES 
                     (:tags_id, :main_type, :sub_type, :sku, :product_name, :barcode, :pallet_id,
                      :zone_location, :status_location, :location_id, :pallet_color, :packs, :pieces, :weight,
                      :lot, :customer_code, :shop_name, :received_date, :expiration_date, :vehicle_no,
                      :transaction_status, :remark, :number_pallet, :name_edit, :timestamp)";
            
            $stmt = $this->conn->prepare($query);
            
            // Assign values to variables first to avoid bindParam reference issues
            $main_type = $data['main_type'] ?? '';
            $sub_type = $data['sub_type'] ?? '';
            $product_name = $data['product_name'] ?? '';
            $barcode = $data['barcode'] ?? '';
            $zone_location = $data['zone_location'] ?? '';
            $status_location = $data['status_location'] ?? '';
            $location_id = $data['location_id'] ?? '';
            $pallet_color = $data['pallet_color'] ?? '';
            $packs = $data['packs'] ?? 0;
            $pieces = $data['pieces'] ?? 0;
            $weight = $data['weight'] ?? 0;
            $lot = $data['lot'] ?? '';
            $customer_code = $data['customer_code'] ?? '';
            $shop_name = $data['shop_name'] ?? '';
            $received_date = $data['received_date'] ?? time();
            $expiration_date = $data['expiration_date'] ?? 0;
            $vehicle_no = $data['vehicle_no'] ?? '';
            $transaction_status = $data['transaction_status'] ?? 'ปกติ';
            $remark = $data['remark'] ?? '';
            $number_pallet = $data['number_pallet'] ?? '';
            $name_edit = $_SESSION['user_name'] ?? '';
            $timestamp = time();
            
            $stmt->bindParam(':tags_id', $tags_id);
            $stmt->bindParam(':main_type', $main_type);
            $stmt->bindParam(':sub_type', $sub_type);
            $stmt->bindParam(':sku', $data['sku']);
            $stmt->bindParam(':product_name', $product_name);
            $stmt->bindParam(':barcode', $barcode);
            $stmt->bindParam(':pallet_id', $data['pallet_id']);
            $stmt->bindParam(':zone_location', $zone_location);
            $stmt->bindParam(':status_location', $status_location);
            $stmt->bindParam(':location_id', $location_id);
            $stmt->bindParam(':pallet_color', $pallet_color);
            $stmt->bindParam(':packs', $packs);
            $stmt->bindParam(':pieces', $pieces);
            $stmt->bindParam(':weight', $weight);
            $stmt->bindParam(':lot', $lot);
            $stmt->bindParam(':customer_code', $customer_code);
            $stmt->bindParam(':shop_name', $shop_name);
            $stmt->bindParam(':received_date', $received_date);
            $stmt->bindParam(':expiration_date', $expiration_date);
            $stmt->bindParam(':vehicle_no', $vehicle_no);
            $stmt->bindParam(':transaction_status', $transaction_status);
            $stmt->bindParam(':remark', $remark);
            $stmt->bindParam(':number_pallet', $number_pallet);
            $stmt->bindParam(':name_edit', $name_edit);
            $stmt->bindParam(':timestamp', $timestamp);
            
            if($stmt->execute()) {
                return [
                    'success' => true, 
                    'pallet_id' => $data['pallet_id'], 
                    'tags_id' => $tags_id,
                    'transaction_id' => $this->conn->lastInsertId()
                ];
            }
            
            return ['success' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลได้'];
        } catch(Exception $e) {
            error_log("Create transaction error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function createReceiveTransaction($data) {
        $data['main_type'] = 'รับสินค้า';
        return $this->createTransaction('receive', $data);
    }
    
    public function createPickingTransaction($data) {
        $data['main_type'] = 'จัดเตรียมสินค้า';
        return $this->createTransaction('picking', $data);
    }
    
    public function createMovementTransaction($data) {
        $data['main_type'] = 'ย้ายสินค้า';
        return $this->createTransaction('movement', $data);
    }
    
    public function createOnlineTransaction($data) {
        $data['main_type'] = 'ออนไลน์';
        return $this->createTransaction('online', $data);
    }
    
    public function createPremiumTransaction($data) {
        $data['main_type'] = 'Premium';
        return $this->createTransaction('premium', $data);
    }
    
    public function createConversionTransaction($data) {
        $data['main_type'] = 'การแปลง';
        return $this->createTransaction('conversion', $data);
    }
    
    public function createAdjustmentTransaction($data, $type = 'pf') {
        $data['main_type'] = 'ปรับสต็อก';
        return $this->createTransaction($type === 'pf' ? 'adjust_pf' : 'adjust_location', $data);
    }
    
    public function createRPTransaction($data) {
        $data['main_type'] = 'รีแพ็ค';
        return $this->createTransaction('rp', $data);
    }
    
    public function getTransactionHistory($filters = []) {
        try {
            $unions = [];
            $params = [];
            
            foreach($this->transaction_tables as $type => $table) {
                $unions[] = "SELECT '$type' as source, tags_id, ประเภทหลัก, ประเภทย่อย, 
                                   sku, product_name, pallet_id, location_id, แพ็ค, ชิ้น, น้ำหนัก,
                                   created_at, name_edit, transaction_status, remark
                            FROM $table";
            }
            
            $query = "SELECT t.*, p.product_name as full_product_name
                     FROM (" . implode(' UNION ALL ', $unions) . ") t
                     LEFT JOIN master_sku_by_stock p ON t.sku = p.sku
                     WHERE 1=1";
            
            if(isset($filters['sku']) && !empty($filters['sku'])) {
                $query .= " AND t.sku = :sku";
                $params[':sku'] = $filters['sku'];
            }
            
            if(isset($filters['pallet_id']) && !empty($filters['pallet_id'])) {
                $query .= " AND t.pallet_id = :pallet_id";
                $params[':pallet_id'] = $filters['pallet_id'];
            }
            
            if(isset($filters['location_id']) && !empty($filters['location_id'])) {
                $query .= " AND t.location_id = :location_id";
                $params[':location_id'] = $filters['location_id'];
            }
            
            if(isset($filters['source']) && !empty($filters['source'])) {
                $query .= " AND t.source = :source";
                $params[':source'] = $filters['source'];
            }
            
            if(isset($filters['date_from']) && !empty($filters['date_from'])) {
                $query .= " AND DATE(t.created_at) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if(isset($filters['date_to']) && !empty($filters['date_to'])) {
                $query .= " AND DATE(t.created_at) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            if(isset($filters['transaction_status']) && !empty($filters['transaction_status'])) {
                $query .= " AND t.transaction_status = :transaction_status";
                $params[':transaction_status'] = $filters['transaction_status'];
            }
            
            $query .= " ORDER BY t.created_at DESC";
            
            if(isset($filters['limit'])) {
                $query .= " LIMIT " . (int)$filters['limit'];
            } else {
                $query .= " LIMIT 1000";
            }
            
            $stmt = $this->conn->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get transaction history error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getRecentTransactions($limit = 20) {
        try {
            $transactions = $this->getTransactionHistory(['limit' => $limit]);
            
            // Validate and clean transaction data
            $cleaned_transactions = [];
            foreach($transactions as $trans) {
                if(!is_array($trans)) {
                    continue;
                }
                
                // Ensure all required fields exist with default values
                $cleaned_trans = [
                    'created_at' => $trans['created_at'] ?? date('Y-m-d H:i:s'),
                    'ประเภทหลัก' => $trans['ประเภทหลัก'] ?? 'ไม่ระบุ',
                    'sku' => $trans['sku'] ?? '-',
                    'product_name' => $trans['product_name'] ?? $trans['full_product_name'] ?? '-',
                    'pallet_id' => $trans['pallet_id'] ?? '-',
                    'location_id' => $trans['location_id'] ?? '-',
                    'ชิ้น' => is_numeric($trans['ชิ้น']) ? $trans['ชิ้น'] : 0,
                    'น้ำหนัก' => is_numeric($trans['น้ำหนัก']) ? $trans['น้ำหนัก'] : 0,
                    'name_edit' => $trans['name_edit'] ?? '-',
                    'source' => $trans['source'] ?? 'unknown',
                    'tags_id' => $trans['tags_id'] ?? '',
                    'ประเภทย่อย' => $trans['ประเภทย่อย'] ?? '',
                    'แพ็ค' => $trans['แพ็ค'] ?? 0,
                    'transaction_status' => $trans['transaction_status'] ?? 'completed',
                    'remark' => $trans['remark'] ?? ''
                ];
                
                $cleaned_transactions[] = $cleaned_trans;
            }
            
            error_log("Cleaned transactions count: " . count($cleaned_transactions));
            
            return $cleaned_transactions;
            
        } catch(Exception $e) {
            error_log("Get recent transactions error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTransactionById($table, $id) {
        try {
            if(!in_array($table, $this->transaction_tables)) {
                throw new Exception("Invalid table: $table");
            }
            
            $query = "SELECT * FROM $table WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get transaction by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTransactionByTagsId($tags_id) {
        try {
            foreach($this->transaction_tables as $type => $table) {
                $query = "SELECT '$type' as source, * FROM $table WHERE tags_id = :tags_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':tags_id', $tags_id);
                $stmt->execute();
                
                if($stmt->rowCount() > 0) {
                    return $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Get transaction by tags ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTransactionsByPalletId($pallet_id) {
        try {
            $transactions = [];
            
            foreach($this->transaction_tables as $type => $table) {
                $query = "SELECT '$type' as source, * FROM $table WHERE pallet_id = :pallet_id ORDER BY created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':pallet_id', $pallet_id);
                $stmt->execute();
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $results);
            }
            
            // Sort by created_at
            usort($transactions, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return $transactions;
        } catch(Exception $e) {
            error_log("Get transactions by pallet ID error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTransactionStatistics($date_from = null, $date_to = null) {
        try {
            $stats = [];
            
            foreach($this->transaction_tables as $type => $table) {
                $query = "SELECT 
                            COUNT(*) as total_transactions,
                            SUM(ชิ้น) as total_pieces,
                            SUM(น้ำหนัก) as total_weight,
                            COUNT(DISTINCT sku) as unique_skus,
                            COUNT(DISTINCT pallet_id) as unique_pallets
                         FROM $table WHERE 1=1";
                
                $params = [];
                if($date_from) {
                    $query .= " AND DATE(created_at) >= :date_from";
                    $params[':date_from'] = $date_from;
                }
                if($date_to) {
                    $query .= " AND DATE(created_at) <= :date_to";
                    $params[':date_to'] = $date_to;
                }
                
                $stmt = $this->conn->prepare($query);
                foreach($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                
                $stats[$type] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return $stats;
        } catch(Exception $e) {
            error_log("Get transaction statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateTransactionStatus($table, $id, $status) {
        try {
            if(!in_array($table, $this->transaction_tables)) {
                throw new Exception("Invalid table: $table");
            }
            
            $query = "UPDATE $table 
                     SET transaction_status = :status, 
                         name_edit = :name_edit,
                         updated_at = NOW() 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':name_edit', $_SESSION['user_name'] ?? '');
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Update transaction status error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteTransaction($table, $id) {
        try {
            if(!in_array($table, $this->transaction_tables)) {
                throw new Exception("Invalid table: $table");
            }
            
            $query = "DELETE FROM $table WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Delete transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateTagsID($type) {
        $prefix = strtoupper(substr($type, 0, 3));
        $timestamp = date('ymd');
        $sequence = sprintf('%04d', mt_rand(1, 9999));
        return $prefix . $timestamp . $sequence;
    }
    
    public function generatePalletID() {
        $prefix = 'ATG';
        $year = date('y');
        $sequence = sprintf('%08d', mt_rand(1, 99999999));
        return $prefix . $year . $sequence;
    }
    
    public function validatePalletID($pallet_id) {
        return !empty($pallet_id) && preg_match('/^ATG\d{10}$/', $pallet_id);
    }
    
    public function validateTagsID($tags_id) {
        return !empty($tags_id) && preg_match('/^[A-Z]{3}\d{10}$/', $tags_id);
    }
    
    public function getTransactionTables() {
        return $this->transaction_tables;
    }
    
    public function backupTransactions($table, $backup_table_suffix = '_backup') {
        try {
            if(!in_array($table, $this->transaction_tables)) {
                throw new Exception("Invalid table: $table");
            }
            
            $backup_table = $table . $backup_table_suffix . '_' . date('Y_m_d');
            
            $query = "CREATE TABLE $backup_table AS SELECT * FROM $table";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $backup_table;
        } catch(Exception $e) {
            error_log("Backup transactions error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTransactionsByDateRange($date_from, $date_to, $type = null) {
        try {
            $filters = [
                'date_from' => $date_from,
                'date_to' => $date_to
            ];
            
            if($type) {
                $filters['source'] = $type;
            }
            
            return $this->getTransactionHistory($filters);
        } catch(Exception $e) {
            error_log("Get transactions by date range error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTransactionsByUser($user_name, $date_from = null, $date_to = null) {
        try {
            $unions = [];
            $params = [':user_name' => $user_name];
            
            foreach($this->transaction_tables as $type => $table) {
                $query = "SELECT '$type' as source, * FROM $table WHERE name_edit = :user_name";
                
                if($date_from) {
                    $query .= " AND DATE(created_at) >= :date_from";
                    $params[':date_from'] = $date_from;
                }
                if($date_to) {
                    $query .= " AND DATE(created_at) <= :date_to";
                    $params[':date_to'] = $date_to;
                }
                
                $unions[] = $query;
            }
            
            $final_query = implode(' UNION ALL ', $unions) . " ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($final_query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get transactions by user error: " . $e->getMessage());
            return [];
        }
    }
}
?>