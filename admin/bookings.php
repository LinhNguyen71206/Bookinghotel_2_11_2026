<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();
$pageTitle = 'Quản lý đơn đặt phòng';

$filter = [];
foreach (['keyword', 'status', 'room_id', 'date_from', 'date_to'] as $k) {
    $filter[$k] = inputString($_GET, $k);
}
$errors = validateBookingFilter($filter);

$bookings = [];
$rooms = [];
$dbError = null;
try {
    expireHolds();
    $rooms = getAllRooms();
    if (!$errors) {
        $bookings = adminSearchBookings($filter);
    }
} catch (PDOException $ex) {
    $dbError = 'Lỗi cơ sở dữ liệu. Hãy chắc chắn đã chạy database/migration_admin.sql.';
}
$statusLabels = bookingStatusLabels();

require __DIR__ . '/../includes/admin_header.php';
?>
<h2>Quản lý đơn đặt phòng</h2>
<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php endif; ?>

<form method="get" action="bookings.php" id="booking-filter-form" class="filter-form" novalidate>
    <div class="form-row">
        <label for="keyword">Từ khóa (mã đơn, khách, SĐT, email, phòng)</label>
        <input type="text" id="keyword" name="keyword" value="<?= e($filter['keyword']) ?>">
        <?php fieldError($errors, 'keyword'); ?>
    </div>
    <div class="form-row">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="">-- Tất cả --</option>
            <?php foreach ($statusLabels as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $filter['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php fieldError($errors, 'status'); ?>
    </div>
    <div class="form-row">
        <label for="room_id">Phòng</label>
        <select id="room_id" name="room_id">
            <option value="">-- Tất cả --</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= e($r['id']) ?>"<?= $filter['room_id'] === $r['id'] ? ' selected' : '' ?>><?= e($r['id'] . ' - ' . $r['hotel_name'] . ' - ' . $r['room_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php fieldError($errors, 'room_id'); ?>
    </div>
    <div class="form-row">
        <label for="date_from">Nhận phòng từ ngày</label>
        <input type="text" id="date_from" name="date_from" value="<?= e($filter['date_from']) ?>" placeholder="YYYY-MM-DD">
        <?php fieldError($errors, 'date_from'); ?>
    </div>
    <div class="form-row">
        <label for="date_to">Nhận phòng đến ngày</label>
        <input type="text" id="date_to" name="date_to" value="<?= e($filter['date_to']) ?>" placeholder="YYYY-MM-DD">
        <?php fieldError($errors, 'date_to'); ?>
    </div>
    <div class="form-row">
        <button type="submit" id="btn-filter">Tìm kiếm</button>
        <a class="btn" href="bookings.php" id="btn-reset">Xóa bộ lọc</a>
    </div>
</form>

<?php if ($errors): ?>
    <div class="error-box" id="validation-summary">Vui lòng kiểm tra lại các trường bị lỗi ở trên.</div>
<?php elseif ($dbError === null): ?>
    <?php if (count($bookings) === 0): ?>
        <div class="info-box" id="no-result">Không tìm thấy đơn đặt phòng phù hợp</div>
    <?php else: ?>
        <p id="result-count">Tìm thấy <?= count($bookings) ?> đơn</p>
        <table id="booking-table">
            <tr>
                <th>Mã đơn</th><th>Khách hàng</th><th>SĐT</th><th>Phòng</th><th>Nhận - trả phòng</th>
                <th>Số phòng</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th></th>
            </tr>
            <?php foreach ($bookings as $b): ?>
                <tr class="booking-row">
                    <td class="col-code"><?= e($b['code']) ?></td>
                    <td class="col-name"><?= e($b['full_name']) ?></td>
                    <td class="col-phone"><?= e($b['phone']) ?></td>
                    <td class="col-room"><?= e($b['room_id']) ?> - <?= e($b['hotel_name'] ?? '?') ?> - <?= e($b['room_name'] ?? '?') ?></td>
                    <td class="col-dates"><?= e($b['check_in']) ?> &rarr; <?= e($b['check_out']) ?></td>
                    <td class="col-quantity"><?= e($b['quantity']) ?></td>
                    <td class="col-total"><?= e(formatPrice($b['total_amount'])) ?></td>
                    <td class="col-status"><span class="badge badge-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
                    <td class="col-created"><?= e($b['created_at']) ?></td>
                    <td><a class="btn btn-view" href="booking_view.php?code=<?= e(urlencode($b['code'])) ?>">Chi tiết</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>