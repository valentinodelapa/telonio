<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione/show/dichiarazione/' . $dichiarazione->id;
// dopo un invio con errori il sottoscrittore è quello inviato, non quello dell'entità
$dichiaranteScelto = isset($formEntity->dichiarante) ? $formEntity->dichiarante : null;
$dichiaranteCorrente = $dichiaranteScelto === null ? null : $dichiaranteScelto->nominativo . ' - ' . $dichiaranteScelto->codiceFiscale;
$vistoScelto = isset($formEntity->vistoProfessionista) ? $formEntity->vistoProfessionista : null;
$vistoCorrente = $vistoScelto === null ? null : $vistoScelto->nominativo . ' - ' . $vistoScelto->codiceFiscale;
$cafScelto = isset($formEntity->vistoCaf) ? $formEntity->vistoCaf : null;
$cafCorrente = $cafScelto === null ? null : $cafScelto->nominativo . ' - ' . $cafScelto->codiceFiscale;
$responsabileScelto = isset($formEntity->vistoResponsabileCaf) ? $formEntity->vistoResponsabileCaf : null;
$responsabileCorrente = $responsabileScelto === null ? null : $responsabileScelto->nominativo . ' - ' . $responsabileScelto->codiceFiscale;
$intermediariScelte = [];
foreach ($intermediari as $intermediario) {
    $intermediariScelte[$intermediario->id] = $intermediario->soggetto->nominativo . ' - ' . $intermediario->soggetto->codiceFiscale;
}
$natureScelte = [];
foreach ($nature as $codice => $descrizione) {
    $natureScelte[$codice] = $codice . ' - ' . $descrizione;
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($dichiarazione->modello->getLabel()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipologia', $campi['tipologia'], $formEntity, $errors, $tipologie, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?php if ($soggetto->isPersonaFisica() === false): ?>
                <?= Campo::selezione('naturaGiuridica', $campi['naturaGiuridica'], $formEntity, $errors, $natureScelte, ['colonna' => 'col-md-8', 'vuota' => $nessuna]) ?>
            <?php endif; ?>
            <div class="w-100 m-0"></div>
            <?= Campo::autocompleta('dichiarante', $campi['dichiarante'], $formEntity, $errors, '/soggetto/cerca', $dichiaranteCorrente, ['colonna' => 'col-md-6', 'aiuto' => $aiutoDichiarante]) ?>
            <?= Campo::selezioneEnum('codiceCarica', $campi['codiceCarica'], $formEntity, $errors, $codiciCarica, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::selezione('intermediario', $campi['intermediario'], $formEntity, $errors, $intermediariScelte, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::selezioneEnum('impegno', $campi['impegno'], $formEntity, $errors, $impegni, ['colonna' => 'col-md-3', 'vuota' => $nessuno]) ?>
            <?= Campo::data('dataImpegno', $campi['dataImpegno'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::autocompleta('vistoProfessionista', $campi['vistoProfessionista'], $formEntity, $errors, '/soggetto/cerca', $vistoCorrente, ['colonna' => 'col-md-6', 'aiuto' => $aiutoVisto]) ?>
            <?= Campo::spunta('esoneroVisto', $campi['esoneroVisto'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => $aiutoEsonero]) ?>
            <?= Campo::spunta('attestazionePatrimoniale', $campi['attestazionePatrimoniale'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => $aiutoAttestazione]) ?>
            <?= Campo::spunta('verificaAccertamenti', $campi['verificaAccertamenti'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => $aiutoVerificaAccertamenti]) ?>
            <div class="w-100 m-0"></div>
            <?= Campo::spunta('adempimentoCollaborativo', $campi['adempimentoCollaborativo'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('concordatoBiennale', $campi['concordatoBiennale'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::autocompleta('vistoCaf', $campi['vistoCaf'], $formEntity, $errors, '/soggetto/cerca', $cafCorrente, ['colonna' => 'col-md-6', 'aiuto' => $aiutoVistoCaf]) ?>
            <?= Campo::autocompleta('vistoResponsabileCaf', $campi['vistoResponsabileCaf'], $formEntity, $errors, '/soggetto/cerca', $responsabileCorrente, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('flagConferma', $campi['flagConferma'], $formEntity, $errors, ['colonna' => 'col-12', 'aiuto' => $aiutoConferma]) ?>
            <?= Campo::areaTesto('annotazioni', $campi['annotazioni'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 3, 'attributi' => 'maxlength="500"']) ?>
        </div>
    </div>
    <?php if ($dichiarazione->modello->isRedditi()): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header"><?= Html::e($sezioneScelte) ?></div>
            <div class="card-body row g-3">
                <p class="small text-body-secondary mb-0"><?= Html::e($aiutoScelte) ?></p>
                <?= Campo::selezioneEnum('ottoPerMille', $campi['ottoPerMille'], $formEntity, $errors, $istituzioniOttoPerMille, ['colonna' => 'col-md-8', 'vuota' => $sceltaNonEffettuata]) ?>
                <?= Campo::selezione('finalitaStato', $campi['finalitaStato'], $formEntity, $errors, $finalitaStato, ['colonna' => 'col-md-4', 'vuota' => $nessuna]) ?>
                <?= Campo::selezioneEnum('cinquePerMille', $campi['cinquePerMille'], $formEntity, $errors, $finalitaCinquePerMille, ['colonna' => 'col-md-8', 'vuota' => $sceltaNonEffettuata]) ?>
                <?= Campo::testo('beneficiarioCinquePerMille', $campi['beneficiarioCinquePerMille'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="11" inputmode="numeric"']) ?>
                <?= Campo::selezione('duePerMille', $campi['duePerMille'], $formEntity, $errors, $partitiDuePerMille, ['colonna' => 'col-md-8', 'vuota' => $sceltaNonEffettuata]) ?>
            </div>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
