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

    function boot() {
        document.querySelectorAll('.farnost-search').forEach(initSearch);
        initAlertDismiss();
        initNavToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
