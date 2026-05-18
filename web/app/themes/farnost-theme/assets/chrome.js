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
        // Skry banner ak má user uloženú dismiss cookie pre toto ID.
        var id = bar.getAttribute('data-banner-id') || '';
        try {
            if (id && document.cookie.indexOf('farnost_banner_dismissed=' + id) !== -1) {
                bar.style.display = 'none';
                return;
            }
        } catch (e) {}
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

    function boot() {
        document.querySelectorAll('.farnost-search').forEach(initSearch);
        initAlertDismiss();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
