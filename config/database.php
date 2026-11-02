<?php
/* ============================================================
 * config/database.php - Cấu hình kết nối CSDL (mặc định của XAMPP)
 * Nếu bạn đặt mật khẩu cho MySQL, sửa DB_PASS bên dưới.
 * ============================================================ */

date_default_timezone_set('Asia/Ho_Chi_Minh');

const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'hotel_booking';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// Thời gian giữ chỗ (phút) trước khi đơn chưa thanh toán bị hủy
const HOLD_MINUTES = 15;