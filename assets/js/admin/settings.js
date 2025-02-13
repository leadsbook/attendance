
class SettingsManager {
    constructor() {
        this.navItems = document.querySelectorAll('.settings-nav .nav-item');
        this.sections = document.querySelectorAll('.settings-section');
        this.forms = document.querySelectorAll('.settings-form');
        this.testEmailBtn = document.getElementById('testEmail');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Navigation
        this.navItems.forEach(item => {
            item.addEventListener('click', () => this.switchSection(item.dataset.target));
        });

        // Form submissions
        this.forms.forEach(form => {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        });

        // Test email button
        if (this.testEmailBtn) {
            this.testEmailBtn.addEventListener('click', () => this.testEmailSettings());
        }
    }

    switchSection(targetId) {
        // Update navigation
        this.navItems.forEach(item => {
            item.classList.toggle('active', item.dataset.target === targetId);
        });

        // Update sections
        this.sections.forEach(section => {
            section.classList.toggle('active', section.id === targetId);
        });
    }

    async handleSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const section = form.dataset.section;
        
        try {
            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            const formData = new FormData(form);
            formData.append('section', section);

            const response = await fetch('../api/settings/save.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Settings saved successfully', 'success');
        } catch (error) {
            console.error('Settings save error:', error);
            this.showNotification(error.message || 'Failed to save settings', 'error');
        } finally {
            // Reset button state
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }

    async testEmailSettings() {
        const form = document.querySelector('[data-section="email"]');
        const testButton = document.getElementById('testEmail');
        
        try {
            // Show loading state
            testButton.disabled = true;
            testButton.textContent = 'Testing...';

            const formData = new FormData(form);
            formData.append('action', 'test');

            const response = await fetch('../api/settings/test-email.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Test email sent successfully', 'success');
        } catch (error) {
            console.error('Email test error:', error);
            this.showNotification(error.message || 'Failed to send test email', 'error');
        } finally {
            // Reset button state
            testButton.disabled = false;
            testButton.textContent = 'Test Email';
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

// Initialize settings manager
const settingsManager = new SettingsManager();
