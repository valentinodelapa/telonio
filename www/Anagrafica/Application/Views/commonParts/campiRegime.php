<?php

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Application\HelperClasses\Campo;

/**
 * Regime fiscale nei form dei dati fiscali: $origineRegime (entità o dati
 * inviati), $erroriRegime e $soggetto. Si propongono i regimi del tipo di
 * soggetto; la coerenza con l'attività è verificata dal form.
 */
$regimiAmmessi = RegimeFiscale::perSoggetto($soggetto->tipoSoggetto, TipoAttivita::impresaIndividuale, true);
?>
<div class="w-100 m-0"></div>
<?= Campo::selezioneEnum('regimeFiscale', $campi['regimeFiscale'], $origineRegime, $erroriRegime, $regimiAmmessi, ['vuota' => $nessunRegime, 'colonna' => 'col-md-6', 'aiuto' => $aiutoRegime]) ?>
<?= Campo::selezioneEnum('periodicitaIva', $campi['periodicitaIva'], $origineRegime, $erroriRegime, PeriodicitaIva::cases(), ['vuota' => $nessunaPeriodicita, 'colonna' => 'col-md-3', 'aiuto' => $aiutoPeriodicita]) ?>
<div class="w-100 m-0"></div>
<?= Campo::spunta('soggettoIsa', $campi['soggettoIsa'], $origineRegime, $erroriRegime, ['colonna' => 'col-md-6']) ?>
<?= Campo::spunta('sostitutoImposta', $campi['sostitutoImposta'], $origineRegime, $erroriRegime, ['colonna' => 'col-md-6']) ?>
<?php if ($soggetto->tipoSoggetto === TipoSoggetto::societaCapitali): ?>
    <?= Campo::spunta('trasparenzaFiscale', $campi['trasparenzaFiscale'], $origineRegime, $erroriRegime, ['colonna' => 'col-md-6']) ?>
<?php endif; ?>
