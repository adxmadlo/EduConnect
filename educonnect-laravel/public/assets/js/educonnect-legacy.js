/* ============================================================
   EduConnect — Legacy Shell Behaviour
   Progressive enhancement for the original pages: mobile drawer,
   sidebar search, keyboard/a11y state. No markup rewrite needed.
   ============================================================ */
(function () {
  'use strict';

  var MOBILE = 1024;

  function isMobile() {
    return window.innerWidth <= MOBILE;
  }

  function sidebar() { return document.getElementById('sidebar'); }
  function main() { return document.getElementById('main'); }

  function scrim() {
    var el = document.querySelector('.ec-scrim');
    if (!el) {
      el = document.createElement('div');
      el.className = 'ec-scrim';
      el.addEventListener('click', closeDrawer);
      document.body.appendChild(el);
    }
    return el;
  }

  function openDrawer() {
    var s = sidebar();
    if (!s) return;
    s.classList.add('open');
    s.classList.remove('collapsed');
    scrim().classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    var s = sidebar();
    if (!s) return;
    s.classList.remove('open');
    scrim().classList.remove('show');
    document.body.style.overflow = '';
  }

  /* Keep the pages' own desktop collapse, add a mobile drawer. */
  var nativeToggle = window.toggleSidebar;
  window.toggleSidebar = function () {
    if (isMobile()) {
      var s = sidebar();
      if (!s) return;
      if (s.classList.contains('open')) closeDrawer(); else openDrawer();
      return;
    }
    if (typeof nativeToggle === 'function') {
      nativeToggle.apply(this, arguments);
      return;
    }
    var sb = sidebar(), mn = main();
    if (sb && mn) {
      sb.classList.toggle('collapsed');
      mn.classList.toggle('expanded');
    }
  };

  function addMenuButton() {
    var bar = document.querySelector('.topbar');
    if (!bar || bar.querySelector('.ec-menu-btn')) return;
    var btn = document.createElement('button');
    btn.className = 'ec-menu-btn tb-icon';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Ouvrir la navigation');
    btn.innerHTML = '<i class="bi bi-list"></i>';
    btn.addEventListener('click', openDrawer);
    bar.insertBefore(btn, bar.firstChild);
  }

  function wireNav() {
    document.querySelectorAll('.nav-item').forEach(function (item) {
      if (item.classList.contains('active')) item.setAttribute('aria-current', 'page');
      item.addEventListener('click', function () {
        if (isMobile()) closeDrawer();
      });
    });

    var field = document.querySelector('.sidebar-search input');
    if (!field) return;
    field.addEventListener('input', function () {
      var q = field.value.trim().toLowerCase();
      document.querySelectorAll('.nav-item').forEach(function (item) {
        var hit = !q || (item.textContent || '').toLowerCase().indexOf(q) !== -1;
        item.style.display = hit ? '' : 'none';
      });
      document.querySelectorAll('.nav-section').forEach(function (sec) {
        var any = false;
        var el = sec.nextElementSibling;
        while (el && !el.classList.contains('nav-section')) {
          if (el.classList && el.classList.contains('nav-item') && el.style.display !== 'none') any = true;
          el = el.nextElementSibling;
        }
        sec.style.display = any || !q ? '' : 'none';
      });
    });
  }

  function wireA11y() {
    var toast = document.getElementById('toast');
    if (toast) {
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
    }
    document.querySelectorAll('.tb-icon').forEach(function (b) {
      if (!b.getAttribute('aria-label') && b.title) b.setAttribute('aria-label', b.title);
    });
    document.querySelectorAll('table').forEach(function (t) {
      if (!t.getAttribute('aria-label')) {
        var th = t.querySelector('th');
        if (th) t.setAttribute('aria-label', 'Tableau : ' + th.textContent.trim());
      }
    });
  }

  function markActiveTab() {
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      btn.setAttribute('aria-selected', btn.classList.contains('active') ? 'true' : 'false');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    addMenuButton();
    wireNav();
    wireA11y();
    markActiveTab();

    window.addEventListener('resize', function () {
      if (!isMobile()) {
        closeDrawer();
        document.body.style.overflow = '';
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
    });
  });
})();
