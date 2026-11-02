<?php
/* ============================================================
 * tests/test_validators.php - Kiểm tra nhanh Phần 2 (validators.php)
 * Mở: http://localhost/hotel-booking/tests/test_validators.php
 * Không cần CSDL. "Hôm nay" được cố định là 2026-09-05.
 * ============================================================ */
require_once __DIR__ . '/../includes/validators.php';

header('Content-Type: text/plain; charset=utf-8');

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function report(string $name, bool $ok, string $detail = ''): void
{
    $GLOBALS[$ok ? 'pass' : 'fail']++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($ok ? '' : ' | ' . $detail) . "\n";
}

/* Mong đợi trường $field có đúng thông báo $message (hoặc không có lỗi nếu $message = null) */
function expectError(string $name, array $errors, string $field, ?string $message): void
{
    $actual = $errors[$field] ?? null;
    report($name, $actual === $message,
        'thực tế: ' . json_encode($actual, JSON_UNESCAPED_UNICODE)
        . ' | mong đợi: ' . json_encode($message, JSON_UNESCAPED_UNICODE));
}

/* Mong đợi toàn bộ dữ liệu hợp lệ */
function expectValid(string $name, array $errors): void
{
    report($name, $errors === [], 'lỗi: ' . json_encode($errors, JSON_UNESCAPED_UNICODE));
}

$today = '2026-09-05';

/* ---------------- 1. TÌM KIẾM PHÒNG ---------------- */
echo "== Tìm kiếm phòng ==\n";
$s = ['destination' => 'Hà Nội', 'check_in' => '2026-09-10', 'check_out' => '2026-09-12',
      'guests' => '2', 'rooms' => '1', 'min_price' => '', 'max_price' => ''];

expectValid('Dữ liệu hợp lệ', validateSearch($s, $today));
expectError('Điểm đến trống', validateSearch(array_merge($s, ['destination' => '']), $today), 'destination', 'Vui lòng nhập điểm đến');
expectError('Điểm đến 50 ký tự hợp lệ', validateSearch(array_merge($s, ['destination' => str_repeat('a', 50)]), $today), 'destination', null);
expectError('Điểm đến 51 ký tự', validateSearch(array_merge($s, ['destination' => str_repeat('a', 51)]), $today), 'destination', 'Điểm đến tối đa 50 ký tự');

expectError('Nhận phòng = hôm nay hợp lệ', validateSearch(array_merge($s, ['check_in' => '2026-09-05', 'check_out' => '2026-09-06']), $today), 'check_in', null);
expectError('Nhận phòng hôm qua', validateSearch(array_merge($s, ['check_in' => '2026-09-04']), $today), 'check_in', 'Ngày nhận phòng không được trước ngày hôm nay');
expectError('Nhận phòng trống', validateSearch(array_merge($s, ['check_in' => '']), $today), 'check_in', 'Vui lòng chọn ngày nhận phòng');
expectError('Nhận phòng 2026-02-30', validateSearch(array_merge($s, ['check_in' => '2026-02-30']), $today), 'check_in', 'Ngày nhận phòng không hợp lệ');
expectError('Nhận phòng sai định dạng 10/09/2026', validateSearch(array_merge($s, ['check_in' => '10/09/2026']), $today), 'check_in', 'Ngày nhận phòng không hợp lệ');
expectError('Trả phòng trống', validateSearch(array_merge($s, ['check_out' => '']), $today), 'check_out', 'Vui lòng chọn ngày trả phòng');
expectError('Trả phòng = nhận phòng', validateSearch(array_merge($s, ['check_out' => '2026-09-10']), $today), 'check_out', 'Ngày trả phòng phải sau ngày nhận phòng');
expectError('Trả phòng trước nhận phòng', validateSearch(array_merge($s, ['check_out' => '2026-09-08']), $today), 'check_out', 'Ngày trả phòng phải sau ngày nhận phòng');
expectError('Ở 30 đêm hợp lệ', validateSearch(array_merge($s, ['check_out' => '2026-10-10']), $today), 'check_out', null);
expectError('Ở 31 đêm', validateSearch(array_merge($s, ['check_out' => '2026-10-11']), $today), 'check_out', 'Thời gian lưu trú tối đa 30 đêm');

