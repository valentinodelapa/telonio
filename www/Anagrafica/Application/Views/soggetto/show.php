<?php

use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$id = $soggetto->id;
$personaFisica = $soggetto->isPersonaFisica();
$schede = [
    'generale' => $schedaGenerale,
    'fiscali' => $schedaFiscali,
    'indirizzi' => $schedaIndirizzi,
    'recapiti' => $schedaRecapiti,
    'conti' => $schedaConti,
    'legami' => $schedaLegami,
    'immobili' => $schedaImmobili,
];
if ($personaFisica) {
    $schede['documenti'] = $schedaDocumenti;
}
$scheda = array_key_exists($scheda, $schede) ? $scheda : 'generale';

$pulsanteElimina = function (string $url) use ($csrfToken, $confermaElimina, $azioni): string {
    return '<form method="post" action="' . Html::e($url) . '" class="d-inline" data-conferma="' . Html::e($confermaElimina) . '">'
            . '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger" title="' . Html::e($azioni['elimina']) . '"><i class="bi bi-trash"></i></button></form>';
};
$pulsanteModifica = fn(string $url): string => '<a href="' . Html::e($url) . '" class="btn btn-sm btn-outline-secondary" title="' . Html::e($azioni['rettifica']) . '"><i class="bi bi-pencil"></i></a>';
$periodo = fn($versione): string => Html::data($versione->validoDal) . ' → ' . ($versione->validoAl === null ? '…' : Html::data($versione->validoAl));
$classeRiga = fn($versione): string => $versione->isVigenteAl($dataRiferimento) ? 'table-success' : 'storico-non-vigente';
$badgeVigente = fn($versione): string => $versione->isVigenteAl($dataRiferimento) ? '<span class="badge text-bg-success">' . Html::e($vigente) . '</span>' : '';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi <?= Html::e($soggetto->tipoSoggetto->getIcona()) ?> me-2 text-primary"></i><?= Html::e($personaFisica ? $soggetto->nominativo : ($datiFiscaliVigenti?->denominazione ?? $soggetto->nominativo)) ?></h1>
        <div class="d-flex flex-wrap gap-2 align-items-center small">
            <span class="badge text-bg-light border"><?= Html::e($soggetto->tipoSoggetto->getLabel()) ?></span>
            <?php if ($datiFiscaliVigenti?->tipoAttivita !== null): ?>
                <span class="badge text-bg-info-subtle text-info-emphasis"><i class="bi <?= Html::e($datiFiscaliVigenti->tipoAttivita->getIcona()) ?> me-1"></i><?= Html::e($datiFiscaliVigenti->tipoAttivita->getLabel()) ?></span>
            <?php endif; ?>
            <?php if ($soggetto->clienteStudio): ?>
                <span class="badge text-bg-primary-subtle text-primary-emphasis"><?= Html::e($campi['clienteStudio']) ?></span>
            <?php endif; ?>
            <?php if ($soggetto->isDeceduto()): ?>
                <span class="badge text-bg-dark"><?= Html::e($campi['dataDecesso']) ?> <?= Html::data($soggetto->dataDecesso) ?></span>
            <?php endif; ?>
            <?php if ($soggetto->isCessato($dataRiferimento)): ?>
                <span class="badge text-bg-secondary"><?= Html::e($campi['dataCessazione']) ?> <?= Html::data($soggetto->dataCessazione) ?></span>
            <?php endif; ?>
            <span class="text-body-secondary"><?= Html::e($campi['codiceFiscale']) ?>: <span class="font-monospace text-body"><?= Html::e($soggetto->codiceFiscale) ?></span></span>
            <?php if ($datiFiscaliVigenti?->partitaIva !== null): ?>
                <span class="text-body-secondary"><?= Html::e($campi['partitaIva']) ?>: <span class="font-monospace text-body"><?= Html::e($datiFiscaliVigenti->partitaIva) ?></span></span>
            <?php endif; ?>
            <?php if ($soggetto->codice !== null): ?>
                <span class="text-body-secondary"><?= Html::e($campi['codice']) ?>: <span class="text-body"><?= Html::e($soggetto->codice) ?></span></span>
            <?php endif; ?>
        </div>
    </div>
    <form method="get" action="/soggetto/show/soggetto/<?= Html::e($id) ?>/scheda/<?= Html::e($scheda) ?>" class="d-flex align-items-center gap-2">
        <label for="al" class="small text-body-secondary text-nowrap"><?= Html::e($situazioneAl) ?></label>
        <input type="date" class="form-control form-control-sm" id="al" name="al" value="<?= Html::e($dataRiferimento->format('Y-m-d')) ?>" onchange="this.form.submit()">
        <?php if ($isOggi === false): ?>
            <a class="btn btn-sm btn-outline-secondary" href="/soggetto/show/soggetto/<?= Html::e($id) ?>/scheda/<?= Html::e($scheda) ?>"><?= Html::e($oggi) ?></a>
        <?php endif; ?>
    </form>
    <div class="d-flex gap-2">
        <a href="/soggetto/update/soggetto/<?= Html::e($id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
        <form method="post" action="/soggetto/delete/soggetto/<?= Html::e($id) ?>" data-conferma="<?= Html::e($confermaEliminaSoggetto) ?>">
            <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger" title="<?= Html::e($eliminaSoggetto) ?>"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<?php if ($isOggi === false): ?>
    <div class="alert alert-info py-2"><i class="bi bi-clock-history me-2"></i><?= Html::e($storicoAvviso) ?> <strong><?= Html::data($dataRiferimento) ?></strong>.</div>
