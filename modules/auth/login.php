<?php
// modules/auth/login.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error_msg = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = trim($_POST['account'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($account) || empty($password)) {
        $error_msg = "Vui lòng điền đầy đủ tài khoản/email và mật khẩu.";
    } else {
        // Tìm kiếm user theo username hoặc email
        $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.role_id 
                               WHERE (u.username = ? OR u.email = ?) LIMIT 1");
        $stmt->execute([$account, $account]);
        $user = $stmt->fetch();

        if (!$user) {
            $error_msg = "Tài khoản hoặc email không tồn tại trong hệ thống.";
        } elseif ($user['status'] === 'Locked' || $user['status'] === 'Inactive') {
            $error_msg = "Tài khoản này hiện đang bị khóa hoặc ngưng hoạt động. Vui lòng liên hệ ban quản trị.";
        } else {
            // Kiểm tra mật khẩu (hỗ trợ cả Bcrypt hiện đại và MD5 từ database mẫu)
            $password_valid = false;
            if (password_verify($password, $user['password_hash'])) {
                $password_valid = true;
            } elseif (md5($password) === $user['password_hash']) {
                $password_valid = true;
                // Tự động nâng cấp hash mật khẩu sang Bcrypt an toàn
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $upStmt->execute([$new_hash, $user['user_id']]);
            }

            if ($password_valid) {
                // Lấy thông tin customer_id nếu tài khoản này là khách hàng
                $custStmt = $pdo->prepare("SELECT customer_id, reward_points FROM customers WHERE user_id = ? LIMIT 1");
                $custStmt->execute([$user['user_id']]);
                $cust = $custStmt->fetch();

                // Lưu dữ liệu đăng nhập vào $_SESSION
                $_SESSION['user'] = [
                    'user_id'       => $user['user_id'],
                    'role_id'       => $user['role_id'],
                    'role_name'     => $user['role_name'],
                    'username'      => $user['username'],
                    'fullname'      => $user['fullname'],
                    'email'         => $user['email'],
                    'phone'         => $user['phone'],
                    'customer_id'   => $cust['customer_id'] ?? null,
                    'reward_points' => $cust['reward_points'] ?? 0
                ];

                // Điều hướng sau đăng nhập
                if (!empty($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header("Location: " . $redirect);
                    exit;
                }

                // Nếu là nhân sự quản trị -> vào Dashboard, nếu là khách -> về Trang chủ
                if (in_array($user['role_name'], ['Administrator', 'Sales Staff', 'Tour Operator', 'Accountant', 'Tour Guide'])) {
                    header("Location: " . BASE_URL . "modules/admin/dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "index.php");
                }
                exit;
            } else {
                $error_msg = "Mật khẩu đăng nhập không chính xác.";
            }
        }
    }
}

$page_title = "Đăng Nhập Hệ Thống - Công ty Du lịch Việt Nam";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
        <div class="text-center">
            <div class="w-12 h-12 bg-blue-600 text-white rounded-xl mx-auto flex items-center justify-center shadow-md shadow-blue-500/30">
                <i data-lucide="lock" class="w-6 h-6"></i>
            </div>
            <h2 class="mt-4 text-2xl font-black text-slate-900 tracking-tight">Đăng Nhập Hệ Thống</h2>
            <p class="text-xs text-slate-500 mt-1">Cổng dùng chung cho Cán bộ nhân viên & Du khách</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-6 space-y-4" method="POST">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tên đăng nhập hoặc Email</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </span>
                    <input type="text" name="account" required value="<?= htmlspecialchars($_POST['account'] ?? '') ?>"
                           class="w-full pl-9 pr-3 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                           placeholder="hieu_pm hoặc yourname@gmail.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mật khẩu</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="key-round" class="w-4 h-4"></i>
                    </span>
                    <input type="password" name="password" required
                           class="w-full pl-9 pr-3 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                           placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center text-slate-600">
                    <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mr-2">
                    Ghi nhớ đăng nhập
                </label>
                <a href="#" class="font-semibold text-blue-600 hover:underline">Quên mật khẩu?</a>
            </div>

            <button type="submit" 
                    class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-xs transition duration-200 shadow-md shadow-blue-500/20 flex items-center justify-center gap-1.5">
                <i data-lucide="log-in" class="w-4 h-4"></i> Xác Nhận Đăng Nhập
            </button>
        </form>

        <div class="border-t border-slate-100 pt-4 text-center text-xs text-slate-500">
            Bạn chưa có tài khoản khách hàng? 
            <a href="<?= BASE_URL ?>modules/auth/register.php" class="font-bold text-blue-600 hover:underline">Đăng ký ngay</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>