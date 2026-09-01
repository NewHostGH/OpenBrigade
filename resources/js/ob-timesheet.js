// Horaires de travail — live per-day and week total computation as the user
// types. Mirrors the server-side calculation (slot1 + slot2 + overtime); the
// authoritative totals are always recomputed on save.

function toMinutes(hm) {
    const m = /^(\d{1,2}):([0-5]\d)$/.exec((hm || '').trim());
    return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : 0;
}

function fmt(minutes) {
    if (minutes <= 0) {
        return '0:00';
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}:${String(m).padStart(2, '0')}`;
}

function slot(start, end) {
    const s = toMinutes(start);
    const e = toMinutes(end);
    return e > s ? e - s : 0;
}

function recompute(grid) {
    let week = 0;
    grid.querySelectorAll('[data-ts-row]').forEach((row) => {
        const t = row.querySelectorAll('[data-ts-time]');
        const ot = row.querySelector('[data-ts-ot]');
        const total = slot(t[0]?.value, t[1]?.value)
            + slot(t[2]?.value, t[3]?.value)
            + toMinutes(ot?.value);
        const cell = row.querySelector('[data-ts-total]');
        if (cell) {
            cell.textContent = fmt(total);
        }
        week += total;
    });
    const weekCell = grid.parentElement.querySelector('[data-ts-week]')
        || document.querySelector('[data-ts-week]');
    if (weekCell) {
        weekCell.textContent = fmt(week);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('ts-grid');
    if (!grid) {
        return;
    }
    grid.addEventListener('input', (e) => {
        if (e.target.matches('[data-ts-time], [data-ts-ot]')) {
            recompute(grid);
        }
    });
});
