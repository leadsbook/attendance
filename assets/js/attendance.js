class AttendanceManager {
    constructor() {
        this.stream = null;
        this.photoCanvas = document.getElementById('photoCanvas');
        this.video = document.getElementById('camera');
        this.locationStatus = document.getElementById('locationStatus');
        this.markAttendanceBtn = document.getElementById('markAttendanceBtn');
        this.cameraError = document.getElementById('cameraError');
        
        this.currentLocation = null;
        this.photoBlob = null;
        
        this.init();
    }

    async init() {
        try {
            // Start camera
            await this.startCamera();
            
            // Get location
            await this.getLocation();
            
            // Update current time
            this.startTimeUpdate();
            
            // Add event listeners
            this.markAttendanceBtn.addEventListener('click', () => this.markAttendance());
        } catch (error) {
            console.error('Initialization error:', error);
            this.showError('Failed to initialize attendance system');
        }
    }

    async startCamera() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });
            this.video.srcObject = this.stream;
            
            // Wait for video to be ready
            await new Promise(resolve => this.video.onloadedmetadata = resolve);
            
            this.updateButtonState();
        } catch (error) {
            console.error('Camera error:', error);
            this.cameraError.textContent = 'Failed to access camera. Please ensure camera permissions are granted.';
            this.cameraError.style.display = 'block';
            throw error;
        }
    }

    async getLocation() {
        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                });
            });
            
            this.currentLocation = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy
            };
            
            this.locationStatus.textContent = 'Location captured';
            this.updateButtonState();
        } catch (error) {
            console.error('Location error:', error);
            this.locationStatus.textContent = 'Failed to get location. Please enable location services.';
            throw error;
        }
    }

    capturePhoto() {
        const context = this.photoCanvas.getContext('2d');
        
        // Set canvas size to match video
        this.photoCanvas.width = this.video.videoWidth;
        this.photoCanvas.height = this.video.videoHeight;
        
        // Draw video frame to canvas
        context.drawImage(this.video, 0, 0);
        
        // Convert canvas to blob
        return new Promise(resolve => {
            this.photoCanvas.toBlob(blob => {
                this.photoBlob = blob;
                resolve(blob);
            }, 'image/jpeg', 0.8);
        });
    }

    updateButtonState() {
        this.markAttendanceBtn.disabled = !(this.stream && this.currentLocation);
    }

    startTimeUpdate() {
        const updateTime = () => {
            const now = new Date();
            document.getElementById('currentTime').textContent = 
                now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit' 
                });
        };
        
        updateTime();
        setInterval(updateTime, 1000);
    }

    async markAttendance() {
        try {
            this.markAttendanceBtn.disabled = true;
            this.markAttendanceBtn.textContent = 'Processing...';
            
            // Capture photo
            await this.capturePhoto();
            
            // Prepare form data
            const formData = new FormData();
            formData.append('photo', this.photoBlob, 'attendance.jpg');
            formData.append('latitude', this.currentLocation.latitude);
            formData.append('longitude', this.currentLocation.longitude);
            formData.append('accuracy', this.currentLocation.accuracy);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            
            // Send to server
            const response = await fetch('../api/attendance/mark.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Reload page to show updated attendance status
                window.location.reload();
            } else {
                throw new Error(result.message || 'Failed to mark attendance');
            }
        } catch (error) {
            console.error('Attendance marking error:', error);
            this.showError(error.message);
            
            this.markAttendanceBtn.disabled = false;
            this.markAttendanceBtn.textContent = 'Mark Attendance';
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.textContent = message;
        
        const container = document.querySelector('.attendance-card');
        container.insertBefore(errorDiv, container.firstChild);
        
        // Remove after 5 seconds
        setTimeout(() => errorDiv.remove(), 5000);
    }

    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
    }
}

// Initialize attendance manager
const attendanceManager = new AttendanceManager();

// Cleanup on page unload
window.addEventListener('unload', () => attendanceManager.cleanup());
