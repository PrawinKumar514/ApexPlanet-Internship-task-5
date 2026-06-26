// Dashboard charts initialization
// This file is included in admin, faculty, student dashboards

function initDashboardCharts() {
    // Student Growth Chart (Admin)
    const ctx1 = document.getElementById('studentGrowthChart');
    if (ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Students',
                    data: [120, 150, 180, 200, 230, 260],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Attendance Trend (Admin)
    const ctx2 = document.getElementById('attendanceTrendChart');
    if (ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Present',
                    data: [85, 90, 78, 92, 88],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }, {
                    label: 'Absent',
                    data: [15, 10, 22, 8, 12],
                    backgroundColor: 'rgba(255, 99, 132, 0.6)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true }
                }
            }
        });
    }

    // Course Distribution (Admin)
    const ctx3 = document.getElementById('courseDistributionChart');
    if (ctx3) {
        new Chart(ctx3.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Computer Science', 'Mathematics', 'Physics', 'Biology', 'Engineering'],
                datasets: [{
                    data: [30, 20, 15, 10, 25],
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Faculty Analytics (Faculty Dashboard)
    const ctx4 = document.getElementById('coursePerformanceChart');
    if (ctx4) {
        new Chart(ctx4.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['Attendance', 'Assignments', 'Marks', 'Participation', 'Feedback'],
                datasets: [{
                    label: 'Performance',
                    data: [85, 70, 90, 60, 80],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Student Marks Progress (Student Dashboard)
    const ctx5 = document.getElementById('marksProgressChart');
    if (ctx5) {
        new Chart(ctx5.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Midterm', 'Quiz 1', 'Assignment', 'Quiz 2', 'Final'],
                datasets: [{
                    label: 'Marks',
                    data: [78, 82, 90, 85, 88],
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

// Call when document ready
$(document).ready(function() {
    initDashboardCharts();
});