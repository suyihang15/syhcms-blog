        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('.admin-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.body.classList.toggle('admin-nav-open');
        });
    });

    document.addEventListener('click', function (event) {
        if (!document.body.classList.contains('admin-nav-open')) return;
        var sidebar = document.getElementById('admin-sidebar');
        if (sidebar && !sidebar.contains(event.target)) {
            document.body.classList.remove('admin-nav-open');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.body.classList.remove('admin-nav-open');
        }
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm') || '确定执行此操作？')) {
                event.preventDefault();
            }
        });
    });

    var title = document.getElementById('title');
    var slug = document.getElementById('slug');
    if (title && slug && slug.dataset.auto === '1') {
        var touched = slug.value.trim() !== '';
        slug.addEventListener('input', function () { touched = slug.value.trim() !== ''; });
        title.addEventListener('input', function () {
            if (touched) return;
            var value = title.value.trim().toLowerCase()
                .replace(/[\s\/#?%\\]+/g, '-')
                .replace(/[^\p{L}\p{N}_\-]+/gu, '')
                .replace(/^-+|-+$/g, '');
            slug.value = value;
        });
    }

    var kind = document.getElementById('menu-kind');
    if (kind) {
        var update = function () {
            var value = kind.value;
            document.querySelectorAll('[data-kind-group]').forEach(function (el) {
                var show = el.getAttribute('data-kind-group') === value;
                el.style.display = show ? '' : 'none';
                el.querySelectorAll('select,input').forEach(function (input) { input.disabled = !show; });
            });
        };
        kind.addEventListener('change', update);
        update();
    }

    var cover = document.getElementById('cover_id');
    if (cover) {
        var preview = document.getElementById('cover-preview');
        var refresh = function () {
            var opt = cover.options[cover.selectedIndex];
            var url = opt ? (opt.getAttribute('data-url') || '') : '';
            if (preview) {
                if (url) { preview.src = url; preview.style.display = ''; }
                else { preview.removeAttribute('src'); preview.style.display = 'none'; }
            }
        };
        cover.addEventListener('change', refresh);
    }

    var mediaInput = document.getElementById('media-file');
    if (mediaInput) {
        mediaInput.addEventListener('change', function () {
            var file = mediaInput.files && mediaInput.files[0];
            var box = document.getElementById('media-preview');
            if (!box) return;
            box.innerHTML = '';
            if (!file) return;
            if (/^image\//.test(file.type)) {
                var img = document.createElement('img');
                img.alt = '';
                img.style.cssText = 'max-width:100%;max-height:180px;border-radius:8px;';
                img.src = URL.createObjectURL(file);
                box.appendChild(img);
            } else {
                box.textContent = file.name;
            }
        });
    }
})();
</script>
</body>
</html>
