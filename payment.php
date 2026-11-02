<?php
/* ============================================================
 * payment.php - Chức năng 4: THANH TOÁN
 * Vào trang bằng GET từ booking.php:  payment.php?code=<mã đặt phòng>
 * Gửi form bằng POST.
 *
 * Quy tắc:
 *  - Không có đơn với mã này                  -> báo lỗi.
 *  - Đơn đã hết hạn giữ chỗ (expired)         -> "Phiên đặt phòng đã hết hạn", không cho thanh toán.
 *  - Đơn đã thanh toán (confirmed)            -> hiện hóa đơn.
 *  - Đơn đang chờ thanh toán (pending_payment):
 *      + dữ liệu thẻ sai định dạng -> báo lỗi từng trường (validatePayment), đơn giữ nguyên.
 *      + thẻ bị từ chối / không đủ số dư -> báo lỗi cụ thể, đơn giữ "chờ thanh toán", cho thử lại.
 *      + hết hạn giữ chỗ ngay lúc bấm thanh toán -> đơn chuyển expired.
 *      + thanh toán thành công -> đơn chuyển "confirmed", hiện hóa đơn.
 *
 * CỔNG THANH TOÁN LÀ GIẢ LẬP (không có cổng thật): kết quả phụ thuộc vào số thẻ,
 * theo bảng bên dưới. Số thẻ khác (đã qua kiểm tra định dạng) được chấp nhận.
 * Thông tin thẻ KHÔNG được lưu vào CSDL.
 * ============================================================ */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/helpers.php';

/* ---------- Thẻ giả lập (quy định của chương trình này, đổi tại đây) ---------- */
const SIMULATED_DECLINED_CARDS = [      // thẻ bị từ chối
    '4000000000000002',   // Visa
    '5100000000000008',   // Mastercard
    '3528000000000007',   // JCB
];
const SIMULATED_INSUFFICIENT_CARDS = [  // thẻ không đủ số dư
    '4000000000009995',   // Visa
    '5100000000000099',   // Mastercard
    '3528000000000098',   // JCB
];

const MSG_EXPIRED      = 'Phiên đặt phòng đã hết hạn';
const MSG_DECLINED     = 'Thẻ bị từ chối, vui lòng kiểm tra lại hoặc dùng thẻ khác';
const MSG_INSUFFICIENT = 'Thẻ không đủ số dư để thanh toán';

/* Kết quả giả lập của cổng thanh toán: 'approved' | 'declined' | 'insufficient' */
function simulateCardCharge(string $digits): string
{
    if (in_array($digits, SIMULATED_DECLINED_CARDS, true)) {
        return 'declined';
    }
    if (in_array($digits, SIMULATED_INSUFFICIENT_CARDS, true)) {
        return 'insufficient';
    }
    return 'approved';
}

/*
 * Xử lý thanh toán trong một giao dịch, khóa dòng đơn (SELECT ... FOR UPDATE)
 * để kiểm tra hạn giữ chỗ và đổi trạng thái không bị xen ngang. Cần bảng InnoDB.
 * Trả về: 'paid' | 'declined' | 'insufficient' | 'expired' | 'already_paid' | 'not_found'
 */
function processPayment(string $code, string $cardDigits, ?string $now = null): string
{
    $now = nowString($now);
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, hold_expires_at FROM bookings WHERE code = :code FOR UPDATE');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return 'not_found';
        }
                if ($row['status'] === 'confirmed' || $row['status'] === 'completed') {
            $pdo->rollBack();
            return 'already_paid';
        }
        if ($row['status'] === 'cancelled') {
            $pdo->rollBack();
            return 'cancelled';
        }
        if ($row['status'] === 'expired' || $row['hold_expires_at'] <= $now) {
            expireHolds($now);
            $pdo->commit();
            return 'expired';
        }

        $charge = simulateCardCharge($cardDigits);
        if ($charge !== 'approved') {
            $pdo->rollBack();       // đơn giữ nguyên "chờ thanh toán"
            return $charge;
        }

        updateBooking($code, ['status' => 'confirmed']);
        $pdo->commit();
        return 'paid';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

$pageTitle = 'Thanh toán';
$code = inputString($_GET, 'code');

