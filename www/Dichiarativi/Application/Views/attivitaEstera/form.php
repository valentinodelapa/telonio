<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$stati = [];
foreach ($statiEsteri as $codice => $denominazione) {
    $stati[$codice] = $denominazione . ' (' . $codice . ')';
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['annoImposta']) ?> <?= Html::e($anno) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoAttivitaEstera) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneBene) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('codiceInvestimento', $campi['codiceInvestimento'], $formEntity, $errors, $codiciInvestimento, ['richiesto' => true, 'colonna' => 'col-md-8']) ?>
            <?= Campo::selezione('codiceStatoEstero', $campi['codiceStatoEstero'], $formEntity, $errors, $stati, ['colonna' => 'col-md-4', 'vuota' => $nessuno]) ?>
            <?= Campo::testo('descrizione', $campi['descrizioneAttivita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::testo('intermediario', $campi['intermediario'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="100"', 'aiuto' => $aiutoIntermediario]) ?>
            <?= Campo::selezione('titoloPossesso', $campi['titoloPossesso'], $formEntity, $errors, $titoliPossesso, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::testo('quota', $campi['quotaPossesso'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::selezione('tipoContribuente', $campi['tipoContribuente'], $formEntity, $errors, $tipiContribuente, ['colonna' => 'col-md-3', 'vuota' => $nessuno]) ?>
            <?= Campo::data('dataInizio', $campi['dataInizioDetenzione'], $formEntity, $errors, ['colonna' => 'col-md-2', 'aiuto' => $aiutoDate]) ?>
            <?= Campo::data('dataFine', $campi['dataFineDetenzione'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneValori) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezione('criterioValore', $campi['criterioValore'], $formEntity, $errors, $criteriValore, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::testo('valoreIniziale', $campi['valoreIniziale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('valoreFinale', $campi['valoreFinale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoValoreFinale]) ?>
            <?= Campo::testo('valoreMassimo', $campi['valoreMassimo'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoValoreMassimo]) ?>
            <?= Campo::testo('creditoImposta', $campi['creditoImpostaEstera'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoCreditoImposta]) ?>
            <?= Campo::testo('mesiAbitazionePrincipale', $campi['mesiAbitazionePrincipale'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric" maxlength="2"', 'aiuto' => $aiutoMesiAbitazione]) ?>
            <?= Campo::selezione('codiceQuadri', $campi['codiceQuadri'], $formEntity, $errors, $codiciQuadri, ['colonna' => 'col-md-3', 'vuota' => $proposto]) ?>
            <?= Campo::spunta('soloMonitoraggio', $campi['soloMonitoraggio'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneProventi) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezione('tipoProventi', $campi['tipoProventi'], $formEntity, $errors, $tipiProventi, ['colonna' => 'col-md-4', 'vuota' => $nessuno]) ?>
            <?= Campo::testo('proventi', $campi['proventi'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoProventi]) ?>
            <?= Campo::testo('impostaEstera', $campi['impostaEstera'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoImpostaEstera]) ?>
            <?= Campo::spunta('tassazioneOrdinaria', $campi['tassazioneOrdinaria'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTitolari) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('quotaPartecipazione', $campi['quotaPartecipazione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('codiceFiscaleEntita', $campi['codiceFiscaleEntita'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="25"']) ?>
            <?= Campo::testo('cointestatario1', $campi['cointestatario1'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
            <?= Campo::testo('cointestatario2', $campi['cointestatario2'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
            <?= Campo::spunta('piuCointestatari', $campi['piuCointestatari'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
