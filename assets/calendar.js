import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

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

    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView: isMobile ? 'listWeek' : 'dayGridMonth',
        locale: 'fr',
        
        height: isMobile ? 480 : 700,
        dayMaxEvents: isMobile ? false : 2,
        eventDisplay: 'block',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        editable: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        events: '/api/calendar/events',
        eventClick: function(info) {
            const eventId = info.event.id;

            if (!eventId.startsWith('cr_')) {
                return;
            }

            const realId = eventId.replace('cr_', '');
            fetch('/api/calendar/events/' + realId + '/open', { method: 'POST' }).catch(() => {});
            openMissionModal(realId);
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

window.openMissionModal = function(id) {
    fetch('/api/calendar/events/' + id + '/details')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            document.getElementById('mission-modal-property').textContent = data.property;
            document.getElementById('mission-modal-service').textContent = data.service;
            document.getElementById('mission-modal-date').textContent = data.date;
            document.getElementById('mission-modal-time').textContent = data.time;
            document.getElementById('mission-modal-status').textContent = data.status;
            document.getElementById('mission-modal-cleaner').textContent = data.cleaner || 'Non assigné';

            const commentRow = document.getElementById('mission-modal-comment-row');
            if (data.comment) {
                document.getElementById('mission-modal-comment').textContent = data.comment;
                commentRow.style.display = 'block';
            } else {
                commentRow.style.display = 'none';
            }

            document.getElementById('mission-modal').style.display = 'flex';
        })
        .catch(() => alert('Impossible de charger les détails de la mission.'));
};

window.closeMissionModal = function() {
    document.getElementById('mission-modal').style.display = 'none';
};

document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const missionId = params.get('mission');
    if (missionId) {
        openMissionModal(missionId);
    }
});


