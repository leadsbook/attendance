
class AdminDashboard {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Leave approval buttons
        document.querySelectorAll('.approve-leave').forEach(button => {
            button.addEventListener('click', (e) => this.handleLeaveAction(e, 'approve'));
        });

        document.querySelectorAll('.reject-leave').forEach(button => {
            button.addEventListener('click', (e) => this.handleLeaveAction(e, 'reject'));
        });
    }

    async handleLeaveAction(event, action) {
        const button = event.currentTarget;
        const leaveId = button.dataset.id;
        
        if (!confirm(`Are you sure you want to ${action} this leave request?`)) {
            return;
        }

        try {
            button.disabled = true;
            const oppositeButton = button.parentElement.querySelector(
                action === 'approve' ? '.reject-leave' : '.approve-leave'
            );
            oppositeButton.disabled = true;

            const response = await fetch('../api/leave/process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    leave_id: leaveId,
                    action: action,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });

            const result = await response.json();

            if (result.success) {
                // Remove the leave request item from the UI
                const leaveItem = button.closest('.leave-request-item');
                leaveItem.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    leaveItem.remove();
                    this.updatePendingCount();
                }, 300);

                // Show success message
                this.showNotification(`Leave request ${action}ed successfully`, 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Leave action error:', error);
            this.showNotification(
                error.message || `Failed to ${action} leave request`,
                'error'
            );
            button.disabled = false;
            oppositeButton.disabled = false;
        }
    }

    updatePendingCount() {
        const pendingCountElement = document.querySelector('.stat-card:last-child .stat-value');
        if (pendingCountElement) {
            const currentCount = parseInt(pendingCountElement.textContent);
            pendingCountElement.textContent = Math.max(0, currentCount - 1);
        }
    }

    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Trigger animation
        setTimeout(() => notification.classList.add('show'), 10);

        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Initialize dashboard
const dashboard = new AdminDashboard();

// Add some CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-10px); }
    }

    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 2rem;
        border-radius: 4px;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transform: translateX(120%);
        transition: transform 0.3s ease-out;
        z-index: 1000;
    }

    .notification.show {
        transform: translateX(0);
    }

    .notification-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .notification-error {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
`;
document.head.appendChild(style);
