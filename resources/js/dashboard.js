import { Chart, BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip, Legend);

const dataElement = document.getElementById('dashboard-chart-data');
if (dataElement) {
    const data = JSON.parse(dataElement.textContent);
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const charts = [];
    const create = (selector, configuration) => {
        const canvas = document.querySelector(selector);
        if (!canvas) return;
        canvas.parentElement.hidden = false;
        charts.push(new Chart(canvas, {
            ...configuration,
            options: { responsive: true, maintainAspectRatio: false, animation: reducedMotion.matches ? false : { duration: 350 }, ...configuration.options },
        }));
        // Sin JS las tablas nacen abiertas; con el gráfico disponible siguen a un clic.
        const table = canvas.closest('article')?.querySelector('details');
        if (table) table.open = false;
    };
    create('[data-weekly-chart]', {
        type: 'bar',
        data: { labels: data.weeks.map(week => week.label), datasets: [{ label: 'Solicitudes', data: data.weeks.map(week => week.count), backgroundColor: '#324853', borderRadius: 3 }] },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } },
    });
    create('[data-status-chart]', {
        type: 'doughnut',
        data: { labels: data.states.map(state => state.label), datasets: [{ data: data.states.map(state => state.count), backgroundColor: ['#b48a47', '#324853', '#986349', '#4b7865', '#922f3e', '#686d73', '#91a4ac'], borderWidth: 2, borderColor: '#fff' }] },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } } } },
    });
    reducedMotion.addEventListener('change', () => charts.forEach(chart => {
        chart.options.animation = reducedMotion.matches ? false : { duration: 350 };
        chart.update('none');
    }));
}