expectError('Số người = 0', validateSearch(array_merge($s, ['guests' => '0']), $today), 'guests', 'Số lượng người phải từ 1 đến 20');
expectError('Số người = 1 hợp lệ', validateSearch(array_merge($s, ['guests' => '1']), $today), 'guests', null);
expectError('Số người = 20 hợp lệ', validateSearch(array_merge($s, ['guests' => '20']), $today), 'guests', null);
expectError('Số người = 21', validateSearch(array_merge($s, ['guests' => '21']), $today), 'guests', 'Số lượng người phải từ 1 đến 20');
expectError('Số người trống', validateSearch(array_merge($s, ['guests' => '']), $today), 'guests', 'Vui lòng nhập số lượng người');
expectError('Số người = abc', validateSearch(array_merge($s, ['guests' => 'abc']), $today), 'guests', 'Số lượng người phải là số nguyên dương');
expectError('Số người = -1', validateSearch(array_merge($s, ['guests' => '-1']), $today), 'guests', 'Số lượng người phải là số nguyên dương');
expectError('Số người = 2.5', validateSearch(array_merge($s, ['guests' => '2.5']), $today), 'guests', 'Số lượng người phải là số nguyên dương');
expectError('Số người số cực lớn', validateSearch(array_merge($s, ['guests' => '99999999999999999999']), $today), 'guests', 'Số lượng người phải từ 1 đến 20');

expectError('Số phòng = 0', validateSearch(array_merge($s, ['rooms' => '0']), $today), 'rooms', 'Số lượng phòng phải từ 1 đến 5');
expectError('Số phòng = 6', validateSearch(array_merge($s, ['rooms' => '6', 'guests' => '10']), $today), 'rooms', 'Số lượng phòng phải từ 1 đến 5');
expectError('Số phòng = 5, số người = 5 hợp lệ', validateSearch(array_merge($s, ['rooms' => '5', 'guests' => '5']), $today), 'rooms', null);
expectError('Số phòng > số người', validateSearch(array_merge($s, ['rooms' => '3', 'guests' => '2']), $today), 'rooms', 'Số lượng phòng không được lớn hơn số lượng người');
expectError('Số phòng trống', validateSearch(array_merge($s, ['rooms' => '']), $today), 'rooms', 'Vui lòng nhập số lượng phòng');

expectValid('Có khoảng giá hợp lệ', validateSearch(array_merge($s, ['min_price' => '500000', 'max_price' => '2000000']), $today));
expectError('Giá min = giá max hợp lệ', validateSearch(array_merge($s, ['min_price' => '500000', 'max_price' => '500000']), $today), 'min_price', null);
expectError('Giá min = 0 hợp lệ', validateSearch(array_merge($s, ['min_price' => '0']), $today), 'min_price', null);
expectError('Giá min > giá max', validateSearch(array_merge($s, ['min_price' => '500000', 'max_price' => '100000']), $today), 'min_price', 'Giá tối thiểu không được lớn hơn giá tối đa');
expectError('Giá min âm', validateSearch(array_merge($s, ['min_price' => '-5']), $today), 'min_price', 'Giá tối thiểu phải là số nguyên không âm');
expectError('Giá max không phải số', validateSearch(array_merge($s, ['max_price' => 'abc']), $today), 'max_price', 'Giá tối đa phải là số nguyên không âm');

/* ---------------- 2. XEM THÔNG TIN PHÒNG ---------------- */
echo "\n== Xem thông tin phòng ==\n";
$v = ['room_id' => 'R001', 'check_in' => '2026-09-10', 'check_out' => '2026-09-12', 'guests' => '2'];

