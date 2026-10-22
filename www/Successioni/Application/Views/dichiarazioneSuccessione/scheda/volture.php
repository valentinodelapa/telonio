<?php

use CommSuite\Application\HelperClasses\Html;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;

$etichetteVoltura = ['chiusura' => $chiusura, 'nuovo' => $nuovo, 'variazione' => $variazione];
$presentata = $dichiarazione->stato === StatoDichiarazioneSuccessione::presentata;
?>
<p class="small text-body-secondary"><?= Html::e($aiutoVolture) ?></p>
<?php if ($dichiarazione->voltureApplicate !== null): ?>
    <div class="alert alert-success small"><?= Html::e($voltureApplicate) ?> <?= Html::data($dichiarazione->voltureApplicate, 'd/m/Y H:i') ?>.</div>
<?php elseif ($presentata === false): ?>
    <div class="alert alert-info small"><?= Html::e($voltureSoloPresentata) ?></div>
<?php endif; ?>
<div class="card shadow-sm">
    <?php if (count($volture) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessunaVoltura) ?></div>
    <?php else: ?>
        <table class="table table-sm mb-0 small">
            <thead class="table-light"><tr><th></th><th><?= Html::e($campi['immobile']) ?></th><th><?= Html::e($campi['soggetto']) ?></th><th><?= Html::e($campi['diritto']) ?></th><th class="text-end">%</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($volture as $voltura): ?>
                    <tr>
                        <td><span class="badge <?= $voltura['tipo'] === 'chiusura' ? 'text-bg-secondary' : 'text-bg-primary' ?>"><?= Html::e($etichetteVoltura[$voltura['tipo']]) ?></span></td>
                        <td><?= Html::e($voltura['immobile']->getIdentificativo()) ?><div class="text-body-secondary"><?= Html::e($voltura['immobile']->comune->denominazione) ?></div></td>
                        <td><?= Html::e($voltura['soggetto']->nominativo) ?></td>
                        <td><?= Html::e($voltura['titolo']->getLabel()) ?></td>
                        <td class="text-end text-nowrap"><?= Html::e(number_format($voltura['quotaPrima'], 2, ',', '')) ?> → <?= Html::e(number_format($voltura['quotaDopo'], 2, ',', '')) ?></td>
                        <td class="text-nowrap"><?= $voltura['tipo'] === 'chiusura' ? 'fino al ' : 'dal ' ?><?= Html::data($voltura['data']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($presentata && ($dichiarazione->voltureApplicate === null)): ?>
            <div class="card-footer bg-white">
                <form method="post" action="/dichiarazione-successione/volture/dichiarazione/<?= Html::e($id) ?>" data-conferma="<?= Html::e($azioni['applicaVolture']) ?>?"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-left-right me-1"></i><?= Html::e($azioni['applicaVolture']) ?></button></form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
