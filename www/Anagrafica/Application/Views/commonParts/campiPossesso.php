<?php

use Telonio\Application\HelperClasses\Campo;

/**
 * Campi del possesso. Variabili: $pEntity, $pErrors, $titoli (uno solo:
 * titolo fisso, es. variazione), $utilizzi, $campi; $prefissoPossesso (null
 * o prefisso dei nomi dei campi).
 */
$opzioniPossesso = isset($prefissoPossesso) ? ['prefisso' => $prefissoPossesso] : [];
?>
<?= Campo::selezioneEnum('titolo', $campi['titolo'], $pEntity, $pErrors, $titoli, ['richiesto' => true, 'vuota' => count($titoli) === 1 ? null : $selezionare, 'colonna' => 'col-md-4'] + $opzioniPossesso) ?>
<?= Campo::testo('quota', $campi['quota'], $pEntity, $pErrors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"'] + $opzioniPossesso) ?>
<?= Campo::data('validoDal', $campi['possessoDal'], $pEntity, $pErrors, ['richiesto' => true, 'colonna' => 'col-md-3'] + $opzioniPossesso) ?>
<?= Campo::data('validoAl', $campi['possessoAl'], $pEntity, $pErrors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoValidoAl] + $opzioniPossesso) ?>
<?= Campo::selezioneEnum('utilizzo', $campi['utilizzo'], $pEntity, $pErrors, $utilizzi, ['vuota' => $selezionare, 'colonna' => 'col-md-4'] + $opzioniPossesso) ?>
<?= Campo::testo('note', $campi['note'], $pEntity, $pErrors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"'] + $opzioniPossesso) ?>
