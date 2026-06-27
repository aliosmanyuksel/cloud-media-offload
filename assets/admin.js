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
