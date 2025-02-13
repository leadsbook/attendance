
class AttendanceViewer {
    constructor() {
        this.selfieModal = document.getElementById('selfieModal');
        this.locationModal = document.getElementById('locationModal');
        this.editModal = document.getElementById('editAttendanceModal');
        this.editForm = document.getElementById('editAttendanceForm');
        this.exportBtn = document.getElementById('exportBtn');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // View selfie buttons
        document.querySelectorAll('.view-selfie').forEach(button => {
            button.addEventListener('click', (e) => this.showSelfie(e.currentTarget.dataset.path));
        });

        // View location buttons
        document.querySelectorAll('.view-location').forEach(button => {
            button.addEventListener('click', (e) => {
                const { lat, long } = e.currentTarget.dataset;
                this.showLocation(parseFloat(lat), parseFloat(long));
            });
        });

        // Edit attendance buttons
        document.querySelectorAll('.edit-attendance').forEach(button => {
            button.addEventListener('click', (e) => this.editAttendance(e.currentTarget.dataset.id));
        });

        // Close modal buttons
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', () => this.closeAllModals());
        });

        // Export button
        this.exportBtn.addEventListener('click', () => this.exportToExcel());

        // Edit form submission
        this.editForm.addEventListener('submit', (e) => this.handleEditSubmit(e));

        // Date filter validation
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        
        if (startDate && endDate) {
            startDate.addEventListener('change', () => this.validateDates(startDate, endDate));
            endDate.addEventListener('change', () => this.validateDates(startDate, endDate));
        }
    }

    showSelfie(path) {
        const selfieImage = document.getElementById('selfieImage');
        selfieImage.src = `../uploads/attendance/${path}`;
        this.selfieModal.style.display = 'block';
    }

    showLocation(lat, long) {
        this.locationModal.style.display = 'block';
        
        // Initialize map if not already initialized
        if (!this.map) {
            this.map = new google.maps.Map(document.getElementById('locationMap'), {
                zoom: 15,
                center: { lat, lng: long }
            });
            this.marker = new google.maps.Marker({
                map: this.map,
                position: { lat, lng: long }
            });
        } else {
            const position = { lat, lng: long };
            this.map.setCenter(position);
            this.marker.setPosition(position);
        }
    }

    async editAttendance(id) {
        try {
            const response = await fetch(`../api/attendance/get.php?id=${id}`, {
                headers: {
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            const attendance = data.attendance;
            
            // Fill form with attendance data
            this.editForm.querySelector('#attendanceId').value = attendance.id;
            this.editForm.querySelector('#attendanceStatus').value = attendance.status;
            this.editForm.querySelector('#checkInTime').value = attendance.check_in ? 
                attendance.check_in.substring(0, 5) : '';
            this.editForm.querySelector('#checkOutTime').value = attendance.check_out ? 
                attendance.check_out.substring(0, 5) : '';
            this.editForm.querySelector('#remarks').value = attendance.remarks || '';
            
            this.editModal.style.display = 'block';
        } catch (error) {
            console.error('Error fetching attendance:', error);
            this.showNotification(error.message || 'Failed to fetch attendance details', 'error');
        }
    }

    async handleEditSubmit(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(this.editForm);
            
            // Show loading state
            const submitButton = this.editForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            const response = await fetch('../api/attendance/update.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            this.showNotification('Attendance updated successfully', 'success');
            this.closeAllModals();
            
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Submission error:', error);
            this.showNotification(error.message || 'Failed to update attendance', 'error');
            
            // Reset button state
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }

    validateDates(startDate, endDate) {
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);
        
        if (start > end) {
            endDate.value = startDate.value;
        }
    }

    async exportToExcel() {
        try {
            // Get current URL parameters
            const params = new URLSearchParams(window.location.search);
            
            // Add export flag
            params.append('export', 'excel');
            
            window.location.href = `../api/attendance/export.php?${params.toString()}`;
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Failed to export attendance records', 'error');
        }
    }

    closeAllModals() {
        this.selfieModal.style.display = 'none';
        this.locationModal.style.display = 'none';
        this.editModal.style.display = 'none';
        
        // Reset form
        this.editForm.reset();
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

// Initialize attendance viewer
const attendanceViewer = new AttendanceViewer();

// Close modals when clicking outside
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        attendanceViewer.closeAllModals();
    }
});
