<?php

use Contabilita\Application\Enumerations\TipoChiusura;
use Telonio\Application\HelperClasses\Html;

/**
 * Abbinamento delle righe del partitario alle partite aperte
 * ($partiteRegistrazione, una voce per controparte e sottoconto).
 */
$importoTesto = fn(?float $valore): string => $valore === null || abs($valore) < 0.005 ? '' : number_format($valore, 2, ',', '');
?>
<?php if (count($partiteRegistrazione) > 0): ?>
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($testiPartite['titoloPartite']) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($testiPartite['aiutoPartite']) ?></span></div>
        <div class="card-body">
            <?php foreach ($partiteRegistrazione as $gruppo): ?>
                <?php $prefisso = 'partite[' . $gruppo['chiave'] . ']'; ?>
                <input type="hidden" name="<?= Html::e($prefisso) ?>[presente]" value="1">
                <div class="mb-3">
                    <div class="fw-semibold"><?= Html::e(($gruppo['cliente'] ? $testiPartite['cliente'] : $testiPartite['fornitore']) . ' ' . $gruppo['controparte']) ?>
                        <span class="fw-normal text-body-secondary">· <?= Html::e($gruppo['apertura'] ? $testiPartite['apreLaPartita'] : $testiPartite['chiude']) ?> <?= Html::e(number_format(abs($gruppo['importo']), 2, ',', '.')) ?></span></div>
                    <?php if (count($gruppo['abbinabili']) === 0): ?>
                        <div class="small text-body-secondary"><?= Html::e($gruppo['apertura'] ? $testiPartite['nessunAcconto'] : $testiPartite['nessunaPartita']) ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1">
                                <thead class="table-light"><tr><th><?= Html::e($testiPartite['partita']) ?></th><th><?= Html::e($testiPartite['scadenza']) ?></th><th class="text-end"><?= Html::e($testiPartite['importo']) ?></th><th class="text-end"><?= Html::e($testiPartite['residuo']) ?></th>
                                    <?php foreach ($gruppo['colonne'] as $tipo): ?><th class="text-end" style="width: 9rem"><?= Html::e(TipoChiusura::from($tipo)->getEtichettaBreve()) ?></th><?php endforeach; ?>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($gruppo['abbinabili'] as $voce): ?>
                                        <tr>
                                            <td><?= Html::e($voce['etichetta']) ?></td>
                                            <td><?= Html::e($voce['scadenza']) ?></td>
                                            <td class="text-end"><?= Html::e(number_format($voce['importo'], 2, ',', '.')) ?></td>
                                            <td class="text-end"><?= Html::e(number_format($voce['residuo'], 2, ',', '.')) ?></td>
                                            <?php foreach ($gruppo['colonne'] as $tipo): ?>
                                                <?php $tipoCella = $gruppo['apertura'] ? $voce['tipo'] : $tipo; ?>
                                                <td><?php if ($tipoCella === $tipo): ?><input type="text" class="form-control form-control-sm text-end" inputmode="decimal" name="<?= Html::e($prefisso . '[abbinamenti][' . $voce['id'] . '][' . $tipo . ']') ?>" value="<?= Html::e($importoTesto($gruppo['scelte']['abbinamenti'][$voce['id']][$tipo] ?? null)) ?>"><?php endif; ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (($gruppo['apertura'] === false) && ($gruppo['nota'] === false)): ?>
                        <div class="form-check small"><input class="form-check-input" type="checkbox" value="1" id="acconto-<?= Html::e($gruppo['chiave']) ?>" name="<?= Html::e($prefisso) ?>[acconto]" <?= $gruppo['scelte']['acconto'] ? 'checked' : '' ?>><label class="form-check-label" for="acconto-<?= Html::e($gruppo['chiave']) ?>"><?= Html::e($testiPartite['eccedenzaAcconto']) ?></label></div>
                    <?php elseif ($gruppo['nota']): ?>
                        <div class="small text-body-secondary"><?= Html::e($testiPartite['eccedenzaNota']) ?></div>
                    <?php elseif (count($gruppo['riapribili']) > 0): ?>
                        <div class="d-flex align-items-center gap-2 small"><label for="riapre-<?= Html::e($gruppo['chiave']) ?>"><?= Html::e($testiPartite['insolutoDi']) ?></label>
                            <select class="form-select form-select-sm w-auto" id="riapre-<?= Html::e($gruppo['chiave']) ?>" name="<?= Html::e($prefisso) ?>[riapre]">
                                <option value=""><?= Html::e($testiPartite['nuovaPartita']) ?></option>
                                <?php foreach ($gruppo['riapribili'] as $id => $etichetta): ?><option value="<?= Html::e($id) ?>" <?= ($gruppo['scelte']['riapre'] ?? null) === $id ? 'selected' : '' ?>><?= Html::e($etichetta) ?></option><?php endforeach; ?>
                            </select></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
