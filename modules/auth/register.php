<?php
// modules/auth/register.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $id_card = trim($_POST['id_card'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? 'Nam';
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    if (empty($fullname) || empty($username) || empty($email) || empty($phone) || empty($password)) {
        $error_msg = "Vui lòng nhập đầy đủ các trường thông tin bắt buộc (*).";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Xác nhận mật khẩu không trùng khớp.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Mật khẩu phải chứa ít nhất 6 ký tự.";
    } else {
        // Kiểm tra username hoặc email đã tồn tại trong bảng users chưa
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->fetch()) {
            $error_msg = "Tên đăng nhập hoặc địa chỉ email này đã được sử dụng.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Tạo tài khoản trong bảng users với vai trò Customer (role_id = 6)
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (role_id, username, password_hash, fullname, email, phone, status) 
                                           VALUES (6, ?, ?, ?, ?, ?, 'Active')");
                $stmtUser->execute([$username, $password_hash, $fullname, $email, $phone]);
                $user_id = $pdo->lastInsertId();

                // 2. Tạo hồ sơ đồng bộ trong bảng customers
                $stmtCust = $pdo->prepare("INSERT INTO customers (user_id, fullname, email, phone, id_card, address, gender, birth_date, reward_points) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmtCust->execute([$user_id, $fullname, $email, $phone, $id_card, $address, $gender, $birth_date]);

                $pdo->commit();
                $success_msg = "Đăng ký tài khoản thành công! Bây giờ bạn có thể đăng nhập để đặt tour.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Có lỗi xảy ra trong quá trình đăng ký: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Đăng Ký Tài Khoản Du Khách - Du Lịch Việt Nam";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
        <div class="border-b pb-4 mb-6 text-center">
            <h1 class="text-2xl font-black text-slate-900">Đăng Ký Tài Khoản Du Khách</h1>
            <p class="text-xs text-slate-500 mt-1">Lưu trữ thông tin xuất hóa đơn VAT, tích điểm thưởng và mua bảo hiểm du lịch tự động</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs space-y-3">
                <div class="flex items-center gap-2 font-bold">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span><?= $success_msg ?></span>
                </div>
                <a href="<?= BASE_URL ?>modules/auth/login.php" class="inline-block bg-emerald-600 text-white font-bold px-4 py-2 rounded-lg text-xs hover:bg-emerald-700">Đến trang Đăng Nhập</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error_msg)): ?>
                <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Họ và tên (*)</label>
                        <input type="text" name="fullname" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tên đăng nhập (*)</label>
                        <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="nguyenvana">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email (*)</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="vana@gmail.com">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Số điện thoại (*)</label>
                        <input type="tel" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="0912345678">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mật khẩu (*)</label>
                        <input type="password" name="password" required class="w-full border rounded-lg p-2.5" placeholder="Tối thiểu 6 ký tự">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Xác nhận mật khẩu (*)</label>
                        <input type="password" name="confirm_password" required class="w-full border rounded-lg p-2.5" placeholder="Nhập lại mật khẩu">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Số CCCD / Hộ chiếu</label>
                        <input type="text" name="id_card" value="<?= htmlspecialchars($_POST['id_card'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="08909800xxxx">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Giới tính</label>
                        <select name="gender" class="w-full border rounded-lg p-2.5">
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Ngày sinh</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" class="w-full border rounded-lg p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Địa chỉ thường trú</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" class="w-full border rounded-lg p-2.5" placeholder="TP. Long Xuyên, An Giang">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow">
                    ĐĂNG KÝ TÀI KHOẢN
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>