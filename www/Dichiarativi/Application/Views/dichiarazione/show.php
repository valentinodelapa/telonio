<?php

use Deleghe\Application\HelperClasses\Importo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$id = $dichiarazione->id;
$modificabile = $dichiarazione->isModificabile();
$redditi = $dichiarazione->modello->isRedditi();
$isa = $dichiarazione->modello === \Dichiarativi\Application\Enumerations\ModelloDichiarativo::isa;
$fileDisponibile = (count($errori) === 0) && ((count($segnalazioni) === 0) || $dichiarazione->flagConferma);

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <?php if ($modificabile): ?>
        <?php if ($isa === false): ?>
            <a href="/dichiarazione/update/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($titoloFrontespizio) ?></a>
        <?php endif; ?>
        <?php if (($dichiarazione->modello->isTrimestrale() === false) && ($redditi === false) && ($isa === false)): ?>
            <a href="/dichiarazione/rettifiche/dichiarazione/<?= Html::e($id) ?><?= ($modulo ?? 1) > 1 ? '/modulo/' . Html::e($modulo) : '' ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($prospettoD) ?></a>
        <?php endif; ?>
        <form method="post" action="/dichiarazione/proponi/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i><?= Html::e($azioni['riproponi']) ?></button></form>
    <?php endif; ?>
    <a href="/dichiarazione/stampa/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></a>
    <?php if ($modelloUfficiale): ?>
        <a href="/dichiarazione/modello/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i><?= Html::e($azioni['modelloUfficiale']) ?></a>
    <?php endif; ?>
    <?php if ($fileDisponibile && ($isa === false)): ?>
        <a href="/dichiarazione/scarica/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['fileTelematico']) ?></a>
    <?php endif; ?>
    <?php if ($modificabile): ?>
        <form method="post" action="/dichiarazione/delete/dichiarazione/<?= Html::e($id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
</div>
<p class="text-body-secondary small mb-3">
    <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
    <?= Html::e($dichiarazione->tipologia->getLabel()) ?>
    <?php if ($dichiarazione->dataPresentazione !== null): ?> · <?= Html::e($presentataIl) ?> <?= Html::data($dichiarazione->dataPresentazione) ?><?= $dichiarazione->protocollo !== null ? ' · ' . Html::e($dichiarazione->protocollo) : '' ?><?php endif; ?>
    · <?= Html::e($campi['scadenza']) ?> <?= Html::data($dichiarazione->getScadenza()) ?>
    <?php if ($dichiarazione->dataElaborazione !== null): ?> · <?= Html::e($propostaIl) ?> <?= Html::data($dichiarazione->dataElaborazione, 'd/m/Y H:i') ?><?php endif; ?>
</p>
<?php foreach ($errori as $errore): ?><div class="alert alert-danger small py-2 mb-2"><i class="bi bi-x-circle me-1"></i><?= Html::e($errore) ?></div><?php endforeach; ?>
<?php if (count($segnalazioni) > 0): ?>
    <div class="alert <?= $dichiarazione->flagConferma ? 'alert-secondary' : 'alert-warning' ?> small py-2 mb-2">
        <div class="fw-semibold"><?= Html::e($dichiarazione->flagConferma ? $segnalazioniConfermate : $titoloSegnalazioni) ?></div>
        <ul class="mb-0"><?php foreach ($segnalazioni as $segnalazione): ?><li><?= Html::e($segnalazione) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php foreach ($avvertenze as $avvertenza): ?><div class="alert alert-warning small py-2 mb-2"><?= Html::e($avvertenza) ?></div><?php endforeach; ?>
