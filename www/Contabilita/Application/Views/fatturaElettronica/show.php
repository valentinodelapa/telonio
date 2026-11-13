<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$daRegistrare = $fattura->stato->value === 'D';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pageTitle) ?> <span class="badge text-bg-<?= Html::e($fattura->stato->getColore()) ?> fs-6"><?= Html::e($fattura->stato->getLabel()) ?></span></h1>
    <?php if ($daRegistrare): ?>
        <a href="/registrazione/fattura/fattura/<?= Html::e($fattura->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-journal-plus me-1"></i><?= Html::e($azioni['registra']) ?></a>
        <form method="post" action="/fattura-elettronica/scarta/fattura/<?= Html::e($fattura->id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['scarta']) ?></button></form>
    <?php elseif ($fattura->stato->value === 'S'): ?>
        <form method="post" action="/fattura-elettronica/scarta/fattura/<?= Html::e($fattura->id) ?>/ripristina/1"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['ripristina']) ?></button></form>
    <?php endif; ?>
    <a href="/fattura-elettronica/scarica/fattura/<?= Html::e($fattura->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['scarica']) ?></a>
    <?php if ($fattura->stato->value !== 'R'): ?>
        <form method="post" action="/fattura-elettronica/delete/fattura/<?= Html::e($fattura->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
</div>
<?php foreach (array_filter(explode("\n", (string) $fattura->avvisi)) as $avviso): ?><div class="alert alert-warning small py-2"><?= Html::e($avviso) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5"><?= Html::e($campi['direzione']) ?></dt><dd class="col-7"><?= Html::e($fattura->direzione->getLabel()) ?></dd>
                    <dt class="col-5"><?= Html::e($campi['tipo']) ?></dt><dd class="col-7"><?= Html::e($fattura->tipoDocumento->value . ' ' . $fattura->tipoDocumento->getLabel()) ?></dd>
                    <dt class="col-5"><?= Html::e($campi['numeroDocumento']) ?></dt><dd class="col-7"><?= Html::e($fattura->numero) ?> del <?= Html::data($fattura->data) ?></dd>
                    <dt class="col-5"><?= Html::e($campi['controparte']) ?></dt>
                    <dd class="col-7"><?= Html::e($fattura->denominazioneControparte) ?><div class="font-monospace"><?= Html::e(($fattura->codicePaese ?? '') . ($fattura->partitaIvaControparte ?? '')) ?> <?= Html::e($fattura->codiceFiscaleControparte ?? '') ?></div>
                        <?php if ($fattura->controparte !== null): ?><a href="/controparte/update/controparte/<?= Html::e($fattura->controparte->id) ?>"><?= Html::e($azioni['apri']) ?></a><?php endif; ?></dd>
                    <dt class="col-5"><?= Html::e($campi['totaleDocumento']) ?></dt><dd class="col-7"><?= $fattura->importoTotale === null ? '' : Html::e(Importo::euro($fattura->importoTotale)) ?></dd>
                    <dt class="col-5"><?= Html::e($campi['file']) ?></dt><dd class="col-7 text-break"><?= Html::e($fattura->nomeFile) ?><?= $fattura->indiceCorpo > 1 ? ' (' . Html::e($fattura->indiceCorpo) . ')' : '' ?></dd>
                    <?php if ($fattura->registrazioneContabile !== null): ?>
                        <dt class="col-5"><?= Html::e($titoloRegistrazione) ?></dt><dd class="col-7"><a href="/registrazione/show/registrazione/<?= Html::e($fattura->registrazioneContabile->id) ?>"><?= Html::e($campi['numero']) ?> <?= Html::e($fattura->registrazioneContabile->numero) ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <?php if ($corpo !== null): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= Html::e($riepiloghi) ?></div>
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th class="text-end"><?= Html::e($campi['aliquota']) ?></th><th><?= Html::e($campi['natura']) ?></th><th class="text-end"><?= Html::e($campi['imponibile']) ?></th><th class="text-end"><?= Html::e($campi['imposta']) ?></th><th><?= Html::e($campi['esigibilita']) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($corpo->riepiloghi as $riepilogo): ?>
                            <tr><td class="text-end"><?= Html::e(number_format($riepilogo['aliquota'], 2, ',', '')) ?></td><td class="small"><?= Html::e($riepilogo['natura']?->value ?? '') ?><?= $riepilogo['riferimentoNormativo'] === null ? '' : '<div class="text-body-secondary">' . Html::e($riepilogo['riferimentoNormativo']) . '</div>' ?></td><td class="text-end"><?= Html::e(Importo::euro($riepilogo['imponibile'])) ?></td><td class="text-end"><?= Html::e(Importo::euro($riepilogo['imposta'])) ?></td><td class="small"><?= Html::e($riepilogo['esigibilita']->value) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($corpo->descrizioni) > 0 || $corpo->causale !== null): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><?= Html::e($descrizioni) ?></div>
                    <ul class="list-group list-group-flush small">
                        <?php if ($corpo->causale !== null): ?><li class="list-group-item"><?= Html::e($corpo->causale) ?></li><?php endif; ?>
                        <?php foreach ($corpo->descrizioni as $descrizione): ?><li class="list-group-item"><?= Html::e($descrizione) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (count($corpo->pagamenti) > 0): ?>
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white fw-semibold"><?= Html::e($titoloPagamenti) ?></div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($corpo->pagamenti as $pagamento): ?>
                            <li class="list-group-item d-flex gap-2"><span class="me-auto"><?= $pagamento['scadenza'] === null ? Html::e($senzaScadenza) : Html::data($pagamento['scadenza']) ?><?= $pagamento['modalita'] === null ? '' : ' · ' . Html::e($pagamento['modalita']->getLabelBreve()) ?><?= $pagamento['iban'] === null ? '' : ' · <span class="font-monospace">' . Html::e($pagamento['iban']) . '</span>' ?></span><span><?= Html::e(number_format($pagamento['importo'], 2, ',', '.')) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
