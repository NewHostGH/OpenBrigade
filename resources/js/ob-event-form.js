(function () {
    var container = document.getElementById('horairesContainer');
    var addBtn    = document.getElementById('addHoraire');

    if (!container || !addBtn) return;

    function renumber() {
        container.querySelectorAll('.ob-horaire-fieldset').forEach(function (fs, i) {
            fs.querySelector('.ob-partie-num').textContent = i + 1;
            fs.querySelectorAll('input').forEach(function (inp) {
                inp.name = inp.name.replace(/horaires\[\d+\]/, 'horaires[' + i + ']');
            });
            var removeBtn = fs.querySelector('.ob-remove-horaire');
            if (i === 0) {
                if (removeBtn) removeBtn.remove();
            } else if (!removeBtn) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-xs btn-light py-0 px-1 text-danger ob-remove-horaire';
                btn.innerHTML = '<i class="fas fa-times"></i>';
                fs.querySelector('.d-flex').appendChild(btn);
                btn.addEventListener('click', function () { fs.remove(); renumber(); });
            }
        });
    }

    addBtn.addEventListener('click', function () {
        var idx = container.querySelectorAll('.ob-horaire-fieldset').length;
        var tpl = container.querySelector('.ob-horaire-fieldset').cloneNode(true);

        tpl.querySelectorAll('input').forEach(function (inp) {
            inp.name = inp.name.replace(/horaires\[\d+\]/, 'horaires[' + idx + ']');
            inp.value = '';
            inp.required = (inp.name.indexOf('EH_DATE_DEBUT') !== -1);
        });
        tpl.querySelector('.ob-partie-num').textContent = idx + 1;

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-xs btn-light py-0 px-1 text-danger ob-remove-horaire';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.addEventListener('click', function () { tpl.remove(); renumber(); });
        tpl.querySelector('.d-flex').appendChild(removeBtn);

        container.appendChild(tpl);
    });

    container.addEventListener('click', function (e) {
        var btn = e.target.closest('.ob-remove-horaire');
        if (btn) { btn.closest('.ob-horaire-fieldset').remove(); renumber(); }
    });
})();

// Renfort activity type: show the "main event" picker only while the selected
// type is the renfort type (the block is rendered server-side, hidden otherwise).
(function () {
    var block = document.querySelector('[data-renfort-parent]');
    var type = document.getElementById('TE_CODE');
    if (!block || !type) { return; }

    function sync() {
        block.hidden = type.value !== block.dataset.renfortType;
    }
    type.addEventListener('change', sync);
    sync();
})();
