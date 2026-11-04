<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$urlAnno = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($soggetto->nominativo) ?></h1>
    <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-person-vcard me-1"></i><?= Html::e($schedaAnagrafica) ?></a>
    <?php require __DIR__ . '/../commonParts/selettoreAnno.php'; ?>
</div>
<p class="text-body-secondary"><?= Html::e($soggetto->codiceFiscale) ?> · <?= Html::e($campi['annoImposta']) ?> <?= Html::e($anno) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDichiarazioni) ?></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr><th><?= Html::e($campi['modello']) ?></th><th><?= Html::e($campi['stato']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($modelli as ['modello' => $modello, 'trimestre' => $trimestre, 'motivo' => $motivo, 'dichiarazione' => $dichiarazione, 'catalogo' => $catalogo]): ?>
                    <tr>
                        <td class="fw-semibold"><?= Html::e($trimestre === null ? $modello->getSigla() . ' ' . ($anno + 1) : $modello->getSigla() . ' ' . $trimestre . '° ' . $campi['trimestre'] . ' ' . $anno) ?><div class="small text-body-secondary fw-normal"><?= Html::e($modello->getLabel()) ?></div></td>
                        <td class="small">
                            <?php if ($dichiarazione !== null): ?>
                                <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
                                <?= Html::e($dichiarazione->tipologia->getLabel()) ?>
                                <?php if ($dichiarazione->dataPresentazione !== null): ?> · <?= Html::data($dichiarazione->dataPresentazione) ?><?php endif; ?>
                            <?php elseif ($motivo !== null): ?>
                                <span class="text-body-secondary"><?= Html::e($nonDovuta) ?>: <?= Html::e($motivo) ?></span>
                            <?php elseif ($modello->isGestito() === false): ?>
                                <span class="text-body-secondary"><?= Html::e($modelloInArrivo) ?></span>
                            <?php else: ?>
                                <span class="text-body-secondary"><?= Html::e($daPredisporre) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= Html::data($modello->getScadenza($anno, $trimestre)) ?></td>
                        <td class="text-end">
                            <?php if ($dichiarazione !== null): ?>
                                <a href="/dichiarazione/show/dichiarazione/<?= Html::e($dichiarazione->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['apri']) ?> <i class="bi bi-chevron-right"></i></a>
                            <?php elseif (($motivo === null) && $modello->isGestito() && $catalogo): ?>
                                <form method="post" action="/dichiarazione/crea/soggetto/<?= Html::e($soggetto->id) ?>/modello/<?= Html::e($modello->value) ?>/anno/<?= Html::e($anno) ?><?= $trimestre === null ? '' : '/trimestre/' . Html::e($trimestre) ?>"><?= $campoCsrf ?>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-magic me-1"></i><?= Html::e($azioni['proponi']) ?></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoDichiarazioni) ?></div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVersamenti) ?></div>
    <?php if (count($versamenti) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunVersamento) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush small">
            <?php foreach ($versamenti as $versamento): ?>
                <li class="list-group-item d-flex gap-2">
                    <span class="me-auto"><?= Html::data($versamento->scadenza) ?> · <?= Html::e($versamento->codiceTributo?->codice ?? '') ?> · <?= Html::e($versamento->descrizione ?? '') ?></span>
                    <span class="text-nowrap"><?= Html::e(Importo::euro($versamento->importoDebito)) ?></span>
                    <span class="text-body-secondary"><?= Html::e($versamento->delega === null ? $daVersare : $versamento->delega->stato->getLabel()) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
