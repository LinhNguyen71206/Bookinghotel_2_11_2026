/* ============================================================
 * js/datepicker.js - Lịch chọn ngày nhận / trả phòng
 * Tự gắn vào 2 ô có id="check_in" và id="check_out" (index.php, room.php).
 *
 * Cố ý GIỮ NGUYÊN 2 ô input dạng text (YYYY-MM-DD, không readonly) để:
 *  - form vẫn gửi GET như cũ, server vẫn validate;
 *  - tester / Selenium vẫn gõ tay được giá trị sai để kiểm thử.
 * ============================================================ */
(function () {
    var inEl  = document.getElementById('check_in');
    var outEl = document.getElementById('check_out');
    if (!inEl || !outEl) return;

    var MONTH_NAMES = null; // dùng dạng "tháng 10 năm 2026"
    var WEEKDAYS = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'];

    /* ---------- Tiện ích ngày (dùng giờ local, tránh lệch múi giờ) ---------- */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }   // m: 0-11
    function parse(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        if (!m) return null;
        var d = new Date(+m[1], +m[2] - 1, +m[3]);
        return fmt(d.getFullYear(), d.getMonth(), d.getDate()) === s ? d : null;
    }
    var now = new Date();
    var TODAY = window.SERVER_TODAY && parse(window.SERVER_TODAY)
        ? window.SERVER_TODAY
        : fmt(now.getFullYear(), now.getMonth(), now.getDate());

    /* ---------- CSS ---------- */
    var css = ''
        + '.dp-pop{position:absolute;z-index:1000;background:#fff;border:1px solid #ccc;border-radius:8px;'
        + 'box-shadow:0 4px 16px rgba(0,0,0,.2);padding:14px 16px;width:350px;font-family:Arial,sans-serif;display:none}'
        + '.dp-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}'
        + '.dp-title{font-weight:bold;font-size:16px}'
        + '.dp-nav{border:0;background:none;font-size:20px;cursor:pointer;color:#0071e3;padding:0 8px}'
        + '.dp-nav:disabled{color:#ccc;cursor:default}'
        + '.dp-grid{display:grid;grid-template-columns:repeat(7,1fr);row-gap:4px}'
        + '.dp-wd{text-align:center;color:#666;font-size:13px;padding:6px 0}'
        + '.dp-day{height:38px;line-height:38px;text-align:center;font-size:15px;cursor:pointer;user-select:none}'
        + '.dp-day.dp-past{color:#aaa;cursor:default}'
        + '.dp-day.dp-today{color:#0071e3;font-weight:bold}'
        + '.dp-day.dp-range{background:#f2f2f2;font-weight:bold}'
        + '.dp-day.dp-start,.dp-day.dp-end{background:#0071e3;color:#fff;font-weight:bold}'
        + '.dp-day.dp-start{border-radius:6px 0 0 6px}'
        + '.dp-day.dp-end{border-radius:0 6px 6px 0}'
        + '.dp-day.dp-start.dp-end{border-radius:6px}'
        + '.dp-day:not(.dp-past):not(.dp-start):not(.dp-end):hover{outline:1px solid #0071e3}'
        + '.dp-hint{margin-top:10px;font-size:12px;color:#777;text-align:center}';
    var style = document.createElement('style');
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);

    /* ---------- Popup ---------- */
    var pop = document.createElement('div');
    pop.className = 'dp-pop';
    pop.id = 'datepicker';
    document.body.appendChild(pop);

    var viewY, viewM;       // tháng đang hiển thị
    var mode = 'in';        // 'in' = lần click tới chọn ngày nhận; 'out' = chọn ngày trả
    var anchor = inEl;      // ô input đang mở lịch

    inEl.setAttribute('autocomplete', 'off');
    outEl.setAttribute('autocomplete', 'off');

    function getStart() { var v = inEl.value.trim();  return parse(v) ? v : ''; }
    function getEnd()   { var v = outEl.value.trim(); return parse(v) ? v : ''; }

    function render() {
        var start = getStart(), end = getEnd();
        var first = new Date(viewY, viewM, 1);
        var offset = (first.getDay() + 6) % 7;                 // Thứ 2 = 0
        var days = new Date(viewY, viewM + 1, 0).getDate();
        var curMonthStart = TODAY.slice(0, 7);
        var canPrev = fmt(viewY, viewM, 1).slice(0, 7) > curMonthStart;

        var h = '<div class="dp-head">'
            + '<button type="button" class="dp-nav" id="dp-prev"' + (canPrev ? '' : ' disabled') + '>&#8249;</button>'
            + '<div class="dp-title" id="dp-title">tháng ' + (viewM + 1) + ' năm ' + viewY + '</div>'
            + '<button type="button" class="dp-nav" id="dp-next">&#8250;</button></div>'
            + '<div class="dp-grid">';
        WEEKDAYS.forEach(function (w) { h += '<div class="dp-wd">' + w + '</div>'; });
        for (var i = 0; i < offset; i++) h += '<div></div>';

        for (var d = 1; d <= days; d++) {
            var s = fmt(viewY, viewM, d);
            var cls = 'dp-day';
            if (s < TODAY) cls += ' dp-past';
            if (s === TODAY) cls += ' dp-today';
            if (start && end && s > start && s < end) cls += ' dp-range';
            if (s === start) cls += ' dp-start';
            if (s === end) cls += ' dp-end';
            h += '<div class="' + cls + '" data-date="' + s + '">' + d + '</div>';
        }
        h += '</div><div class="dp-hint">'
            + (mode === 'in' ? 'Chọn ngày nhận phòng' : 'Chọn ngày trả phòng') + '</div>';
        pop.innerHTML = h;
    }

    function position() {
        var r = anchor.getBoundingClientRect();
        pop.style.left = (r.left + window.pageXOffset) + 'px';
        pop.style.top  = (r.bottom + window.pageYOffset + 4) + 'px';
    }

    function open(from) {
        anchor = from;
        var start = getStart(), end = getEnd();
        mode = (from === outEl && start) ? 'out' : 'in';
        var base = parse(mode === 'out' && end ? end : start) || parse(TODAY);
        viewY = base.getFullYear();
        viewM = base.getMonth();
        render();
        pop.style.display = 'block';
        position();
    }
    function close() { pop.style.display = 'none'; }
    function isOpen() { return pop.style.display === 'block'; }

    function pick(s) {
        var start = getStart();
        if (mode === 'in' || !start || s <= start) {
            inEl.value = s;
            outEl.value = '';
            mode = 'out';
            render();
        } else {
            outEl.value = s;
            close();
        }
    }

    /* ---------- Sự kiện ---------- */
    [inEl, outEl].forEach(function (el) {
        el.addEventListener('focus', function () { open(el); });
        el.addEventListener('click', function () { if (!isOpen() || anchor !== el) open(el); });
        el.addEventListener('input', function () {      // gõ tay -> lịch cập nhật theo
            var p = parse(el.value.trim());
            if (p) { viewY = p.getFullYear(); viewM = p.getMonth(); }
            if (isOpen()) render();
        });
    });

    pop.addEventListener('mousedown', function (e) { e.preventDefault(); }); // không làm mất focus input
    pop.addEventListener('click', function (e) {
        var t = e.target;
        if (t.id === 'dp-prev' && !t.disabled) {
            viewM--; if (viewM < 0) { viewM = 11; viewY--; }
            render();
        } else if (t.id === 'dp-next') {
            viewM++; if (viewM > 11) { viewM = 0; viewY++; }
            render();
        } else if (t.classList.contains('dp-day') && !t.classList.contains('dp-past')) {
            pick(t.getAttribute('data-date'));
        }
    });

    document.addEventListener('mousedown', function (e) {
        if (isOpen() && !pop.contains(e.target) && e.target !== inEl && e.target !== outEl) close();
    });
    document.addEventListener('focusin', function (e) {
        if (isOpen() && !pop.contains(e.target) && e.target !== inEl && e.target !== outEl) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (isOpen()) position(); });
})();