<?php if (count($dichiarazione->getAvvisi()) > 0): ?>
    <details class="alert alert-info small py-2 mb-3" open>
        <summary class="fw-semibold"><?= Html::e($titoloAvvisi) ?></summary>
        <ul class="mb-0 mt-1"><?php foreach ($dichiarazione->getAvvisi() as $avviso): ?><li><?= Html::e($avviso) ?></li><?php endforeach; ?></ul>
    </details>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <?php if ($isa): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloModelloIsa) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><strong><?= Html::e($dichiarazione->isa) ?></strong> <?= Html::e($indiceIsa?->descrizione ?? '') ?></li>
                <li class="list-group-item"><?= Html::e($campi['contribuente']) ?>: <?= Html::e($soggetto->nominativo) ?> · <?= Html::e($soggetto->codiceFiscale) ?></li>
                <?php if ($dichiarazione->dichiarazioneRedditi !== null): ?>
                    <li class="list-group-item"><?= Html::e($dichiarazioneRedditiCollegata) ?>: <a href="/dichiarazione/show/dichiarazione/<?= Html::e($dichiarazione->dichiarazioneRedditi->id) ?>"><?= Html::e($dichiarazione->dichiarazioneRedditi->getDescrizione()) ?></a></li>
                <?php endif; ?>
            </ul>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoModelloIsa) ?></div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <span class="fw-semibold me-auto"><?= Html::e($punteggioIsa) ?></span>
                <?php if ($dichiarazione->calcoloIsa !== null): ?>
                    <a href="/dichiarazione/stampa-isa/dichiarazione/<?= Html::e($id) ?>/tipo/modello" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i><?= Html::e($azioni['stampaModelloIsa']) ?></a>
                    <a href="/dichiarazione/stampa-isa/dichiarazione/<?= Html::e($id) ?>/tipo/esito" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i><?= Html::e($azioni['stampaEsitoIsa']) ?></a>
                <?php endif; ?>
                <?php if ($modificabile): ?>
                    <form method="post" action="/dichiarazione/calcola-isa/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-calculator me-1"></i><?= Html::e($azioni['calcolaIsa']) ?></button></form>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if ($esitoIsa === null): ?>
                    <li class="list-group-item text-body-secondary"><?= Html::e($nonCalcolato) ?></li>
                <?php else: ?>
                    <li class="list-group-item d-flex align-items-baseline gap-2"><span class="display-6 fw-semibold"><?= Html::e($esitoIsa['punteggio'] ?? '-') ?></span><span class="text-body-secondary">/ 10</span>
                        <?php if ($calcoloIsaAggiornato === false): ?><span class="badge text-bg-warning ms-auto"><?= Html::e($calcoloSuperato) ?></span><?php endif; ?></li>
                    <li class="list-group-item"><div class="fw-semibold mb-1"><?= Html::e($indicatoriAffidabilita) ?></div>
                        <?php foreach ($esitoIsa['affidabilita'] as $indicatore): ?><div class="d-flex"><span class="me-auto"><?= Html::e($indicatore['descrizione']) ?></span><?= Html::e($indicatore['punteggio']) ?></div><?php endforeach; ?></li>
                    <?php if (count($esitoIsa['anomalie']) > 0): ?>
                        <li class="list-group-item"><div class="fw-semibold mb-1"><?= Html::e($indicatoriAnomalia) ?></div>
                            <?php foreach ($esitoIsa['anomalie'] as $indicatore): ?><div class="d-flex text-danger-emphasis"><span class="me-auto"><?= Html::e($indicatore['descrizione']) ?></span><?= Html::e($indicatore['punteggio']) ?></div><?php endforeach; ?></li>
                    <?php endif; ?>
                    <?php if (count($esitoIsa['modelliBusiness']) > 0): ?>
                        <li class="list-group-item"><?= Html::e($modelloBusiness) ?>: <?= Html::e($esitoIsa['modelliBusiness'][0]['descrizione']) ?></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <?php if ($modificabile): ?>
                <form method="post" action="/dichiarazione/precalcolate-isa/dichiarazione/<?= Html::e($id) ?>" enctype="multipart/form-data" class="card-body d-flex gap-2 align-items-end border-top">
                    <?= $campoCsrf ?>
                    <div class="flex-grow-1"><label class="form-label small mb-0" for="precalcolate"><?= Html::e($fileVariabiliPrecalcolate) ?></label><input type="file" class="form-control form-control-sm" id="precalcolate" name="precalcolate" accept=".xml,application/xml,text/xml"></div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i><?= Html::e($azioni['importaPrecalcolate']) ?></button>
                </form>
            <?php endif; ?>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoCalcoloIsa) ?></div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloCpb) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if ($propostaCpb === null): ?>
                    <li class="list-group-item text-body-secondary"><?= Html::e($nessunaPropostaCpb) ?></li>
                <?php else: ?>
                    <?php foreach (['P0100601' => $redditoProposto2026, 'P0100701' => $redditoProposto2027, 'P0100801' => $irapProposta2026, 'P0100901' => $irapProposta2027] as $codiceCpb => $etichettaCpb): ?>
                        <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($etichettaCpb) ?></span><?= Html::e(Importo::euro((float) ($propostaCpb[$codiceCpb] ?? 0))) ?></li>
                    <?php endforeach; ?>
                    <?php if ($cpbAccettato): ?><li class="list-group-item text-success-emphasis"><i class="bi bi-check-circle me-1"></i><?= Html::e($testoCpbAccettato) ?></li><?php endif; ?>
                <?php endif; ?>
            </ul>
            <?php if ($modificabile && (($propostaCpb !== null) || $cpbAccettato)): ?>
                <div class="card-body d-flex gap-2">
                    <?php if (($propostaCpb !== null) && ($cpbAccettato === false)): ?>
                        <form method="post" action="/dichiarazione/concordato-isa/dichiarazione/<?= Html::e($id) ?>/scelta/aderisci"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-success"><?= Html::e($azioni['aderisciCpb']) ?></button></form>
                    <?php endif; ?>
                    <form method="post" action="/dichiarazione/concordato-isa/dichiarazione/<?= Html::e($id) ?>/scelta/rinuncia"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($cpbAccettato ? $azioni['revocaCpb'] : $azioni['rinunciaCpb']) ?></button></form>
                </div>
            <?php endif; ?>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoCpb) ?></div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($noteAggiuntiveIsa) ?></div>
            <?php if ($modificabile): ?>
                <form method="post" action="/dichiarazione/note-isa/dichiarazione/<?= Html::e($id) ?>" class="card-body">
                    <?= $campoCsrf ?>
                    <textarea class="form-control form-control-sm mb-2" name="note" rows="4" maxlength="1768" aria-label="<?= Html::e($noteAggiuntiveIsa) ?>"><?= Html::e($dichiarazione->annotazioni ?? '') ?></textarea>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['salvaNote']) ?></button>
                </form>
            <?php else: ?>
                <div class="card-body small"><?= nl2br(Html::e($dichiarazione->annotazioni ?? '-')) ?></div>
            <?php endif; ?>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoNoteIsa) ?></div>
        </div>
        <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloFrontespizio) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><?= Html::e($campi['contribuente']) ?>: <?= Html::e($soggetto->nominativo) ?> · <?= Html::e($soggetto->codiceFiscale) ?></li>
                <?php if ($soggetto->isPersonaFisica() === false): ?>
                    <li class="list-group-item"><?= Html::e($campi['naturaGiuridica']) ?>: <?= Html::e($dichiarazione->naturaGiuridica === null ? '-' : $dichiarazione->naturaGiuridica . ' - ' . $naturaGiuridica) ?></li>
                <?php endif; ?>
                <li class="list-group-item"><?= Html::e($campi['dichiarante']) ?>: <?= $dichiarazione->dichiarante === null ? '-' : Html::e($dichiarazione->dichiarante->nominativo . ' · ' . $dichiarazione->dichiarante->codiceFiscale) . '<div class="text-body-secondary">' . Html::e($dichiarazione->codiceCarica?->getLabel() ?? '') . '</div>' ?></li>
                <li class="list-group-item"><?= Html::e($campi['intermediario']) ?>: <?= $dichiarazione->intermediario === null ? '-' : Html::e($dichiarazione->intermediario->getEtichetta()) . '<div class="text-body-secondary">' . Html::e(($dichiarazione->impegno?->getLabel() ?? '') . ' · ') . Html::data($dichiarazione->dataImpegno) . '</div>' ?></li>
                <li class="list-group-item"><?= Html::e($campi['vistoProfessionista']) ?>: <?= $dichiarazione->vistoProfessionista !== null ? Html::e($dichiarazione->vistoProfessionista->nominativo . ' · ' . $dichiarazione->vistoProfessionista->codiceFiscale)
                        : ($dichiarazione->vistoCaf !== null ? Html::e($dichiarazione->vistoCaf->nominativo . ' · ' . ($dichiarazione->vistoResponsabileCaf?->nominativo ?? '')) : ($dichiarazione->esoneroVisto ? Html::e($campi['esoneroVisto']) : '-')) ?></li>
                <?php if ($redditi): ?>
                    <li class="list-group-item"><?= Html::e($campi['ottoPerMille']) ?>: <?= Html::e($dichiarazione->ottoPerMille === null ? '-' : $dichiarazione->ottoPerMille->getLabel() . ($dichiarazione->finalitaStato !== null ? ' (' . (\Dichiarativi\Application\Enumerations\OttoPerMille::getFinalitaStato()[$dichiarazione->finalitaStato] ?? '') . ')' : '')) ?></li>
                    <li class="list-group-item"><?= Html::e($campi['cinquePerMille']) ?>: <?= Html::e($dichiarazione->cinquePerMille === null ? '-' : $dichiarazione->cinquePerMille->getLabel() . ($dichiarazione->beneficiarioCinquePerMille !== null ? ' · ' . $dichiarazione->beneficiarioCinquePerMille : '')) ?></li>
                    <li class="list-group-item"><?= Html::e($campi['duePerMille']) ?>: <?= Html::e($dichiarazione->duePerMille === null ? '-' : ((\Dichiarativi\Application\Services\Redditi\PartitiDuePerMille::getPartiti($dichiarazione->anno)[$dichiarazione->duePerMille] ?? '') . ' (' . $dichiarazione->duePerMille . ')')) ?></li>
                <?php endif; ?>
                <?php if ($dichiarazione->flagConferma): ?><li class="list-group-item text-warning-emphasis"><?= Html::e($campi['flagConferma']) ?></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($redditi): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloModelliIsa) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($modelliIsa as $modelloIsa): ?>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <span class="me-auto">
                            <span class="font-monospace"><?= Html::e($modelloIsa['quadro']) ?></span> · <?= Html::e($modelloIsa['codiceAttivita'] ?? '-') ?>
                            <?php if ($modelloIsa['indice'] !== null): ?> · <strong><?= Html::e($modelloIsa['indice']->codice) ?></strong> <?= Html::e($modelloIsa['indice']->descrizione) ?><?php else: ?> · <span class="text-body-secondary"><?= Html::e($attivitaSenzaIsa) ?></span><?php endif; ?>
                            <?php if ($modelloIsa['causaEsclusione'] !== null): ?><div class="text-warning-emphasis"><?= Html::e($causaEsclusioneIsa . ' ' . $modelloIsa['causaEsclusione']) ?></div><?php endif; ?>
                        </span>
                        <?php if ($modelloIsa['dichiarazione'] !== null): ?>
                            <a href="/dichiarazione/show/dichiarazione/<?= Html::e($modelloIsa['dichiarazione']->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['apriModelloIsa']) ?></a>
                        <?php elseif ($modelloIsa['indice'] !== null): ?>
                            <form method="post" action="/dichiarazione/crea-isa/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($modelloIsa['quadro']) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['creaModelloIsa']) ?></button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (count($modelliIsa) === 0): ?><li class="list-group-item text-body-secondary"><?= Html::e($nessunQuadroContabile) ?></li><?php endif; ?>
            </ul>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoModelliIsa) ?></div>
        </div>
        <?php endif; ?>

        <?php if (($redditi === false) && ($isa === false)): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <span class="fw-semibold me-auto"><?= Html::e($titoloOrganoControllo) ?></span>
                <?php if ($modificabile): ?>
                    <form method="post" action="/dichiarazione/proponi-sottoscrizioni/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($aiutoProponiOrgano) ?>"><i class="bi bi-magic"></i></button></form>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($sottoscrizioni as $sottoscrizione): ?>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <span class="me-auto"><?= Html::e($sottoscrizione->tipoSoggetto . ' - ' . $tipiSottoscrizione[$sottoscrizione->tipoSoggetto]) ?>: <?= Html::e($sottoscrizione->soggetto->nominativo . ' · ' . $sottoscrizione->soggetto->codiceFiscale) ?></span>
                        <?php if ($modificabile): ?>
                            <form method="post" action="/dichiarazione/elimina-sottoscrizione/sottoscrizione/<?= Html::e($sottoscrizione->id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-link text-danger p-0" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-x-lg"></i></button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (count($sottoscrizioni) === 0): ?><li class="list-group-item text-body-secondary"><?= Html::e($nessunaSottoscrizione) ?></li><?php endif; ?>
            </ul>
            <?php if ($modificabile && (count($sottoscrizioni) < 5)): ?>
                <form method="post" action="/dichiarazione/aggiungi-sottoscrizione/dichiarazione/<?= Html::e($id) ?>" class="card-body d-flex flex-wrap gap-2 align-items-end">
                    <?= $campoCsrf ?>
                    <select class="form-select form-select-sm" name="tipoSoggetto" style="width: 12rem" aria-label="<?= Html::e($campi['tipoSottoscrizione']) ?>">
                        <?php foreach ($tipiSottoscrizione as $codice => $etichetta): ?><option value="<?= Html::e($codice) ?>"><?= Html::e($codice . ' - ' . $etichetta) ?></option><?php endforeach; ?>
                    </select>
                    <div class="flex-grow-1 autocompleta" data-url="/soggetto/cerca">
                        <input type="text" class="form-control form-control-sm autocompleta-testo" placeholder="<?= Html::e($segnapostoSoggetto) ?>" autocomplete="off" aria-label="<?= Html::e($segnapostoSoggetto) ?>">
                        <input type="hidden" class="autocompleta-valore" name="soggetto">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['aggiungi']) ?></button>
                </form>
            <?php endif; ?>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoOrganoControllo) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($isa): ?>
        <?php elseif ($redditi): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloRisultato) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($riepilogoRedditi as $etichettaRigo => $importoRigo): ?>
                    <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($etichettaRigo) ?></span><?= Html::e(Importo::euro($importoRigo)) ?></li>
                <?php endforeach; ?>
                <?php if (count($riepilogoRedditi) === 0): ?><li class="list-group-item text-body-secondary"><?= Html::e($nessunRedditoProposto) ?></li><?php endif; ?>
                <?php foreach ($versamenti as $versamento): ?>
                    <li class="list-group-item text-body-secondary"><?= Html::e($versamento->codiceTributo?->codice ?? '') ?> · <?= Html::data($versamento->scadenza) ?> · <?= Html::e(Importo::euro($versamento->importoDebito)) ?> · <?= Html::e($versamento->delega === null ? $daVersare : $versamento->delega->stato->getLabel()) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="post" action="/dichiarazione/versamenti/dichiarazione/<?= Html::e($id) ?>" class="row g-2 align-items-end"><?= $campoCsrf ?>
                    <div class="col-8">
                        <label class="form-label small mb-0" for="modalitaSaldo"><?= Html::e($campi['modalitaSaldoRedditi']) ?></label>
                        <select class="form-select form-select-sm" id="modalitaSaldo" name="modalitaSaldo">
                            <?php foreach ($modalitaSaldo as $modalita): ?><option value="<?= Html::e($modalita->value) ?>" <?= $modalita === $dichiarazione->modalitaSaldo ? 'selected' : '' ?>><?= Html::e($modalita->getLabelRedditi()) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4"><label class="form-label small mb-0" for="numeroRate"><?= Html::e($campi['numeroRate']) ?></label><input type="number" min="2" max="7" class="form-control form-control-sm" id="numeroRate" name="numeroRate" value="<?= Html::e($dichiarazione->numeroRate ?? '') ?>"></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-credit-card-2-front me-1"></i><?= Html::e($azioni['registraVersamenti']) ?></button></div>
                </form>
                <div class="small text-body-secondary mt-2"><?= Html::e($aiutoVersamentiRedditi) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloRisultato) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($rigoVx1) ?></span><strong><?= Html::e(Importo::euro($quadri->getImporto('VX001001', 1))) ?></strong></li>
                <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($rigoVx2) ?></span><strong class="text-success"><?= Html::e(Importo::euro($quadri->getImporto('VX002001', 1))) ?></strong></li>
                <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($rigoVx5) ?></span><?= Html::e(Importo::euro($quadri->getImporto('VX005001', 1))) ?></li>
                <?php foreach (['VX007001' => $rigoVx7, 'VX008001' => $rigoVx8, 'VY001001' => $rigoVy1, 'VY005001' => $rigoVy5] as $codiceRigo => $etichettaRigo): ?>
                    <?php if ($quadri->getImporto($codiceRigo, 1) > 0): ?><li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($etichettaRigo) ?></span><?= Html::e(Importo::euro($quadri->getImporto($codiceRigo, 1))) ?></li><?php endif; ?>
                <?php endforeach; ?>
                <?php if ($quadri->get('VF034009', 1) !== null): ?><li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($percentualeDetrazione) ?></span><?= Html::e($quadri->get('VF034009', 1)) ?>%</li><?php endif; ?>
                <?php foreach ($versamenti as $versamento): ?>
                    <li class="list-group-item text-body-secondary"><?= Html::e($versamento->codiceTributo?->codice ?? '') ?> · <?= Html::data($versamento->scadenza) ?> · <?= Html::e(Importo::euro($versamento->importoDebito)) ?> · <?= Html::e($versamento->delega === null ? $daVersare : $versamento->delega->stato->getLabel()) ?></li>
                <?php endforeach; ?>
                <?php if ($credito !== null): ?>
                    <li class="list-group-item text-body-secondary"><?= Html::e($creditoRegistrato) ?> <?= Html::e(Importo::euro($credito->importo)) ?> · <?= Html::e($compensabileDal) ?> <?= Html::data($credito->utilizzabileDal) ?></li>
                <?php endif; ?>
            </ul>
            <div class="card-body">
                <form method="post" action="/dichiarazione/versamenti/dichiarazione/<?= Html::e($id) ?>" class="row g-2 align-items-end"><?= $campoCsrf ?>
                    <div class="col-12">
                        <label class="form-label small mb-0" for="modalitaSaldo"><?= Html::e($campi['modalitaSaldo']) ?></label>
                        <select class="form-select form-select-sm" id="modalitaSaldo" name="modalitaSaldo">
                            <?php foreach ($modalitaSaldo as $modalita): ?><option value="<?= Html::e($modalita->value) ?>" <?= $modalita === $dichiarazione->modalitaSaldo ? 'selected' : '' ?>><?= Html::e($modalita->getLabel()) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4"><label class="form-label small mb-0" for="numeroRate"><?= Html::e($campi['numeroRate']) ?></label><input type="number" min="2" max="9" class="form-control form-control-sm" id="numeroRate" name="numeroRate" value="<?= Html::e($dichiarazione->numeroRate ?? '') ?>"></div>
                    <div class="col-8"><label class="form-label small mb-0" for="dataDifferimento"><?= Html::e($campi['dataDifferimento']) ?></label><input type="date" class="form-control form-control-sm" id="dataDifferimento" name="dataDifferimento" value="<?= Html::e($dataDifferimento->format('Y-m-d')) ?>"></div>
                    <div class="col-12 form-check small ms-1"><input class="form-check-input" type="checkbox" id="maggiorazioneUlteriore" name="maggiorazioneUlteriore" value="1" <?= $dichiarazione->maggiorazioneUlteriore ? 'checked' : '' ?>><label class="form-check-label" for="maggiorazioneUlteriore"><?= Html::e($campi['maggiorazioneUlteriore']) ?></label></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-credit-card-2-front me-1"></i><?= Html::e($azioni['registraVersamenti']) ?></button></div>
                </form>
                <div class="small text-body-secondary mt-2"><?= Html::e($aiutoVersamenti) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isa === false): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <?php if ($modificabile): ?>
                    <form method="post" action="/dichiarazione/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazione::presentata->value) ?>" class="d-flex flex-wrap align-items-end gap-2">
                        <?= $campoCsrf ?>
                        <div><label class="form-label small mb-0" for="dataPresentazione"><?= Html::e($campi['dataPresentazione']) ?></label><input type="date" class="form-control form-control-sm" id="dataPresentazione" name="dataPresentazione" value="<?= Html::e(date('Y-m-d')) ?>"></div>
                        <div><label class="form-label small mb-0" for="protocollo"><?= Html::e($campi['protocollo']) ?></label><input type="text" class="form-control form-control-sm" id="protocollo" name="protocollo" maxlength="50"></div>
                        <button type="submit" class="btn btn-sm btn-success" <?= $fileDisponibile ? '' : 'disabled' ?>><?= Html::e($azioni['segnaPresentata']) ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/dichiarazione/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazione::bozza->value) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['riportaBozza']) ?></button></form>
                <?php endif; ?>
                <div class="small text-body-secondary mt-2"><?= Html::e($aiutoTelematico) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <ul class="nav nav-pills me-auto">
                <?php foreach ($moduliDichiarazione as $numero => $etichetta): ?>
                    <li class="nav-item"><a class="nav-link py-1 <?= $numero === $modulo ? 'active' : '' ?>" href="/dichiarazione/show/dichiarazione/<?= Html::e($id) ?><?= $numero > 1 ? '/quadro/' . Html::e($redditi ? ($quadri->getQuadriCompilati($numero)[0] ?? 'RB') : 'VA') . '/modulo/' . Html::e($numero) : '' ?>" title="<?= Html::e($etichetta) ?>"><?= Html::e($numero === 1 ? $etichetta : mb_strimwidth($etichetta, 0, 40, '…')) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($modificabile && ($modulo > 1)): ?>
                <form method="post" action="/dichiarazione/elimina-modulo/dichiarazione/<?= Html::e($id) ?>/numero/<?= Html::e($modulo) ?>" data-conferma="<?= Html::e($confermaEliminaModulo) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['eliminaModulo']) ?></button></form>
            <?php endif; ?>
            <?php if ($modificabile && ($redditi === false) && ($isa === false)): ?>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuovoModulo" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiModulo']) ?></button>
            <?php endif; ?>
        </div>
        <?php if ($modificabile && ($redditi === false) && ($isa === false)): ?>
            <div class="collapse mb-2" id="nuovoModulo">
                <form method="post" action="/dichiarazione/aggiungi-modulo/dichiarazione/<?= Html::e($id) ?>" class="card card-body shadow-sm row g-2 flex-row align-items-end">
                    <?= $campoCsrf ?>
                    <div class="col-md-3"><label class="form-label small mb-0" for="tipoModulo"><?= Html::e($campi['tipoModulo']) ?></label>
                        <select class="form-select form-select-sm" id="tipoModulo" name="tipo"><?php foreach ($tipiModulo as $tipo): ?><option value="<?= Html::e($tipo->value) ?>"><?= Html::e($tipo->getLabel()) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label small mb-0"><?= Html::e($campi['soggettoFonte']) ?></label>
                        <div class="autocompleta" data-url="/soggetto/cerca"><input type="text" class="form-control form-control-sm autocompleta-testo" placeholder="<?= Html::e($segnapostoSoggetto) ?>" autocomplete="off" aria-label="<?= Html::e($campi['soggettoFonte']) ?>">
                        <input type="hidden" class="autocompleta-valore" name="soggettoFonte"></div></div>
                    <div class="col-md-2"><label class="form-label small mb-0" for="descrizioneModulo"><?= Html::e($campi['descrizione']) ?></label><input type="text" class="form-control form-control-sm" id="descrizioneModulo" name="descrizione" maxlength="255"></div>
                    <div class="col-md-2"><label class="form-label small mb-0" for="dataOperazione"><?= Html::e($campi['dataOperazione']) ?></label><input type="date" class="form-control form-control-sm" id="dataOperazione" name="dataOperazione"></div>
                    <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-primary"><?= Html::e($azioni['aggiungi']) ?></button></div>
                    <div class="col-12 small text-body-secondary"><?= Html::e($aiutoModuli) ?></div>
                </form>
            </div>
        <?php endif; ?>
        <ul class="nav nav-tabs">
            <?php foreach ($elencoQuadri as $sigla => $denominazione): ?>
                <li class="nav-item"><a class="nav-link <?= $sigla === $quadro ? 'active' : '' ?>" href="/dichiarazione/show/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($sigla) ?><?= $modulo > 1 ? '/modulo/' . Html::e($modulo) : '' ?>" title="<?= Html::e($denominazione) ?>"><?= Html::e($sigla) ?></a></li>
            <?php endforeach; ?>
            <?php if ($modificabile && (count($altriQuadri) > 0)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false"><i class="bi bi-plus-lg"></i> <?= Html::e($altroQuadro) ?></a>
                    <ul class="dropdown-menu">
                        <?php foreach ($altriQuadri as $sigla => $denominazione): ?><li><a class="dropdown-item small" href="/dichiarazione/quadro/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($sigla) ?><?= $modulo > 1 ? '/modulo/' . Html::e($modulo) : '' ?>"><?= Html::e($sigla . ' - ' . $denominazione) ?></a></li><?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
        <div class="card shadow-sm border-top-0 rounded-top-0">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <span class="fw-semibold me-auto"><?= Html::e($quadro . ' - ' . $elencoQuadri[$quadro]) ?></span>
                <?php if ($modificabile): ?>
                    <a href="/dichiarazione/quadro/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($quadro) ?><?= $modulo > 1 ? '/modulo/' . Html::e($modulo) : '' ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modificaQuadro']) ?></a>
                <?php endif; ?>
            </div>
            <?php if (count($righe) === 0): ?>
                <div class="card-body small text-body-secondary"><?= Html::e($quadroVuoto) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th><?= Html::e($campi['rigo']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th class="text-end"><?= Html::e($campi['valore']) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($righe as ['campo' => $campo, 'valore' => $valore, 'manuale' => $manuale]): ?>
                                <tr>
                                    <td class="text-nowrap small font-monospace" title="<?= Html::e($campo->codice) ?>"><?= Html::e($campo->getEtichettaRigo()) ?><span class="text-body-secondary"> c. <?= Html::e($campo->getColonna()) ?></span></td>
                                    <td class="small"><?= Html::e($campo->descrizione) ?></td>
                                    <td class="text-end text-nowrap"><?= Html::e($campo->formato?->mostra($valore) ?? $valore) ?>
                                        <?php if ($manuale): ?><span class="badge text-bg-warning ms-1" title="<?= Html::e($aiutoManuale) ?>"><?= Html::e($badgeManuale) ?></span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
