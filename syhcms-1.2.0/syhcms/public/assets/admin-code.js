(function () {
    var area = document.getElementById('code-area');
    if (!area) return;

    var gutter = document.getElementById('code-gutter');
    var form = document.getElementById('code-form');
    var search = document.getElementById('code-search');
    var list = document.getElementById('code-list');
    var noResult = document.getElementById('code-no-result');
    var dirty = false;

    function updateGutter() {
        if (!gutter) return;
        var count = area.value.split('\n').length;
        var html = '';
        for (var i = 1; i <= count; i++) html += i + '\n';
        gutter.textContent = html;
        gutter.scrollTop = area.scrollTop;
    }

    function syncScroll() {
        if (gutter) gutter.scrollTop = area.scrollTop;
    }

    area.addEventListener('input', function () {
        dirty = true;
        updateGutter();
    });
    area.addEventListener('scroll', syncScroll);

    area.addEventListener('keydown', function (event) {
        if (event.key === 'Tab') {
            event.preventDefault();
            var start = area.selectionStart;
            var end = area.selectionEnd;
            var value = area.value;
            var lineStart = value.lastIndexOf('\n', start - 1) + 1;

            if (start !== end && value.slice(start, end).indexOf('\n') !== -1) {
                var block = value.slice(lineStart, end);
                var shifted = event.shiftKey
                    ? block.replace(/^( {1,4}|\t)/gm, '')
                    : block.replace(/^/gm, '    ');
                area.setRangeText(shifted, lineStart, end, 'select');
            } else {
                area.setRangeText('    ', start, end, 'end');
            }
            dirty = true;
            updateGutter();
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            form.submit();
        }
    });

    if (form) {
        form.addEventListener('submit', function () {
            dirty = false;
        });
    }

    if (search && list) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            var visible = 0;
            list.querySelectorAll('.admin-code-group').forEach(function (group) {
                var groupVisible = 0;
                group.querySelectorAll('.admin-code-file').forEach(function (link) {
                    var match = q === '' || (link.getAttribute('data-path') || '').toLowerCase().indexOf(q) !== -1;
                    link.hidden = !match;
                    if (match) groupVisible++;
                });
                group.hidden = q !== '' && groupVisible === 0;
                visible += groupVisible;
            });
            if (noResult) noResult.hidden = visible !== 0 || q === '';
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    updateGutter();
})();
