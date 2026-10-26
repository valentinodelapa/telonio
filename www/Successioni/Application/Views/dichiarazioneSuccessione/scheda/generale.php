<?php

use Telonio\Application\HelperClasses\Html;

$defunto = $dichiarazione->defunto;
$luogoDecesso = $dichiarazione->comuneDecesso?->getEtichetta() ?? $dichiarazione->statoDecesso;
?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneFrontespizio) ?></span>
        <?php if ($modificabile): ?><a href="/dichiarazione-successione/update/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a><?php endif; ?>
    </div>
    <div class="card-body small row">
        <dl class="row mb-0 col-lg-6">
            <dt class="col-sm-5"><?= Html::e($campi['defunto']) ?></dt>
            <dd class="col-sm-7"><?= Html::e($defunto->nominativo) ?> <span class="font-monospace text-body-secondary"><?= Html::e($defunto->codiceFiscale) ?></span></dd>
            <dt class="col-sm-5"><?= Html::e($campi['dataDecesso']) ?></dt>
            <dd class="col-sm-7"><?= Html::data($defunto->dataDecesso) ?><?= $luogoDecesso !== null ? ' - ' . Html::e($luogoDecesso) : '' ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['statoCivile']) ?></dt>
            <dd class="col-sm-7"><?= Html::e($dichiarazione->statoCivile?->getLabel() ?? '—') ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['devoluzioneLegge']) ?> / <?= Html::e(mb_strtolower($campi['devoluzioneTestamento'])) ?></dt>
            <dd class="col-sm-7"><?= $dichiarazione->devoluzioneLegge ? '<i class="bi bi-check-lg"></i> legge ' : '' ?><?= $dichiarazione->devoluzioneTestamento ? '<i class="bi bi-check-lg"></i> testamento' : '' ?></dd>
            <?php if ($dichiarazione->devoluzioneTestamento): ?>
                <dt class="col-sm-5"><?= Html::e($sezioneTestamento) ?></dt>
                <dd class="col-sm-7"><?= $dichiarazione->testamentoEstero ? Html::e($campi['testamentoEstero']) : Html::e(trim(($dichiarazione->testamentoPubblicoUfficiale ?? '') . ' ' . Html::data($dichiarazione->testamentoDataPubblicazione)) . ' · reg. ' . ($dichiarazione->testamentoUfficio?->codice ?? '?') . ' ' . ($dichiarazione->testamentoSerie ?? '') . ' n. ' . ($dichiarazione->testamentoNumero ?? '') . ' ' . Html::data($dichiarazione->testamentoDataRegistrazione)) ?></dd>
            <?php endif; ?>
        </dl>
        <dl class="row mb-0 col-lg-6">
            <dt class="col-sm-5"><?= Html::e($campi['presentatore']) ?></dt>
            <dd class="col-sm-7"><?= $dichiarazione->presentatore === null ? '—' : Html::e($dichiarazione->presentatore->nominativo . ' (' . $dichiarazione->codiceCarica?->getLabel() . ')') ?></dd>
            <?php if ($dichiarazione->rappresentato !== null): ?>
                <dt class="col-sm-5"><?= Html::e($campi['rappresentato']) ?></dt>
                <dd class="col-sm-7"><?= Html::e($dichiarazione->rappresentato->nominativo) ?></dd>
            <?php endif; ?>
            <dt class="col-sm-5"><?= Html::e($campi['telefono']) ?> / <?= Html::e($campi['email']) ?></dt>
            <dd class="col-sm-7"><?= Html::e(implode(' · ', array_filter([$dichiarazione->telefono, $dichiarazione->email])) ?: '—') ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['intermediario']) ?></dt>
            <dd class="col-sm-7"><?= $dichiarazione->intermediario === null ? '—' : Html::e($dichiarazione->intermediario->soggetto->nominativo . ' · ' . $campi['dataImpegno'] . ' ' . Html::data($dichiarazione->dataImpegno)) ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['dataDichiarazione']) ?></dt>
            <dd class="col-sm-7"><?= Html::data($dichiarazione->dataDichiarazione) ?></dd>
            <?php if ($dichiarazione->senzaVolture || $dichiarazione->copiaConforme): ?>
                <dt class="col-sm-5"></dt>
                <dd class="col-sm-7"><?= Html::e(implode(' · ', array_filter([$dichiarazione->senzaVolture ? $campi['senzaVolture'] : null, $dichiarazione->copiaConforme ? $campi['copiaConforme'] : null]))) ?></dd>
            <?php endif; ?>
        </dl>
        <?php if ($dichiarazione->annotazioni !== null): ?><div class="col-12 mt-2"><div class="fw-semibold"><?= Html::e($campi['annotazioni']) ?></div><?= nl2br(Html::e($dichiarazione->annotazioni)) ?></div><?php endif; ?>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiutoTelematico) ?></p>
