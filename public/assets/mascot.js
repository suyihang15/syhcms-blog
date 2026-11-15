/**
 * 看板娘容器交互：折叠 / 关闭 / 恢复 / 滚动淡出 / 状态记忆
 * 形象渲染由 live2d.js 负责，本文件不触碰画布与模型。
 */
(function () {
    'use strict';

    var root = document.getElementById('mascot');
    if (!root) return;

    var toggles = root.querySelectorAll('[data-mascot="toggle"]');
    var closeBtn = root.querySelector('[data-mascot="close"]');
    var restoreBtn = root.querySelector('[data-mascot="restore"]');
    var KEY = { closed: 'mascot.closed', collapsed: 'mascot.collapsed' };

    function read(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }
    function write(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* 隐私模式忽略 */ }
    }

    /* 记忆：关闭优先于折叠；窄屏首次访问默认折叠，避免压住正文 */
    if (read(KEY.closed) === '1') {
        root.classList.add('is-closed');
    }
    var collapsed = read(KEY.collapsed);
    if (collapsed === '1' || (collapsed === null && window.innerWidth < 1680)) {
        root.classList.add('is-collapsed');
    }

    function setCollapsed(on) {
        root.classList.toggle('is-collapsed', on);
        write(KEY.collapsed, on ? '1' : '0');
    }

    Array.prototype.forEach.call(toggles, function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            setCollapsed(!root.classList.contains('is-collapsed'));
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            write(KEY.closed, '1');
            root.classList.add('is-closed');
        });
    }

    if (restoreBtn) {
        restoreBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            write(KEY.closed, '0');
            root.classList.remove('is-closed');
            setCollapsed(false);
        });
    }

    /* 无操作一段时间后稍微透明，少挡阅读；任何交互立刻恢复并重新计时 */
    var IDLE_MS = 8000;
    var idleTimer = 0;

    function wake() {
        window.clearTimeout(idleTimer);
        root.classList.remove('is-idle');
        idleTimer = window.setTimeout(function () {
            if (document.hidden) return;
            root.classList.add('is-idle');
        }, IDLE_MS);
    }

    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (type) {
        document.addEventListener(type, wake, { passive: true });
    });
    root.addEventListener('pointerenter', wake);

    /* 滚动时淡出，减少阅读干扰 */
    var fadeTimer = 0;
    window.addEventListener('scroll', function () {
        root.classList.add('is-scrolling');
        window.clearTimeout(fadeTimer);
        fadeTimer = window.setTimeout(function () {
            root.classList.remove('is-scrolling');
        }, 600);
        wake();
    }, { passive: true });

    wake();
})();
