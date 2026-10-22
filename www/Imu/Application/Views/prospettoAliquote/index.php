<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$sezioneImu = 'aliquote';
$urlAnno = '/prospetto-aliquote/index/anno/';
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$percentuale = fn(?float $aliquota): string => $aliquota === null ? '—' : number_format($aliquota, 2, ',', '') . '%';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navImu.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($navAliquote) ?> <?= Html::e($anno) ?></h1>
    <?php if ($importabile && (count($comuni) > 0)): ?>
        <form method="post" action="/prospetto-aliquote/importa/anno/<?= Html::e($anno) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-primary"><i class="bi bi-cloud-download me-1"></i><?= Html::e($azioni['importa']) ?></button></form>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/selettoreAnno.php'; ?>
</div>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($importabile === false): ?><div class="alert alert-info small"><?= Html::e($nonImportabile) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <?php if (count($comuni) === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><?= Html::e($nessunComune) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['comune']) ?></th>
                        <th><?= Html::e($colonnaApplicate) ?></th>
                        <?php foreach ($fattispecie as $voce): ?>
                            <th class="text-end small" title="<?= Html::e($voce->getLabel()) ?>"><?= Html::e($voce->getCodiceTributo()) ?></th>
                        <?php endforeach; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comuni as ['codice' => $codice, 'comune' => $comune, 'delloStudio' => $prospettoStudio, 'pubblicato' => $pubblicatoComune, 'applicato' => $applicato]): ?>
                        <?php $prospetto = $applicato['prospetto']; ?>
                        <tr>
                            <td><span class="fw-semibold"><?= Html::e($comune?->getEtichetta() ?? $codice) ?></span> <span class="font-monospace small text-body-secondary"><?= Html::e($codice) ?></span></td>
                            <td class="small">
                                <?php if ($prospettoStudio !== null): ?>
                                    <span class="badge text-bg-primary"><?= Html::e($delloStudio) ?></span>
                                <?php elseif ($pubblicatoComune !== null): ?>
                                    <span class="badge text-bg-success"><?= Html::e($pubblicato) ?></span>
                                <?php endif; ?>
                                <?php if (($prospettoStudio !== null) || ($pubblicatoComune !== null)): ?>
                                    <a href="/prospetto-aliquote/show/prospetto/<?= Html::e(($prospettoStudio ?? $pubblicatoComune)->id) ?>"><?= Html::e($applicato['origine']) ?></a>
                                <?php else: ?>
                                    <span class="text-warning-emphasis"><?= Html::e($applicato['origine']) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($fattispecie as $voce): ?>
                                <td class="text-end small text-nowrap"><?= Html::e($percentuale($prospetto?->getAliquota($voce) ?? ($prospetto === null ? $voce->getAliquotaBase() : null))) ?></td>
                            <?php endforeach; ?>
                            <td class="text-end text-nowrap">
                                <?php if ($pubblicatoComune !== null): ?>
                                    <a href="/prospetto-aliquote/show/prospetto/<?= Html::e($pubblicatoComune->id) ?>" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['vediProspetto']) ?></a>
                                <?php endif; ?>
                                <a href="/prospetto-aliquote/create/codice/<?= Html::e($codice) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['inserisci']) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
