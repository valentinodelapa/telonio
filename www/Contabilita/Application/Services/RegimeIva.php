<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Anagrafica\Application\Models\DatiFiscaliModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Regime IVA dell'azienda nell'anno, dai dati fiscali storicizzati
 * dell'Anagrafica: la periodicità delle liquidazioni è un'opzione annuale
 * e si legge al 1° gennaio (o, per le attività iniziate nell'anno, al 31
 * dicembre). Con le attività separate l'azienda liquida mensilmente se
 * almeno un'attività è mensile (AttivitaSeparate).
 */
class RegimeIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getDatiFiscali(Soggetto $soggetto, int $anno): ?DatiFiscali
    {
        $model = new DatiFiscaliModel($this->dataMapper);
        return $model->getVigenteAl($soggetto, new SismaDate($anno . '-01-01')) ?? $model->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
    }

    /**
     * L'azienda applica la ventilazione dei corrispettivi nell'anno.
     */
    public function isVentilazione(Soggetto $soggetto, int $anno): bool
    {
        return $this->getDatiFiscali($soggetto, $anno)?->ventilazioneCorrispettivi ?? false;
    }

    /**
     * L'azienda è trimestrale per natura dell'attività nell'anno (art. 74,
     * c. 4, D.P.R. 633/1972; dal 2027 art. 139, c. 4, D.Lgs. 10/2026).
     */
    public function isTrimestraleSpeciale(Soggetto $soggetto, int $anno): bool
    {
        return $this->getDatiFiscali($soggetto, $anno)?->trimestraleSpeciale ?? false;
    }

    /**
     * @throws \DomainException se nell'anno l'azienda non liquida l'IVA
     */
    public function getPeriodicita(Soggetto $soggetto, int $anno): PeriodicitaIva
    {
        $datiFiscali = $this->getDatiFiscali($soggetto, $anno);
        if (($datiFiscali === null) || ($datiFiscali->partitaIva === null) || ($datiFiscali->regimeFiscale === null)) {
            throw new \DomainException('Nei dati fiscali dell\'Anagrafica per il ' . $anno . ' mancano partita IVA o regime fiscale.');
        }
        if ($datiFiscali->regimeFiscale->isLiquidazioneIva() === false) {
            throw new \DomainException('Nel ' . $anno . ' l\'azienda è nel regime ' . mb_strtolower($datiFiscali->regimeFiscale->getLabel()) . ', che non prevede le liquidazioni IVA.');
        }
        if ($datiFiscali->regimeDetrazioneIva === RegimeDetrazioneIva::agricoloEsonerato) {
            throw new \DomainException('Nel ' . $anno . ' l\'azienda è un agricoltore esonerato (art. 34, c. 6): nessuna liquidazione IVA.');
        }
        $periodicita = $datiFiscali->regimeFiscale->getPeriodicitaObbligata() ?? $datiFiscali->periodicitaIva
                ?? throw new \DomainException('Nei dati fiscali dell\'Anagrafica per il ' . $anno . ' manca la periodicità IVA.');
        // attività separate: mensile se almeno un'attività liquida mensilmente
        $separate = new AttivitaSeparate($this->dataMapper);
        $attivita = $separate->getAttivita($soggetto, $anno);
        if (count($attivita) === 0) {
            return $periodicita;
        }
        foreach ($attivita as $singola) {
            if ($separate->getPeriodicita($singola, $periodicita) === PeriodicitaIva::mensile) {
                return PeriodicitaIva::mensile;
            }
        }
        return PeriodicitaIva::trimestrale;
    }

    /**
     * Periodicità dell'azienda dai dati fiscali, senza le attività
     * separate.
     */
    public function getPeriodicitaAzienda(Soggetto $soggetto, int $anno): PeriodicitaIva
    {
        $datiFiscali = $this->getDatiFiscali($soggetto, $anno);
        return $datiFiscali?->regimeFiscale?->getPeriodicitaObbligata() ?? $datiFiscali?->periodicitaIva ?? $this->getPeriodicita($soggetto, $anno);
    }
}
