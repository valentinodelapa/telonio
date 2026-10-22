<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$personaFisica = $soggetto->isPersonaFisica();
$urlAnnulla = $nuovo ? '/soggetto/index' : '/soggetto/show/soggetto/' . $soggetto->id;

require Layout::percorso('header');
?>
<h1 class="h3 mb-3"><i class="bi <?= Html::e($soggetto->tipoSoggetto->getIcona()) ?> me-2"></i><?= Html::e($pageTitle) ?></h1>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneIdentificazione) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('codiceFiscale', $campi['codiceFiscale'], $formEntity, $errors, ['richiesto' => $personaFisica === false, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="16" style="text-transform: uppercase"', 'aiuto' => $personaFisica ? $aiutoCodiceFiscale : null]) ?>
            <?= Campo::testo('codice', $campi['codice'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::spunta('clienteStudio', $campi['clienteStudio'], $formEntity, $errors, ['colonna' => 'col-md-5']) ?>
        </div>
    </div>

    <?php if ($personaFisica): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezionePersona) ?></div>
            <div class="card-body row g-3">
                <?= Campo::testo('cognome', $campi['cognome'], $formEntity, $errors, ['richiesto' => true, 'attributi' => 'maxlength="100"']) ?>
                <?= Campo::testo('nome', $campi['nome'], $formEntity, $errors, ['richiesto' => true, 'attributi' => 'maxlength="100"']) ?>
                <?= Campo::selezioneEnum('sesso', $campi['sesso'], $formEntity, $errors, $sessi, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-3']) ?>
                <?= Campo::data('dataNascita', $campi['dataNascita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
                <?= Campo::selezioneEnum('statoCivile', $campi['statoCivile'], $formEntity, $errors, $statiCivili, ['vuota' => $selezionare, 'colonna' => 'col-md-3']) ?>
                <?= Campo::autocompleta('cittadinanza', $campi['cittadinanza'], $formEntity, $errors, '/tabelle/nazioni', $formEntity->cittadinanza?->denominazione, ['colonna' => 'col-md-3']) ?>
                <?= Campo::autocompleta('comuneNascita', $campi['comuneNascita'], $formEntity, $errors, '/tabelle/comuni', $formEntity->comuneNascita?->getEtichetta(), ['aiuto' => $aiutoNascita]) ?>
                <?= Campo::autocompleta('nazioneNascita', $campi['nazioneNascita'], $formEntity, $errors, '/tabelle/nazioni', $formEntity->nazioneNascita?->denominazione) ?>
                <?php if ($nuovo === false): ?>
                    <?= Campo::data('dataDecesso', $campi['dataDecesso'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoDecesso]) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneEnte) ?></div>
            <div class="card-body row g-3">
                <?= Campo::data('dataCostituzione', $campi['dataCostituzione'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($nuovo && isset($datiFiscaliEntity)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($personaFisica ? $sezioneAttivita : $sezioneDatiFiscali) ?></div>
            <div class="card-body row g-3">
                <?php if ($personaFisica): ?>
                    <?= Campo::selezioneEnum('tipoAttivita', $campi['tipoAttivita'], $datiFiscaliEntity, $datiFiscaliErrors, $tipiAttivita, ['richiesto' => true, 'colonna' => 'col-md-5']) ?>
                    <?= Campo::data('validoDal', $campi['inizioAttivita'], $datiFiscaliEntity, $datiFiscaliErrors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
                    <?= Campo::testo('partitaIva', $campi['partitaIva'], $datiFiscaliEntity, $datiFiscaliErrors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="13"']) ?>
                    <?= Campo::testo('denominazione', $campi['ditta'], $datiFiscaliEntity, $datiFiscaliErrors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
                <?php else: ?>
                    <?= Campo::testo('denominazione', $campi['denominazione'], $datiFiscaliEntity, $datiFiscaliErrors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
                    <?= Campo::data('validoDal', $campi['validoDal'], $datiFiscaliEntity, $datiFiscaliErrors, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
                    <?= Campo::selezioneEnum('naturaGiuridica', $campi['naturaGiuridica'], $datiFiscaliEntity, $datiFiscaliErrors, $soggetto->tipoSoggetto->getNatureGiuridiche(), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-5']) ?>
                    <?= Campo::testo('partitaIva', $campi['partitaIva'], $datiFiscaliEntity, $datiFiscaliErrors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="13"']) ?>
                <?php endif; ?>
                <?= Campo::autocompleta('codiceAteco', $campi['codiceAteco'], $datiFiscaliEntity, $datiFiscaliErrors, '/tabelle/ateco', isset($datiFiscaliEntity->codiceAteco) ? $datiFiscaliEntity->codiceAteco->getEtichetta() : null, ['colonna' => 'col-12', 'campoData' => 'validoDal', 'segnaposto' => $segnapostoAteco]) ?>
                <?= Campo::testo('descrizioneAttivita', $campi['descrizioneAttivita'], $datiFiscaliEntity, $datiFiscaliErrors, ['colonna' => 'col-12', 'aiuto' => $aiutoDatiFiscali]) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::data('dataCessazione', $campi['dataCessazione'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
        </div>
    </div>

    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
