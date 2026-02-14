// ========================================
// ระบบการแจ้งเตือน (Notification System)
// ========================================

class NotificationManager {
    constructor() {
        this.updateInterval = 30000; // อัพเดททุก 30 วินาที
        this.intervalId = null;
    }

    // เริ่มต้นการทำงาน
    init() {
        this.updateNotificationCount();
        this.startAutoUpdate();
        this.attachEventListeners();
    }

    // อัพเดทจำนวนการแจ้งเตือน
    async updateNotificationCount() {
        try {
            const response = await fetch('api/getnotificationcount.php');
            const data = await response.json();
            
            const badge = document.getElementById('notification-badge');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error updating notification count:', error);
        }
    }

    // ดึงรายการการแจ้งเตือนทั้งหมด
    async getNotifications() {
        try {
            const response = await fetch('api/getnotifications.php');
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return { notifications: [], unread_count: 0 };
        }
    }

    // ทำเครื่องหมายว่าอ่านแล้ว (ticket เดียว)
    async markAsRead(ticketId) {
        try {
            const response = await fetch('api/marknotificationread.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ticket_id: ticketId })
            });
            
            const data = await response.json();
            if (data.success) {
                // อัพเดทจำนวนการแจ้งเตือน
                await this.updateNotificationCount();
            }
            return data;
        } catch (error) {
            console.error('Error marking notification as read:', error);
            return { success: false };
        }
    }

    // ทำเครื่องหมายว่าอ่านทั้งหมด
    async markAllAsRead() {
        try {
            const response = await fetch('api/marknotificationread.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mark_all_read: true })
            });
            
            const data = await response.json();
            if (data.success) {
                // อัพเดทจำนวนการแจ้งเตือน
                await this.updateNotificationCount();
                // รีเฟรชรายการ
                await this.displayNotifications();
            }
            return data;
        } catch (error) {
            console.error('Error marking all as read:', error);
            return { success: false };
        }
    }

    // แสดงรายการการแจ้งเตือน
    async displayNotifications() {
        const data = await this.getNotifications();
        const container = document.getElementById('notification-list');
        
        if (!container) return;

        if (data.notifications.length === 0) {
            container.innerHTML = '<p class="no-notifications">ไม่มีการแจ้งเตือน</p>';
            return;
        }

        let html = '';
        data.notifications.forEach(notif => {
            const readClass = notif.is_read ? 'read' : 'unread';
            const priorityClass = `priority-${notif.priority}`;
            
            html += `
                <div class="notification-item ${readClass}" data-ticket-id="${notif.id}">
                    <div class="notification-header">
                        <span class="notification-title">${notif.title}</span>
                        <span class="notification-badge ${priorityClass}">${notif.priority}</span>
                    </div>
                    <div class="notification-meta">
                        <span>โดย: ${notif.created_by_name}</span>
                        <span>${this.formatDate(notif.created_at)}</span>
                    </div>
                    ${!notif.is_read ? '<span class="unread-indicator">●</span>' : ''}
                </div>
            `;
        });

        container.innerHTML = html;

        // เพิ่ม event listeners สำหรับการคลิก
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                const ticketId = e.currentTarget.dataset.ticketId;
                
                // ทำเครื่องหมายว่าอ่านแล้ว
                await this.markAsRead(ticketId);
                
                // ไปที่หน้า ticket
                window.location.href = `ticket_detail.php?id=${ticketId}`;
            });
        });
    }

    // จัดรูปแบบวันที่
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'เมื่อสักครู่';
        if (diffMins < 60) return `${diffMins} นาทีที่แล้ว`;
        if (diffHours < 24) return `${diffHours} ชั่วโมงที่แล้ว`;
        if (diffDays < 7) return `${diffDays} วันที่แล้ว`;
        
        return date.toLocaleDateString('th-TH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // เริ่มการอัพเดทอัตโนมัติ
    startAutoUpdate() {
        this.intervalId = setInterval(() => {
            this.updateNotificationCount();
        }, this.updateInterval);
    }

    // หยุดการอัพเดทอัตโนมัติ
    stopAutoUpdate() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    // ผูก event listeners
    attachEventListeners() {
        // ปุ่มแสดงการแจ้งเตือน
        const notifBtn = document.getElementById('notification-button');
        if (notifBtn) {
            notifBtn.addEventListener('click', async () => {
                await this.displayNotifications();
                // แสดง dropdown หรือ modal
                const dropdown = document.getElementById('notification-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                }
            });
        }

        // ปุ่มทำเครื่องหมายว่าอ่านทั้งหมด
        const markAllBtn = document.getElementById('mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
                await this.markAllAsRead();
            });
        }

        // ปิด dropdown เมื่อคลิกข้างนอก
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notification-dropdown');
            const notifBtn = document.getElementById('notification-button');
            
            if (dropdown && notifBtn) {
                if (!dropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });
    }
}

// เริ่มต้นเมื่อโหลดหน้าเว็บเสร็จ
document.addEventListener('DOMContentLoaded', () => {
    const notificationManager = new NotificationManager();
    notificationManager.init();
    
    // เก็บ instance ไว้ใน window เพื่อใช้งานจากที่อื่น
    window.notificationManager = notificationManager;
});

// ========================================
// ตัวอย่างการใช้งาน
// ========================================

/*
// HTML ตัวอย่าง:

<div class="notification-container">
    <button id="notification-button" class="notification-btn">
        <i class="icon-bell"></i>
        <span id="notification-badge" class="badge">0</span>
    </button>
    
    <div id="notification-dropdown" class="notification-dropdown">
        <div class="notification-header">
            <h3>การแจ้งเตือน</h3>
            <button id="mark-all-read">อ่านทั้งหมด</button>
        </div>
        <div id="notification-list" class="notification-list">
            <!-- รายการจะถูกเพิ่มที่นี่ -->
        </div>
    </div>
</div>

// CSS ตัวอย่าง:

.notification-btn {
    position: relative;
}

.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: red;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    display: none;
}

.notification-dropdown {
    display: none;
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    width: 350px;
    max-height: 500px;
    overflow-y: auto;
}

.notification-dropdown.show {
    display: block;
}

.notification-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.notification-item:hover {
    background: #f5f5f5;
}

.notification-item.unread {
    background: #e3f2fd;
    font-weight: bold;
}

.unread-indicator {
    color: #2196F3;
    font-size: 20px;
    margin-left: 5px;
}

*/