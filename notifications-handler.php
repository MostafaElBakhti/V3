<?php
// notifications-handler.php - Include this in your helper dashboard files
require_once 'cconfig.php';

// Function to get notifications for the current user
function getNotifications($pdo, $user_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                t.title as task_title,
                CASE 
                    WHEN n.type = 'application' THEN 'Application'
                    WHEN n.type = 'message' THEN 'Message'
                    WHEN n.type = 'task_status' THEN 'Job Update'
                    WHEN n.type = 'review' THEN 'Review'
                    ELSE 'Notification'
                END as type_label,
                CASE 
                    WHEN n.type = 'application' THEN '#10b981'
                    WHEN n.type = 'message' THEN '#3b82f6'
                    WHEN n.type = 'task_status' THEN '#f59e0b'
                    WHEN n.type = 'review' THEN '#8b5cf6'
                    ELSE '#64748b'
                END as type_color
            FROM notifications n
            LEFT JOIN tasks t ON n.related_id = t.id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

// Function to get unread notification count
function getUnreadCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch()['unread_count'];
    } catch (PDOException $e) {
        error_log("Error counting notifications: " . $e->getMessage());
        return 0;
    }
}

// Function to mark notification as read
function markAsRead($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Function to mark all notifications as read
function markAllAsRead($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

// AJAX Handler for notification actions
if (isset($_GET['action']) && isset($_SESSION['user_id'])) {
    $action = $_GET['action'];
    $user_id = $_SESSION['user_id'];
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'get_notifications':
            $notifications = getNotifications($pdo, $user_id);
            $unread_count = getUnreadCount($pdo, $user_id);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            exit;
            
        case 'mark_read':
            $notification_id = intval($_GET['id'] ?? 0);
            $success = markAsRead($pdo, $notification_id, $user_id);
            
            echo json_encode([
                'success' => $success,
                'unread_count' => getUnreadCount($pdo, $user_id)
            ]);
            exit;
            
        case 'mark_all_read':
            $success = markAllAsRead($pdo, $user_id);
            
            echo json_encode([
                'success' => $success,
                'unread_count' => 0
            ]);
            exit;
            
        case 'get_count':
            $unread_count = getUnreadCount($pdo, $user_id);
            
            echo json_encode([
                'success' => true,
                'unread_count' => $unread_count
            ]);
            exit;
    }
}

// If this is included in a dashboard file, get the initial data
if (isset($_SESSION['user_id']) && !isset($_GET['action'])) {
    $user_id = $_SESSION['user_id'];
    $notifications = getNotifications($pdo, $user_id, 5); // Get 5 recent notifications
    $unread_count = getUnreadCount($pdo, $user_id);
}
?>

<!-- Notification Dropdown HTML (Add this to your dashboard header) -->
<style>
/* Notification Styles */
.notification-container {
    position: relative;
    display: inline-block;
}

.notification-button {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    position: relative;
}

.notification-button:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    animation: pulse 2s infinite;
}

