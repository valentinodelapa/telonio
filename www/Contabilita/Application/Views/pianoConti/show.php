<?php

use Contabilita\Application\Enumerations\LivelloConto;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$azienda = $pianoConti->soggetto !== null;
$rientro = ['M' => 0, 'C' => 1, 'S' => 2];

require Layout::percorso('header');
if ($azienda) {
    require __DIR__ . '/../commonParts/navAzienda.php';
} else {
    $sezioneContabilita = 'piani';
    require __DIR__ . '/../commonParts/navContabilita.php';
}
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pianoConti->denominazione) ?></h1>
    <?php if ($modificabile): ?>
        <a href="/piano-conti/update/piano/<?= Html::e($pianoConti->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
        <a href="/conto/create/piano/<?= Html::e($pianoConti->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovoMastro) ?></a>
    <?php endif; ?>
    <?php if ($pianoConti->isModello()): ?>
        <form method="post" action="/piano-conti/copia/piano/<?= Html::e($pianoConti->id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-copy me-1"></i><?= Html::e($azioni['copia']) ?></button></form>
    <?php endif; ?>
    <?php if ($pianoConti->isModello() && $modificabile): ?>
        <form method="post" action="/piano-conti/delete/piano/<?= Html::e($pianoConti->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
</div>
<p class="small text-body-secondary"><?= Html::e($azienda ? $aiutoAzienda : ($modificabile ? $aiutoModello : $aiutoSuite)) ?></p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th><?= Html::e($campi['codice']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['natura']) ?></th><th><?= Html::e($campi['funzione']) ?></th><th><?= Html::e($campi['voceCee']) ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($conti as $conto): ?>
                    <tr class="<?= $conto->attivo ? '' : 'text-body-secondary' ?>">
                        <td class="font-monospace text-nowrap <?= $conto->livello === LivelloConto::mastro ? 'fw-bold' : '' ?>"><?= Html::e($conto->codice) ?></td>
                        <td style="padding-left: <?= 0.5 + $rientro[$conto->livello->value] * 1.25 ?>rem" class="<?= $conto->livello === LivelloConto::mastro ? 'fw-bold' : ($conto->livello === LivelloConto::conto ? 'fw-semibold' : '') ?>">
                            <?php if ($azienda && $conto->livello === LivelloConto::sottoconto): ?>
                                <a href="/stampa-contabile/scheda/soggetto/<?= Html::e($pianoConti->soggetto->id) ?>/conto/<?= Html::e($conto->id) ?>" class="text-decoration-none"><?= Html::e($conto->descrizione) ?></a>
                            <?php else: ?>
                                <?= Html::e($conto->descrizione) ?>
                            <?php endif; ?>
                            <?php if ($conto->attivo === false): ?><span class="badge text-bg-light border"><?= Html::e($nonAttivo) ?></span><?php endif; ?>
                        </td>
                        <td class="small"><?= $conto->livello === LivelloConto::mastro ? Html::e($conto->natura->getLabel()) : '' ?></td>
                        <td class="small"><?= Html::e($conto->funzione?->getLabel() ?? '') ?></td>
                        <td class="small text-body-secondary"><?= Html::e($conto->voceCee?->getEtichetta() ?? '') ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($modificabile): ?>
                                <?php if ($conto->livello !== LivelloConto::sottoconto): ?>
                                    <a href="/conto/create/piano/<?= Html::e($pianoConti->id) ?>/padre/<?= Html::e($conto->id) ?>" class="btn btn-sm btn-link py-0" title="<?= Html::e($nuovoSottoconto) ?>"><i class="bi bi-plus-lg"></i></a>
                                <?php endif; ?>
                                <a href="/conto/update/conto/<?= Html::e($conto->id) ?>" class="btn btn-sm btn-link py-0" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
