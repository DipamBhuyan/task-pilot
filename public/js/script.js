    // Function to fetch tasks for Boss
    function fetchTasks(page = 1) {
        const search = document.getElementById('search').value;
        const status = document.getElementById('status').value;
        const date = document.getElementById('date_filter').value;

        const xhr = new XMLHttpRequest();
        xhr.open('GET', `../ajax/fetch_tasks.php?page=${page}&search=${encodeURIComponent(search)}&status=${status}&date=${date}`, true);
        xhr.onload = function () {
            if (this.status === 200) {
                document.getElementById('task-results').innerHTML = this.responseText;
            }
        };
        xhr.send();
    }

    // Load default tasks
    document.addEventListener('DOMContentLoaded', () => {
        fetchTasks();
    });

    // Function to fetch tasks for My Tasks section
    function fetchMyTasks(page = 1) {
        const search = document.getElementById('my-search').value;
        const status = document.getElementById('my-status').value;
        const date = document.getElementById('my-date').value;

        const xhr = new XMLHttpRequest();
        xhr.open('GET', `../ajax/fetch_my_tasks.php?page=${page}&search=${encodeURIComponent(search)}&status=${status}&date=${date}`, true);
        xhr.onload = function () {
            if (this.status === 200) {
                document.getElementById('my-task-results').innerHTML = this.responseText;
            }
        };
        xhr.send();
    }

    // Load default My Tasks
    document.addEventListener('DOMContentLoaded', () => {
        fetchMyTasks();
    });


    // Function to update task status
    function updateStatus(taskId, newStatus) {
        fetch('update_task_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ task_id: taskId, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMyTasks(1); // Refresh current task list
            } else {
                alert("Status update failed: " + data.message);
            }
        });
    }


    // Function to delete a task
    function deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task?')) return;

        fetch('../ajax/delete_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `task_id=${taskId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('task-row-' + taskId);
                if (row) row.remove();

                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Something went wrong.', 'danger');
        });
    }

    // Function to show the toast message for deletion
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        toast.role = 'alert';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }


    // Code for Calendar features
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('calendar');

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: '../ajax/fetch_events.php',

            dateClick: function(info) {
                clearModal();
                document.getElementById('start').value = info.dateStr + 'T09:00';
                document.getElementById('end').value = info.dateStr + 'T10:00';
                new bootstrap.Modal(document.getElementById('eventModal')).show();
            },

            eventClick: function(info) {
                const event = info.event;
                document.getElementById('event_id').value = event.id;
                document.getElementById('title').value = event.title;
                document.getElementById('start').value = event.start.toISOString().slice(0, 16);
                document.getElementById('end').value = event.end ? event.end.toISOString().slice(0, 16) : '';
                document.getElementById('description').value = event.extendedProps.description || '';
                document.getElementById('deleteBtn').classList.remove('d-none');
                new bootstrap.Modal(document.getElementById('eventModal')).show();
            },

            height: "auto",
            eventColor: '#3788d8'
        });

        calendar.render();

        // Clear modal on open
        function clearModal() {
            document.getElementById('eventForm').reset();
            document.getElementById('event_id').value = '';
            document.getElementById('deleteBtn').classList.add('d-none');
        }

        // Handle event form submission
        document.getElementById('eventForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            fetch('../ajax/save_event.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    calendar.refetchEvents();
                    bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                } else {
                    alert('Error saving event');
                }
            });
        });

        // Handle event delete
        document.getElementById('deleteBtn').addEventListener('click', function () {
            const id = document.getElementById('event_id').value;
            if (confirm("Are you sure you want to delete this event?")) {
                fetch(`../ajax/delete_event.php?id=${id}`, {
                    method: 'GET'
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        calendar.refetchEvents();
                        bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                    } else {
                        alert('Failed to delete.');
                    }
                });
            }
        });
    });