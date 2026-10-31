<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';

// Chỉ cho phép Administrator (role_id = 1) truy cập quản lý nhân sự
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] !== 1)) {
    header('Location: ../../index.php');
    exit;
}

$message = '';
$error   = '';

// Xử lý Thêm / Sửa / Xóa nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. THÊM NHÂN VIÊN MỚI & PHÂN QUYỀN
    if ($action === 'add') {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id  = (int)($_POST['role_id'] ?? 2);
        $status   = $_POST['status'] ?? 'Active';

        if ($fullname === '' || $username === '' || $email === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ họ tên, tài khoản, email và mật khẩu.';
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (role_id, username, password_hash, fullname, email, phone, status)
                    VALUES (:role_id, :username, :password_hash, :fullname, :email, :phone, :status)
                ");
                $stmt->execute([
                    ':role_id'       => $role_id,
                    ':username'      => $username,
                    ':password_hash' => $password_hash,
                    ':fullname'      => $fullname,
                    ':email'         => $email,
                    ':phone'         => $phone,
                    ':status'        => $status
                ]);
                $message = "Đã thêm nhân viên {$fullname} và phân quyền thành công!";
            } catch (PDOException $e) {
                $error = 'Lỗi: Tên đăng nhập hoặc Email đã tồn tại trên hệ thống.';
            }
        }
    }

    // 2. CẬP NHẬT THÔNG TIN & ĐỔI QUYỀN NHÂN VIÊN
    if ($action === 'edit') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $role_id  = (int)($_POST['role_id'] ?? 2);
        $status   = $_POST['status'] ?? 'Active';
        $password = $_POST['password'] ?? '';

        if ($user_id === (int)$_SESSION['user_id'] && ($role_id !== 1 || $status !== 'Active')) {
            $error = 'Bạn không thể tự hạ quyền hoặc tự khóa tài khoản Quản trị viên đang đăng nhập.';
        } else {
            try {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET fullname = :fullname, email = :email, phone = :phone, 
                            role_id = :role_id, status = :status, password_hash = :password_hash
                        WHERE user_id = :user_id
                    ");
                    $stmt->execute([
                        ':fullname'      => $fullname,
                        ':email'         => $email,
                        ':phone'         => $phone,
                        ':role_id'       => $role_id,
                        ':status'        => $status,
                        ':password_hash' => $password_hash,
                        ':user_id'       => $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET fullname = :fullname, email = :email, phone = :phone, 
                            role_id = :role_id, status = :status
                        WHERE user_id = :user_id
                    ");
                    $stmt->execute([
                        ':fullname' => $fullname,
                        ':email'    => $email,
                        ':phone'    => $phone,
                        ':role_id'  => $role_id,
                        ':status'   => $status,
                        ':user_id'  => $user_id
                    ]);
                }
                $message = "Cập nhật thông tin và phân quyền nhân viên #{$user_id} thành công!";
            } catch (PDOException $e) {
                $error = 'Không thể cập nhật: Email có thể bị trùng với tài khoản khác.';
            }
        }
    }

    // 3. XÓA NHÂN VIÊN
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id === (int)$_SESSION['user_id']) {
            $error = 'Không thể xóa tài khoản Quản trị viên đang đăng nhập!';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id AND role_id != 6");
                $stmt->execute([':user_id' => $user_id]);
                $message = "Đã xóa nhân viên #{$user_id} khỏi hệ thống.";
            } catch (PDOException $e) {
                $error = 'Không thể xóa nhân viên này. Hãy chuyển trạng thái sang Locked (Khóa).';
            }
        }
    }
}

// Lấy danh sách các vai trò nội bộ (role_id từ 1 đến 5, trừ Customer = 6)
$rolesStmt = $pdo->query("SELECT * FROM roles WHERE role_id != 6 ORDER BY role_id ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Lọc danh sách nhân sự
$filterRole = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
$sqlStaff = "SELECT u.*, r.role_name, r.description AS role_desc 
             FROM users u 
             INNER JOIN roles r ON u.role_id = r.role_id 
             WHERE u.role_id != 6";
$params = [];
if ($filterRole > 0) {
    $sqlStaff .= " AND u.role_id = :role_id";
    $params[':role_id'] = $filterRole;
}
$sqlStaff .= " ORDER BY u.role_id ASC, u.user_id DESC";
$stmtStaff = $pdo->prepare($sqlStaff);
$stmtStaff->execute($params);
$staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold mb-0">Quản lý Nhân sự & Phân quyền</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    + Thêm nhân viên mới
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Bộ lọc theo chức vụ / phân quyền -->
            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="role_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Tất cả chức vụ / phòng ban --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>" <?= $filterRole === (int)$r['role_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['role_name']) ?> (<?= htmlspecialchars($r['description']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <!-- Bảng danh sách nhân viên -->
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Họ và tên</th>
                                <th>Tài khoản</th>
                                <th>Email / SĐT</th>
                                <th>Vai trò (Phân quyền)</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffList as $st): ?>
                                <tr>
                                    <td>#<?= $st['user_id'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($st['fullname']) ?></td>
                                    <td><code><?= htmlspecialchars($st['username']) ?></code></td>
                                    <td>
                                        <div><?= htmlspecialchars($st['email']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($st['phone']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($st['role_name']) ?></span>
                                        <div class="small text-muted"><?= htmlspecialchars($st['role_desc']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($st['status'] === 'Active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($st['status'] === 'Locked'): ?>
                                            <span class="badge bg-danger">Locked</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editStaffModal<?= $st['user_id'] ?>">
                                            Sửa / Phân quyền
                                        </button>
                                        <?php if ((int)$st['user_id'] !== (int)$_SESSION['user_id']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhân viên này?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $st['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal Sửa & Phân quyền nhân viên -->
                                <div class="modal fade" id="editStaffModal<?= $st['user_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Cập nhật & Phân quyền: <?= htmlspecialchars($st['fullname']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="user_id" value="<?= $st['user_id'] ?>">
                                                <div class="mb-2">
                                                    <label class="form-label">Họ và tên</label>
                                                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($st['fullname']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($st['email']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Số điện thoại</label>
                                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($st['phone']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-bold">Chức vụ / Cấp độ phân quyền</label>
                                                    <select name="role_id" class="form-select" required>
                                                        <?php foreach ($roles as $r): ?>
                                                            <option value="<?= $r['role_id'] ?>" <?= (int)$st['role_id'] === (int)$r['role_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($r['role_name']) ?> - <?= htmlspecialchars($r['description']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Trạng thái tài khoản</label>
                                                    <select name="status" class="form-select">
                                                        <option value="Active" <?= $st['status'] === 'Active' ? 'selected' : '' ?>>Active (Đang làm việc)</option>
                                                        <option value="Inactive" <?= $st['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive (Tạm nghỉ)</option>
                                                        <option value="Locked" <?= $st['status'] === 'Locked' ? 'selected' : '' ?>>Locked (Khóa truy cập)</option>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Mật khẩu mới (Để trống nếu giữ nguyên)</label>
                                                    <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu mới nếu cần đổi...">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm nhân viên mới -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm nhân viên mới & Phân quyền</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="mb-2">
                    <label class="form-label">Họ và tên nhân viên</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Tên đăng nhập (Username)</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Mật khẩu khởi tạo</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email công việc</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold">Phân quyền chức vụ</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>">
                                <?= htmlspecialchars($r['role_name']) ?> - <?= htmlspecialchars($r['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active (Kích hoạt ngay)</option>
                        <option value="Inactive">Inactive (Chưa kích hoạt)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-success">Tạo tài khoản nhân viên</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>