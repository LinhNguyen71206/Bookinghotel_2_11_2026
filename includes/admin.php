<?php
/* ============================================================
 * includes/admin.php - Logic khu vực quản trị
 * ============================================================ */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/search.php';          // dùng escapeLike()
require_once __DIR__ . '/../config/admin.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Các ngưỡng của form phòng (quy định của chương trình này) ---------- */
const LIMIT_HOTEL_NAME_MAX  = 100;
const LIMIT_ROOM_NAME_MAX   = 100;
const LIMIT_ADDRESS_MAX     = 200;
const LIMIT_DESCRIPTION_MAX = 1000;
const LIMIT_POLICY_MAX      = 200;
const LIMIT_MEAL_MAX        = 100;
const LIMIT_AMENITY_MAX     = 50;
const LIMIT_AMENITY_COUNT   = 20;
const LIMIT_ROOM_GUESTS     = 10;
const LIMIT_ROOM_TOTAL      = 100;
const LIMIT_ROOM_PRICE      = 100000000;
const LIMIT_RATING_MAX      = 10;
const LIMIT_KEYWORD_MAX     = 100;
const DEFAULT_IMAGE_COLOR   = '#4a90d9';

const ROOM_COLUMNS = ['hotel_name', 'room_name', 'destination', 'address', 'rating', 'max_guests',
                      'description', 'amenities', 'cancellation_policy', 'meal_policy',
                      'price', 'total_rooms', 'image_color', 'status'];

/* ---------- Đăng nhập / CSRF / thông báo ---------- */
function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_user']);
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminLogin(string $user, string $pass): bool
{
    if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $user;
        return true;
    }
    return false;
}

