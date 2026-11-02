/* ============================================================
 * js/expirypicker.js - Bảng chọn tháng/năm cho ô "Ngày hết hạn" thẻ
 * Tự gắn vào ô có id="expiry" (payment.php). Giá trị điền vào có dạng MM/YY.
 *
 * Giữ nguyên ô input dạng text (không readonly) để tester / Selenium vẫn gõ
 * tay được giá trị sai (13/30, 1/30, 12/2030...) và server vẫn validate.
 * ============================================================ */
(function () {
    var input = document.getElementById('expiry');
    if (!input) return;

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    /* "Hôm nay" theo giờ server (đồng bộ với validatePayment), dự phòng giờ trình duyệt */
    var t = /^(\d{4})-(\d{2})-\d{2}$/.exec(window.SERVER_TODAY || '');
    var now = new Date();
    var CUR_Y = t ? +t[1] : now.getFullYear();
    var CUR_M = t ? +t[2] : now.getMonth() + 1;   // 1-12
    var MAX_Y = 2099;                              // YY được hiểu là 2000 + YY

    var css = ''
        + '.ep-pop{position:absolute;z-index:1000;background:#fff;border:1px solid #ccc;border-radius:8px;'
        + 'box-shadow:0 4px 16px rgba(0,0,0,.2);padding:14px 16px;width:260px;font-family:Arial,sans-serif;display:none}'
        + '.ep-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}'
        + '.ep-title{font-weight:bold;font-size:16px}'
        + '.ep-nav{border:0;background:none;font-size:20px;cursor:pointer;color:#0071e3;padding:0 8px}'
        + '.ep-nav:disabled{color:#ccc;cursor:default}'
        + '.ep-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}'
        + '.ep-month{height:40px;line-height:40px;text-align:center;font-size:15px;cursor:pointer;border-radius:6px;user-select:none}'
        + '.ep-month.ep-past{color:#aaa;cursor:default}'
        + '.ep-month.ep-current{color:#0071e3;font-weight:bold}'
        + '.ep-month.ep-selected{background:#0071e3;color:#fff;font-weight:bold}'
        + '.ep-month:not(.ep-past):not(.ep-selected):hover{outline:1px solid #0071e3}'
        + '.ep-hint{margin-top:10px;font-size:12px;color:#777;text-align:center}';
    var style = document.createElement('style');
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);

    var pop = document.createElement('div');
    pop.className = 'ep-pop';
    pop.id = 'expirypicker';
    document.body.appendChild(pop);

    input.setAttribute('autocomplete', 'off');

    var viewY = CUR_Y;

    /* Đọc giá trị đang có trong ô; trả về {y, m} hoặc null nếu không đúng MM/YY */
    function readValue() {
        var m = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(input.value.trim());
        return m ? { y: 2000 + (+m[2]), m: +m[1] } : null;
    }

    function render() {
        var sel = readValue();
        var h = '<div class="ep-head">'
            + '<button type="button" class="ep-nav" id="ep-prev"' + (viewY <= CUR_Y ? ' disabled' : '') + '>&#8249;</button>'
            + '<div class="ep-title" id="ep-title">năm ' + viewY + '</div>'
            + '<button type="button" class="ep-nav" id="ep-next"' + (viewY >= MAX_Y ? ' disabled' : '') + '>&#8250;</button></div>'
            + '<div class="ep-grid">';
        for (var m = 1; m <= 12; m++) {
            var cls = 'ep-month';
            var past = viewY < CUR_Y || (viewY === CUR_Y && m < CUR_M);
            if (past) cls += ' ep-past';
            if (viewY === CUR_Y && m === CUR_M) cls += ' ep-current';
            if (sel && sel.y === viewY && sel.m === m) cls += ' ep-selected';
            h += '<div class="' + cls + '" data-m="' + m + '">Tháng ' + m + '</div>';
        }
        h += '</div><div class="ep-hint">Chọn tháng hết hạn của thẻ</div>';
        pop.innerHTML = h;
    }

    function position() {
        var r = input.getBoundingClientRect();
        pop.style.left = (r.left + window.pageXOffset) + 'px';
        pop.style.top  = (r.bottom + window.pageYOffset + 4) + 'px';
    }
    function isOpen() { return pop.style.display === 'block'; }
    function close() { pop.style.display = 'none'; }
    function open() {
        var sel = readValue();
        viewY = sel && sel.y >= CUR_Y ? sel.y : CUR_Y;
        render();
        pop.style.display = 'block';
        position();
    }

    input.addEventListener('focus', open);
    input.addEventListener('click', function () { if (!isOpen()) open(); });
    input.addEventListener('input', function () {      // gõ tay -> bảng cập nhật theo
        var sel = readValue();
        if (sel && sel.y >= CUR_Y && sel.y <= MAX_Y) viewY = sel.y;
        if (isOpen()) render();
    });

    pop.addEventListener('mousedown', function (e) { e.preventDefault(); });
    pop.addEventListener('click', function (e) {
        var el = e.target;
        if (el.id === 'ep-prev' && !el.disabled) {
            viewY--; render();
        } else if (el.id === 'ep-next' && !el.disabled) {
            viewY++; render();
        } else if (el.classList.contains('ep-month') && !el.classList.contains('ep-past')) {
            input.value = pad(+el.getAttribute('data-m')) + '/' + pad(viewY % 100);
            close();
        }
    });

    document.addEventListener('mousedown', function (e) {
        if (isOpen() && !pop.contains(e.target) && e.target !== input) close();
    });
    document.addEventListener('focusin', function (e) {
        if (isOpen() && !pop.contains(e.target) && e.target !== input) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (isOpen()) position(); });
})();