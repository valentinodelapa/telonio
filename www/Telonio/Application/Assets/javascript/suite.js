/*
 * Autocompletamento per i campi che referenziano un'entità (comuni, nazioni,
 * soggetti). Struttura attesa:
 *
 * <div class="autocompleta" data-url="/comune/cerca">
 *     <input type="text" class="form-control autocompleta-testo">
 *     <input type="hidden" name="comune" class="autocompleta-valore">
 * </div>
 *
 * L'endpoint riceve ?testo=... e restituisce [{id, etichetta}, ...].
 * Con data-campo-data="idCampo" riceve anche &al=<valore del campo data>.
 */
document.querySelectorAll('.autocompleta').forEach(function (contenitore) {
    const testo = contenitore.querySelector('.autocompleta-testo');
    const valore = contenitore.querySelector('.autocompleta-valore');
    const risultati = document.createElement('div');
    risultati.className = 'list-group autocompleta-risultati shadow-sm d-none';
    contenitore.appendChild(risultati);
    let attesa = null;

    function chiudi() {
        risultati.classList.add('d-none');
        risultati.replaceChildren();
    }

    function mostra(elementi) {
        risultati.replaceChildren();
        elementi.forEach(function (elemento) {
            const voce = document.createElement('button');
            voce.type = 'button';
            voce.className = 'list-group-item list-group-item-action py-1';
            voce.textContent = elemento.etichetta;
            voce.addEventListener('mousedown', function (evento) {
                evento.preventDefault();
                testo.value = elemento.etichetta;
                valore.value = elemento.id;
                chiudi();
            });
            risultati.appendChild(voce);
        });
        risultati.classList.toggle('d-none', elementi.length === 0);
    }

    testo.addEventListener('input', function () {
        valore.value = '';
        clearTimeout(attesa);
        if (testo.value.trim().length < 2) {
            chiudi();
            return;
        }
        attesa = setTimeout(function () {
            let url = contenitore.dataset.url + '?testo=' + encodeURIComponent(testo.value.trim());
            const campoData = contenitore.dataset.campoData ? document.getElementById(contenitore.dataset.campoData) : null;
            if (campoData && campoData.value) {
                url += '&al=' + encodeURIComponent(campoData.value);
            }
            fetch(url, {credentials: 'same-origin'})
                    .then(function (risposta) {
                        return risposta.ok ? risposta.json() : [];
                    })
                    .then(mostra)
                    .catch(chiudi);
        }, 250);
    });
    testo.addEventListener('blur', function () {
        setTimeout(chiudi, 150);
        if (valore.value === '') {
            testo.value = '';
        }
    });
});

/*
 * Conferma prima di inviare i form distruttivi (data-conferma="messaggio").
 */
document.querySelectorAll('form[data-conferma]').forEach(function (form) {
    form.addEventListener('submit', function (evento) {
        if (!window.confirm(form.dataset.conferma)) {
            evento.preventDefault();
        }
    });
});

/*
 * Casella che seleziona o deseleziona tutte quelle indicate dal selettore
 * (data-seleziona-tutti="selettore CSS").
 */
document.querySelectorAll('input[data-seleziona-tutti]').forEach(function (casella) {
    casella.addEventListener('change', function () {
        document.querySelectorAll(casella.dataset.selezionaTutti).forEach(function (altra) {
            altra.checked = casella.checked;
        });
    });
});
