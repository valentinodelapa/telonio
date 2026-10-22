<?php

use Anagrafica\Application\Enumerations\TipoCatasto;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$id = $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$urlAnno = '/contribuente-imu/show/soggetto/' . $id . '/anno/';
$ritorno = '/soggetto/' . $id . '/anno/' . $anno;
$percentuale = fn(?float $aliquota): string => $aliquota === null ? '' : number_format($aliquota, 2, ',', '') . '%';
$periodo = fn($versione): string => Html::data($versione->validoDal) . ' – ' . ($versione->validoAl === null ? $sempre : Html::data($versione->validoAl));
$totali = ['impostaAnnua' => 0.0, 'impostaAcconto' => 0.0, 'detrazioneAnnua' => 0.0, 'detrazioneAcconto' => 0.0];
foreach ($righi as $rigo) {
    foreach ($totali as $chiave => $valore) {
        $totali[$chiave] = $valore + $rigo->$chiave;
    }
}

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi bi-house-gear me-2 text-primary"></i><?= Html::e($soggetto->nominativo) ?> <span class="text-body-secondary fw-normal">· IMU <?= Html::e($anno) ?></span>
            <?php if ($enteNonCommerciale): ?><span class="badge text-bg-info fs-6 align-middle"><?= Html::e($badgeEnc) ?></span><?php endif; ?></h1>
        <div class="text-body-secondary">
            <span class="font-monospace"><?= Html::e($soggetto->codiceFiscale) ?></span>
            · <a href="/soggetto/show/soggetto/<?= Html::e($id) ?>/scheda/immobili"><?= Html::e($gestisciAnagrafica) ?></a>
            · <a href="/contribuente/show/soggetto/<?= Html::e($id) ?>"><?= Html::e($azioni['deleghe']) ?></a>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php require __DIR__ . '/../commonParts/selettoreAnno.php'; ?>
        <?php if ($calcolo !== null): ?>
            <a href="/calcolo-imu/stampa/calcolo/<?= Html::e($calcolo->id) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-buildings me-2 text-primary"></i><?= Html::e($sezioneImmobili) ?></div>
    <div class="card-body pb-0"><p class="small text-body-secondary"><?= Html::e($aiutoImmobili) ?></p></div>
    <?php if (count($immobili) === 0): ?>
        <div class="card-body text-center text-body-secondary py-4"><?= Html::e($nessunImmobile) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['immobile']) ?></th>
                        <th><?= Html::e($campi['classamento']) ?></th>
                        <th><?= Html::e($campi['possesso']) ?></th>
                        <th>IMU</th>
                        <th><?= Html::e($prospettoComune) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($immobili as $voce): ?>
                        <?php
                        $immobile = $voce['immobile'];
                        $datiCatastali = $voce['datiCatastali'];
                        $aliquotaApplicata = $voce['aliquotaApplicata'];
                        ?>
                        <tr>
                            <td>
                                <i class="bi <?= Html::e($immobile->tipoCatasto->getIcona()) ?> me-1"></i><a href="/immobile/show/immobile/<?= Html::e($immobile->id) ?>"><?= Html::e($immobile->getIdentificativo()) ?></a>
                                <div class="small text-body-secondary"><?= Html::e($immobile->getUbicazione()) ?></div>
                            </td>
                            <td class="small text-nowrap">
                                <?php if ($datiCatastali === null): ?>
                                    <span class="text-danger">—</span>
                                <?php elseif ($immobile->tipoCatasto === TipoCatasto::fabbricati): ?>
                                    <?= Html::e($datiCatastali->categoria?->value ?? '') ?> · R.C. € <?= Html::e(Importo::euro((float) $datiCatastali->rendita)) ?>
                                <?php else: ?>
                                    R.D. € <?= Html::e(Importo::euro((float) $datiCatastali->redditoDominicale)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php foreach ($voce['possessi'] as $possesso): ?>
                                    <div><?= Html::e($possesso->titolo->getLabel()) ?> <?= Html::e(rtrim(rtrim(number_format($possesso->quota, 4, ',', ''), '0'), ',')) ?>% · <?= Html::e($possesso->utilizzo?->getLabel() ?? '') ?> <span class="text-body-secondary">(<?= $periodo($possesso) ?>)</span></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="small">
                                <?php foreach ($voce['caratteristiche'] as $caratteristiche): ?>
                                    <div>
                                        <?php foreach ($caratteristiche->getEtichette() as $etichetta): ?><span class="badge text-bg-light border me-1"><?= Html::e($etichetta) ?></span><?php endforeach; ?>
                                        <?php if ($caratteristiche->areaFabbricabile): ?>€ <?= Html::e(Importo::euro((float) $caratteristiche->valoreVenale)) ?><?php endif; ?>
                                        <a href="/caratteristiche-imu/update/caratteristiche/<?= Html::e($caratteristiche->id) ?>/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>" class="text-body-secondary" title="<?= Html::e($azioni['modifica']) ?>"><?= $periodo($caratteristiche) ?> <i class="bi bi-pencil"></i></a>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($voce['agevolazioni'] as $agevolazione): ?>
                                    <div>
                                        <?php foreach ($agevolazione->getEtichette() as $etichetta): ?><span class="badge text-bg-info-subtle border me-1"><?= Html::e($etichetta) ?></span><?php endforeach; ?>
                                        <a href="/agevolazione-imu/update/agevolazione/<?= Html::e($agevolazione->id) ?>/anno/<?= Html::e($anno) ?>" class="text-body-secondary" title="<?= Html::e($azioni['modifica']) ?>"><?= $periodo($agevolazione) ?> <i class="bi bi-pencil"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="small">
                                <?php if ($voce['prospetto']['prospetto'] !== null): ?>
                                    <a href="/prospetto-aliquote/show/prospetto/<?= Html::e($voce['prospetto']['prospetto']->id) ?>"><?= Html::e($voce['prospetto']['origine']) ?></a>
                                <?php else: ?>
                                    <span class="text-warning-emphasis"><?= Html::e($voce['prospetto']['origine']) ?></span>
                                <?php endif; ?>
                                <?php if ($aliquotaApplicata !== null): ?>
                                    <div><span class="badge text-bg-primary"><?= Html::e($percentuale($aliquotaApplicata->getValore())) ?></span> <a href="/aliquota-applicata/update/aliquota-applicata/<?= Html::e($aliquotaApplicata->id) ?>"><?= Html::e($aliquotaScelta) ?></a></div>
                                <?php endif; ?>
                                <?php if (isset($utilizziEnc[$immobile->id])): ?>
                                    <?php $utilizzoEnc = $utilizziEnc[$immobile->id]; ?>
                                    <div><a href="/utilizzo-misto-enc/update/utilizzo/<?= Html::e($utilizzoEnc->id) ?>" class="badge text-bg-info text-decoration-none"><?= Html::e($utilizzoEnc->esente ? $encEsente : $encMisto . ' ' . number_format($utilizzoEnc->getCoefficienteImponibile() * 100, 2, ',', '') . '%') ?></a></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-sliders"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="/caratteristiche-imu/create/immobile/<?= Html::e($immobile->id) ?>/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>"><i class="bi bi-building-gear me-2"></i><?= Html::e($azioni['caratteristiche']) ?></a></li>
                                        <li><a class="dropdown-item" href="/agevolazione-imu/create/soggetto/<?= Html::e($id) ?>/immobile/<?= Html::e($immobile->id) ?>/anno/<?= Html::e($anno) ?>"><i class="bi bi-person-check me-2"></i><?= Html::e($azioni['agevolazioni']) ?></a></li>
                                        <li><a class="dropdown-item" href="/aliquota-applicata/create/soggetto/<?= Html::e($id) ?>/immobile/<?= Html::e($immobile->id) ?>/anno/<?= Html::e($anno) ?>"><i class="bi bi-percent me-2"></i><?= Html::e($azioni['aliquota']) ?> <?= Html::e($anno) ?></a></li>
                                        <?php if (count(array_filter($voce['caratteristiche'], fn($caratteristiche) => $caratteristiche->valoreContabile)) > 0): ?>
                                            <li><a class="dropdown-item" href="/costo-contabile-imu/index/immobile/<?= Html::e($immobile->id) ?>/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>"><i class="bi bi-journal-text me-2"></i><?= Html::e($azioni['costiContabili']) ?></a></li>
                                        <?php endif; ?>
                                        <?php if ($enteNonCommerciale): ?>
                                            <li><a class="dropdown-item" href="/utilizzo-misto-enc/create/soggetto/<?= Html::e($id) ?>/immobile/<?= Html::e($immobile->id) ?>/anno/<?= Html::e($anno) ?>"><i class="bi bi-building me-2"></i><?= Html::e($azioni['utilizzoEnc']) ?> <?= Html::e($anno) ?></a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold me-auto"><i class="bi bi-calculator me-2 text-primary"></i><?= Html::e($sezioneCalcolo) ?>
            <?php if ($calcolo !== null): ?><span class="small text-body-secondary fw-normal ms-2"><?= Html::e($elaboratoIl) ?> <?= Html::data($calcolo->dataElaborazione, 'd/m/Y H:i') ?></span><?php endif; ?>
        </span>
        <form method="post" action="/calcolo-imu/elabora/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>" class="d-flex align-items-center gap-2">
            <?= $campoCsrf ?>
            <div class="form-check small mb-0">
                <input class="form-check-input" type="checkbox" name="unicaSoluzione" value="1" id="unicaSoluzione" <?= ($calcolo?->unicaSoluzione ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="unicaSoluzione"><?= Html::e($campi['unicaSoluzione']) ?></label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i><?= Html::e($calcolo === null ? $azioni['elabora'] : $azioni['ricalcola']) ?></button>
        </form>
        <?php if ($calcolo !== null): ?>
            <form method="post" action="/calcolo-imu/delete/calcolo/<?= Html::e($calcolo->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
        <?php endif; ?>
    </div>
    <div class="card-body pb-0"><p class="small text-body-secondary"><?= Html::e($aiutoCalcolo) ?></p></div>
    <?php if ($calcolo === null): ?>
        <div class="card-body text-center text-body-secondary py-4"><i class="bi bi-calculator fs-1 d-block mb-2"></i><?= Html::e($nessunCalcolo) ?></div>
    <?php else: ?>
        <?php if (count($calcolo->getAvvisi()) > 0): ?>
            <div class="alert alert-warning mx-3 small">
                <div class="fw-semibold mb-1"><?= Html::e($sezioneAvvisi) ?></div>
                <ul class="mb-0"><?php foreach ($calcolo->getAvvisi() as $avviso): ?><li><?= Html::e($avviso) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (count($calcolo->getDifferenze()) > 0): ?>
            <div class="alert alert-danger mx-3 small">
                <div class="fw-semibold mb-1"><?= Html::e($sezioneDifferenze) ?></div>
                <ul class="mb-0"><?php foreach ($calcolo->getDifferenze() as $differenza): ?><li><?= Html::e($differenza) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['immobile']) ?></th>
                        <th><?= Html::e($campi['periodo']) ?></th>
                        <th><?= Html::e($campi['fattispecie']) ?></th>
                        <th class="text-end"><?= Html::e($campi['baseImponibile']) ?></th>
                        <th class="text-end"><?= Html::e($campi['aliquota']) ?></th>
                        <th class="text-end"><?= Html::e($campi['impostaAnnua']) ?></th>
                        <th class="text-end"><?= Html::e($campi['acconto']) ?></th>
                        <th class="text-end"><?= Html::e($campi['detrazione']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($righi as $rigo): ?>
                        <tr class="<?= $rigo->isImponibile() ? '' : 'text-body-secondary' ?>">
                            <td class="small">
                                <?= Html::e($rigo->immobile->getIdentificativo()) ?> <span class="text-body-secondary">(<?= Html::e($rigo->codiceComune) ?>)</span>
                                <div class="text-body-secondary"><?= Html::e($rigo->categoria?->value ?? '') ?> · <?= Html::e($rigo->titolo->getLabel()) ?> <?= Html::e(rtrim(rtrim(number_format($rigo->quota, 4, ',', ''), '0'), ',')) ?>%<?= $rigo->utilizzo !== null ? ' · ' . Html::e($rigo->utilizzo->getLabel()) : '' ?></div>
                            </td>
                            <td class="small text-nowrap"><?= Html::data($rigo->dal) ?> – <?= Html::data($rigo->al) ?><div class="text-body-secondary"><?= Html::e($campi['mesi']) ?> <?= Html::e($rigo->mesi) ?> (<?= Html::e($rigo->mesiAcconto) ?> I sem.)</div></td>
                            <td class="small">
                                <?php if ($rigo->isImponibile()): ?>
                                    <span class="font-monospace fw-semibold"><?= Html::e($rigo->fattispecie->getCodiceTributo()) ?><?= $rigo->fattispecie->getCodiceTributoStato() !== null ? '/' . Html::e($rigo->fattispecie->getCodiceTributoStato()) : '' ?></span> <?= Html::e($rigo->fattispecie->getLabel()) ?>
                                    <?php if ($rigo->note !== null): ?><div class="text-body-secondary"><?= Html::e($rigo->note) ?></div><?php endif; ?>
                                    <div class="text-body-secondary fst-italic"><?= Html::e($rigo->origineAliquota ?? '') ?></div>
                                <?php else: ?>
                                    <span class="badge text-bg-light border"><?= Html::e($esente) ?></span> <?= Html::e($rigo->esenzione ?? $nonImponibile) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap small"><?= $rigo->isImponibile() ? Html::e(Importo::euro($rigo->getBaseImponibileQuota())) : '' ?><?php if ($rigo->isImponibile() && ($rigo->riduzioneImposta < 1)): ?><div class="text-body-secondary">× <?= Html::e(number_format($rigo->riduzioneImposta * 100, 1, ',', '')) ?>%</div><?php endif; ?></td>
                            <td class="text-end text-nowrap small"><?= Html::e($percentuale($rigo->aliquota)) ?><?php if ($rigo->isImponibile()): ?><div class="text-body-secondary"><?= Html::e($percentuale($rigo->aliquotaAcconto)) ?></div><?php endif; ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($rigo->impostaAnnua, true)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($rigo->impostaAcconto, true)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($rigo->detrazioneAnnua, true)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="5"><?= Html::e($totale) ?></td>
                        <td class="text-end text-nowrap"><?= Html::e(Importo::euro($totali['impostaAnnua'])) ?></td>
                        <td class="text-end text-nowrap"><?= Html::e(Importo::euro($totali['impostaAcconto'])) ?></td>
                        <td class="text-end text-nowrap"><?= Html::e(Importo::euro($totali['detrazioneAnnua'], true)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><i class="bi bi-cash-coin me-2 text-primary"></i><?= Html::e($sezioneAcconti) ?></span>
        <a href="/acconto-versato-imu/create/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoAcconto']) ?></a>
    </div>
    <?php if (count($accontiVersati) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($aiutoAcconti) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($accontiVersati as $acconto): ?>
                <li class="list-group-item d-flex small gap-2">
                    <span class="font-monospace fw-semibold"><?= Html::e($acconto->codiceTributo) ?></span> <?= Html::e($acconto->codiceComune) ?>
                    <span class="me-auto text-body-secondary"><?= Html::data($acconto->dataVersamento) ?> <?= Html::e($acconto->note ?? '') ?></span>
                    <strong>€ <?= Html::e(Importo::euro($acconto->importo)) ?></strong>
                    <a href="/acconto-versato-imu/update/acconto/<?= Html::e($acconto->id) ?>" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= Html::e($sezioneDichiarazioni) ?> <?= Html::e($anno) ?></span>
        <form method="post" action="/dichiarazione-imu/proponi/soggetto/<?= Html::e($id) ?>/anno/<?= Html::e($anno) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-magic me-1"></i><?= Html::e($azioni['proponiDichiarazioni']) ?></button></form>
    </div>
    <div class="card-body pb-0"><p class="small text-body-secondary"><?= Html::e($enteNonCommerciale ? $aiutoDichiarazioniEnc : $aiutoDichiarazioni) ?></p></div>
    <?php if (count($dichiarazioni) > 0): ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($dichiarazioni as $dichiarazione): ?>
                <li class="list-group-item d-flex align-items-center gap-2 small">
                    <a href="/dichiarazione-imu/show/dichiarazione/<?= Html::e($dichiarazione->id) ?>" class="fw-semibold me-auto"><?= Html::e($dichiarazione->denominazioneComune) ?> (<?= Html::e($dichiarazione->codiceComune) ?>) · <?= Html::e($dichiarazione->tipologia->getLabel()) ?></a>
                    <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
                    <span class="text-body-secondary"><?= Html::e($scadenzaDichiarazione) ?> <?= Html::data($dichiarazione->getScadenza()) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><i class="bi bi-credit-card-2-front me-2 text-primary"></i><?= Html::e($sezioneVersamenti) ?></span>
        <a href="/contribuente/show/soggetto/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['deleghe']) ?> <i class="bi bi-chevron-right"></i></a>
    </div>
    <?php if (count($versamenti) === 0): ?>
        <div class="card-body text-center text-body-secondary py-4"><?= Html::e($nessunVersamento) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['scadenza']) ?></th>
                        <th><?= Html::e($campi['codiceTributo']) ?></th>
                        <th><?= Html::e($campi['comune']) ?></th>
                        <th></th>
                        <th class="text-end"><?= Html::e($campi['detrazione']) ?></th>
                        <th class="text-end"><?= Html::e($campi['importo']) ?></th>
                        <th><?= Html::e($campi['stato']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versamenti as $versamento): ?>
                        <tr>
                            <td class="text-nowrap"><?= Html::data($versamento->scadenza) ?></td>
                            <td class="font-monospace fw-semibold"><?= Html::e($versamento->getCodice()) ?></td>
                            <td><?= Html::e($versamento->codiceEnte) ?></td>
                            <td class="small"><?= Html::e(trim(($versamento->acconto ? $campi['acconto'] . ' ' : '') . ($versamento->saldo ? $campi['saldo'] : ''))) ?> · <?= Html::e($versamento->numeroImmobili) ?> imm.</td>
                            <td class="text-end text-nowrap"><?= $versamento->detrazioneImu === null ? '' : Html::e(Importo::euro($versamento->detrazioneImu)) ?></td>
                            <td class="text-end text-nowrap fw-semibold"><?= Html::e(Importo::euro($versamento->importoDebito)) ?></td>
                            <td>
                                <?php if ($versamento->delega === null): ?>
                                    <span class="badge text-bg-light border"><?= Html::e($daVersare) ?></span>
                                <?php else: ?>
                                    <a href="/delega/show/delega/<?= Html::e($versamento->delega->id) ?>" class="badge <?= Html::e($versamento->delega->stato->getClasseBadge()) ?> text-decoration-none">F24 <?= Html::e($versamento->delega->stato->getLabel()) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
