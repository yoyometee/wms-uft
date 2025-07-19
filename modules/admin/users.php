<?php
// Include new configuration system
require_once '../../config/app_config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

// Check login and admin permission
checkPermission('admin');

// Initialize database connection
$db = getDBConnection();

// Initialize classes
$user = new User($db);

// Get current user
$current_user = $user->getCurrentUser();
if(!$current_user) {
    header('Location: ../../logout.php');
    exit;
}

$error_message = '';
$success_message = '';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'create':
                    $user_data = [
                        'user_id' => sanitizeInput($_POST['user_id']),
                        'รหัสผู้ใช้' => sanitizeInput($_POST['รหัสผู้ใช้']),
                        'ชื่อ_สกุล' => sanitizeInput($_POST['ชื่อ_สกุล']),
                        'ตำแหน่ง' => sanitizeInput($_POST['ตำแหน่ง']),
                        'email' => sanitizeInput($_POST['email']),
                        'role' => sanitizeInput($_POST['role']),
                        'password' => $_POST['password'],
                        'active' => 1
                    ];
                    
                    if($user->createUser($user_data)) {
                        $success_message = 'เพิ่มผู้ใช้เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถเพิ่มผู้ใช้ได้';
                    }
                    break;
                    
                case 'update':
                    $user_id = (int)$_POST['user_id'];
                    $user_data = [
                        'รหัสผู้ใช้' => sanitizeInput($_POST['รหัสผู้ใช้']),
                        'ชื่อ_สกุล' => sanitizeInput($_POST['ชื่อ_สกุล']),
                        'ตำแหน่ง' => sanitizeInput($_POST['ตำแหน่ง']),
                        'email' => sanitizeInput($_POST['email']),
                        'role' => sanitizeInput($_POST['role']),
                        'active' => (int)$_POST['active']
                    ];
                    
                    if($user->updateUser($user_id, $user_data)) {
                        $success_message = 'อัพเดตข้อมูลผู้ใช้เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'ไม่สามารถอัพเดตข้อมูลได้';
                    }
                    break;
                    
                case 'delete':
                    $user_id = (int)$_POST['user_id'];
                    if($user_id != $current_user['id']) { // Can't delete self
                        if($user->deleteUser($user_id)) {
                            $success_message = 'ลบผู้ใช้เรียบร้อยแล้ว';
                        } else {
                            $error_message = 'ไม่สามารถลบผู้ใช้ได้';
                        }
                    } else {
                        $error_message = 'ไม่สามารถลบตัวเองได้';
                    }
                    break;
            }
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all users
$users = $user->getAllUsers();

$page_title = 'จัดการผู้ใช้';
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-users"></i> จัดการผู้ใช้</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../../" class="text-white">หน้าหลัก</a></li>
                                    <li class="breadcrumb-item"><a href="index.php" class="text-white">จัดการระบบ</a></li>
                                    <li class="breadcrumb-item active text-white">จัดการผู้ใช้</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus"></i> เพิ่มผู้ใช้ใหม่
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-table"></i> รายการผู้ใช้งาน</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="exportUsers()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-info btn-sm" onclick="refreshTable()">
                            <i class="fas fa-sync"></i> รีเฟรช
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>รหัส</th>
                                    <th>ชื่อ-สกุล</th>
                                    <th>ตำแหน่ง</th>
                                    <th>อีเมล</th>
                                    <th>บทบาท</th>
                                    <th>สถานะ</th>
                                    <th>วันที่สร้าง</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['ชื่อ_สกุล']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($u['รหัสผู้ใช้']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['ตำแหน่ง']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php
                                        $role_colors = [
                                            'admin' => 'danger',
                                            'office' => 'warning',
                                            'worker' => 'primary'
                                        ];
                                        $role_names = [
                                            'admin' => 'ผู้ดูแลระบบ',
                                            'office' => 'เจ้าหน้าที่',
                                            'worker' => 'พนักงาน'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $role_colors[$u['role']] ?? 'secondary'; ?>">
                                            <?php echo $role_names[$u['role']] ?? $u['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $u['active'] ? 'ใช้งาน' : 'ระงับ'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($u['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if($u['id'] != $current_user['id']): ?>
                                            <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['ชื่อ_สกุล']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">User ID <span class="text-danger">*</span></label>
                            <input type="text" name="user_id" class="form-control" required pattern="[A-Z0-9]+" 
                                   placeholder="เช่น ADMIN001">
                            <div class="form-text">ใช้ตัวพิมพ์ใหญ่และตัวเลขเท่านั้น</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">รหัสผู้ใช้</label>
                            <input type="text" name="รหัสผู้ใช้" class="form-control" placeholder="รหัสพนักงาน">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อ-สกุล <span class="text-danger">*</span></label>
                            <input type="text" name="ชื่อ_สกุล" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ตำแหน่ง</label>
                            <input type="text" name="ตำแหน่ง" class="form-control" placeholder="เช่น พนักงานคลัง">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">บทบาท <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">เลือกบทบาท</option>
                                <option value="worker">พนักงาน</option>
                                <option value="office">เจ้าหน้าที่</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-text">ความยาวอย่างน้อย 6 ตัวอักษร</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> แก้ไขข้อมูลผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">รหัสผู้ใช้</label>
                            <input type="text" name="รหัสผู้ใช้" id="edit_รหัสผู้ใช้" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อ-สกุล <span class="text-danger">*</span></label>
                            <input type="text" name="ชื่อ_สกุล" id="edit_ชื่อ_สกุล" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ตำแหน่ง</label>
                            <input type="text" name="ตำแหน่ง" id="edit_ตำแหน่ง" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">บทบาท <span class="text-danger">*</span></label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="worker">พนักงาน</option>
                                <option value="office">เจ้าหน้าที่</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สถานะ</label>
                            <select name="active" id="edit_active" class="form-select">
                                <option value="1">ใช้งาน</option>
                                <option value="0">ระงับ</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">อัพเดต</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
        order: [[6, 'desc']], // Sort by created date
        pageLength: 25,
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        },
        columnDefs: [
            { targets: -1, orderable: false } // Disable sorting for actions column
        ]
    });
});

function editUser(userData) {
    $('#edit_user_id').val(userData.id);
    $('#edit_รหัสผู้ใช้').val(userData.รหัสผู้ใช้);
    $('#edit_ชื่อ_สกุล').val(userData.ชื่อ_สกุล);
    $('#edit_ตำแหน่ง').val(userData.ตำแหน่ง);
    $('#edit_email').val(userData.email);
    $('#edit_role').val(userData.role);
    $('#edit_active').val(userData.active);
    
    $('#editUserModal').modal('show');
}

function deleteUser(userId, userName) {
    if(confirm(`ต้องการลบผู้ใช้ "${userName}" หรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportUsers() {
    // Export users to CSV
    const table = document.getElementById('usersTable');
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'รหัส,ชื่อ-สกุล,ตำแหน่ง,อีเมล,บทบาท,สถานะ,วันที่สร้าง\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        for(let i = 0; i < cells.length - 1; i++) { // Exclude actions column
            rowData.push('"' + cells[i].textContent.trim().replace(/"/g, '""') + '"');
        }
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'users_export_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

function refreshTable() {
    location.reload();
}
</script>

<?php include '../../includes/footer.php'; ?>