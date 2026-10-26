<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use GestioneStudio\Application\Enumerations\CondizioneProroga;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><i class="bi <?= Html::e($adempimento->categoria->getIcona()) ?> text-primary me-2"></i><?= Html::e($pageTitle) ?></h1>
    <a href="/adempimento/assegna/adempimento/<?= Html::e($adempimento->id) ?>" class="btn btn-primary"><i class="bi bi-people me-1"></i><?= Html::e($azioni['assegna']) ?></a>
    <?php if ($modificabile): ?>
        <a href="/adempimento/update/adempimento/<?= Html::e($adempimento->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
        <form method="post" action="/adempimento/delete/adempimento/<?= Html::e($adempimento->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($campi['anno']) ?> <?= Html::e($anno) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anni as $voceAnno): ?>
                <li><a class="dropdown-item <?= $voceAnno === $anno ? 'active' : '' ?>" href="/adempimento/show/adempimento/<?= Html::e($adempimento->id) ?>/anno/<?= Html::e($voceAnno) ?>"><?= Html::e($voceAnno) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="text-body-secondary mb-1">
    <span class="badge <?= $adempimento->isDelCalendario() ? 'text-bg-light border' : 'text-bg-info' ?> me-1"><?= Html::e($adempimento->isDelCalendario() ? $calendarioFiscale : $delloStudio) ?></span>
    <?= Html::e($adempimento->categoria->getLabel()) ?> · <?= Html::e($campi['ambito']) ?>: <?= Html::e($adempimento->ambito->getLabel()) ?>
</p>
<?php if ($adempimento->fonte !== null): ?><p class="small text-body-secondary mb-1"><?= Html::e($campi['fonte']) ?>: <?= Html::e($adempimento->fonte) ?></p><?php endif; ?>
<?php if ($adempimento->note !== null): ?><p class="small"><?= Html::e($adempimento->note) ?></p><?php endif; ?>

<div class="card shadow-sm my-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneTermini) ?> <?= Html::e($anno) ?></span>
        <?php if ($modificabile): ?>
            <a href="/termine-adempimento/create/adempimento/<?= Html::e($adempimento->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoTermine']) ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body small text-body-secondary py-2"><?= Html::e($aiutoTermini) ?></div>
    <?php if (count($termini) === 0): ?>
        <div class="card-body text-center text-body-secondary"><?= Html::e($nessunTermine) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['periodo']) ?></th>
                        <th><?= Html::e($campi['termine']) ?></th>
                        <th><?= Html::e($campi['termineOrdinario']) ?></th>
                        <th><?= Html::e($campi['termineEffettivo']) ?></th>
                        <th><?= Html::e($prorogheStudio) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($termini as ['effettivo' => $effettivo, 'proroghe' => $proroghe]): ?>
                        <tr>
                            <td><?= Html::e($effettivo->getPeriodo()) ?></td>
                            <td class="small"><?= Html::e($effettivo->termine->getDescrizioneTermine()) ?></td>
                            <td><?= Html::data($effettivo->ordinario) ?></td>
                            <td class="fw-semibold"><?= Html::data($effettivo->data) ?></td>
                            <td class="small">
                                <?php foreach ($proroghe as $proroga): ?>
                                    <div>
                                        <span class="badge <?= $proroga->isDelCalendario() ? 'text-bg-light border' : 'text-bg-info' ?>"><?= Html::e($proroga->anno) ?></span>
                                        <?= Html::data($proroga->data) ?> · <?= Html::e($proroga->provvedimento) ?>
                                        <?php if ($proroga->condizione !== CondizioneProroga::tutti): ?><span class="badge text-bg-warning ms-1"><?= Html::e($proroga->condizione->getLabel()) ?></span><?php endif; ?>
                                        <?php if ($proroga->note !== null): ?><div class="text-body-secondary"><?= Html::e($proroga->note) ?></div><?php endif; ?>
                                        <?php if ($proroga->isDelCalendario() === false): ?>
                                            <a href="/proroga-adempimento/update/proroga/<?= Html::e($proroga->id) ?>" class="ms-1" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="/proroga-adempimento/delete/proroga/<?= Html::e($proroga->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="/proroga-adempimento/create/termine/<?= Html::e($effettivo->termine->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-plus me-1"></i><?= Html::e($azioni['proroga']) ?></a>
                                <?php if ($modificabile): ?>
                                    <a href="/termine-adempimento/update/termine/<?= Html::e($effettivo->termine->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/termine-adempimento/delete/termine/<?= Html::e($effettivo->termine->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneAssegnati) ?> (<?= Html::e(count($assegnati)) ?>)</div>
    <?php if (count($assegnati) === 0): ?>
        <div class="card-body text-center text-body-secondary"><?= Html::e($nessunAssegnato) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($assegnati as $assegnazione): ?>
                <li class="list-group-item d-flex align-items-center gap-2 small">
                    <a href="/scadenzario/soggetto/soggetto/<?= Html::e($assegnazione->soggetto->id) ?>" class="me-auto text-decoration-none"><?= Html::e($assegnazione->soggetto->nominativo) ?></a>
                    <span class="text-body-secondary"><?= Html::e($assegnazione->responsabile?->getNomeCompleto() ?? '') ?></span>
                    <span><?= Html::data($assegnazione->validoDal) ?> – <?= $assegnazione->validoAl === null ? Html::e($sempre) : Html::data($assegnazione->validoAl) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
