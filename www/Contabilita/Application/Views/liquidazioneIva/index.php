<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['liquidazioni']) ?> <?= Html::e($annoIva) ?><?= $periodicita === null ? '' : ' · ' . Html::e($periodicita->getLabel()) ?></h1>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoIva) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniIva as $voce): ?><li><a class="dropdown-item <?= $voce === $annoIva ? 'active' : '' ?>" href="/liquidazione-iva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<?php if ($erroreIva !== null): ?>
    <div class="alert alert-warning"><?= Html::e($erroreIva) ?> <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>">Anagrafica</a></div>
<?php else: ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th><?= Html::e($campi['periodo']) ?></th><th class="text-end"><?= Html::e($campi['ivaEsigibile']) ?></th><th class="text-end"><?= Html::e($campi['ivaDetraibile']) ?></th><th class="text-end"><?= Html::e($campi['importoDovuto']) ?></th><th class="text-end"><?= Html::e($campi['creditoFinale']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th style="min-width: 22rem"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($periodi as $periodo => $dati): ?>
                        <?php $liquidazione = $liquidazioni[$periodo] ?? null; ?>
                        <tr>
                            <td><?= $liquidazione === null ? Html::e($dati['descrizione']) : '<a href="/liquidazione-iva/show/liquidazione/' . Html::e($liquidazione->id) . '" class="fw-semibold text-decoration-none">' . Html::e($dati['descrizione']) . '</a>' ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->ivaEsigibile)) ?></td>
                            <td class="text-end"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->ivaDetraibile)) ?></td>
                            <td class="text-end fw-semibold"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->importoDovuto, true)) ?><?= ($liquidazione !== null) && ($liquidazione->debitoRinviato > 0) ? '<div class="small fw-normal text-body-secondary">' . Html::e(Importo::euro($liquidazione->debitoRinviato)) . ' rinviato</div>' : '' ?></td>
                            <td class="text-end text-success"><?= $liquidazione === null ? '' : Html::e(Importo::euro($liquidazione->creditoFinale, true)) ?></td>
                            <td class="small text-nowrap"><?= Html::data($dati['scadenza']) ?></td>
                            <td>
                                <form method="post" action="/liquidazione-iva/elabora/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>/periodo/<?= Html::e($periodo) ?>" class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                    <?= $campoCsrf ?>
                                    <input type="text" class="form-control form-control-sm text-end" style="width: 7rem" name="creditoAnnoPrecedente" value="<?= $liquidazione === null || $liquidazione->creditoAnnoPrecedente == 0 ? '' : Html::e(Importo::euro($liquidazione->creditoAnnoPrecedente)) ?>" placeholder="0,00" title="<?= Html::e($campi['creditoAnnoPrecedente']) ?>" inputmode="decimal">
                                    <div class="form-check form-check-inline mb-0 small" title="<?= Html::e($etichettaGiroconto) ?>">
                                        <input class="form-check-input" type="checkbox" name="giroconto" value="1" id="giroconto<?= Html::e($periodo) ?>" <?= ($liquidazione === null) || ($liquidazione->registrazioneContabile !== null) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="giroconto<?= Html::e($periodo) ?>"><i class="bi bi-journal-text"></i></label>
                                    </div>
                                    <button type="submit" class="btn btn-sm <?= $liquidazione === null ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($liquidazione === null ? $azioni['elabora'] : $azioni['rielabora']) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($campi['creditoAnnoPrecedente']) ?> · <i class="bi bi-journal-text"></i> <?= Html::e($etichettaGiroconto) ?></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <div class="fw-semibold"><?= Html::e($accontoTitolo) ?> <?= Html::e($annoIva) ?></div>
                <?php if ($acconto === null): ?>
                    <div class="small text-body-secondary"><?= Html::e($accontoNonCalcolato) ?> <?= Html::e($campi['scadenza']) ?> <?= Html::data($scadenzaAcconto) ?></div>
                <?php else: ?>
                    <div class="small"><?= Html::e($acconto->metodo->getLabel()) ?>: € <?= Html::e(Importo::euro($acconto->importo)) ?><?= $acconto->isDovuto() ? ' · ' . Html::e($campi['scadenza']) . ' ' . Html::data($scadenzaAcconto) : ' · ' . Html::e($accontoNonDovuto) ?></div>
                <?php endif; ?>
            </div>
            <a href="/acconto-iva/form/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($calcolaAcconto) ?></a>
            <?php if ($acconto !== null): ?>
                <form method="post" action="/acconto-iva/delete/acconto/<?= Html::e($acconto->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