$booking      = null;
$room         = null;
$dbError      = null;
$paymentError = null;   // lỗi mức giao dịch: thẻ bị từ chối / không đủ số dư / hết hạn
$errors       = [];     // lỗi định dạng từng trường
$form         = ['card_holder' => '', 'card_type' => '', 'card_number' => '', 'expiry' => '', 'cvv' => '', 'amount' => ''];

try {
    expireHolds();
    $booking = $code === '' ? null : getBookingByCode($code);

    if ($booking !== null
        && $booking['status'] === 'pending_payment'
        && $_SERVER['REQUEST_METHOD'] === 'POST') {

        foreach ($form as $f => $_) {
            $form[$f] = inputString($_POST, $f);
        }
        $errors = validatePayment($form, (int) $booking['total_amount']);

        if (!$errors) {
            $digits = preg_replace('/[\s\-]/', '', $form['card_number']);
            $result = processPayment($booking['code'], $digits);
            switch ($result) {
                case 'declined':     $paymentError = MSG_DECLINED;     break;
                case 'insufficient': $paymentError = MSG_INSUFFICIENT; break;
                case 'expired':      $paymentError = MSG_EXPIRED;      break;
                // 'paid', 'already_paid', 'not_found': đọc lại đơn bên dưới để hiển thị đúng trạng thái
            }
            $booking = getBookingByCode($code);
        }
        $form['cvv'] = '';   // không điền lại CVV sau khi gửi
    } elseif ($booking !== null && $booking['status'] === 'pending_payment') {
        $form['amount'] = (string) $booking['total_amount'];   // gợi ý sẵn số tiền của đơn
    }

    if ($booking !== null) {
        $room = getRoomById($booking['room_id']);
    }
} catch (PDOException $ex) {
    $dbError = 'Không kết nối được cơ sở dữ liệu hoặc lỗi khi truy vấn.';
}

require __DIR__ . '/includes/header.php';
?>

<p><a href="index.php">&laquo; Về trang tìm kiếm</a></p>

<?php if ($dbError !== null): ?>
    <div class="error-box" id="db-error"><?= e($dbError) ?></div>

<?php elseif ($booking === null): ?>
    <div class="error-box" id="booking-error">Không tìm thấy đơn đặt phòng</div>

<?php elseif ($booking['status'] === 'expired'): ?>
    <div class="error-box" id="expired-error"><?= e(MSG_EXPIRED) ?></div>
    <p><a class="btn" id="btn-search-again" href="index.php">Tìm phòng khác</a></p>

<?php elseif ($booking['status'] === 'cancelled'): ?>
    <div class="error-box" id="cancelled-error">Đơn đặt phòng đã bị hủy</div>
    <p><a class="btn" id="btn-search-again" href="index.php">Tìm phòng khác</a></p>

<?php elseif (in_array($booking['status'], ['confirmed', 'completed'], true)): ?>
    <div class="success-box" id="payment-success">Thanh toán thành công. Đơn đặt phòng đã được xác nhận.</div>
    <div class="card" id="invoice">
        <h3>Hóa đơn</h3>
        <table class="info">
            <tr><th>Mã đặt phòng</th><td id="inv-code"><?= e($booking['code']) ?></td></tr>
            <tr><th>Trạng thái</th><td id="inv-status"><?= $booking['status'] === 'completed' ? 'Đã hoàn thành' : 'Đã xác nhận / Đã thanh toán' ?></td></tr>
            <tr><th>Khách hàng</th><td id="inv-name"><?= e($booking['full_name']) ?></td></tr>
            <tr><th>Email</th><td id="inv-email"><?= e($booking['email']) ?></td></tr>
            <tr><th>Số điện thoại</th><td id="inv-phone"><?= e($booking['phone']) ?></td></tr>
            <?php if ($room !== null): ?>
                <tr><th>Khách sạn</th><td id="inv-hotel"><?= e($room['hotel_name']) ?></td></tr>
                <tr><th>Loại phòng</th><td id="inv-room"><?= e($room['room_name']) ?></td></tr>
            <?php endif; ?>
            <tr><th>Ngày nhận - trả phòng</th><td id="inv-dates"><?= e($booking['check_in']) ?> &rarr; <?= e($booking['check_out']) ?></td></tr>
            <tr><th>Số đêm</th><td id="inv-nights"><?= e(calculateNights($booking['check_in'], $booking['check_out'])) ?></td></tr>
            <tr><th>Số phòng</th><td id="inv-rooms"><?= e($booking['quantity']) ?></td></tr>
            <tr><th>Số người</th><td id="inv-guests"><?= e($booking['guests']) ?></td></tr>
            <tr><th>Số tiền đã thanh toán</th><td id="inv-total"><?= e(formatPrice($booking['total_amount'])) ?></td></tr>
        </table>
    </div>

