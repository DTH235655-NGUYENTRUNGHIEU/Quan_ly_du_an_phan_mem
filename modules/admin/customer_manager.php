<?php
// modules/admin/customer_manager.php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

/** @var PDO $pdo */

// 1. Kiểm tra quyền truy cập: bắt buộc đăng nhập và role_id khác 6 (Khách hàng)
if (!isset($_SESSION['user']) || $_SESSION['user']['role_id'] == 6) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$success_msg = '';
$error_msg = '';

// 2. Xử lý cập nhật điểm tích lũy thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_points') {
    $cust_id = intval($_POST['customer_id'] ?? 0);
    $point_change = intval($_POST['point_change'] ?? 0);

    if ($cust_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT reward_points FROM customers WHERE customer_id = ?");
        $stmtCheck->execute([$cust_id]);
        $cust = $stmtCheck->fetch();

        if ($cust) {
            $new_points = max(0, $cust['reward_points'] + $point_change);
            $stmtUp = $pdo->prepare("UPDATE customers SET reward_points = ? WHERE customer_id = ?");
            $stmtUp->execute([$new_points, $cust_id]);
            $success_msg = "Đã cập nhật điểm tích lũy thành công!";
        }
    }
}

// 3. Xử lý tìm kiếm khách hàng
$keyword = trim($_GET['keyword'] ?? '');
$params = [];

$sql = "SELECT c.*, 
               (SELECT u.username FROM users u WHERE u.user_id = c.user_id) AS account_username,
               (SELECT COUNT(b.booking_id) FROM bookings b WHERE b.customer_id = c.customer_id) AS total_bookings
        FROM customers c 
        WHERE 1 = 1 ";

if ($keyword !== '') {
    $sql .= " AND (c.fullname LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.id_card LIKE ?) ";
    $searchTerm = "%{$keyword}%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$sql .= " ORDER BY c.customer_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$page_title = "Quản Lý Khách Hàng & Điểm Thưởng";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-50">
    <!-- Sidebar -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Header & Search -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div>
                    <h1 class="text-xl font-black text-slate-800">Quản Lý Khách Hàng & Điểm Thưởng</h1>
                    <p class="text-xs text-slate-500 mt-1">Tra cứu thông tin du khách, số lượng đơn đã đặt và quản lý điểm tích lũy.</p>
                </div>

                <form method="GET" class="flex gap-2 w-full md:w-auto">
                    <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" 
                           placeholder="Tên, SĐT, Email hoặc CCCD..." 
                           class="text-xs border border-slate-300 rounded-xl px-4 py-2.5 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition">
                        Tìm kiếm
                    </button>
                    <?php if ($keyword !== ''): ?>
                        <a href="customer_manager.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold px-3 py-2.5 rounded-xl transition">
                            Hủy
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($success_msg): ?>
                <div class="p-3 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-medium">
                    ✓ <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <!-- Table Khách hàng -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-3.5 text-center w-12">STT</th>
                                <th class="p-3.5">Họ và Tên</th>
                                <th class="p-3.5">Thông tin liên hệ</th>
                                <th class="p-3.5">CCCD / Ngày sinh</th>
                                <th class="p-3.5 text-center">Tài khoản Web</th>
                                <th class="p-3.5 text-center">Đơn đã đặt</th>
                                <th class="p-3.5 text-center">Điểm tích lũy</th>
                                <th class="p-3.5 text-center w-48">Cập nhật điểm</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center p-8 text-slate-400">Không tìm thấy khách hàng nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $idx => $c): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-3.5 text-center font-bold text-slate-500"><?= $idx + 1 ?></td>
                                        <td class="p-3.5 font-bold text-slate-800">
                                            <?= htmlspecialchars($c['fullname']) ?>
                                            <div class="text-[11px] font-normal text-slate-400">Mã KH: #<?= $c['customer_id'] ?></div>
                                        </td>
                                        <td class="p-3.5 space-y-0.5">
                                            <div class="text-blue-600 font-semibold"><?= htmlspecialchars($c['phone']) ?></div>
                                            <div class="text-slate-500"><?= htmlspecialchars($c['email']) ?></div>
                                            <div class="text-[11px] text-slate-400 truncate max-w-xs"><?= htmlspecialchars($c['address'] ?? 'Chưa cập nhật') ?></div>
                                        </td>
                                        <td class="p-3.5">
                                            <div class="font-mono text-slate-700"><?= $c['id_card'] ?: 'Chưa có' ?></div>
                                            <div class="text-[11px] text-slate-400">
                                                <?= $c['birth_date'] ? date('d/m/Y', strtotime($c['birth_date'])) : '—' ?>
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <?php if ($c['account_username']): ?>
                                                <span class="inline-block px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-bold">
                                                    <?= htmlspecialchars($c['account_username']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[11px]">
                                                    Vãng lai
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-center font-bold text-slate-700">
                                            <?= $c['total_bookings'] ?> đơn
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-block px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-black text-xs">
                                                ⭐ <?= number_format($c['reward_points'], 0, ',', '.') ?> điểm
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <form method="POST" class="flex items-center justify-center gap-1.5">
                                                <input type="hidden" name="action" value="update_points">
                                                <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                                                <input type="number" name="point_change" placeholder="+/-" 
                                                       class="w-16 border border-slate-300 rounded-lg px-2 py-1 text-center text-xs focus:ring-1 focus:ring-blue-500" required>
                                                <button type="submit" title="Cộng hoặc trừ điểm" 
                                                        class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-2.5 py-1 rounded-lg text-xs transition">
                                                    Lưu
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>