<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\EsitoControllo;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$nuovo = $piano === null;
$bloccato = ($nuovo === false) && ($motivoBlocco !== null);
$totale = ['quote' => 0, 'interessi' => 0];
foreach ($anteprima as $voce) {
    $totale['quote'] += $voce['quote'];
    $totale['interessi'] += $voce['interessi'];
}
require __DIR__ . '/../commonParts/riferimentiVersamento.php';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-calendar3 me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($scadenzaDeiVersamenti) ?> <?= Html::data($scadenzaPiano) ?></p>
<?php if ($norma === null): ?>
    <p class="small text-body-secondary"><?= Html::e($aiutoRateazione) ?></p>
<?php else: ?>
    <div class="alert alert-info small">
        <?= Html::e(sprintf($aiutoSecondoAcconto, $norma->periodoImposta, Html::data($norma->primaRata), $norma->numeroRate)) ?>
        <div class="mt-1"><?= Html::e($etichettaNorma) ?>: <?= Html::e($norma->norma) ?>.</div>
    </div>
<?php endif; ?>

<?php if (isset($erroreRateazione) && (count($erroriDate) === 0)): ?>
    <div class="alert alert-danger"><?= Html::e($erroreRateazione) ?></div>
<?php endif; ?>
<?php if ($bloccato): ?>
    <div class="alert alert-warning"><?= Html::e($pianoBloccato) ?>: <?= Html::e($motivoBlocco) ?>.</div>
<?php endif; ?>

<form method="post" action="<?= Html::e($urlForm) ?>">
    <?= $campoCsrf ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVersamenti) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($rateizzabili as $versamento): ?>
                        <tr>
                            <td style="width: 2rem">
                                <?php if ($nuovo): ?>
                                    <input class="form-check-input" type="checkbox" name="versamenti[]" value="<?= Html::e($versamento->id) ?>" <?= in_array($versamento->id, $idScelti, true) ? 'checked' : '' ?> aria-label="<?= Html::e($versamento->getCodice()) ?>">
                                <?php else: ?>
                                    <i class="bi bi-check2 text-success"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><span class="badge text-bg-light border"><?= Html::e($versamento->sezione->getLabel()) ?></span> <span class="font-monospace fw-semibold"><?= Html::e($versamento->getCodice()) ?></span></td>
                            <td class="small"><?= Html::e($versamento->descrizione ?? $versamento->getDescrizione()) ?><div class="text-body-secondary"><?= Html::e($riferimenti($versamento)) ?></div></td>
                            <td class="text-end text-nowrap">€ <?= Html::e(Importo::euro($versamento->importoDebito)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRate) ?></div>
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="numeroRate"><?= Html::e($etichettaNumeroRate) ?></label>
                <select class="form-select" id="numeroRate" name="numeroRate" <?= $bloccato ? 'disabled' : '' ?>>
                    <?php for ($numero = $minimoRate; $numero <= max($minimoRate, $massimoRate); $numero++): ?>
                        <option value="<?= $numero ?>" <?= $numero === count($date) ? 'selected' : '' ?>><?= $numero === 1 ? Html::e($unicaSoluzione) : $numero ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="descrizione"><?= Html::e($campi['descrizione']) ?></label>
                <input type="text" class="form-control" id="descrizione" name="descrizione" maxlength="100" value="<?= Html::e($descrizione ?? '') ?>" placeholder="<?= Html::e($segnapostoDescrizione) ?>" <?= $bloccato ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" name="azione" value="anteprima" class="btn btn-outline-primary" <?= $bloccato ? 'disabled' : '' ?>><?= Html::e($azioni['anteprima']) ?></button>
            </div>
        </div>
        <?php foreach ($erroriDate as $errore): ?>
            <div class="alert alert-danger mx-3 py-2 small"><?= Html::e($errore) ?></div>
        <?php endforeach; ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($colonnaRata) ?></th>
                        <th><?= Html::e($colonnaScadenza) ?></th>
                        <th class="text-end"><?= Html::e($colonnaQuota) ?></th>
                        <th class="text-end"><?= Html::e($colonnaPercentuale) ?></th>
                        <th class="text-end"><?= Html::e($colonnaInteressi) ?></th>
                        <th class="text-end"><?= Html::e($colonnaTotale) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($date as $indice => $data): ?>
                        <?php $voce = $anteprima[$indice] ?? null; ?>
                        <tr>
                            <td><?= $indice + 1 ?>/<?= count($date) ?></td>
                            <td>
                                <?php if ($indice === 0): ?>
                                    <input type="hidden" name="date[]" value="<?= Html::data($data, 'Y-m-d') ?>"><?= Html::data($data) ?>
                                <?php else: ?>
                                    <input type="date" class="form-control form-control-sm d-inline-block w-auto" name="date[]" value="<?= Html::data($data, 'Y-m-d') ?>" <?= $bloccato ? 'disabled' : '' ?> aria-label="<?= Html::e($colonnaScadenza) ?> <?= $indice + 1 ?>">
                                <?php endif; ?>
                                <?php if (($voce !== null) && ($voce['versamento']->format('Y-m-d') !== $data->format('Y-m-d'))): ?>
                                    <span class="small text-body-secondary"><?= Html::e($versamentoIl) ?> <?= Html::data($voce['versamento']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap"><?= $voce !== null ? EsitoControllo::euro($voce['quote']) : '' ?></td>
                            <td class="text-end text-nowrap"><?= ($voce !== null) && ($indice > 0) ? Html::e(number_format($voce['percentuale'], 2, ',', '.')) . '%' : '' ?></td>
                            <td class="text-end text-nowrap"><?= ($voce !== null) && ($voce['interessi'] > 0) ? EsitoControllo::euro($voce['interessi']) : '' ?></td>
                            <td class="text-end text-nowrap fw-semibold"><?= $voce !== null ? EsitoControllo::euro($voce['quote'] + $voce['interessi']) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (count($anteprima) > 0): ?>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="2"><?= Html::e($colonnaTotale) ?></td>
                            <td class="text-end"><?= EsitoControllo::euro($totale['quote']) ?></td>
                            <td></td>
                            <td class="text-end"><?= EsitoControllo::euro($totale['interessi']) ?></td>
                            <td class="text-end"><?= EsitoControllo::euro($totale['quote'] + $totale['interessi']) ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="submit" name="azione" value="salva" class="btn btn-primary" <?= ($bloccato || (count($anteprima) === 0)) ? 'disabled' : '' ?>><i class="bi bi-check-lg me-1"></i><?= Html::e($nuovo ? $azioni['creaPiano'] : $azioni['salva']) ?></button>
        <a href="/contribuente/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
    </div>
</form>

<?php if (($nuovo === false) && ($bloccato === false)): ?>
    <form method="post" action="/rateazione/delete/piano/<?= Html::e($piano->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaEliminaPiano) ?>">
        <?= $campoCsrf ?>
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['eliminaPiano']) ?></button>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
