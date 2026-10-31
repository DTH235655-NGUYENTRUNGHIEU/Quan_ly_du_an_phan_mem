<?php
// includes/auth.php - Middleware xác thực và phân quyền
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra xem người dùng đã đăng nhập hay chưa
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

/**
 * Lấy thông tin tài khoản hiện tại trong session
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Kiểm tra người dùng có một trong các vai trò chỉ định không
 * @param array|string $roles Tên vai trò (VD: 'Administrator' hoặc ['Sales Staff', 'Accountant'])
 */
function has_role($roles): bool {
    if (!is_logged_in()) return false;
    $userRole = $_SESSION['user']['role_name'] ?? '';
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    return $userRole === $roles;
}

/**
 * Bắt buộc phải đăng nhập mới được truy cập
 */
function require_login(string $redirect_url = '/dulichvietnam/modules/auth/login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Vui lòng đăng nhập để tiếp tục!";
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Bắt buộc người dùng phải có đúng vai trò được phép truy cập
 */
function require_roles(array $allowed_roles): void {
    require_login();
    if (!has_role($allowed_roles)) {
        http_response_code(403);
        die("
            <div style='font-family: sans-serif; text-align: center; padding: 50px;'>
                <h1 style='color: #e11d48; font-size: 28px;'>403 - TRUY CẬP BỊ TỪ CHỐI</h1>
                <p style='color: #475569;'>Bạn không có quyền hạn (" . htmlspecialchars($_SESSION['user']['role_name']) . ") để sử dụng chức năng này.</p>
                <a href='/dulichvietnam/index.php' style='display: inline-block; margin-top: 15px; padding: 10px 20px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;'>Về Trang Chủ</a>
            </div>
        ");
    }
}