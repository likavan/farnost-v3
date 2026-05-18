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
     * Mobile sidebar drawer — sidebar je default skrytý mimo viewport-u
     * (CSS `transform: translateX(100%)` v mobile breakpointe). Peek tab
     * pripnutý na pravom okraji slúži ako pozvánka („Bohoslužby & kontakt"),
     * klik otvorí drawer s backdrop-om; backdrop click / Esc / X zatvára.
     *
     * Peek + backdrop sa generujú JS-om aby template-parts ostali clean —
     * potrebné iba na mobile a sidebar.html nemá kde elegantne pridať.
     */
    function initMobileSidebar() {
        var sidebar = document.querySelector('.farnost-sidebar');
        if (!sidebar) {
            return;
        }
        var mq = window.matchMedia('(max-width: 980px)');

        var peek = document.createElement('button');
        peek.type = 'button';
        peek.className = 'farnost-sidebar-peek';
        peek.setAttribute('aria-expanded', 'false');
        peek.setAttribute('aria-controls', 'farnost-sidebar-drawer');
        peek.innerHTML = '<span class="farnost-sidebar-peek__label">Bohoslužby &amp; kontakt</span>';

        var backdrop = document.createElement('div');
        backdrop.className = 'farnost-sidebar-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'farnost-sidebar-close';
        closeBtn.setAttribute('aria-label', 'Zavrieť');
        closeBtn.innerHTML = '✕'; // ✕
        sidebar.insertBefore(closeBtn, sidebar.firstChild);

        sidebar.id = 'farnost-sidebar-drawer';

        // Portal: pri mobile mode presunieme sidebar do <body> aby bol sibling
        // backdropu — inak môže byť skrytý pod ním kvôli stacking context-u
        // ktorý vytvára `.site-main` grid + template-part wrapper. Pri prepnutí
        // do desktop mode vrátime sidebar na pôvodné miesto v DOM.
        var sidebarHome = sidebar.parentNode;
        var sidebarAnchor = sidebar.nextSibling;

        var setMobile = function (mobile) {
            document.body.classList.toggle('farnost-has-sidebar-drawer', mobile);
            if (mobile) {
                if (sidebar.parentNode !== document.body) {
                    document.body.appendChild(sidebar);
                }
            } else {
                close();
                if (sidebar.parentNode === document.body && sidebarHome) {
                    sidebarHome.insertBefore(sidebar, sidebarAnchor);
                }
            }
        };

        var open = function () {
            sidebar.classList.add('is-open');
            backdrop.classList.add('is-open');
            peek.setAttribute('aria-expanded', 'true');
            document.body.classList.add('farnost-drawer-open');
        };
        var close = function () {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            peek.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('farnost-drawer-open');
        };

        peek.addEventListener('click', function () {
            if (sidebar.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });
        closeBtn.addEventListener('click', close);
        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                close();
            }
        });

        document.body.appendChild(backdrop);
        document.body.appendChild(peek);

        setMobile(mq.matches);
        if (mq.addEventListener) {
            mq.addEventListener('change', function (e) { setMobile(e.matches); });
        } else if (mq.addListener) {
            mq.addListener(function (e) { setMobile(e.matches); });
        }
    }

    function boot() {
        document.querySelectorAll('.farnost-search').forEach(initSearch);
        initAlertDismiss();
        initNavToggle();
        initMobileSidebar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
