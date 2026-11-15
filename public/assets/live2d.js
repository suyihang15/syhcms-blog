/* 看板娘 Live2D 渲染层：PIXI 6 + pixi-live2d-display(Cubism 4)
 * 尺寸档位由 #mascot 的 data-size 交给 CSS 控制，这里只负责渲染、自适应与台词。
 * 以下情况静默退出并保留模板里的占位图：手机端禁用、省流/减少动态、无 WebGL、加载失败。
 */
(function () {
    'use strict';

    var mascot = document.getElementById('mascot');
    if (!mascot) return;

    var root = mascot.querySelector('[data-live2d-root]');
    if (!root) return;

    var modelUrl = root.getAttribute('data-model') || '';
    var libUrl = root.getAttribute('data-lib') || '/assets/live2d/lib';
    var bubble = mascot.querySelector('.mascot-bubble');
    var bubbleOn = mascot.getAttribute('data-bubble') === '1' && !!bubble;
    var mobileOff = mascot.getAttribute('data-mobile') === '0';
    var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;

    var greeting = (mascot.getAttribute('data-greeting') || '').trim() || '欢迎来到我主人的博客网站～';
    var customLines = (mascot.getAttribute('data-lines') || '').split(/\r?\n/)
        .map(function (line) { return line.trim(); })
        .filter(function (line) { return line !== ''; });
    var LINES = customLines.length ? customLines : [
        '写博客辛苦啦，喝口水吧～',
        '这个页面是我在守着哦。',
        '慢慢看，不着急。',
        '有问题可以留言给我主人。',
        '今天也要好好生活呀。'
    ];
    var GESTURES = ['RaiseHand', 'Grass', 'PointUp'];
    /* 每个动作组在 model3.json 里是「切换出来 / 切回默认」两段，
     * 必须按 打开 -> 保持 -> 收回 的顺序播完，只播第一段会一直停在前一个姿势。 */
    var GESTURE_TIMING = {
        RaiseHand: { open: 1150, hold: 900, close: 1050 },
        Grass: { open: 1250, hold: 1300, close: 1150 },
        PointUp: { open: 1250, hold: 1100, close: 1250 }
    };

    /* 形态切换：模型自带的三套体型表情，点击按钮循环切换 */
    var FORMS = ['默认体型', '体型1', '体型2'];
    var FORM_KEY = 'mascot.form';
    var formIndex = 0;

    var app = null;
    var model = null;
    var booted = false;
    var ready = false;
    var bubbleTimer = 0;
    var gestureTimer = 0;
    var lineIndex = 0;
    var gestureIndex = 0;
    var gestureBusy = false;
    var gestureToken = 0;
    var coreModel = null;
    var noseDupIndex = -1;
    var swayParams = [];
    var swayClock = 0;
    var swayLast = 0;
    var lastPointer = null;
    var headParams = null;
    var headNow = { x: 0, y: 0 };
    var headTarget = { x: 0, y: 0 };

    function isMobile() {
        return !!(mobileQuery && mobileQuery.matches);
    }

    function say(text, hold) {
        if (!bubbleOn || !text) return;
        bubble.textContent = text;
        bubble.classList.add('is-on');
        window.clearTimeout(bubbleTimer);
        bubbleTimer = window.setTimeout(function () {
            bubble.classList.remove('is-on');
        }, hold || 3200);
    }

    function readForm() {
        try {
            var raw = localStorage.getItem(FORM_KEY);
            var n = parseInt(raw, 10);
            if (isFinite(n) && n >= 0 && n < FORMS.length) return n;
        } catch (e) { /* 隐私模式忽略 */ }
        return 0;
    }

    function writeForm(index) {
        try { localStorage.setItem(FORM_KEY, String(index)); } catch (e) { /* 隐私模式忽略 */ }
    }

    /* 形态 = 模型自带的三套体型表情，名字取自 model3.json，调用失败就留在当前形态 */
    function applyForm(index, silent) {
        formIndex = ((index % FORMS.length) + FORMS.length) % FORMS.length;
        var name = FORMS[formIndex];

        if (model && ready) {
            try {
                var result = model.expression(name);
                if (result && typeof result.catch === 'function') {
                    result.catch(function () { /* 模型未定义该表情时忽略 */ });
                }
            } catch (e) { /* 忽略 */ }
        }

        writeForm(formIndex);
        var btn = mascot.querySelector('[data-mascot="form"]');
        if (btn) {
            btn.setAttribute('title', '切换形态（当前：' + name + '）');
            btn.setAttribute('aria-label', '切换看板娘形态，当前' + name);
        }
        if (!silent) say('换成了' + name + '～');
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var el = document.createElement('script');
            el.src = src;
            el.async = false;
            el.onload = function () { resolve(); };
            el.onerror = function () { reject(new Error('无法加载 ' + src)); };
            document.head.appendChild(el);
        });
    }

    function hasWebGL() {
        try {
            var canvas = document.createElement('canvas');
            return !!(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
        } catch (e) {
            return false;
        }
    }

    function skip() {
        if (!modelUrl) return true;
        if (mobileOff && isMobile()) return true;
        var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn && (conn.saveData || /(^|-)2g$/.test(conn.effectiveType || ''))) return true;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-data: reduce)').matches) return true;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return true;
        return !hasWebGL();
    }

    /* Cubism 的模型画布是「宽 1 : 高 2.05」这种竖长比例，
     * 必须按画布尺寸等比缩放，不能用固定宽高猜，否则会出现只露出半截的情况。 */
    function modelSize() {
        if (!model) return null;
        var w = model.internalModel && model.internalModel.width;
        var h = model.internalModel && model.internalModel.height;
        if (!(w > 0) || !(h > 0)) {
            w = model.width;
            h = model.height;
        }
        if (!(w > 0) || !(h > 0)) return null;
        return { w: w, h: h };
    }

    /* 只看头部：模型是 1 : 2.05 的全身像，这里只取头部放大铺满容器，身体裁掉。
     * 数值来自轮廓实测（停动作后逐行测量）：头顶在模型高 0.104 处，下巴约 0.331，
     * 肩线从 0.339 起变宽。取景框定为 0.100 ~ 0.322，只看得到头、下巴略裁，
     * 肩线落在框外，不会再露出身体。 */
    var HEAD_VIEW = { cx: 0.5, cy: 0.211, w: 0.365, h: 0.222 };
    var fitState = null;

    function fit() {
        if (!app || !model || !ready) return;
        var w = root.clientWidth;
        var h = root.clientHeight;
        if (!w || !h) return;

        app.renderer.resize(w, h);

        var size = modelSize();
        if (!size) return;

        var scale = Math.min(w / (size.w * HEAD_VIEW.w), h / (size.h * HEAD_VIEW.h));
        if (!isFinite(scale) || scale <= 0) return;

        var baseX = w / 2 - size.w * HEAD_VIEW.cx * scale;
        var baseY = h / 2 - size.h * HEAD_VIEW.cy * scale;

        /* 转头/位移的支点取在取景框底边中部（下巴附近），
         * 绕这里转才像歪头看，而不是整块图平移。 */
        fitState = {
            baseX: baseX,
            baseY: baseY,
            scale: scale,
            qx: size.w * HEAD_VIEW.cx,
            qy: size.h * (HEAD_VIEW.cy + HEAD_VIEW.h / 2),
            w: w,
            h: h
        };

        try { model.anchor.set(0, 0); } catch (e) { /* Container 没有 anchor，忽略 */ }
        model.pivot.set(fitState.qx, fitState.qy);
        model.scale.set(scale);
        model.x = baseX + fitState.qx * scale;
        model.y = baseY + fitState.qy * scale;
        model.rotation = 0;
    }

    /* 这个模型的 ParamAngleX/Y/Z 没有绑到头部网格（实测设到 ±30 画面只动眼球），
     * 所以「头跟着鼠标」用整体轻微旋转 + 位移来做：支点在下巴，
     * 向左看就是头往左歪一点点并往左挪一点，配合眼球已经能读成转头。 */
    function applyPose() {
        if (!model || !fitState) return;
        var rot = headNow.x * 0.055;                /* ±3.1° */
        var tx = headNow.x * fitState.w * 0.03;
        var ty = headNow.y * fitState.h * 0.035;
        model.rotation = rot;
        model.x = fitState.baseX + fitState.qx * fitState.scale + tx;
        model.y = fitState.baseY + fitState.qy * fitState.scale + ty;
    }

    function playMotion(group, index) {
        if (!model || !ready) return false;
        try {
            var result = model.motion(group, index, 2);
            if (result && typeof result.catch === 'function') {
                result.catch(function () { /* 模型未定义该动作组时忽略 */ });
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    /* 播一整套动作：打开 -> 保持 -> 收回。收回播完才允许下一次，
     * 避免两个动作叠在同一条手臂上导致姿势卡死。 */
    function playGesture(group, onDone) {
        var timing = GESTURE_TIMING[group] || { open: 1200, hold: 900, close: 1200 };
        gestureToken++;
        var token = gestureToken;
        gestureBusy = true;

        if (!playMotion(group, 0)) {
            gestureBusy = false;
            if (onDone) onDone();
            return;
        }

        window.setTimeout(function () {
            if (token !== gestureToken || !ready) return;
            playMotion(group, 1);
            window.setTimeout(function () {
                if (token !== gestureToken) return;
                gestureBusy = false;
                if (onDone) onDone();
            }, timing.close + 120);
        }, timing.open + timing.hold);
    }

    function nextGesture() {
        if (gestureBusy) return;
        var group = GESTURES[gestureIndex % GESTURES.length];
        gestureIndex++;
        playGesture(group);
    }

    function scheduleGesture() {
        window.clearTimeout(gestureTimer);
        gestureTimer = window.setTimeout(function () {
            if (!document.hidden && ready) {
                nextGesture();
            }
            scheduleGesture();
        }, 16000 + Math.random() * 12000);
    }

    /* 模型里 ArtMesh246 与 ArtMesh682 是两块几何、UV 完全相同的鼻子网格，
     * 同一像素被画两遍，鼻头就比其它部位暗一截、边缘发闷。
     * 固定隐藏后画的那一块，只由另一块正常绘制。 */
    function prepareCore() {
        try {
            coreModel = model.internalModel.coreModel;
            var ids = model.internalModel.getDrawableIDs();
            var idx = ids.indexOf('ArtMesh682');
            if (idx < 0) idx = ids.indexOf('ArtMesh246');
            noseDupIndex = idx;
        } catch (e) {
            coreModel = null;
            return;
        }
        if (!coreModel) return;

        swayParams = [];
        /* 待机动作只带动了脸部和汗滴，身体几乎不动，看起来像定格；
         * 这里在动作层之上叠一点很轻的重心摆动和呼吸。 */
        addSway('ParamBodyAngleX', 0.06, 2.2, 0.9, 0);
        addSway('ParamBodyAngleZ', 0.04, 1.4, 0.55, 0.6);
        addSway('ParamBodyAngleY', 0.03, 1.0, 0.42, 2.1);
        addSway('ParamBreath', 0.18, 0.5, 1.5, 0.2);

        headParams = {
            ax: headParam('ParamAngleX'),
            ay: headParam('ParamAngleY'),
            az: headParam('ParamAngleZ'),
            ex: headParam('ParamEyeBallX'),
            ey: headParam('ParamEyeBallY')
        };
        headNow = { x: 0, y: 0 };
        headTarget = { x: 0, y: 0 };
    }

    function headParam(id) {
        try {
            var index = coreModel.getParameterIndex(id);
            if (index < 0) return null;
            return {
                index: index,
                min: coreModel.getParameterMinimumValue(index),
                max: coreModel.getParameterMaximumValue(index)
            };
        } catch (e) {
            return null;
        }
    }

    function setHead(param, value) {
        if (!param) return;
        if (value < param.min) value = param.min;
        if (value > param.max) value = param.max;
        try { coreModel.setParameterValueByIndex(param.index, value); } catch (e) { /* 忽略 */ }
    }

    function addSway(id, ratio, cap, speed, phase) {
        try {
            var index = coreModel.getParameterIndex(id);
            if (index < 0) return;
            var min = coreModel.getParameterMinimumValue(index);
            var max = coreModel.getParameterMaximumValue(index);
            var amp = Math.min(cap, (max - min) * ratio);
            if (!(amp > 0)) return;
            swayParams.push({
                index: index,
                base: coreModel.getParameterDefaultValue(index),
                amp: amp,
                min: min,
                max: max,
                speed: speed,
                phase: phase
            });
        } catch (e) { /* 参数不存在就跳过 */ }
    }

    /* 动作/表情/物理都跑完之后再叠加微动，并强制隐藏重复的鼻子网格 */
    function polish() {
        if (!coreModel) return;

        var now = (window.performance && window.performance.now) ? window.performance.now() : Date.now();
        var dt = swayLast ? (now - swayLast) / 1000 : 0;
        swayLast = now;
        if (!(dt > 0)) dt = 0;
        if (dt > 0.2) dt = 0.2;
        swayClock += dt;

        for (var i = 0; i < swayParams.length; i++) {
            var p = swayParams[i];
            var v = p.base + Math.sin(swayClock * p.speed + p.phase) * p.amp;
            if (v < p.min) v = p.min;
            if (v > p.max) v = p.max;
            try { coreModel.setParameterValueByIndex(p.index, v); } catch (e) { /* 忽略 */ }
        }

        /* 头跟着鼠标：必须在这里直接写角度参数。
         * 库自带的 focus 用 addParameterValueById 加完角度后紧接着 loadParameters()
         * 会把参数还原，我们再 update 一次就等于把转角抹掉（实测 ParamAngleX 恒为 0）。 */
        if (headParams) {
            var k = dt > 0 ? 1 - Math.exp(-dt * 6) : 1;
            headNow.x += (headTarget.x - headNow.x) * k;
            headNow.y += (headTarget.y - headNow.y) * k;
            setHead(headParams.ax, 30 * headNow.x);
            /* 屏幕坐标向下为正，ParamAngleY 正为抬头，这里取反 */
            setHead(headParams.ay, -30 * headNow.y);
            setHead(headParams.az, -6 * headNow.x * headNow.y);
            setHead(headParams.ex, 0.7 * headNow.x);
            setHead(headParams.ey, -0.7 * headNow.y);
            applyPose();
        }

        try { coreModel.update(); } catch (e) { /* 忽略 */ }
        /* 必须放在 update 之后：更新会用模型默认值覆盖绘制透明度 */
        if (noseDupIndex >= 0) {
            try { coreModel._model.drawables.opacities[noseDupIndex] = 0; } catch (e) { /* 忽略 */ }
        }
    }

    function bind() {
        var canvas = app.view;

        var formBtn = mascot.querySelector('[data-mascot="form"]');
        if (formBtn) {
            formBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                applyForm(formIndex + 1);
            });
        }

        canvas.addEventListener('pointerdown', function (event) {
            var hit = null;
            try {
                var rect = canvas.getBoundingClientRect();
                hit = model.hitTest(event.clientX - rect.left, event.clientY - rect.top);
            } catch (e) { /* 命中测试失败不影响台词 */ }
            if (hit && hit.length) {
                say('摸头好舒服～');
                if (!gestureBusy) playGesture('RaiseHand');
                return;
            }
            say(LINES[lineIndex % LINES.length]);
            lineIndex++;
            nextGesture();
        });

        /* 头跟着鼠标走：整页移动都跟随，而不是只有指针压在画布上才转 */
        document.addEventListener('pointermove', follow, { passive: true });
        window.addEventListener('mouseout', function (event) {
            if (event.relatedTarget) return;
            aim(0, 0);
        });
        window.addEventListener('scroll', function () {
            if (lastPointer) follow(lastPointer);
        }, { passive: true });

        window.addEventListener('resize', fit);
        window.addEventListener('orientationchange', function () {
            window.setTimeout(fit, 260);
        });
        if (window.ResizeObserver) {
            var pending = 0;
            new ResizeObserver(function () {
                window.clearTimeout(pending);
                pending = window.setTimeout(fit, 60);
            }).observe(root);
        }

        if (window.IntersectionObserver) {
            new IntersectionObserver(function (entries) {
                if (!app) return;
                if (entries[0].isIntersecting) {
                    app.ticker.start();
                    fit();
                } else {
                    app.ticker.stop();
                }
            }, { threshold: 0.01 }).observe(root);
        }

        document.addEventListener('visibilitychange', function () {
            if (!app) return;
            if (document.hidden) {
                app.ticker.stop();
            } else {
                app.ticker.start();
            }
        });

        scheduleGesture();
    }

    /* 头跟着鼠标：按整个视口归一化，指针离屏幕中心越远，头偏得越多；
     * 换算到 -1..1 后交给 polish() 平滑写入角度参数。 */
    function follow(event) {
        if (!ready) return;
        lastPointer = { clientX: event.clientX, clientY: event.clientY };
        var vw = window.innerWidth || 1;
        var vh = window.innerHeight || 1;
        aim((event.clientX / vw) * 2 - 1, (event.clientY / vh) * 2 - 1);
    }

    function aim(nx, ny) {
        if (nx < -1) nx = -1;
        if (nx > 1) nx = 1;
        if (ny < -1) ny = -1;
        if (ny > 1) ny = 1;
        headTarget.x = nx;
        headTarget.y = ny;
    }

    function init() {
        if (!window.PIXI || !window.PIXI.live2d) throw new Error('Live2D 运行时不可用');

        var canvas = document.createElement('canvas');
        canvas.className = 'mascot-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        root.appendChild(canvas);

        app = new window.PIXI.Application({
            view: canvas,
            transparent: true,
            backgroundAlpha: 0,
            antialias: true,
            autoDensity: true,
            autoStart: true,
            resolution: Math.min(window.devicePixelRatio || 1, 2),
            width: root.clientWidth || 1,
            height: root.clientHeight || 1
        });

        return window.PIXI.live2d.Live2DModel.from(modelUrl, { autoInteract: false }).then(function (loaded) {
            model = loaded;
            app.stage.addChild(model);

            ready = true;
            root.classList.remove('is-loading');
            root.classList.add('is-ready');

            prepareCore();
            /* 库内部是按函数引用注册更新的，改写 model.update 不会被执行；
             * 挂到同一个 PIXI 共享 ticker 上（模型先注册，这里后注册），
             * 就能稳定跑在模型更新之后、渲染之前。 */
            if (window.PIXI && window.PIXI.Ticker && window.PIXI.Ticker.shared) {
                window.PIXI.Ticker.shared.add(polish);
            } else {
                app.ticker.add(polish);
            }

            fit();
            window.requestAnimationFrame(fit);
            window.setTimeout(fit, 320);
            window.setTimeout(fit, 1200);

            bind();

            /* 恢复上次选的形态；没存过或表情不存在时保持模型默认体型 */
            formIndex = readForm();
            if (formIndex > 0) applyForm(formIndex, true);

            if (bubbleOn) {
                window.setTimeout(function () { say(greeting, 5200); }, 420);
            }
        });
    }

    function boot() {
        if (booted) return;
        booted = true;
        root.classList.add('is-loading');

        loadScript(libUrl + '/pixi.min.js')
            .then(function () { return loadScript(libUrl + '/live2dcubismcore.min.js'); })
            .then(function () { return loadScript(libUrl + '/cubism4.min.js'); })
            .then(init)
            .catch(function (error) {
                root.classList.remove('is-loading');
                root.classList.add('is-failed');
                if (window.console && console.warn) {
                    console.warn('[live2d]', error && error.message ? error.message : error);
                }
            });
    }

    if (skip()) {
        root.classList.add('is-static');
        return;
    }

    /* 模型 + 贴图超过 20MB，首屏自动下载会占满公网带宽，
     * 导致首页和其它静态资源一起变慢变卡。
     * 改为点击看板娘后才开始加载；首次唤醒后记住偏好，
     * 之后进入站点再空闲预加载（此时模型通常已在 HTTP 缓存里）。 */
    var WAKE_KEY = 'syh_live2d_waked';
    var wakedBefore = false;
    try { wakedBefore = window.localStorage.getItem(WAKE_KEY) === '1'; } catch (e) { wakedBefore = false; }

    function wake() {
        if (booted) return;
        if (root.classList.contains('is-closed')) return;
        root.classList.remove('is-idle');
        try { window.localStorage.setItem(WAKE_KEY, '1'); } catch (e) { /* 隐私模式忽略 */ }
        boot();
    }

    if (wakedBefore) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(wake, { timeout: 4000 });
        } else {
            window.setTimeout(wake, 1500);
        }
    } else {
        root.classList.add('is-idle');
        root.setAttribute('role', 'button');
        root.setAttribute('tabindex', '0');
        root.setAttribute('aria-label', '点击唤醒看板娘');
        root.addEventListener('pointerdown', wake);
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                wake();
            }
        });
    }

    if (mobileQuery && mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', function () {
            if (!ready && mobileOff) return;
            fit();
        });
    }
})();
