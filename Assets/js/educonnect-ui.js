/* ============================================================
   EduConnect — Shell behaviour
   Mobile navigation drawer, toast, segmented period picker.
   Small on purpose: pages stay plain PHP + HTML.
   ============================================================ */
(function (global) {
  'use strict';

  var toastTimer = null;

  function closeNav() {
    document.body.classList.remove('nav-open');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) backdrop.classList.remove('is-visible');
  }

  function openNav() {
    document.body.classList.add('nav-open');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) backdrop.classList.add('is-visible');
  }

  function toggleNav() {
    if (document.body.classList.contains('nav-open')) closeNav();
    else openNav();
  }

  function toast(message) {
    var el = document.getElementById('toast');
    if (!el) return;
    el.innerHTML = '<i class="bi bi-check-circle-fill" aria-hidden="true"></i><span></span>';
    el.querySelector('span').textContent = message;
    el.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      el.classList.remove('is-visible');
    }, 3200);
  }

  /**
   * Segmented period control. Marks the clicked button only within its own
   * group and updates the optional label target.
   */
  function setPeriod(button, label) {
    if (!button) return;
    var group = button.closest('.segmented');
    if (group) {
      group.querySelectorAll('button').forEach(function (b) {
        b.classList.remove('is-active');
        b.setAttribute('aria-pressed', 'false');
      });
    }
    button.classList.add('is-active');
    button.setAttribute('aria-pressed', 'true');

    if (label) {
      var target = document.getElementById(label.target || 'periodLabel');
      if (target) target.textContent = label.text || button.textContent.trim();
    }
  }

  function init() {
    var backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) backdrop.addEventListener('click', closeNav);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeNav();
    });

    // Chart.js keeps its own resize observer; here we only close the mobile
    // drawer once the viewport is wide enough for the sidebar again.
    var resizeTimer;
    global.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (global.innerWidth > 900) closeNav();
      }, 180);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.EduUI = {
    openNav: openNav,
    closeNav: closeNav,
    toggleNav: toggleNav,
    toast: toast,
    setPeriod: setPeriod
  };
})(window);
