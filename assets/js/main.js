document.addEventListener('DOMContentLoaded', function () {
    // 1. Tự động ẩn các thông báo alert sau 5 giây
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alertEl) {
        setTimeout(function () {
            alertEl.style.transition = 'opacity 0.4s ease';
            alertEl.style.opacity = '0';
            setTimeout(() => alertEl.remove(), 400);
        }, 5000);
    });

    // 2. Xác nhận trước khi thực hiện thao tác xóa hoặc hủy
    const confirmLinks = document.querySelectorAll('[data-confirm]');
    confirmLinks.forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});

// Hàm định dạng tiền tệ VNĐ dùng chung
function formatCurrencyVND(amount) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(amount || 0)) + ' đ';
}