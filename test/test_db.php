<?php
/* ============================================================
 * tests/test_db.php - Kiểm tra nhanh Phần 1 (CSDL + db.php)
 * Mở: http://localhost/hotel-booking/tests/test_db.php
 * Lưu ý: file này XÓA toàn bộ đơn đặt phòng để có dữ liệu sạch.
 * ============================================================ */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

function check(string $name, $actual, $expected): void
{
    $ok = ($actual === $expected);
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name;
    if (!$ok) {
        echo ' | thực tế: ' . json_encode($actual, JSON_UNESCAPED_UNICODE)
           . ' | mong đợi: ' . json_encode($expected, JSON_UNESCAPED_UNICODE);
    }
    echo "\n";
}

$now = '2026-09-05 10:00:00';
resetBookings();

check('Có 8 phòng', count(getAllRooms()), 8);
check('Lấy phòng R001', getRoomById('R001')['room_name'], 'Phòng Deluxe hướng hồ');
check('ID không tồn tại -> null', getRoomById('XXX'), null);
check('Có 4 điểm đến', count(getAllDestinations()), 4);

check('R008 còn 1 phòng khi chưa có đơn', getAvailableQuantity('R008', '2026-09-10', '2026-09-12', $now), 1);

$code = addBooking([
    'room_id' => 'R008', 'check_in' => '2026-09-10', 'check_out' => '2026-09-12',
    'guests' => 2, 'quantity' => 1, 'full_name' => 'Nguyen Van A', 'phone' => '0912345678',
    'country' => 'Việt Nam', 'email' => 'a@example.com', 'total_amount' => 1900000,
], $now);

check('Mã đơn đúng định dạng', preg_match('/^BK260905-[A-Z2-9]{5}$/', $code) === 1, true);
check('Đơn mới ở trạng thái chờ thanh toán', getBookingByCode($code)['status'], 'pending_payment');
check('R008 hết phòng khi có đơn giữ chỗ', getAvailableQuantity('R008', '2026-09-10', '2026-09-12', $now), 0);
check('Ngày nhận = ngày trả của đơn khác vẫn còn phòng', getAvailableQuantity('R008', '2026-09-12', '2026-09-13', $now), 1);
check('Hết hạn giữ chỗ thì phòng trống lại', getAvailableQuantity('R008', '2026-09-10', '2026-09-12', '2026-09-05 10:16:00'), 1);
check('expireHolds hủy 1 đơn', expireHolds('2026-09-05 10:16:00'), 1);
check('Đơn chuyển sang expired', getBookingByCode($code)['status'], 'expired');
check('Phòng không tồn tại -> 0', getAvailableQuantity('ZZ', '2026-09-10', '2026-09-12', $now), 0);

$after = updateBooking($code, ['status' => 'confirmed']);
check('updateBooking đổi trạng thái', $after['status'], 'confirmed');
check('Đơn đã xác nhận chiếm phòng', getAvailableQuantity('R008', '2026-09-10', '2026-09-12', $now), 0);

resetBookings();
echo "\nXong. Đã xóa dữ liệu đơn đặt phòng thử nghiệm.\n";