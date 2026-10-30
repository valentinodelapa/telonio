<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\EsitoControllo;

$id = $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$sezioniRavvedimento = [SezioneF24::erario, SezioneF24::regioni, SezioneF24::tributiLocali];
$creditiConResiduo = count(array_filter($crediti, fn(array $voce) => $voce['residuo'] > 0));
$numeroDaVersare = array_sum(array_map('count', $daVersare));
$schede = ['versamenti' => [$schedaVersamenti, $numeroDaVersare], 'deleghe' => [$schedaDeleghe, count($deleghe)], 'crediti' => [$schedaCrediti, $creditiConResiduo]];
require __DIR__ . '/../commonParts/riferimentiVersamento.php';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi bi-person-vcard me-2 text-primary"></i><?= Html::e($soggetto->nominativo) ?></h1>
        <div class="text-body-secondary">
            <span class="font-monospace"><?= Html::e($soggetto->codiceFiscale) ?></span>
            · <a href="/soggetto/show/soggetto/<?= Html::e($id) ?>"><?= Html::e($schedaAnagrafica) ?></a>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoVersamento']) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($sezioni as $sezione): ?>
                    <li><a class="dropdown-item" href="/versamento/create/soggetto/<?= Html::e($id) ?>/sezione/<?= Html::e($sezione->value) ?>"><i class="bi <?= Html::e($sezione->getIcona()) ?> me-2"></i><?= Html::e($sezione->getLabel()) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-piggy-bank me-1"></i><?= Html::e($azioni['nuovoCredito']) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($sezioniCredito as $sezione): ?>
                    <li><a class="dropdown-item" href="/credito/create/soggetto/<?= Html::e($id) ?>/sezione/<?= Html::e($sezione->value) ?>"><?= Html::e($sezione->getLabel()) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <a href="/delega/create/soggetto/<?= Html::e($id) ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-plus me-1"></i><?= Html::e($azioni["delegaVuota"]) ?></a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($schede as $chiave => [$etichetta, $numero]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $vista === $chiave ? 'active' : '' ?>" href="/contribuente/show/soggetto/<?= Html::e($id) ?><?= $chiave === 'versamenti' ? '' : '/vista/' . $chiave ?>">
                <?= Html::e($etichetta) ?><?php if ($numero > 0): ?> <span class="badge rounded-pill text-bg-light border"><?= Html::e($numero) ?></span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($vista === 'versamenti'): ?>
    <p class="small text-body-secondary"><?= Html::e($aiutoVersamenti) ?></p>
    <?php if ($creditiConResiduo > 0): ?>
        <div class="card shadow-sm mb-3 border-success">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold me-auto"><i class="bi bi-piggy-bank me-2 text-success"></i><?= Html::e($sezioneCreditiDisponibili) ?></span>
                <span class="small text-body-secondary"><?= Html::e($aiutoCreditiDisponibili) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <tbody>
                        <?php foreach ($crediti as ['credito' => $credito, 'residuo' => $residuo]): ?>
                            <?php if ($residuo <= 0) continue; ?>
                            <tr>
                                <td class="text-nowrap"><span class="badge text-bg-light border"><?= Html::e($credito->sezione->getLabel()) ?></span> <span class="font-monospace fw-semibold"><?= Html::e($credito->getCodice()) ?></span> <?= Html::e($credito->annoRiferimento ?? '') ?></td>
                                <td class="small"><?= Html::e($credito->descrizione ?? $credito->codiceTributo?->descrizione ?? '') ?></td>
                                <td class="small text-nowrap"><?= $credito->utilizzabileDal !== null ? Html::e($campi['utilizzabileDal']) . ' ' . Html::data($credito->utilizzabileDal) : '' ?></td>
                                <td class="text-end text-nowrap"><?= Html::e($colonnaResiduo) ?> <strong class="text-success">€ <?= EsitoControllo::euro($residuo) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php if (count($piani) > 0): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i><?= Html::e($sezionePiani) ?></div>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <tbody>
                        <?php foreach ($piani as $piano): ?>
                            <?php $datePiano = $piano->getDate(); ?>
                            <tr>
                                <td class="text-nowrap"><a href="/rateazione/update/piano/<?= Html::e($piano->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($piano->descrizione ?? $pianoDel . ' ' . Html::data($piano->scadenza)) ?></a></td>
                                <td class="small"><?= Html::e($piano->numeroRate) ?> <?= Html::e($etichettaRate) ?>: <?= Html::data($datePiano[0]) ?> - <?= Html::data(end($datePiano)) ?></td>
                                <td class="text-end"><a href="/rateazione/update/piano/<?= Html::e($piano->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php if (count($daVersare) === 0): ?>
        <div class="card shadow-sm"><div class="card-body text-center text-body-secondary py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2"></i><?= Html::e($nessunVersamento) ?></div></div>
    <?php endif; ?>
    <?php foreach ($daVersare as $data => $versamenti): ?>
        <?php
        $scaduto = $data < $oggi;
        $totale = array_sum(array_map(fn($versamento) => EsitoControllo::centesimi($versamento->importoDebito) - EsitoControllo::centesimi($versamento->importoCredito), $versamenti));
        ?>
        <div class="card shadow-sm mb-3 <?= $scaduto ? 'border-danger' : '' ?>">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold me-auto">
                    <i class="bi bi-calendar-event me-1 text-primary"></i><?= Html::data(new DateTime($data)) ?>
                    <?php if ($scaduto): ?><span class="badge text-bg-danger ms-1"><?= Html::e($etichettaScaduto) ?></span><?php endif; ?>
                    <span class="text-body-secondary fw-normal ms-2">€ <?= EsitoControllo::euro($totale) ?></span>
                </span>
                <?php if (isset($dateRateizzabili[$data])): ?>
                    <a href="/rateazione/create/soggetto/<?= Html::e($id) ?>/scadenza/<?= Html::e($data) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3 me-1"></i><?= Html::e($azioni['rateizza']) ?></a>
                <?php endif; ?>
                <?php if ($scaduto === false): ?>
                    <form method="post" action="/delega/genera/soggetto/<?= Html::e($id) ?>" id="genera-<?= Html::e($data) ?>" class="d-flex align-items-center gap-2">
                        <?= $campoCsrf ?>
                        <input type="hidden" name="scadenza" value="<?= Html::e($data) ?>">
                        <input type="hidden" name="scelta" value="1">
                        <?php if ($creditiConResiduo > 0): ?>
                            <div class="form-check small mb-0">
                                <input class="form-check-input" type="checkbox" name="compensa" value="1" id="compensa-<?= Html::e($data) ?>" checked>
                                <label class="form-check-label" for="compensa-<?= Html::e($data) ?>"><?= Html::e($etichettaCompensa) ?></label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-arrow-down me-1"></i><?= Html::e($azioni['generaDelega']) ?></button>
                    </form>
                <?php else: ?>
                    <span class="small text-danger"><?= Html::e($aiutoScaduti) ?></span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <tbody>
                        <?php foreach ($versamenti as $versamento): ?>
                            <tr>
                                <td style="width: 2rem">
                                    <?php if ($scaduto === false): ?>
                                        <input class="form-check-input" type="checkbox" name="versamenti[]" value="<?= Html::e($versamento->id) ?>" form="genera-<?= Html::e($data) ?>" checked title="<?= Html::e($aiutoSceltaDelega) ?>" aria-label="<?= Html::e($aiutoSceltaDelega) ?> <?= Html::e($versamento->getCodice()) ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><span class="badge text-bg-light border"><?= Html::e($versamento->sezione->getLabel()) ?></span></td>
                                <td class="font-monospace fw-semibold"><?= Html::e($versamento->getCodice()) ?></td>
                                <td class="small">
                                    <?= Html::e($versamento->descrizione ?? $versamento->getDescrizione()) ?>
                                    <div class="text-body-secondary"><?= Html::e($riferimenti($versamento)) ?></div>
                                    <?php if ($versamento->isGenerata()): ?><span class="badge text-bg-light border"><i class="bi bi-gear me-1"></i><?= Html::e($versamento->origine) ?></span><?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= Html::e(Importo::euro($versamento->importoDebito, true)) ?>
                                    <?php if (($ravveduti[$versamento->id] ?? 0) > 0): ?>
                                        <div class="small text-body-secondary"><?= Html::e($etichettaRavveduto) ?> € <?= EsitoControllo::euro($ravveduti[$versamento->id]) ?></div>
                                        <div class="small fw-semibold text-danger"><?= Html::e($colonnaResiduo) ?> € <?= EsitoControllo::euro(EsitoControllo::centesimi($versamento->importoDebito) - $ravveduti[$versamento->id]) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro($versamento->importoCredito, true)) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php $regolarizzabile = $scaduto && ($versamento->importoDebito > 0) && (CalcoloRateazione::isInteresse($versamento) === false); ?>
                                    <?php if ($regolarizzabile && in_array($versamento->sezione, $sezioniRavvedimento, true)): ?>
                                        <form method="post" action="/ravvedimento/da-versamento/versamento/<?= Html::e($versamento->id) ?>" class="d-inline-flex align-items-center gap-1">
                                            <?= $campoCsrf ?>
                                            <input type="date" class="form-control form-control-sm" name="scadenza" value="<?= Html::data($prossimaScadenza, 'Y-m-d') ?>" required aria-label="<?= Html::e($campi['scadenza']) ?>" title="<?= Html::e($campi['scadenza']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-clock-history me-1"></i><?= Html::e($azioni['ravvedi']) ?></button>
                                        </form>
                                    <?php elseif ($regolarizzabile && in_array($versamento->sezione, [SezioneF24::inps, SezioneF24::inail], true)): ?>
                                        <form method="post" action="/sanzione-civile/da-versamento/versamento/<?= Html::e($versamento->id) ?>" class="d-inline-flex align-items-center gap-1">
                                            <?= $campoCsrf ?>
                                            <input type="date" class="form-control form-control-sm" name="scadenza" value="<?= Html::data($prossimaScadenza, 'Y-m-d') ?>" required aria-label="<?= Html::e($campi['scadenza']) ?>" title="<?= Html::e($campi['scadenza']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap" title="<?= Html::e($aiutoVersaInRitardo) ?>"><i class="bi bi-clock-history me-1"></i><?= Html::e($azioni['versaInRitardo']) ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php $riga = $versamento; require __DIR__ . '/../commonParts/pulsantiCopia.php'; ?>
                                    <?php if ($versamento->isGenerata() === false): ?>
                                        <a href="/versamento/update/versamento/<?= Html::e($versamento->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <?php endif; ?>
                                    <form method="post" action="/versamento/delete/versamento/<?= Html::e($versamento->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($vista === 'deleghe'): ?>
    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form method="get" action="/contribuente/show" class="row g-2 align-items-center">
                <input type="hidden" name="soggetto" value="<?= Html::e($id) ?>">
                <input type="hidden" name="vista" value="deleghe">
                <div class="col-6 col-lg-3">
                    <select class="form-select" name="stato" aria-label="<?= Html::e($campi['stato']) ?>">
                        <option value=""><?= Html::e($tutti) ?></option>
                        <?php foreach ($stati as $stato): ?>
                            <option value="<?= Html::e($stato->value) ?>" <?= $statoFiltro === $stato ? 'selected' : '' ?>><?= Html::e($stato->getLabel()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <input type="number" class="form-control" name="anno" value="<?= $annoFiltro > 0 ? Html::e($annoFiltro) : '' ?>" min="2000" max="2100" placeholder="<?= Html::e($anno) ?>" aria-label="<?= Html::e($anno) ?>">
                </div>
                <div class="col-6 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
                </div>
            </form>
        </div>
        <?php if (count($deleghe) === 0): ?>
            <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><?= Html::e($nessunaDelega) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-anagrafica mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= Html::e($campi['scadenza']) ?></th>
                            <th><?= Html::e($campi['modello']) ?></th>
                            <th><?= Html::e($campi['descrizione']) ?></th>
                            <th><?= Html::e($campi['stato']) ?></th>
                            <th class="text-end"><?= Html::e($campi['debito']) ?></th>
                            <th class="text-end"><?= Html::e($campi['credito']) ?></th>
                            <th class="text-end"><?= Html::e($campi['saldoFinale']) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deleghe as ['delega' => $delega, 'totali' => $totali]): ?>
                            <?php $scaduta = $delega->stato->isModificabile() && ($delega->scadenza->format('Y-m-d') < $oggi); ?>
                            <tr>
                                <td class="text-nowrap">
                                    <a href="/delega/show/delega/<?= Html::e($delega->id) ?>" class="fw-semibold text-decoration-none"><?= Html::data($delega->scadenza) ?></a>
                                    <?php if ($scaduta): ?><span class="badge text-bg-danger ms-1"><?= Html::e($etichettaScaduto) ?></span><?php endif; ?>
                                </td>
                                <td class="small"><?= Html::e($delega->modello->getLabel()) ?></td>
                                <td class="small"><?= Html::e($delega->descrizione ?? '') ?></td>
                                <td><span class="badge <?= Html::e($delega->stato->getClasseBadge()) ?>"><?= Html::e($delega->stato->getLabel()) ?></span></td>
                                <td class="text-end text-nowrap"><?= EsitoControllo::euro($totali['debito']) ?></td>
                                <td class="text-end text-nowrap"><?= $totali['credito'] > 0 ? EsitoControllo::euro($totali['credito']) : '' ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= EsitoControllo::euro($totali['debito'] - $totali['credito']) ?></td>
                                <td class="text-end"><a href="/delega/show/delega/<?= Html::e($delega->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <p class="small text-body-secondary"><?= Html::e($aiutoCrediti) ?></p>
    <div class="card shadow-sm">
        <?php if (count($crediti) === 0): ?>
            <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-piggy-bank fs-1 d-block mb-2"></i><?= Html::e($nessunCredito) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= Html::e($campi['codice']) ?></th>
                            <th><?= Html::e($campi['descrizione']) ?></th>
                            <th class="text-end"><?= Html::e($campi['importo']) ?></th>
                            <th class="text-end"><?= Html::e($colonnaUtilizzato) ?></th>
                            <th class="text-end"><?= Html::e($colonnaResiduo) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($crediti as ['credito' => $credito, 'residuo' => $residuo, 'utilizzi' => $utilizzi]): ?>
                            <tr>
                                <td class="text-nowrap"><span class="badge text-bg-light border"><?= Html::e($credito->sezione->getLabel()) ?></span> <span class="font-monospace fw-semibold"><?= Html::e($credito->getCodice()) ?></span></td>
                                <td class="small">
                                    <?= Html::e($credito->descrizione ?? $credito->codiceTributo?->descrizione ?? '') ?>
                                    <div class="text-body-secondary"><?= Html::e($riferimenti($credito)) ?><?= $credito->utilizzabileDal !== null ? ' · ' . Html::e($campi['utilizzabileDal']) . ' ' . Html::data($credito->utilizzabileDal) : '' ?></div>
                                    <?php foreach ($utilizzi as $utilizzo): ?>
                                        <div class="text-body-secondary"><i class="bi bi-arrow-return-right me-1"></i><a href="/delega/show/delega/<?= Html::e($utilizzo->delega?->id) ?>">F24 <?= Html::data($utilizzo->scadenza) ?></a> € <?= Html::e(Importo::euro($utilizzo->importoCredito)) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro($credito->importo)) ?></td>
                                <td class="text-end text-nowrap"><?= EsitoControllo::euro((int) round($credito->importo * 100) - $residuo) ?></td>
                                <td class="text-end text-nowrap fw-semibold <?= $residuo > 0 ? 'text-success' : 'text-body-secondary' ?>"><?= EsitoControllo::euro($residuo) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="/credito/update/credito/<?= Html::e($credito->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <?php if (count($utilizzi) === 0): ?>
                                        <form method="post" action="/credito/delete/credito/<?= Html::e($credito->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
