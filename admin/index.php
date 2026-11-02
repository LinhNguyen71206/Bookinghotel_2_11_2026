<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();
$pageTitle = 'Tổng quan';

$roomCounts = [];
$bookingCounts = [];
$dbError = null;
try {
    expireHolds();
    $roomCounts    = getPDO()->query('SELECT status, COUNT(*) FROM rooms GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $bookingCounts = getPDO()->query('SELECT status, COUNT(*) FROM bookings GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $ex) {
    $dbError = 'Lỗi cơ sở dữ liệu. Hãy chắc chắn đã chạy database/migration_admin.sql.';
}
require __DIR__ . '/../includes/admin_header.php';
?>
<h2>Tổng quan</h2>
<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php else: ?>
    <div class="card" id="room-stats">
        <h3>Phòng (<a href="rooms.php">quản lý</a>)</h3>
        <table class="info">
            <?php foreach (roomStatusLabels() as $k => $label): ?>
                <tr><th><?= e($label) ?></th><td id="room-count-<?= e($k) ?>"><?= e($roomCounts[$k] ?? 0) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="card" id="booking-stats">
        <h3>Đơn đặt phòng (<a href="bookings.php">quản lý</a>)</h3>
        <table class="info">
            <?php foreach (bookingStatusLabels() as $k => $label): ?>
                <tr><th><?= e($label) ?></th><td id="booking-count-<?= e($k) ?>"><?= e($bookingCounts[$k] ?? 0) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>