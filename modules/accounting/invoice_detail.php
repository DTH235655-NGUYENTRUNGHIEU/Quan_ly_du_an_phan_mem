<?php
// modules/accounting/invoice_detail.php
require_once '../../config/database.php';
$invoice_id = intval($_GET['id'] ?? 1);

// Lấy thông tin hóa đơn điện tử VAT
$stmt = $pdo->prepare("SELECT i.*, b.booking_code 
                       FROM invoices i 
                       JOIN bookings b ON i.booking_id = b.booking_id 
                       WHERE i.invoice_id = ?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch();

if (!$inv) die("Không tìm thấy hóa đơn này!");

// Lấy chi tiết các dòng mục dịch vụ (invoice_details)
$stmtDet = $pdo->prepare("SELECT * FROM invoice_details WHERE invoice_id = ?");
$stmtDet->execute([$invoice_id]);
$details = $stmtDet->fetchAll();

$page_title = "Hóa Đơn Điện Tử " . $inv['invoice_number'];
include '../../includes/header.php';
?>

<div class="max-w-3xl mx-auto my-10 p-8 bg-white border border-slate-300 rounded-2xl shadow-2xl space-y-6">
    <!-- Header Hóa Đơn -->
    <div class="flex justify-between items-start border-b-2 border-rose-500 pb-4">
        <div>
            <h1 class="text-xl font-black text-rose-600">CÔNG TY DU LỊCH VIỆT NAM</h1>
            <p class="text-xs text-slate-500">Mã số thuế: <strong>1600173428</strong></p>
            <p class="text-xs text-slate-500">Địa chỉ: Ung Văn Khiêm, P. Đông Xuyên, TP. Long Xuyên, An Giang</p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-bold text-slate-800">HÓA ĐƠN ĐIỆN TỬ (VAT)</h2>
            <p class="text-xs text-slate-400">Ký hiệu: <strong><?= $inv['invoice_series'] ?></strong></p>
            <p class="text-sm font-black text-rose-600"><?= $inv['invoice_number'] ?></p>
            <p class="text-[11px] text-slate-400">Ngày lập: <?= date('d/m/Y H:i', strtotime($inv['issue_date'])) ?></p>
        </div>
    </div>

    <!-- Thông tin khách hàng & Mã đơn -->
    <div class="text-xs space-y-1 bg-slate-50 p-4 rounded-xl border border-slate-100">
        <div>Đơn vị mua hàng: <strong><?= htmlspecialchars($inv['company_name']) ?></strong></div>
        <div>Mã số thuế cơ quan: <strong><?= $inv['tax_code'] ?: 'Khách hàng cá nhân' ?></strong></div>
        <div>Người đại diện: <strong><?= htmlspecialchars($inv['buyer_name']) ?></strong> | Email: <?= $inv['buyer_email'] ?></div>
        <div>Mã Booking liên kết: <span class="font-mono font-bold text-blue-600"><?= $inv['booking_code'] ?></span> | Phương thức: <strong><?= $inv['payment_method'] ?></strong></div>
    </div>

    <!-- Bảng chi tiết hóa đơn (invoice_details) -->
    <table class="w-full text-xs border border-slate-200 text-center">
        <thead class="bg-slate-100 font-bold text-slate-700">
            <tr>
                <th class="p-2 border">STT</th>
                <th class="p-2 border text-left">Nội dung dịch vụ tour</th>
                <th class="p-2 border">ĐVT</th>
                <th class="p-2 border">Số lượng</th>
                <th class="p-2 border text-right">Đơn giá</th>
                <th class="p-2 border text-right">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $idx => $d): ?>
                <tr>
                    <td class="p-2 border"><?= $idx + 1 ?></td>
                    <td class="p-2 border text-left font-semibold"><?= htmlspecialchars($d['item_name']) ?></td>
                    <td class="p-2 border"><?= $d['unit'] ?></td>
                    <td class="p-2 border font-bold"><?= $d['quantity'] ?></td>
                    <td class="p-2 border text-right"><?= number_format($d['unit_price'], 0, ',', '.') ?> đ</td>
                    <td class="p-2 border text-right font-bold"><?= number_format($d['amount'], 0, ',', '.') ?> đ</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tổng tiền & Thuế VAT 8% -->
    <div class="flex justify-end text-xs">
        <div class="w-72 space-y-1.5 border-t border-slate-200 pt-2">
            <div class="flex justify-between text-slate-600">
                <span>Cộng tiền dịch vụ:</span>
                <strong><?= number_format($inv['subtotal_amount'], 0, ',', '.') ?> đ</strong>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Thuế GTGT (VAT <?= $inv['vat_rate'] ?>%):</span>
                <strong><?= number_format($inv['vat_amount'], 0, ',', '.') ?> đ</strong>
            </div>
            <div class="flex justify-between text-sm font-extrabold text-rose-600 border-t pt-1">
                <span>TỔNG THANH TOÁN:</span>
                <span><?= number_format($inv['total_amount'], 0, ',', '.') ?> đ</span>
            </div>
        </div>
    </div>

    <!-- Chữ ký số -->
    <div class="flex justify-between text-center text-xs pt-8">
        <div>
            <p class="font-bold">NGƯỜI MUA HÀNG</p>
            <p class="text-[10px] text-slate-400 italic">(Ký, ghi rõ họ tên)</p>
        </div>
        <div>
            <p class="font-bold">ĐƠN VỊ BÁN HÀNG</p>
            <p class="text-[10px] text-emerald-600 font-bold mt-2">✓ ĐÃ KÝ ĐIỆN TỬ BỞI CÔNG TY DU LỊCH VIỆT NAM</p>
        </div>
    </div>

    <div class="text-center pt-4 border-t">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-xl text-xs shadow">
            In Hóa Đơn VAT Này
        </button>
    </div>
</div>
</body>
</html>