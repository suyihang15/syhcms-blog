/* 外观设置：实时预览、配色联动、媒体库选图 */
(function () {
    'use strict';

    var form = document.getElementById('appearance-form');
    if (!form) return;

    var media = Array.isArray(window.SYH_MEDIA) ? window.SYH_MEDIA : [];

    var frame = document.getElementById('ap-frame');
    var site = frame ? frame.querySelector('.ap-site') : null;

    var defaults = {
        primary_color: '#0071e3',
        background_color: '#f5f6f8',
        text_color: '#1d2327'
    };
    var colorVars = {
        primary_color: '--ap-primary',
        background_color: '--ap-bg',
        text_color: '--ap-text'
    };

    function byId(id) { return document.getElementById(id); }
    function isHex(value) { return /^#[0-9a-f]{6}$/i.test(value || ''); }

    /* ------------------------------------------------ 实时预览 */
    function setColor(key, value) {
        if (!frame || !colorVars[key]) return;
        if (isHex(value)) frame.style.setProperty(colorVars[key], value);
        else frame.style.removeProperty(colorVars[key]);
    }

    function attachBackground(value) {
        if (!site) return;
        var url = (value || '').trim();
        if (url !== '' && (url.charAt(0) === '/' || /^https?:\/\//i.test(url))) {
            site.style.backgroundImage = 'url("' + url.replace(/"/g, '%22') + '")';
        } else {
            site.style.backgroundImage = '';
        }
    }

    function paint(kind, value) {
        var text = (value || '').trim();
        if (kind === 'name') {
            var nameEl = byId('ap-name');
            var markEl = byId('ap-logo-mark');
            if (nameEl) nameEl.textContent = text || '站点名称';
            if (markEl) markEl.textContent = text !== '' ? (Array.from(text)[0] || '站') : '站';
        } else if (kind === 'desc') {
            var descEl = byId('ap-desc-title');
            if (descEl) descEl.textContent = text || '记录技术与生活';
        } else if (kind === 'banner') {
            var bannerEl = byId('ap-banner');
            if (bannerEl) {
                bannerEl.textContent = text;
                bannerEl.hidden = text === '';
            }
        } else if (kind === 'bgimage') {
            attachBackground(text);
        } else if (kind === 'cover') {
            var coverEl = byId('ap-cover');
            if (coverEl) {
                if (text === '') {
                    coverEl.removeAttribute('src');
                    coverEl.hidden = true;
                } else {
                    coverEl.src = text;
                    coverEl.hidden = false;
                }
                if (coverEl.parentNode) coverEl.parentNode.classList.toggle('ap-cover--empty', text === '');
            }
        } else if (colorVars[kind]) {
            setColor(kind, text);
        }
    }

    form.querySelectorAll('[data-preview]').forEach(function (input) {
        if (input.type === 'hidden') return;
        var kind = input.getAttribute('data-preview');
        var handler = function () { paint(kind, input.value); };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
    });

    /* ------------------------------------------------ 配色联动 */
    form.querySelectorAll('[data-color-for]').forEach(function (picker) {
        var key = picker.getAttribute('data-color-for');
        var text = form.querySelector('[data-color-text="' + key + '"]');
        picker.addEventListener('input', function () {
            if (text) text.value = picker.value;
            paint(key, picker.value);
        });
        if (text) {
            text.addEventListener('input', function () {
                var value = text.value.trim();
                if (isHex(value)) {
                    picker.value = value;
                    paint(key, value);
                } else if (value === '') {
                    paint(key, '');
                }
            });
        }
        setColor(key, text ? text.value : '');
    });

    /* ------------------------------------------------ 媒体库弹层 */
    var modal = byId('ap-media-modal');
    var grid = byId('ap-media-grid');
    var search = byId('ap-media-search');
    var picker = null;

    function renderMedia(keyword) {
        if (!grid) return;
        var q = (keyword || '').trim().toLowerCase();
        grid.innerHTML = '';
        var shown = 0;
        media.forEach(function (item) {
            var label = item.title || item.alt || '';
            if (q
                && label.toLowerCase().indexOf(q) === -1
                && String(item.url).toLowerCase().indexOf(q) === -1) {
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'syh-media-item';
            btn.setAttribute('data-id', String(item.id));

            var img = document.createElement('img');
            img.src = item.url;
            img.alt = item.alt || label;
            img.loading = 'lazy';

            var span = document.createElement('span');
            span.textContent = label || String(item.url).split('/').pop();

            btn.appendChild(img);
            btn.appendChild(span);
            btn.addEventListener('click', function () { chooseMedia(item); });
            grid.appendChild(btn);
            shown++;
        });
        if (shown === 0) {
            var empty = document.createElement('p');
            empty.className = 'hint';
            empty.textContent = media.length ? '没有匹配的图片。' : '媒体库还没有图片，请先上传。';
            grid.appendChild(empty);
        }
    }

    function openPicker(btn) {
        if (!modal) return;
        picker = btn;
        if (search) search.value = '';
        renderMedia('');
        modal.hidden = false;
        if (search) search.focus();
    }

    function closeModal() {
        if (modal) modal.hidden = true;
        picker = null;
    }

    function chooseMedia(item) {
        if (!picker) return;
        var targetId = picker.getAttribute('data-target') || '';
        var mode = picker.getAttribute('data-mode') || 'id';
        var target = byId(targetId);
        if (!target) { closeModal(); return; }

        if (mode === 'url') {
            target.value = item.url;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            target.value = String(item.id);
            if (targetId === 'custom_logo') updateLogo(String(item.id), item.url);
            if (targetId === 'site_icon') updateIcon(String(item.id), item.url);
        }
        closeModal();
    }

    form.querySelectorAll('[data-media-pick]').forEach(function (btn) {
        btn.addEventListener('click', function () { openPicker(btn); });
    });

    /* ------------------------------------------------ Logo / 图标 */
    function updateLogo(id, url) {
        var img = byId('preview-logo');
        var empty = byId('preview-logo-empty');
        var preview = byId('ap-logo');
        var mark = byId('ap-logo-mark');
        var hasImage = !!id && id !== '0';
        if (img) {
            img.hidden = !hasImage;
            if (hasImage && url) img.src = url;
        }
        if (empty) empty.hidden = hasImage;
        if (preview) {
            preview.hidden = !hasImage;
            if (hasImage && url) preview.src = url;
        }
        if (mark) mark.hidden = hasImage;
    }

    function updateIcon(id, url) {
        var img = byId('preview-icon');
        var empty = byId('preview-icon-empty');
        var hasImage = !!id && id !== '0';
        if (img) {
            img.hidden = !hasImage;
            if (hasImage && url) img.src = url;
        }
        if (empty) empty.hidden = hasImage;
    }

    /* ------------------------------------------------ 清除 / 恢复默认 */
    form.querySelectorAll('[data-clear-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-clear-target') || '';
            var target = byId(targetId);
            if (!target) return;

            if (targetId === 'custom_logo') {
                target.value = '0';
                updateLogo('0', '');
                return;
            }
            if (targetId === 'site_icon') {
                target.value = '0';
                updateIcon('0', '');
                return;
            }
            target.value = '';
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            if (colorVars[targetId]) {
                var colorPicker = form.querySelector('[data-color-for="' + targetId + '"]');
                if (colorPicker) colorPicker.value = defaults[targetId];
            }
        });
    });

    /* ------------------------------------------------ 弹层关闭 */
    if (search) {
        search.addEventListener('input', function () { renderMedia(search.value); });
    }
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-close]')) closeModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
    });

    /* 初始同步一次（服务端已渲染，这里只处理规范化后的值） */
    var banner = byId('homepage_banner_text');
    if (banner) paint('banner', banner.value);
    var bgInput = byId('background_image');
    if (bgInput) paint('bgimage', bgInput.value);
})();
