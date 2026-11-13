<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/controparte/index/soggetto/' . $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$sceltaContropartite = [];
foreach ($contropartite as $conto) {
    $sceltaContropartite[$conto->id] = $conto->getEtichetta();
}
$sceltaCodici = [];
foreach ($codiciIva as $codiceIva) {
    $sceltaCodici[$codiceIva->id] = $codiceIva->getEtichetta();
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::testo('denominazione', $campi['denominazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('cognome', $campi['cognome'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="60"']) ?>
            <?= Campo::testo('nome', $campi['nome'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="60"']) ?>
            <?= Campo::selezione('nazione', $campi['nazione'], $formEntity, $errors, $nazioni, ['colonna' => 'col-md-4', 'vuota' => 'Italia']) ?>
            <?= Campo::testo('partitaIva', $campi['partitaIva'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="28"']) ?>
            <?= Campo::testo('codiceFiscale', $campi['codiceFiscale'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="16" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('indirizzo', $campi['indirizzo'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('cap', $campi['cap'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('comune', $campi['comune'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="60"']) ?>
            <?= Campo::testo('provincia', $campi['provincia'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="2" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('pec', $campi['pec'], $formEntity, $errors, ['colonna' => 'col-md-6', 'tipo' => 'email']) ?>
            <?= Campo::testo('codiceDestinatario', $campi['codiceDestinatario'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" style="text-transform: uppercase"']) ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::spunta('cliente', $campi['cliente'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::spunta('fornitore', $campi['fornitore'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::spunta('agricoltoreEsonerato', $campi['agricoltoreEsonerato'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('pubblicaAmministrazione', $campi['pubblicaAmministrazione'], $formEntity, $errors, ['colonna' => 'col-md-8']) ?>
            <?= Campo::selezione('contoContropartita', $campi['contoContropartita'], $formEntity, $errors, $sceltaContropartite, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::selezione('codiceIva', $campi['codiceIva'], $formEntity, $errors, $sceltaCodici, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::testo('giorniPagamento', $campi['giorniPagamento'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="100" placeholder="30,60,90"', 'aiuto' => $aiutoGiorniPagamento]) ?>
            <?= Campo::spunta('fineMesePagamento', $campi['fineMesePagamento'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::selezioneEnum('modalitaPagamento', $campi['modalitaPagamento'], $formEntity, $errors, $modalitaPagamento, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
            <?php if (isset($controparte->id)): ?>
                <div class="col-12 small">
                    <?php foreach (['cliente' => $controparte->contoCliente, 'fornitore' => $controparte->contoFornitore] as $ruolo => $conto): ?>
                        <?php if ($conto !== null): ?><span class="me-3"><?= Html::e($campi[$ruolo]) ?>: <a href="/stampa-contabile/scheda/soggetto/<?= Html::e($soggetto->id) ?>/conto/<?= Html::e($conto->id) ?>" class="font-monospace"><?= Html::e($conto->codice) ?></a></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($controparte->id)): ?>
    <form method="post" action="/controparte/delete/controparte/<?= Html::e($controparte->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
