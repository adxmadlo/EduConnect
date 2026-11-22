/**
 * EduConnect — animations.js
 * Scroll-reveal + page transitions for all public pages.
 * Drop this <script src="Assets/js/animations.js"> before </body> on every page.
 */

/* ══════════════════════════════════════════
   1. PAGE ENTRY OVERLAY
   Inject once, animate out on load
══════════════════════════════════════════ */
(function injectOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'page-overlay';
    document.body.prepend(overlay);
    // Remove from DOM after animation ends so it can't block clicks
    overlay.addEventListener('animationend', () => overlay.remove(), { once: true });
})();


/* ══════════════════════════════════════════
   2. PAGE-LEAVING TRANSITION
   Intercept internal <a> clicks, animate out
══════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto') || href.startsWith('tel')) return;
    if (link.target === '_blank') return;
    // Only same-origin navigation
    try {
        const url = new URL(href, location.href);
        if (url.origin !== location.origin) return;
    } catch (_) { return; }

    e.preventDefault();

    // Re-inject overlay for leave
    const overlay = document.createElement('div');
    overlay.className = 'page-overlay';
    overlay.style.cssText = 'transform:scaleY(0);transform-origin:bottom;animation:overlayIn 0.45s cubic-bezier(0.76,0,0.24,1) forwards';
    document.body.prepend(overlay);

    overlay.addEventListener('animationend', () => {
        window.location.href = href;
    }, { once: true });
});


/* ══════════════════════════════════════════
   3. SCROLL-REVEAL OBSERVER
   Adds .sr class to target elements, then
   triggers .visible when they enter viewport
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {

    // Elements to animate — add .sr class, optionally .sr-left / .sr-right / .sr-scale
    const selectors = [
        // Staggered grid children
        { sel: '.features .item',            variant: null,      stagger: true },
        { sel: '.values .item',              variant: null,      stagger: true },
        { sel: '.additional-services .item', variant: null,      stagger: true },
        { sel: '.services-list .item',       variant: null,      stagger: true },
        { sel: '.services-main .service-card', variant: null,    stagger: true },
        { sel: '.stats .stat-item',          variant: 'sr-scale',stagger: true },
        { sel: '.testimonials .item',        variant: null,      stagger: true },
        { sel: '.mission-vision .item',      variant: null,      stagger: true },
        { sel: '.team .team-member',         variant: null,      stagger: true },
        { sel: '.why-choose .item',          variant: null,      stagger: true },
        { sel: '.contact-info .info-item',   variant: 'sr-scale',stagger: true },
        { sel: '.pricing-plans .plan',       variant: null,      stagger: true },
        // Single elements
        { sel: 'h2.separator',              variant: 'sr-left',  stagger: false },
        { sel: '.services-preview .category', variant: null,     stagger: false },
        { sel: '.faq-item',                 variant: null,       stagger: true  },
        { sel: '.timeline-item',            variant: 'sr-left',  stagger: true  },
        { sel: '.cta-section',              variant: null,       stagger: false },
        { sel: '.comparison-table',         variant: null,       stagger: false },
        { sel: '.contact-form-container',   variant: null,       stagger: false },
        { sel: '.form-message',             variant: null,       stagger: false },
        { sel: 'footer',                    variant: null,       stagger: false },
    ];

    const staggerClasses = ['sr-d1','sr-d2','sr-d3','sr-d4','sr-d5','sr-d6'];

    selectors.forEach(({ sel, variant, stagger }) => {
        document.querySelectorAll(sel).forEach((el, i) => {
            el.classList.add('sr');
            if (variant) el.classList.add(variant);
            if (stagger) {
                const delay = staggerClasses[Math.min(i, staggerClasses.length - 1)];
                el.classList.add(delay);
            }
        });
    });

    // Also animate separator underlines
    document.querySelectorAll('h2.separator').forEach(el => {
        el.classList.add('sr');
    });

    // IntersectionObserver
    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // Trigger separator underline
                if (entry.target.matches('h2.separator')) {
                    entry.target.classList.add('in-view');
                }
                io.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -40px 0px'
    });

    document.querySelectorAll('.sr').forEach(el => io.observe(el));


    /* ══════════════════════════════════════
       4. STAT COUNTER ANIMATION
    ══════════════════════════════════════ */
    const statObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const h3 = entry.target.querySelector('h3');
            if (!h3 || h3.dataset.counted) return;
            h3.dataset.counted = '1';

            const raw = h3.textContent.trim();
            // Extract numeric part and suffix (e.g. "5,000+" → 5000, "+")
            const num = parseFloat(raw.replace(/[^0-9.]/g, ''));
            if (!num) return;
            const suffix = raw.replace(/[0-9.,]/g, '').trim();
            const hasComma = raw.includes(',');

            const dur = 1400;
            const t0 = performance.now();
            (function step(now) {
                const t = Math.min((now - t0) / dur, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                const current = Math.round(eased * num);
                h3.textContent = (hasComma ? current.toLocaleString() : current) + suffix;
                if (t < 1) requestAnimationFrame(step);
            })(t0);

            statObserver.unobserve(entry.target);
        });
    }, { threshold: 0.4 });

    document.querySelectorAll('.stat-item').forEach(el => statObserver.observe(el));


    /* ══════════════════════════════════
       5. FAQ ACCORDION
    ══════════════════════════════════ */
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.faq-item');
            const isOpen = item.classList.contains('open');
            // Close all
            document.querySelectorAll('.faq-item.open').forEach(o => o.classList.remove('open'));
            if (!isOpen) item.classList.add('open');
        });
    });


    /* ══════════════════════════════════
       6. NAV ACTIVE STATE ON SCROLL (index only)
    ══════════════════════════════════ */
    const sections = document.querySelectorAll('section[id]');
    if (sections.length) {
        const navLinks = document.querySelectorAll('nav .links a');
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(s => {
                if (window.scrollY >= s.offsetTop - 120) current = s.id;
            });
            navLinks.forEach(a => {
                a.classList.remove('active');
                if (a.getAttribute('href') === '#' + current) a.classList.add('active');
            });
        }, { passive: true });
    }

});
