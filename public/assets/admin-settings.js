/* 站点设置：SMTP 服务商预设、密码显隐、测试邮件内联反馈 */
(function () {
    'use strict';

    /* ------------------------------------------------ 服务商预设 */
    var preset = document.querySelector('[data-smtp-preset]');
    if (preset) {
        preset.addEventListener('change', function () {
            var parts = String(preset.value || '').split('|');
            if (parts.length < 3) return;
            var host = document.getElementById('smtp_host');
            var port = document.getElementById('smtp_port');
            var secure = document.getElementById('smtp_secure');
            if (host) {
                host.value = parts[0];
                host.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (port) {
                port.value = parts[1];
                port.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (secure) {
                secure.value = parts[2];
                secure.dispatchEvent(new Event('change', { bubbles: true }));
            }
            preset.value = '';
        });
    }

    /* ------------------------------------------------ 密码显隐 */
    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-toggle-pass') || '');
        if (!input) return;
        btn.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.textContent = reveal ? '隐藏' : '显示';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        });
    });

    /* ------------------------------------------------ 测试邮件 */
    var form = document.getElementById('mail-test-form');
    var status = document.getElementById('mail-test-status');
    if (!form || !status) return;

    function setStatus(message, state) {
        status.textContent = message;
        status.className = 'mail-test-status' + (state ? ' is-' + state : '');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        var data = new FormData(form);
        data.set('ajax', '1');
        if (button) button.disabled = true;
        setStatus('发送中，SMTP 连接可能需要几秒…', '');

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.text().then(function (text) {
                var payload = null;
                try { payload = JSON.parse(text); } catch (err) { payload = null; }
                if (!res.ok) throw new Error((payload && payload.message) || '请求失败（' + res.status + '）');
                if (!payload) throw new Error('服务器返回了无法识别的结果。');
                setStatus(payload.message || (payload.ok ? '测试邮件已发送。' : '发送失败。'), payload.ok ? 'ok' : 'error');
            });
        }).catch(function (err) {
            setStatus(err && err.message ? err.message : '发送失败，请稍后重试。', 'error');
        }).then(function () {
            if (button) button.disabled = false;
        });
    });
})();