function adminLogout(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfValid(array $in): bool
{
    return isset($in['csrf']) && is_string($in['csrf']) && hash_equals(csrfToken(), $in['csrf']);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function takeFlash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Nhãn trạng thái ---------- */
function roomStatusLabels(): array
{
    return ['active' => 'Đang hoạt động', 'maintenance' => 'Bảo trì', 'inactive' => 'Ngừng kinh doanh'];
}

function bookingStatusLabels(): array
{
    return ['pending_payment' => 'Chờ thanh toán', 'confirmed' => 'Đã xác nhận',
            'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn giữ chỗ'];
}

/* ============================================================
 * QUẢN LÝ PHÒNG
 * ============================================================ */

/* Tìm phòng cho admin: từ khóa (mã, khách sạn, loại phòng, điểm đến), trạng thái, điểm đến */
function adminSearchRooms(string $keyword, string $status, string $destination): array
{
    $sql = 'SELECT * FROM rooms WHERE 1=1';
    $params = [];
    if ($keyword !== '') {
        $kw = '%' . escapeLike($keyword) . '%';
        $sql .= " AND (id LIKE :k1 ESCAPE '!' OR hotel_name LIKE :k2 ESCAPE '!'
                  OR room_name LIKE :k3 ESCAPE '!' OR destination LIKE :k4 ESCAPE '!')";
        $params += [':k1' => $kw, ':k2' => $kw, ':k3' => $kw, ':k4' => $kw];
    }
    if ($status !== '') {
        $sql .= ' AND status = :status';
        $params[':status'] = $status;
    }
    if ($destination !== '') {
        $sql .= ' AND destination = :dest';
        $params[':dest'] = $destination;
    }
    $sql .= ' ORDER BY id';
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return array_map('decodeRoom', $stmt->fetchAll());
}

/* "Vui lòng nhập..." / độ dài; $min = 1 nghĩa là chỉ cần không rỗng */
function validateText(string $raw, string $name, int $min, int $max): ?string
{
    if ($raw === '') {
        return 'Vui lòng nhập ' . mb_strtolower($name);
    }
    $len = mb_strlen($raw);
    if ($len < $min || $len > $max) {
        return $min > 1 ? "$name phải từ $min đến $max ký tự" : "$name tối đa $max ký tự";
    }
    return null;
}

/* Textarea tiện nghi: mỗi dòng một tiện nghi, bỏ dòng trống */
function parseAmenities(string $raw): array
{
    $out = [];
    foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/* Kiểm tra form thêm/sửa phòng. Trả về ['tên_trường' => 'lỗi'] ([] = hợp lệ) */
function validateRoomForm(array $in): array
{
    $errors = [];
    $checks = [
        ['hotel_name',          'Tên khách sạn',    2, LIMIT_HOTEL_NAME_MAX],
        ['room_name',           'Tên loại phòng',   2, LIMIT_ROOM_NAME_MAX],
        ['destination',         'Điểm đến',         1, LIMIT_DESTINATION_MAX],
        ['address',             'Địa chỉ',          1, LIMIT_ADDRESS_MAX],
        ['description',         'Mô tả',            1, LIMIT_DESCRIPTION_MAX],
        ['cancellation_policy', 'Chính sách hủy',   1, LIMIT_POLICY_MAX],
        ['meal_policy',         'Chính sách bữa ăn', 1, LIMIT_MEAL_MAX],
    ];
    foreach ($checks as [$field, $name, $min, $max]) {
        $err = validateText(inputString($in, $field), $name, $min, $max);
        if ($err !== null) {
            $errors[$field] = $err;
        }
    }

    $rating = inputString($in, 'rating');
    if ($rating === '') {
        $errors['rating'] = 'Vui lòng nhập điểm đánh giá';
    } elseif (!preg_match('/^\d{1,2}(\.\d)?$/', $rating) || (float) $rating > LIMIT_RATING_MAX) {
        $errors['rating'] = 'Điểm đánh giá phải từ 0 đến ' . LIMIT_RATING_MAX . ' (tối đa 1 chữ số thập phân)';
    }

    [$e] = validateCount(inputString($in, 'max_guests'), 'Số khách tối đa mỗi phòng', LIMIT_ROOM_GUESTS);
    if ($e !== null) {
        $errors['max_guests'] = $e;
    }
    [$e] = validateCount(inputString($in, 'total_rooms'), 'Tổng số phòng', LIMIT_ROOM_TOTAL);
    if ($e !== null) {
        $errors['total_rooms'] = $e;
    }

    [$e, $price] = validatePrice(inputString($in, 'price'), 'Giá mỗi đêm', 1);
    if ($e !== null) {
        $errors['price'] = $e;
    } elseif ($price > LIMIT_ROOM_PRICE) {
        $errors['price'] = 'Giá mỗi đêm tối đa ' . formatPrice(LIMIT_ROOM_PRICE);
    }

    $amenities = parseAmenities(inputString($in, 'amenities'));
    if (count($amenities) > LIMIT_AMENITY_COUNT) {
        $errors['amenities'] = 'Tối đa ' . LIMIT_AMENITY_COUNT . ' tiện nghi';
    } else {
        foreach ($amenities as $a) {
            if (mb_strlen($a) > LIMIT_AMENITY_MAX) {
                $errors['amenities'] = 'Mỗi tiện nghi tối đa ' . LIMIT_AMENITY_MAX . ' ký tự';
                break;
            }
        }
    }

    $color = inputString($in, 'image_color');
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $errors['image_color'] = 'Màu ảnh phải có dạng #RRGGBB';
    }

    $status = inputString($in, 'status');
    if ($status === '') {
        $errors['status'] = 'Vui lòng chọn trạng thái phòng';
    } elseif (!array_key_exists($status, roomStatusLabels())) {
        $errors['status'] = 'Trạng thái phòng không hợp lệ';
    }

    return $errors;
}

/* Chuyển dữ liệu form (đã hợp lệ) thành dữ liệu ghi CSDL */
function roomFormData(array $in): array
{
    $color = inputString($in, 'image_color');
    return [
        'hotel_name'          => inputString($in, 'hotel_name'),
        'room_name'           => inputString($in, 'room_name'),
        'destination'         => inputString($in, 'destination'),
        'address'             => inputString($in, 'address'),
        'rating'              => number_format((float) inputString($in, 'rating'), 1, '.', ''),
        'max_guests'          => (int) inputString($in, 'max_guests'),
        'description'         => inputString($in, 'description'),
        'amenities'           => json_encode(parseAmenities(inputString($in, 'amenities')), JSON_UNESCAPED_UNICODE),
        'cancellation_policy' => inputString($in, 'cancellation_policy'),
        'meal_policy'         => inputString($in, 'meal_policy'),
        'price'               => (int) inputString($in, 'price'),
        'total_rooms'         => (int) inputString($in, 'total_rooms'),
        'image_color'         => $color === '' ? DEFAULT_IMAGE_COLOR : $color,
        'status'              => inputString($in, 'status'),
    ];
}

function roomParams(array $data): array
{
    $p = [];
    foreach (ROOM_COLUMNS as $c) {
        $p[':' . $c] = $data[$c];
    }
    return $p;
}

/* Mã phòng kế tiếp: R + 3 chữ số, lớn hơn mã lớn nhất hiện có */
function adminNextRoomId(): string
{
    $max = 0;
    foreach (getPDO()->query('SELECT id FROM rooms')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (preg_match('/^R(\d{3})$/', (string) $id, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    if ($max >= 999) {
        throw new RuntimeException('Đã hết mã phòng (R999)');
    }
    return 'R' . sprintf('%03d', $max + 1);
}

function adminCreateRoom(array $data): string
{
    $id = adminNextRoomId();
    $sql = 'INSERT INTO rooms (id, ' . implode(', ', ROOM_COLUMNS) . ')
            VALUES (:id, :' . implode(', :', ROOM_COLUMNS) . ')';
    getPDO()->prepare($sql)->execute([':id' => $id] + roomParams($data));
    return $id;
}

function adminUpdateRoom(string $id, array $data): void
{
    $sets = [];
    foreach (ROOM_COLUMNS as $c) {
        $sets[] = "$c = :$c";
    }
    $sql = 'UPDATE rooms SET ' . implode(', ', $sets) . ' WHERE id = :id';
    getPDO()->prepare($sql)->execute([':id' => $id] + roomParams($data));
}

function adminSetRoomStatus(string $id, string $status): void
{
    getPDO()->prepare('UPDATE rooms SET status = :status WHERE id = :id')
        ->execute([':status' => $status, ':id' => $id]);
}

function adminCountBookingsOfRoom(string $id): int
{
    $stmt = getPDO()->prepare('SELECT COUNT(*) FROM bookings WHERE room_id = :id');
    $stmt->execute([':id' => $id]);
    return (int) $stmt->fetchColumn();
}

/* Xóa phòng nếu chưa có đơn nào. Trả về 'ok' | 'not_found' | 'has_bookings' */
function adminDeleteRoom(string $id): string
{
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM rooms WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $id]);
        if (!$lock->fetch()) {
            $pdo->rollBack();
            return 'not_found';
        }
        if (adminCountBookingsOfRoom($id) > 0) {
            $pdo->rollBack();
            return 'has_bookings';
        }
        $pdo->prepare('DELETE FROM rooms WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
        return 'ok';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ============================================================
 * QUẢN LÝ ĐƠN ĐẶT PHÒNG
 * ============================================================ */

function validateBookingFilter(array $f): array
{
    $errors = [];
    if (mb_strlen($f['keyword']) > LIMIT_KEYWORD_MAX) {
        $errors['keyword'] = 'Từ khóa tối đa ' . LIMIT_KEYWORD_MAX . ' ký tự';
    }
    if ($f['status'] !== '' && !array_key_exists($f['status'], bookingStatusLabels())) {
        $errors['status'] = 'Trạng thái không hợp lệ';
    }
    if ($f['room_id'] !== '' && !preg_match(ROOM_ID_PATTERN, $f['room_id'])) {
        $errors['room_id'] = 'Mã phòng không hợp lệ';
    }
    $fromOk = $toOk = false;
    if ($f['date_from'] !== '') {
        if (isValidDate($f['date_from'])) {
            $fromOk = true;
        } else {
            $errors['date_from'] = 'Ngày bắt đầu không hợp lệ (YYYY-MM-DD)';
        }
    }
    if ($f['date_to'] !== '') {
        if (isValidDate($f['date_to'])) {
            $toOk = true;
        } else {
            $errors['date_to'] = 'Ngày kết thúc không hợp lệ (YYYY-MM-DD)';
        }
    }
    if ($fromOk && $toOk && $f['date_from'] > $f['date_to']) {
        $errors['date_to'] = 'Ngày kết thúc không được trước ngày bắt đầu';
    }
    return $errors;
}

/*
 * Tìm đơn: từ khóa (mã đơn, tên, SĐT, email, mã phòng, khách sạn, loại phòng),
 * trạng thái, phòng, khoảng ngày NHẬN PHÒNG [date_from, date_to].
 * Mới nhất xếp trước.
 */
function adminSearchBookings(array $f): array
{
    $sql = 'SELECT b.*, r.hotel_name, r.room_name
            FROM bookings b LEFT JOIN rooms r ON r.id = b.room_id
            WHERE 1=1';
    $params = [];
    if ($f['keyword'] !== '') {
        $kw = '%' . escapeLike($f['keyword']) . '%';
        $sql .= " AND (b.code LIKE :k1 ESCAPE '!' OR b.full_name LIKE :k2 ESCAPE '!'
                  OR b.phone LIKE :k3 ESCAPE '!' OR b.email LIKE :k4 ESCAPE '!'
                  OR b.room_id LIKE :k5 ESCAPE '!' OR r.hotel_name LIKE :k6 ESCAPE '!'
                  OR r.room_name LIKE :k7 ESCAPE '!')";
        foreach (['k1', 'k2', 'k3', 'k4', 'k5', 'k6', 'k7'] as $k) {
            $params[':' . $k] = $kw;
        }
    }
    if ($f['status'] !== '') {
        $sql .= ' AND b.status = :status';
        $params[':status'] = $f['status'];
    }
    if ($f['room_id'] !== '') {
        $sql .= ' AND b.room_id = :room_id';
        $params[':room_id'] = $f['room_id'];
    }
    if ($f['date_from'] !== '') {
        $sql .= ' AND b.check_in >= :date_from';
        $params[':date_from'] = $f['date_from'];
    }
    if ($f['date_to'] !== '') {
        $sql .= ' AND b.check_in <= :date_to';
        $params[':date_to'] = $f['date_to'];
    }
    $sql .= ' ORDER BY b.created_at DESC, b.code ASC';
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* Các chuyển trạng thái được phép */
function allowedBookingTransitions(string $from): array
{
    $map = [
        'pending_payment' => ['confirmed', 'cancelled'],
        'confirmed'       => ['completed', 'cancelled'],
        'completed'       => [],
        'cancelled'       => [],
        'expired'         => [],
    ];
    return $map[$from] ?? [];
}

/*
 * Admin đổi trạng thái đơn (trong giao dịch, khóa dòng đơn).
 * Trả về [true|false, thông báo].
 */
function adminChangeBookingStatus(string $code, string $new, ?string $now = null): array
{
    $labels = bookingStatusLabels();
    if (!array_key_exists($new, $labels)) {
        return [false, 'Trạng thái mới không hợp lệ'];
    }
    $now = nowString($now);
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, check_in, hold_expires_at FROM bookings WHERE code = :code FOR UPDATE');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return [false, 'Không tìm thấy đơn đặt phòng'];
        }
        $cur = $row['status'];
        if ($cur === 'pending_payment' && $row['hold_expires_at'] <= $now) {
            expireHolds($now);
            $pdo->commit();
            return [false, 'Đơn đã hết hạn giữ chỗ nên không thể cập nhật'];
        }
        if ($new === $cur) {
            $pdo->rollBack();
            return [false, 'Đơn đã ở trạng thái "' . $labels[$cur] . '"'];
        }
        if (!in_array($new, allowedBookingTransitions($cur), true)) {
            $pdo->rollBack();
            return [false, 'Không thể chuyển đơn từ "' . ($labels[$cur] ?? $cur) . '" sang "' . $labels[$new] . '"'];
        }
        if ($new === 'completed' && $row['check_in'] > substr($now, 0, 10)) {
            $pdo->rollBack();
            return [false, 'Chỉ có thể hoàn thành đơn từ ngày nhận phòng trở đi'];
        }

        updateBooking($code, ['status' => $new]);
        $pdo->commit();
        return [true, 'Đã chuyển đơn ' . $code . ' sang trạng thái "' . $labels[$new] . '"'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}