<?php
/* ============================================================
 * includes/validators.php - Kiểm tra dữ liệu đầu vào (validate)
 *
 * Mỗi hàm validateXxx() nhận mảng dữ liệu (thường là $_GET / $_POST)
 * và trả về mảng lỗi dạng  ['tên_trường' => 'thông báo lỗi'].
 * Mảng rỗng [] nghĩa là dữ liệu hợp lệ. Mỗi trường chỉ báo 1 lỗi (lỗi đầu tiên gặp).
 *
 * Ngày dùng định dạng 'Y-m-d'. Tham số $today (tùy chọn) cho phép giả lập
 * "hôm nay" khi kiểm thử.
 *
 * CÁC NGƯỠNG BÊN DƯỚI LÀ QUY ĐỊNH CỦA CHƯƠNG TRÌNH NÀY (không phải chuẩn chung);
 * đây cũng là các giá trị biên để thiết kế ca kiểm thử. Có thể đổi tại đây.
 * ============================================================ */

const LIMIT_MAX_NIGHTS          = 30;   // số đêm lưu trú tối đa
const LIMIT_MAX_GUESTS          = 20;   // số người tối đa mỗi lần tìm kiếm
const LIMIT_MAX_ROOMS           = 5;    // số phòng tối đa mỗi lần đặt
const LIMIT_DESTINATION_MAX     = 50;   // độ dài tối đa của điểm đến
const LIMIT_NAME_MIN            = 2;    // độ dài họ tên: tối thiểu
const LIMIT_NAME_MAX            = 100;  // độ dài họ tên: tối đa
const LIMIT_EMAIL_MAX           = 100;  // độ dài email tối đa
const LIMIT_REQUEST_MAX         = 100;  // độ dài yêu cầu đặc biệt tối đa
const LIMIT_CARD_HOLDER_MAX     = 50;   // độ dài tên chủ thẻ tối đa

const ROOM_ID_PATTERN = '/^R\d{3}$/';                     // ví dụ: R001
const NAME_PATTERN    = '/^\p{L}[\p{L}\s\'.\-]*$/u';      // chữ cái, khoảng trắng, ' . -

/* ---------- Danh sách lựa chọn ---------- */
function getCountryList(): array
{
    return ['Việt Nam', 'Hoa Kỳ', 'Nhật Bản', 'Hàn Quốc', 'Trung Quốc', 'Thái Lan',
            'Singapore', 'Úc', 'Anh', 'Pháp', 'Đức', 'Khác'];
}

function getCardTypes(): array
{
    return ['visa' => 'Visa', 'mastercard' => 'Mastercard', 'jcb' => 'JCB'];
}

/* ---------- Hàm dùng chung ---------- */

/* Lấy giá trị của một khóa trong mảng dạng chuỗi đã trim; thiếu/không phải số-chuỗi -> '' */
function inputString(array $in, string $key): string
{
    if (!isset($in[$key]) || !is_scalar($in[$key])) {
        return '';
    }
    return trim((string) $in[$key]);
}

/*
 * Chuỗi toàn chữ số -> số nguyên; chuỗi khác (rỗng, có dấu âm, dấu chấm, chữ...) -> null.
 * Chuỗi quá dài được gán 1000000000 để chắc chắn bị coi là "vượt ngưỡng".
 */
function parseDigits(string $s): ?int
{
    if (!preg_match('/^\d+$/', $s)) {
        return null;
    }
    if (strlen(ltrim($s, '0')) > 9) {
        return 1000000000;
    }
    return (int) $s;
}

/* Ngày hợp lệ đúng định dạng Y-m-d và có thật (loại 2026-02-30, 2026-9-5...) */
function isValidDate(string $s): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

function nightsBetween(string $checkIn, string $checkOut): int
{
    return (int) (new DateTime($checkIn))->diff(new DateTime($checkOut))->days;
}

/* Thuật toán Luhn kiểm tra số thẻ (chuỗi chỉ gồm chữ số) */
function luhnCheck(string $digits): bool
{
    $sum = 0;
    $double = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $d = (int) $digits[$i];
        if ($double) {
            $d *= 2;
            if ($d > 9) {
                $d -= 9;
            }
        }
        $sum += $d;
        $double = !$double;
    }
    return $sum % 10 === 0;
}

/* Đầu số thẻ có khớp loại thẻ không (Visa: 4; Mastercard: 51-55, 2221-2720; JCB: 3528-3589) */
function cardMatchesType(string $digits, string $type): bool
{
    $p2 = (int) substr($digits, 0, 2);
    $p4 = (int) substr($digits, 0, 4);
    switch ($type) {
        case 'visa':
            return $digits !== '' && $digits[0] === '4';
        case 'mastercard':
            return ($p2 >= 51 && $p2 <= 55) || ($p4 >= 2221 && $p4 <= 2720);
        case 'jcb':
            return $p4 >= 3528 && $p4 <= 3589;
    }
    return false;
}

