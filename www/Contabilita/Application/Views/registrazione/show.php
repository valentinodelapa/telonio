<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$segno = $registrazione->causale->getSegno();
$dare = $avere = 0.0;

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($campi['numero']) ?> <?= Html::e($registrazione->numero) ?> · <?= Html::e($registrazione->causale->getLabel()) ?></h1>
    <?php if ($autofattura): ?>
        <a href="/registrazione/autofattura/registrazione/<?= Html::e($registrazione->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-xml me-1"></i><?= Html::e($campi['autofattura']) ?></a>
    <?php endif; ?>
    <?php if ($registrazione->isModificabile() && (($registrazione->isGenerata() === false) || ($registrazione->origine === 'Corrispettivi'))): ?>
        <a href="/registrazione/update/registrazione/<?= Html::e($registrazione->id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
    <?php endif; ?>
    <?php if ($registrazione->isModificabile()): ?>
        <form method="post" action="/registrazione/delete/registrazione/<?= Html::e($registrazione->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
    <?php endif; ?>
</div>
<?php if ($registrazione->isGenerata()): ?><div class="alert alert-info small py-2"><?= Html::e($registrazione->origine === 'Corrispettivi' ? $generataCorrispettivi : $generataDa) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <dl class="row mb-0 small">
            <dt class="col-sm-3"><?= Html::e($campi['dataRegistrazione']) ?></dt><dd class="col-sm-3"><?= Html::data($registrazione->dataRegistrazione) ?></dd>
            <dt class="col-sm-3"><?= Html::e($campi['descrizione']) ?></dt><dd class="col-sm-3"><?= Html::e($registrazione->descrizione ?? '') ?></dd>
            <?php if ($registrazione->controparte !== null): ?>
                <dt class="col-sm-3"><?= Html::e($campi['controparte']) ?></dt><dd class="col-sm-9"><a href="/controparte/update/controparte/<?= Html::e($registrazione->controparte->id) ?>"><?= Html::e($registrazione->controparte->getEtichetta()) ?></a></dd>
            <?php endif; ?>
            <?php if ($registrazione->numeroDocumento !== null): ?>
                <dt class="col-sm-3"><?= Html::e($campi['numeroDocumento']) ?></dt><dd class="col-sm-3"><?= Html::e($registrazione->numeroDocumento) ?></dd>
                <dt class="col-sm-3"><?= Html::e($campi['dataDocumento']) ?></dt><dd class="col-sm-3"><?= Html::data($registrazione->dataDocumento) ?></dd>
            <?php endif; ?>
            <?php if ($registrazione->registroIva !== null): ?>
                <dt class="col-sm-3"><?= Html::e($campi['registroIva']) ?></dt><dd class="col-sm-3"><?= Html::e($registrazione->registroIva->getEtichetta()) ?> · <?= Html::e($campi['protocolloIva']) ?> <?= Html::e($registrazione->protocolloIva) ?></dd>
                <dt class="col-sm-3"><?= Html::e($campi['dataCompetenzaIva']) ?></dt><dd class="col-sm-3"><?= Html::data($registrazione->dataCompetenzaIva) ?> · <?= Html::e($registrazione->esigibilita->getLabel($registrazione->dataCompetenzaIva)) ?></dd>
            <?php endif; ?>
            <?php if ($registrazione->totaleDocumento !== null): ?>
                <dt class="col-sm-3"><?= Html::e($campi['totaleDocumento']) ?></dt><dd class="col-sm-3"><?= Html::e(Importo::euro($registrazione->totaleDocumento)) ?></dd>
            <?php endif; ?>
            <?php if ($fattura !== null): ?>
                <dt class="col-sm-3"><?= Html::e($daFattura) ?></dt><dd class="col-sm-3"><a href="/fattura-elettronica/show/fattura/<?= Html::e($fattura->id) ?>"><?= Html::e($fattura->nomeFile) ?></a></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if (count($righeIva) > 0): ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['codiceIva']) ?></th><th class="text-end"><?= Html::e($campi['imponibile']) ?></th><th class="text-end"><?= Html::e($campi['imposta']) ?></th><th class="text-end"><?= Html::e($campi['detraibilita']) ?></th><th><?= Html::e($campi['conto']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($righeIva as $riga): ?>
                        <tr>
                            <td><?= Html::e($riga->codiceIva->getEtichetta()) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($riga->imponibile)) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($riga->imposta)) ?></td>
                            <td class="text-end"><?= Html::e(number_format($riga->detraibilita, 0, ',', '')) ?>%</td>
                            <td class="small"><?= Html::e($riga->conto?->getEtichetta() ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th><?= Html::e($campi['conto']) ?></th><th><?= Html::e($campi['controparte']) ?></th><th class="text-end"><?= Html::e($campi['dare']) ?></th><th class="text-end"><?= Html::e($campi['avere']) ?></th><th><?= Html::e($campi['descrizione']) ?></th></tr></thead>
            <tbody>
                <?php foreach ($righe as $riga): ?>
                    <?php $dare += $riga->dare; $avere += $riga->avere; ?>
                    <tr>
                        <td><a href="/stampa-contabile/scheda/soggetto/<?= Html::e($soggetto->id) ?>/conto/<?= Html::e($riga->conto->id) ?>/anno/<?= Html::e($registrazione->esercizioContabile->anno) ?>" class="text-decoration-none"><?= Html::e($riga->conto->getEtichetta()) ?></a></td>
                        <td class="small"><?= Html::e($riga->controparte?->denominazione ?? '') ?></td>
                        <td class="text-end"><?= Html::e(Importo::euro($riga->dare, true)) ?></td>
                        <td class="text-end"><?= Html::e(Importo::euro($riga->avere, true)) ?></td>
                        <td class="small"><?= Html::e($riga->descrizione ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light"><tr><th colspan="2" class="text-end"><?= Html::e($totale) ?></th><th class="text-end"><?= Html::e(Importo::euro($dare)) ?></th><th class="text-end"><?= Html::e(Importo::euro($avere)) ?></th><th></th></tr></tfoot>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
