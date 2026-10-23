import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

// Expose for console debugging / ad-hoc chart code from other scripts.
window.Chart = Chart;

Chart.defaults.font.family =
    "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 12;

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const parseJson = (raw, fallback) => {
    if (!raw) return fallback;
    try {
        return JSON.parse(raw);
    } catch {
        return fallback;
    }
};

function buildChart(canvas) {
    if (Chart.getChart(canvas)) return;

    const type = canvas.dataset.chart || 'doughnut';
    const labels = parseJson(canvas.dataset.chartLabels, []);
    const values = parseJson(canvas.dataset.chartValues, []);
    const colors = parseJson(canvas.dataset.chartColors, []);

    if (!values.length) {
        canvas.replaceWith(
            Object.assign(document.createElement('p'), {
                className: 'text-sm text-gray-500',
                textContent: canvas.dataset.chartEmpty || 'Belum ada data untuk ditampilkan.',
            })
        );
        return;
    }

    try {
        new Chart(canvas, {
            type,
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: colors.length ? colors : ['#2563eb'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: type === 'doughnut' ? '62%' : undefined,
                animation: prefersReducedMotion() ? false : { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${ctx.parsed}`,
                        },
                    },
                },
            },
        });
    } catch (err) {
        console.error('Gagal membuat grafik', err);
    }
}

function animateCountUp(el) {
    const target = Number(el.dataset.countTo || 0);
    if (prefersReducedMotion() || target === 0) {
        el.textContent = target.toLocaleString('id-ID');
        return;
    }

    const duration = 900;
    const start = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 3);

    const step = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        el.textContent = Math.round(ease(progress) * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
}

function refreshMotion() {
    document.querySelectorAll('[data-chart]').forEach(buildChart);

    document.querySelectorAll('[data-count-to]').forEach((el) => {
        if (el.dataset.countDone === '1') return;
        el.dataset.countDone = '1';
        animateCountUp(el);
    });
}

document.addEventListener('DOMContentLoaded', () => refreshMotion());

// Livewire replaces component DOM on SPA navigation; re-run so new canvases and
// counters initialize. buildChart is idempotent and countDone guards the counters.
['livewire:navigated', 'livewire:updated', 'livewire:update'].forEach((evt) =>
    document.addEventListener(evt, () => refreshMotion())
);