<?php else: /* pending_payment */ ?>
    <div class="card" id="payment-summary">
        <h3>Đơn đặt phòng chờ thanh toán</h3>
        <table class="info">
            <tr><th>Mã đặt phòng</th><td id="pay-code"><?= e($booking['code']) ?></td></tr>
            <tr><th>Trạng thái</th><td id="pay-status">Chờ thanh toán</td></tr>
            <?php if ($room !== null): ?>
                <tr><th>Phòng</th><td id="pay-room"><?= e($room['hotel_name']) ?> - <?= e($room['room_name']) ?></td></tr>
            <?php endif; ?>
            <tr><th>Ngày nhận - trả phòng</th><td id="pay-dates"><?= e($booking['check_in']) ?> &rarr; <?= e($booking['check_out']) ?></td></tr>
            <tr><th>Tổng tiền</th><td id="pay-total"><?= e(formatPrice($booking['total_amount'])) ?></td></tr>
            <tr><th>Giữ chỗ đến</th><td id="pay-hold"><?= e($booking['hold_expires_at']) ?></td></tr>
        </table>
    </div>

    <?php if ($paymentError !== null): ?>
        <div class="error-box" id="payment-error"><?= e($paymentError) ?></div>
    <?php endif; ?>

    <?php if ($paymentError !== MSG_EXPIRED): ?>
        <div class="card">
            <h3>Thông tin thẻ</h3>
            <form method="post" action="payment.php?code=<?= e(urlencode($booking['code'])) ?>" id="payment-form" novalidate>
                <div class="form-row">
                    <label for="card_holder">Tên chủ thẻ</label>
                    <input type="text" id="card_holder" name="card_holder" value="<?= e($form['card_holder']) ?>">
                    <?php fieldError($errors, 'card_holder'); ?>
                </div>
                <div class="form-row">
                    <label for="card_type">Loại thẻ</label>
                    <select id="card_type" name="card_type">
                        <option value="">-- Chọn loại thẻ --</option>
                        <?php foreach (getCardTypes() as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $form['card_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php fieldError($errors, 'card_type'); ?>
                </div>
                <div class="form-row">
                    <label for="card_number">Số thẻ</label>
                    <input type="text" id="card_number" name="card_number" value="<?= e($form['card_number']) ?>" autocomplete="off">
                    <?php fieldError($errors, 'card_number'); ?>
                </div>
                <div class="form-row">
                    <label for="expiry">Ngày hết hạn</label>
                    <input type="text" id="expiry" name="expiry" value="<?= e($form['expiry']) ?>" placeholder="MM/YY">
                    <?php fieldError($errors, 'expiry'); ?>
                </div>
                <div class="form-row">
                    <label for="cvv">CVV</label>
                    <input type="text" id="cvv" name="cvv" value="" autocomplete="off">
                    <?php fieldError($errors, 'cvv'); ?>
                </div>
                <div class="form-row">
                    <label for="amount">Số tiền thanh toán (VND)</label>
                    <input type="text" id="amount" name="amount" value="<?= e($form['amount']) ?>">
                    <?php fieldError($errors, 'amount'); ?>
                </div>
                <div class="form-row">
                    <button type="submit" id="btn-pay">Thanh toán</button>
                </div>
            </form>
            <?php if ($errors): ?>
                <div class="error-box" id="validation-summary">Vui lòng kiểm tra lại các trường bị lỗi ở trên.</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p><a class="btn" id="btn-search-again" href="index.php">Tìm phòng khác</a></p>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>