<?php endif; ?>

<ul class="nav nav-tabs" role="tablist">
    <?php foreach ($schede as $chiave => $etichetta): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $chiave === $scheda ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#scheda-<?= Html::e($chiave) ?>" type="button" role="tab"><?= Html::e($etichetta) ?></button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom shadow-sm p-3 mb-4">

    <div class="tab-pane fade <?= $scheda === 'generale' ? 'show active' : '' ?>" id="scheda-generale" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-6">
                <dl class="row mb-0">
                    <?php if ($personaFisica): ?>
                        <dt class="col-sm-5"><?= Html::e($campi['cognome']) ?> / <?= Html::e(mb_strtolower($campi['nome'])) ?></dt>
                        <dd class="col-sm-7"><?= Html::e($soggetto->cognome) ?> <?= Html::e($soggetto->nome) ?></dd>
                        <dt class="col-sm-5"><?= Html::e($campi['sesso']) ?></dt>
                        <dd class="col-sm-7"><?= Html::e($soggetto->sesso?->getLabel() ?? '') ?></dd>
                        <dt class="col-sm-5"><?= Html::e($campi['dataNascita']) ?></dt>
                        <dd class="col-sm-7"><?= Html::data($soggetto->dataNascita) ?></dd>
                        <dt class="col-sm-5"><?= Html::e($soggetto->comuneNascita !== null ? $campi['comuneNascita'] : $campi['nazioneNascita']) ?></dt>
                        <dd class="col-sm-7"><?= Html::e($soggetto->comuneNascita?->getEtichetta() ?? $soggetto->nazioneNascita?->denominazione ?? '') ?></dd>
                        <dt class="col-sm-5"><?= Html::e($campi['cittadinanza']) ?></dt>
                        <dd class="col-sm-7"><?= Html::e($soggetto->cittadinanza?->denominazione ?? '') ?></dd>
                        <dt class="col-sm-5"><?= Html::e($campi['statoCivile']) ?></dt>
                        <dd class="col-sm-7"><?= Html::e($soggetto->statoCivile?->getLabel() ?? '') ?></dd>
                        <?php if ($soggetto->dataDecesso !== null): ?>
                            <dt class="col-sm-5"><?= Html::e($campi['dataDecesso']) ?></dt>
                            <dd class="col-sm-7"><?= Html::data($soggetto->dataDecesso) ?></dd>
                        <?php endif; ?>
                    <?php else: ?>
                        <dt class="col-sm-5"><?= Html::e($campi['dataCostituzione']) ?></dt>
                        <dd class="col-sm-7"><?= Html::data($soggetto->dataCostituzione) ?></dd>
                    <?php endif; ?>
                    <?php if ($datiFiscaliVigenti !== null): ?>
                        <?php if ($datiFiscaliVigenti->tipoAttivita !== null): ?>
                            <dt class="col-sm-5"><?= Html::e($campi['tipoAttivita']) ?></dt>
                            <dd class="col-sm-7"><?= Html::e($datiFiscaliVigenti->tipoAttivita->getLabel()) ?></dd>
                        <?php endif; ?>
                        <?php if ($datiFiscaliVigenti->denominazione !== null): ?>
                            <dt class="col-sm-5"><?= Html::e($personaFisica ? $campi['ditta'] : $campi['denominazione']) ?></dt>
                            <dd class="col-sm-7"><?= Html::e($datiFiscaliVigenti->denominazione) ?></dd>
                        <?php endif; ?>
                        <?php if ($datiFiscaliVigenti->naturaGiuridica !== null): ?>
                            <dt class="col-sm-5"><?= Html::e($campi['naturaGiuridica']) ?></dt>
                            <dd class="col-sm-7"><?= Html::e($datiFiscaliVigenti->naturaGiuridica->getLabel()) ?><?php if ($datiFiscaliVigenti->enteNonCommerciale): ?> <span class="badge text-bg-light border"><?= Html::e($campi['enteNonCommerciale']) ?></span><?php endif; ?></dd>
                        <?php endif; ?>
                        <?php if ($datiFiscaliVigenti->codiceAteco !== null): ?>
                            <dt class="col-sm-5"><?= Html::e($campi['codiceAteco']) ?></dt>
                            <dd class="col-sm-7"><?= Html::e($datiFiscaliVigenti->codiceAteco->getEtichetta()) ?> <span class="badge text-bg-light border"><?= Html::e($datiFiscaliVigenti->codiceAteco->classificazione->getLabel()) ?></span><?php if ($datiFiscaliVigenti->descrizioneAttivita !== null): ?><div class="small text-body-secondary"><?= Html::e($datiFiscaliVigenti->descrizioneAttivita) ?></div><?php endif; ?></dd>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($soggetto->note !== null): ?>
                        <dt class="col-sm-5"><?= Html::e($campi['note']) ?></dt>
                        <dd class="col-sm-7"><?= nl2br(Html::e($soggetto->note)) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
            <div class="col-lg-6">
                <?php foreach ($indirizziVigenti as $indirizzo): ?>
                    <div class="mb-3">
                        <div class="small text-body-secondary"><i class="bi bi-geo-alt me-1"></i><?= Html::e($indirizzo->tipoIndirizzo->getLabel()) ?></div>
                        <div><?= Html::e($indirizzo->getRiga1()) ?></div>
                        <div><?= Html::e($indirizzo->getRiga2()) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($recapiti as $recapito): ?>
                    <?php if ($recapito->principale): ?>
                        <div class="mb-1">
                            <i class="bi <?= Html::e($recapito->tipoRecapito->getIcona()) ?> me-1 text-body-secondary" title="<?= Html::e($recapito->tipoRecapito->getLabel()) ?>"></i>
                            <?php $collegamento = $recapito->tipoRecapito->getCollegamento($recapito->valore); ?>
                            <?php if ($collegamento !== null): ?>
                                <a href="<?= Html::e($collegamento) ?>"><?= Html::e($recapito->valore) ?></a>
                            <?php else: ?>
                                <?= Html::e($recapito->valore) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="small text-body-secondary border-top pt-2 mt-3">
            <?= Html::e($inserito) ?> <?= Html::data($soggetto->dataInserimento, 'd/m/Y H:i') ?>
            <?php if ($soggetto->dataModifica !== null): ?>
                · <?= Html::e($modificato) ?> <?= Html::data($soggetto->dataModifica, 'd/m/Y H:i') ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade <?= $scheda === 'fiscali' ? 'show active' : '' ?>" id="scheda-fiscali" role="tabpanel">
        <div class="d-flex align-items-center mb-2">
            <h2 class="h6 mb-0 me-auto"><?= Html::e($storicoVersioni) ?></h2>
            <a href="/dati-fiscali/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['variazione']) ?></a>
        </div>
        <?php if (count($datiFiscaliStorico) === 0): ?>
            <p class="text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead><tr><th><?= Html::e($campi['validoDal']) ?></th><th><?= Html::e($personaFisica ? $campi['ditta'] : $campi['denominazione']) ?></th><th><?= Html::e($personaFisica ? $campi['tipoAttivita'] : $campi['naturaGiuridica']) ?></th><th><?= Html::e($campi['partitaIva']) ?></th><th><?= Html::e($campi['codiceAteco']) ?></th><th></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($datiFiscaliStorico as $versione): ?>
                            <tr class="<?= $classeRiga($versione) ?>">
                                <td class="text-nowrap"><?= $periodo($versione) ?></td>
                                <td><?= Html::e($versione->denominazione ?? '') ?></td>
                                <td><?= Html::e(($personaFisica ? $versione->tipoAttivita?->getLabel() : $versione->naturaGiuridica?->getLabel()) ?? ($personaFisica ? $nessunaAttivita : '')) ?></td>
                                <td class="font-monospace"><?= Html::e($versione->partitaIva ?? '') ?></td>
                                <td title="<?= Html::e($versione->codiceAteco?->titolo ?? '') ?>"><?= Html::e($versione->codiceAteco?->codice ?? '') ?></td>
                                <td><?= $badgeVigente($versione) ?></td>
                                <td class="text-end text-nowrap"><?= $pulsanteModifica('/dati-fiscali/update/dati-fiscali/' . $versione->id) ?> <?= $pulsanteElimina('/dati-fiscali/delete/dati-fiscali/' . $versione->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $scheda === 'indirizzi' ? 'show active' : '' ?>" id="scheda-indirizzi" role="tabpanel">
        <?php foreach ($soggetto->tipoSoggetto->getTipiIndirizzo() as $tipoIndirizzo): ?>
            <div class="d-flex align-items-center mt-2 mb-2">
                <h2 class="h6 mb-0 me-auto"><?= Html::e($tipoIndirizzo->getLabel()) ?></h2>
                <a href="/indirizzo/create/soggetto/<?= Html::e($id) ?>/tipo/<?= Html::e($tipoIndirizzo->value) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
            </div>
            <?php $versioni = array_filter(iterator_to_array($indirizziStorico), fn($indirizzo) => $indirizzo->tipoIndirizzo === $tipoIndirizzo); ?>
            <?php if (count($versioni) === 0): ?>
                <p class="small text-body-secondary"><?= Html::e($nessunDato) ?></p>
            <?php else: ?>
                <table class="table table-sm table-anagrafica mb-3">
                    <tbody>
                        <?php foreach ($versioni as $versione): ?>
                            <tr class="<?= $classeRiga($versione) ?>">
                                <td class="text-nowrap" style="width: 13rem;"><?= $periodo($versione) ?></td>
                                <td><?= $versione->presso !== null ? Html::e('c/o ' . $versione->presso) . ' - ' : '' ?><?= Html::e($versione->getRiga1()) ?> - <?= Html::e($versione->getRiga2()) ?></td>
                                <td><?= $badgeVigente($versione) ?></td>
                                <td class="text-end text-nowrap"><?= $pulsanteModifica('/indirizzo/update/indirizzo/' . $versione->id) ?> <?= $pulsanteElimina('/indirizzo/delete/indirizzo/' . $versione->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="tab-pane fade <?= $scheda === 'recapiti' ? 'show active' : '' ?>" id="scheda-recapiti" role="tabpanel">
        <div class="d-flex justify-content-end mb-2">
            <a href="/recapito/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
        </div>
        <?php if (count($recapiti) === 0): ?>
            <p class="text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($recapiti as $recapito): ?>
                        <?php $collegamento = $recapito->tipoRecapito->getCollegamento($recapito->valore); ?>
                        <tr>
                            <td style="width: 12rem;"><i class="bi <?= Html::e($recapito->tipoRecapito->getIcona()) ?> me-2"></i><?= Html::e($recapito->tipoRecapito->getLabel()) ?></td>
                            <td>
                                <?php if ($collegamento !== null): ?>
                                    <a href="<?= Html::e($collegamento) ?>"><?= Html::e($recapito->valore) ?></a>
                                <?php else: ?>
                                    <?= Html::e($recapito->valore) ?>
                                <?php endif; ?>
                                <?php if ($recapito->principale): ?><i class="bi bi-star-fill text-warning ms-1" title="Principale"></i><?php endif; ?>
                            </td>
                            <td class="small text-body-secondary"><?= Html::e($recapito->note ?? '') ?></td>
                            <td class="text-end text-nowrap"><?= $pulsanteModifica('/recapito/update/recapito/' . $recapito->id) ?> <?= $pulsanteElimina('/recapito/delete/recapito/' . $recapito->id) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $scheda === 'conti' ? 'show active' : '' ?>" id="scheda-conti" role="tabpanel">
        <div class="d-flex justify-content-end mb-2">
            <a href="/conto-corrente/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
        </div>
        <?php if (count($conti) === 0): ?>
            <p class="text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($conti as $conto): ?>
                        <tr class="<?= $conto->attivo ? '' : 'storico-non-vigente' ?>">
                            <td class="font-monospace">
                                <?= Html::e($conto->getIbanFormattato()) ?>
                                <?php if ($conto->predefinito): ?><i class="bi bi-star-fill text-warning ms-1" title="<?= Html::e($campi['contoPredefinito']) ?>"></i><?php endif; ?>
                                <?php if ($conto->attivo === false): ?><span class="badge text-bg-secondary ms-1"><?= Html::e($contoEstinto) ?></span><?php endif; ?>
                            </td>
                            <td class="small text-body-secondary"><?= Html::e($conto->descrizione ?? '') ?></td>
                            <td class="text-end text-nowrap"><?= $pulsanteModifica('/conto-corrente/update/conto/' . $conto->id) ?> <?= $pulsanteElimina('/conto-corrente/delete/conto/' . $conto->id) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $scheda === 'legami' ? 'show active' : '' ?>" id="scheda-legami" role="tabpanel">
        <div class="d-flex align-items-center mb-2">
            <h2 class="h6 mb-0 me-auto"><?= Html::e($collegatiTitolo) ?></h2>
            <a href="/legame/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
        </div>
        <?php if (count($collegati) === 0): ?>
            <p class="small text-body-secondary"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <table class="table table-sm table-anagrafica mb-4">
                <thead><tr><th><?= Html::e($campi['ruolo']) ?></th><th><?= Html::e($campi['soggettoCollegato']) ?></th><th><?= Html::e($campi['quotaPartecipazione']) ?></th><th><?= Html::e($campi['validoDal']) ?></th><th></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($collegati as $legame): ?>
                        <tr class="<?= $classeRiga($legame) ?>">
                            <td><?= Html::e($legame->ruolo->getLabel()) ?></td>
                            <td><a href="/soggetto/show/soggetto/<?= Html::e($legame->soggettoCollegato->id) ?>"><?= Html::e($legame->soggettoCollegato->nominativo) ?></a> <span class="small text-body-secondary font-monospace"><?= Html::e($legame->soggettoCollegato->codiceFiscale) ?></span></td>
                            <td><?= $legame->quotaPartecipazione !== null ? Html::e(number_format($legame->quotaPartecipazione, 2, ',', '.')) . ' %' : '' ?></td>
                            <td class="text-nowrap"><?= $periodo($legame) ?></td>
                            <td><?= $badgeVigente($legame) ?></td>
                            <td class="text-end text-nowrap"><?= $pulsanteModifica('/legame/update/legame/' . $legame->id) ?> <?= $pulsanteElimina('/legame/delete/legame/' . $legame->id) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <h2 class="h6"><?= Html::e($incarichiTitolo) ?></h2>
        <?php if (count($incarichi) === 0): ?>
            <p class="small text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($incarichi as $legame): ?>
                        <tr class="<?= $classeRiga($legame) ?>">
                            <td><?= Html::e($legame->ruolo->getLabel()) ?></td>
                            <td><a href="/soggetto/show/soggetto/<?= Html::e($legame->soggetto->id) ?>/scheda/legami"><?= Html::e($legame->soggetto->nominativo) ?></a></td>
                            <td><?= $legame->quotaPartecipazione !== null ? Html::e(number_format($legame->quotaPartecipazione, 2, ',', '.')) . ' %' : '' ?></td>
                            <td class="text-nowrap"><?= $periodo($legame) ?></td>
                            <td><?= $badgeVigente($legame) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $scheda === 'immobili' ? 'show active' : '' ?>" id="scheda-immobili" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
            <a href="/immobile/create/tipo/F/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-house-door me-1"></i><?= Html::e($nuovoFabbricato) ?></a>
            <a href="/immobile/create/tipo/T/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-tree me-1"></i><?= Html::e($nuovoTerreno) ?></a>
            <a href="/possesso/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-link-45deg me-1"></i><?= Html::e($collegaImmobile) ?></a>
            <?php if (ModuloSuite::imu->isDisponibile()): ?>
                <a href="/contribuente-imu/show/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-house-gear me-1"></i><?= Html::e(ModuloSuite::imu->getLabel()) ?></a>
            <?php endif; ?>
        </div>
        <?php if (count($possessi) === 0): ?>
            <p class="text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead><tr><th><?= Html::e($campi['immobile']) ?></th><th><?= Html::e($campi['classamento']) ?></th><th><?= Html::e($campi['titolo']) ?></th><th class="text-end"><?= Html::e($campi['quota']) ?></th><th><?= Html::e($campi['utilizzo']) ?></th><th><?= Html::e($campi['possessoDal']) ?></th><th></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($possessi as $possesso): ?>
                            <?php $immobile = $possesso->immobile; $classamento = $datiCatastaliVigenti[$immobile->id] ?? null; ?>
                            <tr class="<?= $classeRiga($possesso) ?>">
                                <td>
                                    <i class="bi <?= Html::e($immobile->tipoCatasto->getIcona()) ?> me-1"></i><a href="/immobile/show/immobile/<?= Html::e($immobile->id) ?>"><?= Html::e($immobile->getIdentificativo()) ?></a>
                                    <div class="small text-body-secondary"><?= Html::e($immobile->getUbicazione()) ?></div>
                                </td>
                                <td class="small text-nowrap">
                                    <?php if ($classamento !== null): ?>
                                        <?= Html::e($classamento->categoria?->value ?? $classamento->qualitaColtura ?? '') ?>
                                        <?php if ($classamento->rendita !== null): ?><div class="text-body-secondary">€ <?= Html::e(number_format($classamento->rendita, 2, ',', '.')) ?></div><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= Html::e($possesso->titolo->getLabel()) ?></td>
                                <td class="text-end"><?= Html::e(number_format($possesso->quota, 2, ',', '.')) ?>%</td>
                                <td class="small"><?= Html::e($possesso->utilizzo?->getLabel() ?? '') ?></td>
                                <td class="text-nowrap"><?= $periodo($possesso) ?></td>
                                <td><?= $badgeVigente($possesso) ?></td>
                                <td class="text-end text-nowrap"><?= $pulsanteModifica('/possesso/update/possesso/' . $possesso->id . '/ritorno/soggetto') ?> <?= $pulsanteElimina('/possesso/delete/possesso/' . $possesso->id . '/ritorno/soggetto') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($personaFisica): ?>
        <div class="tab-pane fade <?= $scheda === 'documenti' ? 'show active' : '' ?>" id="scheda-documenti" role="tabpanel">
            <div class="d-flex justify-content-end mb-2">
                <a href="/documento-identita/create/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
            </div>
            <?php if (count($documenti) === 0): ?>
                <p class="text-body-secondary mb-0"><?= Html::e($nessunDato) ?></p>
            <?php else: ?>
                <table class="table table-sm table-anagrafica mb-0">
                    <thead><tr><th><?= Html::e($campi['tipoDocumento']) ?></th><th><?= Html::e($campi['numero']) ?></th><th><?= Html::e($campi['rilasciatoDa']) ?></th><th><?= Html::e($campi['dataRilascio']) ?></th><th><?= Html::e($campi['dataScadenza']) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($documenti as $documento): ?>
                            <tr>
                                <td><?= Html::e($documento->tipoDocumento->getLabel()) ?></td>
                                <td class="font-monospace"><?= Html::e($documento->numero) ?></td>
                                <td><?= Html::e($documento->rilasciatoDa ?? '') ?></td>
                                <td><?= Html::data($documento->dataRilascio) ?></td>
                                <td><?= Html::data($documento->dataScadenza) ?> <?php if ($documento->isScaduto()): ?><span class="badge text-bg-danger"><?= Html::e($scaduto) ?></span><?php endif; ?></td>
                                <td class="text-end text-nowrap"><?= $pulsanteModifica('/documento-identita/update/documento-identita/' . $documento->id) ?> <?= $pulsanteElimina('/documento-identita/delete/documento-identita/' . $documento->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
