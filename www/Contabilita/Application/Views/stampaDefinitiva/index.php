<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['stampeDefinitive']) ?> <?= Html::e($annoStampe) ?></h1>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoStampe) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniStampe as $voce): ?><li><a class="dropdown-item <?= $voce === $annoStampe ? 'active' : '' ?>" href="/stampa-definitiva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<?php if (count($libri) === 0): ?>
    <div class="alert alert-light border small"><?= Html::e($nessunLibro) ?></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($libri as $chiave => $libro): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center">
                    <span class="fw-semibold me-auto"><?= Html::e($libro['titolo']) ?></span>
                    <span class="small text-body-secondary"><?= Html::e($libro['tipo']->getNorma()) ?></span>
                </div>
                <?php if (count($libro['stampe']) > 0): ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($libro['stampe'] as $indice => $stampa): ?>
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="me-auto"><?= Html::data($stampa->dal) ?> - <?= Html::data($stampa->al) ?> · <?= Html::e($campi['pagine']) ?> <?= Html::e($stampa->getPagine()) ?>
                                    <span class="text-body-secondary">· <?= Html::data($stampa->dataStampa, 'd/m/Y') ?></span></span>
                                <a href="/stampa-definitiva/stampa/stampa/<?= Html::e($stampa->id) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['ristampa']) ?>"><i class="bi bi-printer"></i></a>
                                <?php if ($indice === array_key_last($libro['stampe'])): ?>
                                    <form method="post" action="/stampa-definitiva/annulla/stampa/<?= Html::e($stampa->id) ?>" data-conferma="<?= Html::e($confermaAnnulla) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['annullaStampa']) ?>"><i class="bi bi-x-lg"></i></button></form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="card-body">
                    <?php if ($libro['completo']): ?>
                        <div class="small text-success"><i class="bi bi-check-circle me-1"></i><?= Html::e($completo) ?></div>
                    <?php elseif ($libro['dal']->format('Y-m-d') > $libro['massimo']): ?>
                        <div class="small text-body-secondary"><?= Html::e($nienteDaStampare) ?> <?= Html::data($libro['dal']) ?>.</div>
                    <?php else: ?>
                        <form method="post" action="/stampa-definitiva/registra/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoStampe) ?>/libro/<?= Html::e($chiave) ?>" class="d-flex flex-wrap align-items-end gap-2" data-conferma="<?= Html::e($confermaRegistra) ?>">
                            <?= $campoCsrf ?>
                            <div><label class="form-label small mb-0"><?= Html::e($campi['dal']) ?></label><input type="text" class="form-control form-control-sm" value="<?= Html::data($libro['dal']) ?>" disabled></div>
                            <div><label class="form-label small mb-0" for="al<?= Html::e($chiave) ?>"><?= Html::e($campi['al']) ?></label><input type="date" class="form-control form-control-sm" id="al<?= Html::e($chiave) ?>" name="al" value="<?= Html::e($libro['proposta']) ?>" min="<?= Html::e($libro['dal']->format('Y-m-d')) ?>" max="<?= Html::e($libro['massimo']) ?>"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-anteprima="/stampa-definitiva/anteprima/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoStampe) ?>/libro/<?= Html::e($chiave) ?>"><i class="bi bi-eye me-1"></i><?= Html::e($azioni['anteprima']) ?></button>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-lock me-1"></i><?= Html::e($azioni['stampaDefinitiva']) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script src="/javascript/contabilita.js"></script>
<?php require Layout::percorso('footer'); ?>