expectValid('Dữ liệu hợp lệ', validateRoomView($v, $today));
expectError('Mã phòng trống', validateRoomView(array_merge($v, ['room_id' => '']), $today), 'room_id', 'Mã phòng không hợp lệ');
expectError('Mã phòng sai định dạng', validateRoomView(array_merge($v, ['room_id' => 'ABC']), $today), 'room_id', 'Mã phòng không hợp lệ');
expectError('Mã phòng có ký tự lạ', validateRoomView(array_merge($v, ['room_id' => "R001' OR 1=1"]), $today), 'room_id', 'Mã phòng không hợp lệ');
expectError('Ngày nhận đã qua -> phiên hết hạn', validateRoomView(array_merge($v, ['check_in' => '2026-09-01']), $today), 'check_in', 'Phiên tìm kiếm đã hết hạn, vui lòng chọn lại ngày');
expectError('Trả phòng trước nhận phòng', validateRoomView(array_merge($v, ['check_out' => '2026-09-09']), $today), 'check_out', 'Ngày trả phòng phải sau ngày nhận phòng');
expectError('Số người = 0', validateRoomView(array_merge($v, ['guests' => '0']), $today), 'guests', 'Số lượng người phải từ 1 đến 20');

/* ---------------- 3. ĐẶT PHÒNG ---------------- */
echo "\n== Đặt phòng (thông tin khách) ==\n";
$b = ['full_name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'country' => 'Việt Nam',
      'email' => 'a@example.com', 'special_request' => '', 'arrival_time' => ''];

expectValid('Dữ liệu hợp lệ', validateBookingInfo($b));
expectError('Họ tên trống', validateBookingInfo(array_merge($b, ['full_name' => ''])), 'full_name', 'Vui lòng nhập họ và tên');
expectError('Họ tên 1 ký tự', validateBookingInfo(array_merge($b, ['full_name' => 'A'])), 'full_name', 'Họ và tên phải từ 2 đến 100 ký tự');
expectError('Họ tên 2 ký tự hợp lệ', validateBookingInfo(array_merge($b, ['full_name' => 'An'])), 'full_name', null);
expectError('Họ tên 100 ký tự hợp lệ', validateBookingInfo(array_merge($b, ['full_name' => str_repeat('a', 100)])), 'full_name', null);
expectError('Họ tên 101 ký tự', validateBookingInfo(array_merge($b, ['full_name' => str_repeat('a', 101)])), 'full_name', 'Họ và tên phải từ 2 đến 100 ký tự');
expectError('Họ tên chứa số', validateBookingInfo(array_merge($b, ['full_name' => 'Nguyen123'])), 'full_name', 'Họ và tên không hợp lệ');
expectError('Họ tên chứa ký tự đặc biệt', validateBookingInfo(array_merge($b, ['full_name' => 'Nguyen@Van'])), 'full_name', 'Họ và tên không hợp lệ');

expectError('SĐT trống', validateBookingInfo(array_merge($b, ['phone' => ''])), 'phone', 'Vui lòng nhập số điện thoại');
expectError('SĐT 8 chữ số', validateBookingInfo(array_merge($b, ['phone' => '12345678'])), 'phone', 'Số điện thoại không hợp lệ');
expectError('SĐT 9 chữ số hợp lệ', validateBookingInfo(array_merge($b, ['phone' => '123456789'])), 'phone', null);
expectError('SĐT 15 chữ số hợp lệ', validateBookingInfo(array_merge($b, ['phone' => '123456789012345'])), 'phone', null);
expectError('SĐT 16 chữ số', validateBookingInfo(array_merge($b, ['phone' => '1234567890123456'])), 'phone', 'Số điện thoại không hợp lệ');
expectError('SĐT có +84 hợp lệ', validateBookingInfo(array_merge($b, ['phone' => '+84912345678'])), 'phone', null);
expectError('SĐT có khoảng trắng hợp lệ', validateBookingInfo(array_merge($b, ['phone' => '0912 345 678'])), 'phone', null);
expectError('SĐT chứa chữ', validateBookingInfo(array_merge($b, ['phone' => '09123abc78'])), 'phone', 'Số điện thoại không hợp lệ');

expectError('Quốc gia trống', validateBookingInfo(array_merge($b, ['country' => ''])), 'country', 'Vui lòng chọn quốc gia');
expectError('Quốc gia ngoài danh sách', validateBookingInfo(array_merge($b, ['country' => 'Narnia'])), 'country', 'Quốc gia không hợp lệ');

expectError('Email trống', validateBookingInfo(array_merge($b, ['email' => ''])), 'email', 'Vui lòng nhập email');
expectError('Email thiếu @', validateBookingInfo(array_merge($b, ['email' => 'abc.example.com'])), 'email', 'Email không hợp lệ');
expectError('Email thiếu tên miền', validateBookingInfo(array_merge($b, ['email' => 'abc@'])), 'email', 'Email không hợp lệ');
expectError('Email dài hơn 100 ký tự', validateBookingInfo(array_merge($b, ['email' => str_repeat('a', 95) . '@b.com'])), 'email', 'Email không hợp lệ');