/*
 * Kiểm tra một số lượng (số người / số phòng) trong khoảng 1..$max.
 * Trả về [lỗi hoặc null, giá trị số hoặc null].
 */
function validateCount(string $raw, string $name, int $max): array
{
    $lower = mb_strtolower($name);
    if ($raw === '') {
        return ["Vui lòng nhập $lower", null];
    }
    $value = parseDigits($raw);
    if ($value === null) {
        return ["$name phải là số nguyên dương", null];
    }
    if ($value < 1 || $value > $max) {
        return ["$name phải từ 1 đến $max", null];
    }
    return [null, $value];
}

/*
 * Kiểm tra giá (VND): phải là số nguyên (không chữ, không dấu thập phân) và >= $minAllowed.
 * Trả về [lỗi hoặc null, giá trị số hoặc null].
 *  - $oneMessage = false (giá tối thiểu): báo riêng "phải là số nguyên" và "không được nhỏ hơn 0".
 *  - $oneMessage = true  (giá tối đa): mọi trường hợp sai (chữ, thập phân, âm, nhỏ hơn mức tối thiểu)
 *    đều báo chung "<tên> phải là số nguyên lớn hơn hoặc bằng <mức tối thiểu>".
 */
function validatePrice(string $raw, string $name, int $minAllowed, bool $oneMessage = false): array
{
    $notInteger = $oneMessage
        ? "$name phải là số nguyên lớn hơn hoặc bằng $minAllowed"
        : "$name phải là số nguyên";
    $tooSmall = $oneMessage
        ? "$name phải là số nguyên lớn hơn hoặc bằng $minAllowed"
        : ($minAllowed <= 0 ? "$name không được nhỏ hơn 0" : "$name phải lớn hơn hoặc bằng $minAllowed");

    if (!preg_match('/^-?\d+$/', $raw)) {
        return [$notInteger, null];
    }
    if ($raw[0] === '-') {
        return [$tooSmall, null];
    }
    $value = parseDigits($raw);
    if ($value < $minAllowed) {
        return [$tooSmall, null];
    }
    return [null, $value];
}

/* Kiểm tra cặp ngày nhận - trả phòng. $pastMessage: thông báo khi ngày nhận phòng đã qua. */
function validateStayDates(array $in, string $today, string $pastMessage): array
{
    $errors = [];
    $checkIn  = inputString($in, 'check_in');
    $checkOut = inputString($in, 'check_out');
    $inValid  = false;
    $outValid = false;

    if ($checkIn === '') {
        $errors['check_in'] = 'Vui lòng chọn ngày nhận phòng';
    } elseif (!isValidDate($checkIn)) {
        $errors['check_in'] = 'Ngày nhận phòng không hợp lệ';
    } else {
        $inValid = true;
        if ($checkIn < $today) {
            $errors['check_in'] = $pastMessage;
        }
    }

    if ($checkOut === '') {
        $errors['check_out'] = 'Vui lòng chọn ngày trả phòng';
    } elseif (!isValidDate($checkOut)) {
        $errors['check_out'] = 'Ngày trả phòng không hợp lệ';
    } else {
        $outValid = true;
    }

    if ($inValid && $outValid) {
        if ($checkOut <= $checkIn) {
            $errors['check_out'] = 'Ngày trả phòng phải sau ngày nhận phòng';
        } elseif (nightsBetween($checkIn, $checkOut) > LIMIT_MAX_NIGHTS) {
            $errors['check_out'] = 'Thời gian lưu trú tối đa ' . LIMIT_MAX_NIGHTS . ' đêm';
        }
    }
    return $errors;
}

/* ============================================================
 * 1. TÌM KIẾM PHÒNG
 * Trường: destination, check_in, check_out, guests, rooms, min_price, max_price
 * (min_price, max_price không bắt buộc)
 * ============================================================ */
