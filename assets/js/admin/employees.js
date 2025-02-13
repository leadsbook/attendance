

class EmployeeManager {
    constructor() {
        this.modal = document.getElementById('employeeModal');
        this.form = document.getElementById('employeeForm');
        this.addButton = document.getElementById('addEmployeeBtn');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Add employee button
        this.addButton.addEventListener('click', () => this.openModal());

        // Edit employee buttons
        document.querySelectorAll('.edit-employee').forEach(button => {
            button.addEventListener('click', (e) => this.editEmployee(e.currentTarget.dataset.id));
        });

        // View attendance buttons
        document.querySelectorAll('.view-attendance').forEach(button => {
            button.addEventListener('click', (e) => this.viewAttendance(e.currentTarget.dataset.id));
        });

        // View leaves buttons
        document.querySelectorAll('.view-leaves').forEach(button => {
            button.addEventListener('click', (e) => this.viewLeaves(e.currentTarget.dataset.id));
        });

        // Close modal button
        document.querySelector('.close-modal').addEventListener('click', () => this.closeModal());

        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });
    }

    openModal(employeeId = null) {
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        if (!employeeId) {
            this.form.reset();
            this.form.querySelector('#modalTitle').textContent = 'Add Employee';
            this.form.querySelector('#employeeId').value = '';
            this.form.querySelector('#password').required = true;
        }
    }

    closeModal() {
        this.modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async editEmployee(employeeId) {
        try {
            const response = await fetch(`../api/employee/get.php?id=${employeeId}`, {
                headers: {
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            const employee = data.employee;
            
            // Fill form with employee data
            this.form.querySelector('#modalTitle').textContent = 'Edit Employee';
            this.form.querySelector('#employeeId').value = employee.id;
            this.form.querySelector('#fullName').value = employee.full_name;
            this.form.querySelector('#empId').value = employee.employee_id;
            this.form.querySelector('#email').value = employee.email;
            this.form.querySelector('#phone').value = employee.phone;
            this.form.querySelector('#designation').value = employee.designation;
            this.form.querySelector('#department').value = employee.department;
            this.form.querySelector('#branch').value = employee.branch_id;
            this.form.querySelector('#joiningDate').value = employee.date_of_joining;
            this.form.querySelector('#username').value = employee.username;
            this.form.querySelector('#role').value = employee.role;
            
            // Password not required for edit
            this.form.querySelector('#password').required = false;
            
            this.openModal(employeeId);
        } catch (error) {
            console.error('Error fetching employee:', error);
            this.showNotification(error.message || 'Failed to fetch employee details', 'error');
        }
    }

    async handleSubmit(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(this.form);
            
            // Show loading state
            const submitButton = this.form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            const response = await fetch('../api/employee/save.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Employee saved successfully', 'success');
            this.closeModal();
            
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Submission error:', error);
            this.showNotification(error.message || 'Failed to save employee', 'error');
            
            // Reset button state
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }

    async viewAttendance(employeeId) {
        window.location.href = `attendance.php?employee_id=${employeeId}`;
    }

    async viewLeaves(employeeId) {
        window.location.href = `leaves.php?employee_id=${employeeId}`;
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
