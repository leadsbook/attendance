
class BranchManager {
    constructor() {
        this.branchModal = document.getElementById('branchModal');
        this.weeklyOffsModal = document.getElementById('weeklyOffsModal');
        this.holidaysModal = document.getElementById('holidaysModal');
        
        this.branchForm = document.getElementById('branchForm');
        this.weeklyOffsForm = document.getElementById('weeklyOffsForm');
        this.addHolidayForm = document.getElementById('addHolidayForm');
        
        this.addButton = document.getElementById('addBranchBtn');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Add branch button
        this.addButton.addEventListener('click', () => this.openBranchModal());

        // Edit branch buttons
        document.querySelectorAll('.edit-branch').forEach(button => {
            button.addEventListener('click', (e) => {
                const branchId = e.currentTarget.closest('.branch-card').dataset.id;
                this.editBranch(branchId);
            });
        });

        // Manage weekly offs buttons
        document.querySelectorAll('.manage-weekly-offs').forEach(button => {
            button.addEventListener('click', (e) => {
                const branchId = e.currentTarget.closest('.branch-card').dataset.id;
                this.manageWeeklyOffs(branchId);
            });
        });

        // Manage holidays buttons
        document.querySelectorAll('.manage-holidays').forEach(button => {
            button.addEventListener('click', (e) => {
                const branchId = e.currentTarget.closest('.branch-card').dataset.id;
                this.manageHolidays(branchId);
            });
        });

        // Form submissions
        this.branchForm.addEventListener('submit', (e) => this.handleBranchSubmit(e));
        this.weeklyOffsForm.addEventListener('submit', (e) => this.handleWeeklyOffsSubmit(e));
        this.addHolidayForm.addEventListener('submit', (e) => this.handleHolidaySubmit(e));

        // Close modal buttons
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', () => this.closeAllModals());
        });

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });
    }

    openBranchModal(branchId = null) {
        this.branchModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        if (!branchId) {
            this.branchForm.reset();
            this.branchForm.querySelector('#branchId').value = branch.id;
            this.branchForm.querySelector('#branchName').value = branch.name;
            this.branchForm.querySelector('#location').value = branch.location;
            this.branchForm.querySelector('#timezone').value = branch.timezone;
            this.branchForm.querySelector('#shiftStart').value = branch.shift_start_time;
            this.branchForm.querySelector('#shiftEnd').value = branch.shift_end_time;
            this.branchForm.querySelector('#gracePeriod').value = branch.grace_period_minutes;
            this.branchForm.querySelector('#halfDayMinutes').value = branch.half_day_after_minutes;
            
            this.openBranchModal(branchId);
        } catch (error) {
            console.error('Error fetching branch:', error);
            this.showNotification(error.message || 'Failed to fetch branch details', 'error');
        }
    }

    async manageWeeklyOffs(branchId) {
        try {
            const response = await fetch(`../api/branch/weekly-offs.php?id=${branchId}`, {
                headers: {
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            // Reset form
            this.weeklyOffsForm.reset();
            
            // Set branch ID
            this.weeklyOffsForm.querySelector('#weeklyOffsBranchId').value = branchId;
            
            // Check boxes for existing weekly offs
            data.weekly_offs.forEach(day => {
                const checkbox = this.weeklyOffsForm.querySelector(`input[value="${day}"]`);
                if (checkbox) checkbox.checked = true;
            });
            
            this.weeklyOffsModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } catch (error) {
            console.error('Error fetching weekly offs:', error);
            this.showNotification(error.message || 'Failed to fetch weekly offs', 'error');
        }
    }

    async manageHolidays(branchId) {
        try {
            await this.loadHolidays(branchId);
            
            this.addHolidayForm.querySelector('#holidaysBranchId').value = branchId;
            
            this.holidaysModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } catch (error) {
            console.error('Error managing holidays:', error);
            this.showNotification(error.message || 'Failed to load holidays', 'error');
        }
    }

    async loadHolidays(branchId) {
        const response = await fetch(`../api/branch/holidays.php?id=${branchId}`, {
            headers: {
                'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
            }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }

        const holidaysList = this.holidaysModal.querySelector('.holidays-list');
        holidaysList.innerHTML = '';

        data.holidays.forEach(holiday => {
            const holidayElement = document.createElement('div');
            holidayElement.className = 'holiday-item';
            holidayElement.innerHTML = `
                <div class="holiday-info">
                    <span class="holiday-name">${holiday.name}</span>
                    <span class="holiday-date">${new Date(holiday.date).toLocaleDateString()}</span>
                </div>
                <button type="button" class="btn btn-icon delete-holiday" 
                        data-id="${holiday.id}" title="Delete Holiday">
                    🗑️
                </button>
            `;
            
            holidayElement.querySelector('.delete-holiday').addEventListener('click', () => 
                this.deleteHoliday(holiday.id)
            );
            
            holidaysList.appendChild(holidayElement);
        });
    }

    async handleBranchSubmit(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(this.branchForm);
            
            // Show loading state
            const submitButton = this.branchForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            const response = await fetch('../api/branch/save.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Branch saved successfully', 'success');
            this.closeAllModals();
            
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Submission error:', error);
            this.showNotification(error.message || 'Failed to save branch', 'error');
            
            // Reset button state
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }

    async handleWeeklyOffsSubmit(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(this.weeklyOffsForm);
            
            const response = await fetch('../api/branch/save-weekly-offs.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Weekly offs saved successfully', 'success');
            this.closeAllModals();
            
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Submission error:', error);
            this.showNotification(error.message || 'Failed to save weekly offs', 'error');
        }
    }

    async handleHolidaySubmit(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(this.addHolidayForm);
            
            const response = await fetch('../api/branch/add-holiday.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            // Reset form
            this.addHolidayForm.reset();
            
            // Reload holidays list
            await this.loadHolidays(formData.get('branch_id'));
            
            this.showNotification('Holiday added successfully', 'success');
        } catch (error) {
            console.error('Submission error:', error);
            this.showNotification(error.message || 'Failed to add holiday', 'error');
        }
    }

    async deleteHoliday(holidayId) {
        if (!confirm('Are you sure you want to delete this holiday?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('holiday_id', holidayId);
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

            const response = await fetch('../api/branch/delete-holiday.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            // Reload holidays list
            await this.loadHolidays(this.addHolidayForm.querySelector('#holidaysBranchId').value);
            
            this.showNotification('Holiday deleted successfully', 'success');
        } catch (error) {
            console.error('Delete error:', error);
            this.showNotification(error.message || 'Failed to delete holiday', 'error');
        }
    }

    closeAllModals() {
        this.branchModal.style.display = 'none';
        this.weeklyOffsModal.style.display = 'none';
        this.holidaysModal.style.display = 'none';
        document.body.style.overflow = 'auto';
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

// Initialize branch manager
const branchManager = new BranchManager();('#modalTitle').textContent = 'Add Branch';
            this.branchForm.querySelector('#branchId').value = '';
        }
    }

    async editBranch(branchId) {
        try {
            const response = await fetch(`../api/branch/get.php?id=${branchId}`, {
                headers: {
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            const branch = data.branch;
            
            // Fill form with branch data
            this.branchForm.querySelector('#modalTitle').textContent = 'Edit Branch';
            this.branchForm.querySelector('#branchId').value = branch.id;
            this.branchForm.querySelector('#branchName').value = branch.name;
            this.branchForm.querySelector
