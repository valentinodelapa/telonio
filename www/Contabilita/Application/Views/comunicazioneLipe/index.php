<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($titolo) ?> <?= Html::e($annoIva) ?></h1>
    <a href="/liquidazione-iva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calculator me-1"></i><?= Html::e($navAzienda['liquidazioni']) ?></a>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoIva) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniIva as $voce): ?><li><a class="dropdown-item <?= $voce === $annoIva ? 'active' : '' ?>" href="/comunicazione-lipe/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th><?= Html::e($campi['periodo']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th><?= Html::e($campi['stato']) ?></th><th><?= Html::e($campi['protocollo']) ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($trimestri as $trimestre => $scadenza): ?>
                    <?php $comunicazione = $comunicazioni[$trimestre] ?? null; ?>
                    <tr>
                        <td><?= $comunicazione === null ? Html::e($trimestre . '° trimestre') : '<a href="/comunicazione-lipe/show/comunicazione/' . Html::e($comunicazione->id) . '" class="fw-semibold text-decoration-none">' . Html::e($trimestre . '° trimestre') . '</a>' ?></td>
                        <td class="small text-nowrap"><?= Html::data($scadenza) ?></td>
                        <td><?= $comunicazione === null ? '<span class="small text-body-secondary">' . Html::e($nonPreparata) . '</span>' : '<span class="badge ' . Html::e($comunicazione->stato->getClasseBadge()) . '">' . Html::e($comunicazione->stato->getLabel()) . '</span>' ?></td>
                        <td class="small"><?= $comunicazione?->dataInvio === null ? '' : Html::data($comunicazione->dataInvio) . ($comunicazione->protocollo === null ? '' : ' · ' . Html::e($comunicazione->protocollo)) ?></td>
                        <td class="text-end">
                            <?php if (($comunicazione === null) || $comunicazione->isModificabile()): ?>
                                <form method="post" action="/comunicazione-lipe/prepara/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>/trimestre/<?= Html::e($trimestre) ?>">
                                    <?= $campoCsrf ?>
                                    <button type="submit" class="btn btn-sm <?= $comunicazione === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($comunicazione === null ? $azioni['prepara'] : $azioni['riprepara']) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
