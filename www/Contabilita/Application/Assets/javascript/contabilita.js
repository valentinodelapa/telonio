/*
 * Form della registrazione di prima nota: imposta proposta dall'aliquota
 * del codice IVA (se il campo è vuoto o era stato calcolato), detraibilità
 * del codice e totali dare/avere con la differenza.
 */
(function () {
    const form = document.getElementById('form-registrazione');
    if (!form) {
        return;
    }

    function numero(testo) {
        const valore = (testo || '').trim().replace(/\s|€/g, '');
        const normalizzato = valore.indexOf(',') >= 0 ? valore.replace(/\./g, '').replace(',', '.') : valore;
        const risultato = parseFloat(normalizzato);
        return isNaN(risultato) ? 0 : risultato;
    }

    function euro(valore) {
        return valore.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function testoImporto(valore) {
        return valore.toFixed(2).replace('.', ',');
    }

    form.querySelectorAll('[data-righe-iva] tbody tr').forEach(function (riga) {
        const codice = riga.querySelector('[data-codice-iva]');
        const imponibile = riga.querySelector('[data-imponibile]');
        const imposta = riga.querySelector('[data-imposta]');
        const detraibilita = riga.querySelector('[data-detraibilita]');

        function calcola() {
            const opzione = codice.options[codice.selectedIndex];
            if (!opzione || !opzione.dataset.aliquota) {
                return;
            }
            if (imposta.value.trim() === '' || imposta.dataset.calcolata === '1') {
                const valore = Math.round(numero(imponibile.value) * parseFloat(opzione.dataset.aliquota)) / 100;
                imposta.value = imponibile.value.trim() === '' ? '' : testoImporto(valore);
                imposta.dataset.calcolata = '1';
            }
        }

        codice.addEventListener('change', function () {
            const opzione = codice.options[codice.selectedIndex];
            if (detraibilita && opzione && opzione.dataset.detraibilita && detraibilita.value.trim() === '') {
                detraibilita.value = testoImporto(parseFloat(opzione.dataset.detraibilita)).replace(',00', '');
            }
            calcola();
        });
        imponibile.addEventListener('input', calcola);
        imposta.addEventListener('input', function () {
            imposta.dataset.calcolata = '0';
        });

        // prodotto agricolo: propone la percentuale di compensazione, che resta modificabile
        const prodotto = riga.querySelector('[data-prodotto]');
        const compensazione = riga.querySelector('[data-percentuale-compensazione]');
        if (prodotto && compensazione) {
            prodotto.addEventListener('change', function () {
                const scelta = Array.from(document.querySelectorAll('#prodottiAgricoli option')).find(function (opzione) {
                    return opzione.value === prodotto.value;
                });
                if (scelta) {
                    compensazione.value = scelta.dataset.percentuale;
                }
            });
        }
    });

    const tabella = form.querySelector('[data-righe]');
    function totali() {
        let dare = 0;
        let avere = 0;
        tabella.querySelectorAll('[data-dare]').forEach(function (campo) {
            dare += numero(campo.value);
        });
        tabella.querySelectorAll('[data-avere]').forEach(function (campo) {
            avere += numero(campo.value);
        });
        tabella.querySelector('[data-totale-dare]').textContent = euro(dare);
        tabella.querySelector('[data-totale-avere]').textContent = euro(avere);
        const differenza = tabella.querySelector('[data-differenza]');
        differenza.textContent = euro(dare - avere);
        differenza.classList.toggle('text-danger', Math.abs(dare - avere) > 0.005);
    }
    if (tabella) {
        tabella.addEventListener('input', totali);
        totali();
    }
})();

/*
 * Griglia dei corrispettivi del mese: totali per giorno e per codice IVA
 * e anteprima dello scorporo dell'imposta dal totale mensile (lordo per
 * aliquota diviso 100 più l'aliquota, al centesimo).
 */
(function () {
    const griglia = document.querySelector('[data-griglia-corrispettivi]');
    if (!griglia) {
        return;
    }

    function numero(testo) {
        const valore = (testo || '').trim().replace(/\s|€/g, '');
        const normalizzato = valore.indexOf(',') >= 0 ? valore.replace(/\./g, '').replace(',', '.') : valore;
        const risultato = parseFloat(normalizzato);
        return isNaN(risultato) ? 0 : risultato;
    }

    function euro(valore) {
        return valore.toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function totali() {
        const colonne = {};
        let mese = 0;
        griglia.querySelectorAll('tbody tr').forEach(function (riga) {
            let giorno = 0;
            riga.querySelectorAll('input[data-colonna]').forEach(function (campo) {
                const valore = numero(campo.value);
                giorno += valore;
                colonne[campo.dataset.colonna] = (colonne[campo.dataset.colonna] || 0) + valore;
            });
            mese += giorno;
            riga.querySelector('[data-totale-giorno]').textContent = giorno === 0 ? '' : euro(giorno);
        });
        let impostaMese = 0;
        griglia.querySelectorAll('[data-totale-colonna]').forEach(function (cella) {
            const id = cella.dataset.totaleColonna;
            const lordo = colonne[id] || 0;
            const aliquota = parseFloat(cella.dataset.aliquota) || 0;
            const imposta = aliquota > 0 ? Math.round(lordo * aliquota / (100 + aliquota) * 100) / 100 : 0;
            impostaMese += imposta;
            cella.textContent = euro(lordo);
            griglia.querySelector('[data-imponibile-colonna="' + id + '"]').textContent = euro(lordo - imposta);
            griglia.querySelector('[data-imposta-colonna="' + id + '"]').textContent = euro(imposta);
        });
        griglia.querySelector('[data-totale-mese]').textContent = euro(mese);
        griglia.querySelector('[data-imposta-mese]').textContent = euro(impostaMese);
    }

    griglia.addEventListener('input', totali);
    totali();
})();

/*
 * Stampe definitive: l'anteprima apre in una nuova scheda le pagine fino
 * alla data indicata nel modulo, senza registrare la stampa.
 */
(function () {
    document.querySelectorAll('[data-anteprima]').forEach(function (pulsante) {
        pulsante.addEventListener('click', function (evento) {
            evento.preventDefault();
            const data = pulsante.form.querySelector('input[name="al"]').value;
            if (data !== '') {
                window.open(pulsante.dataset.anteprima + '/al/' + encodeURIComponent(data), '_blank');
            }
        });
    });
})();