.notification-badge.hidden {
    display: none;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 380px;
    max-height: 500px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.notification-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.mark-all-read {
    background: none;
    border: none;
    color: #3b82f6;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s;
}

.mark-all-read:hover {
    background: #eff6ff;
}

.notifications-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
    display: flex;
    gap: 12px;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item.unread {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 20px;
    right: 20px;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-text {
    font-size: 14px;
    color: #1a1a1a;
    line-height: 1.4;
    margin-bottom: 4px;
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #64748b;
}

.notification-type {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.notification-time {
    font-weight: 500;
}

.notification-footer {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}

.view-all-link {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
    display: inline-block;
}

.view-all-link:hover {
    background: #eff6ff;
}

.empty-notifications {
    padding: 40px 24px;
    text-align: center;
    color: #64748b;
}

.empty-notifications svg {
    width: 48px;
    height: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.loading-notifications {
    padding: 20px;
    text-align: center;
    color: #64748b;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .notification-dropdown {
        width: 320px;
        right: -20px;
    }
    
    .notification-item {
        padding: 12px 16px;
    }
    
    .notification-header {
        padding: 16px 20px 12px;
    }
}
</style>

<!-- Notification HTML Structure -->
<div class="notification-container">
    <button class="notification-button" id="notificationButton" onclick="toggleNotifications()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notification-badge" id="notificationBadge">
            <?php echo isset($unread_count) && $unread_count > 0 ? ($unread_count > 99 ? '99+' : $unread_count) : ''; ?>
        </span>
    </button>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3 class="notification-title">Notifications</h3>
            <button class="mark-all-read" onclick="markAllAsRead()" id="markAllReadBtn">
                Mark all as read
            </button>
        </div>
        
        <div class="notifications-list" id="notificationsList">
            <div class="loading-notifications">
                <div class="loading-spinner"></div>
                Loading notifications...
            </div>
        </div>
        
        <div class="notification-footer">
            <a href="notifications.php" class="view-all-link">View all notifications</a>
        </div>
    </div>
</div>

<script>
// Notification System JavaScript
class NotificationSystem {
    constructor() {
        this.isOpen = false;
        this.notifications = [];
        this.unreadCount = 0;
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        // Load initial notifications
        this.loadNotifications();
        
        // Set up auto-refresh every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadNotifications(false); // Silent refresh
        }, 30000);
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const container = document.querySelector('.notification-container');
            if (!container.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeDropdown();
            }
        });
    }
    
    async loadNotifications(showLoading = true) {
        try {
            if (showLoading) {
                this.showLoading();
            }
            
            const response = await fetch('?action=get_notifications');
            const data = await response.json();
            
            if (data.success) {
                this.notifications = data.notifications;
                this.unreadCount = data.unread_count;
                this.updateBadge();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showError();
        }
    }
    
    updateBadge() {
        const badge = document.getElementById('notificationBadge');
        if (this.unreadCount > 0) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
    
    renderNotifications() {
        const container = document.getElementById('notificationsList');
        
        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }
        
        const notificationsHTML = this.notifications.map(notification => {
            const timeAgo = this.getTimeAgo(notification.created_at);
            const isUnread = !notification.is_read;
            
            return `
                <div class="notification-item ${isUnread ? 'unread' : ''}" 
                     onclick="handleNotificationClick(${notification.id}, '${notification.type}', ${notification.related_id})">
                    <div class="notification-icon" style="background: ${notification.type_color};">
                        ${this.getNotificationIcon(notification.type)}
                    </div>
                    <div class="notification-content">
                        <div class="notification-text">${notification.content}</div>
                        <div class="notification-meta">
                            <span class="notification-type">${notification.type_label}</span>
                            <span class="notification-time">${timeAgo}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = notificationsHTML;
    }
    
    getNotificationIcon(type) {
        const icons = {
            'application': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>',
            'message': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'task_status': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
            'review': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>'
        };
        return icons[type] || icons['application'];
    }
    
    getTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffInSeconds = Math.floor((now - time) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        
        return time.toLocaleDateString();
    }
    
    showLoading() {
        const container = document.getElementById('notificationsList');
        container.innerHTML = `
            <div class="loading-notifications">
                <div class="loading-spinner"></div>
                Loading notifications...
            </div>
        `;
    }
    
    showError() {
        const container = document.getElementById('notificationsList');
        container.innerHTML = `
            <div class="empty-notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="12" y1="18" x2="12.01" y2="18"/>
                </svg>
                <p>Failed to load notifications</p>
            </div>
        `;
    }
    
    async markAsRead(notificationId) {
        try {
            const response = await fetch(`?action=mark_read&id=${notificationId}`);
            const data = await response.json();
            
            if (data.success) {
                this.unreadCount = data.unread_count;
                this.updateBadge();
                
                // Update the notification in the list
                const notification = this.notifications.find(n => n.id == notificationId);
                if (notification) {
                    notification.is_read = true;
                }
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const response = await fetch('?action=mark_all_read');
            const data = await response.json();
            
            if (data.success) {
                this.unreadCount = 0;
                this.updateBadge();
                
                // Update all notifications as read
                this.notifications.forEach(n => n.is_read = true);
                this.renderNotifications();
                
                // Show success feedback
                this.showSuccessMessage('All notifications marked as read');
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }
    
    showSuccessMessage(message) {
        // Create a temporary success message
        const successDiv = document.createElement('div');
        successDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10001;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        `;
        successDiv.textContent = message;
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.remove();
        }, 3000);
    }
    
    openDropdown() {
        this.isOpen = true;
        document.getElementById('notificationDropdown').classList.add('active');
        this.loadNotifications();
    }
    
    closeDropdown() {
        this.isOpen = false;
        document.getElementById('notificationDropdown').classList.remove('active');
    }
    
    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Global functions for onclick handlers
function toggleNotifications() {
    window.notificationSystem.toggleDropdown();
}

function markAllAsRead() {
    window.notificationSystem.markAllAsRead();
}

function handleNotificationClick(notificationId, type, relatedId) {
    // Mark as read
    window.notificationSystem.markAsRead(notificationId);
    
    // Navigate based on notification type
    setTimeout(() => {
        switch (type) {
            case 'application':
                window.location.href = `my-applications.php`;
                break;
            case 'message':
                window.location.href = `helper-messages.php?task_id=${relatedId}`;
                break;
            case 'task_status':
                window.location.href = `my-jobs.php`;
                break;
            case 'review':
                window.location.href = `my-jobs.php`;
                break;
            default:
                window.location.href = `helper-dashboard.php`;
        }
    }, 200);
}

// Initialize notification system when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.notificationSystem = new NotificationSystem();
});

// Clean up when page unloads
window.addEventListener('beforeunload', function() {
    if (window.notificationSystem) {
        window.notificationSystem.destroy();
    }
});
</script>