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
            out.textContent = 'Test ediliyor...';
            post('r2mo_test').then(function (d) {
                out.textContent = d.success ? d.data : ('Hata: ' + d.data);
            }).catch(function (err) {
                out.textContent = 'Bağlantı hatası: ' + err;
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
            out.textContent = 'Test ediliyor...';
            window.R2MO_post('r2mo_test').then(function (d) {
                out.textContent = d.success ? d.data : ('Hata: ' + d.data);
            });
        });
    }
    show(1);
})();
