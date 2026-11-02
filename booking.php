<?php
/* ============================================================
 * booking.php - Chức năng 3: ĐẶT PHÒNG
 * Vào trang bằng GET từ nút "Đặt phòng" ở room.php:
 *     booking.php?room_id=&check_in=&check_out=&guests=&rooms=
 * Gửi form bằng POST (các tham số trên được giữ lại bằng trường ẩn).
 *
 * Quy tắc:
 *  - Thông tin lưu trú (mã phòng, ngày, số người, số phòng) sai/hết hạn -> báo lỗi, KHÔNG hiện form.
 *  - Thông tin khách sai -> báo lỗi từng trường, KHÔNG tạo đơn.
 *  - Phòng vừa hết (người khác đặt trước) -> "Phòng vừa hết, vui lòng chọn phòng khác", KHÔNG tạo đơn.
 *  - Hợp lệ -> tạo đơn (trạng thái pending_payment, có mã đặt phòng duy nhất,
 *    giữ chỗ HOLD_MINUTES phút) rồi chuyển sang payment.php?code=<mã đơn>.
 * ============================================================ */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/helpers.php';

const MSG_SOLD_OUT = 'Phòng vừa hết, vui lòng chọn phòng khác';

/*
 * Kiểm tra lại số phòng trống rồi tạo đơn trong CÙNG một giao dịch.
 * Khóa dòng của phòng (SELECT ... FOR UPDATE) để hai người đặt cùng lúc
 * không thể cùng lấy phòng cuối cùng. Cần bảng dùng InnoDB.
 * Trả về mã đơn, hoặc null nếu không còn đủ phòng.
 */
function placeBooking(array $data, ?string $now = null): ?string
{
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM rooms WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $data['room_id']]);
        $lock->fetch();

        expireHolds($now);
        $available = getAvailableQuantity($data['room_id'], $data['check_in'], $data['check_out'], $now);
        if ($available < $data['quantity']) {
            $pdo->rollBack();
            return null;
        }

        $code = addBooking($data, $now);
        $pdo->commit();
        return $code;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

$pageTitle = 'Đặt phòng';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$src = $isPost ? $_POST : $_GET;

/* ---------- Thông tin lưu trú (từ GET hoặc trường ẩn của POST) ---------- */
$stay = [];
foreach (['room_id', 'check_in', 'check_out', 'guests', 'rooms'] as $f) {
    $stay[$f] = inputString($src, $f);
}

$stayErrors = validateRoomView($stay);

$rooms = 1; // để trống = 1 phòng (giống room.php)
if ($stay['rooms'] !== '') {
    [$roomsError, $roomsValue] = validateCount($stay['rooms'], 'Số lượng phòng', LIMIT_MAX_ROOMS);
    if ($roomsError !== null) {
        $stayErrors['rooms'] = $roomsError;
    } else {
        $rooms = $roomsValue;
    }
}
$guests = isset($stayErrors['guests']) ? null : (int) $stay['guests'];
if ($guests !== null && !isset($stayErrors['rooms']) && $rooms > $guests) {
    $stayErrors['rooms'] = 'Số lượng phòng không được lớn hơn số lượng người';
}

/* ---------- Tìm phòng ---------- */
$room      = null;
$roomError = null;
$dbError   = null;
if (isset($stayErrors['room_id'])) {
    $roomError = $stayErrors['room_id'];
} else {
    try {
        $room = getRoomById($stay['room_id']);
        if ($room === null) {
            $roomError = 'Không tìm thấy phòng có mã ' . $stay['room_id'];
        }
    } catch (PDOException $ex) {
        $dbError = 'Không kết nối được cơ sở dữ liệu. Hãy bật MySQL trong XAMPP và import database/schema.sql.';
    }
}

/* ---------- Kiểm tra sức chứa, tình trạng phòng, tính tiền ---------- */
$summary       = null;   // thông tin tóm tắt đơn (chỉ khi được phép đặt)
$bookingError  = null;   // lỗi mức đơn: không đủ sức chứa / hết phòng
$form          = [];     // dữ liệu khách đã nhập
$errors        = [];     // lỗi từng trường của khách
foreach (['full_name', 'phone', 'country', 'email', 'special_request', 'arrival_time'] as $f) {
    $form[$f] = $isPost ? inputString($_POST, $f) : '';
}

