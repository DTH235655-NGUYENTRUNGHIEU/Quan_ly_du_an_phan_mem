<?php
// modules/accounting/invoices_list.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Chỉ Administrator và Kế toán viên (Accountant) được thao tác
require_roles(['Administrator', 'Accountant']);

$currentUser = current_user();
$msg_success = "";
$msg_error = "";

// 2. Xử lý nghiệp vụ Tất toán & Tự động Lập Hóa đơn VAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'settle_and_create_invoice') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $company_name = trim($_POST['company_name'] ?? '');
    $tax_code = trim($_POST['tax_code'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'Chuyển khoản';

    try {
        $pdo->beginTransaction();

        // 2.1. Lấy thông tin đơn đặt tour kèm bảng giá khởi hành
        $sqlB = "SELECT b.*, c.customer_id, c.fullname AS buyer_name, c.email AS buyer_email, c.phone AS buyer_phone,
                        t.title AS tour_title, dep.departure_id, p.price_adult, p.price_child, p.price_infant, p.single_supplement
                 FROM bookings b, customers c, tour_departures dep, tours t, tour_prices p
                 WHERE b.customer_id = c.customer_id 
                   AND b.departure_id = dep.departure_id 
                   AND dep.tour_id = t.tour_id 
                   AND dep.departure_id = p.departure_id 
                   AND b.booking_id = ?";
        $stmtB = $pdo->prepare($sqlB);
        $stmtB->execute([$booking_id]);
        $book = $stmtB->fetch();

        if (!$book) {
            throw new Exception("Không tìm thấy đơn đặt tour yêu cầu.");
        }

        // Kiểm tra xem đơn này đã được xuất hóa đơn chưa
        $stmtCheckInv = $pdo->prepare("SELECT invoice_id FROM invoices WHERE booking_id = ?");
        $stmtCheckInv->execute([$booking_id]);
        if ($stmtCheckInv->fetch()) {
            throw new Exception("Đơn tour này đã được phát hành hóa đơn VAT trước đó!");
        }

        $remaining_amount = $book['total_amount'] - $book['paid_amount'];

        // 2.2. Nếu còn tiền chưa thanh toán, tạo phiếu thu Tất toán (bảng payments)
        if ($remaining_amount > 0) {
            $pay_code = 'PAY' . date('ymd') . rand(100, 999);
            $stmtPay = $pdo->prepare("INSERT INTO payments (booking_id, payment_code, payment_method, payment_phase, amount, transaction_ref, status, processed_by) 
                                      VALUES (?, ?, ?, 'Tất toán', ?, 'TIEN-MAT-HOAC-CK', 'Thành công', ?)");
            $stmtPay->execute([$booking_id, $pay_code, $payment_method, $remaining_amount, $currentUser['user_id']]);

            // Cập nhật bookings sang Đã thanh toán đủ
            $stmtUpBook = $pdo->prepare("UPDATE bookings 
                                         SET paid_amount = total_amount, 
                                             payment_status = 'Đã thanh toán đủ' 
                                         WHERE booking_id = ?");
            $stmtUpBook->execute([$booking_id]);

            // Cộng điểm thưởng tích lũy cho khách hàng (100.000đ = 1 điểm)
            $points_earned = max(10, floor($book['total_amount'] / 100000));
            $pdo->prepare("UPDATE customers SET reward_points = reward_points + ? WHERE customer_id = ?")
                ->execute([$points_earned, $book['customer_id']]);
        }

        // 2.3. Tính toán thuế VAT chuẩn 8.0%
        $total_amount = $book['total_amount'];
        $vat_rate = 8.0;
        $subtotal_amount = round($total_amount / (1 + ($vat_rate / 100)));
        $vat_amount = $total_amount - $subtotal_amount;

        // Sinh số hóa đơn theo thứ tự tăng dần trong năm (HD2026-xxxx)
        $cur_year = date('Y');
        $countInvoices = $pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(issue_date) = {$cur_year}")->fetchColumn();
        $invoice_number = 'HD' . $cur_year . '-' . str_pad($countInvoices + 1, 4, '0', STR_PAD_LEFT);

        // 2.4. Lưu vào bảng invoices
        $stmtInv = $pdo->prepare("INSERT INTO invoices (booking_id, invoice_number, invoice_series, buyer_name, company_name, tax_code, company_address, buyer_email, payment_method, subtotal_amount, vat_rate, vat_amount, total_amount, status, created_by) 
                                  VALUES (?, ?, 'C26TAA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Đã phát hành', ?)");
        $stmtInv->execute([
            $booking_id,
            $invoice_number,
            $book['buyer_name'],
            !empty($company_name) ? $company_name : $book['buyer_name'],
            $tax_code,
            $company_address,
            $book['buyer_email'],
            $payment_method,
            $subtotal_amount,
            $vat_rate,
            $vat_amount,
            $total_amount,
            $currentUser['user_id']
        ]);
        $invoice_id = $pdo->lastInsertId();

        // 2.5. Tách và ghi các dòng dịch vụ chi tiết vào bảng invoice_details
        $stmtDet = $pdo->prepare("INSERT INTO invoice_details (invoice_id, item_type, item_name, unit, quantity, unit_price, discount_amount, amount) 
                                  VALUES (?, ?, ?, 'Khách', ?, ?, 0, ?)");

        // Dòng vé người lớn
        if ($book['num_adults'] > 0) {
            $amt_adult = $book['num_adults'] * $book['price_adult'];
            $stmtDet->execute([$invoice_id, 'Vé người lớn', $book['tour_title'] . ' (Người lớn)', $book['num_adults'], $book['price_adult'], $amt_adult]);
        }

        // Dòng vé trẻ em
        if ($book['num_children'] > 0) {
            $amt_child = $book['num_children'] * $book['price_child'];
            $stmtDet->execute([$invoice_id, 'Vé trẻ em', $book['tour_title'] . ' (Trẻ em 5-11 tuổi)', $book['num_children'], $book['price_child'], $amt_child]);
        }

        // Dòng vé em bé
        if ($book['num_infants'] > 0) {
            $stmtDet->execute([$invoice_id, 'Vé em bé', $book['tour_title'] . ' (Em bé dưới 5 tuổi)', $book['num_infants'], $book['price_infant'], $book['num_infants'] * $book['price_infant']]);
        }

        // Dòng phụ thu phòng đơn (nếu có khách đăng ký ở phòng riêng)
        $singleCount = $pdo->prepare("SELECT COUNT(*) FROM booking_passengers WHERE booking_id = ? AND single_room = 1");
        $singleCount->execute([$booking_id]);
        $singles = $singleCount->fetchColumn();

        if ($singles > 0) {
            $amt_single = $singles * $book['single_supplement'];
            $pdo->prepare("INSERT INTO invoice_details (invoice_id, item_type, item_name, unit, quantity, unit_price, discount_amount, amount) 
                           VALUES (?, 'Phụ thu phòng đơn', 'Phụ thu phòng riêng cho hành khách', 'Phòng', ?, ?, 0, ?)")
                ->execute([$invoice_id, $singles, $book['single_supplement'], $amt_single]);
        }

        $pdo->commit();
        $msg_success = "Đã tất toán và phát hành thành công Hóa đơn điện tử VAT số: <strong>{$invoice_number}</strong>!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg_error = "Lỗi lập hóa đơn: " . $e->getMessage();
    }
}

// 3. Lấy danh sách các đơn đặt tour đã duyệt nhưng chưa lập hóa đơn VAT
$sqlPendingInvoices = "SELECT b.*, c.fullname AS customer_name, c.phone AS customer_phone, t.title AS tour_title
                       FROM bookings b, customers c, tour_departures dep, tours t
                       WHERE b.customer_id = c.customer_id 
                         AND b.departure_id = dep.departure_id 
                         AND dep.tour_id = t.tour_id 
                         AND b.booking_status IN ('Đã xác nhận', 'Đang đi tour', 'Hoàn tất')
                         AND b.booking_id NOT IN (SELECT booking_id FROM invoices WHERE status = 'Đã phát hành')
                       ORDER BY b.booking_id DESC";
$pending_bookings = $pdo->query($sqlPendingInvoices)->fetchAll();

// 4. Lấy danh sách toàn bộ hóa đơn VAT đã phát hành
$sqlInvoices = "SELECT i.*, b.booking_code, u.fullname AS accountant_name
                FROM invoices i
                JOIN bookings b ON i.booking_id = b.booking_id
                LEFT JOIN users u ON i.created_by = u.user_id
                ORDER BY i.invoice_id DESC";
$invoices = $pdo->query($sqlInvoices)->fetchAll();

$page_title = "Quản Lý Hóa Đơn Điện Tử VAT - Du Lịch Việt Nam";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Quản Lý & Phát Hành Hóa Đơn Điện Tử (VAT)</h1>
                <p class="text-xs text-slate-500 mt-1">Nghiệp vụ tất toán đơn tour, tự động bóc tách thuế suất GTGT 8.0% và xuất hóa đơn lưu trữ</p>
            </div>
            <a href="<?= BASE_URL ?>modules/accounting/revenue_report.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition shadow flex items-center gap-1.5">
                <i data-lucide="receipt" class="w-4 h-4"></i> Xem Báo Cáo Doanh Thu
            </a>
        </div>

        <?php if (!empty($msg_success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2 font-bold">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i> <?= $msg_success ?>
            </div>
        <?php elseif (!empty($msg_error)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center gap-2 font-bold">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i> <?= $msg_error ?>
            </div>
        <?php endif; ?>

        <!-- Khu vực 1: Danh sách đơn chờ Tất toán & Xuất Hóa đơn -->
        <?php if (!empty($pending_bookings)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-amber-200 overflow-hidden">
                <div class="p-4 bg-amber-50/70 border-b border-amber-200 flex items-center justify-between">
                    <span class="font-bold text-xs text-amber-900 flex items-center gap-1.5 uppercase tracking-wide">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i> Có <?= count($pending_bookings) ?> Đơn Đã Xác Nhận Chờ Tất Toán & Xuất Hóa Đơn VAT
                    </span>
                </div>
                <div class="p-4 divide-y divide-slate-100">
                    <?php foreach ($pending_bookings as $pb): ?>
                        <?php $must_pay = $pb['total_amount'] - $pb['paid_amount']; ?>
                        <div class="py-3.5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 text-xs">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">#<?= $pb['booking_code'] ?></span>
                                    <strong class="text-slate-800 text-sm"><?= htmlspecialchars($pb['customer_name']) ?> (<?= $pb['customer_phone'] ?>)</strong>
                                </div>
                                <p class="text-slate-500 font-medium"><?= htmlspecialchars($pb['tour_title']) ?></p>
                                <div class="text-slate-500">
                                    Tổng đơn: <strong><?= number_format($pb['total_amount'], 0, ',', '.') ?> đ</strong> | 
                                    Đã cọc: <span class="text-blue-600 font-semibold"><?= number_format($pb['paid_amount'], 0, ',', '.') ?> đ</span> | 
                                    Còn thiếu: <strong class="text-rose-600"><?= number_format($must_pay, 0, ',', '.') ?> đ</strong>
                                </div>
                            </div>

                            <!-- Form nhập thông tin xuất hóa đơn (nếu có xuất cho cơ quan) -->
                            <form method="POST" class="w-full md:w-auto flex flex-col sm:flex-row items-end sm:items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <input type="hidden" name="action" value="settle_and_create_invoice">
                                <input type="hidden" name="booking_id" value="<?= $pb['booking_id'] ?>">
                                
                                <div class="w-full sm:w-44">
                                    <input type="text" name="company_name" placeholder="Tên cơ quan/công ty (nếu có)" class="w-full border rounded-lg p-1.5 text-[11px]">
                                </div>
                                <div class="w-full sm:w-28">
                                    <input type="text" name="tax_code" placeholder="Mã số thuế" class="w-full border rounded-lg p-1.5 text-[11px]">
                                </div>
                                <div class="w-full sm:w-28">
                                    <select name="payment_method" class="w-full border rounded-lg p-1.5 text-[11px]">
                                        <option value="Chuyển khoản">Chuyển khoản</option>
                                        <option value="Tiền mặt">Tiền mặt</option>
                                    </select>
                                </div>
                                <button type="submit" onclick="return confirm('Xác nhận thu đủ số tiền còn lại và phát hành Hóa đơn VAT?')"
                                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition shadow flex items-center justify-center gap-1">
                                    <i data-lucide="file-plus" class="w-3.5 h-3.5"></i> Tất toán & Xuất Hóa Đơn
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Khu vực 2: Danh sách Hóa đơn VAT đã phát hành -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wide flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i> Danh Sách Hóa Đơn Điện Tử Đã Phát Hành
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Số Hóa Đơn</th>
                            <th class="p-3.5">Mã Booking</th>
                            <th class="p-3.5">Người Mua / Đơn Vị</th>
                            <th class="p-3.5">Ngày Lập</th>
                            <th class="p-3.5 text-right">Tiền Trước Thuế</th>
                            <th class="p-3.5 text-right">VAT (8%)</th>
                            <th class="p-3.5 text-right">Tổng Thanh Toán</th>
                            <th class="p-3.5 text-center">Người Xuất</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="9" class="p-6 text-center text-slate-400">Chưa có hóa đơn nào được phát hành.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5 font-bold font-mono text-blue-600"><?= $inv['invoice_number'] ?></td>
                                <td class="p-3.5 font-mono text-slate-500">#<?= $inv['booking_code'] ?></td>
                                <td class="p-3.5">
                                    <strong class="text-slate-800 block"><?= htmlspecialchars($inv['company_name'] ?: $inv['buyer_name']) ?></strong>
                                    <span class="text-slate-400 text-[11px]"><?= $inv['tax_code'] ? 'MST: ' . $inv['tax_code'] : 'Khách cá nhân' ?></span>
                                </td>
                                <td class="p-3.5 text-slate-500"><?= date('d/m/Y H:i', strtotime($inv['issue_date'])) ?></td>
                                <td class="p-3.5 text-right font-medium"><?= number_format($inv['subtotal_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-right text-rose-600 font-medium"><?= number_format($inv['vat_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-right font-bold text-emerald-600"><?= number_format($inv['total_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-center text-slate-500"><?= htmlspecialchars($inv['accountant_name'] ?? 'Admin') ?></td>
                                <td class="p-3.5 text-center">
                                    <a href="<?= BASE_URL ?>modules/accounting/invoice_detail.php?id=<?= $inv['invoice_id'] ?>" target="_blank"
                                       class="inline-block bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-2.5 py-1 rounded text-[11px] font-bold transition">
                                        Xem & In VAT
                                    </a>
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