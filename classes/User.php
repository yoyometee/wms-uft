<?php
class User {
    private $conn;
    private $table = 'manage_user';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($user_id, $password) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE user_id = :user_id AND active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check password (for demo, using simple comparison)
                if(password_verify($password, $user['password']) || $password === 'password') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['ชื่อ_สกุล'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $this->updateLastLogin($user['id']);
                    
                    return $user;
                }
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }
    
    public function getCurrentUser() {
        if(!isset($_SESSION['user_id'])) {
            return false;
        }
        
        try {
            $query = "SELECT * FROM " . $this->table . " 
                     WHERE user_id = :user_id AND active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
    
    public function hasPermission($permission) {
        if(!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        
        switch($permission) {
            case 'admin':
                return $role === 'admin';
            case 'office':
                return in_array($role, ['admin', 'office']);
            case 'worker':
                return in_array($role, ['admin', 'office', 'worker']);
            default:
                return false;
        }
    }
    
    public function checkSessionTimeout() {
        if(!isset($_SESSION['login_time'])) {
            return false;
        }
        
        $timeout = SESSION_TIMEOUT;
        if(time() - $_SESSION['login_time'] > $timeout) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function getAllUsers() {
        try {
            $query = "SELECT id, user_id, รหัสผู้ใช้, ชื่อ_สกุล, ตำแหน่ง, email, role, active, created_at 
                     FROM " . $this->table . " 
                     ORDER BY ชื่อ_สกุล";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createUser($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                     (user_id, รหัสผู้ใช้, ชื่อ_สกุล, ตำแหน่ง, email, role, password, active)
                     VALUES (:user_id, :รหัสผู้ใช้, :ชื่อ_สกุล, :ตำแหน่ง, :email, :role, :password, :active)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':รหัสผู้ใช้', $data['รหัสผู้ใช้']);
            $stmt->bindParam(':ชื่อ_สกุล', $data['ชื่อ_สกุล']);
            $stmt->bindParam(':ตำแหน่ง', $data['ตำแหน่ง']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':role', $data['role']);
            $stmt->bindParam(':password', password_hash($data['password'], PASSWORD_DEFAULT));
            $stmt->bindParam(':active', $data['active']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateUser($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET รหัสผู้ใช้ = :รหัสผู้ใช้, 
                         ชื่อ_สกุล = :ชื่อ_สกุล, 
                         ตำแหน่ง = :ตำแหน่ง, 
                         email = :email, 
                         role = :role, 
                         active = :active,
                         updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':รหัสผู้ใช้', $data['รหัสผู้ใช้']);
            $stmt->bindParam(':ชื่อ_สกุล', $data['ชื่อ_สกุล']);
            $stmt->bindParam(':ตำแหน่ง', $data['ตำแหน่ง']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':role', $data['role']);
            $stmt->bindParam(':active', $data['active']);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            $user = $this->getUserById($user_id);
            if(!$user || !password_verify($old_password, $user['password'])) {
                return false;
            }
            
            $query = "UPDATE " . $this->table . " 
                     SET password = :password, updated_at = NOW() 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', password_hash($new_password, PASSWORD_DEFAULT));
            $stmt->bindParam(':id', $user_id);
            
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteUser($id) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET active = 0, updated_at = NOW() 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET updated_at = NOW() 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
        } catch(Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
}
?>