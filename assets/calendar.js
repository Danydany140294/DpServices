import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: '/api/calendar/events',
        eventClick: function(info) {
            alert(
                'Logement : ' + info.event.title + '\n' +
                'Prestation : ' + info.event.extendedProps.service + '\n' +
                'Statut : ' + info.event.extendedProps.status + '\n' +
                'FdM : ' + info.event.extendedProps.cleaner
            );
        }
    });

    calendar.render();
});