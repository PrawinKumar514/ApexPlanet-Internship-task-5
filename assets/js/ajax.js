// AJAX search functions

function searchStudents(query) {
    if (query.length < 2) {
        $('#student-results').html('');
        return;
    }

    ajaxRequest(
        '../admin/ajax/search-student.php',
        'GET',
        { search: query },
        function(response) {

            if (response.length > 0) {

                let html = '<ul class="list-group">';

                response.forEach(function(s) {
                    html += `
                        <li class="list-group-item">
                            <strong>${s.student_id}</strong> -
                            ${s.first_name} ${s.last_name}
                            <span class="badge bg-secondary">
                                ${s.department_name || ''}
                            </span>
                        </li>
                    `;
                });

                html += '</ul>';

                $('#student-results').html(html);

            } else {

                $('#student-results').html(
                    '<p class="text-muted">No students found</p>'
                );

            }
        }
    );
}

function searchFaculty(query) {

    if (query.length < 2) {
        $('#faculty-results').html('');
        return;
    }

    ajaxRequest(
        '../admin/ajax/search-faculty.php',
        'GET',
        { search: query },

        function(response) {

            if (response.length > 0) {

                let html = '<ul class="list-group">';

                response.forEach(function(f) {

                    html += `
                        <li class="list-group-item">
                            <strong>${f.faculty_id}</strong> -
                            ${f.first_name} ${f.last_name}
                            <span class="badge bg-secondary">
                                ${f.department_name || ''}
                            </span>
                        </li>
                    `;

                });

                html += '</ul>';

                $('#faculty-results').html(html);

            } else {

                $('#faculty-results').html(
                    '<p class="text-muted">No faculty found</p>'
                );

            }
        }
    );
}

function searchCourses(query) {

    if (query.length < 2) {
        $('#course-results').html('');
        return;
    }

    ajaxRequest(
        '../admin/ajax/search-course.php',
        'GET',
        { search: query },

        function(response) {

            if (response.length > 0) {

                let html = '<ul class="list-group">';

                response.forEach(function(c) {

                    html += `
                        <li class="list-group-item">
                            <strong>${c.course_code}</strong> -
                            ${c.name}
                        </li>
                    `;

                });

                html += '</ul>';

                $('#course-results').html(html);

            } else {

                $('#course-results').html(
                    '<p class="text-muted">No courses found</p>'
                );

            }
        }
    );
}

// Notification polling

function startNotificationPolling() {

    setInterval(function () {
        fetchNotifications();
    }, 30000);

}

$(document).ready(function () {

    $('#search-student').on('keyup', function () {
        searchStudents($(this).val());
    });

    $('#search-faculty').on('keyup', function () {
        searchFaculty($(this).val());
    });

    $('#search-course').on('keyup', function () {
        searchCourses($(this).val());
    });

    if ($('#notification-count').length) {
        startNotificationPolling();
    }

});