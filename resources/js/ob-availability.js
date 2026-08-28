// Disponibilités grid — the personnel checkbox filter shows/hides grid rows
// client-side (all scoped rows are already rendered server-side).

function applyFilter(root, grid) {
    const checked = new Set(
        Array.from(root.querySelectorAll('[data-av-person]:checked')).map((c) => c.value)
    );
    grid.querySelectorAll('[data-av-row]').forEach((row) => {
        row.style.display = checked.has(row.dataset.avRow) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('av-grid');
    if (!grid) {
        return;
    }

    const root = document.getElementById('av-people');
    if (root) {
        root.addEventListener('change', (e) => {
            if (e.target.matches('[data-av-person]')) {
                applyFilter(root, grid);
            }
        });
        document.querySelectorAll('[data-av-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const check = btn.dataset.avToggle === 'all';
                root.querySelectorAll('[data-av-person]').forEach((c) => { c.checked = check; });
                applyFilter(root, grid);
            });
        });
    }

    // Self-declaration: clicking one of your own period slots toggles it.
    const toggleUrl = grid.dataset.toggleUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    if (toggleUrl) {
        grid.addEventListener('click', (e) => {
            const slot = e.target.closest('[data-av-slot]');
            if (!slot || slot.dataset.busy) {
                return;
            }
            slot.dataset.busy = '1';
            fetch(toggleUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ date: slot.dataset.date, period: slot.dataset.period }),
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => { slot.classList.toggle('is-on', !!data.available); })
                .catch(() => {})
                .finally(() => { delete slot.dataset.busy; });
        });
    }
});
