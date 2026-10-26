<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$percentuale = fn(?float $aliquota): string => $aliquota === null ? '' : number_format($aliquota, 2, ',', '') . '%';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
        <div class="text-body-secondary">
            <?= Html::e(ucfirst($prospetto->getOrigine())) ?>
            <?php if ($prospetto->numeroDelibera !== null): ?> · <?= Html::e($campi['numeroDelibera']) ?> <?= Html::e($prospetto->numeroDelibera) ?> <?= $prospetto->dataDelibera !== null ? 'del ' . Html::data($prospetto->dataDelibera) : '' ?><?php endif; ?>
            <?php if ($prospetto->dataPubblicazione !== null): ?> · <?= Html::e($campi['dataPubblicazione']) ?> <?= Html::data($prospetto->dataPubblicazione) ?><?php endif; ?>
        </div>
    </div>
    <?php if ($prospetto->urlProspetto !== null): ?>
        <a href="<?= Html::e($prospetto->urlProspetto) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i><?= Html::e($vediPdf) ?></a>
    <?php endif; ?>
    <?php if ($prospetto->isPubblicato() === false): ?>
        <a href="/prospetto-aliquote/update/prospetto/<?= Html::e($prospetto->id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
        <form method="post" action="/prospetto-aliquote/delete/prospetto/<?= Html::e($prospetto->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
    <a href="/prospetto-aliquote/index/anno/<?= Html::e($prospetto->anno) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['tornaElenco']) ?></a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneBase) ?></div>
            <table class="table table-sm mb-0">
                <tbody>
                    <?php foreach ($fattispecie as $voce): ?>
                        <tr>
                            <td class="small"><span class="font-monospace"><?= Html::e($voce->getCodiceTributo()) ?></span> <?= Html::e($voce->getLabel()) ?></td>
                            <td class="text-end text-nowrap"><?= $prospetto->getAliquota($voce) === null ? '<span class="small text-body-secondary">' . Html::e($nonIndicata) . ' ' . Html::e($percentuale($voce->getAliquotaBase())) . '</span>' : '<strong>' . Html::e($percentuale($prospetto->getAliquota($voce))) . '</strong>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td class="small"><?= Html::e($campi['assimilazioneAnziani']) ?></td><td class="text-end"><?= $prospetto->assimilazioneAnziani ? 'SI' : 'NO' ?></td></tr>
                    <tr><td class="small"><?= Html::e($campi['detrazioneAbitazionePrincipale']) ?></td><td class="text-end"><?= Html::e(Importo::euro($prospetto->detrazioneAbitazionePrincipale)) ?></td></tr>
                    <tr><td class="small"><?= Html::e($campi['importoMinimo']) ?></td><td class="text-end"><?= Html::e(Importo::euro($prospetto->importoMinimo)) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDifferenziate) ?></div>
            <?php if (count($differenziate) === 0): ?>
                <div class="card-body small text-body-secondary"><?= Html::e($nessunaDifferenziata) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($differenziate as $differenziata): ?>
                        <li class="list-group-item d-flex gap-2">
                            <span class="badge text-bg-primary align-self-start"><?= Html::e($percentuale($differenziata->aliquota)) ?></span>
                            <span class="small"><span class="fw-semibold"><?= Html::e($differenziata->fattispecie?->getLabel() ?? '') ?></span><span class="d-block text-body-secondary" style="white-space: pre-line"><?= Html::e($differenziata->descrizione) ?></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php if (($prospetto->esenzioni !== null) || ($prospetto->note !== null)): ?>
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneEsenzioni) ?></div>
                <div class="card-body small" style="white-space: pre-line"><?= Html::e(trim(($prospetto->esenzioni ?? '') . "\n" . ($prospetto->note ?? ''))) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
