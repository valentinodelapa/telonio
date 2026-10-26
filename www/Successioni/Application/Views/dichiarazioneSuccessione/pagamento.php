<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/liquidazione';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->getTitolo()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoPagamento) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?php if (count($conti) === 0): ?><div class="col-12 small text-warning"><?= Html::e($nessunConto) ?></div><?php endif; ?>
            <?= Campo::selezione('contoAddebito', $campi['contoAddebito'], $formEntity, $errors, $conti, ['vuota' => $nessuno, 'colonna' => 'col-md-8']) ?>
            <?= Campo::testo('numeroCircoscrizioni', $campi['numeroCircoscrizioni'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric" maxlength="2"', 'aiuto' => 'Calcolate: ' . $risultato->circoscrizioniTassa]) ?>
            <?php if ($dichiarazione->codiceCarica === \Successioni\Application\Enumerations\CaricaDichiarante::trustee): ?>
                <?= Campo::spunta('pagamentoAnticipatoTrust', $campi['pagamentoAnticipatoTrust'], $formEntity, $errors, ['colonna' => 'col-12', 'aiuto' => $aiutoAnticipato]) ?>
            <?php else: ?>
                <input type="hidden" name="pagamentoAnticipatoTrust" value="0">
            <?php endif; ?>
            <?php if ($dichiarazione->isAutoliquidazione()): ?>
                <?= Campo::selezioneEnum('tempisticaPagamento', $campi['tempisticaPagamento'], $formEntity, $errors, $tempistiche, ['vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
                <?= Campo::data('dataVersamento', $campi['dataVersamento'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => 'Entro il ' . $dichiarazione->getScadenzaPagamento()->format('d/m/Y')]) ?>
                <?= Campo::testo('numeroRate', $campi['numeroRate'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric" maxlength="2"']) ?>
                <?= Campo::testo('acconto', $campi['acconto'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"', 'aiuto' => 'Imposta da versare: € ' . number_format($risultato->successioneDaVersare, 0, ',', '.')]) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCredito) ?></div>
        <div class="card-body row g-3">
            <div class="col-12 small text-body-secondary"><?= Html::e($aiutoCredito) ?></div>
            <?= Campo::selezioneEnum('creditoTipo', $campi['creditoTipo'], $formEntity, $errors, $tipiCredito, ['vuota' => $nessuno, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('creditoImporto', $campi['creditoImporto'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"']) ?>
            <?php foreach (['Acquisto', 'Vendita'] as $atto): ?>
                <div class="col-12 small fw-semibold"><?= Html::e(${'registrazione' . $atto}) ?></div>
                <?= Campo::autocompleta('credito' . $atto . 'Ufficio', $campi['credito' . $atto . 'Ufficio'], $formEntity, $errors, '/tabelle-successioni/uffici', $formEntity->{'credito' . $atto . 'Ufficio'}?->getEtichetta(), ['colonna' => 'col-md-5']) ?>
                <?= Campo::testo('credito' . $atto . 'Serie', $campi['credito' . $atto . 'Serie'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="2"']) ?>
                <?= Campo::testo('credito' . $atto . 'Numero', $campi['credito' . $atto . 'Numero'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
                <?= Campo::testo('credito' . $atto . 'Sottonumero', $campi['credito' . $atto . 'Sottonumero'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="3"']) ?>
                <?= Campo::data('credito' . $atto . 'Data', $campi['credito' . $atto . 'Data'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($dichiarazione->tipoDichiarazione->isSostitutiva()): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneSostitutiva) ?></div>
            <div class="card-body row g-3">
                <?php foreach (['ipotecariaVersata', 'catastaleVersata', 'successioneVersata', 'creditoIpotecaria', 'creditoCatastale', 'creditoSuccessione'] as $campo): ?>
                    <?= Campo::testo($campo, $campi[$campo], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="numeric"']) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
