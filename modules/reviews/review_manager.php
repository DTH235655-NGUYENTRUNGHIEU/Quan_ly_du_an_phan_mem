<?php
// modules/reviews/review_manager.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Administrator và Sales Staff được quyền kiểm duyệt bình luận
require_roles(['Administrator', 'Sales Staff']);

$currentUser = current_user();
$msg_success = "";

// 2. Xử lý thao tác Duyệt / Ẩn / Xóa đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $review_id = intval($_POST['review_id'] ?? 0);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE reviews SET status = 'Đã duyệt' WHERE review_id = ?")->execute([$review_id]);
        $msg_success = "Đã duyệt và cho phép hiển thị đánh giá #{$review_id} trên trang tour!";
    } elseif ($action === 'hide') {
        $pdo->prepare("UPDATE reviews SET status = 'Ẩn' WHERE review_id = ?")->execute([$review_id]);
        $msg_success = "Đã ẩn đánh giá #{$review_id} khỏi website.";
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM reviews WHERE review_id = ?")->execute([$review_id]);
        $msg_success = "Đã xóa hoàn toàn đánh giá khỏi cơ sở dữ liệu.";
    }
}

// 3. Lấy danh sách toàn bộ đánh giá từ bảng reviews
$status_filter = trim($_GET['status'] ?? '');
$where = "1=1";
$params = [];

if (!empty($status_filter)) {
    $where = "r.status = ?";
    $params[] = $status_filter;
}

$sqlRev = "SELECT r.*, c.fullname AS customer_name, c.email AS customer_email,
                  t.title AS tour_title, t.tour_code, b.booking_code
           FROM reviews r
           JOIN customers c ON r.customer_id = c.customer_id
           JOIN tours t ON r.tour_id = t.tour_id
           LEFT JOIN bookings b ON r.booking_id = b.booking_id
           WHERE {$where}
           ORDER BY r.review_id DESC";
$stmt = $pdo->prepare($sqlRev);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$page_title = "Kiểm Duyệt Đánh Giá Khách Hàng - Du Lịch Việt Nam";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Kiểm Duyệt Đánh Giá Của Du Khách</h1>
                <p class="text-xs text-slate-500 mt-1">Lắng nghe phản hồi dịch vụ, phê duyệt xuất bản đánh giá chân thực từ khách hàng</p>
            </div>
            
            <!-- Bộ lọc trạng thái bình luận -->
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>modules/reviews/review_manager.php" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= empty($status_filter) ? 'bg-blue-600 text-white' : 'bg-white border text-slate-600 hover:bg-slate-50' ?>">Tất cả</a>
                <a href="<?= BASE_URL ?>modules/reviews/review_manager.php?status=Chờ duyệt" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $status_filter === 'Chờ duyệt' ? 'bg-amber-600 text-white' : 'bg-white border text-slate-600 hover:bg-slate-50' ?>">Chờ duyệt</a>
                <a href="<?= BASE_URL ?>modules/reviews/review_manager.php?status=Đã duyệt" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $status_filter === 'Đã duyệt' ? 'bg-emerald-600 text-white' : 'bg-white border text-slate-600 hover:bg-slate-50' ?>">Đã duyệt</a>
            </div>
        </div>

        <?php if (!empty($msg_success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2 font-bold">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i> <?= $msg_success ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wide flex items-center gap-2">
                <i data-lucide="message-square" class="w-4 h-4 text-blue-600"></i> Danh Sách Nhận Xét (<?= count($reviews) ?>)
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Khách Hàng</th>
                            <th class="p-3.5">Tour & Mã Booking</th>
                            <th class="p-3.5 text-center">Đánh Giá</th>
                            <th class="p-3.5 max-w-md">Nội Dung Nhận Xét</th>
                            <th class="p-3.5">Thời Gian</th>
                            <th class="p-3.5 text-center">Trạng Thái</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($reviews)): ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400">Không có đánh giá nào phù hợp.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reviews as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5">
                                    <strong class="text-slate-900 block"><?= htmlspecialchars($r['customer_name']) ?></strong>
                                    <span class="text-slate-400 text-[11px]"><?= $r['customer_email'] ?></span>
                                </td>
                                <td class="p-3.5">
                                    <span class="font-medium text-slate-800 block line-clamp-1"><?= htmlspecialchars($r['tour_title']) ?></span>
                                    <span class="text-blue-600 font-mono text-[11px]">#<?= $r['booking_code'] ?: 'N/A' ?></span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="text-amber-500 font-bold text-sm"><?= str_repeat('★', $r['rating']) ?></span>
                                </td>
                                <td class="p-3.5 max-w-md">
                                    <p class="text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                                </td>
                                <td class="p-3.5 text-slate-400 text-[11px] whitespace-nowrap">
                                    <?= date('d/m/Y H:i', strtotime($r['review_date'])) ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= $r['status'] === 'Đã duyệt' ? 'bg-emerald-100 text-emerald-800' : ($r['status'] === 'Chờ duyệt' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600') ?>">
                                        <?= $r['status'] ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center space-x-1 whitespace-nowrap">
                                    <?php if ($r['status'] !== 'Đã duyệt'): ?>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded text-[11px] font-bold transition">Duyệt</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                            <input type="hidden" name="action" value="hide">
                                            <button type="submit" class="bg-amber-100 hover:bg-amber-200 text-amber-800 px-2.5 py-1 rounded text-[11px] font-bold transition">Ẩn</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" class="inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhận xét này?')">
                                        <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-rose-600 hover:text-rose-800 px-1 py-1 font-bold transition" title="Xóa">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>lucide.createIcons();</script>
</body>
</html>