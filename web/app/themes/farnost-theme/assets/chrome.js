/**
 * Header chrome — lupa search expand/collapse + dismiss mimoriadneho oznamu.
 */
(function () {
    'use strict';

    function initSearch(root) {
        var trigger = root.querySelector('.farnost-search-trigger');
        var field = root.querySelector('.farnost-search-field');
        if (!trigger || !field) {
            return;
        }
        var open = function () {
            root.classList.add('is-open');
            field.removeAttribute('aria-hidden');
            field.removeAttribute('tabindex');
            setTimeout(function () { field.focus(); }, 60);
        };
        var close = function () {
            if (field.value.trim() !== '') {
                return;
            }
            root.classList.remove('is-open');
            field.setAttribute('aria-hidden', 'true');
            field.setAttribute('tabindex', '-1');
        };
        trigger.addEventListener('click', function (e) {
            if (root.classList.contains('is-open')) {
                if (field.value.trim() === '') {
                    e.preventDefault();
                    close();
                }
                return;
            }
            e.preventDefault();
            open();
        });
        field.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                field.value = '';
                close();
            }
        });
        document.addEventListener('mousedown', function (e) {
            if (!root.contains(e.target)) {
                close();
            }
        });
    }

    function initAlertDismiss() {
        var bar = document.querySelector('.farnost-alert');
        if (!bar) {
            return;
        }
        var close = bar.querySelector('.farnost-alert-close');
        if (!close) {
            return;
        }
        // Server (Banner block) skontroluje dismiss cookie pred renderom,
        // tým je banner v DOM-e iba ak má byť viditeľný — žiadny FOUC.
        // JS rieši len klik na close: skryje + uloží cookie pre ďalšie loads.
        var id = bar.getAttribute('data-banner-id') || '';
        close.addEventListener('click', function () {
            bar.style.display = 'none';
            if (id) {
                try {
                    var d = new Date();
                    d.setTime(d.getTime() + 30 * 24 * 60 * 60 * 1000);
                    document.cookie = 'farnost_banner_dismissed=' + encodeURIComponent(id) +
                        '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
                } catch (e) {}
            }
        });
    }

    function initNavToggle() {
        var toggle = document.querySelector('.site-nav-toggle');
        var list = document.querySelector('.site-nav-list');
        if (!toggle || !list) {
            return;
        }
        var setOpen = function (open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute(
                'aria-label',
                open ? 'Zavrieť menu' : 'Otvoriť menu'
            );
            list.classList.toggle('is-open', open);
        };
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            setOpen(open);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    /**
     * Twin-sticky sidebar — sidebar follows scroll: pri scroll dole spodok
     * doháňa vp bottom, pri scroll hore vrch doháňa vp top. Pre tall
     * sidebar (vyšší než viewport).
     *
     * Stav:
     *   offset — posun sidebar-u v page-coords (positive = sidebar je
     *            nižšie než jeho natural position)
     *   wrapper poskytuje "rail" — sidebar má position: relative s top: offset
     *
     * Logika v update():
     *   - sidebarTopAbs = wrapper.offsetTop + offset
     *   - sidebarBotAbs = sidebarTopAbs + sh
     *   - vpTopAbs = scrollY + headerOffset
     *   - vpBotAbs = scrollY + vh
     *   - Ak smer dole a sidebarBotAbs < vpBotAbs → offset = vpBotAbs - sh - wrapper.offsetTop
     *   - Ak smer hore a sidebarTopAbs > vpTopAbs → offset = vpTopAbs - wrapper.offsetTop
     *   - Inak offset zostáva (sidebar "floats" v page-coords)
     *   - Clamp offset do [0, wrapper.offsetHeight - sh].
     */
    function initStickySidebar() {
        var sidebar = document.querySelector('.farnost-sidebar');
        if (!sidebar) {
            return;
        }
        // Rail je main container (drží aj feed) — dlhší než sidebar.
        var wrapper = sidebar.closest('main') || sidebar.parentElement;
        if (!wrapper) {
            return;
        }
        var HEADER_OFFSET = 24;
        var lastY = window.scrollY;
        var offset = 0;
        // Cache-ujeme tieto v page-coords aby update() nevolal
        // getBoundingClientRect() na každý frame (triggeruje layout).
        var naturalTopAbs = 0;
        var wrapperBottomAbs = 0;
        var sidebarHeight = 0;
        var ticking = false;

        var measure = function () {
            var prevTransform = sidebar.style.transform;
            sidebar.style.transform = '';
            var sRect = sidebar.getBoundingClientRect();
            naturalTopAbs = window.scrollY + sRect.top;
            sidebarHeight = sidebar.offsetHeight;
            var wRect = wrapper.getBoundingClientRect();
            wrapperBottomAbs = window.scrollY + wRect.bottom;
            sidebar.style.transform = prevTransform;
        };

        var update = function () {
            ticking = false;
            var y = window.scrollY;
            var dir = y > lastY ? 'down' : (y < lastY ? 'up' : null);
            lastY = y;

            var sh = sidebarHeight;
            var vh = window.innerHeight;
            var maxOffset = Math.max(0, wrapperBottomAbs - naturalTopAbs - sh);
            var vpTopAbs = y + HEADER_OFFSET;
            var vpBotAbs = y + vh;

            if (sh + HEADER_OFFSET <= vh) {
                offset = Math.max(0, Math.min(maxOffset, vpTopAbs - naturalTopAbs));
            } else {
                var sidebarTopAbs = naturalTopAbs + offset;
                var sidebarBotAbs = sidebarTopAbs + sh;
                if (dir === 'down' && sidebarBotAbs < vpBotAbs) {
                    offset = vpBotAbs - sh - naturalTopAbs;
                } else if (dir === 'up' && sidebarTopAbs > vpTopAbs) {
                    offset = vpTopAbs - naturalTopAbs;
                }
                offset = Math.max(0, Math.min(maxOffset, offset));
            }
            sidebar.style.transform = 'translate3d(0,' + Math.round(offset) + 'px,0)';
        };

        var onScroll = function () {
            if (!ticking) {
                window.requestAnimationFrame(update);
                ticking = true;
            }
        };

        var onResize = function () {
            offset = 0;
            sidebar.style.transform = '';
            measure();
            lastY = window.scrollY;
            update();
        };

        // Initial measurement po load + jedna ďalšia po images/fonts paint.
        measure();
        window.addEventListener('load', measure);
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onResize, { passive: true });
        update();
    }

    function boot() {
        document.querySelectorAll('.farnost-search').forEach(initSearch);
        initAlertDismiss();
        initNavToggle();
        initStickySidebar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
