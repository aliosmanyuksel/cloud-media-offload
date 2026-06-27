(function () {
    function post(action, extra) {
        var body = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(R2MO.nonce);
        if (extra) { body += extra; }
        return fetch(R2MO.ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); });
    }
    window.R2MO_post = post;

    var testBtn = document.getElementById('r2mo-test');
    if (testBtn) {
        testBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var out = document.getElementById('r2mo-test-result');
            out.textContent = R2MO.i18n.testing;
            post('r2mo_test').then(function (d) {
                out.textContent = d.success ? d.data : (R2MO.i18n.error + ' ' + d.data);
            }).catch(function (err) {
                out.textContent = R2MO.i18n.connError + ' ' + err;
            });
        });
    }
})();

(function () {
    var form = document.getElementById('r2mo-wizard-form');
    if (!form) { return; }
    var steps = form.querySelectorAll('.r2mo-wizard-step');
    var dots = document.querySelectorAll('.r2mo-steps span');
    var current = 1;

    function show(n) {
        current = Math.max(1, Math.min(steps.length, n));
        steps.forEach(function (el) {
            el.classList.toggle('active', parseInt(el.getAttribute('data-step'), 10) === current);
        });
        dots.forEach(function (d) {
            d.classList.toggle('on', parseInt(d.getAttribute('data-dot'), 10) <= current);
        });
    }
    form.addEventListener('click', function (e) {
        if (e.target.classList.contains('r2mo-next')) { e.preventDefault(); show(current + 1); }
        if (e.target.classList.contains('r2mo-prev')) { e.preventDefault(); show(current - 1); }
    });

    var wizardTest = document.getElementById('r2mo-wizard-test');
    if (wizardTest) {
        wizardTest.addEventListener('click', function (e) {
            e.preventDefault();
            var out = document.getElementById('r2mo-test-result');
            out.textContent = R2MO.i18n.testing;
            window.R2MO_post('r2mo_test').then(function (d) {
                out.textContent = d.success ? d.data : (R2MO.i18n.error + ' ' + d.data);
            });
        });
    }
    show(1);
})();

(function () {
    var startBtn = document.getElementById('r2mo-start');
    if (!startBtn) { return; }
    var stopBtn = document.getElementById('r2mo-stop');
    var bar = document.getElementById('r2mo-bar');
    var log = document.getElementById('r2mo-log');
    var pendingEl = document.getElementById('r2mo-pending');
    var total = parseInt(startBtn.getAttribute('data-total'), 10) || 0;
    var running = false;

    function addLog(t) { log.textContent += t + '\n'; log.scrollTop = log.scrollHeight; }
    function toggle(on) { running = on; startBtn.disabled = on; stopBtn.disabled = !on; }

    function batch() {
        if (!running) { return; }
        window.R2MO_post('r2mo_migrate_batch').then(function (d) {
            if (!d.success) { addLog(R2MO.i18n.failPrefix + ' ' + d.data); toggle(false); return; }
            (d.data.errors || []).forEach(addLog);
            var pct = total ? Math.round(((total - d.data.remaining) / total) * 100) : 100;
            bar.style.width = pct + '%';
            bar.textContent = pct + '%';
            pendingEl.textContent = d.data.remaining;
            if (d.data.remaining > 0 && d.data.processed > 0) {
                batch();
            } else {
                addLog(R2MO.i18n.completed + ' ' + d.data.remaining);
                toggle(false);
            }
        }).catch(function (e) { addLog(R2MO.i18n.connError + ' ' + e); toggle(false); });
    }

    startBtn.addEventListener('click', function () { toggle(true); addLog(R2MO.i18n.started); batch(); });
    stopBtn.addEventListener('click', function () { toggle(false); addLog(R2MO.i18n.stopped); });
})();
