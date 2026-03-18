document.addEventListener('DOMContentLoaded', () => {
    const chartColors = ['#667eea', '#475569', '#4ade80', '#f97316', '#ef4444', '#6366f1', '#14b8a6', '#facc15'];
    const payloadEl = document.querySelector('[data-report-payload]');
    let dataStore = {};
    if (payloadEl?.dataset?.reportPayload) {
        try {
            dataStore = JSON.parse(payloadEl.dataset.reportPayload);
        } catch (error) {
            console.error('Unable to parse report payload', error);
        }
    }
    const categoryData = dataStore.categoryData || [];
    const priorityData = dataStore.priorityData || [];
    const statusData = dataStore.statusData || [];
    const trendLineData = dataStore.trendLineData || [];

    function initDoughnut(ctx, labels, values, colorMap = []) {
        if (!ctx) return;
        const colors = labels.map((_, idx) => colorMap[idx] || chartColors[idx % chartColors.length]);
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } }
                }
            }
        });
    }

    function setupCharts() {
        const categoryLabels = categoryData.map(item => item.category || 'ไม่ระบุ');
        const categoryValues = categoryData.map(item => Number(item.count) || 0);
        initDoughnut(document.getElementById('categoryChart'), categoryLabels, categoryValues);

        const priorityMap = {
            urgent: '#ef4444',
            high: '#f97316',
            normal: '#3b82f6',
            low: '#10b981'
        };
        const priorityLabels = priorityData.map(item => (item.priority || 'Unknown').replace('_', ' '));
        const priorityValues = priorityData.map(item => Number(item.count) || 0);
        const priorityColors = priorityData.map(item => priorityMap[item.priority] || '#cbd5e0');
        initDoughnut(document.getElementById('priorityChart'), priorityLabels, priorityValues, priorityColors);

        const statusMap = {
            new: '#38bdf8',
            assigned: '#2563eb',
            in_progress: '#facc15',
            resolved: '#22c55e',
            closed: '#475569'
        };
        const statusLabels = statusData.map(item => (item.status || 'Unknown').replace('_', ' '));
        const statusValues = statusData.map(item => Number(item.count) || 0);
        const statusColors = statusData.map(item => statusMap[item.status] || '#cbd5e0');
        initDoughnut(document.getElementById('statusChart'), statusLabels, statusValues, statusColors);

        const trendCtx = document.getElementById('trendChart');
        if (!trendCtx) return;
        if (trendLineData.length) {
            const labels = trendLineData.map(item => new Date(item.date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short' }));
            const counts = trendLineData.map(item => Number(item.count) || 0);
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Tickets',
                        data: counts,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.25)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#1d4ed8'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#475569' },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#475569' },
                            grid: { color: '#e2e8f0' }
                        }
                    }
                }
            });
        } else {
            const ctx = trendCtx.getContext('2d');
            ctx.font = '14px \"Sarabun\", sans-serif';
            ctx.fillStyle = '#94a3b8';
            ctx.textAlign = 'center';
            ctx.fillText('ยังไม่มีข้อมูลเทรนด์', trendCtx.width / 2, trendCtx.height / 2);
        }
    }

    setupCharts();
});
