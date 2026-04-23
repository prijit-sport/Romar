/**
 * Romar IT Support - Notification System (Global)
 */

(function() {
    'use strict';

    const DEFAULT_TEXTS = {
        empty: 'No notifications yet',
        loading: 'Loading notifications...',
        error: 'Server unavailable',
        marked: 'All notifications marked',
        markError: 'Unable to mark notifications'
    };

    class NotificationSystem {
        constructor() {
            this.button = null;
            this.dropdown = null;
            this.badge = null;
            this.markAllBtn = null;
            this.list = null;
            this.apiUrls = null;
            this.loading = false;
            this.texts = { ...DEFAULT_TEXTS };
            this.markAllLabel = '';
            this.markAllTemplate = '';
            this.apiUrls = this.buildApiUrls();
            this.init();
        }

        init() {
            this.button = document.getElementById('notification-button');
            this.dropdown = document.getElementById('notification-dropdown');
            this.badge = document.getElementById('notification-badge');
            this.markAllBtn = document.getElementById('mark-all-read');
            this.list = document.getElementById('notification-list');

            if (!this.button || !this.dropdown || !this.list) {
                return;
            }

            this.texts = this.resolveTexts();
            this.markAllLabel = this.markAllBtn?.textContent.trim() || '';
            this.markAllTemplate = this.markAllBtn?.innerHTML || this.markAllLabel;
            this.bindEvents();
            this.loadNotifications();
            this.startPolling();
        }

        buildApiUrls() {
            const base = this.resolveApiBase();
            return {
                count: `${base}getnotificationcount.php`,
                list: `${base}getnotifications.php`,
                mark: `${base}marknotificationread.php`
            };
        }

        resolveApiBase() {
            let base = '';
            if (typeof window.ROMAR_API_BASE === 'string' && window.ROMAR_API_BASE.trim() !== '') {
                base = window.ROMAR_API_BASE;
            } else if (typeof window.ROMAR_BASE_URL === 'string' && window.ROMAR_BASE_URL.trim() !== '') {
                base = `${window.ROMAR_BASE_URL}api/`;
            } else {
                base = '../api/';
            }
            return base.replace(/\/+$/, '') + '/';
        }

        resolveTexts() {
            const placeholder = document.getElementById('notification-empty');
            const markBtn = document.getElementById('mark-all-read');
            return {
                empty: placeholder?.dataset.emptyText?.trim() || placeholder?.textContent?.trim() || DEFAULT_TEXTS.empty,
                loading: placeholder?.dataset.loadingText?.trim() || DEFAULT_TEXTS.loading,
                error: placeholder?.dataset.errorText?.trim() || DEFAULT_TEXTS.error,
                marked: markBtn?.dataset.successText?.trim() || DEFAULT_TEXTS.marked,
                markError: markBtn?.dataset.errorText?.trim() || DEFAULT_TEXTS.markError
            };
        }

        bindEvents() {
            if (!this.button) return;

            this.button.addEventListener('click', (event) => {
                event.stopPropagation();
                this.toggle();
            });

            document.addEventListener('click', (event) => {
                if (!this.button.contains(event.target)) {
                    this.close();
                }
            });

            if (this.markAllBtn) {
                this.markAllBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.markAllRead();
                });
            }

            if (this.list) {
                this.list.addEventListener('click', (event) => {
                    const item = event.target.closest('.notification-item');
                    if (item) {
                        event.stopPropagation();
                        const notifId = item.dataset.notifId;
                        const ticketId = item.dataset.ticketId;
                        const notifType = item.dataset.notifType;
                        if (notifId && ticketId) {
                            this.markReadAndView(notifId, ticketId, notifType);
                        }
                    }
                });
            }
        }

        toggle() {
            if (!this.dropdown || !this.button) return;
            const isOpening = !this.dropdown.classList.contains('show');
            this.dropdown.classList.toggle('show');
            this.button.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
            if (isOpening) {
                this.loadNotifications();
            }
        }

        close() {
            if (!this.dropdown || !this.button) return;
            this.dropdown.classList.remove('show');
            this.button.setAttribute('aria-expanded', 'false');
        }

        async startPolling() {
            await this.updateBadge();
            this.pollTimer = setInterval(() => {
                this.updateBadge().catch(() => {});
            }, 30000);
        }

        async updateBadge() {
            if (!this.button) return;
            try {
                const response = await fetch(this.apiUrls.count, { credentials: 'include' });
                if (!response.ok) throw new Error('count');
                const data = await response.json();
                const count = parseInt(data.count, 10) || 0;
                this.setBadge(count);
                this.toggleMarkAllButton(count);
            } catch (error) {
                if (typeof APP_DEBUG !== 'undefined' && APP_DEBUG === true) {
                    console.warn('Notification count failed', error);
                }
            }
        }

        setBadge(count) {
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

        toggleMarkAllButton(count) {
            if (!this.markAllBtn) return;
            if (count > 0) {
                this.markAllBtn.style.display = '';
                this.markAllBtn.disabled = false;
                this.markAllBtn.innerHTML = this.markAllTemplate;
            } else {
                this.markAllBtn.style.display = 'none';
            }
        }

        async loadNotifications() {
            if (!this.list || this.loading) return;
            this.loading = true;
            this.renderPlaceholder(this.texts.loading);
            try {
                const response = await fetch(this.apiUrls.list, { credentials: 'include' });
                if (!response.ok) throw new Error('fetch failed');
                const data = await response.json();
                const notifications = Array.isArray(data.notifications) ? data.notifications : [];
                this.renderNotifications(notifications);
                const count = parseInt(data.unread_count, 10) || 0;
                this.setBadge(count);
                this.toggleMarkAllButton(count);
            } catch (error) {
                this.renderPlaceholder(this.texts.error);
                if (typeof APP_DEBUG !== 'undefined' && APP_DEBUG === true) {
                    console.warn('Notification list failed', error);
                }
            } finally {
                this.loading = false;
            }
        }

        renderPlaceholder(text) {
            if (!this.list) return;
            this.list.innerHTML = '';
            const placeholder = document.createElement('div');
            placeholder.className = 'no-notifications';
            placeholder.textContent = text;
            this.list.appendChild(placeholder);
        }

        renderNotifications(items) {
            if (!this.list) return;
            if (!items.length) {
                this.renderPlaceholder(this.texts.empty);
                return;
            }
            this.list.innerHTML = '';
            items.forEach(item => {
                this.list.appendChild(this.createNotificationItem(item));
            });
        }

        createNotificationItem(item) {
            const wrapper = document.createElement('div');
            wrapper.className = 'notification-item';
            if (!item.is_read) {
                wrapper.classList.add('unread');
            }
            if (item.notif_id) wrapper.dataset.notifId = item.notif_id;
            if (item.ticket_id) wrapper.dataset.ticketId = item.ticket_id;
            if (item.comment_id) wrapper.dataset.commentId = item.comment_id;
            if (item.type) wrapper.dataset.notifType = item.type;

            if (item.ticket_title) {
                const titleEl = document.createElement('div');
                titleEl.className = 'notification-ticket-title';
                const prefix = item.ticket_number ? `[${item.ticket_number}] ` : '';
                titleEl.textContent = `${prefix}${item.ticket_title}`;
                wrapper.appendChild(titleEl);
            }

            const messageEl = document.createElement('div');
            messageEl.className = 'notification-message';
            messageEl.textContent = item.message;
            wrapper.appendChild(messageEl);

            const badges = document.createElement('div');
            badges.className = 'notification-badges';
            if (item.ticket_priority) {
                const priority = document.createElement('span');
                priority.className = `badge badge-priority-${item.ticket_priority.toLowerCase()}`;
                priority.textContent = item.ticket_priority;
                badges.appendChild(priority);
            }
            if (item.ticket_status) {
                const normalized = item.ticket_status.toLowerCase().replace(/\s+/g, '_');
                const statusBadge = document.createElement('span');
                statusBadge.className = `badge badge-status-${normalized}`;
                statusBadge.textContent = item.ticket_status;
                badges.appendChild(statusBadge);
            }
            if (badges.children.length) {
                wrapper.appendChild(badges);
            }

            const meta = document.createElement('div');
            meta.className = 'notification-meta';
            if (item.triggered_by_name) {
                const actor = document.createElement('span');
                actor.textContent = item.triggered_by_name;
                meta.appendChild(actor);
            }
            if (item.time_ago) {
                const time = document.createElement('span');
                time.className = 'notification-time';
                time.textContent = item.time_ago;
                meta.appendChild(time);
            }
            if (meta.children.length) {
                wrapper.appendChild(meta);
            }

            return wrapper;
        }

        async markReadAndView(notifId, ticketId, type = '') {
            try {
                await fetch(this.apiUrls.mark, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ notif_id: parseInt(notifId, 10) })
                });
            } catch (error) {
                console.warn('notify mark read failed', error);
            } finally {
                const base = window.ROMAR_BASE_URL || '';
                const route = this.resolveTargetRoute(type);
                const target = base ? `${base}modules/${route}?id=${ticketId}` : `${route}?id=${ticketId}`;
                window.location.href = target;
            }
        }

        resolveTargetRoute(type) {
            if (!type) {
                return 'ticket_view.php';
            }
            const normalized = type.toLowerCase();
            if (normalized === 'new_ticket') {
                return 'ticket_update.php';
            }
            return 'ticket_view.php';
        }

        async markAllRead() {
            if (!this.markAllBtn) return;
            const btn = this.markAllBtn;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${this.markAllLabel}`;
            try {
                const response = await fetch(this.apiUrls.mark, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ mark_all_read: true })
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    this.setBadge(0);
                    this.markAllBtn.style.display = 'none';
                    this.showToast(this.texts.marked, 'success');
                    this.renderPlaceholder(this.texts.empty);
                } else {
                    throw new Error(data.message || 'mark failed');
                }
            } catch (error) {
                console.warn('mark all failed', error);
                btn.disabled = false;
                btn.innerHTML = this.markAllTemplate;
                this.showToast(this.texts.markError, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = this.markAllTemplate;
            }
        }

        showToast(message, type = 'success') {
            if (!message) return;
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 1300;
                padding: 0.75rem 1rem;
                border-radius: 0.75rem;
                background: ${type === 'error' ? '#b91c1c' : '#15803d'};
                color: white;
                font-family: 'Sarabun', sans-serif;
                font-size: 0.9rem;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
                opacity: 0;
                transition: opacity 0.25s ease, transform 0.25s ease;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            requestAnimationFrame(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(-4px)';
            });
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(0)';
                setTimeout(() => toast.remove(), 300);
            }, 3200);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new NotificationSystem());
    } else {
        new NotificationSystem();
    }
})();
