

class ReportManager {
    constructor() {
        this.reportType = 'attendance';
        this.mainChart = null;
        this.secondaryChart = null;
        this.employeeSearchTimeout = null;
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Report type selection
        document.querySelectorAll('.report-type').forEach(button => {
            button.addEventListener('click', () => this.switchReportType(button.dataset.type));
        });

        // Date range selection
        document.getElementById('dateRange').addEventListener('change', (e) => {
            const customDates = document.querySelector('.custom-dates');
            customDates.style.display = e.target.value === 'custom' ? 'block' : 'none';
            
            if (e.target.value !== 'custom') {
                this.generateReport();
            }
        });

        // Custom date validation
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        
        [startDate, endDate].forEach(input => {
            input.addEventListener('change', () => this.validateDateRange());
        });

        // Employee search
        const employeeInput = document.getElementById('employee');
        employeeInput.addEventListener('input', () => {
            clearTimeout(this.employeeSearchTimeout);
            this.employeeSearchTimeout = setTimeout(() => this.searchEmployees(employeeInput.value), 300);
        });

        // Form submission
        document.getElementById('reportFilters').addEventListener('submit', (e) => {
            e.preventDefault();
            this.generateReport();
        });

        // Export button
        document.getElementById('exportReport').addEventListener('click', () => this.exportReport());

        // Initial report generation
        this.generateReport();
    }

    async switchReportType(type) {
        // Update UI
        document.querySelectorAll('.report-type').forEach(button => {
            button.classList.toggle('active', button.dataset.type === type);
        });

        this.reportType = type;
        document.getElementById('reportType').value = type;

        // Clear previous report
        document.getElementById('reportContent').innerHTML = '';
        
        // Destroy existing charts
        if (this.mainChart) {
            this.mainChart.destroy();
        }
        if (this.secondaryChart) {
            this.secondaryChart.destroy();
        }

        // Generate new report
        await this.generateReport();
    }

    validateDateRange() {
        const startDate = new Date(document.getElementById('startDate').value);
        const endDate = new Date(document.getElementById('endDate').value);
        
        if (endDate < startDate) {
            document.getElementById('endDate').value = document.getElementById('startDate').value;
        }

        this.generateReport();
    }

