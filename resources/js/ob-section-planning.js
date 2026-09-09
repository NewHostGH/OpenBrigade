// Shared FullCalendar whose events are the activities and absences of the people
// the viewer selects from a personnel list. Selecting or deselecting a person
// (or all of them) refetches the feed, which is scoped server-side. Selection is
// always explicit: with nobody checked the calendar is empty (you can hide your
// own calendar too).

import { Calendar } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

function selectedIds(root) {
    return Array.from(root.querySelectorAll('[data-sp-person]:checked')).map((c) => c.value);
}

function mount(el) {
    const eventsUrl = el.dataset.eventsUrl;
    const filterRoot = el.dataset.filter ? document.querySelector(el.dataset.filter) : null;
    if (!eventsUrl || !filterRoot) {
        return;
    }

    // Selection-aware links (print / XLS / CSV) kept in sync with the checked
    // people, each preserving its own base href.
    const selectionLinks = Array.from(document.querySelectorAll('[data-sp-selection-link]'))
        .map((link) => ({ link, base: link.getAttribute('href') }));

    // The displayed month lives only in FullCalendar; carry it onto the export
    // and print links so they match what the user is looking at.
    let viewDate = null;

    function syncPrintLink() {
        if (selectionLinks.length === 0) {
            return;
        }
        const params = new URLSearchParams();
        if (viewDate) {
            params.set('year', String(viewDate.getFullYear()));
            params.set('month', String(viewDate.getMonth() + 1));
        }
        selectedIds(filterRoot).forEach((id) => params.append('people[]', id));
        const qs = params.toString();
        selectionLinks.forEach(({ link, base }) => {
            link.setAttribute('href', qs ? `${base}?${qs}` : base);
        });
    }

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, listPlugin, interactionPlugin],
        locale: frLocale,
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 1,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth',
        },
        buttonText: { today: "Aujourd'hui", month: 'Mois', list: 'Liste' },
        eventDisplay: 'block',
        displayEventEnd: true,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        events(info, success, failure) {
            // `filtered` marks the selection as explicit so an empty set means
            // "show nobody" rather than falling back to the current user.
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr, filtered: '1' });
            selectedIds(filterRoot).forEach((id) => params.append('people[]', id));
            fetch(`${eventsUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(success)
                .catch(failure);
        },
        eventClick(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        datesSet() {
            // getDate() returns the calendar's current anchor date, whose month
            // is the one being displayed in month view.
            viewDate = calendar.getDate();
            syncPrintLink();
        },
    });

    calendar.render();
    syncPrintLink();

    filterRoot.addEventListener('change', (e) => {
        if (e.target.matches('[data-sp-person]')) {
            calendar.refetchEvents();
            syncPrintLink();
        }
    });

    // Select-all / clear controls may live outside the (scrollable) list.
    document.querySelectorAll('[data-sp-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const check = btn.dataset.spToggle === 'all';
            filterRoot.querySelectorAll('[data-sp-person]').forEach((c) => { c.checked = check; });
            calendar.refetchEvents();
            syncPrintLink();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ob-section-planning]').forEach(mount);
});
