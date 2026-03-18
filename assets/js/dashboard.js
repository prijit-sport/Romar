(() => {
    const data = window.dashboardData || {};
    const assetsByStatus = Array.isArray(data.assetsByStatus) ? data.assetsByStatus : [];
    const ticketsByStatus = Array.isArray(data.ticketsByStatus) ? data.ticketsByStatus : [];
    const ticketsByPriority = Array.isArray(data.ticketsByPriority) ? data.ticketsByPriority : [];
    const toastMessages = data.toastMessages || {};
    const defaultToast = {
        marking: 'Marking notifications...',
        marked: 'All notifications marked',
        markError: 'Unable to mark notifications, please retry',
        serverError: 'Cannot reach the server, please try again'
    };
    const chartColors = ['#667eea', '#4299e1', '#48bb78', '#ed8936', '#f56565', '#9f7aea'];

    const messages = {
        marking: toastMessages.marking || defaultToast.marking,
        marked: toastMessages.marked || defaultToast.marked,
        markError: toastMessages.markError || defaultToast.markError,
        serverError: toastMessages.serverError || defaultToast.serverError
    };

    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) {
            return;
        }
        dropdown.classList.toggle('show');
    }

    function readAndViewTicket(notifId, ticketId) {
        if (!notifId || !ticketId) return;
        fetch('../api/marknotificationread.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notif_id: notifId })
        }).finally(() => {
            window.location.href = 'ticket_view.php?id=' + ticketId;
        });
    }

    function showToast(message, type = 'success') {
        if (!message) return;
        let toast = document.getElementById('toastMsg');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toastMsg';
            toast.style.cssText = `
                position:fixed; bottom:30px; right:30px; z-index:9999;
                background:#2d3748; color:white; padding:12px 20px;
                border-radius:10px; font-family:'Sarabun',sans-serif;
                font-size:0.95em; box-shadow:0 4px 20px rgba(0,0,0,0.3);
                transition:opacity 0.3s; opacity:0;
            `;
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.opacity = '0';
        }, 3000);
    }

    function markAllRead(btn) {
        if (!btn) return;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${messages.marking}`;

        fetch('../api/marknotificationread.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_all_read: true })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();

                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.style.background = '';
                        item.style.borderLeft = '';
                        const newBadge = item.querySelector('span[style*="e53e3e"]');
                        if (newBadge) newBadge.remove();
                    });

                    btn.style.display = 'none';
                    showToast(messages.marked);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    showToast(messages.markError, 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                showToast(messages.serverError, 'error');
            });
    }

    function initializeCharts() {
        const createChart = (ctx, config) => {
            if (!ctx || typeof Chart === 'undefined') return;
            new Chart(ctx, config);
        };

        const assetsStatusCtx = document.getElementById('assetsStatusChart');
        if (assetsStatusCtx) {
            const statusLabels = assetsByStatus.map(item => (item.status || 'unknown').toUpperCase());
            const statusCounts = assetsByStatus.map(item => Number(item.count) || 0);
            createChart(assetsStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: chartColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 13 } } },
                        tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 14 } }
                    },
                    layout: { padding: { top: 8, bottom: 8 } }
                }
            });
        }

        const ticketsStatusCtx = document.getElementById('ticketsStatusChart');
        if (ticketsStatusCtx) {
            const tStatusLabels = ticketsByStatus.map(item => (item.status || 'unknown').toUpperCase());
            const tStatusCounts = ticketsByStatus.map(item => Number(item.count) || 0);
            createChart(ticketsStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: tStatusLabels,
                    datasets: [{
                        data: tStatusCounts,
                        backgroundColor: chartColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 13 } } },
                        tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 14 } }
                    },
                    layout: { padding: { top: 8, bottom: 8 } }
                }
            });
        }

        const ticketsPriorityCtx = document.getElementById('ticketsPriorityChart');
        if (ticketsPriorityCtx) {
            const priorityLabels = ticketsByPriority.map(item => (item.priority || '').toUpperCase());
            const priorityCounts = ticketsByPriority.map(item => Number(item.count) || 0);
            const priorityColors = ticketsByPriority.map(item => {
                switch ((item.priority || '').toLowerCase()) {
                    case 'urgent':
                        return '#f56565';
                    case 'high':
                        return '#ed8936';
                    case 'normal':
                        return '#4299e1';
                    case 'low':
                        return '#48bb78';
                    default:
                        return '#cbd5e0';
                }
            });
            createChart(ticketsPriorityCtx, {
                type: 'bar',
                data: {
                    labels: priorityLabels,
                    datasets: [{
                        label: 'Tickets',
                        data: priorityCounts,
                        backgroundColor: priorityColors,
                        borderColor: priorityColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 13 } }
                    },
                    layout: { padding: { top: 6, bottom: 6 } },
                    scales: {
                        x: { beginAtZero: true, ticks: { font: { family: 'Sarabun', size: 12 } } },
                        y: { ticks: { font: { family: 'Sarabun', size: 12 } } }
                    }
                }
            });
        }
    }

    function updateNotificationBadge() {
        fetch('../api/getnotificationcount.php')
            .then(r => r.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                const bell = document.querySelector('.notification-bell');
                if (!bell) return;
                if (Number(data.count) > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = data.count;
                        bell.appendChild(newBadge);
                    }
                } else if (badge) {
                    badge.remove();
                }
            })
            .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', () => {
        initializeCharts();
    });

    document.addEventListener('click', event => {
        const wrapper = document.querySelector('.notification-wrapper');
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown && wrapper && !wrapper.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    window.toggleNotifications = toggleNotifications;
    window.readAndViewTicket = readAndViewTicket;
    window.markAllRead = markAllRead;

    updateNotificationBadge();
    setInterval(updateNotificationBadge, 30000);
})();
