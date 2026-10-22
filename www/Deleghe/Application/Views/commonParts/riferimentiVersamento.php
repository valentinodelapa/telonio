<?php

use Deleghe\Application\Enumerations\SezioneF24;

/**
 * $riferimenti($versamento): riferimenti del rigo in una riga di testo
 * (ente, sede, matricola, periodo, rateazione, anno), secondo la sezione.
 * Richiede $campi, $periodo, $rateazioneAbbreviata, $anno e, se presenti,
 * le denominazioni delle regioni in $regioni.
 */
$denominazioniRegioni = $regioni ?? [];
$riferimenti = function ($riga) use ($denominazioniRegioni, $campi, $periodo, $rateazioneAbbreviata, $anno): string {
    $parti = match ($riga->sezione) {
        SezioneF24::erario => [],
        SezioneF24::regioni => [$riga->codiceEnte . ' ' . ($denominazioniRegioni[$riga->codiceEnte] ?? '')],
        SezioneF24::tributiLocali => [$riga->codiceEnte, implode(' ', array_filter([
                ($riga->ravvedimento ?? false) ? $campi['ravvedimento'] : null, ($riga->immobiliVariati ?? false) ? $campi['immobiliVariati'] : null,
                ($riga->acconto ?? false) ? $campi['acconto'] : null, ($riga->saldo ?? false) ? $campi['saldo'] : null])),
            ($riga->numeroImmobili ?? null) !== null ? $campi['numeroImmobili'] . ' ' . $riga->numeroImmobili : null],
        SezioneF24::inps => [$campi['codiceSede'] . ' ' . $riga->codiceSede, $riga->posizione, $riga->periodoDa !== null ? $periodo . ' ' . $riga->periodoDa . ($riga->periodoA !== null ? ' - ' . $riga->periodoA : '') : null],
        SezioneF24::inail => [$campi['codiceSede'] . ' ' . $riga->codiceSede, $campi['posizioneInail'] . ' ' . $riga->posizione . ' ' . $campi['cifraControllo'] . ' ' . $riga->cifraControllo, $campi['numeroRiferimento'] . ' ' . $riga->numeroRiferimento],
        SezioneF24::altriEnti => [$campi['codiceEnteAltriEnti'] . ' ' . $riga->codiceEnte, $campi['codiceSede'] . ' ' . $riga->codiceSede, $riga->posizione, $riga->periodoDa !== null ? $periodo . ' ' . $riga->periodoDa . ($riga->periodoA !== null ? ' - ' . $riga->periodoA : '') : null],
        SezioneF24::elide => [$campi['tipoElide'] . ' ' . $riga->tipoElide, $riga->elementiIdentificativi],
        SezioneF24::accise => [$riga->codiceTributo?->enteAccise, $riga->provincia, $riga->codiceIdentificativoEnte, $riga->meseRiferimento !== null ? sprintf('%02d', $riga->meseRiferimento) : null],
    };
    if (($riga->numeroCertificazione ?? null) !== null) {
        $parti[] = $campi['numeroCertificazione'] . ' ' . $riga->numeroCertificazione;
    }
    if ($riga->rateazione !== null) {
        $parti[] = $rateazioneAbbreviata . ' ' . $riga->rateazione;
    }
    if ($riga->annoRiferimento !== null) {
        $parti[] = $anno . ' ' . $riga->annoRiferimento;
    }
    return implode(' · ', array_filter($parti, fn($parte) => ($parte !== null) && (trim($parte) !== '')));
};
