<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();
$pageTitle = 'Chi tiết phòng';

$id = inputString($_GET, 'id');
$room = null;
$error = null;
$bookingCount = 0;
try {
    if (!preg_match(ROOM_ID_PATTERN, $id)) {
        $error = 'Mã phòng không hợp lệ';
    } else {
        $room = getRoomById($id);
        if ($room === null) {
            $error = 'Không tìm thấy phòng có mã ' . $id;
        } else {
            $bookingCount = adminCountBookingsOfRoom($id);
        }
    }
} catch (PDOException $ex) {
    $error = 'Lỗi cơ sở dữ liệu';
}
$statusLabels = roomStatusLabels();
require __DIR__ . '/../includes/admin_header.php';
?>
<p><a href="rooms.php" id="link-back">&laquo; Quay lại danh sách phòng</a></p>
<?php if ($error !== null): ?>
    <div class="error-box" id="room-error"><?= e($error) ?></div>
<?php else: $st = $room['status'] ?? 'active'; ?>
    <div class="card" id="room-info">
        <div class="room-image" style="background: <?= e($room['image_color']) ?>">Ảnh phòng</div>
        <h3 id="room-name"><?= e($room['room_name']) ?></h3>
        <table class="info">
            <tr><th>Mã phòng</th><td id="room-id"><?= e($room['id']) ?></td></tr>
            <tr><th>Trạng thái</th><td id="room-status"><span class="badge badge-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span></td></tr>
            <tr><th>Khách sạn</th><td id="hotel-name"><?= e($room['hotel_name']) ?></td></tr>
            <tr><th>Điểm đến</th><td id="destination"><?= e($room['destination']) ?></td></tr>
            <tr><th>Địa chỉ</th><td id="address"><?= e($room['address']) ?></td></tr>
            <tr><th>Đánh giá</th><td id="rating"><?= e($room['rating']) ?></td></tr>
            <tr><th>Số khách tối đa / phòng</th><td id="max-guests"><?= e($room['max_guests']) ?></td></tr>
            <tr><th>Tổng số phòng</th><td id="total-rooms"><?= e($room['total_rooms']) ?></td></tr>
            <tr><th>Giá / đêm</th><td id="base-price"><?= e(formatPrice($room['price'])) ?></td></tr>
            <tr><th>Mô tả</th><td id="description"><?= e($room['description']) ?></td></tr>
            <tr><th>Tiện nghi</th><td><ul id="amenities">
                <?php foreach ($room['amenities'] as $a): ?><li><?= e($a) ?></li><?php endforeach; ?>
            </ul></td></tr>
            <tr><th>Chính sách hủy</th><td id="cancellation-policy"><?= e($room['cancellation_policy']) ?></td></tr>
            <tr><th>Bữa ăn</th><td id="meal-policy"><?= e($room['meal_policy']) ?></td></tr>
            <tr><th>Số đơn đặt phòng</th><td id="booking-count"><a href="bookings.php?room_id=<?= e(urlencode($room['id'])) ?>"><?= e($bookingCount) ?></a></td></tr>
        </table>
        <p><a class="btn" id="btn-edit" href="room_form.php?id=<?= e(urlencode($room['id'])) ?>">Sửa phòng</a></p>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>