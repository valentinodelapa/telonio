<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['gruppoIva']) ?> <?= Html::e($annoIva) ?><?= $periodicitaGruppo === null ? '' : ' · ' . Html::e($periodicitaGruppo->getLabel()) ?></h1>
    <a href="/gruppo-iva/create/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovoGruppo) ?></a>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoIva) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniIva as $voce): ?><li><a class="dropdown-item <?= $voce === $annoIva ? 'active' : '' ?>" href="/gruppo-iva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<?php foreach ($partecipazioni as $partecipazione): ?>
    <div class="alert alert-info small"><?= Html::e(sprintf($partecipaA, $partecipazione->gruppoIva->controllante->nominativo, $partecipazione->annoIngresso)) ?><?= $partecipazione->annoUscita === null ? '' : Html::e(sprintf($uscitaDal, $partecipazione->annoUscita)) ?><?= $partecipazione->dataCessazione === null ? '' : ' · ' . Html::e($campi['dataCessazione']) . ' ' . Html::data($partecipazione->dataCessazione) ?></div>
<?php endforeach; ?>

<?php if (count($gruppi) > 0): ?>
    <div class="list-group shadow-sm mb-3">
        <?php foreach ($gruppi as $voce): ?>
            <a href="/gruppo-iva/update/gruppo/<?= Html::e($voce->id) ?>" class="list-group-item list-group-item-action <?= ($gruppo?->id ?? null) === $voce->id ? 'active' : '' ?>"><?= Html::e($voce->getEtichetta()) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($gruppo === null): ?>
    <p class="text-body-secondary"><?= Html::e(count($gruppi) === 0 ? $nessunGruppo : $gruppoNonAttivo) ?></p>
