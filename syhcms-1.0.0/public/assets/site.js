/**
 * 苏的小站主题脚本
 * Version: 3.0
 */

(function() {
    'use strict';

    // 滚动事件节流：把同一帧内的多次触发合并成一次，降低滚动时开销。
    function onScroll(handler) {
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                handler();
                ticking = false;
            });
        }, { passive: true });
    }

    // ============================================
    const BackToTop = {
        init() {
            this.button = document.querySelector('.back-to-top');
            if (!this.button) return;

            this.button.addEventListener('click', () => this.scrollToTop());
            onScroll(() => this.toggleVisibility());
        },

        scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        toggleVisibility() {
            if (window.pageYOffset > 300) {
                this.button.classList.add('visible');
            } else {
                this.button.classList.remove('visible');
            }
        }
    };

    // ============================================
    // 阅读进度条
    // ============================================
    const ReadingProgress = {
        init() {
            this.bar = document.querySelector('.reading-progress-bar');
            if (!this.bar) return;

            onScroll(() => this.update());
        },

        update() {
            const doc = document.documentElement;
            const winScroll = window.pageYOffset || doc.scrollTop || 0;
            const height = doc.scrollHeight - doc.clientHeight;
            if (height <= 0) {
                this.bar.style.width = '0%';
                return;
            }
            const scrolled = Math.min(100, Math.max(0, (winScroll / height) * 100));
            this.bar.style.width = scrolled + '%';
        }
    };

    // ============================================
    // 代码复制功能
    // ============================================
    const CodeCopy = {
        init() {
            this.addCopyButtons();
        },

        addCopyButtons() {
            const codeBlocks = document.querySelectorAll('pre code');
            codeBlocks.forEach((block, index) => {
                const pre = block.parentElement;
                const button = document.createElement('button');
                button.className = 'code-copy-btn';
                button.innerHTML = '复制';
                button.setAttribute('data-code', block.textContent);
                button.addEventListener('click', () => this.copyCode(button));
                pre.style.position = 'relative';
                pre.appendChild(button);
            });
        },

        async copyCode(button) {
            const code = button.getAttribute('data-code');
            try {
                await navigator.clipboard.writeText(code);
                const originalText = button.innerHTML;
                button.innerHTML = '已复制';
                button.classList.add('copied');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('copied');
                }, 2000);
            } catch (err) {
                console.error('复制失败:', err);
                button.innerHTML = '失败';
                setTimeout(() => {
                    button.innerHTML = '复制';
                }, 2000);
            }
        }
    };

    // ============================================
    // 文章目录（TOC）
    // ============================================
    const TOC = {
        init() {
            this.container = document.getElementById('toc-container');
            if (!this.container) return;

            this.toggle = this.container.querySelector('.toc-toggle');
            this.list = this.container.querySelector('.toc-list');
            
            if (this.toggle) {
                this.toggle.addEventListener('click', () => this.toggleTOC());
            }

            // 小屏幕上默认收起目录，避免长目录挤占正文。
            if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
                this.list.classList.add('collapsed');
                this.toggle.classList.add('collapsed');
                this.toggle.setAttribute('aria-expanded', 'false');
            }

            this.highlightOnScroll();
            this.smoothScroll();
        },

        toggleTOC() {
            const collapsed = this.list.classList.toggle('collapsed');
            this.toggle.classList.toggle('collapsed', collapsed);
            if (this.toggle.setAttribute) {
                this.toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            }
        },

        highlightOnScroll() {
            const headings = document.querySelectorAll('.entry-content h2[id], .entry-content h3[id], .entry-content h4[id]');
            const tocLinks = this.container.querySelectorAll('.toc-item');

            onScroll(() => {
                let current = '';
                headings.forEach(heading => {
                    const headingTop = heading.offsetTop - 100;
                    if (window.pageYOffset >= headingTop) {
                        current = heading.getAttribute('id');
                    }
                });

                tocLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + current) {
                        link.classList.add('active');
                    }
                });
            });
        },

        smoothScroll() {
            const tocLinks = this.container.querySelectorAll('.toc-item');
            tocLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = link.getAttribute('href').substring(1);
                    const target = document.getElementById(targetId);
                    if (target) {
                        const offsetTop = target.offsetTop - 80;
                        window.scrollTo({
                            top: offsetTop,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        }
    };

    // ============================================
    // 分享按钮 - 复制链接
    // ============================================
    const ShareButtons = {
        init() {
            const copyButtons = document.querySelectorAll('.share-copy');
            copyButtons.forEach(button => {
                button.addEventListener('click', () => this.copyLink(button));
            });
        },

        async copyLink(button) {
            const url = button.getAttribute('data-url');
            try {
                await navigator.clipboard.writeText(url);
                const originalText = button.innerHTML;
                button.innerHTML = '已复制';
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            } catch (err) {
                console.error('复制失败:', err);
            }
        }
    };

    // ============================================
    // 移动端菜单
    // ============================================
    const MobileMenu = {
        init() {
            this.toggle = document.querySelector('.mobile-menu-toggle');
            this.menu = document.querySelector('.site-nav');
            this.closeBtn = document.querySelector('.mobile-menu-close');
            this.overlay = null;
            
            if (!this.toggle || !this.menu) return;

            this.toggle.addEventListener('click', () => {
                if (this.menu.classList.contains('mobile-open')) {
                    this.closeMenu();
                } else {
                    this.openMenu();
                }
            });
            
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', () => this.closeMenu());
            }
            
            // 点击菜单外部关闭
            this.menu.addEventListener('click', (e) => {
                if (e.target === this.menu) {
                    this.closeMenu();
                }
            });
            
            // ESC键关闭
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.menu.classList.contains('mobile-open')) {
                    this.closeMenu();
                }
            });
        },

        openMenu() {
            this.menu.classList.add('mobile-open');
            this.toggle.classList.add('active');
            this.toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            
            // 创建遮罩层
            if (!this.overlay) {
                this.overlay = document.createElement('div');
                this.overlay.className = 'mobile-menu-overlay';
                this.overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.45);
                    z-index: 99;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                `;
                // 遮罩层位于头部（z-index:100）之下，点击可关闭菜单
                this.overlay.addEventListener('click', () => this.closeMenu());
                document.body.appendChild(this.overlay);
            }
            
            // 显示遮罩层
            setTimeout(() => {
                this.overlay.style.opacity = '1';
            }, 10);
        },

        closeMenu() {
            this.menu.classList.remove('mobile-open');
            this.toggle.classList.remove('active');
            this.toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            
            // 隐藏遮罩层
            if (this.overlay) {
                this.overlay.style.opacity = '0';
                setTimeout(() => {
                    if (this.overlay) {
                        this.overlay.remove();
                        this.overlay = null;
                    }
                }, 300);
            }
        }
    };

    // ============================================
    // 明暗模式切换（auto → dark → light → auto）
    // 初始明暗由 <head> 内联脚本按 localStorage 决定，
    // 这里只负责循环切换并把选择写回 localStorage。
    // ============================================
    const ThemeToggle = {
        MODES: ['auto', 'dark', 'light'],

        init() {
            this.buttons = document.querySelectorAll('.theme-toggle');
            if (!this.buttons.length) return;

            this.syncButtons();
            this.buttons.forEach(btn => {
                btn.addEventListener('click', () => this.cycle());
            });
        },

        current() {
            const mode = localStorage.getItem('themeMode');
            return this.MODES.indexOf(mode) >= 0 ? mode : 'auto';
        },

        cycle() {
            const next = this.MODES[(this.MODES.indexOf(this.current()) + 1) % this.MODES.length];
            try {
                localStorage.setItem('themeMode', next);
            } catch (e) {}
            this.apply(next);
            this.syncButtons();
        },

        apply(mode) {
            const el = document.documentElement;
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const dark = mode === 'dark' || (mode === 'auto' && prefersDark);
            el.classList.toggle('dark-mode', dark);
            el.classList.toggle('light-mode', !dark);
            el.setAttribute('data-theme-mode', mode);
        },

        syncButtons() {
            const labels = { auto: '跟随系统', dark: '深色', light: '浅色' };
            const mode = this.current();
            const isDark = document.documentElement.classList.contains('dark-mode');
            this.buttons.forEach(btn => {
                btn.setAttribute('title', '当前：' + labels[mode] + '（点击切换）');
                btn.setAttribute('aria-label', '切换明暗模式，当前：' + labels[mode]);
                btn.setAttribute('aria-pressed', String(isDark));
            });
        }
    };

    // ============================================
    // 字号调节
    // ============================================
    const FontSize = {
        init() {
            this.controls = document.querySelector('.font-size-controls');
            if (!this.controls) return;

            this.decreaseBtn = this.controls.querySelector('.font-size-decrease');
            this.resetBtn = this.controls.querySelector('.font-size-reset');
            this.increaseBtn = this.controls.querySelector('.font-size-increase');
            this.content = document.querySelector('.entry-content');

            if (!this.content) return;

            const saved = parseInt(localStorage.getItem('fontSize'), 10);
            this.currentSize = saved || Math.round(parseFloat(getComputedStyle(this.content).fontSize)) || 16;
            this.applySize();

            this.decreaseBtn.addEventListener('click', () => this.changeSize(-1));
            this.resetBtn.addEventListener('click', () => this.resetSize());
            this.increaseBtn.addEventListener('click', () => this.changeSize(1));
        },

        changeSize(delta) {
            this.currentSize = Math.max(12, Math.min(24, this.currentSize + delta));
            localStorage.setItem('fontSize', this.currentSize);
            this.applySize();
        },

        resetSize() {
            this.currentSize = 16;
            localStorage.setItem('fontSize', this.currentSize);
            this.applySize();
        },

        applySize() {
            this.content.style.fontSize = this.currentSize + 'px';
        }
    };

    // ============================================
    // 阅读模式
    // ============================================
    const ReadingMode = {
        init() {
            this.toggle = document.querySelector('.reading-mode-toggle');
            if (!this.toggle) return;

            this.toggle.addEventListener('click', () => this.toggleMode());
        },

        toggleMode() {
            document.body.classList.toggle('reading-mode');
            const isReadingMode = document.body.classList.contains('reading-mode');
            localStorage.setItem('readingMode', isReadingMode ? '1' : '0');
        }
    };

    // ============================================
    // 图片灯箱
    // ============================================
    const Lightbox = {
        init() {
            this.images = document.querySelectorAll('.entry-content img[data-lightbox]');
            if (this.images.length === 0) return;

            this.images.forEach(img => {
                img.style.cursor = 'pointer';
                img.addEventListener('click', (e) => this.openLightbox(e, img));
            });
        },

        openLightbox(e, img) {
            e.preventDefault();
            
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                cursor: pointer;
            `;

            const fullImg = document.createElement('img');
            fullImg.src = img.src;
            fullImg.style.cssText = `
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
            `;

            overlay.appendChild(fullImg);
            document.body.appendChild(overlay);

            overlay.addEventListener('click', () => {
                overlay.remove();
            });
        }
    };

    // ============================================
    // 社交二维码弹层（QQ / 微信）
    // ============================================
    const SocialContact = {
        popover: null,
        trigger: null,

        init() {
            if (!document.querySelector('.social-item[data-social]')) return;

            this.popover = document.createElement('div');
            this.popover.className = 'social-popover';
            this.popover.setAttribute('role', 'dialog');
            this.popover.setAttribute('aria-label', '联系方式');
            this.popover.hidden = true;
            document.body.appendChild(this.popover);

            document.querySelectorAll('.social-item[data-social]').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (this.trigger === item && !this.popover.hidden) {
                        this.close();
                    } else {
                        this.open(item);
                    }
                });
            });

            document.addEventListener('click', (event) => {
                if (this.popover.hidden) return;
                if (this.popover.contains(event.target)) return;
                this.close();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || this.popover.hidden) return;
                event.preventDefault();
                this.close(true);
            });

            window.addEventListener('resize', () => this.close());
            window.addEventListener('scroll', () => {
                if (!this.trigger || this.popover.hidden) return;
                const rect = this.trigger.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) {
                    this.close();
                } else {
                    this.position();
                }
            }, true);
        },

        open(trigger) {
            const image = trigger.getAttribute('data-image') || '';
            const caption = trigger.getAttribute('data-caption') || '';
            if (!image) return;

            if (this.trigger) {
                this.trigger.setAttribute('aria-expanded', 'false');
            }

            this.popover.innerHTML = '';

            if (caption) {
                const title = document.createElement('p');
                title.className = 'social-popover-title';
                title.textContent = caption;
                this.popover.appendChild(title);
            }

            const img = document.createElement('img');
            img.className = 'social-popover-qr';
            img.src = image;
            img.alt = caption || '二维码';
            img.loading = 'lazy';
            this.popover.appendChild(img);

            this.trigger = trigger;
            trigger.setAttribute('aria-expanded', 'true');
            this.popover.hidden = false;
            this.position();
        },

        position() {
            if (!this.trigger || this.popover.hidden) return;

            const rect = this.trigger.getBoundingClientRect();
            const gap = 8;
            const margin = 12;
            const width = this.popover.offsetWidth;
            const height = this.popover.offsetHeight;

            let left = rect.left + rect.width / 2 - width / 2;
            left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

            let top = rect.bottom + gap;
            if (top + height > window.innerHeight - margin) {
                top = rect.top - height - gap;
            }
            top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));

            this.popover.style.left = left + 'px';
            this.popover.style.top = top + 'px';
        },

        close(returnFocus = false) {
            if (!this.popover || this.popover.hidden) return;
            const trigger = this.trigger;
            this.popover.hidden = true;
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
                if (returnFocus) {
                    trigger.focus({ preventScroll: true });
                }
            }
            this.trigger = null;
        }
    };

    // ============================================
    // 发布热力图：悬停 / 点击格子查看当天文章
    // ============================================
    const Heatmap = {
        init() {
            this.figure = document.querySelector('.post-heatmap');
            if (!this.figure) return;

            this.cells = Array.prototype.slice.call(
                this.figure.querySelectorAll('a.heat-cell[data-heat-date]')
            );
            if (!this.cells.length) return;

            // 默认滚到最右边，让「最近」先出现在视野里
            this.scroller = this.figure.querySelector('[data-heatmap-scroll]');
            if (this.scroller) {
                this.scroller.scrollLeft = this.scroller.scrollWidth;
            }

            this.tip = document.createElement('div');
            this.tip.className = 'heatmap-tip';
            this.tip.setAttribute('role', 'dialog');
            this.tip.setAttribute('aria-label', '当天发布记录');
            this.tip.hidden = true;
            document.body.appendChild(this.tip);

            this.active = null;
            this.hideTimer = null;
            // 有精确指针（鼠标）时，点击格子仍然可以进入当天归档页。
            this.canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

            this.cells.forEach((cell) => {
                cell.addEventListener('mouseenter', () => this.show(cell));
                cell.addEventListener('focus', () => this.show(cell));
                cell.addEventListener('mouseleave', () => this.hideLater());
                cell.addEventListener('blur', () => this.hideLater());
                cell.addEventListener('click', (event) => {
                    if (this.canHover) return;
                    event.preventDefault();
                    this.show(cell);
                    this.position(cell);
                });
                cell.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault();
                    this.show(cell);
                    const first = this.tip.querySelector('a');
                    if (first) first.focus({ preventScroll: true });
                });
            });

            this.tip.addEventListener('mouseenter', () => this.cancelHide());
            this.tip.addEventListener('mouseleave', () => this.hideLater());
            // 键盘进入浮层里的文章链接时，别因为格子失焦就把浮层关掉
            this.tip.addEventListener('focusin', () => this.cancelHide());
            this.tip.addEventListener('focusout', (event) => {
                if (this.tip.contains(event.relatedTarget)) return;
                this.hideLater();
            });

            document.addEventListener('click', (event) => {
                if (this.tip.hidden) return;
                if (this.tip.contains(event.target)) return;
                if (event.target.closest && event.target.closest('a.heat-cell')) return;
                this.hide();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') this.hide(true);
            });

            window.addEventListener('scroll', () => this.hide(), { passive: true });
            window.addEventListener('resize', () => {
                if (!this.tip.hidden && this.active) this.position(this.active);
            });
        },

        build(cell) {
            const date = cell.dataset.heatDate || '';
            const count = Number(cell.dataset.heatCount || 0);
            let posts = [];
            try {
                posts = JSON.parse(cell.dataset.heatPosts || '[]');
            } catch (error) {
                posts = [];
            }

            this.tip.textContent = '';

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'heatmap-tip-close';
            closeBtn.setAttribute('aria-label', '关闭');
            closeBtn.textContent = '×';
            closeBtn.addEventListener('click', () => this.hide(true));
            this.tip.appendChild(closeBtn);

            const title = document.createElement('p');
            title.className = 'heatmap-tip-title';
            title.textContent = this.formatDate(date);
            const countLabel = document.createElement('span');
            countLabel.className = 'heatmap-tip-count';
            countLabel.textContent = count + ' 篇文章';
            title.appendChild(countLabel);
            this.tip.appendChild(title);

            if (posts.length) {
                const list = document.createElement('ul');
                list.className = 'heatmap-tip-list';
                posts.forEach((post) => {
                    if (!post || !post.url || !post.title) return;
                    const item = document.createElement('li');
                    if (post.time) {
                        const time = document.createElement('span');
                        time.className = 'heatmap-tip-time';
                        time.textContent = post.time;
                        item.appendChild(time);
                    }
                    const link = document.createElement('a');
                    link.href = post.url;
                    link.textContent = post.title;
                    item.appendChild(link);
                    list.appendChild(item);
                });
                this.tip.appendChild(list);
            }

            const dayLink = document.createElement('a');
            dayLink.className = 'heatmap-tip-all';
            dayLink.href = cell.getAttribute('href') || '#';
            dayLink.textContent = '查看当天全部 ' + count + ' 篇 →';
            this.tip.appendChild(dayLink);
        },

        formatDate(value) {
            const parts = String(value).split('-');
            if (parts.length !== 3) return value;
            return Number(parts[0]) + ' 年 ' + Number(parts[1]) + ' 月 ' + Number(parts[2]) + ' 日';
        },

        show(cell) {
            this.cancelHide();
            this.active = cell;
            this.build(cell);
            this.tip.hidden = false;
            this.cells.forEach((item) => item.setAttribute('aria-expanded', item === cell ? 'true' : 'false'));
            this.position(cell);
        },

        position(cell) {
            const rect = cell.getBoundingClientRect();
            const gap = 8;
            const margin = 12;
            const width = this.tip.offsetWidth;
            const height = this.tip.offsetHeight;

            let left = rect.left + rect.width / 2 - width / 2;
            left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

            let top = rect.bottom + gap;
            if (top + height > window.innerHeight - margin) {
                top = rect.top - height - gap;
            }
            top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));

            this.tip.style.left = left + 'px';
            this.tip.style.top = top + 'px';
        },

        hideLater() {
            this.cancelHide();
            // 焦点已经在浮层内时（键盘用户正在点文章），不要自动关闭
            if (this.tip && !this.tip.hidden && this.tip.contains(document.activeElement)) return;
            this.hideTimer = window.setTimeout(() => this.hide(), 140);
        },

        cancelHide() {
            if (this.hideTimer) {
                window.clearTimeout(this.hideTimer);
                this.hideTimer = null;
            }
        },

        hide(returnFocus) {
            if (!this.tip || this.tip.hidden) return;
            this.cancelHide();
            const cell = this.active;
            // 焦点还在浮层里（例如点了关闭按钮）时要把焦点送回格子，避免焦点丢在隐藏元素上
            const hadFocus = this.tip.contains(document.activeElement);
            this.tip.hidden = true;
            if (cell) {
                cell.setAttribute('aria-expanded', 'false');
                if (returnFocus || hadFocus) cell.focus({ preventScroll: true });
            }
            this.active = null;
        }
    };

    // ============================================
    // 站点稳定运行时间（每秒刷新）
    // ============================================
    const SiteUptime = {
        init() {
            this.el = document.querySelector('[data-site-uptime]');
            if (!this.el) return;

            const start = Number(this.el.dataset.start);
            if (!start) return;

            this.units = {
                d: this.el.querySelector('[data-unit="d"]'),
                h: this.el.querySelector('[data-unit="h"]'),
                m: this.el.querySelector('[data-unit="m"]'),
                s: this.el.querySelector('[data-unit="s"]')
            };

            this.tick(start);
            setInterval(() => this.tick(start), 1000);
        },

        tick(start) {
            let rest = Math.max(0, Math.floor((Date.now() - start) / 1000));
            const days = Math.floor(rest / 86400);
            rest %= 86400;
            const hours = Math.floor(rest / 3600);
            rest %= 3600;
            const minutes = Math.floor(rest / 60);
            const seconds = rest % 60;

            this.set('d', days);
            this.set('h', hours, 2);
            this.set('m', minutes, 2);
            this.set('s', seconds, 2);
        },

        set(unit, value, pad = 0) {
            const node = this.units[unit];
            if (node) {
                node.textContent = String(value).padStart(pad, '0');
            }
        }
    };

    // ============================================
    // 浏览量 / 来源上报
    // 页面缓存插件不会执行 PHP，所以这里用浏览器端上报，
    // 只发送文章 ID 和首次进入的 referrer。
    // ============================================
    const Tracker = {
        init() {
            const config = window.syhStats;
            if (!config || !config.hitEndpoint) return;
            if (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl) return;

            const payload = {};

            if (config.postId && this.markPost(config.postId)) {
                payload.id = config.postId;
            }

            if (!this.sourceSent()) {
                payload.ref = document.referrer || '';
                this.markSource();
            }

            if (!payload.id && !Object.prototype.hasOwnProperty.call(payload, 'ref')) return;
            this.send(config.hitEndpoint, payload);
        },

        todayKey() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },

        markPost(id) {
            const today = this.todayKey();
            try {
                const seen = JSON.parse(localStorage.getItem('syh_seen') || '{}');
                if (seen[id] === today) return false;

                // 只保留当天的记录，避免 localStorage 无限增长
                const next = {};
                Object.keys(seen).forEach((key) => {
                    if (seen[key] === today) next[key] = today;
                });
                next[id] = today;
                localStorage.setItem('syh_seen', JSON.stringify(next));
            } catch (err) {
                // 隐私模式下 localStorage 不可用时照常上报
            }
            return true;
        },

        sourceSent() {
            try {
                return sessionStorage.getItem('syh_src') === '1';
            } catch (err) {
                return false;
            }
        },

        markSource() {
            try {
                sessionStorage.setItem('syh_src', '1');
            } catch (err) {
                // 忽略
            }
        },

        send(endpoint, payload) {
            const body = JSON.stringify(payload);

            if (navigator.sendBeacon) {
                const blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(endpoint, blob)) return;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                credentials: 'omit',
                keepalive: true
            }).catch(() => {});
        }
    };

    // ============================================
    // 统计页
    // ============================================
    const Stats = {
        init() {
            this.root = document.querySelector('[data-stats-page]');
            if (!this.root) return;

            const config = window.syhStats || {};
            if (!config.statsEndpoint) {
                this.fail();
                return;
            }

            fetch(config.statsEndpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'omit'
            })
                .then((res) => (res.ok ? res.json() : Promise.reject(new Error('bad status'))))
                .then((data) => this.render(data))
                .catch(() => this.fail());
        },

        fmt(value) {
            return Number(value || 0).toLocaleString('zh-CN');
        },

        el(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        },

        formatDate(iso, withYear) {
            const parts = String(iso || '').split('-');
            if (parts.length < 3) return String(iso || '');
            const month = Number(parts[1]);
            const day = Number(parts[2]);
            return withYear ? `${parts[0]} 年 ${month} 月 ${day} 日` : `${month} 月 ${day} 日`;
        },

        render(data) {
            if (!this.root) return;
            this.root.removeAttribute('aria-busy');

            const cards = {
                total_views: data.total_views,
                today_views: data.today_views,
                week_views: data.week_views,
                avg_views: data.avg_views
            };
            Object.keys(cards).forEach((key) => {
                const node = this.root.querySelector('[data-stat="' + key + '"]');
                if (node) node.textContent = this.fmt(cards[key]);
            });

            const since = this.root.querySelector('[data-stat="since"]');
            if (since && data.since) {
                since.textContent = `数据自 ${this.formatDate(data.since, true)}起累积。`;
            }

            this.renderTrend(data.trend || []);
            this.renderDaily(data.trend || []);
            this.renderTop(data.top || []);
            this.renderSources(data.sources || {});
            this.renderRefs(data.refs || []);
            this.renderCats(data.cats || []);
            this.renderFacts(data);
        },

        renderTrend(trend) {
            const box = this.root.querySelector('[data-stats-trend]');
            const summary = this.root.querySelector('[data-stats-trend-summary]');
            if (!box) return;

            box.textContent = '';
            const total = trend.reduce((sum, item) => sum + Number(item.views || 0), 0);

            if (!total) {
                box.appendChild(this.el('p', 'stats-empty', '还没有浏览记录，等第一位访客。'));
                if (summary) summary.textContent = '';
                return;
            }

            const max = Math.max(...trend.map((item) => Number(item.views || 0)), 1);

            trend.forEach((item, index) => {
                const col = this.el('div', 'stats-trend-col');
                const views = Number(item.views || 0);

                const bar = this.el('span', 'stats-trend-bar' + (views ? '' : ' is-zero'));
                const height = views ? Math.max(4, Math.round((views / max) * 100)) : 2;
                bar.style.height = height + '%';
                bar.title = `${this.formatDate(item.date)} · ${this.fmt(views)} 次访问`;
                col.appendChild(bar);

                if (views) {
                    const value = this.el('span', 'stats-trend-value', this.fmt(views));
                    value.style.bottom = `calc(${height}% + 4px)`;
                    col.appendChild(value);
                }

                if (index % 5 === 0 || index === trend.length - 1) {
                    col.appendChild(this.el('span', 'stats-trend-label', item.label));
                }

                box.appendChild(col);
            });

            if (summary) {
                summary.textContent = `30 天共 ${this.fmt(total)} 次访问，日均约 ${this.fmt(Math.round(total / 30))} 次。`;
            }
            box.setAttribute('aria-label', `近 30 天每日访问量柱状图，共 ${this.fmt(total)} 次访问`);
        },

        renderDaily(trend) {
            const box = this.root.querySelector('[data-stats-daily]');
            if (!box) return;

            box.textContent = '';
            const total = trend.reduce((sum, item) => sum + Number(item.views || 0), 0);
            if (!trend.length || !total) {
                box.appendChild(this.el('p', 'stats-empty', '还没有每日数据。'));
                return;
            }

            const table = this.el('table', 'stats-daily-table-el');
            table.appendChild(this.el('caption', 'screen-reader-text', '近 30 天每日访问量'));

            const thead = this.el('thead');
            const headRow = this.el('tr');
            headRow.appendChild(this.el('th', '', '日期'));
            headRow.appendChild(this.el('th', '', '访问量'));
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = this.el('tbody');
            trend.slice().reverse().forEach((item) => {
                const views = Number(item.views || 0);
                const row = this.el('tr');
                const dateCell = this.el('th', '', this.formatDate(item.date, true));
                dateCell.scope = 'row';
                row.appendChild(dateCell);
                row.appendChild(this.el('td', '', this.fmt(views) + ' 次'));
                tbody.appendChild(row);
            });
            table.appendChild(tbody);

            const tfoot = this.el('tfoot');
            const totalRow = this.el('tr');
            const totalLabel = this.el('th', '', '合计');
            totalLabel.scope = 'row';
            totalRow.appendChild(totalLabel);
            totalRow.appendChild(this.el('td', '', this.fmt(total) + ' 次'));
            tfoot.appendChild(totalRow);
            table.appendChild(tfoot);

            box.appendChild(table);
        },

        renderTop(items) {
            const list = this.root.querySelector('[data-stats-top]');
            if (!list) return;

            list.textContent = '';
            if (!items.length) {
                list.appendChild(this.el('li', 'stats-empty', '还没有文章浏览数据。'));
                return;
            }

            const max = Math.max(...items.map((item) => Number(item.views || 0)), 1);

            items.forEach((item, index) => {
                const li = this.el('li', 'stats-top-item');

                li.appendChild(this.el('span', 'stats-top-rank', String(index + 1)));

                const head = this.el('span', 'stats-top-head');
                const link = this.el('a', 'stats-top-title', item.title);
                link.href = item.url;
                head.appendChild(link);
                if (item.category) head.appendChild(this.el('span', 'stats-top-cat', item.category));
                li.appendChild(head);

                li.appendChild(this.el('span', 'stats-top-views', this.fmt(item.views) + ' 次'));

                const track = this.el('span', 'stats-top-bar');
                const fill = this.el('span');
                fill.style.width = Math.round((Number(item.views || 0) / max) * 100) + '%';
                track.appendChild(fill);
                li.appendChild(track);

                list.appendChild(li);
            });
        },

        renderSources(sources) {
            const box = this.root.querySelector('[data-stats-sources]');
            if (!box) return;

            const rows = [
                { key: 'direct', name: '直接访问' },
                { key: 'search', name: '搜索引擎' },
                { key: 'social', name: '社交平台' },
                { key: 'referral', name: '其他外链' }
            ];
            const total = rows.reduce((sum, row) => sum + Number(sources[row.key] || 0), 0);

            box.textContent = '';
            if (!total) {
                box.appendChild(this.el('p', 'stats-empty', '还没有来源数据。'));
                return;
            }

            const bar = this.el('div', 'stats-source-bar');
            rows.forEach((row) => {
                const value = Number(sources[row.key] || 0);
                if (!value) return;
                const seg = this.el('span', 'stats-source-seg is-' + row.key);
                seg.style.width = (value / total) * 100 + '%';
                seg.title = `${row.name} ${Math.round((value / total) * 100)}%`;
                bar.appendChild(seg);
            });
            box.appendChild(bar);

            const legend = this.el('ul', 'stats-source-legend');
            rows.forEach((row) => {
                const value = Number(sources[row.key] || 0);
                const li = this.el('li');

                li.appendChild(this.el('span', 'stats-source-dot is-' + row.key));
                li.appendChild(this.el('span', 'stats-source-name', row.name));
                li.appendChild(this.el('span', 'stats-source-value', this.fmt(value) + ' · ' + Math.round((value / total) * 100) + '%'));

                legend.appendChild(li);
            });
            box.appendChild(legend);
        },

        renderRefs(refs) {
            const list = this.root.querySelector('[data-stats-refs]');
            if (!list) return;

            list.textContent = '';
            if (!refs.length) {
                list.appendChild(this.el('li', 'stats-empty', '暂无外链来源。'));
                return;
            }

            refs.forEach((ref) => {
                const li = this.el('li', 'stats-ref-item');
                li.appendChild(this.el('span', 'stats-ref-host', ref.host));
                li.appendChild(this.el('span', 'stats-ref-views', this.fmt(ref.views) + ' 次'));
                list.appendChild(li);
            });
        },

        renderCats(cats) {
            const box = this.root.querySelector('[data-stats-cats]');
            if (!box) return;

            box.textContent = '';
            if (!cats.length) {
                box.appendChild(this.el('p', 'stats-empty', '还没有分类数据。'));
                return;
            }

            const max = Math.max(...cats.map((cat) => Number(cat.views || 0)), 1);

            cats.forEach((cat) => {
                const row = this.el('div', 'stats-bar-row');

                const head = this.el('div', 'stats-bar-head');
                const link = this.el('a', '', cat.name);
                link.href = cat.url;
                head.appendChild(link);
                head.appendChild(this.el('span', '', this.fmt(cat.views) + ' 次'));
                row.appendChild(head);

                const track = this.el('div', 'stats-bar-track');
                const fill = this.el('span', 'stats-bar-fill');
                fill.style.width = Math.round((Number(cat.views || 0) / max) * 100) + '%';
                track.appendChild(fill);
                row.appendChild(track);

                box.appendChild(row);
            });
        },

        renderFacts(data) {
            const facts = {
                posts: this.fmt(data.posts) + ' 篇',
                comments: this.fmt(data.comments) + ' 条',
                taxonomies: this.fmt(data.categories) + ' / ' + this.fmt(data.tags),
                days_running: this.fmt(data.days_running) + ' 天',
                month_posts: this.fmt(data.month_posts) + ' 篇',
                first_post: data.first_post ? this.formatDate(data.first_post, true) : '—'
            };

            Object.keys(facts).forEach((key) => {
                const node = this.root.querySelector('[data-fact="' + key + '"]');
                if (node) node.textContent = facts[key];
            });
        },

        fail() {
            if (!this.root) return;
            this.root.removeAttribute('aria-busy');
            this.root.querySelectorAll('[data-stats-trend], [data-stats-daily], [data-stats-top], [data-stats-sources], [data-stats-cats], [data-stats-refs]').forEach((box) => {
                box.textContent = '';
                const tag = box.tagName === 'OL' || box.tagName === 'UL' ? 'li' : 'p';
                box.appendChild(this.el(tag, 'stats-empty', '统计数据暂时加载失败，刷新试试。'));
            });
        }
    };

    // ============================================
    // 初始化所有模块
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        BackToTop.init();
        ReadingProgress.init();
        CodeCopy.init();
        TOC.init();
        ShareButtons.init();
        MobileMenu.init();
        ThemeToggle.init();
        FontSize.init();
        ReadingMode.init();
        Lightbox.init();
        SocialContact.init();
        Heatmap.init();
        SiteUptime.init();
        Tracker.init();
        Stats.init();

        // 平滑滚动到锚点
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length <= 1) return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const offsetTop = target.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });

    // 页面加载完成后隐藏加载动画
    window.addEventListener('load', () => {
        document.body.classList.add('loaded');
    });

})();
