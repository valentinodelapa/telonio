<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/attivo';
$selezionato = fn($valore, $opzione): string => (string) $valore === (string) $opzione ? 'selected' : '';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($bene->codiceDiritto->getLabel()) ?> · <?= Html::e($valoreBene) ?> € <?= Html::e(number_format($bene->getValoreTotale(), 0, ',', '.')) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if (isset($erroriRighe['generale'])): ?><div class="alert alert-danger small"><?= Html::e($erroriRighe['generale']) ?></div><?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['beneficiario']) ?></th><th><?= Html::e($campi['quota']) ?></th><th><?= Html::e($campi['diritto']) ?></th><th><?= Html::e($campi['valoreQuota']) ?></th><th><?= Html::e($campi['agevolazione']) ?></th><th><?= Html::e($campi['riduzione']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($righe as $indice => $riga): ?>
                        <?php $nome = 'righe[' . $indice . ']'; ?>
                        <tr class="<?= isset($erroriRighe[$indice]) ? 'table-danger' : '' ?>">
                            <td>
                                <select class="form-select form-select-sm" name="<?= Html::e($nome) ?>[beneficiario]">
                                    <option value=""><?= Html::e($selezionare) ?></option>
                                    <?php foreach ($beneficiari as $beneficiario): ?><option value="<?= Html::e($beneficiario->id) ?>" <?= $selezionato($riga['beneficiario'], $beneficiario->id) ?>><?= Html::e($beneficiario->getRiferimento() . ' ' . $beneficiario->soggetto->nominativo) ?></option><?php endforeach; ?>
                                </select>
                                <?php if (isset($erroriRighe[$indice])): ?><div class="small text-danger"><?= Html::e($erroriRighe[$indice]) ?></div><?php endif; ?>
                            </td>
                            <td class="text-nowrap"><input type="text" class="form-control form-control-sm d-inline-block" style="width:4.5em" name="<?= Html::e($nome) ?>[numeratore]" value="<?= Html::e($riga['numeratore']) ?>" inputmode="numeric"> / <input type="text" class="form-control form-control-sm d-inline-block" style="width:4.5em" name="<?= Html::e($nome) ?>[denominatore]" value="<?= Html::e($riga['denominatore']) ?>" inputmode="numeric"></td>
                            <td><select class="form-select form-select-sm" name="<?= Html::e($nome) ?>[diritto]"><?php foreach ($diritti as $diritto): ?><option value="<?= Html::e($diritto->value) ?>" <?= $selezionato($riga['diritto'], $diritto->value) ?>><?= Html::e($diritto->getLabel()) ?></option><?php endforeach; ?></select></td>
                            <td><input type="text" class="form-control form-control-sm" name="<?= Html::e($nome) ?>[valore]" value="<?= Html::e($riga['valore'] === null ? '' : number_format((float) $riga['valore'], 2, ',', '')) ?>" inputmode="decimal" placeholder="calcolato"></td>
                            <td><select class="form-select form-select-sm" name="<?= Html::e($nome) ?>[agevolazione]"><option value=""><?= Html::e($nessuna) ?></option><?php foreach ($agevolazioni as $agevolazione): ?><option value="<?= Html::e($agevolazione->value) ?>" <?= $selezionato($riga['agevolazione'], $agevolazione->value) ?>><?= Html::e($agevolazione->getLabel()) ?></option><?php endforeach; ?></select></td>
                            <td><select class="form-select form-select-sm" name="<?= Html::e($nome) ?>[riduzione]"><option value=""><?= Html::e($nessuna) ?></option><?php foreach ($riduzioni as $riduzione): ?><option value="<?= Html::e($riduzione->value) ?>" <?= $selezionato($riga['riduzione'], $riduzione->value) ?>><?= Html::e($riduzione->getLabel()) ?></option><?php endforeach; ?></select></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
