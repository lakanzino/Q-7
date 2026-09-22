/**
 * Quantum Pedia Child UI scripts — Cosmic (v2)
 *
 * - فعال‌سازی کلاس is-scrolled روی هدر هنگام اسکرول
 *   (پدیدار شدن خط نورانی زیر هدر + شفافیت ملایم)
 * - باز/بستن اورلی جست‌وجو با آیکون ذره‌بین
 * - بستن اورلی با ESC یا کلیک روی پس‌زمینه
 *
 * @package Quantum_Pedia_Child
 */
(function () {
  'use strict';

  // ───────────────────────────────────────────────────────────────
  // ۱. اسکرول — اضافه/حذف کلاس is-scrolled
  // ───────────────────────────────────────────────────────────────
  var header = document.querySelector('[data-qp-header]');
  if (header) {
    var ticking = false;
    var SCROLL_THRESHOLD = 8;

    var update = function () {
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      if (y > SCROLL_THRESHOLD) {
        header.classList.add('is-scrolled');
      } else {
        header.classList.remove('is-scrolled');
      }
      ticking = false;
    };

    var onScroll = function () {
      if (!ticking) {
        window.requestAnimationFrame(update);
        ticking = true;
      }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    update();
  }

  // ───────────────────────────────────────────────────────────────
  // ۲. اورلی جست‌وجو
  // ───────────────────────────────────────────────────────────────
  var toggle = document.querySelector('.qp-global-header__search-toggle');
  var overlay = document.getElementById('qp-search-overlay');

  if (toggle && overlay) {
    var input = overlay.querySelector('.qp-search-overlay__input');
    var previousBodyOverflow = '';

    var getFocusableElements = function () {
      return Array.prototype.slice.call(
        overlay.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
      ).filter(function (element) {
        return !element.hidden && element.offsetParent !== null;
      });
    };

    var openOverlay = function () {
      previousBodyOverflow = document.body.style.overflow;
      overlay.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      // انتقال فوکوس پس از آشکارشدن پنجره.
      window.requestAnimationFrame(function () {
        if (input) input.focus();
      });
      document.body.style.overflow = 'hidden';
    };

    var closeOverlay = function () {
      overlay.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = previousBodyOverflow;
      toggle.focus();
    };

    toggle.addEventListener('click', function () {
      if (overlay.hidden) {
        openOverlay();
      } else {
        closeOverlay();
      }
    });

    // بستن با کلیک روی backdrop یا دکمهٔ بستن
    overlay.querySelectorAll('[data-qp-search-close]').forEach(function (el) {
      el.addEventListener('click', closeOverlay);
    });

    // بستن با ESC و نگه‌داشتن فوکوس داخل پنجرهٔ modal.
    document.addEventListener('keydown', function (e) {
      if (overlay.hidden) {
        return;
      }
      if (e.key === 'Escape') {
        closeOverlay();
        return;
      }
      if (e.key !== 'Tab') {
        return;
      }

      var focusable = getFocusableElements();
      if (!focusable.length) {
        e.preventDefault();
        return;
      }

      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // ارسال فرم — اگر خالی نبود، اجازه بده ارسال بشه؛ اگر خالی بود جلوگیری کن
    var form = overlay.querySelector('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (input && !input.value.trim()) {
          e.preventDefault();
          input.focus();
        }
      });
    }
  }
})();