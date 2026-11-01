<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <a href="/liquidazione-iva/show/liquidazione/<?= Html::e($liquidazione->id) ?>/stampa/1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['versioneStampa']) ?></a>
    <form method="post" action="/liquidazione-iva/elabora/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($liquidazione->anno) ?>/periodo/<?= Html::e($liquidazione->periodo) ?>">
        <?= $campoCsrf ?>
        <input type="hidden" name="creditoAnnoPrecedente" value="<?= Html::e(number_format($liquidazione->creditoAnnoPrecedente, 2, '.', '')) ?>">
        <?php if ($liquidazione->registrazioneContabile !== null): ?><input type="hidden" name="giroconto" value="1"><?php endif; ?>
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i><?= Html::e($azioni['rielabora']) ?></button>
    </form>
    <form method="post" action="/liquidazione-iva/delete/liquidazione/<?= Html::e($liquidazione->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
</div>
<?php if ($registriVariati): ?><div class="alert alert-warning small py-2"><?= Html::e($variata) ?></div><?php endif; ?>
<?php foreach (array_filter(explode("\n", (string) $liquidazione->avvisi)) as $avviso): ?><div class="alert alert-info small py-2"><?= Html::e($avviso) ?></div><?php endforeach; ?>
<?php foreach (array_filter(explode("\n", (string) $liquidazione->differenze)) as $differenza): ?><div class="alert alert-warning small py-2"><?= Html::e($differenza) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <?php $classeTabella = 'table table-sm mb-0'; $classeNumero = 'text-end text-nowrap'; $classeTotale = 'table-light fw-semibold'; require __DIR__ . '/prospetto.php'; ?>
            <div class="card-footer bg-white small text-body-secondary"><?= Html::data($liquidazione->dataElaborazione, 'd/m/Y H:i') ?></div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloVersamenti) ?></div>
            <?php if (count($versamenti) === 0): ?>
                <div class="card-body small text-body-secondary"><?= Html::e($nessunVersamento) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($versamenti as $versamento): ?>
                        <li class="list-group-item d-flex"><span class="me-auto"><?= Html::e($versamento->codiceTributo?->codice ?? '') ?> · <?= Html::e($versamento->descrizione ?? '') ?> · <?= Html::data($versamento->scadenza) ?></span><span><?= Html::e(Importo::euro($versamento->importoDebito)) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-footer bg-white"><a href="/contribuente/show/soggetto/<?= Html::e($soggetto->id) ?>" class="small"><?= Html::e($azioni['deleghe']) ?></a></div>
            <?php endif; ?>
        </div>
        <?php if ($liquidazione->registrazioneContabile !== null): ?>
            <div class="mt-3 small"><i class="bi bi-journal-text me-1"></i><?= Html::e($titoloGiroconto) ?>: <a href="/registrazione/show/registrazione/<?= Html::e($liquidazione->registrazioneContabile->id) ?>"><?= Html::e($campi['numero']) ?> <?= Html::e($liquidazione->registrazioneContabile->numero) ?></a></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-7">
        <?php if (($ventilazione !== null) && ($ventilazione->corrispettivi != 0)): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= Html::e($titoloVentilazione) ?></div>
                <div class="table-responsive"><?php $classeTabella = 'table table-sm mb-0'; $classeNumero = 'text-end text-nowrap'; $classeTotale = 'table-light fw-semibold'; require __DIR__ . '/ventilazione.php'; ?></div>
                <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoVentilazione) ?></div>
            </div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titoloRiepilogo) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th><?= Html::e($campi['registroIva']) ?></th><th><?= Html::e($campi['codiceIva']) ?></th><th class="text-end"><?= Html::e($campi['imponibile']) ?></th><th class="text-end"><?= Html::e($campi['imposta']) ?></th><th class="text-end"><?= Html::e($campi['ivaEsigibile']) ?></th><th class="text-end"><?= Html::e($campi['ivaDetraibile']) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($riepilogo as $voce): ?>
                            <tr><td class="small"><?= Html::e($voce['registro']) ?></td><td class="small"><?= Html::e($voce['codice']) ?></td><td class="text-end"><?= Html::e(Importo::euro($voce['imponibile'])) ?></td><td class="text-end"><?= Html::e(Importo::euro($voce['imposta'])) ?></td><td class="text-end"><?= Html::e(Importo::euro($voce['esigibile'], true)) ?></td><td class="text-end"><?= Html::e(Importo::euro($voce['detraibile'], true)) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
