<?php
// modules/accounting/export_excel.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_roles(['Administrator', 'Accountant']);

// Thiết lập header ép trình duyệt tải file Excel (.xls)
$filename = "Bao_Cao_Doanh_Thu_VAT_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Xuất BOM UTF-8 để Excel hiển thị đúng dấu tiếng Việt
echo "\xEF\xBB\xBF";

// Lấy danh sách hóa đơn
$sql = "SELECT i.*, b.booking_code, t.title AS tour_title
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.booking_id
        JOIN tour_departures dep ON b.departure_id = dep.departure_id
        JOIN tours t ON dep.tour_id = t.tour_id
        ORDER BY i.invoice_id DESC";
$invoices = $pdo->query($sql)->fetchAll();
?>
<table border="1">
    <thead>
        <tr style="background-color: #0284c7; color: #ffffff; font-weight: bold; height: 30px;">
            <th colspan="9" style="font-size: 16px; text-align: center;">CÔNG TY DU LỊCH VIỆT NAM - BÁO CÁO DOANH THU & HÓA ĐƠN VAT</th>
        </tr>
        <tr style="background-color: #f1f5f9; font-weight: bold;">
            <th>STT</th>
            <th>Số Hóa Đơn</th>
            <th>Mã Booking</th>
            <th>Chương Trình Tour</th>
            <th>Người Mua / Đơn Vị</th>
            <th>Mã Số Thuế</th>
            <th>Tiền Trước Thuế (VNĐ)</th>
            <th>Thuế VAT 8% (VNĐ)</th>
            <th>Tổng Thanh Toán (VNĐ)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $sum_subtotal = 0;
        $sum_vat = 0;
        $sum_total = 0;
        foreach ($invoices as $idx => $inv): 
            $sum_subtotal += $inv['subtotal_amount'];
            $sum_vat += $inv['vat_amount'];
            $sum_total += $inv['total_amount'];
        ?>
            <tr>
                <td style="text-align: center;"><?= $idx + 1 ?></td>
                <td style="text-align: center; font-weight: bold;"><?= $inv['invoice_number'] ?></td>
                <td style="text-align: center;"><?= $inv['booking_code'] ?></td>
                <td><?= htmlspecialchars($inv['tour_title']) ?></td>
                <td><?= htmlspecialchars($inv['company_name'] ?: $inv['buyer_name']) ?></td>
                <td style="text-align: center;"><?= $inv['tax_code'] ?: 'Khách cá nhân' ?></td>
                <td style="text-align: right;"><?= number_format($inv['subtotal_amount'], 0, '', '.') ?></td>
                <td style="text-align: right;"><?= number_format($inv['vat_amount'], 0, '', '.') ?></td>
                <td style="text-align: right; font-weight: bold;"><?= number_format($inv['total_amount'], 0, '', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <tr style="background-color: #fef08a; font-weight: bold;">
            <td colspan="6" style="text-align: center;">TỔNG CỘNG DOANH THU:</td>
            <td style="text-align: right;"><?= number_format($sum_subtotal, 0, '', '.') ?></td>
            <td style="text-align: right;"><?= number_format($sum_vat, 0, '', '.') ?></td>
            <td style="text-align: right;"><?= number_format($sum_total, 0, '', '.') ?></td>
        </tr>
    </tbody>
</table>