<?php

use Anagrafica\Application\Enumerations\TipoCatasto;
use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;

/**
 * Campi dei dati catastali. Variabili: $dcEntity, $dcErrors, $tipoCatasto,
 * $categorie, $causali (una sola: campo nascosto), $campi; $mostraValidoAl
 * (default true); $decorrenzaDalPossesso
 * (default false: decorrenza facoltativa, vuota = inizio del possesso) e
 * $decorrenzaVuota (primo invio: campo non precompilato).
 */
$decorrenzaDalPossesso ??= false;
?>
<?= Campo::data('validoDal', $campi['validoDalCatasto'], ($decorrenzaVuota ?? false) ? null : $dcEntity, $dcErrors,
        $decorrenzaDalPossesso ? ['colonna' => 'col-md-3', 'aiuto' => $aiutoDecorrenzaDalPossesso] : ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
<?php if ($mostraValidoAl ?? true): ?>
    <?= Campo::data('validoAl', $campi['validoAl'], $dcEntity, $dcErrors, ['colonna' => 'col-md-3']) ?>
<?php endif; ?>
<?php if (count($causali) === 1): ?>
    <input type="hidden" name="causale" value="<?= Html::e($causali[0]->value) ?>">
<?php else: ?>
    <?= Campo::selezioneEnum('causale', $campi['causale'], $dcEntity, $dcErrors, $causali, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6', 'aiuto' => $aiutoCausale]) ?>
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
