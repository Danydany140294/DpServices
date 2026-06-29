import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

let calendar;

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

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
        editable: true,
        events: '/api/calendar/events',
        eventClick: function(info) {
            const eventId = info.event.id;

            if (eventId.startsWith('cr_')) {
                const realId = eventId.replace('cr_', '');
                fetch('/api/calendar/events/' + realId + '/open', { method: 'POST' }).catch(() => {});
            }

            alert(
                'Logement : ' + info.event.title + '\n' +
                'Prestation : ' + info.event.extendedProps.service + '\n' +
                'Statut : ' + info.event.extendedProps.status + '\n' +
                'FdM : ' + info.event.extendedProps.cleaner
            );
        },
        eventDrop: function(info) {
            const eventId = info.event.id;
            if (!eventId.startsWith('cr_')) {
                info.revert();
                return;
            }
            const realId = eventId.replace('cr_', '');
            fetch('/api/calendar/events/' + realId + '/move', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ start: info.event.start.toISOString() })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur lors du déplacement : ' + (data.error || 'inconnue'));
                    info.revert();
                } else if (data.warning) {
                    alert(data.warning);
                }
            })
            .catch(() => {
                alert('Erreur réseau lors du déplacement.');
                info.revert();
            });
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
