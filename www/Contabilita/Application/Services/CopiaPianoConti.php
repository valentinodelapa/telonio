<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Models\ContoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Entities\Studio;

/**
 * Copia di un piano dei conti con tutti i conti: un modello della suite
 * diventa un modello dello studio, un modello diventa il piano di
 * un'azienda. La copia è indipendente: le modifiche al modello non si
 * riflettono sui piani già copiati.
 */
class CopiaPianoConti
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function copia(PianoConti $origine, Studio $studio, ?Soggetto $soggetto, string $denominazione): PianoConti
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $piano = new PianoConti($this->dataMapper);
        $piano->studio = $studio;
        $piano->soggetto = $soggetto;
        $piano->denominazione = mb_substr($denominazione, 0, 100);
        $piano->descrizione = $origine->descrizione;
        $piano->pianoOrigine = $origine;
        $this->dataMapper->save($piano);
        $copiati = [];
        foreach ((new ContoModel($this->dataMapper))->getElenco($origine) as $conto) {
            $copia = new Conto($this->dataMapper);
            $copia->pianoConti = $piano;
            $copia->parentConto = $conto->parentConto === null ? null : ($copiati[$conto->parentConto->codice] ?? null);
            $copia->codice = $conto->codice;
            $copia->descrizione = $conto->descrizione;
            $copia->livello = $conto->livello;
            $copia->natura = $conto->natura;
            $copia->funzione = $conto->funzione;
            $copia->voceCee = $conto->voceCee;
            $copia->attivo = $conto->attivo;
            $this->dataMapper->save($copia);
            $copiati[$copia->codice] = $copia;
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $piano;
    }
}