<?php else: ?>
    <?php foreach ($requisiti as $avviso): ?><div class="alert alert-warning small mb-2"><?= Html::e($avviso) ?></div><?php endforeach; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center"><span class="fw-semibold me-auto"><?= Html::e($titoloPartecipanti) ?></span><a href="/gruppo-iva/partecipante/gruppo/<?= Html::e($gruppo->id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovaControllata) ?></a></div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><span class="fw-semibold"><?= Html::e($gruppo->controllante->nominativo) ?></span> <span class="badge text-bg-light border"><?= Html::e($campi['controllante']) ?></span></li>
            <?php foreach ($partecipanti as $partecipante): ?>
                <li class="list-group-item">
                    <a href="/gruppo-iva/partecipante/gruppo/<?= Html::e($gruppo->id) ?>/partecipante/<?= Html::e($partecipante->id) ?>" class="fw-semibold text-decoration-none <?= $partecipante->partecipaNel($annoIva) ? '' : 'text-body-secondary' ?>"><?= Html::e($partecipante->soggetto->nominativo) ?></a>
                    <span class="small text-body-secondary"><?= Html::e($campi['annoIngresso']) ?> <?= Html::e($partecipante->annoIngresso) ?><?= $partecipante->annoUscita === null ? '' : ' · ' . Html::e($campi['annoUscita']) . ' ' . Html::e($partecipante->annoUscita) ?><?= $partecipante->dataCessazione === null ? '' : ' · ' . Html::e($campi['dataCessazione']) . ' ' . Html::data($partecipante->dataCessazione) ?><?= $partecipante->percentualePossesso === null ? '' : ' · ' . Html::e(number_format($partecipante->percentualePossesso, 2, ',', '.')) . '%' ?><?= $partecipante->soloCatena ? ' · ' . Html::e($campi['soloCatena']) : '' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($titoloLiquidazioni) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th><?= Html::e($campi['periodo']) ?></th><th class="text-end"><?= Html::e($campi['debitiTrasferiti']) ?></th><th class="text-end"><?= Html::e($campi['creditiTrasferiti']) ?></th><th class="text-end"><?= Html::e($campi['importoDovuto']) ?></th><th class="text-end"><?= Html::e($campi['creditoFinale']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th style="min-width: 18rem"></th></tr></thead>
                <tbody>
                    <?php foreach ($periodi as $periodo => $dati): ?>
                        <?php $liquidazione = $liquidazioni[$periodo] ?? null; ?>
                        <tr>
                            <td><?= Html::e($liquidazione?->getDescrizionePeriodo() ?? $dati['descrizione']) ?><?php if (($liquidazione?->avvisi ?? null) !== null): ?> <i class="bi bi-exclamation-triangle text-warning" title="<?= Html::e($liquidazione->avvisi) ?>"></i><?php endif; ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->debitiTrasferiti)) ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->creditiTrasferiti)) ?></td>
                            <td class="text-end fw-semibold"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->importoDovuto, true)) ?><?= ($liquidazione !== null) && ($liquidazione->debitoRinviato > 0) ? '<div class="small fw-normal text-body-secondary">' . Html::e(Importo::euro($liquidazione->debitoRinviato)) . ' rinviato</div>' : '' ?></td>
                            <td class="text-end text-success"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->creditoFinale, true)) ?></td>
                            <td class="small text-nowrap"><?= Html::data($dati['scadenza']) ?></td>
                            <td>
                                <form method="post" action="/gruppo-iva/elabora/gruppo/<?= Html::e($gruppo->id) ?>/anno/<?= Html::e($annoIva) ?>/periodo/<?= Html::e($periodo) ?>" class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                    <?= $campoCsrf ?>
                                    <input type="text" class="form-control form-control-sm text-end" style="width: 7rem" name="creditoAnnoPrecedente" value="<?= $liquidazione === null || $liquidazione->creditoAnnoPrecedente == 0 ? '' : Html::e(Importo::euro($liquidazione->creditoAnnoPrecedente)) ?>" placeholder="0,00" title="<?= Html::e($campi['creditoAnnoPrecedente']) ?>" inputmode="decimal">
                                    <?php if ($conTrimestrali && ($periodo % 3 === 0) && ($periodo < 12)): ?>
                                        <input type="hidden" name="anticipa" value="0">
                                        <div class="form-check form-check-inline mb-0 small" title="<?= Html::e($aiutoAnticipa) ?>">
                                            <input class="form-check-input" type="checkbox" name="anticipa" value="1" id="anticipa<?= Html::e($periodo) ?>" <?= isset($trimestriSeparati[intdiv($periodo, 3)]) || (($liquidazione !== null) && ($liquidazione->trimestreAnticipato === null)) ? '' : 'checked' ?>>
                                            <label class="form-check-label" for="anticipa<?= Html::e($periodo) ?>"><?= Html::e($anticipaTrimestre) ?></label>
                                        </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm <?= $liquidazione === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($liquidazione === null ? $azioni['elabora'] : $azioni['rielabora']) ?></button>
                                </form>
                                <?php if ($liquidazione !== null): ?>
                                    <form method="post" action="/gruppo-iva/elimina-liquidazione/liquidazione/<?= Html::e($liquidazione->id) ?>" class="text-end mt-1" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= Html::e($azioni['elimina']) ?></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($conTrimestrali && ($periodo % 3 === 0) && ($periodo < 12) && ($liquidazione !== null) && ($liquidazione->trimestreAnticipato === null)): ?>
                            <?php $trimestre = intdiv($periodo, 3); $separata = $trimestriSeparati[$trimestre] ?? null; ?>
                            <tr class="table-light">
                                <td class="ps-4"><?= Html::e($separata?->getDescrizionePeriodo() ?? $trimestre . '° ' . $campi['trimestre'] . ' · ' . $titoloTrimestreSeparato) ?></td>
                                <td class="text-end"><?= $separata === null ? '' : Html::e(Importo::euro($separata->debitiTrasferiti)) ?></td>
                                <td class="text-end"><?= $separata === null ? '' : Html::e(Importo::euro($separata->creditiTrasferiti)) ?></td>
                                <td class="text-end fw-semibold"><?= $separata === null ? '' : Html::e(Importo::euro($separata->importoDovuto, true)) ?></td>
                                <td class="text-end text-success"><?= $separata === null ? '' : Html::e(Importo::euro($separata->creditoFinale, true)) ?></td>
                                <td class="small text-nowrap"><?= $separata?->scadenza === null ? '' : Html::data($separata->scadenza) ?></td>
                                <td>
                                    <form method="post" action="/gruppo-iva/elabora/gruppo/<?= Html::e($gruppo->id) ?>/anno/<?= Html::e($annoIva) ?>/periodo/<?= Html::e($trimestre) ?>" class="text-end"><?= $campoCsrf ?><input type="hidden" name="trimestre" value="1"><button type="submit" class="btn btn-sm <?= $separata === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($separata === null ? $azioni['elabora'] : $azioni['rielabora']) ?></button></form>
                                    <?php if ($separata !== null): ?>
                                        <form method="post" action="/gruppo-iva/elimina-liquidazione/liquidazione/<?= Html::e($separata->id) ?>" class="text-end mt-1" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= Html::e($azioni['elimina']) ?></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoLiquidazioni) ?></div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-semibold"><?= Html::e($accontoGruppo) ?> <?= Html::e($annoIva) ?></div>
                    <div class="small text-body-secondary mb-2"><?= $acconto === null ? Html::e($accontoNonCalcolato) : Html::e($acconto->metodo->getLabel()) . ': € ' . Html::e(Importo::euro($acconto->importo)) ?></div>
                    <a href="/acconto-iva/gruppo/gruppo/<?= Html::e($gruppo->id) ?>/anno/<?= Html::e($annoIva) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($calcolaAcconto) ?></a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-2"><?= Html::e($comunicazioniGruppo) ?></div>
                    <?php for ($trimestre = 1; $trimestre <= 4; $trimestre++): ?>
                        <?php $comunicazione = $comunicazioni[$trimestre] ?? null; ?>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="me-auto small"><?= Html::e($trimestre) ?>° <?= Html::e($campi['trimestre']) ?><?= $comunicazione === null ? '' : ' · <a href="/comunicazione-lipe/show/comunicazione/' . Html::e($comunicazione->id) . '">' . Html::e($comunicazione->stato->getLabel()) . '</a>' ?></span>
                            <form method="post" action="/gruppo-iva/lipe/gruppo/<?= Html::e($gruppo->id) ?>/anno/<?= Html::e($annoIva) ?>/trimestre/<?= Html::e($trimestre) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($comunicazione === null ? $azioni['prepara'] : $azioni['riprepara']) ?></button></form>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
