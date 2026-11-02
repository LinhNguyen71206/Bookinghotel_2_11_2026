<?php
/* ============================================================
 * index.php - Chức năng 1: TÌM KIẾM PHÒNG
 * Form gửi bằng GET. Cố ý KHÔNG dùng thuộc tính kiểm tra của HTML5
 * (required, type=number, type=date...) để mọi lỗi nhập liệu đều do
 * server kiểm tra và báo lỗi, thuận tiện cho việc kiểm thử.
 * ============================================================ */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/search.php';

$pageTitle = 'Tìm kiếm phòng';

// Giá trị người dùng nhập (để giữ lại trên form sau khi gửi)
$fields = ['destination', 'check_in', 'check_out', 'guests', 'rooms', 'min_price', 'max_price'];
$in = [];
foreach ($fields as $f) {
    $in[$f] = inputString($_GET, $f);
}

$searched = isset($_GET['search']);
$errors   = [];
$results  = null;   // null = chưa tìm; [] = tìm nhưng không có kết quả
$dbError  = null;
$destinations = [];

try {
    $destinations = getAllDestinations();
} catch (PDOException $ex) {
    $dbError = 'Không kết nối được cơ sở dữ liệu. Hãy bật MySQL trong XAMPP và import database/schema.sql.';
}

if ($searched) {
    $errors = validateSearch($in);
    if (!$errors && $dbError === null) {
        try {
            expireHolds();
            $results = searchRooms(
                $in['destination'],
                $in['check_in'],
                $in['check_out'],
                (int) $in['guests'],
                (int) $in['rooms'],
                $in['min_price'] === '' ? null : (int) $in['min_price'],
                $in['max_price'] === '' ? null : (int) $in['max_price']
            );
        } catch (PDOException $ex) {
            $dbError = 'Lỗi khi truy vấn cơ sở dữ liệu.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php endif; ?>

<form method="get" action="index.php" id="search-form" novalidate>
    <div class="form-row">
        <label for="destination">Điểm đến</label>
        <input type="text" id="destination" name="destination" list="destination-list"
               value="<?= e($in['destination']) ?>" placeholder="Ví dụ: Hà Nội">
        <datalist id="destination-list">
            <?php foreach ($destinations as $d): ?>
                <option value="<?= e($d) ?>">
            <?php endforeach; ?>
        </datalist>
        <?php fieldError($errors, 'destination'); ?>
    </div>

    <div class="form-row">
        <label for="check_in">Ngày nhận phòng</label>
        <input type="text" id="check_in" name="check_in"
               value="<?= e($in['check_in']) ?>" placeholder="YYYY-MM-DD">
        <?php fieldError($errors, 'check_in'); ?>
    </div>

    <div class="form-row">
        <label for="check_out">Ngày trả phòng</label>
        <input type="text" id="check_out" name="check_out"
               value="<?= e($in['check_out']) ?>" placeholder="YYYY-MM-DD">
        <?php fieldError($errors, 'check_out'); ?>
    </div>

    <div class="form-row">
        <label for="guests">Số lượng người</label>
        <input type="text" id="guests" name="guests" value="<?= e($in['guests']) ?>">
        <?php fieldError($errors, 'guests'); ?>
    </div>

    <div class="form-row">
        <label for="rooms">Số lượng phòng</label>
        <input type="text" id="rooms" name="rooms" value="<?= e($in['rooms']) ?>">
        <?php fieldError($errors, 'rooms'); ?>
    </div>

    <div class="form-row">
        <label for="min_price">Giá tối thiểu / đêm (VND)</label>
        <input type="text" id="min_price" name="min_price" value="<?= e($in['min_price']) ?>">
        <span class="hint">không bắt buộc</span>
        <?php fieldError($errors, 'min_price'); ?>
    </div>

    <div class="form-row">
        <label for="max_price">Giá tối đa / đêm (VND)</label>
        <input type="text" id="max_price" name="max_price" value="<?= e($in['max_price']) ?>">
        <span class="hint">không bắt buộc</span>
        <?php fieldError($errors, 'max_price'); ?>
    </div>

    <div class="form-row">
        <button type="submit" id="btn-search" name="search" value="1">Tìm kiếm</button>
    </div>
</form>

<?php if ($searched && $errors): ?>
    <div class="error-box" id="validation-summary">Vui lòng kiểm tra lại các trường bị lỗi ở trên.</div>
<?php endif; ?>

<?php if ($results !== null): ?>
    <div id="results">
        <?php if (count($results) === 0): ?>
            <div class="info-box" id="no-result">Không tìm thấy kết quả phù hợp</div>
        <?php else: ?>
            <p id="result-count">Tìm thấy <?= count($results) ?> kết quả
                (<?= e(calculateNights($in['check_in'], $in['check_out'])) ?> đêm, <?= e((int) $in['rooms']) ?> phòng)</p>
            <table id="result-table">
                <tr>
                    <th>Mã phòng</th><th>Khách sạn</th><th>Loại phòng</th><th>Điểm đến</th>
                    <th>Giá / đêm</th><th>Đánh giá</th><th>Còn trống</th><th>Tổng tiền</th><th></th>
                </tr>
                <?php foreach ($results as $r):
                    $query = http_build_query([
                        'room_id'   => $r['id'],
                        'check_in'  => $in['check_in'],
                        'check_out' => $in['check_out'],
                        'guests'    => $in['guests'],
                        'rooms'     => $in['rooms'],
                    ]); ?>
                    <tr class="room-row">
                        <td class="col-id"><?= e($r['id']) ?></td>
                        <td class="col-hotel"><?= e($r['hotel_name']) ?></td>
                        <td class="col-room"><?= e($r['room_name']) ?></td>
                        <td class="col-destination"><?= e($r['destination']) ?></td>
                        <td class="col-price"><?= e(formatPrice($r['price'])) ?></td>
                        <td class="col-rating"><?= e($r['rating']) ?></td>
                        <td class="col-available"><?= e($r['available']) ?></td>
                        <td class="col-total"><?= e(formatPrice($r['total_price'])) ?></td>
                        <td><a class="btn btn-view" href="room.php?<?= e($query) ?>">Xem chi tiết</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>