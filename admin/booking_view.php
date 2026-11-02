<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();
$pageTitle = 'Chi tiết đơn đặt phòng';

$code = inputString($_GET, 'code');

/* ---------- Cập nhật trạng thái (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrfValid($_POST)) {
            flash('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại');
        } else {
            [$ok, $msg] = adminChangeBookingStatus($code, inputString($_POST, 'status'));
            flash($ok ? 'success' : 'error', $msg);
        }
    } catch (PDOException $ex) {
        flash('error', 'Lỗi cơ sở dữ liệu, không cập nhật được trạng thái');
    }
    header('Location: booking_view.php?code=' . urlencode($code));
    exit;
}

$booking = null;
$room = null;
$dbError = null;
try {
    expireHolds();
    $booking = $code === '' ? null : getBookingByCode($code);
    if ($booking !== null) {
        $room = getRoomById($booking['room_id']);
    }
} catch (PDOException $ex) {
    $dbError = 'Lỗi cơ sở dữ liệu';
}
$statusLabels = bookingStatusLabels();

require __DIR__ . '/../includes/admin_header.php';
?>
<p><a href="bookings.php" id="link-back">&laquo; Quay lại danh sách đơn</a></p>
<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php elseif ($booking === null): ?>
    <div class="error-box" id="booking-error">Không tìm thấy đơn đặt phòng</div>
<?php else: ?>
    <div class="card" id="booking-info">
        <h3>Đơn <?= e($booking['code']) ?></h3>
        <table class="info">
            <tr><th>Mã đặt phòng</th><td id="bk-code"><?= e($booking['code']) ?></td></tr>
            <tr><th>Trạng thái</th><td id="bk-status"><span class="badge badge-<?= e($booking['status']) ?>"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span></td></tr>
            <tr><th>Khách hàng</th><td id="bk-name"><?= e($booking['full_name']) ?></td></tr>
            <tr><th>Số điện thoại</th><td id="bk-phone"><?= e($booking['phone']) ?></td></tr>
            <tr><th>Email</th><td id="bk-email"><?= e($booking['email']) ?></td></tr>
            <tr><th>Quốc gia</th><td id="bk-country"><?= e($booking['country']) ?></td></tr>
            <tr><th>Yêu cầu đặc biệt</th><td id="bk-request"><?= e($booking['special_request'] ?? '') ?></td></tr>
            <tr><th>Giờ nhận phòng dự kiến</th><td id="bk-arrival"><?= e($booking['arrival_time'] ?? '') ?></td></tr>
            <tr><th>Phòng</th><td id="bk-room">
                <?= e($booking['room_id']) ?>
                <?php if ($room !== null): ?> - <?= e($room['hotel_name']) ?> - <?= e($room['room_name']) ?><?php endif; ?>
            </td></tr>
            <tr><th>Ngày nhận - trả phòng</th><td id="bk-dates"><?= e($booking['check_in']) ?> &rarr; <?= e($booking['check_out']) ?></td></tr>
            <tr><th>Số đêm</th><td id="bk-nights"><?= e(calculateNights($booking['check_in'], $booking['check_out'])) ?></td></tr>
            <tr><th>Số phòng</th><td id="bk-rooms"><?= e($booking['quantity']) ?></td></tr>
            <tr><th>Số người</th><td id="bk-guests"><?= e($booking['guests']) ?></td></tr>
            <tr><th>Tổng tiền</th><td id="bk-total"><?= e(formatPrice($booking['total_amount'])) ?></td></tr>
            <tr><th>Ngày tạo</th><td id="bk-created"><?= e($booking['created_at']) ?></td></tr>
            <tr><th>Giữ chỗ đến</th><td id="bk-hold"><?= e($booking['hold_expires_at']) ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h3>Cập nhật trạng thái</h3>
        <form method="post" action="booking_view.php?code=<?= e(urlencode($booking['code'])) ?>" id="status-form" novalidate>
            <?= csrfField() ?>
            <div class="form-row">
                <label for="status">Trạng thái mới</label>
                <select id="status" name="status">
                    <?php foreach ($statusLabels as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $booking['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <button type="submit" id="btn-update-status">Cập nhật trạng thái</button>
            </div>
        </form>
        <p class="hint">Chuyển hợp lệ: Chờ thanh toán &rarr; Đã xác nhận / Đã hủy; Đã xác nhận &rarr; Đã hoàn thành (từ ngày nhận phòng) / Đã hủy.
            Đơn Đã hoàn thành, Đã hủy, Hết hạn giữ chỗ không đổi được nữa.</p>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>