/* 文章 / 页面编辑器：可视化 + HTML 双模式、媒体库、草稿自动保存 */
(function () {
    'use strict';

    var root = document.getElementById('syh-editor');
    var form = document.getElementById('post-form');
    if (!root || !form) return;

    var visual = document.getElementById('syh-visual');
    var source = document.getElementById('content');
    var tabs = root.querySelectorAll('.syh-tab');
    var countEl = root.querySelector('[data-editor-count]');
    var title = document.getElementById('title');
    var excerpt = document.getElementById('excerpt');
    var excerptCount = document.getElementById('excerpt-count');
    var slug = document.getElementById('slug');
    var slugPreview = document.getElementById('slug-preview');
    var media = Array.isArray(window.SYH_MEDIA) ? window.SYH_MEDIA : [];
    var mode = 'visual';
    var submitIntent = null;
    var savedRange = null;

    /* ------------------------------------------------ 基础工具 */
    function toText(html) {
        var box = document.createElement('div');
        box.innerHTML = html;
        return (box.textContent || '').replace(/\s+/g, '');
    }

    function cleanHtml(html) {
        var box = document.createElement('div');
        box.innerHTML = html;
        box.querySelectorAll('script, style, iframe').forEach(function (n) { n.remove(); });
        return box.innerHTML;
    }

    function syncFromVisual() {
        if (mode === 'visual') source.value = visual.innerHTML;
    }

    function syncToVisual() {
        if (mode === 'html') visual.innerHTML = source.value;
    }

    function currentHtml() {
        return mode === 'visual' ? visual.innerHTML : source.value;
    }

    /* ------------------------------------------------ 字数统计 */
    function refreshCount() {
        if (!countEl) return;
        var n = toText(currentHtml()).length;
        countEl.textContent = n + ' 字';
    }

    function refreshExcerptCount() {
        if (!excerptCount || !excerpt) return;
        excerptCount.textContent = String(excerpt.value.replace(/\s+/g, '').length);
    }

    /* ------------------------------------------------ 模式切换 */
    function setMode(next) {
        if (next === mode) return;
        if (next === 'html') {
            source.value = visual.innerHTML;
            root.classList.add('is-source');
        } else {
            visual.innerHTML = source.value;
            root.classList.remove('is-source');
        }
        mode = next;
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-mode') === next);
        });
        refreshCount();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { setMode(tab.getAttribute('data-mode')); });
    });

    /* ------------------------------------------------ 选区与工具 */
    visual.addEventListener('keyup', refreshCount);
    visual.addEventListener('input', refreshCount);
    source.addEventListener('input', refreshCount);

    function rememberSelection() {
        var sel = window.getSelection();
        if (sel && sel.rangeCount && visual.contains(sel.anchorNode)) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    }

    function restoreSelection() {
        visual.focus();
        if (!savedRange) return;
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
    }

    function exec(cmd, value) {
        if (mode !== 'visual') setMode('visual');
        restoreSelection();
        document.execCommand(cmd, false, value || null);
        visual.focus();
        rememberSelection();
        refreshCount();
    }

    function insertHtmlAtCaret(html) {
        if (mode !== 'visual') setMode('visual');
        restoreSelection();
        document.execCommand('insertHTML', false, html);
        visual.focus();
        rememberSelection();
        refreshCount();
    }

    var toolbar = root.querySelector('.syh-editor-toolbar');
    if (toolbar) {
        toolbar.addEventListener('mousedown', function (e) {
            if (e.target.closest('button, select')) {
                if (e.target.closest('button')) rememberSelection();
            }
        });
        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-cmd]');
            if (!btn) return;
            e.preventDefault();
            var cmd = btn.getAttribute('data-cmd');
            switch (cmd) {
                case 'link':
                    var url = window.prompt('链接地址', 'https://');
                    if (!url) return;
                    if (/^javascript:/i.test(url.trim())) return;
                    exec('createLink', url.trim());
                    break;
                case 'inlineCode':
                    var text = window.getSelection().toString() || '代码';
                    var frag = document.createElement('code');
                    frag.textContent = text;
                    insertHtmlAtCaret(frag.outerHTML);
                    break;
                case 'image':
                    rememberSelection();
                    openMedia('insert');
                    break;
                case 'preview':
                    showPreview();
                    break;
                case 'expand':
                    toggleExpand();
                    break;
                default:
                    exec(cmd);
            }
        });
    }

    var formatSelect = root.querySelector('[data-format]');
    if (formatSelect) {
        formatSelect.addEventListener('change', function () {
            var tag = formatSelect.value;
            if (tag === 'pre') {
                exec('formatBlock', 'pre');
            } else {
                exec('formatBlock', tag);
            }
            if (mode === 'visual') formatSelect.value = 'p';
        });
    }

    /* ------------------------------------------------ 全屏 */
    function toggleExpand() {
        root.classList.toggle('is-expanded');
        document.body.classList.toggle('syh-editor-open', root.classList.contains('is-expanded'));
    }

    /* ------------------------------------------------ 预览 */
    var previewModal = document.getElementById('syh-preview-modal');
    var previewBody = document.getElementById('syh-preview-body');

    function showPreview() {
        if (!previewModal || !previewBody) return;
        var html = cleanHtml(currentHtml());
        previewBody.innerHTML = html || '<p style="color:#86868b">还没有内容。</p>';
        previewModal.hidden = false;
    }

    /* ------------------------------------------------ 媒体库 */
    var mediaModal = document.getElementById('syh-media-modal');
    var mediaGrid = document.getElementById('syh-media-grid');
    var mediaSearch = document.getElementById('syh-media-search');
    var coverSelect = document.getElementById('cover_id');
    var coverPreview = document.getElementById('cover-preview');
    var mediaPurpose = 'insert';

    function renderMedia(keyword) {
        if (!mediaGrid) return;
        var q = (keyword || '').trim().toLowerCase();
        mediaGrid.innerHTML = '';
        var shown = 0;
        media.forEach(function (item) {
            var label = item.title || '';
            if (q && label.toLowerCase().indexOf(q) === -1 && item.url.toLowerCase().indexOf(q) === -1) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'syh-media-item';
            btn.setAttribute('data-id', String(item.id));
            var img = document.createElement('img');
            img.src = item.url;
            img.alt = item.alt || label;
            img.loading = 'lazy';
            var span = document.createElement('span');
            span.textContent = label || item.url.split('/').pop();
            btn.appendChild(img);
            btn.appendChild(span);
            btn.addEventListener('click', function () { pickMedia(item); });
            mediaGrid.appendChild(btn);
            shown++;
        });
        if (!shown) {
            var empty = document.createElement('p');
            empty.className = 'hint';
            empty.textContent = '没有匹配的图片。';
            mediaGrid.appendChild(empty);
        }
    }

    function openMedia(purpose) {
        if (!mediaModal) return;
        mediaPurpose = purpose;
        if (mediaSearch) mediaSearch.value = '';
        renderMedia('');
        mediaModal.hidden = false;
        if (mediaSearch) mediaSearch.focus();
    }

    function pickMedia(item) {
        if (mediaPurpose === 'cover') {
            if (coverSelect) {
                coverSelect.value = String(item.id);
                coverSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closeModal(mediaModal);
            return;
        }
        var safeAlt = String(item.alt || '').replace(/"/g, '&quot;');
        insertHtmlAtCaret('<img src="' + item.url.replace(/"/g, '&quot;') + '" alt="' + safeAlt + '">');
        closeModal(mediaModal);
    }

    function closeModal(modal) {
        if (modal) modal.hidden = true;
    }

    if (mediaSearch) {
        mediaSearch.addEventListener('input', function () { renderMedia(mediaSearch.value); });
    }

    document.querySelectorAll('.syh-modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-close]')) closeModal(modal);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal(mediaModal);
            closeModal(previewModal);
            if (root.classList.contains('is-expanded')) toggleExpand();
        }
    });

    /* 封面 */
    if (coverSelect) {
        coverSelect.addEventListener('change', function () {
            var opt = coverSelect.options[coverSelect.selectedIndex];
            var url = opt ? (opt.getAttribute('data-url') || '') : '';
            if (coverPreview) {
                coverPreview.src = url;
                coverPreview.style.display = url ? '' : 'none';
            }
        });
    }
    var coverPick = document.getElementById('cover-pick');
    if (coverPick) {
        coverPick.addEventListener('click', function () { openMedia('cover'); });
    }

    var previewBtn = document.getElementById('syh-preview-btn');
    if (previewBtn) previewBtn.addEventListener('click', showPreview);

    /* ------------------------------------------------ 固定链接预览 */
    if (slug && slugPreview) {
        slug.addEventListener('input', function () {
            slugPreview.textContent = slug.value.trim() || '别名';
        });
    }

    /* ------------------------------------------------ 标题自动聚焦 */
    if (title && !title.value) title.focus();

    /* ------------------------------------------------ 自动保存 */
    var storeKey = 'syh:draft:' + location.pathname;
    var lastSnapshot = '';
    var ignoredKey = false;

    function snapshot() {
        return JSON.stringify({
            title: title ? title.value : '',
            content: currentHtml(),
            excerpt: excerpt ? excerpt.value : ''
        });
    }

    function saveLocal() {
        try {
            var snap = snapshot();
            if (snap === lastSnapshot) return;
            var data = JSON.parse(snap);
            data.ts = Date.now();
            localStorage.setItem(storeKey, JSON.stringify(data));
            lastSnapshot = snap;
        } catch (err) { /* 隐私模式下不可用，忽略 */ }
    }

    function readLocal() {
        try {
            var raw = localStorage.getItem(storeKey);
            return raw ? JSON.parse(raw) : null;
        } catch (err) { return null; }
    }

    var restoreBar = document.getElementById('syh-restore');
    var draft = readLocal();
    if (draft && restoreBar && !ignoredKey) {
        var serverHtml = (visual.innerHTML || '').trim();
        if ((draft.content || '').trim() !== serverHtml || (draft.title || '') !== (title ? title.value : '')) {
            restoreBar.hidden = false;
            var yes = document.getElementById('syh-restore-yes');
            var no = document.getElementById('syh-restore-no');
            if (yes) yes.addEventListener('click', function () {
                if (title) title.value = draft.title || '';
                if (mode !== 'visual') setMode('visual');
                visual.innerHTML = draft.content || '';
                if (source) source.value = visual.innerHTML;
                if (excerpt) excerpt.value = draft.excerpt || '';
                restoreBar.hidden = true;
                refreshCount();
                refreshExcerptCount();
            });
            if (no) no.addEventListener('click', function () {
                ignoredKey = true;
                restoreBar.hidden = true;
                try { localStorage.removeItem(storeKey); } catch (err) { /* noop */ }
            });
        }
    }
    lastSnapshot = snapshot();

    window.setInterval(saveLocal, 20000);
    window.addEventListener('beforeunload', function () { saveLocal(); });

    /* ------------------------------------------------ 提交 */
    function prepareSubmit() {
        if (mode === 'html') syncToVisual();
        else syncFromVisual();
        if (mode !== 'visual') {
            /* 保证 textarea 与可视化内容一致 */
            source.value = visual.innerHTML;
        }
    }

    form.addEventListener('submit', function () {
        prepareSubmit();
        try { localStorage.removeItem(storeKey); } catch (err) { /* noop */ }
    });

    document.querySelectorAll('[data-save]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var intent = btn.getAttribute('data-save');
            if (intent === 'draft') {
                e.preventDefault();
                var status = document.getElementById('status');
                if (status) status.value = 'draft';
                prepareSubmit();
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        var meta = e.ctrlKey || e.metaKey;
        if (!meta) return;
        if (e.key === 's' || e.key === 'S') {
            e.preventDefault();
            prepareSubmit();
            form.requestSubmit ? form.requestSubmit() : form.submit();
        } else if ((e.key === 'k' || e.key === 'K') && mode === 'visual') {
            e.preventDefault();
            var url = window.prompt('链接地址', 'https://');
            if (url && !/^javascript:/i.test(url.trim())) exec('createLink', url.trim());
        }
    });

    /* HTML 模式 Tab 缩进 */
    if (source) {
        source.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            e.preventDefault();
            var start = source.selectionStart;
            var end = source.selectionEnd;
            source.value = source.value.slice(0, start) + '    ' + source.value.slice(end);
            source.selectionStart = source.selectionEnd = start + 4;
            refreshCount();
        });
    }

    /* 粘进图片地址时自动转图片（纯文本 URL） */
    visual.addEventListener('paste', function (e) {
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (/^https?:\/\/\S+\.(png|jpe?g|gif|webp|svg)(\?\S*)?$/i.test((text || '').trim())) {
            e.preventDefault();
            insertHtmlAtCaret('<img src="' + text.trim() + '" alt="">');
        }
    });

    refreshCount();
    refreshExcerptCount();
})();
