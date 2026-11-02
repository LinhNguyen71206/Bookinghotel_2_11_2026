<?php
/* ============================================================
 * room.php - Chức năng 2: XEM THÔNG TIN PHÒNG
 * Tham số GET: room_id, check_in, check_out, guests, rooms (tùy chọn, mặc định 1)
 *
 * Quy tắc hiển thị:
 *  - room_id sai định dạng hoặc không tồn tại -> chỉ báo lỗi, KHÔNG hiện trang chi tiết.
 *  - Phòng hợp lệ nhưng ngày/số khách sai hoặc phiên hết hạn (ngày nhận đã qua)
 *    -> hiện thông tin phòng + form chọn lại ngày, KHÔNG hiện giá và tình trạng.
 *  - Mọi thứ hợp lệ -> hiện giá theo ngày đã chọn, tình trạng còn phòng,
 *    và nút "Đặt phòng" nếu đủ phòng trống và đủ sức chứa.
 * ============================================================ */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Thông tin phòng';

$fields = ['room_id', 'check_in', 'check_out', 'guests', 'rooms'];
$in = [];
foreach ($fields as $f) {
    $in[$f] = inputString($_GET, $f);
}

$errors = validateRoomView($in);

// Số phòng: không bắt buộc, để trống = 1
$rooms = 1;
if ($in['rooms'] !== '') {
    [$roomsError, $roomsValue] = validateCount($in['rooms'], 'Số lượng phòng', LIMIT_MAX_ROOMS);
    if ($roomsError !== null) {
        $errors['rooms'] = $roomsError;
    } else {
        $rooms = $roomsValue;
    }
}
$guests = isset($errors['guests']) ? null : (int) $in['guests'];
if ($guests !== null && !isset($errors['rooms']) && $rooms > $guests) {
    $errors['rooms'] = 'Số lượng phòng không được lớn hơn số lượng người';
}

// Tìm phòng
$room      = null;
$roomError = null;
$dbError   = null;
if (isset($errors['room_id'])) {
    $roomError = $errors['room_id'];
} else {
    try {
        $room = getRoomById($in['room_id']);
        if ($room === null) {
            $roomError = 'Không tìm thấy phòng có mã ' . $in['room_id'];
        }
    } catch (PDOException $ex) {
        $dbError = 'Không kết nối được cơ sở dữ liệu. Hãy bật MySQL trong XAMPP và import database/schema.sql.';
    }
}

// Giá và tình trạng phòng (chỉ khi phòng tồn tại và mọi dữ liệu hợp lệ)
$view = null;
if ($room !== null && !$errors) {
    try {
        expireHolds();
        $nights    = calculateNights($in['check_in'], $in['check_out']);
        $available = getAvailableQuantity($room['id'], $in['check_in'], $in['check_out']);
        $maxGuests = (int) $room['max_guests'] * $rooms;
        $capacityOk = $guests <= $maxGuests;

        if ($available >= $rooms) {
            $availabilityText = "Còn trống ($available phòng)";
        } elseif ($available === 0) {
            $availabilityText = 'Hết phòng trong ngày đã chọn';
        } else {
            $availabilityText = "Chỉ còn $available phòng trong ngày đã chọn (bạn chọn $rooms phòng)";
        }

        $view = [
            'nights'       => $nights,
            'total'        => calculateTotal((int) $room['price'], $nights, $rooms),
            'available'    => $available,
            'availability' => $availabilityText,
            'capacityOk'   => $capacityOk,
            'capacityText' => 'Không đủ sức chứa: ' . $rooms . ' phòng chứa tối đa ' . $maxGuests . ' khách',
            'bookable'     => $capacityOk && $available >= $rooms,
        ];
    } catch (PDOException $ex) {
        $dbError = 'Lỗi khi truy vấn cơ sở dữ liệu.';
    }
}

require __DIR__ . '/includes/header.php';
?>

<p><a href="index.php">&laquo; Quay lại tìm kiếm</a></p>

<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php elseif ($roomError !== null): ?>
    <div class="error-box" id="room-error"><?= e($roomError) ?></div>
