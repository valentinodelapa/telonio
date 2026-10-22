<?php

use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$personaFisica = $soggetto->isPersonaFisica();
$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/fiscali';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($nuovo ? $aiutoVariazione : $aiutoRettifica) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::data('validoDal', $campi['validoDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('validoAl', $campi['validoAl'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoValidoAl]) ?>
            <div class="w-100 m-0"></div>
            <?php if ($personaFisica): ?>
                <?= Campo::selezioneEnum('tipoAttivita', $campi['tipoAttivita'], $formEntity, $errors, $tipiAttivita, ['vuota' => $nessunaAttivita, 'colonna' => 'col-md-4', 'aiuto' => $aiutoTipoAttivita]) ?>
                <?= Campo::testo('denominazione', $campi['ditta'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?php else: ?>
                <?= Campo::testo('denominazione', $campi['denominazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
                <?= Campo::selezioneEnum('naturaGiuridica', $campi['naturaGiuridica'], $formEntity, $errors, $soggetto->tipoSoggetto->getNatureGiuridiche(), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']) ?>
                <?php if ($soggetto->tipoSoggetto === TipoSoggetto::ente): ?>
                    <?= Campo::spunta('enteNonCommerciale', $campi['enteNonCommerciale'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?= Campo::testo('partitaIva', $campi['partitaIva'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="13"']) ?>
            <?= Campo::autocompleta('codiceAteco', $campi['codiceAteco'], $formEntity, $errors, '/tabelle/ateco', isset($formEntity->codiceAteco) ? $formEntity->codiceAteco->getEtichetta() : null, ['colonna' => 'col-md-9', 'campoData' => 'validoDal', 'segnaposto' => $segnapostoAteco, 'aiuto' => isset($atecoConvertitoDa) ? $atecoConvertitoDa->classificazione->getLabel() . ' ' . $atecoConvertitoDa->getEtichetta() . ': ' . ($atecoRaccordoTrovato ? $aiutoRaccordoAteco : $aiutoRaccordoAtecoAssente) : null]) ?>
            <?= Campo::testo('descrizioneAttivita', $campi['descrizioneAttivita'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
