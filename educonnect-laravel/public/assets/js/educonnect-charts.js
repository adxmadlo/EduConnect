/* ============================================================
   EduConnect — Charts layer (Chart.js 4 wrapper)
   One visual language for every graph in the platform.

   Usage:
     EduCharts.line('chartId', {labels:[], values:[], label:'',
                                format:'score'|'money'|'count'|fn,
                                color:'#326ea9', max:20, fill:true});
     EduCharts.bar('chartId', {...});
     EduCharts.doughnut('chartId', {labels, values, colors, center:{value,label}});
     EduCharts.radar('chartId', {...});

   Every factory:
     - renders an explicit empty state instead of a blank canvas
     - exposes an accessible aria-label on the canvas
     - formats tooltips in fr-SN (FCFA) or /20
     - disables animation when the user prefers reduced motion
   ============================================================ */
(function (global) {
  'use strict';

  var registry = {};

  var money = new Intl.NumberFormat('fr-SN', {
    maximumFractionDigits: 0
  });

  function fmtMoney(v) {
    return money.format(Math.round(Number(v) || 0)) + ' FCFA';
  }

  function fmtScore(v) {
    var n = Number(v);
    if (!isFinite(n)) return '—';
    return n.toFixed(2).replace(/\.00$/, '').replace(/0$/, '') + '/20';
  }

  function fmtCount(v) {
    return money.format(Number(v) || 0);
  }

  function formatter(kind) {
    if (typeof kind === 'function') return kind;
    if (kind === 'money') return fmtMoney;
    if (kind === 'score') return fmtScore;
    return fmtCount;
  }

  function reducedMotion() {
    return global.matchMedia &&
      global.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function hasData(values) {
    if (!values || !values.length) return false;
    return values.some(function (v) {
      return v !== null && v !== undefined && v !== '' && isFinite(Number(v));
    });
  }

  function canvas(id) {
    return typeof id === 'string' ? document.getElementById(id) : id;
  }

  function applyDefaults() {
    if (!global.Chart) return false;
    var C = global.Chart;
    C.defaults.font.family = "'Ubuntu', -apple-system, 'Segoe UI', Roboto, sans-serif";
    C.defaults.font.size = 11;
    C.defaults.color = '#718096';
    C.defaults.borderColor = '#e3e8ef';
    C.defaults.maintainAspectRatio = false;
    C.defaults.animation.duration = reducedMotion() ? 0 : 550;
    C.defaults.plugins.legend.display = false;
    C.defaults.plugins.tooltip.backgroundColor = '#111c2d';
    C.defaults.plugins.tooltip.padding = 10;
    C.defaults.plugins.tooltip.cornerRadius = 8;
    C.defaults.plugins.tooltip.titleFont = { weight: '600', size: 11 };
    C.defaults.plugins.tooltip.bodyFont = { size: 11 };
    C.defaults.plugins.tooltip.displayColors = true;
    C.defaults.plugins.tooltip.boxWidth = 8;
    C.defaults.plugins.tooltip.boxHeight = 8;
    C.defaults.plugins.tooltip.boxPadding = 4;
    return true;
  }

  /**
   * Mounts a chart, replacing a previous one bound to the same canvas and
   * toggling the `.is-empty` state of the surrounding `.chart-wrap`.
   */
  /**
   * Sizes a chart from its container and re-measures whenever that container
   * changes width or height (mobile drawer, rotation, split view).
   * Sizing is explicit (responsive:false) so the canvas never disagrees with
   * the layout after a breakpoint change.
   */
  function applySize(wrap, chart) {
    if (!wrap || !chart) return;
    var w = Math.round(wrap.clientWidth);
    var h = Math.round(wrap.clientHeight);
    if (w > 0 && h > 0) {
      try { chart.resize(w, h); } catch (e) {}
    }
  }

  function watchSize(wrap, chart) {
    if (wrap) applySize(wrap, chart);
    if (!global.ResizeObserver || !wrap) return;

    var frame = null;
    var ro = new ResizeObserver(function () {
      if (frame) cancelAnimationFrame(frame);
      frame = requestAnimationFrame(function () {
        applySize(wrap, chart);
      });
    });
    ro.observe(wrap);
    chart.$resizeObserver = ro;

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () { applySize(wrap, chart); });
    }
  }

  function mount(el, config) {
    if (!el) return null;
    var wrap = el.closest ? el.closest('.chart-wrap, .donut-wrap') : null;

    if (registry[el.id]) {
      if (registry[el.id].$resizeObserver) registry[el.id].$resizeObserver.disconnect();
      registry[el.id].destroy();
      delete registry[el.id];
    }

    if (!applyDefaults()) {
      if (wrap) wrap.classList.add('is-empty');
      return null;
    }

    if (wrap) wrap.classList.remove('is-empty');

    // Explicit sizing: the wrapper owns the dimensions, the canvas follows.
    config.options = config.options || {};
    config.options.responsive = false;
    config.options.maintainAspectRatio = false;

    var chart = new Chart(el, config);
    if (el.id) registry[el.id] = chart;
    watchSize(wrap, chart);
    return chart;
  }

  function setAria(el, label) {
    if (!el || !label) return;
    el.setAttribute('role', 'img');
    el.setAttribute('aria-label', label);
  }

  function gradient(ctx, color) {
    var g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.clientHeight || 240);
    g.addColorStop(0, hexAlpha(color, 0.28));
    g.addColorStop(1, hexAlpha(color, 0.02));
    return g;
  }

  function hexAlpha(hex, alpha) {
    var h = String(hex || '#326ea9').replace('#', '');
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    var n = parseInt(h, 16);
    if (!isFinite(n)) return 'rgba(50,110,169,' + alpha + ')';
    var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function axis(max, format) {
    var f = formatter(format);
    var ticks = {
      font: { size: 10 },
      color: '#8b97a8',
      padding: 6
    };
    if (format === 'money') {
      ticks.callback = function (v) {
        return Number(v) >= 1000
          ? Math.round(Number(v) / 1000) + 'k'
          : money.format(Number(v) || 0);
      };
    } else if (format === 'score') {
      ticks.callback = function (v) { return v; };
    }
    return {
      beginAtZero: true,
      max: max,
      border: { display: false },
      grid: { color: '#eef2f7', drawTicks: false },
      ticks: ticks,
      _f: f
    };
  }

  function emptyState(el, wrap, message) {
    if (wrap) {
      var node = wrap.querySelector('.chart-empty');
      if (node && message) {
        var target = node.querySelector('span');
        if (target) target.textContent = message;
      }
      wrap.classList.add('is-empty');
    }
    if (el) {
      el.setAttribute('role', 'img');
      el.setAttribute('aria-label', message || 'Aucune donnée disponible');
    }
    return null;
  }

  var api = {
    money: fmtMoney,
    score: fmtScore,
    count: fmtCount,
    empty: emptyState,

    line: function (id, opts) {
      var el = canvas(id);
      if (!el) return null;
      opts = opts || {};
      var wrap = el.closest ? el.closest('.chart-wrap, .donut-wrap') : null;
      setAria(el, opts.aria || opts.label || 'Graphique');
      if (!hasData(opts.values)) return emptyState(el, wrap, opts.empty);

      var color = opts.color || '#326ea9';
      var f = formatter(opts.format);
      var y = axis(opts.max, opts.format);

      return mount(el, {
        type: 'line',
        data: {
          labels: opts.labels || [],
          datasets: [{
            label: opts.label || '',
            data: opts.values,
            borderColor: color,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#fff',
            pointBorderColor: color,
            pointBorderWidth: 2,
            tension: 0.32,
            fill: opts.fill !== false,
            backgroundColor: function (ctx2) {
              return gradient(ctx2.chart.ctx, color);
            }
          }]
        },
        options: {
          interaction: { mode: 'index', intersect: false },
          plugins: {
            tooltip: {
              callbacks: {
                label: function (c) { return ' ' + f(c.parsed.y); }
              }
            }
          },
          scales: {
            x: {
              grid: { display: false },
              border: { display: false },
              ticks: { font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 }
            },
            y: y
          }
        }
      });
    },

    bar: function (id, opts) {
      var el = canvas(id);
      if (!el) return null;
      opts = opts || {};
      var wrap = el.closest ? el.closest('.chart-wrap, .donut-wrap') : null;
      setAria(el, opts.aria || opts.label || 'Graphique');
      if (!hasData(opts.values)) return emptyState(el, wrap, opts.empty);

      var color = opts.color || '#326ea9';
      var f = formatter(opts.format);
      var colors = opts.colors || opts.values.map(function () { return color; });
      var horizontal = opts.horizontal === true;

      var valueAxis = axis(opts.max, opts.format);
      var categoryAxis = {
        grid: { display: false },
        border: { display: false },
        ticks: { font: { size: 10 }, autoSkip: false }
      };

      return mount(el, {
        type: 'bar',
        data: {
          labels: opts.labels || [],
          datasets: [{
            label: opts.label || '',
            data: opts.values,
            backgroundColor: colors,
            borderRadius: 5,
            borderSkipped: false,
            maxBarThickness: horizontal ? 18 : 34
          }]
        },
        options: {
          indexAxis: horizontal ? 'y' : 'x',
          plugins: {
            tooltip: {
              callbacks: {
                label: function (c) {
                  return ' ' + f(horizontal ? c.parsed.x : c.parsed.y);
                }
              }
            }
          },
          scales: horizontal
            ? { x: valueAxis, y: categoryAxis }
            : { x: categoryAxis, y: valueAxis }
        }
      });
    },

    doughnut: function (id, opts) {
      var el = canvas(id);
      if (!el) return null;
      opts = opts || {};
      var wrap = el.closest ? el.closest('.chart-wrap, .donut-wrap') : null;
      setAria(el, opts.aria || opts.label || 'Répartition');
      if (!hasData(opts.values)) return emptyState(el, wrap, opts.empty);

      var f = formatter(opts.format);

      var chart = mount(el, {
        type: 'doughnut',
        data: {
          labels: opts.labels || [],
          datasets: [{
            data: opts.values,
            backgroundColor: opts.colors || ['#326ea9', '#15803d', '#b45309', '#c0272d'],
            borderColor: '#fff',
            borderWidth: 2,
            hoverOffset: 4
          }]
        },
        options: {
          cutout: opts.cutout || '68%',
          plugins: {
            tooltip: {
              callbacks: {
                label: function (c) {
                  var total = c.dataset.data.reduce(function (a, b) {
                    return a + Number(b || 0);
                  }, 0);
                  var pct = total > 0 ? Math.round((Number(c.parsed) / total) * 100) : 0;
                  return ' ' + c.label + ' : ' + f(c.parsed) + ' (' + pct + '%)';
                }
              }
            }
          }
        }
      });

      if (opts.center && el.id) {
        var center = document.getElementById(el.id + 'Center');
        if (center) {
          var strong = center.querySelector('strong');
          var span = center.querySelector('span');
          if (strong) strong.textContent = opts.center.value;
          if (span) span.textContent = opts.center.label;
        }
      }
      return chart;
    },

    radar: function (id, opts) {
      var el = canvas(id);
      if (!el) return null;
      opts = opts || {};
      var wrap = el.closest ? el.closest('.chart-wrap, .donut-wrap') : null;
      setAria(el, opts.aria || opts.label || 'Profil');
      if (!hasData(opts.values)) return emptyState(el, wrap, opts.empty);

      var color = opts.color || '#326ea9';
      var f = formatter(opts.format);

      return mount(el, {
        type: 'radar',
        data: {
          labels: opts.labels || [],
          datasets: [{
            label: opts.label || '',
            data: opts.values,
            borderColor: color,
            borderWidth: 2,
            backgroundColor: hexAlpha(color, 0.14),
            pointBackgroundColor: color,
            pointRadius: 3
          }]
        },
        options: {
          scales: {
            r: {
              beginAtZero: true,
              max: opts.max || 20,
              grid: { color: '#eef2f7' },
              angleLines: { color: '#eef2f7' },
              pointLabels: { font: { size: 10 }, color: '#465568' },
              ticks: { display: false }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function (c) { return ' ' + f(c.parsed.r); }
              }
            }
          }
        }
      });
    }
  };

  global.EduCharts = api;
})(window);