<?php else: ?>

    <div class="card" id="room-info">
        <div class="room-image" style="background: <?= e($room['image_color']) ?>">Ảnh phòng</div>
        <h3 id="room-name"><?= e($room['room_name']) ?></h3>
        <table class="info">
            <tr><th>Mã phòng</th><td id="room-id"><?= e($room['id']) ?></td></tr>
            <tr><th>Khách sạn</th><td id="hotel-name"><?= e($room['hotel_name']) ?></td></tr>
            <tr><th>Điểm đến</th><td id="destination"><?= e($room['destination']) ?></td></tr>
            <tr><th>Địa chỉ</th><td id="address"><?= e($room['address']) ?></td></tr>
            <tr><th>Đánh giá</th><td id="rating"><?= e($room['rating']) ?></td></tr>
            <tr><th>Số khách tối đa / phòng</th><td id="max-guests"><?= e($room['max_guests']) ?></td></tr>
            <tr><th>Mô tả</th><td id="description"><?= e($room['description']) ?></td></tr>
            <tr><th>Tiện nghi</th>
                <td><ul id="amenities">
                    <?php foreach ($room['amenities'] as $a): ?>
                        <li><?= e($a) ?></li>
                    <?php endforeach; ?>
                </ul></td></tr>
            <tr><th>Chính sách hủy</th><td id="cancellation-policy"><?= e($room['cancellation_policy']) ?></td></tr>
            <tr><th>Bữa ăn</th><td id="meal-policy"><?= e($room['meal_policy']) ?></td></tr>
            <tr><th>Giá / đêm</th><td id="base-price"><?= e(formatPrice($room['price'])) ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h3>Chọn ngày để xem giá và tình trạng phòng</h3>
        <form method="get" action="room.php" id="view-form" novalidate>
            <input type="hidden" name="room_id" value="<?= e($room['id']) ?>">
            <div class="form-row">
                <label for="check_in">Ngày nhận phòng</label>
                <input type="text" id="check_in" name="check_in" value="<?= e($in['check_in']) ?>" placeholder="YYYY-MM-DD">
                <?php fieldError($errors, 'check_in'); ?>
            </div>
            <div class="form-row">
                <label for="check_out">Ngày trả phòng</label>
                <input type="text" id="check_out" name="check_out" value="<?= e($in['check_out']) ?>" placeholder="YYYY-MM-DD">
                <?php fieldError($errors, 'check_out'); ?>
            </div>
            <div class="form-row">
                <label for="guests">Số lượng người</label>
                <input type="text" id="guests" name="guests" value="<?= e($in['guests']) ?>">
                <?php fieldError($errors, 'guests'); ?>
            </div>
            <div class="form-row">
                <label for="rooms">Số lượng phòng</label>
                <input type="text" id="rooms" name="rooms" value="<?= e($in['rooms']) ?>" placeholder="1">
                <span class="hint">để trống = 1 phòng</span>
                <?php fieldError($errors, 'rooms'); ?>
            </div>
            <div class="form-row">
                <button type="submit" id="btn-update">Cập nhật giá</button>
            </div>
        </form>
    </div>

    <?php if ($errors): ?>
        <div class="info-box" id="price-unavailable">
            Vui lòng chọn ngày và số khách hợp lệ để xem giá và tình trạng phòng chính xác.
        </div>
    <?php elseif ($view !== null): ?>
        <div class="card" id="price-info">
            <h3>Giá theo ngày đã chọn</h3>
            <table class="info">
                <tr><th>Ngày nhận - trả phòng</th><td id="stay-dates"><?= e($in['check_in']) ?> &rarr; <?= e($in['check_out']) ?></td></tr>
                <tr><th>Số đêm</th><td id="nights"><?= e($view['nights']) ?></td></tr>
                <tr><th>Số phòng</th><td id="rooms-count"><?= e($rooms) ?></td></tr>
                <tr><th>Số người</th><td id="guests-count"><?= e($guests) ?></td></tr>
                <tr><th>Giá / đêm</th><td id="price-per-night"><?= e(formatPrice($room['price'])) ?></td></tr>
                <tr><th>Tổng tiền</th><td id="total-price"><?= e(formatPrice($view['total'])) ?></td></tr>
                <tr><th>Tình trạng</th><td id="availability"><?= e($view['availability']) ?></td></tr>
            </table>

            <?php if (!$view['capacityOk']): ?>
                <div class="error-box" id="capacity-error"><?= e($view['capacityText']) ?></div>
            <?php endif; ?>

            <?php if ($view['bookable']):
                $query = http_build_query([
                    'room_id'   => $room['id'],
                    'check_in'  => $in['check_in'],
                    'check_out' => $in['check_out'],
                    'guests'    => $guests,
                    'rooms'     => $rooms,
                ]); ?>
                <p><a class="btn" id="btn-book" href="booking.php?<?= e($query) ?>">Đặt phòng</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>