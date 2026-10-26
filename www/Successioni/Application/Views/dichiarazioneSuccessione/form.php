<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Successioni\Application\Entities\DichiarazioneSuccessione;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id;
$etichettaSoggetto = fn(?object $soggetto): ?string => $soggetto === null ? null : $soggetto->nominativo . ' - ' . $soggetto->codiceFiscale;
$intermediariScelte = [];
foreach ($intermediari as $intermediario) {
    $intermediariScelte[$intermediario->id] = $intermediario->soggetto->nominativo . ' - ' . $intermediario->soggetto->codiceFiscale;
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->getTitolo()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTipo) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoDichiarazione', $campi['tipoDichiarazione'], $formEntity, $errors, $tipiDichiarazione, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('annoPrima', $campi['annoPrima'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="4"']) ?>
            <?= Campo::testo('volumePrima', $campi['volumePrima'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="5"']) ?>
            <?= Campo::testo('numeroPrima', $campi['numeroPrima'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
            <?= Campo::spunta('devoluzioneLegge', $campi['devoluzioneLegge'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('devoluzioneTestamento', $campi['devoluzioneTestamento'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('leggeEstera', $campi['leggeEstera'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('beneficioInventario', $campi['beneficioInventario'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::testo('testamentoPubblicoUfficiale', $campi['testamentoPubblicoUfficiale'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="50"']) ?>
            <?= Campo::data('testamentoDataPubblicazione', $campi['testamentoDataPubblicazione'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('testamentoEstero', $campi['testamentoEstero'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::autocompleta('testamentoUfficio', $campi['testamentoUfficio'], $formEntity, $errors, '/tabelle-successioni/uffici', $formEntity->testamentoUfficio?->getEtichetta(), ['colonna' => 'col-md-5']) ?>
            <?= Campo::testo('testamentoSerie', $campi['testamentoSerie'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="2"']) ?>
            <?= Campo::testo('testamentoNumero', $campi['testamentoNumero'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
            <?= Campo::testo('testamentoSottonumero', $campi['testamentoSottonumero'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="3"']) ?>
            <?= Campo::data('testamentoDataRegistrazione', $campi['testamentoDataRegistrazione'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDefunto) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('statoCivile', $campi['statoCivile'], $formEntity, $errors, $statiCivili, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
            <?= Campo::selezioneEnum('residenzaEstera', $campi['residenzaEstera'], $formEntity, $errors, $residenzeEstere, ['vuota' => $nessuna, 'colonna' => 'col-md-6']) ?>
            <?= Campo::autocompleta('comuneDecesso', $campi['comuneDecesso'], $formEntity, $errors, '/tabelle/comuni', $formEntity->comuneDecesso?->getEtichetta(), ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('statoDecesso', $campi['statoDecesso'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="50"']) ?>
            <?= Campo::spunta('separazioneColpa', $campi['separazioneColpa'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('eventiEccezionali', $campi['eventiEccezionali'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::data('decorrenzaTermine', $campi['decorrenzaTermine'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDichiarante) ?></div>
        <div class="card-body row g-3">
            <?= Campo::autocompleta('presentatore', $campi['presentatore'], $formEntity, $errors, '/soggetto/cerca', $etichettaSoggetto($formEntity->presentatore), ['richiesto' => true, 'colonna' => 'col-md-6', 'aiuto' => $aiutoPresentatore]) ?>
            <?= Campo::selezioneEnum('codiceCarica', $campi['codiceCarica'], $formEntity, $errors, $cariche, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
            <?= Campo::autocompleta('rappresentato', $campi['rappresentato'], $formEntity, $errors, '/soggetto/cerca', $etichettaSoggetto($formEntity->rappresentato), ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('telefono', $campi['telefono'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::testo('email', $campi['email'], $formEntity, $errors, ['tipo' => 'email', 'colonna' => 'col-md-3', 'attributi' => 'maxlength="100"']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezionePrimaCasa) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezione('primaCasaOpzione', $campi['primaCasaOpzione'], $formEntity, $errors, DichiarazioneSuccessione::OPZIONI_PRIMA_CASA, ['vuota' => $nessuno, 'colonna' => 'col-md-6']) ?>
            <?= Campo::selezione('primaCasaAltraCasa', $campi['primaCasaAltraCasa'], $formEntity, $errors, DichiarazioneSuccessione::ALTRA_CASA, ['vuota' => $nessuno, 'colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('primaCasaNessunaAltraComune', $campi['primaCasaNessunaAltraComune'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('primaCasaTrasferimentoPrima', $campi['primaCasaTrasferimentoPrima'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneAgevolazioni) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('vincoloProtocollo', $campi['vincoloProtocollo'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="30"']) ?>
            <?= Campo::data('vincoloData', $campi['vincoloData'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <div class="w-100 m-0"></div>
            <?= Campo::testo('precedenteUfficio', $campi['precedenteUfficio'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="4"']) ?>
            <?= Campo::testo('precedenteAnno', $campi['precedenteAnno'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="4"']) ?>
            <?= Campo::testo('precedenteVolume', $campi['precedenteVolume'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="5"']) ?>
            <?= Campo::testo('precedenteNumero', $campi['precedenteNumero'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTrasmissione) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezione('intermediario', $campi['intermediario'], $formEntity, $errors, $intermediariScelte, ['vuota' => $nessuno, 'colonna' => 'col-md-6']) ?>
            <?= Campo::selezioneEnum('impegno', $campi['impegno'], $formEntity, $errors, $impegni, ['vuota' => $nessuno, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('dataImpegno', $campi['dataImpegno'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('senzaVolture', $campi['senzaVolture'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('copiaConforme', $campi['copiaConforme'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::data('dataDichiarazione', $campi['dataDichiarazione'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::areaTesto('annotazioni', $campi['annotazioni'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2, 'attributi' => 'maxlength="1000"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
