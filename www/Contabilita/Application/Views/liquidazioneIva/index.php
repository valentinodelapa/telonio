<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['liquidazioni']) ?> <?= Html::e($annoIva) ?><?= $periodicita === null ? '' : ' · ' . Html::e($periodicita->getLabel()) ?></h1>
    <a href="/comunicazione-lipe/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-send me-1"></i><?= Html::e($comunicazioniLipe) ?></a>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoIva) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniIva as $voce): ?><li><a class="dropdown-item <?= $voce === $annoIva ? 'active' : '' ?>" href="/liquidazione-iva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<?php if ($erroreIva !== null): ?>
    <div class="alert alert-warning"><?= Html::e($erroreIva) ?> <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>">Anagrafica</a></div>
<?php else: ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th><?= Html::e($campi['periodo']) ?></th><th class="text-end"><?= Html::e($campi['ivaEsigibile']) ?></th><th class="text-end"><?= Html::e($campi['ivaDetraibile']) ?></th><th class="text-end"><?= Html::e($campi['importoDovuto']) ?></th><th class="text-end"><?= Html::e($campi['creditoFinale']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th style="min-width: 22rem"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($periodi as $periodo => $dati): ?>
                        <?php $liquidazione = $liquidazioni[$periodo] ?? null; ?>
                        <tr>
                            <td><?= $liquidazione === null ? Html::e($dati['descrizione']) : '<a href="/liquidazione-iva/show/liquidazione/' . Html::e($liquidazione->id) . '" class="fw-semibold text-decoration-none">' . Html::e($dati['descrizione']) . '</a>' ?><?= ($liquidazione?->trimestreAnticipato ?? null) === null ? '' : '<div class="small text-body-secondary">+ ' . Html::e($liquidazione->trimestreAnticipato) . '° ' . Html::e($campi['trimestre']) . '</div>' ?><?= ($liquidazione?->gruppoIva ?? null) === null ? '' : ' <span class="badge text-bg-info" title="' . Html::e(sprintf($trasferitaAlGruppo, $liquidazione->gruppoIva->controllante->nominativo)) . '">' . Html::e($gruppoBadge) . '</span>' ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->ivaEsigibile)) ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->ivaDetraibile)) ?></td>
                            <td class="text-end fw-semibold"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->importoDovuto, true)) ?><?= ($liquidazione !== null) && ($liquidazione->debitoRinviato > 0) ? '<div class="small fw-normal text-body-secondary">' . Html::e(Importo::euro($liquidazione->debitoRinviato)) . ' rinviato</div>' : '' ?></td>
                            <td class="text-end text-success"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->creditoFinale, true)) ?></td>
                            <td class="small text-nowrap"><?= Html::data($dati['scadenza']) ?></td>
                            <td>
                                <form method="post" action="/liquidazione-iva/elabora/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>/periodo/<?= Html::e($periodo) ?>" class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                    <?= $campoCsrf ?>
                                    <?php if ($liquidazione !== null): ?>
                                        <input type="hidden" name="versamentiAuto" value="<?= Html::e(number_format($liquidazione->versamentiAuto, 2, '.', '')) ?>">
                                        <input type="hidden" name="creditiImposta" value="<?= Html::e(number_format($liquidazione->creditiImposta, 2, '.', '')) ?>">
                                    <?php endif; ?>
                                    <input type="text" class="form-control form-control-sm text-end" style="width: 7rem" name="creditoAnnoPrecedente" value="<?= $liquidazione === null || $liquidazione->creditoAnnoPrecedente == 0 ? '' : Html::e(Importo::euro($liquidazione->creditoAnnoPrecedente)) ?>" placeholder="0,00" title="<?= Html::e($campi['creditoAnnoPrecedente']) ?>" inputmode="decimal">
                                    <div class="form-check form-check-inline mb-0 small" title="<?= Html::e($etichettaGiroconto) ?>">
                                        <input class="form-check-input" type="checkbox" name="giroconto" value="1" id="giroconto<?= Html::e($periodo) ?>" <?= ($liquidazione === null) || ($liquidazione->registrazioneContabile !== null) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="giroconto<?= Html::e($periodo) ?>"><i class="bi bi-journal-text"></i></label>
                                    </div>
                                    <?php if ((count($attivitaTrimestrali) > 0) && ($periodo % 3 === 0) && ($periodo < 12)): ?>
                                        <input type="hidden" name="anticipa" value="0">
                                        <div class="form-check form-check-inline mb-0 small" title="<?= Html::e($aiutoAnticipa) ?>">
                                            <input class="form-check-input" type="checkbox" name="anticipa" value="1" id="anticipa<?= Html::e($periodo) ?>" <?= isset($trimestriSeparati[intdiv($periodo, 3)]) || (($liquidazione !== null) && ($liquidazione->trimestreAnticipato === null)) ? '' : 'checked' ?>>
                                            <label class="form-check-label" for="anticipa<?= Html::e($periodo) ?>"><?= Html::e($anticipaTrimestre) ?></label>
                                        </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm <?= $liquidazione === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($liquidazione === null ? $azioni['elabora'] : $azioni['rielabora']) ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php if ((count($attivitaTrimestrali) > 0) && ($periodo % 3 === 0) && ($periodo < 12) && ($liquidazione !== null) && ($liquidazione->trimestreAnticipato === null)): ?>
                            <?php $trimestre = intdiv($periodo, 3); $separata = $trimestriSeparati[$trimestre] ?? null; ?>
                            <tr class="table-light">
                                <td class="ps-4"><?= $separata === null ? Html::e($trimestre . '° ' . $campi['trimestre'] . ' · ' . $titoloTrimestreSeparato) : '<a href="/liquidazione-iva/show/liquidazione/' . Html::e($separata->id) . '" class="fw-semibold text-decoration-none">' . Html::e($separata->getDescrizionePeriodo()) . '</a>' ?></td>
                                <td class="text-end"><?= $separata === null ? '' : Html::e(Importo::euro($separata->ivaEsigibile)) ?></td>
                                <td class="text-end"><?= $separata === null ? '' : Html::e(Importo::euro($separata->ivaDetraibile)) ?></td>
                                <td class="text-end fw-semibold"><?= $separata === null ? '' : Html::e(Importo::euro($separata->importoDovuto, true)) ?></td>
                                <td class="text-end text-success"><?= $separata === null ? '' : Html::e(Importo::euro($separata->creditoFinale, true)) ?></td>
                                <td class="small text-nowrap"><?= $separata?->scadenza === null ? '' : Html::data($separata->scadenza) ?></td>
                                <td>
                                    <form method="post" action="/liquidazione-iva/elabora/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>/periodo/<?= Html::e($trimestre) ?>" class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                        <?= $campoCsrf ?>
                                        <input type="hidden" name="trimestre" value="1">
                                        <input type="hidden" name="giroconto" value="1">
                                        <button type="submit" class="btn btn-sm <?= $separata === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($separata === null ? $azioni['elabora'] : $azioni['rielabora']) ?></button>
                                    </form>
                                    <?php if ($separata !== null): ?>
                                        <form method="post" action="/liquidazione-iva/delete/liquidazione/<?= Html::e($separata->id) ?>" class="text-end mt-1" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= Html::e($azioni['elimina']) ?></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($campi['creditoAnnoPrecedente']) ?> · <i class="bi bi-journal-text"></i> <?= Html::e($etichettaGiroconto) ?> · <?= Html::e($aiutoAltriImporti) ?></div>
    </div>

    <?php if ($requisitiVentilazione !== null): ?>
        <?php $requisiti = $requisitiVentilazione; ?>
        <div class="card shadow-sm mb-3 <?= count($requisiti['superati']) > 0 ? 'border-danger' : '' ?>">
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= Html::e($titoloRequisiti) ?> (<?= Html::e($annoPrecedente) ?> <?= Html::e($requisiti['anno']) ?>)</div>
                <?php if ($requisiti['disponibile'] === false): ?>
                    <div class="text-body-secondary"><?= Html::e($requisitiNonVerificabili) ?></div>
                <?php else: ?>
                    <div><?= Html::e($altriProdotti) ?>: € <?= Html::e(Importo::euro($requisiti['altriProdotti'])) ?> su € <?= Html::e(Importo::euro($requisiti['acquisti'])) ?> = <span class="<?= $requisiti['percentualeAltri'] > 50 ? 'text-danger fw-semibold' : '' ?>"><?= Html::e(number_format($requisiti['percentualeAltri'], 2, ',', '.')) ?>%</span> (max 50%)</div>
                    <div><?= Html::e($cessioniFatturate) ?>: € <?= Html::e(Importo::euro($requisiti['fatture'])) ?> su € <?= Html::e(Importo::euro($requisiti['volumeAffari'])) ?> = <span class="<?= $requisiti['percentualeFatture'] > 20 ? 'text-danger fw-semibold' : '' ?>"><?= Html::e(number_format($requisiti['percentualeFatture'], 2, ',', '.')) ?>%</span> (max 20%)</div>
                    <?php if (count($requisiti['superati']) > 0): ?><div class="text-danger mt-1"><?= Html::e($requisitiSuperati) ?></div><?php endif; ?>
                <?php endif; ?>
                <div class="text-body-secondary mt-1"><?= Html::e($aiutoRequisiti) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($attivitaSeparate as $numero => $attivita): ?>
        <?php $prorataSingola = $prorataAttivita[$numero]; ?>
        <div class="card shadow-sm mb-2">
            <form method="post" action="/liquidazione-iva/prorata/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
                <?= $campoCsrf ?>
                <input type="hidden" name="attivita" value="<?= Html::e($numero) ?>">
                <div class="me-auto">
                    <div class="fw-semibold small"><?= Html::e($prorataTitolo) ?> <?= Html::e($annoIva) ?> · <?= Html::e($attivita->getEtichetta()) ?></div>
                    <div class="small text-body-secondary"><?= Html::e($prorataSingola === null ? $prorataAssente : sprintf($prorataApplicato, $prorataSingola->percentuale) . ($prorataSingola->origine === null ? '' : ' (' . $prorataDaDichiarazione . ')')) ?></div>
                </div>
                <div class="input-group input-group-sm" style="width: 8rem">
                    <input type="text" class="form-control text-end" name="percentuale" value="<?= Html::e($prorataSingola?->percentuale ?? '') ?>" inputmode="numeric" placeholder="100" aria-label="<?= Html::e($prorataTitolo) ?>">
                    <span class="input-group-text">%</span>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['salva']) ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (count($attivitaSeparate) === 0): ?>
    <div class="card shadow-sm mb-3">
        <form method="post" action="/liquidazione-iva/prorata/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="card-body d-flex flex-wrap align-items-center gap-2">
            <?= $campoCsrf ?>
            <div class="me-auto">
                <div class="fw-semibold"><?= Html::e($prorataTitolo) ?> <?= Html::e($annoIva) ?></div>
                <div class="small text-body-secondary"><?= Html::e($prorata === null ? $prorataAssente : sprintf($prorataApplicato, $prorata->percentuale) . ($prorata->origine === null ? '' : ' (' . $prorataDaDichiarazione . ')')) ?> <?= Html::e($aiutoProrata) ?></div>
            </div>
            <div class="input-group input-group-sm" style="width: 8rem">
                <input type="text" class="form-control text-end" name="percentuale" value="<?= Html::e($prorata?->percentuale ?? '') ?>" inputmode="numeric" placeholder="100" aria-label="<?= Html::e($prorataTitolo) ?>">
                <span class="input-group-text">%</span>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['salva']) ?></button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mt-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <div class="fw-semibold"><?= Html::e($accontoTitolo) ?> <?= Html::e($annoIva) ?></div>
                <?php if ($acconto === null): ?>
                    <div class="small text-body-secondary"><?= Html::e($accontoNonCalcolato) ?> <?= Html::e($campi['scadenza']) ?> <?= Html::data($scadenzaAcconto) ?></div>
                <?php else: ?>
                    <div class="small"><?= Html::e($acconto->metodo->getLabel()) ?>: € <?= Html::e(Importo::euro($acconto->importo)) ?><?= $acconto->isDovuto() ? ' · ' . Html::e($campi['scadenza']) . ' ' . Html::data($scadenzaAcconto) : ' · ' . Html::e($accontoNonDovuto) ?></div>
                <?php endif; ?>
            </div>
            <a href="/acconto-iva/form/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($calcolaAcconto) ?></a>
            <?php if ($acconto !== null): ?>
                <form method="post" action="/acconto-iva/delete/acconto/<?= Html::e($acconto->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
