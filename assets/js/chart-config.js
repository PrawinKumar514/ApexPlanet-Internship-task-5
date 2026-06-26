// Shared chart configuration settings
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#666';

// Function to get chart colors based on current theme
function getChartColors() {
    const isDark = $('body').hasClass('dark-mode');
    return {
        gridColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)',
        textColor: isDark ? '#e0e0e0' : '#666'
    };
}

// Common chart options with responsive and maintainAspectRatio
function getDefaultChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: getChartColors().textColor
                }
            }
        },
        scales: {
            x: {
                ticks: { color: getChartColors().textColor },
                grid: { color: getChartColors().gridColor }
            },
            y: {
                ticks: { color: getChartColors().textColor },
                grid: { color: getChartColors().gridColor }
            }
        }
    };
}

// Update chart colors on theme change
$(document).on('click', '.btn-dark-mode', function() {
    // Re-initialize charts after theme change
    // You can call a function to update chart options or destroy and recreate
    // For simplicity, we reload the page or you can implement a full update
    // We'll use a simple reload for demo
    // location.reload();
});