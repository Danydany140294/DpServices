import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

let calendar;

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    // Remplir les selects si les filtres existent
    const filters = window.calendarFilters;
    if (filters) {
        const cleanerSelect = document.getElementById('filter-cleaner');
        const ownerSelect = document.getElementById('filter-owner');

        if (cleanerSelect) {
            filters.cleaners.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.text = c.name;
                cleanerSelect.appendChild(opt);
            });
        }

        if (ownerSelect) {
            filters.owners.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.text = o.name;
                ownerSelect.appendChild(opt);
            });
        }
    }

    calendar = new Calendar(calendarEl, {
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

window.applyFilters = function() {
    const cleaner = document.getElementById('filter-cleaner')?.value || '';
    const owner = document.getElementById('filter-owner')?.value || '';

    calendar.removeAllEventSources();
    calendar.addEventSource('/api/calendar/events?cleaner=' + cleaner + '&owner=' + owner);
};

window.resetFilters = function() {
    document.getElementById('filter-cleaner').value = '';
    document.getElementById('filter-owner').value = '';
    calendar.removeAllEventSources();
    calendar.addEventSource('/api/calendar/events');
};