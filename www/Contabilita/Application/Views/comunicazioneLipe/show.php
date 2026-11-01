<?php

use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$id = $comunicazione->id;
$euro = fn(float $importo): string => $importo == 0 ? '' : Html::e(Importo::euro($importo));
// righi del quadro VP: etichetta e valore per modulo
$righi = [
    'VP2' => fn($modulo) => Html::e(Importo::euro($modulo->operazioniAttive)),
    'VP3' => fn($modulo) => Html::e(Importo::euro($modulo->operazioniPassive)),
    'VP4' => fn($modulo) => Html::e(Importo::euro($modulo->ivaEsigibile)),
    'VP5' => fn($modulo) => Html::e(Importo::euro($modulo->ivaDetratta)),
    'VP6' => fn($modulo) => $modulo->getIvaPeriodo() >= 0 ? $euro($modulo->getIvaPeriodo()) : '<span class="text-success">' . $euro(-$modulo->getIvaPeriodo()) . ' cr.</span>',
    'VP7' => fn($modulo) => $euro($modulo->debitoPrecedente),
    'VP8' => fn($modulo) => $euro($modulo->creditoPrecedente),
    'VP9' => fn($modulo) => $euro($modulo->creditoAnnoPrecedente),
    'VP10' => fn($modulo) => $euro($modulo->versamentiAuto),
    'VP11' => fn($modulo) => $euro($modulo->creditiImposta),
    'VP12' => fn($modulo) => $euro($modulo->interessi),
    'VP13' => fn($modulo) => $modulo->acconto == 0 ? '' : Html::e($modulo->metodoAcconto) . ' · ' . $euro($modulo->acconto),
    'VP14' => fn($modulo) => $modulo->isQuartoTrimestreTrimestrali() ? '<span class="small text-body-secondary">' . Html::e($conDichiarazione) . '</span>'
        : ($modulo->importoACredito > 0 ? '<span class="text-success">' . $euro($modulo->importoACredito) . ' cr.</span>' : $euro($modulo->importoDaVersare)),
];

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <?php if ($comunicazione->isModificabile()): ?>
        <a href="/comunicazione-lipe/update/comunicazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($titoloFrontespizio) ?></a>
        <form method="post" action="/comunicazione-lipe/prepara/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($comunicazione->anno) ?>/trimestre/<?= Html::e($comunicazione->trimestre) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i><?= Html::e($azioni['riprepara']) ?></button></form>
    <?php endif; ?>
    <?php if (count($errori) === 0): ?>
        <a href="/comunicazione-lipe/scarica/comunicazione/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['scarica']) ?></a>
    <?php endif; ?>
    <?php if ($comunicazione->isModificabile()): ?>
        <form method="post" action="/comunicazione-lipe/delete/comunicazione/<?= Html::e($id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
</div>
<p class="text-body-secondary small mb-3">
    <span class="badge <?= Html::e($comunicazione->stato->getClasseBadge()) ?>"><?= Html::e($comunicazione->stato->getLabel()) ?></span>
    <?php if ($comunicazione->dataInvio !== null): ?> <?= Html::e($inviataIl) ?> <?= Html::data($comunicazione->dataInvio) ?><?= $comunicazione->protocollo !== null ? ' · ' . Html::e($comunicazione->protocollo) : '' ?><?php endif; ?>
    · <?= Html::e($campi['scadenza']) ?> <?= Html::data($scadenzaLipe) ?> · <?= Html::e($campi['identificativo']) ?> <?= Html::e($comunicazione->getIdentificativo()) ?>
</p>
<?php foreach ($errori as $errore): ?><div class="alert alert-danger small py-2"><?= Html::e($errore) ?></div><?php endforeach; ?>
<?php foreach (array_filter(explode("\n", (string) $comunicazione->avvisi)) as $avviso): ?><div class="alert alert-info small py-2"><?= Html::e($avviso) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloFrontespizio) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><?= Html::e($campi['contribuente']) ?>: <?= Html::e($soggetto->nominativo) ?> · <?= Html::e($soggetto->codiceFiscale) ?> · <?= Html::e($campi['partitaIva']) ?> <?= Html::e($soggetto->partitaIva ?? '-') ?></li>
                <li class="list-group-item"><?= Html::e($campi['dichiarante']) ?>: <?= $comunicazione->dichiarante === null ? '-' : Html::e($comunicazione->dichiarante->nominativo . ' · ' . $comunicazione->dichiarante->codiceFiscale) . '<div class="text-body-secondary">' . Html::e($comunicazione->codiceCarica?->getLabel() ?? '') . '</div>' ?></li>
                <li class="list-group-item"><?= Html::e($campi['intermediario']) ?>: <?= $comunicazione->intermediario === null ? '-' : Html::e($comunicazione->intermediario->getEtichetta()) . '<div class="text-body-secondary">' . Html::e(($comunicazione->impegno?->getLabel() ?? '') . ' · ') . Html::data($comunicazione->dataImpegno) . '</div>' ?></li>
            </ul>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <?php if ($comunicazione->isModificabile()): ?>
                    <form method="post" action="/comunicazione-lipe/stato/comunicazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoComunicazioneLipe::inviata->value) ?>" class="d-flex flex-wrap align-items-end gap-2">
                        <?= $campoCsrf ?>
                        <div><label class="form-label small mb-0" for="dataInvio"><?= Html::e($campi['dataInvio']) ?></label><input type="date" class="form-control form-control-sm" id="dataInvio" name="dataInvio" value="<?= Html::e(date('Y-m-d')) ?>"></div>
                        <div><label class="form-label small mb-0" for="protocollo"><?= Html::e($campi['protocollo']) ?></label><input type="text" class="form-control form-control-sm" id="protocollo" name="protocollo" maxlength="50"></div>
                        <button type="submit" class="btn btn-sm btn-success" <?= count($errori) > 0 ? 'disabled' : '' ?>><?= Html::e($azioni['segnaInviata']) ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/comunicazione-lipe/stato/comunicazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoComunicazioneLipe::bozza->value) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['riportaBozza']) ?></button></form>
                <?php endif; ?>
                <div class="small text-body-secondary mt-2"><?= Html::e($aiutoInvio) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloQuadro) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>VP1</th><?php foreach ($moduliLipe as $modulo): ?><th class="text-end"><?= Html::e($modulo->getDescrizionePeriodo()) ?><?php if ($modulo->liquidazioneIva !== null): ?> <a href="/liquidazione-iva/show/liquidazione/<?= Html::e($modulo->liquidazioneIva->id) ?>" title="<?= Html::e($navAzienda['liquidazioni']) ?>"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($righi as $rigo => $valore): ?>
                            <tr><td class="text-nowrap"><span class="fw-semibold"><?= Html::e($rigo) ?></span> <span class="small text-body-secondary"><?= Html::e($descrizioniRighi[$rigo]) ?></span></td><?php foreach ($moduliLipe as $modulo): ?><td class="text-end text-nowrap"><?= $valore($modulo) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoQuadro) ?> · <?= Html::data($comunicazione->dataElaborazione, 'd/m/Y H:i') ?></div>
        </div>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
