class LeaveManager {
    constructor() {
        this.form = document.getElementById('leaveForm');
        this.leaveType = document.getElementById('leaveType');
        this.startDate = document.getElementById('startDate');
        this.endDate = document.getElementById('endDate');
        this.submitButton = document.getElementById('submitLeave');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Date validation
        this.startDate.addEventListener('change', () => this.validateDates());
        this.endDate.addEventListener('change', () => this.validateDates());
        
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Leave type change
        this.leaveType.addEventListener('change', () => this.validateDates());
    }

    validateDates() {
        const start = new Date(this.startDate.value);
        const end = new Date(this.endDate.value);
        
        // Reset end date min attribute
        this.endDate.min = this.startDate.value;
        
        if (start > end) {
            this.endDate.value = this.startDate.value;
        }

        // Validate weekends and holidays
        if (this.startDate.value && this.endDate.value) {
            this.validateLeaveRequest();
        }
    }

    async validateLeaveRequest() {
        try {
            const formData = new FormData();
            formData.append('start_date', this.startDate.value);
            formData.append('end_date', this.endDate.value);
            formData.append('leave_type', this.leaveType.value);
            formData.append('csrf_token', this.form.querySelector('[name="csrf_token"]').value);

            const response = await fetch('../api/leave/validate.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                this.showError(result.message);
                this.submitButton.disabled = true;
            } else {
                this.clearError();
                this.submitButton.disabled = false;
            }
        } catch (error) {
            console.error('Validation error:', error);
            this.showError('Failed to validate leave request');
            this.submitButton.disabled = true;
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        if (this.submitButton.disabled) {
            return;
        }

        try {
            this.submitButton.disabled = true;
            this.submitButton.textContent = 'Submitting...';

            const formData = new FormData(this.form);
            
            const response = await fetch('../api/leave/apply.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Show success message and refresh page
                this.showSuccess('Leave application submitted successfully');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Submission error:', error);
            this.showError(error.message || 'Failed to submit leave application');
            this.submitButton.disabled = false;
            this.submitButton.textContent = 'Submit Leave Application';
        }
    }

    showError(message) {
        this.clearMessages();
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.textContent = message;
        this.form.insertBefore(errorDiv, this.form.firstChild);
    }

    showSuccess(message) {
        this.clearMessages();
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success';
        successDiv.textContent = message;
        this.form.insertBefore(successDiv, this.form.firstChild);
    }

    clearError() {
        const errorDiv = this.form.querySelector('.alert-danger');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    clearMessages() {
        const messages = this.form.querySelectorAll('.alert');
        messages.forEach(msg => msg.remove());
    }
}

// Initialize leave manager
const leaveManager = new LeaveManager();
