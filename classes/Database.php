<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }
    
    public function connect() {
        $this->conn = null;
        
        // Auto-detect port if not specified
        $host_with_port = $this->host;
        if(defined('DB_PORT') && DB_PORT) {
            $host_with_port = $this->host . ':' . DB_PORT;
        } elseif(strpos($this->host, ':') === false) {
            // Try multiple ports automatically
            $ports_to_try = ['3306', '3307'];
            
            foreach($ports_to_try as $port) {
                try {
                    $test_dsn = "mysql:host=" . $this->host . ";port=" . $port . ";charset=" . DB_CHARSET;
                    $test_conn = new PDO($test_dsn, $this->username, $this->password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3
                    ]);
                    $host_with_port = $this->host . ':' . $port;
                    $test_conn = null;
                    break;
                } catch(PDOException $e) {
                    continue;
                }
            }
        }
        
        try {
            $dsn = "mysql:host=" . $host_with_port . ";dbname=" . $this->db_name . ";charset=" . DB_CHARSET;
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                    PDO::ATTR_TIMEOUT => 10
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        return $this->conn;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
    
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollback() {
        return $this->conn->rollback();
    }
    
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    public function quote($string) {
        return $this->conn->quote($string);
    }
    
    public function prepare($query) {
        return $this->conn->prepare($query);
    }
    
    public function query($query) {
        return $this->conn->query($query);
    }
    
    public function exec($query) {
        return $this->conn->exec($query);
    }
    
    public function isConnected() {
        return $this->conn !== null;
    }
    
    public function testConnection() {
        try {
            $this->connect();
            $stmt = $this->conn->query("SELECT 1");
            return $stmt !== false;
        } catch(Exception $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTableList() {
        try {
            $stmt = $this->conn->query("SHOW TABLES");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            error_log("Get table list failed: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTableRowCount($table) {
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) FROM `$table`");
            return $stmt->fetchColumn();
        } catch(Exception $e) {
            error_log("Get row count failed for table $table: " . $e->getMessage());
            return 0;
        }
    }
}
?>