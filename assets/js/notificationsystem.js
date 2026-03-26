/**
 * Romar IT Support - Notification System v1.0
 * Global notification handling for all dashboard pages
 */

(function() {
    'use strict';

    class NotificationSystem {
        constructor() {
            this.init();
        }

        init() {
            // Page-specific (dashboard/modules)
            this.button = document.getElementById('notification-button');
            this.dropdown = document.getElementById('notification-dropdown');
            this.badge = document.getElementById('notification-badge');
            this.markAllBtn = document.getElementById('mark-all-read');
            this.list = document.getElementById('notification-list');

            if (this.button) this.bindEvents();
            this.startPolling();
        }

        bindEvents() {
            if (!this.button) return;
            
            // Toggle dropdown
            this.button.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!this.button.contains(e.target)) {
                    this.close();
                }
            });

            // Mark all read
            if (this.markAllBtn) {
                this.markAllBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.markAllRead();
                });
            }

            // Individual notification clicks
            if (this.list) {
                this.list.addEventListener('click', (e) => {
                    const item = e.target.closest('.notification-item');
                    if (item) {
                        e.stopPropagation();
                        const notifId = item.dataset.notifId;
                        const ticketId = item.dataset.ticketId;
                        if (notifId && ticketId) {
                            this.markReadAndView(notifId, ticketId);
                        }
                    }
                });
            }
        }

        toggle() {
            this.dropdown.classList.toggle('show');
        }

        close() {
            this.dropdown.classList.remove('show');
        }

        async markReadAndView(notifId, ticketId) {
            try {
                await fetch('../api/marknotificationread.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notif_id: parseInt(notifId) })
                });
                window.location.href = `ticket_view.php?id=${ticketId}`;
            } catch (error) {
                this.showToast('เกิดข้อผิดพลาดในการอ่านการแจ้งเตือน');
            }
        }

        async markAllRead() {
            const btn = this.markAllBtn;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังอัปเดต...';

            try {
                const response = await fetch('../api/marknotificationread.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mark_all_read: true })
                });

                const data = await response.json();
                if (data.success) {
                    // Update UI immediately
                    this.updateBadge(0);
                    this.markAllUIAsRead();
                    btn.style.display = 'none';
                    this.showCompactToast('✅ อ่านแล้ว');
                } else {
                    throw new Error('API failed');
                }
            } catch (error) {
                btn.disabled = false;
                btn.textContent = originalText;
                this.showToast('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
            }
        }

        markAllUIAsRead() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
        }

        updateBadge(count) {
            if (count > 0) {
                if (!this.badge) {
                    this.badge = document.createElement('span');
                    this.badge.className = 'notification-badge';
                    this.button.appendChild(this.badge);
                }
                this.badge.textContent = count;
            } else if (this.badge) {
                this.badge.remove();
                this.badge = null;
            }
        }

        startPolling() {
            setInterval(async () => {
                try {
                    const response = await fetch('../api/getnotificationcount.php');
                    const data = await response.json();
                    this.updateBadge(data.count);
                } catch (error) {
                    console.log('Polling failed:', error);
                }
            }, 30000); // 30 seconds
        }

        showCompactToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast-compact toast-success';
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 200);
                }, 2000);
            }, 100);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new NotificationSystem());
    } else {
        new NotificationSystem();
    }
})();

