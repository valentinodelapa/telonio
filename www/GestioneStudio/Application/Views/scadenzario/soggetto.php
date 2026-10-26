<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$mostraSoggetto = false;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-0"><?= Html::e($soggetto->nominativo) ?></h1>
        <div class="small text-body-secondary font-monospace"><?= Html::e($soggetto->codiceFiscale) ?></div>
    </div>
    <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-person-vcard me-1"></i><?= Html::e($azioni['schedaAnagrafica']) ?></a>
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($campi['anno']) ?> <?= Html::e($anno) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anni as $voceAnno): ?>
                <li><a class="dropdown-item <?= $voceAnno === $anno ? 'active' : '' ?>" href="/scadenzario/soggetto/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voceAnno) ?>"><?= Html::e($voceAnno) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneAdempimenti) ?></span>
        <a href="/assegnazione-adempimento/create/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['assegnaCliente']) ?></a>
    </div>
    <div class="card-body small text-body-secondary py-2"><?= Html::e($aiutoAdempimenti) ?></div>
    <?php if (count($assegnazioni) === 0): ?>
        <div class="card-body text-center text-body-secondary"><?= Html::e($nessunAdempimento) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['adempimento']) ?></th>
                        <th><?= Html::e($campi['validoDal']) ?></th>
                        <th><?= Html::e($campi['validoAl']) ?></th>
                        <th><?= Html::e($campi['responsabile']) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assegnazioni as $assegnazione): ?>
                        <tr class="<?= $assegnazione->isVigenteAl($oggi) ? '' : 'text-body-secondary' ?>">
                            <td>
                                <a href="/adempimento/show/adempimento/<?= Html::e($assegnazione->adempimento->id) ?>" class="text-decoration-none"><?= Html::e($assegnazione->adempimento->denominazione) ?></a>
                                <?php if ($assegnazione->note !== null): ?><div class="small"><?= Html::e($assegnazione->note) ?></div><?php endif; ?>
                            </td>
                            <td><?= Html::data($assegnazione->validoDal) ?></td>
                            <td><?= $assegnazione->validoAl === null ? Html::e($sempre) : Html::data($assegnazione->validoAl) ?></td>
                            <td><?= Html::e($assegnazione->responsabile?->getNomeCompleto() ?? '') ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($assegnazione->isAperto()): ?>
                                    <a href="/assegnazione-adempimento/variazione/assegnazione/<?= Html::e($assegnazione->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['nuovaVariazione']) ?>"><i class="bi bi-plus-slash-minus"></i></a>
                                <?php endif; ?>
                                <a href="/assegnazione-adempimento/update/assegnazione/<?= Html::e($assegnazione->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="/assegnazione-adempimento/delete/assegnazione/<?= Html::e($assegnazione->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneScadenze) ?> <?= Html::e($anno) ?></div>
    <?php if (count($voci) === 0): ?>
        <div class="card-body text-center text-body-secondary"><?= Html::e($nessunaScadenza) ?></div>
    <?php else: ?>
        <?php require __DIR__ . '/../commonParts/tabellaVoci.php'; ?>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
