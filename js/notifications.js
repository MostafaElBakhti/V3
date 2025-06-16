// js/notifications.js - Complete notification system JavaScript
class NotificationSystem {
    constructor() {
        this.isOpen = false;
        this.unreadCount = 0;
        this.notifications = [];
        this.pollingInterval = null;
        this.init();
    }
    
    init() {
        this.createNotificationElements();
        this.bindEvents();
        this.startPolling();
        this.fetchNotifications();
    }
    
    createNotificationElements() {
        // Create toast container
        if (!document.querySelector('.toast-container')) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                pointer-events: none;
            `;
            document.body.appendChild(toastContainer);
        }
        
        // Find notification bell (should already exist in header)
        this.bellElement = document.querySelector('.notification-bell');
        if (!this.bellElement) {
            console.warn('Notification bell element not found');
            return;
        }
        
        // Create badge if it doesn't exist
        if (!this.bellElement.querySelector('.notification-badge')) {
            const badge = document.createElement('span');
            badge.className = 'notification-badge hidden';
            badge.style.cssText = `
                position: absolute;
                top: -8px;
                right: -8px;
                background: #ef4444;
                color: white;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 6px;
                border-radius: 10px;
                min-width: 18px;
                text-align: center;
                animation: pulse 2s infinite;
            `;
            this.bellElement.appendChild(badge);
        }
        
        this.badgeElement = this.bellElement.querySelector('.notification-badge');
        
        // Create dropdown if it doesn't exist
        if (!this.bellElement.querySelector('.notification-dropdown')) {
            const dropdown = document.createElement('div');
            dropdown.className = 'notification-dropdown';
            dropdown.style.cssText = `
                position: absolute;
                top: 100%;
                right: 0;
                width: 380px;
                max-height: 480px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid #e2e8f0;
                z-index: 1000;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.3s ease;
            `;
            dropdown.innerHTML = this.getDropdownHTML();
            this.bellElement.appendChild(dropdown);
        }
        
        this.dropdownElement = this.bellElement.querySelector('.notification-dropdown');
        
        // Add required CSS if not already present
        this.addRequiredCSS();
    }
    
    addRequiredCSS() {
        if (document.getElementById('notification-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification-badge.hidden { display: none; }
            .notification-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
            
            .notification-header {
                padding: 20px 24px 16px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .notification-title {
                font-size: 18px;
                font-weight: 700;
                color: #1a1a1a;
            }
            
            .notification-actions {
                display: flex;
                gap: 8px;
            }
            
            .notification-action-btn {
                padding: 6px 12px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 12px;
                color: #64748b;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .notification-action-btn:hover {
                background: #e2e8f0;
            }
            
            .notification-list {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .notification-item {
                padding: 16px 24px;
                border-bottom: 1px solid #f1f5f9;
                cursor: pointer;
                transition: all 0.2s;
                position: relative;
            }
            
            .notification-item:hover {
                background: #f8fafc;
            }
            
            .notification-item.unread {
                background: #eff6ff;
                border-left: 4px solid #3b82f6;
            }
            
            .notification-item.unread::before {
                content: '';
                position: absolute;
                top: 20px;
                right: 20px;
                width: 8px;
                height: 8px;
                background: #3b82f6;
                border-radius: 50%;
            }
            
            .notification-content {
                display: flex;
                gap: 12px;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .notification-icon.application {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            }
            
            .notification-icon.message {
                background: linear-gradient(135deg, #10b981, #059669);
            }
            
            .notification-icon.task_status {
                background: linear-gradient(135deg, #f59e0b, #d97706);
            }
            
            .notification-icon.review {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            }
            
            .notification-text {
                flex: 1;
            }
            
            .notification-message {
                font-size: 14px;
                color: #1a1a1a;
                line-height: 1.4;
                margin-bottom: 4px;
            }
            
            .notification-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .notification-time {
                font-size: 12px;
                color: #64748b;
            }
            
            .notification-delete {
                background: none;
                border: none;
                color: #64748b;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                opacity: 0;
                transition: all 0.2s;
            }
            
            .notification-item:hover .notification-delete {
                opacity: 1;
            }
            
            .notification-delete:hover {
                background: #fef2f2;
                color: #ef4444;
            }
            
            .notification-empty {
                padding: 40px 24px;
                text-align: center;
                color: #64748b;
            }
            
            .notification-empty-icon {
                width: 48px;
                height: 48px;
                background: #f1f5f9;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 16px;
            }
            
            .notification-footer {
                padding: 16px 24px;
                border-top: 1px solid #f1f5f9;
                text-align: center;
            }
            
            .view-all-btn {
                color: #3b82f6;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                padding: 8px 16px;
                border-radius: 8px;
                transition: all 0.2s;
            }
            
            .view-all-btn:hover {
                background: #eff6ff;
            }
            
            .toast {
                background: white;
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                border-left: 4px solid #3b82f6;
                min-width: 300px;
                max-width: 400px;
                opacity: 0;
                transform: translateX(400px);
                transition: all 0.3s ease;
                pointer-events: auto;
                position: relative;
            }
            
            .toast.show {
                opacity: 1;
                transform: translateX(0);
            }
            
            .toast.success {
                border-left-color: #10b981;
            }
            
            .toast.error {
                border-left-color: #ef4444;
            }
            
            .toast.warning {
                border-left-color: #f59e0b;
            }
            
            .toast-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            
            .toast-icon {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
                margin-top: 2px;
            }
            
            .toast-text {
                flex: 1;
            }
            
            .toast-title {
                font-weight: 600;
                color: #1a1a1a;
                margin-bottom: 4px;
                font-size: 14px;
            }
            
            .toast-message {
                color: #64748b;
                font-size: 13px;
                line-height: 1.4;
            }
            
            .toast-close {
                position: absolute;
                top: 8px;
                right: 8px;
                background: none;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s;
            }
            
            .toast-close:hover {
                background: #f1f5f9;
                color: #64748b;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            
            @media (max-width: 768px) {
                .notification-dropdown {
                    width: calc(100vw - 32px);
                    right: -100px;
                }
                
                .toast-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }
                
                .toast {
                    min-width: auto;
                    max-width: none;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    getDropdownHTML() {
        return `
            <div class="notification-header">
                <div class="notification-title">Notifications</div>
                <div class="notification-actions">
                    <button class="notification-action-btn" onclick="notificationSystem.markAllAsRead()">
                        Mark all read
                    </button>
                </div>
            </div>
            <div class="notification-list" id="notificationList">
                <!-- Notifications will be loaded here -->
            </div>
            <div class="notification-footer">
                <a href="notifications-page.php" class="view-all-btn">View all notifications</a>
            </div>
        `;
    }
    
    bindEvents() {
        // Toggle dropdown
        this.bellElement.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.bellElement.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // Handle notification clicks
        this.dropdownElement.addEventListener('click', (e) => {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem) {
                const notificationId = notificationItem.dataset.notificationId;
                this.handleNotificationClick(notificationId, notificationItem);
            }
        });
    }
    
    startPolling() {
        // Poll for new notifications every 30 seconds
        this.pollingInterval = setInterval(() => {
            this.fetchNotifications();
        }, 30000);
        
        // Also check when window regains focus
        window.addEventListener('focus', () => {
            this.fetchNotifications();
        });
    }
    
    async fetchNotifications() {
        try {
            const response = await fetch('notifications.php?action=fetch&limit=10');
            const data = await response.json();
            
            if (data.success) {
                const oldCount = this.unreadCount;
                this.notifications = data.notifications;
                this.unreadCount = data.unread_count;
                
                this.updateBadge();
                this.updateDropdown();
                
                // Show toast for new notifications
                if (this.unreadCount > oldCount && oldCount > 0) {
                    const newNotifications = this.unreadCount - oldCount;
                    this.showToast('info', 'New Notifications', `You have ${newNotifications} new notification${newNotifications > 1 ? 's' : ''}`);
                }
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }
    
    updateBadge() {
        if (this.unreadCount > 0) {
            this.badgeElement.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            this.badgeElement.classList.remove('hidden');
        } else {
            this.badgeElement.classList.add('hidden');
        }
    }
    
    updateDropdown() {
        const listElement = this.dropdownElement.querySelector('#notificationList');
        
        if (this.notifications.length === 0) {
            listElement.innerHTML = `
                <div class="notification-empty">
                    <div class="notification-empty-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <div>No notifications yet</div>
                </div>
            `;
        } else {
            listElement.innerHTML = this.notifications.map(notification => this.renderNotification(notification)).join('');
        }
    }
    
    renderNotification(notification) {
        const iconSvg = this.getNotificationIcon(notification.type);
        const isUnread = !notification.is_read;
        
        return `
            <div class="notification-item ${isUnread ? 'unread' : ''}" 
                 data-notification-id="${notification.id}"
                 data-type="${notification.type}"
                 data-related-id="${notification.related_id || ''}">
                <div class="notification-content">
                    <div class="notification-icon ${notification.type}">
                        ${iconSvg}
                    </div>
                    <div class="notification-text">
                        <div class="notification-message">${this.escapeHtml(notification.content)}</div>
                        <div class="notification-meta">
                            <span class="notification-time">${notification.time_ago}</span>
                            <button class="notification-delete" onclick="notificationSystem.deleteNotification(${notification.id}, event)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6L6 18"/>
                                    <path d="M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    getNotificationIcon(type) {
        const icons = {
            application: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>',
            message: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            task_status: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
            review: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>'
        };
        return icons[type] || icons.message;
    }
    
    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    openDropdown() {
        this.dropdownElement.classList.add('active');
        this.isOpen = true;
        this.fetchNotifications(); // Refresh when opening
    }
    
