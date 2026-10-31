<?php
require_once __DIR__ . '/../../includes/auth.php';
// Chỉ Administrator, Sales Staff và Accountant được vào quản lý đơn tour
require_roles(['Administrator', 'Sales Staff', 'Accountant']);