<?php

use Anagrafica\Application\Enumerations\TipoCatasto;
use CommSuite\Application\HelperClasses\Campo;

/**
 * Campi dei dati catastali. Variabili: $dcEntity, $dcErrors, $tipoCatasto,
 * $categorie, $campi; $mostraValidoAl (default true).
 */
?>
<?= Campo::data('validoDal', $campi['validoDalCatasto'], $dcEntity, $dcErrors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
<?php if ($mostraValidoAl ?? true): ?>
    <?= Campo::data('validoAl', $campi['validoAl'], $dcEntity, $dcErrors, ['colonna' => 'col-md-3']) ?>
<?php endif; ?>
<div class="w-100 m-0"></div>
<?php if ($tipoCatasto === TipoCatasto::fabbricati): ?>
    <?= Campo::selezioneEnum('categoria', $campi['categoria'], $dcEntity, $dcErrors, $categorie, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
    <?= Campo::testo('classe', $campi['classe'], $dcEntity, $dcErrors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
    <?= Campo::testo('consistenza', $campi['consistenza'], $dcEntity, $dcErrors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
    <?= Campo::testo('superficie', $campi['superficieCatastale'], $dcEntity, $dcErrors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
    <?= Campo::testo('rendita', $campi['rendita'], $dcEntity, $dcErrors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoRendita]) ?>
<?php else: ?>
    <?= Campo::testo('qualitaColtura', $campi['qualitaColtura'], $dcEntity, $dcErrors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="50" placeholder="SEMINATIVO"']) ?>
    <?= Campo::testo('classe', $campi['classe'], $dcEntity, $dcErrors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
    <?= Campo::testo('superficie', $campi['superficieTerreno'], $dcEntity, $dcErrors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
    <?= Campo::testo('redditoDominicale', $campi['redditoDominicale'], $dcEntity, $dcErrors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
    <?= Campo::testo('redditoAgrario', $campi['redditoAgrario'], $dcEntity, $dcErrors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
<?php endif; ?>