if ($dbError === null && $roomError === null && !$stayErrors) {
    try {
        expireHolds();
        $nights = calculateNights($stay['check_in'], $stay['check_out']);
        $total  = calculateTotal((int) $room['price'], $nights, $rooms);
        $summary = ['nights' => $nights, 'total' => $total];

        $maxGuests = (int) $room['max_guests'] * $rooms;
        if ($guests > $maxGuests) {
            $bookingError = 'Không đủ sức chứa: ' . $rooms . ' phòng chứa tối đa ' . $maxGuests . ' khách';
        } elseif (getAvailableQuantity($room['id'], $stay['check_in'], $stay['check_out']) < $rooms) {
            $bookingError = MSG_SOLD_OUT;
        }

        /* ---------- Xử lý gửi form ---------- */
        if ($isPost && $bookingError === null) {
            $errors = validateBookingInfo($form);
            if (!$errors) {
                $code = placeBooking([
                    'room_id'         => $room['id'],
                    'check_in'        => $stay['check_in'],
                    'check_out'       => $stay['check_out'],
                    'guests'          => $guests,
                    'quantity'        => $rooms,
                    'full_name'       => $form['full_name'],
                    'phone'           => $form['phone'],
                    'country'         => $form['country'],
                    'email'           => $form['email'],
                    'special_request' => $form['special_request'] === '' ? null : $form['special_request'],
                    'arrival_time'    => $form['arrival_time'] === '' ? null : $form['arrival_time'],
                    'total_amount'    => $total,
                ]);
                if ($code === null) {
                    $bookingError = MSG_SOLD_OUT;
                } else {
                    header('Location: payment.php?code=' . urlencode($code));
                    exit;
                }
            }
        }
    } catch (PDOException $ex) {
        $dbError = 'Lỗi khi truy vấn cơ sở dữ liệu.';
    }
}

/* Đường dẫn quay lại trang phòng để chọn lại ngày */
$backQuery = http_build_query($stay);

require __DIR__ . '/includes/header.php';
?>

<p><a href="room.php?<?= e($backQuery) ?>" id="link-back">&laquo; Quay lại thông tin phòng</a></p>

<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>
<?php elseif ($roomError !== null): ?>
    <div class="error-box" id="room-error"><?= e($roomError) ?></div>
<?php elseif ($stayErrors): ?>
    <div class="error-box" id="stay-error">
        <?php foreach ($stayErrors as $msg): ?>
            <div><?= e($msg) ?></div>
        <?php endforeach; ?>
    </div>
<?php else: ?>

    <div class="card" id="booking-summary">
        <h3>Thông tin đặt phòng</h3>
        <table class="info">
            <tr><th>Mã phòng</th><td id="sum-room-id"><?= e($room['id']) ?></td></tr>
            <tr><th>Khách sạn</th><td id="sum-hotel"><?= e($room['hotel_name']) ?></td></tr>
            <tr><th>Loại phòng</th><td id="sum-room"><?= e($room['room_name']) ?></td></tr>
            <tr><th>Ngày nhận - trả phòng</th><td id="sum-dates"><?= e($stay['check_in']) ?> &rarr; <?= e($stay['check_out']) ?></td></tr>
            <tr><th>Số đêm</th><td id="sum-nights"><?= e($summary['nights']) ?></td></tr>
            <tr><th>Số phòng</th><td id="sum-rooms"><?= e($rooms) ?></td></tr>
            <tr><th>Số người</th><td id="sum-guests"><?= e($guests) ?></td></tr>
            <tr><th>Giá / đêm</th><td id="sum-price"><?= e(formatPrice($room['price'])) ?></td></tr>
            <tr><th>Tổng tiền</th><td id="sum-total"><?= e(formatPrice($summary['total'])) ?></td></tr>
        </table>
    </div>

    <?php if ($bookingError !== null): ?>
        <div class="error-box" id="booking-error"><?= e($bookingError) ?></div>
        <p><a class="btn" id="btn-search-again" href="index.php">Tìm phòng khác</a></p>
    <?php else: ?>
        <div class="card">
            <h3>Thông tin khách</h3>
            <form method="post" action="booking.php" id="booking-form" novalidate>
                <?php foreach ($stay as $name => $value): ?>
                    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>

                <div class="form-row">
                    <label for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>">
                    <?php fieldError($errors, 'full_name'); ?>
                </div>
                <div class="form-row">
                    <label for="phone">Số điện thoại</label>
                    <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>">
                    <?php fieldError($errors, 'phone'); ?>
                </div>
                <div class="form-row">
                    <label for="country">Quốc gia</label>
                    <select id="country" name="country">
                        <option value="">-- Chọn quốc gia --</option>
                        <?php foreach (getCountryList() as $c): ?>
                            <option value="<?= e($c) ?>"<?= $form['country'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php fieldError($errors, 'country'); ?>
                </div>
                <div class="form-row">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" value="<?= e($form['email']) ?>">
                    <?php fieldError($errors, 'email'); ?>
                </div>
                <div class="form-row">
                    <label for="special_request">Yêu cầu đặc biệt</label>
                    <textarea id="special_request" name="special_request" rows="3"><?= e($form['special_request']) ?></textarea>
                    <span class="hint">không bắt buộc</span>
                    <?php fieldError($errors, 'special_request'); ?>
                </div>
                <div class="form-row">
                    <label for="arrival_time">Giờ nhận phòng dự kiến</label>
                    <input type="text" id="arrival_time" name="arrival_time" value="<?= e($form['arrival_time']) ?>" placeholder="HH:MM">
                    <span class="hint">không bắt buộc</span>
                    <?php fieldError($errors, 'arrival_time'); ?>
                </div>
                <div class="form-row">
                    <button type="submit" id="btn-submit">Tiếp tục thanh toán</button>
                </div>
            </form>
            <?php if ($isPost && $errors): ?>
                <div class="error-box" id="validation-summary">Vui lòng kiểm tra lại các trường bị lỗi ở trên.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>