<?php
// modules/admin/staff_manager.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Đảm bảo chỉ Administrator mới được truy cập trang Quản lý Nhân sự & Phân quyền
if (!has_role(['Administrator'])) {
    header('Location: ' . BASE_URL . 'modules/admin/dashboard.php');
    exit;
}

// Đảm bảo biến kết nối PDO tương thích với config/database.php
if (!isset($pdo)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } elseif (function_exists('getDB')) {
        $pdo = getDB();
    } else {
        $dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $dbName = defined('DB_NAME') ? DB_NAME : 'tour_dulich_vietnam';
        $dbUser = defined('DB_USER') ? DB_USER : 'root';
        $dbPass = defined('DB_PASS') ? DB_PASS : '';
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}

$currentUser = current_user();
$currentUserId = (int)($currentUser['user_id'] ?? $_SESSION['user_id'] ?? 0);

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
            $error = 'Vui lòng nhập đầy đủ họ tên, tên đăng nhập, email và mật khẩu.';
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
                $message = "Đã thêm nhân viên «{$fullname}» và cấp quyền thành công!";
            } catch (PDOException $e) {
                $error = 'Không thể tạo tài khoản: Username hoặc Email đã tồn tại trên hệ thống.';
            }
        }
    }

    // 2. CẬP NHẬT THÔNG TIN & ĐỔI PHÂN QUYỀN NHÂN VIÊN
    if ($action === 'edit') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $role_id  = (int)($_POST['role_id'] ?? 2);
        $status   = $_POST['status'] ?? 'Active';
        $password = $_POST['password'] ?? '';

        if ($user_id === $currentUserId && ($role_id !== 1 || $status !== 'Active')) {
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
                $message = "Đã cập nhật thông tin và phân quyền cho nhân viên #{$user_id}!";
            } catch (PDOException $e) {
                $error = 'Lỗi cập nhật: Email có thể đã trùng với một tài khoản khác.';
            }
        }
    }

    // 3. XÓA NHÂN VIÊN
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id === $currentUserId) {
            $error = 'Không thể xóa tài khoản Quản trị viên đang đăng nhập!';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id AND role_id != 6");
                $stmt->execute([':user_id' => $user_id]);
                $message = "Đã xóa nhân viên #{$user_id} khỏi hệ thống.";
            } catch (PDOException $e) {
                $error = 'Không thể xóa nhân viên này do ràng buộc dữ liệu. Vui lòng chuyển trạng thái sang Locked.';
            }
        }
    }
}

// Lấy danh sách các vai trò nội bộ công ty (role_id từ 1 đến 5, không lấy Customer = 6)
$rolesStmt = $pdo->query("SELECT * FROM roles WHERE role_id != 6 ORDER BY role_id ASC");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Bộ lọc chức vụ & từ khóa
$filterRole = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
$keyword    = isset($_GET['q']) ? trim($_GET['q']) : '';

$sqlStaff = "SELECT u.*, r.role_name, r.description AS role_desc 
             FROM users u 
             INNER JOIN roles r ON u.role_id = r.role_id 
             WHERE u.role_id != 6";
$params = [];

if ($filterRole > 0) {
    $sqlStaff .= " AND u.role_id = :role_id";
    $params[':role_id'] = $filterRole;
}
if ($keyword !== '') {
    $sqlStaff .= " AND (u.fullname LIKE :kw OR u.username LIKE :kw OR u.email LIKE :kw OR u.phone LIKE :kw)";
    $params[':kw'] = '%' . $keyword . '%';
}