function validateSearch(array $in, ?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $errors = validateStayDates($in, $today, 'Ngày nhận phòng không được trước ngày hôm nay');

    // Điểm đến
    $destination = inputString($in, 'destination');
    if ($destination === '') {
        $errors['destination'] = 'Vui lòng nhập điểm đến';
    } elseif (mb_strlen($destination) > LIMIT_DESTINATION_MAX) {
        $errors['destination'] = 'Điểm đến tối đa ' . LIMIT_DESTINATION_MAX . ' ký tự';
    }

    // Số người, số phòng
    [$guestsError, $guests] = validateCount(inputString($in, 'guests'), 'Số lượng người', LIMIT_MAX_GUESTS);
    if ($guestsError !== null) {
        $errors['guests'] = $guestsError;
    }
    [$roomsError, $rooms] = validateCount(inputString($in, 'rooms'), 'Số lượng phòng', LIMIT_MAX_ROOMS);
    if ($roomsError !== null) {
        $errors['rooms'] = $roomsError;
    }
    if ($guests !== null && $rooms !== null && $rooms > $guests) {
        $errors['rooms'] = 'Số lượng phòng không được lớn hơn số lượng người';
    }

    // Khoảng giá (không bắt buộc)
    $minRaw = inputString($in, 'min_price');
    $maxRaw = inputString($in, 'max_price');
    $min = null;
    $max = null;
    if ($minRaw !== '') {                       // giá tối thiểu: số nguyên >= 0
        [$minError, $min] = validatePrice($minRaw, 'Giá tối thiểu', 0);
        if ($minError !== null) {
            $errors['min_price'] = $minError;
        }
    }
    if ($maxRaw !== '') {                       // giá tối đa: số nguyên >= 1
        [$maxError, $max] = validatePrice($maxRaw, 'Giá tối đa', 1, true);
        if ($maxError !== null) {
            $errors['max_price'] = $maxError;
        }
    }
    if ($min !== null && $max !== null && $min > $max) {
        $errors['min_price'] = 'Giá tối thiểu không được lớn hơn giá tối đa';
    }

    return $errors;
}

/* ============================================================
 * 2. XEM THÔNG TIN PHÒNG
 * Trường: room_id, check_in, check_out, guests
 * Ngày nhận phòng đã qua được hiểu là phiên tìm kiếm hết hạn.
 * (Việc phòng có tồn tại trong CSDL hay không do trang room.php kiểm tra.)
 * ============================================================ */
function validateRoomView(array $in, ?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $errors = validateStayDates($in, $today, 'Phiên tìm kiếm đã hết hạn, vui lòng chọn lại ngày');

    $roomId = inputString($in, 'room_id');
    if ($roomId === '' || !preg_match(ROOM_ID_PATTERN, $roomId)) {
        $errors['room_id'] = 'Mã phòng không hợp lệ';
    }

    [$guestsError] = validateCount(inputString($in, 'guests'), 'Số lượng người', LIMIT_MAX_GUESTS);
    if ($guestsError !== null) {
        $errors['guests'] = $guestsError;
    }

    return $errors;
}

/* ============================================================
 * 3. ĐẶT PHÒNG - thông tin khách
 * Trường: full_name, phone, country, email, special_request (tùy chọn),
 *         arrival_time (tùy chọn, HH:MM)
 * ============================================================ */
function validateBookingInfo(array $in): array
{
    $errors = [];

    // Họ và tên
    $name = inputString($in, 'full_name');
    if ($name === '') {
        $errors['full_name'] = 'Vui lòng nhập họ và tên';
    } elseif (mb_strlen($name) < LIMIT_NAME_MIN || mb_strlen($name) > LIMIT_NAME_MAX) {
        $errors['full_name'] = 'Họ và tên phải từ ' . LIMIT_NAME_MIN . ' đến ' . LIMIT_NAME_MAX . ' ký tự';
    } elseif (!preg_match(NAME_PATTERN, $name)) {
        $errors['full_name'] = 'Họ và tên không hợp lệ';
    }

    // Số điện thoại: bỏ khoảng trắng, dấu chấm, gạch ngang; cho phép dấu + đầu; 9-15 chữ số
    $phone = inputString($in, 'phone');
    if ($phone === '') {
        $errors['phone'] = 'Vui lòng nhập số điện thoại';
    } elseif (!preg_match('/^\+?\d{9,15}$/', preg_replace('/[\s.\-]/', '', $phone))) {
        $errors['phone'] = 'Số điện thoại không hợp lệ';
    }

    // Quốc gia: phải nằm trong danh sách
    $country = inputString($in, 'country');
    if ($country === '') {
        $errors['country'] = 'Vui lòng chọn quốc gia';
    } elseif (!in_array($country, getCountryList(), true)) {
        $errors['country'] = 'Quốc gia không hợp lệ';
    }

    // Email
    $email = inputString($in, 'email');
    if ($email === '') {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (mb_strlen($email) > LIMIT_EMAIL_MAX || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Email không hợp lệ';
    }

    // Yêu cầu đặc biệt (không bắt buộc)
    if (mb_strlen(inputString($in, 'special_request')) > LIMIT_REQUEST_MAX) {
        $errors['special_request'] = 'Yêu cầu đặc biệt tối đa ' . LIMIT_REQUEST_MAX . ' ký tự';
    }

    // Giờ nhận phòng dự kiến (không bắt buộc), 24 giờ HH:MM
    $arrival = inputString($in, 'arrival_time');
    if ($arrival !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $arrival)) {
        $errors['arrival_time'] = 'Giờ nhận phòng phải theo định dạng HH:MM (00:00 - 23:59)';
    }

    return $errors;
}

