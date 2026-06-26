$(document).ready(function () {

    // Toggle Sidebar
    $('.toggle-sidebar').on('click', function () {
        $('.sidebar').toggleClass('show');
    });

    // Dark Mode Toggle
    $('.btn-dark-mode').on('click', function () {
        $('body').toggleClass('dark-mode');

        const icon = $(this).find('i');

        if ($('body').hasClass('dark-mode')) {
            icon.removeClass('fa-moon').addClass('fa-sun');
            localStorage.setItem('darkMode', 'true');
        } else {
            icon.removeClass('fa-sun').addClass('fa-moon');
            localStorage.setItem('darkMode', 'false');
        }
    });

    // Restore Dark Mode Preference
    if (localStorage.getItem('darkMode') === 'true') {
        $('body').addClass('dark-mode');
        $('.btn-dark-mode i')
            .removeClass('fa-moon')
            .addClass('fa-sun');
    }

    // Auto Hide Alerts
    setTimeout(function () {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Notification Bell
    $('.notification-bell').on('click', function () {
        fetchNotifications();
    });

});

// Reusable AJAX Function
function ajaxRequest(url, method, data, successCallback, errorCallback) {

    $.ajax({
        url: url,
        type: method,
        data: data,
        dataType: 'json',

        success: function (response) {
            if (successCallback) {
                successCallback(response);
            }
        },

        error: function (xhr, status, error) {

            console.error('AJAX Error:', error);

            if (errorCallback) {
                errorCallback(xhr, status, error);
            }
        }
    });

}

// Fetch Notifications
function fetchNotifications() {

    ajaxRequest(
        'ajax/fetch-notifications.php', // <-- fixed path
        'GET',
        {},

        function (response) {

            if (response.status === 'success') {

                let html = '';

                response.notifications.forEach(function (notification) {

                    html += `
                        <li class="list-group-item ${notification.is_read ? '' : 'bg-light'}">
                            <a href="${notification.link || '#'}">
                                ${notification.message}
                            </a>
                            <small class="d-block text-muted">
                                ${notification.created_at}
                            </small>
                        </li>
                    `;

                });

                $('#notification-list').html(
                    html || '<li class="list-group-item">No notifications</li>'
                );

                $('#notification-count').text(
                    response.unread || 0
                );

            }

        }

    );

}