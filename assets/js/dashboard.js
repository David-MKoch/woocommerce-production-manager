jQuery(document).ready(function($) {
    function fetchDashboardData(type, callback) {
        $.ajax({
            url: wpmDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_get_dashboard_data',
                nonce: wpmDashboard.nonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data);
                }
            }
        });
    }

    fetchDashboardData('capacity', function(data) {
        new Chart(document.getElementById('capacityChart'), {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: wpmDashboard.i18n.capacityUsed,
                    data: data.values,
                    borderColor: '#0073aa',
                    fill: false
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });

    fetchDashboardData('delayed_orders', function(data) {
        new Chart(document.getElementById('delayedOrdersChart'), {
            type: 'bar',
            data: {
                labels: data.dates,
                datasets: [{
                    label: wpmDashboard.i18n.delayedOrders,
                    data: data.values,
                    backgroundColor: '#ff5733'
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });

    fetchDashboardData('sms', function(data) {
        new Chart(document.getElementById('smsChart'), {
            type: 'pie',
            data: {
                labels: ['Success', 'Failed'],
                datasets: [{
                    label: wpmDashboard.i18n.smsSent,
                    data: [data.success, data.failed],
                    backgroundColor: ['#28a745', '#dc3545']
                }]
            }
        });
    });
});