/* ============================================================
 * 4. THANH TOÁN
 * Trường: card_holder, card_type, card_number, expiry (MM/YY), cvv, amount
 * $expectedAmount: tổng tiền của đơn (VND); nếu truyền vào thì số tiền thanh toán phải bằng đúng.
 * Đây mới là kiểm tra ĐỊNH DẠNG; việc thẻ bị từ chối / không đủ số dư do trang thanh toán xử lý.
 * ============================================================ */
function validatePayment(array $in, ?int $expectedAmount = null, ?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $errors = [];

    // Tên chủ thẻ
    $holder = inputString($in, 'card_holder');
    if ($holder === '') {
        $errors['card_holder'] = 'Vui lòng nhập tên chủ thẻ';
    } elseif (mb_strlen($holder) < LIMIT_NAME_MIN || mb_strlen($holder) > LIMIT_CARD_HOLDER_MAX
              || !preg_match(NAME_PATTERN, $holder)) {
        $errors['card_holder'] = 'Tên chủ thẻ không hợp lệ';
    }

    // Loại thẻ
    $type = inputString($in, 'card_type');
    $typeValid = false;
    if ($type === '') {
        $errors['card_type'] = 'Vui lòng chọn loại thẻ';
    } elseif (!array_key_exists($type, getCardTypes())) {
        $errors['card_type'] = 'Loại thẻ không được hỗ trợ';
    } else {
        $typeValid = true;
    }

    // Số thẻ: bỏ khoảng trắng và gạch ngang; 16 chữ số; qua Luhn; đầu số khớp loại thẻ
    $numberRaw = inputString($in, 'card_number');
    if ($numberRaw === '') {
        $errors['card_number'] = 'Vui lòng nhập số thẻ';
    } else {
        $number = preg_replace('/[\s\-]/', '', $numberRaw);
        if (!preg_match('/^\d{16}$/', $number)) {
            $errors['card_number'] = 'Số thẻ phải gồm 16 chữ số';
        } elseif (!luhnCheck($number)) {
            $errors['card_number'] = 'Số thẻ không hợp lệ';
        } elseif ($typeValid && !cardMatchesType($number, $type)) {
            $errors['card_number'] = 'Số thẻ không khớp với loại thẻ';
        }
    }

    // Ngày hết hạn MM/YY: thẻ còn hiệu lực đến hết tháng ghi trên thẻ
    $expiry = inputString($in, 'expiry');
    if ($expiry === '') {
        $errors['expiry'] = 'Vui lòng nhập ngày hết hạn';
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiry, $m)) {
        $errors['expiry'] = 'Ngày hết hạn phải theo định dạng MM/YY';
    } else {
        $expYear  = 2000 + (int) $m[2];
        $expMonth = (int) $m[1];
        $curYear  = (int) substr($today, 0, 4);
        $curMonth = (int) substr($today, 5, 2);
        if ($expYear < $curYear || ($expYear === $curYear && $expMonth < $curMonth)) {
            $errors['expiry'] = 'Thẻ đã hết hạn';
        }
    }

    // CVV: 3 chữ số
    $cvv = inputString($in, 'cvv');
    if ($cvv === '') {
        $errors['cvv'] = 'Vui lòng nhập CVV';
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = 'CVV phải gồm 3 chữ số';
    }

    // Số tiền thanh toán (VND, số nguyên, không có dấu phân cách)
    $amountRaw = inputString($in, 'amount');
    if ($amountRaw === '') {
        $errors['amount'] = 'Vui lòng nhập số tiền thanh toán';
    } else {
        $amount = parseDigits($amountRaw);
        if ($amount === null) {
            $errors['amount'] = 'Số tiền thanh toán phải là số nguyên dương';
        } elseif ($amount < 1) {
            $errors['amount'] = 'Số tiền thanh toán phải lớn hơn 0';
        } elseif ($expectedAmount !== null && $amount !== $expectedAmount) {
            $errors['amount'] = 'Số tiền thanh toán không khớp với tổng tiền đơn đặt phòng';
        }
    }

    return $errors;
}