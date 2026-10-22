<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Entities\SanzioneCivile;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Registra il calcolo delle sanzioni civili e, se è indicata la causale di
 * versamento, il rigo della sanzione nella delega (ArchivioVersamenti,
 * origine "SanzioniCivili"): codice sede, matricola e periodo sono quelli
 * dei contributi a cui la sanzione si riferisce.
 */
class SanzioniCiviliService
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function registra(SanzioneCivile $sanzioneCivile): void
    {
        $delega = $sanzioneCivile->delega;
        $calcolo = (new CalcoloSanzioniCivili($this->dataMapper))->calcola($sanzioneCivile->importoContributi, $sanzioneCivile->scadenzaOriginale, $delega->scadenza,
                $sanzioneCivile->regime, $sanzioneCivile->spontaneo);
        $sanzioneCivile->giorniRitardo = $calcolo['giorni'];
        $sanzioneCivile->sanzione = $calcolo['sanzione'];
        $sanzioneCivile->tettoApplicato = $calcolo['tetto'];
        $sanzioneCivile->dettaglio = json_encode($calcolo['periodi']);
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($sanzioneCivile);
        $righe = [];
        if (($sanzioneCivile->causale !== null) && ($sanzioneCivile->sanzione > 0)) {
            $riga = new Versamento($this->dataMapper);
            $riga->sezione = $sanzioneCivile->sezione;
            $riga->codiceSede = $sanzioneCivile->codiceSede;
            $riga->causale = $sanzioneCivile->causale;
            $riga->posizione = $sanzioneCivile->posizione;
            $riga->periodoDa = $sanzioneCivile->periodoDa;
            $riga->periodoA = $sanzioneCivile->periodoA;
            $riga->importoDebito = $sanzioneCivile->sanzione;
            $riga->descrizione = mb_substr('Sanzioni civili ' . $sanzioneCivile->riferimento, 0, 100);
            $righe[] = $riga;
        }
        (new ArchivioVersamenti($this->dataMapper))->registra($delega->soggetto, SanzioneCivile::ORIGINE, $sanzioneCivile->getRiferimentoOrigine(), $righe, $delega);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    public function elimina(SanzioneCivile $sanzioneCivile): void
    {
        $delega = $sanzioneCivile->delega;
        $primaTransazione = $this->dataMapper->startTransaction();
        (new ArchivioVersamenti($this->dataMapper))->registra($delega->soggetto, SanzioneCivile::ORIGINE, $sanzioneCivile->getRiferimentoOrigine(), [], $delega);
        $this->dataMapper->delete($sanzioneCivile);
        $this->dataMapper->commitTransaction($primaTransazione);
    }
}
