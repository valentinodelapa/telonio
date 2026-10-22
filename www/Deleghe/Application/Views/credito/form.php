<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Services\EsitoControllo;

$sezione = $credito->sezione;
$urlAnnulla = '/contribuente/show/soggetto/' . $credito->soggetto->id . '/vista/crediti';
$scelte = fn(array $voci): array => array_map(fn($voce) => $voce->getEtichetta(), $voci);
$valore = fn(string $campo): ?string => (isset($formEntity->$campo) && ($formEntity->$campo !== '')) ? (string) $formEntity->$campo : null;
$maiuscolo = 'style="text-transform: uppercase"';
$etichettaPosizione = match ($sezione) {
    SezioneF24::inail => $campi['posizioneInail'],
    SezioneF24::altriEnti => $campi['posizioneAltriEnti'],
    default => $campi['posizioneInps'],
};
$campiSezione = [
    'codiceTributo' => fn() => Campo::autocompleta('codiceTributo', $campi['codiceTributo'], $formEntity, $errors, '/codice-tributo/cerca/sezione/' . $sezione->getSezioneCodici()->value, $codiceTributoCorrente,
            ['richiesto' => true, 'colonna' => 'col-md-8', 'segnaposto' => $segnapostoCodice]),
    'codiceEnte' => fn() => match ($sezione) {
        SezioneF24::regioni => Campo::selezione('codiceEnte', $campi['codiceEnteRegioni'], $formEntity, $errors, $scelte($regioni), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']),
        SezioneF24::tributiLocali => Campo::testo('codiceEnte', $campi['codiceEnteTributiLocali'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" ' . $maiuscolo]),
        default => Campo::selezione('codiceEnte', $campi['codiceEnteAltriEnti'], $formEntity, $errors, $scelte($altriEnti), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-5']),
    },
    'codiceSede' => fn() => match ($sezione) {
        SezioneF24::inps => Campo::autocompleta('codiceSede', $campi['codiceSede'], $formEntity, $errors, '/tabelle-f24/voci/tabella/' . TabellaF24::sediInps->value, $valore('codiceSede'), ['richiesto' => true, 'colonna' => 'col-md-4', 'segnaposto' => $segnapostoCodice]),
        SezioneF24::inail => Campo::autocompleta('codiceSede', $campi['codiceSede'], $formEntity, $errors, '/tabelle-f24/voci/tabella/' . TabellaF24::sediInail->value, $valore('codiceSede'), ['richiesto' => true, 'colonna' => 'col-md-4', 'segnaposto' => $segnapostoCodice]),
        default => Campo::testo('codiceSede', $campi['codiceSede'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="5" ' . $maiuscolo]),
    },
    'causale' => fn() => match ($sezione) {
        SezioneF24::inps => Campo::autocompleta('causale', $campi['causale'], $formEntity, $errors, '/tabelle-f24/causali/ente/' . CausaleContributo::ENTE_INPS, $valore('causale'), ['richiesto' => true, 'colonna' => 'col-md-4', 'segnaposto' => $segnapostoCodice]),
        SezioneF24::inail => Campo::testo('causale', $campi['causaleInail'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="1" ' . $maiuscolo]),
        default => Campo::testo('causale', $campi['causale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" ' . $maiuscolo]),
    },
    'posizione' => fn() => Campo::testo('posizione', $etichettaPosizione, $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-5', 'attributi' => 'maxlength="17" ' . $maiuscolo]),
    'cifraControllo' => fn() => Campo::testo('cifraControllo', $campi['cifraControllo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="2" inputmode="numeric"']),
    'numeroRiferimento' => fn() => Campo::testo('numeroRiferimento', $campi['numeroRiferimento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="6" inputmode="numeric"']),
    'rateazione' => fn() => Campo::testo('rateazione', $campi['rateazione'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="4" ' . $maiuscolo]),
    'annoRiferimento' => fn() => Campo::testo('annoRiferimento', $campi['annoRiferimento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']),
    'periodoDa' => fn() => Campo::testo('periodoDa', $campi['periodoDa'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']),
    'periodoA' => fn() => Campo::testo('periodoA', $campi['periodoA'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']),
];

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-piggy-bank me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($sezione->getLabel()) ?> · <?= Html::e($credito->soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRigo) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoRigo) ?></p>
            <?php foreach ($campiRigo as $campo): ?>
                <?= $campiSezione[$campo]() ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneImporto) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('importo', $campi['importo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"',
                'aiuto' => $utilizzato > 0 ? $giaUtilizzato . ' € ' . EsitoControllo::euro($utilizzato) : null]) ?>
            <?= Campo::data('utilizzabileDal', $campi['utilizzabileDal'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoUtilizzabile]) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="100"', 'aiuto' => $aiutoDescrizione]) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
