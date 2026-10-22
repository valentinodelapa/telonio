<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Successioni\Application\Enumerations\QuadroBene;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/attivo';
$quadro = $bene->quadro;
$nature = ['T' => 'T - Terreno', 'LE' => 'LE - Lotto edificabile', 'R' => 'R - Fabbricato rurale', 'C' => 'C - Fabbricato in corso di costruzione', 'EU' => 'EU - Ente urbano', 'F' => 'F - Bene futuro', 'PF' => 'PF - Porzione di bene futuro'];

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->getTitolo()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?php if ($quadro->isImmobile()): ?>
                <?php if (count($immobili) === 0): ?><div class="col-12 small text-warning"><?= Html::e($nessunImmobile) ?></div><?php endif; ?>
                <?= Campo::selezione('immobile', $campi['immobile'], $formEntity, $errors, $immobili, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-12', 'aiuto' => $aiutoImmobile]) ?>
            <?php elseif ($quadro === QuadroBene::aziende): ?>
                <?= Campo::testo('descrizione', $campi['denominazioneAzienda'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="150"']) ?>
                <?= Campo::testo('codiceFiscaleEmittente', $campi['codiceFiscaleAzienda'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="16"']) ?>
                <?= Campo::data('dataInventario', $campi['dataInventario'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
                <?= Campo::data('dataDeposito', $campi['dataDeposito'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
                <?= Campo::testo('cameraCommercio', $campi['cameraCommercio'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="35"']) ?>
            <?php elseif ($quadro === QuadroBene::aeromobili): ?>
                <?= Campo::testo('marcaModello', $campi['marcaModello'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="45"']) ?>
                <?= Campo::testo('nazionalita', $campi['nazionalita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="20"']) ?>
                <?= Campo::testo('annoCostruzione', $campi['annoCostruzione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
                <?= Campo::testo('annoIscrizione', $campi['annoImmatricolazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
                <?= Campo::testo('numeroIscrizione', $campi['numeroImmatricolazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="10"']) ?>
            <?php elseif ($quadro === QuadroBene::navi): ?>
                <?= Campo::selezioneEnum('tipoUnita', $campi['tipoUnita'], $formEntity, $errors, $tipiUnita, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']) ?>
                <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="250"']) ?>
                <?= Campo::testo('nazionalita', $campi['nazionalita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="20"']) ?>
                <?= Campo::testo('ufficioIscrizione', $campi['ufficioIscrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
                <?= Campo::testo('annoIscrizione', $campi['annoIscrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
                <?= Campo::testo('numeroIscrizione', $campi['numeroIscrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
                <?= Campo::testo('annoCostruzione', $campi['annoCostruzione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
                <?= Campo::testo('dimensione', $campi['dimensione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"', 'aiuto' => $aiutoDimensione]) ?>
            <?php else: ?>
                <?php if ($quadro === QuadroBene::altriBeni): ?>
                    <?= Campo::selezioneEnum('tipoCespite', $campi['tipoCespite'], $formEntity, $errors, $tipiCespite, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
                <?php else: ?>
                    <?= Campo::selezioneEnum('tipoTitolo', $campi['tipoTitolo'], $formEntity, $errors, $tipiTitolo, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
                    <?= Campo::testo('codiceFiscaleEmittente', $campi['codiceFiscaleEmittente'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
                    <?= Campo::testo('codiceTitolo', $campi['codiceTitolo'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="12"']) ?>
                    <?= Campo::testo('quantita', $campi['quantita'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"']) ?>
                    <?= Campo::selezioneEnum('tipoDocumento', $campi['tipoDocumento'], $formEntity, $errors, $tipiDocumento, ['vuota' => $nessuno, 'colonna' => 'col-md-3']) ?>
                    <?= Campo::data('dataDocumento', $campi['dataDocumento'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
                <?php endif; ?>
                <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-12', 'attributi' => 'maxlength="250"']) ?>
            <?php endif; ?>
            <?= Campo::selezioneEnum('codiceDiritto', $campi['codiceDiritto'], $formEntity, $errors, $diritti, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('quotaNumeratore', $campi['quotaNumeratore'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('quotaDenominatore', $campi['quotaDenominatore'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"']) ?>
            <?php if ($quadro->isTerreno()): ?>
                <?= Campo::selezioneEnum('tipologiaTerreno', $campi['tipologiaTerreno'], $formEntity, $errors, $tipologieTerreno, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
                <?= Campo::selezione('natura', $campi['natura'], $formEntity, $errors, $nature, ['colonna' => 'col-md-6']) ?>
            <?php elseif ($quadro->isFabbricato()): ?>
                <?= Campo::selezioneEnum('dirittoAbitazione', $campi['dirittoAbitazione'], $formEntity, $errors, $dirittiAbitazione, ['vuota' => $nessuno, 'colonna' => 'col-12']) ?>
            <?php endif; ?>
            <?php if ($quadro->isImmobile()): ?>
                <?= Campo::selezione('azienda', $campi['azienda'], $formEntity, $errors, $aziende, ['vuota' => $nessuna, 'colonna' => 'col-md-6', 'aiuto' => $aiutoAzienda]) ?>
            <?php endif; ?>
            <?php if (in_array($quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true)): ?>
                <?= Campo::spunta('discordanzaIntestatario', $campi['discordanzaIntestatario'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
                <?= Campo::spunta('passaggiSenzaAtti', $campi['passaggiSenzaAtti'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?php endif; ?>
            <?= Campo::testo('valore', $campi['valore'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"', 'aiuto' => $valoreProposto !== null ? $valoreProposto . ': € ' . number_format($valoreProposto, 0, ',', '.') : null]) ?>
            <?php if ($quadro === QuadroBene::titoli): ?>
                <?= Campo::testo('valoreEsente', $campi['valoreEsente'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"']) ?>
            <?php endif; ?>
            <?= Campo::testo('valorePrecedente', $campi['valorePrecedente'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"']) ?>
            <?php if ($quadro->isImmobile() === false): ?>
                <?= Campo::spunta('beneEstero', $campi['beneEstero'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?php endif; ?>
            <?php if ($quadro->isTavolare() === false): ?>
                <?= Campo::testo('impostaEstero', $campi['impostaEstero'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"']) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
