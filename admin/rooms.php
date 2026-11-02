<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();
$pageTitle = 'Quản lý phòng';

/* ---------- Xử lý thao tác (POST) rồi chuyển hướng ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrfValid($_POST)) {
            flash('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại');
        } else {
            $action = inputString($_POST, 'action');
            $roomId = inputString($_POST, 'room_id');
            if (!preg_match(ROOM_ID_PATTERN, $roomId) || getRoomById($roomId) === null) {
                flash('error', 'Không tìm thấy phòng cần thao tác');
            } elseif ($action === 'delete') {
                $r = adminDeleteRoom($roomId);
                if ($r === 'ok') {
                    flash('success', "Đã xóa phòng $roomId");
                } elseif ($r === 'has_bookings') {
                    flash('error', "Không thể xóa phòng $roomId vì đã có đơn đặt phòng. Hãy chuyển sang trạng thái \"Ngừng kinh doanh\"");
                } else {
                    flash('error', 'Không tìm thấy phòng cần thao tác');
                }
            } elseif ($action === 'set_status') {
                $status = inputString($_POST, 'status');
                if (!array_key_exists($status, roomStatusLabels())) {
                    flash('error', 'Trạng thái phòng không hợp lệ');
                } else {
                    adminSetRoomStatus($roomId, $status);
                    flash('success', "Đã cập nhật phòng $roomId: " . roomStatusLabels()[$status]);
                }
            } else {
                flash('error', 'Thao tác không hợp lệ');
            }
        }
    } catch (PDOException $ex) {
        flash('error', 'Lỗi cơ sở dữ liệu, không thực hiện được thao tác');
    }
    header('Location: rooms.php');
    exit;
}

/* ---------- Danh sách + tìm kiếm ---------- */
$filter = ['keyword' => inputString($_GET, 'keyword'),
           'status' => inputString($_GET, 'status'),
           'destination' => inputString($_GET, 'destination')];
$errors = [];
if (mb_strlen($filter['keyword']) > LIMIT_KEYWORD_MAX) {
    $errors['keyword'] = 'Từ khóa tối đa ' . LIMIT_KEYWORD_MAX . ' ký tự';
}
if ($filter['status'] !== '' && !array_key_exists($filter['status'], roomStatusLabels())) {
    $errors['status'] = 'Trạng thái không hợp lệ';
}

$rooms = [];
$destinations = [];
$dbError = null;
try {
    $destinations = getAllDestinations();
    if (!$errors) {
        $rooms = adminSearchRooms($filter['keyword'], $filter['status'], $filter['destination']);
    }
} catch (PDOException $ex) {
    $dbError = 'Lỗi cơ sở dữ liệu. Hãy chắc chắn đã chạy database/migration_admin.sql.';
}
$statusLabels = roomStatusLabels();

require __DIR__ . '/../includes/admin_header.php';
?>
<h2>Quản lý phòng</h2>
<p><a class="btn" id="btn-add-room" href="room_form.php">+ Thêm phòng</a></p>

<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php endif; ?>

<form method="get" action="rooms.php" id="room-filter-form" class="filter-form" novalidate>
    <div class="form-row">
        <label for="keyword">Từ khóa (mã, khách sạn, loại phòng, điểm đến)</label>
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
        <label for="destination">Điểm đến</label>
        <select id="destination" name="destination">
            <option value="">-- Tất cả --</option>
            <?php foreach ($destinations as $d): ?>
                <option value="<?= e($d) ?>"<?= $filter['destination'] === $d ? ' selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row">
        <button type="submit" id="btn-filter">Tìm kiếm</button>
        <a class="btn" href="rooms.php" id="btn-reset">Xóa bộ lọc</a>
    </div>
</form>

<?php if ($dbError === null && !$errors): ?>
    <?php if (count($rooms) === 0): ?>
        <div class="info-box" id="no-result">Không tìm thấy phòng phù hợp</div>
    <?php else: ?>
        <p id="result-count">Tìm thấy <?= count($rooms) ?> phòng</p>
        <table id="room-table">
            <tr>
                <th>Mã</th><th>Khách sạn</th><th>Loại phòng</th><th>Điểm đến</th>
                <th>Giá / đêm</th><th>Số phòng</th><th>Đánh giá</th><th>Trạng thái</th><th>Thao tác</th>
            </tr>
            <?php foreach ($rooms as $r): $st = $r['status'] ?? 'active'; ?>
                <tr class="room-row">
                    <td class="col-id"><?= e($r['id']) ?></td>
                    <td class="col-hotel"><?= e($r['hotel_name']) ?></td>
                    <td class="col-room"><?= e($r['room_name']) ?></td>
                    <td class="col-destination"><?= e($r['destination']) ?></td>
                    <td class="col-price"><?= e(formatPrice($r['price'])) ?></td>
                    <td class="col-total"><?= e($r['total_rooms']) ?></td>
                    <td class="col-rating"><?= e($r['rating']) ?></td>
                    <td class="col-status"><span class="badge badge-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-view" href="room_view.php?id=<?= e(urlencode($r['id'])) ?>">Xem</a>
                        <a class="btn btn-edit" href="room_form.php?id=<?= e(urlencode($r['id'])) ?>">Sửa</a>
                        <form method="post" action="rooms.php" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="room_id" value="<?= e($r['id']) ?>">
                            <select name="status" class="sel-status">
                                <?php foreach ($statusLabels as $k => $label): ?>
                                    <option value="<?= e($k) ?>"<?= $st === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-set-status">Cập nhật</button>
                        </form>
                        <form method="post" action="rooms.php" class="inline-form"
                              onsubmit="return confirm('Bạn có chắc muốn xóa phòng <?= e($r['id']) ?>?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="room_id" value="<?= e($r['id']) ?>">
                            <button type="submit" class="btn-delete">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>