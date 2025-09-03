// Chart.js global theming without imports (uses CDN-provided window.Chart)
(function () {
  if (!window.Chart) return;
  const Chart = window.Chart;
  Chart.defaults.color = '#334155'; // slate-700
  Chart.defaults.font.family = getComputedStyle(document.documentElement).fontFamily || 'system-ui, -apple-system, Segoe UI, Roboto, Noto Sans KR, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.plugins.legend.labels.boxWidth = 12;
  Chart.defaults.plugins.tooltip.cornerRadius = 6;
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
  Chart.defaults.plugins.tooltip.titleColor = '#e2e8f0';
  Chart.defaults.plugins.tooltip.bodyColor = '#e2e8f0';
  Chart.defaults.elements.line.tension = 0.35;
  Chart.defaults.scales.category.grid.color = 'rgba(148,163,184,.2)';
  Chart.defaults.scales.linear.grid.color = 'rgba(148,163,184,.2)';

  // Dataset color preset (indigo / emerald / slate / amber / cyan)
  const palette = ['#4f46e5', '#10b981', '#475569', '#f59e0b', '#06b6d4', '#7c3aed', '#0ea5e9'];
  Chart.register({
    id: 'ykp-color-preset',
    beforeInit(chart) {
      const ds = chart.config.data && chart.config.data.datasets ? chart.config.data.datasets : [];
      ds.forEach((d, i) => {
        const color = palette[i % palette.length];
        if (!d.borderColor) d.borderColor = color;
        if (!d.backgroundColor) {
          // semi-transparent fill for area/points
          d.backgroundColor = color + '33';
        }
        if (d.type === 'bar') {
          d.borderWidth = d.borderWidth ?? 0;
        } else {
          d.borderWidth = d.borderWidth ?? 2;
        }
        d.pointRadius = d.pointRadius ?? 0;
        d.pointHoverRadius = d.pointHoverRadius ?? 3;
      });
    }
  });
})();
