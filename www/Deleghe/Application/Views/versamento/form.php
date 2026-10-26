<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;

$delega = $versamento->delega;
$urlAnnulla = $delega === null ? '/contribuente/show/soggetto/' . $versamento->soggetto->id : '/delega/show/delega/' . $delega->id;
$scelte = fn(array $voci): array => array_map(fn($voce) => $voce->getEtichetta(), $voci);
$etichettaPosizione = match ($sezione) {
    SezioneF24::inail => $campi['posizioneInail'],
    SezioneF24::altriEnti => $campi['posizioneAltriEnti'],
    default => $campi['posizioneInps'],
};
$valore = fn(string $campo): ?string => (isset($formEntity->$campo) && ($formEntity->$campo !== '')) ? (string) $formEntity->$campo : null;
$maiuscolo = 'style="text-transform: uppercase"';
$campiSezione = [
    'codiceTributo' => fn() => Campo::autocompleta('codiceTributo', $campi['codiceTributo'], $formEntity, $errors, '/codice-tributo/cerca/sezione/' . $sezione->getSezioneCodici()->value, $codiceTributoCorrente,
            ['richiesto' => true, 'colonna' => 'col-md-8', 'segnaposto' => $segnapostoCodice]),
    'codiceEnte' => fn() => match ($sezione) {
        SezioneF24::regioni => Campo::selezione('codiceEnte', $campi['codiceEnteRegioni'], $formEntity, $errors, $scelte($regioni), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']),
        SezioneF24::tributiLocali => Campo::testo('codiceEnte', $campi['codiceEnteTributiLocali'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" ' . $maiuscolo, 'aiuto' => $aiutoCodiceComune]),
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
        default => Campo::testo('causale', $campi['causale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" ' . $maiuscolo, 'aiuto' => $aiutoCausale]),
    },
    'posizione' => fn() => Campo::testo('posizione', $etichettaPosizione, $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-5', 'attributi' => 'maxlength="17" ' . $maiuscolo]),
    'cifraControllo' => fn() => Campo::testo('cifraControllo', $campi['cifraControllo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="2" inputmode="numeric"']),
    'numeroRiferimento' => fn() => Campo::testo('numeroRiferimento', $campi['numeroRiferimento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="6" inputmode="numeric"']),
    'rateazione' => fn() => Campo::testo('rateazione', $campi['rateazione'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="4" ' . $maiuscolo, 'aiuto' => $formatoRateazione ?? $aiutoRateazione]),
    'annoRiferimento' => fn() => Campo::testo('annoRiferimento', $campi['annoRiferimento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']),
    'periodoDa' => fn() => Campo::testo('periodoDa', $campi['periodoDa'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']),
    'periodoA' => fn() => Campo::testo('periodoA', $campi['periodoA'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']),
    'ravvedimento' => fn() => Campo::spunta('ravvedimento', $campi['ravvedimento'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']),
    'immobiliVariati' => fn() => Campo::spunta('immobiliVariati', $campi['immobiliVariati'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']),
    'acconto' => fn() => Campo::spunta('acconto', $campi['acconto'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']),
    'saldo' => fn() => Campo::spunta('saldo', $campi['saldo'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']),
    'numeroImmobili' => fn() => Campo::testo('numeroImmobili', $campi['numeroImmobili'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="3" inputmode="numeric"']),
    'tipoElide' => fn() => Campo::selezione('tipoElide', $campi['tipoElide'], $formEntity, $errors, $scelte($tipiElide), ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']),
    'numeroCertificazione' => fn() => Campo::testo('numeroCertificazione', $campi['numeroCertificazione'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="16" ' . $maiuscolo, 'aiuto' => $aiutoCertificazione]),
    'provincia' => fn() => Campo::testo('provincia', $campi['provincia'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="2" ' . $maiuscolo]),
    'codiceIdentificativoEnte' => fn() => Campo::testo('codiceIdentificativoEnte', $campi['codiceIdentificativoEnte'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="14" ' . $maiuscolo, 'aiuto' => $descrizioneIdentificativo ?? $aiutoIdentificativoEnte]),
    'meseRiferimento' => fn() => Campo::testo('meseRiferimento', $campi['meseRiferimento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="2" inputmode="numeric"']),
    'elementiIdentificativi' => fn() => Campo::testo('elementiIdentificativi', $campi['elementiIdentificativi'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="17" ' . $maiuscolo, 'aiuto' => $aiutoElementi]),
];

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi <?= Html::e($sezione->getIcona()) ?> me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= $delega === null ? Html::e($etichettaDaVersare) : 'F24 ' . Html::data($delega->scadenza) ?> · <?= Html::e($versamento->soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?php if ($delega === null): ?>
                <?= Campo::data('scadenza', $campi['scadenza'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?php endif; ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => $delega === null ? 'col-md-9' : 'col-12', 'attributi' => 'maxlength="100"', 'aiuto' => $aiutoDescrizione]) ?>
            <div class="w-100 m-0"></div>
            <?php foreach ($sezione->getCampi() as $campo): ?>
                <?= $campiSezione[$campo]() ?>
            <?php endforeach; ?>
            <div class="w-100 m-0"></div>
            <?= Campo::testo('importoDebito', $campi['importoDebito'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?php if (in_array($sezione, [SezioneF24::elide, SezioneF24::accise], true) === false): ?>
                <?= Campo::testo('importoCredito', $campi['importoCredito'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
                <div class="col-md-6 small text-body-secondary align-self-center"><?= Html::e($aiutoImporti) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