expectError('Yêu cầu đặc biệt 500 ký tự hợp lệ', validateBookingInfo(array_merge($b, ['special_request' => str_repeat('a', 500)])), 'special_request', null);
expectError('Yêu cầu đặc biệt 501 ký tự', validateBookingInfo(array_merge($b, ['special_request' => str_repeat('a', 501)])), 'special_request', 'Yêu cầu đặc biệt tối đa 500 ký tự');

expectError('Giờ nhận 00:00 hợp lệ', validateBookingInfo(array_merge($b, ['arrival_time' => '00:00'])), 'arrival_time', null);
expectError('Giờ nhận 23:59 hợp lệ', validateBookingInfo(array_merge($b, ['arrival_time' => '23:59'])), 'arrival_time', null);
expectError('Giờ nhận 24:00', validateBookingInfo(array_merge($b, ['arrival_time' => '24:00'])), 'arrival_time', 'Giờ nhận phòng phải theo định dạng HH:MM (00:00 - 23:59)');
expectError('Giờ nhận 12:60', validateBookingInfo(array_merge($b, ['arrival_time' => '12:60'])), 'arrival_time', 'Giờ nhận phòng phải theo định dạng HH:MM (00:00 - 23:59)');
expectError('Giờ nhận 9:30 (thiếu số 0)', validateBookingInfo(array_merge($b, ['arrival_time' => '9:30'])), 'arrival_time', 'Giờ nhận phòng phải theo định dạng HH:MM (00:00 - 23:59)');

/* ---------------- 4. THANH TOÁN ---------------- */
echo "\n== Thanh toán ==\n";
$p = ['card_holder' => 'NGUYEN VAN A', 'card_type' => 'visa', 'card_number' => '4111111111111111',
      'expiry' => '12/30', 'cvv' => '123', 'amount' => '2400000'];
$expected = 2400000;

expectValid('Dữ liệu hợp lệ', validatePayment($p, $expected, $today));
expectError('Tên chủ thẻ trống', validatePayment(array_merge($p, ['card_holder' => '']), $expected, $today), 'card_holder', 'Vui lòng nhập tên chủ thẻ');
expectError('Tên chủ thẻ 1 ký tự', validatePayment(array_merge($p, ['card_holder' => 'N']), $expected, $today), 'card_holder', 'Tên chủ thẻ không hợp lệ');
expectError('Tên chủ thẻ chứa số', validatePayment(array_merge($p, ['card_holder' => 'NGUYEN 123']), $expected, $today), 'card_holder', 'Tên chủ thẻ không hợp lệ');

expectError('Loại thẻ trống', validatePayment(array_merge($p, ['card_type' => '']), $expected, $today), 'card_type', 'Vui lòng chọn loại thẻ');
expectError('Loại thẻ không hỗ trợ', validatePayment(array_merge($p, ['card_type' => 'amex']), $expected, $today), 'card_type', 'Loại thẻ không được hỗ trợ');

expectError('Số thẻ trống', validatePayment(array_merge($p, ['card_number' => '']), $expected, $today), 'card_number', 'Vui lòng nhập số thẻ');
expectError('Số thẻ có dấu cách hợp lệ', validatePayment(array_merge($p, ['card_number' => '4111 1111 1111 1111']), $expected, $today), 'card_number', null);
expectError('Số thẻ 15 chữ số', validatePayment(array_merge($p, ['card_number' => '411111111111111']), $expected, $today), 'card_number', 'Số thẻ phải gồm 16 chữ số');
expectError('Số thẻ 17 chữ số', validatePayment(array_merge($p, ['card_number' => '41111111111111111']), $expected, $today), 'card_number', 'Số thẻ phải gồm 16 chữ số');
expectError('Số thẻ chứa chữ', validatePayment(array_merge($p, ['card_number' => '4111abcd11111111']), $expected, $today), 'card_number', 'Số thẻ phải gồm 16 chữ số');
expectError('Số thẻ sai Luhn', validatePayment(array_merge($p, ['card_number' => '4111111111111112']), $expected, $today), 'card_number', 'Số thẻ không hợp lệ');
expectError('Số thẻ Mastercard nhưng chọn Visa', validatePayment(array_merge($p, ['card_number' => '5555555555554444']), $expected, $today), 'card_number', 'Số thẻ không khớp với loại thẻ');
expectError('Mastercard đúng loại', validatePayment(array_merge($p, ['card_type' => 'mastercard', 'card_number' => '5555555555554444']), $expected, $today), 'card_number', null);
expectError('JCB đúng loại', validatePayment(array_merge($p, ['card_type' => 'jcb', 'card_number' => '3530111333300000']), $expected, $today), 'card_number', null);

