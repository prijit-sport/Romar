/**
 * Room Booking Calendar - FullCalendar integration
 */
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('roomCalendar');
    if (!calendarEl) return;

    // Fetch bookings from API or generate dummy
    fetch('../api/getroombookings.php')
        .then(response => response.json())
        .then(data => {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: data.map(event => ({
                    title: `${event.room_name} - ${event.purpose}`,
                    start: `${event.booking_date}T${event.start_time}`,
                    end: `${event.booking_date}T${event.end_time}`,
                    backgroundColor: event.status === 'pending' ? '#fed7d7' : event.status === 'approved' ? '#c6f6d5' : '#fff3cd',
                    borderColor: event.status === 'pending' ? '#fc8181' : event.status === 'approved' ? '#38a169' : '#facf5a'
                })),
                eventClick: function(info) {
                    alert('Booking: ' + info.event.title + '\nStatus: ' + info.event.extendedProps.status);
                },
                dateClick: function(info) {
                    // Prefill date but require room selection first
                    document.getElementById('booking_date').value = info.dateStr.split('T')[0];
                    alert('กรุณาเลือกห้องประชุมจากรายการด้านล่างก่อนจอง');
                }
            });
            calendar.render();
        });
});

