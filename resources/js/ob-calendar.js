// Universal calendar integration built on FullCalendar v6.
//
// Any page can mount a calendar by rendering a container element:
//
//   <div data-ob-calendar
//        data-events-url="/planning/events"
//        data-initial-view="dayGridMonth"
//        data-initial-date="2026-08-01"></div>
//
// The events URL must return FullCalendar event objects as JSON; FullCalendar
// appends ?start=&end= for the visible range. Events carrying a `url` become
// clickable links. Styling comes from FullCalendar's own CSS (bundled by Vite)
// plus the theme overrides in resources/css/ob-calendar.css.

import { Calendar } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

function mount(el) {
    const eventsUrl = el.dataset.eventsUrl;
    if (!eventsUrl) {
        return;
    }

    // Compact mode (e.g. the dashboard agenda widget): a trimmed toolbar and a
    // fixed height, so the calendar sits neatly inside a small card.
    const compact = el.dataset.compact !== undefined;

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, listPlugin, interactionPlugin],
        locale: frLocale,
        initialView: el.dataset.initialView || 'dayGridMonth',
        initialDate: el.dataset.initialDate || undefined,
        height: el.dataset.height ? Number(el.dataset.height) : 'auto',
        firstDay: 1,
        headerToolbar: compact
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listMonth',
            },
        noEventsContent: el.dataset.emptyText || undefined,
        buttonText: { today: "Aujourd'hui", month: 'Mois', list: 'Liste' },
        // Render timed events as full blocks showing the start–end range.
        eventDisplay: 'block',
        displayEventEnd: true,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        events: { url: eventsUrl },
        eventClick(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        // Keep an optional "print this month" link in sync with the visible month.
        datesSet(info) {
            const printUrl = el.dataset.printUrl;
            const link = document.querySelector('[data-ob-calendar-print]');
            if (!printUrl || !link) {
                return;
            }
            const mid = new Date((info.start.getTime() + info.end.getTime()) / 2);
            link.href = `${printUrl}?year=${mid.getFullYear()}&month=${mid.getMonth() + 1}`;
        },
    });

    calendar.render();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ob-calendar]').forEach(mount);
});