$sqlStaff .= " ORDER BY u.role_id ASC, u.user_id DESC";
$stmtStaff = $pdo->prepare($sqlStaff);
$stmtStaff->execute($params);
$staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Sidebar Quản trị -->
    <?php include __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Nội dung chính -->
    <main class="flex-1 p-6 lg:p-8 overflow-x-auto">
        <!-- Header trang -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Quản lý Nhân sự & Phân quyền</h1>
                <p class="text-xs text-slate-500 mt-1">Thêm mới, điều chỉnh cấp bậc phân quyền (Quản trị, Điều hành, Sales, HDV, Kế toán) và quản lý trạng thái tài khoản</p>
            </div>
            <button type="button" onclick="openAddModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Thêm nhân viên mới
            </button>
        </div>

        <!-- Thông báo kết quả -->
        <?php if ($message): ?>
            <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">✕</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">✕</button>
            </div>
        <?php endif; ?>

        <!-- Thanh công cụ tìm kiếm & lọc theo vai trò -->
        <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 mb-6 flex flex-col md:flex-row gap-3 items-center">
            <div class="relative flex-1 w-full">
                <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" 
                       placeholder="Tìm theo họ tên, username, email hoặc số điện thoại..." 
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
            </div>
            <div class="w-full md:w-72">
                <select name="role_id" onchange="this.form.submit()" 
                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="0">-- Tất cả chức vụ / phân quyền --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['role_id'] ?>" <?= $filterRole === (int)$r['role_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition">
                Lọc dữ liệu
            </button>
            <?php if ($filterRole > 0 || $keyword !== ''): ?>
                <a href="staff_manager.php" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold transition">
                    Đặt lại
                </a>
            <?php endif; ?>
        </form>

        <!-- Bảng danh sách nhân viên -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase text-slate-500 tracking-wider">
                            <th class="py-3.5 px-4">Mã NV</th>
                            <th class="py-3.5 px-4">Họ và tên</th>
                            <th class="py-3.5 px-4">Tài khoản</th>
                            <th class="py-3.5 px-4">Liên hệ</th>
                            <th class="py-3.5 px-4">Vai trò / Phân quyền</th>
                            <th class="py-3.5 px-4">Trạng thái</th>
                            <th class="py-3.5 px-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <?php if (empty($staffList)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">Không tìm thấy nhân viên nào phù hợp.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $st): ?>
                                <?php
                                $roleBadge = match ((int)$st['role_id']) {
                                    1 => 'bg-purple-100 text-purple-700 border-purple-200',
                                    2 => 'bg-blue-100 text-blue-700 border-blue-200',
                                    3 => 'bg-amber-100 text-amber-700 border-amber-200',
                                    4 => 'bg-teal-100 text-teal-700 border-teal-200',
                                    5 => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200'
                                };
                                $statusBadge = match ($st['status']) {
                                    'Active'   => 'bg-emerald-100 text-emerald-700',
                                    'Locked'   => 'bg-rose-100 text-rose-700',
                                    default    => 'bg-slate-100 text-slate-600'
                                };
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4 font-bold text-slate-500">#<?= (int)$st['user_id'] ?></td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($st['fullname']) ?></div>
                                        <div class="text-[11px] text-slate-400">Tạo ngày: <?= date('d/m/Y', strtotime($st['created_at'])) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 font-mono text-[11px] font-semibold text-slate-700">
                                            <?= htmlspecialchars($st['username']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-slate-700"><?= htmlspecialchars($st['email']) ?></div>
                                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($st['phone']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-block px-2.5 py-1 rounded-lg border text-[11px] font-bold <?= $roleBadge ?>">
                                            <?= htmlspecialchars($st['role_name']) ?>
                                        </span>
                                        <div class="text-[11px] text-slate-400 mt-1 max-w-xs"><?= htmlspecialchars($st['role_desc']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?= $statusBadge ?>">
                                            <?= htmlspecialchars($st['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                        <button type="button" 
                                                onclick='openEditModal(<?= json_encode($st, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 font-bold text-[11px] transition">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Sửa / Phân quyền
                                        </button>

                                        <?php if ((int)$st['user_id'] !== $currentUserId): ?>
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhân viên <?= htmlspecialchars(addslashes($st['fullname'])) ?>?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= (int)$st['user_id'] ?>">
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-[11px] transition">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Xóa
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL THÊM NHÂN VIÊN MỚI -->
<div id="addStaffModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="text-sm font-extrabold">Thêm nhân viên mới & Phân quyền</h3>
            <button type="button" onclick="closeModal('addStaffModal')" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4 text-xs">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Họ và tên nhân viên *</label>
                <input type="text" name="fullname" required placeholder="VD: Nguyễn Văn A"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tên đăng nhập (Username) *</label>
                    <input type="text" name="username" required placeholder="VD: nv_sale01"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Mật khẩu khởi tạo *</label>
                    <input type="password" name="password" required placeholder="Nhập mật khẩu..."
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email công việc *</label>
                    <input type="email" name="email" required placeholder="ten@dulichvietnam.vn"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Số điện thoại *</label>
                    <input type="text" name="phone" required placeholder="09xxxxxxxx"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block font-bold text-blue-700 mb-1">Cấp độ Phân quyền (Role) *</label>
                <select name="role_id" required class="w-full px-3.5 py-2 rounded-xl border border-blue-300 bg-blue-50/40 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['role_id'] ?>">
                            <?= htmlspecialchars($r['role_name']) ?> — <?= htmlspecialchars($r['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Trạng thái hoạt động</label>
                <select name="status" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="Active">Active (Đang làm việc)</option>
                    <option value="Inactive">Inactive (Tạm ngưng)</option>
                    <option value="Locked">Locked (Khóa tài khoản)</option>
                </select>
            </div>
            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('addStaffModal')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold">Hủy</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow">Tạo nhân viên</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA & PHÂN QUYỀN NHÂN VIÊN -->
<div id="editStaffModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="text-sm font-extrabold">Cập nhật thông tin & Phân quyền nhân sự</h3>
            <button type="button" onclick="closeModal('editStaffModal')" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4 text-xs">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Họ và tên nhân viên *</label>
                <input type="text" name="fullname" id="edit_fullname" required
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email công việc *</label>
                    <input type="email" name="email" id="edit_email" required
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Số điện thoại *</label>
                    <input type="text" name="phone" id="edit_phone" required
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block font-bold text-blue-700 mb-1">Cấp độ Phân quyền (Role) *</label>
                <select name="role_id" id="edit_role_id" required class="w-full px-3.5 py-2 rounded-xl border border-blue-300 bg-blue-50/40 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['role_id'] ?>">
                            <?= htmlspecialchars($r['role_name']) ?> — <?= htmlspecialchars($r['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Trạng thái tài khoản</label>
                    <select name="status" id="edit_status" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Active">Active (Đang làm việc)</option>
                        <option value="Inactive">Inactive (Tạm ngưng)</option>
                        <option value="Locked">Locked (Khóa truy cập)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Đổi mật khẩu mới (Tùy chọn)</label>
                    <input type="password" name="password" placeholder="Để trống nếu giữ nguyên"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('editStaffModal')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold">Đóng</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addStaffModal').classList.remove('hidden');
}

function openEditModal(staff) {
    document.getElementById('edit_user_id').value = staff.user_id;
    document.getElementById('edit_fullname').value = staff.fullname;
    document.getElementById('edit_email').value = staff.email;
    document.getElementById('edit_phone').value = staff.phone;
    document.getElementById('edit_role_id').value = staff.role_id;
    document.getElementById('edit_status').value = staff.status;
    document.getElementById('editStaffModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>