    async searchEmployees(query) {
        if (!query.trim()) {
            document.getElementById('employeeResults').innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`../api/employee/search.php?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
                }
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            this.displayEmployeeResults(data.employees);
        } catch (error) {
            console.error('Employee search error:', error);
        }
    }

    displayEmployeeResults(employees) {
        const resultsContainer = document.getElementById('employeeResults');
        resultsContainer.innerHTML = '';

        if (employees.length === 0) {
            resultsContainer.innerHTML = '<div class="no-results">No employees found</div>';
            return;
        }

        employees.forEach(employee => {
            const div = document.createElement('div');
            div.className = 'employee-result';
            div.innerHTML = `
                <div class="employee-name">${employee.full_name}</div>
                <div class="employee-info">${employee.employee_id} - ${employee.department}</div>
            `;
            
            div.addEventListener('click', () => this.selectEmployee(employee));
            resultsContainer.appendChild(div);
        });
    }

    selectEmployee(employee) {
        document.getElementById('employee').value = employee.full_name;
        document.getElementById('employeeResults').innerHTML = '';
        this.generateReport();
    }

    async generateReport() {
        const form = document.getElementById('reportFilters');
        const formData = new FormData(form);
        
        try {
            this.showLoading();

            const response = await fetch('../api/reports/generate.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            this.displayReport(data.report);
        } catch (error) {
            console.error('Report generation error:', error);
            this.showError(error.message);
        } finally {
            this.hideLoading();
        }
    }

    displayReport(report) {
        // Get template based on report type
        const template = document.getElementById(`${this.reportType}ReportTemplate`).content.cloneNode(true);
        
        // Fill in template values
        for (const [key, value] of Object.entries(report.summary)) {
            template.innerHTML = template.innerHTML.replace(`{{${key}}}`, value);
        }

        // Display report content
        const reportContent = document.getElementById('reportContent');
        reportContent.innerHTML = '';
        reportContent.appendChild(template);

        // Generate charts
        this.generateCharts(report.charts);

        // Generate tables
        this.generateTables(report.tables);
    }

    generateCharts(chartData) {
        const ctx1 = document.getElementById('mainChart').getContext('2d');
        const ctx2 = document.getElementById('secondaryChart').getContext('2d');

        // Destroy existing charts if they exist
        if (this.mainChart) {
            this.mainChart.destroy();
        }
        if (this.secondaryChart) {
            this.secondaryChart.destroy();
        }

        // Create new charts based on report type
        switch (this.reportType) {
            case 'attendance':
                this.mainChart = this.createAttendanceChart(ctx1, chartData.attendance);
                this.secondaryChart = this.createPunctualityChart(ctx2, chartData.punctuality);
                break;
            case 'leave':
                this.mainChart = this.createLeaveTypeChart(ctx1, chartData.leaveTypes);
                this.secondaryChart = this.createLeaveTrendChart(ctx2, chartData.leaveTrend);
                break;
            case 'performance':
                this.mainChart = this.createPerformanceChart(ctx1, chartData.performance);
                this.secondaryChart = this.createTrendChart(ctx2, chartData.trend);
                break;
        }
    }

    createAttendanceChart(ctx, data) {
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Present',
                    data: data.present,
                    backgroundColor: '#28a745'
                }, {
                    label: 'Absent',
                    data: data.absent,
                    backgroundColor: '#dc3545'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Employees'
                        }
                    }
                }
            }
        });
    }

    createPunctualityChart(ctx, data) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'On Time',
                    data: data.onTime,
                    borderColor: '#28a745',
                    fill: false
                }, {
                    label: 'Late',
                    data: data.late,
                    borderColor: '#ffc107',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    createLeaveTypeChart(ctx, data) {
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    createLeaveTrendChart(ctx, data) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Leave Trend',
                    data: data.values,
                    borderColor: '#4e73df',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    createPerformanceChart(ctx, data) {
        return new Chart(ctx, {
            type: 'radar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Performance Metrics',
                    data: data.values,
                    backgroundColor: 'rgba(78, 115, 223, 0.2)',
                    borderColor: '#4e73df',
                    pointBackgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    createTrendChart(ctx, data) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Performance Trend',
                    data: data.values,
                    borderColor: '#1cc88a',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    generateTables(tablesData) {
        const tablesContainer = document.getElementById('reportTables');
        tablesContainer.innerHTML = '';

        Object.entries(tablesData).forEach(([title, data]) => {
            const tableSection = document.createElement('div');
            tableSection.className = 'table-section';
            
            tableSection.innerHTML = `
                <h3>${title}</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                ${Object.keys(data[0]).map(key => 
                                    `<th>${this.formatColumnHeader(key)}</th>`
                                ).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(row => `
                                <tr>
                                    ${Object.values(row).map(value => 
                                        `<td>${this.formatTableCell(value)}</td>`
                                    ).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            tablesContainer.appendChild(tableSection);
        });
    }

    formatColumnHeader(header) {
        return header
            .split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    formatTableCell(value) {
        if (typeof value === 'number') {
            if (value % 1 === 0) {
                return value.toString();
            }
            return value.toFixed(2);
        }
        if (value instanceof Date) {
            return value.toLocaleDateString();
        }
        return value;
    }

    async exportReport() {
        const form = document.getElementById('reportFilters');
        const formData = new FormData(form);
        formData.append('export', '1');
        
        try {
            const response = await fetch('../api/reports/export.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            // Create a download link
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${this.reportType}_report_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Export error:', error);
            this.showError('Failed to export report');
        }
    }

    showLoading() {
        document.getElementById('loadingIndicator').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loadingIndicator').style.display = 'none';
    }

    showError(message) {
        const notification = document.createElement('div');
        notification.className = 'notification notification-error';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }, 10);
    }
}

// Initialize report manager
const reportManager = new ReportManager();
