(() => {
    const data = window.assetsReportsData || {};
    const monthlyConfig = data.monthlyChart || {};
    const typeConfig = data.typeChart || {};

    function renderMonthlyChart() {
        const ctx = document.getElementById('monthlyChart');
        if (!ctx || typeof Chart === 'undefined') {
            return;
        }
        const labels = Array.isArray(monthlyConfig.labels) ? monthlyConfig.labels : [];
        const totals = Array.isArray(monthlyConfig.totals) ? monthlyConfig.totals : [];
        const labelText = monthlyConfig.seriesLabel || '';
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: labelText,
                    data: totals,
                    backgroundColor: 'rgba(16,206,48,0.7)',
                    borderColor: '#10ce30',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '฿' + Number(value || 0).toLocaleString('en-US')
                        }
                    }
                }
            }
        });
    }

    function renderTypeChart() {
        const ctx = document.getElementById('typeChart');
        if (!ctx || typeof Chart === 'undefined') {
            return;
        }
        const labels = Array.isArray(typeConfig.labels) && typeConfig.labels.length
            ? typeConfig.labels
            : ['No data'];
        const totals = Array.isArray(typeConfig.totals) && typeConfig.totals.length
            ? typeConfig.totals
            : [1];
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: totals,
                    backgroundColor: ['#10ce30', '#4299e1', '#f6ad55', '#fc8181', '#9f7aea', '#68d391', '#63b3ed'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    function attachPrintHandler() {
        const button = document.querySelector('[data-action="print-report"]');
        if (!button) return;
        button.addEventListener('click', printReport);
    }

    function printReport() {
        const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        document.body.dataset.printDate = dateStr;
        window.print();
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderMonthlyChart();
        renderTypeChart();
        attachPrintHandler();
    });

    window.printReport = printReport;
})();
