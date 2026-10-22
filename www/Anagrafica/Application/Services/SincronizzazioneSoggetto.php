<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Riporta sul soggetto i valori correnti dei dati storicizzati (nominativo,
 * partita IVA, tipo di attività), usati per elenchi e ricerche, e ne
 * aggiorna la data di
 * ultima modifica: va invocata dopo ogni modifica ai dati del soggetto.
 */
class SincronizzazioneSoggetto
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function aggiorna(Soggetto $soggetto, bool $registraModifica = true): void
    {
        $datiFiscaliModel = new DatiFiscaliModel($this->dataMapper);
        $datiCorrenti = $datiFiscaliModel->getVigenteAl($soggetto, new SismaDate()) ?? ($datiFiscaliModel->getStorico($soggetto)[0] ?? null);
        if ($soggetto->isPersonaFisica()) {
            $soggetto->nominativo = mb_strtoupper(trim($soggetto->cognome . ' ' . $soggetto->nome));
        } elseif (($datiCorrenti !== null) && ($datiCorrenti->denominazione !== null)) {
            $soggetto->nominativo = $datiCorrenti->denominazione;
        }
        $soggetto->partitaIva = $datiCorrenti?->partitaIva;
        $soggetto->tipoAttivita = $datiCorrenti?->tipoAttivita;
        if ($registraModifica) {
            $soggetto->dataModifica = new SismaDateTime();
        }
        $this->dataMapper->save($soggetto);
    }
}
