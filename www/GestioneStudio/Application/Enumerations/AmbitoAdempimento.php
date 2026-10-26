<?php

namespace GestioneStudio\Application\Enumerations;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\TipoSoggetto;

/**
 * Soggetti a cui l'adempimento si propone quando lo si assegna ai clienti.
 *
 * La proposta usa i dati dell'Anagrafica: tipo di soggetto e, dai dati
 * fiscali in vigore, partita IVA, attività d'impresa, regime fiscale,
 * periodicità IVA e qualifica di sostituto d'imposta. Va sempre confermata:
 * i dati non dicono tutto (es. chi effettua operazioni intracomunitarie),
 * e per questi adempimenti la proposta è solo a richiesta.
 */
enum AmbitoAdempimento: string
{

    case tutti = 'TUT';
    case personeFisiche = 'PF';
    case personeFisicheSenzaPartitaIva = 'PFP';
    case societaPersone = 'SP';
    case societaCapitali = 'SC';
    case enti = 'EN';
    case societaEnti = 'SOE';
    case soggettiIva = 'IVA';
    case liquidazioniIva = 'IVO';
    case ivaMensile = 'IVM';
    case ivaTrimestrale = 'IVT';
    case imprese = 'IMP';
    case artigianiCommercianti = 'ART';
    case sostitutiImposta = 'SOS';
    case suRichiesta = 'MAN';

    public function getLabel(): string
    {
        return match ($this) {
            self::tutti => 'Tutti i clienti',
            self::personeFisiche => 'Persone fisiche',
            self::personeFisicheSenzaPartitaIva => 'Persone fisiche senza partita IVA',
            self::societaPersone => 'Società di persone',
            self::societaCapitali => 'Società di capitali',
            self::enti => 'Enti',
            self::societaEnti => 'Società ed enti',
            self::soggettiIva => 'Titolari di partita IVA',
            self::liquidazioniIva => 'Titolari di partita IVA con liquidazioni IVA (non forfetari)',
            self::ivaMensile => 'Liquidazione IVA mensile',
            self::ivaTrimestrale => 'Liquidazione IVA trimestrale',
            self::imprese => 'Imprese (registro delle imprese)',
            self::artigianiCommercianti => 'Imprenditori individuali (artigiani e commercianti)',
            self::sostitutiImposta => 'Sostituti d\'imposta',
            self::suRichiesta => 'Nessuno: si assegna a richiesta',
        };
    }

    /**
     * Ai soggetti deceduti o cessati non si propone nulla: gli adempimenti
     * finali (es. dichiarazione per il defunto) si assegnano a mano.
     *
     * @param DatiFiscali|null $dati dati fiscali in vigore alla data della proposta
     */
    public function isProponibile(Soggetto $soggetto, ?DatiFiscali $dati = null): bool
    {
        if ($soggetto->isDeceduto() || $soggetto->isCessato()) {
            return false;
        }
        $partitaIva = $dati === null ? $soggetto->partitaIva : $dati->partitaIva;
        $tipoAttivita = $dati === null ? $soggetto->tipoAttivita : $dati->tipoAttivita;
        $impresaIndividuale = $soggetto->isPersonaFisica() && ($tipoAttivita?->isImpresa() ?? false);
        return match ($this) {
            self::tutti => true,
            self::personeFisiche => $soggetto->tipoSoggetto === TipoSoggetto::personaFisica,
            self::personeFisicheSenzaPartitaIva => $soggetto->isPersonaFisica() && ($partitaIva === null),
            self::societaPersone => $soggetto->tipoSoggetto === TipoSoggetto::societaPersone,
            self::societaCapitali => $soggetto->tipoSoggetto === TipoSoggetto::societaCapitali,
            self::enti => $soggetto->tipoSoggetto === TipoSoggetto::ente,
            self::societaEnti => $soggetto->isPersonaFisica() === false,
            self::soggettiIva => $partitaIva !== null,
            self::liquidazioniIva => ($partitaIva !== null) && ($dati?->regimeFiscale?->isLiquidazioneIva() ?? true),
            self::ivaMensile => ($partitaIva !== null) && ($dati?->periodicitaIva === PeriodicitaIva::mensile),
            self::ivaTrimestrale => ($partitaIva !== null) && ($dati?->periodicitaIva === PeriodicitaIva::trimestrale),
            self::imprese => in_array($soggetto->tipoSoggetto, [TipoSoggetto::societaPersone, TipoSoggetto::societaCapitali], true) || $impresaIndividuale,
            self::artigianiCommercianti => $impresaIndividuale,
            self::sostitutiImposta => $dati?->sostitutoImposta ?? false,
            self::suRichiesta => false,
        };
    }
}