expectError('Hết hạn trống', validatePayment(array_merge($p, ['expiry' => '']), $expected, $today), 'expiry', 'Vui lòng nhập ngày hết hạn');
expectError('Hết hạn đúng tháng hiện tại hợp lệ', validatePayment(array_merge($p, ['expiry' => '09/26']), $expected, $today), 'expiry', null);
expectError('Hết hạn tháng trước', validatePayment(array_merge($p, ['expiry' => '08/26']), $expected, $today), 'expiry', 'Thẻ đã hết hạn');
expectError('Hết hạn năm trước', validatePayment(array_merge($p, ['expiry' => '12/25']), $expected, $today), 'expiry', 'Thẻ đã hết hạn');
expectError('Hết hạn tháng 13', validatePayment(array_merge($p, ['expiry' => '13/30']), $expected, $today), 'expiry', 'Ngày hết hạn phải theo định dạng MM/YY');
expectError('Hết hạn tháng 00', validatePayment(array_merge($p, ['expiry' => '00/30']), $expected, $today), 'expiry', 'Ngày hết hạn phải theo định dạng MM/YY');
expectError('Hết hạn thiếu số 0', validatePayment(array_merge($p, ['expiry' => '1/30']), $expected, $today), 'expiry', 'Ngày hết hạn phải theo định dạng MM/YY');
expectError('Hết hạn dạng MM/YYYY', validatePayment(array_merge($p, ['expiry' => '12/2030']), $expected, $today), 'expiry', 'Ngày hết hạn phải theo định dạng MM/YY');

expectError('CVV trống', validatePayment(array_merge($p, ['cvv' => '']), $expected, $today), 'cvv', 'Vui lòng nhập CVV');
expectError('CVV 2 chữ số', validatePayment(array_merge($p, ['cvv' => '12']), $expected, $today), 'cvv', 'CVV phải gồm 3 chữ số');
expectError('CVV 4 chữ số', validatePayment(array_merge($p, ['cvv' => '1234']), $expected, $today), 'cvv', 'CVV phải gồm 3 chữ số');
expectError('CVV chứa chữ', validatePayment(array_merge($p, ['cvv' => '12a']), $expected, $today), 'cvv', 'CVV phải gồm 3 chữ số');

expectError('Số tiền trống', validatePayment(array_merge($p, ['amount' => '']), $expected, $today), 'amount', 'Vui lòng nhập số tiền thanh toán');
expectError('Số tiền = 0', validatePayment(array_merge($p, ['amount' => '0']), $expected, $today), 'amount', 'Số tiền thanh toán phải lớn hơn 0');
expectError('Số tiền âm', validatePayment(array_merge($p, ['amount' => '-100']), $expected, $today), 'amount', 'Số tiền thanh toán phải là số nguyên dương');
expectError('Số tiền chứa chữ', validatePayment(array_merge($p, ['amount' => 'abc']), $expected, $today), 'amount', 'Số tiền thanh toán phải là số nguyên dương');
expectError('Số tiền không khớp tổng đơn', validatePayment(array_merge($p, ['amount' => '2400001']), $expected, $today), 'amount', 'Số tiền thanh toán không khớp với tổng tiền đơn đặt phòng');
expectError('Không truyền tổng đơn thì bỏ qua đối chiếu', validatePayment(array_merge($p, ['amount' => '2400001']), null, $today), 'amount', null);

echo "\nTổng kết: {$GLOBALS['pass']} PASS, {$GLOBALS['fail']} FAIL\n";