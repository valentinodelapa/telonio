<?php

namespace Imu\Application\Enumerations;

/**
 * Unità immobiliari assimilate all'abitazione principale (art. 1, comma
 * 741, lett. c, L. 160/2019): si trattano come abitazione principale anche
 * se il possessore non vi risiede.
 */
enum AssimilazioneAbitazione: string
{

    case cooperativaSoci = 'CS';
    case cooperativaStudenti = 'CU';
    case alloggioSociale = 'AS';
    case casaFamiliare = 'CF';
    case forzeArmate = 'FA';
    case anzianiDisabili = 'AD';

    public function getLabel(): string
    {
        return match ($this) {
            self::cooperativaSoci => 'Cooperativa edilizia a proprietà indivisa, adibita ad abitazione principale dei soci',
            self::cooperativaStudenti => 'Cooperativa edilizia destinata a studenti universitari soci assegnatari',
            self::alloggioSociale => 'Alloggio sociale (D.M. 22 aprile 2008) adibito ad abitazione principale',
            self::casaFamiliare => 'Casa familiare assegnata al genitore affidatario dei figli',
            self::forzeArmate => 'Unica unità del personale delle Forze armate, di polizia, dei Vigili del fuoco o prefettizio',
            self::anzianiDisabili => 'Unità di anziani o disabili ricoverati in istituto, non locata (se prevista dal comune)',
        };
    }

    public function getRiferimento(): string
    {
        return 'comma 741, lett. c), n. ' . match ($this) {
            self::cooperativaSoci => '1',
            self::cooperativaStudenti => '2',
            self::alloggioSociale => '3',
            self::casaFamiliare => '4',
            self::forzeArmate => '5',
            self::anzianiDisabili => '6',
        } . ', L. 160/2019';
    }

    /**
     * L'assimilazione per anziani e disabili vale solo se prevista dal
     * comune (indicata nel prospetto delle aliquote).
     */
    public function richiedeComune(): bool
    {
        return $this === self::anzianiDisabili;
    }
}