    closeDropdown() {
        this.dropdownElement.classList.remove('active');
        this.isOpen = false;
    }
    
    async handleNotificationClick(notificationId, element) {
        // Mark as read
        if (element.classList.contains('unread')) {
            await this.markAsRead(notificationId);
            element.classList.remove('unread');
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            this.updateBadge();
        }
        
        // Handle navigation based on notification type
        const type = element.dataset.type;
        const relatedId = element.dataset.relatedId;
        
        switch (type) {
            case 'message':
                if (relatedId) {
                    window.location.href = `helper-messages.php?task_id=${relatedId}`;
                }
                break;
            case 'application':
                window.location.href = 'my-applications.php';
                break;
            case 'task_status':
                if (relatedId) {
                    window.location.href = `task-details.php?id=${relatedId}`;
                }
                break;
            case 'review':
                window.location.href = 'my-jobs.php';
                break;
        }
        
        this.closeDropdown();
    }
    
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            await fetch('notifications.php?action=mark_read', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            await fetch('notifications.php?action=mark_all_read', {
                method: 'POST'
            });
            
            this.unreadCount = 0;
            this.updateBadge();
            
            // Update UI
            this.dropdownElement.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            this.showToast('success', 'All notifications marked as read');
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.showToast('error', 'Failed to mark notifications as read');
        }
    }
    
    async deleteNotification(notificationId, event) {
        event.stopPropagation();
        
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            const response = await fetch('notifications.php?action=delete', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove from UI
                const notificationElement = this.dropdownElement.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationElement) {
                    if (notificationElement.classList.contains('unread')) {
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        this.updateBadge();
                    }
                    notificationElement.remove();
                }
                
                // Update notifications array
                this.notifications = this.notifications.filter(n => n.id != notificationId);
                
                // Update UI if no notifications left
                if (this.notifications.length === 0) {
                    this.updateDropdown();
                }
                
                this.showToast('success', 'Notification deleted');
            } else {
                this.showToast('error', 'Failed to delete notification');
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.showToast('error', 'Failed to delete notification');
        }
    }
    
    showToast(type, title, message = '') {
        const toastContainer = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const iconSvg = this.getToastIcon(type);
        
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">${iconSvg}</div>
                <div class="toast-text">
                    <div class="toast-title">${this.escapeHtml(title)}</div>
                    ${message ? `<div class="toast-message">${this.escapeHtml(message)}</div>` : ''}
                </div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18"/>
                    <path d="M6 6l12 12"/>
                </svg>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    getToastIcon(type) {
        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
        };
        return icons[type] || icons.info;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    destroy() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    }
}

// Initialize notification system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.notificationSystem = new NotificationSystem();
});

// Clean up when page unloads
window.addEventListener('beforeunload', function() {
    if (window.notificationSystem) {
        window.notificationSystem.destroy();
    }
});

// Export for module usage (optional)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}