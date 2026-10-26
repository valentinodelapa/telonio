<?php

namespace GestioneStudio\Application\Services;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Interfaces\FonteScadenze;
use CommSuite\Application\Services\Scadenza;
use GestioneStudio\Application\Models\AssegnazioneAdempimentoModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenze degli adempimenti assegnati ai clienti: per ogni assegnazione,
 * i termini effettivi dell'adempimento che cadono nel periodo e nella sua
 * validità, con il responsabile dell'assegnazione.
 */
class ScadenzeModulo implements FonteScadenze
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        $calendario = new CalendarioAdempimenti($studio, $this->dataMapper);
        $scadenze = [];
        foreach ((new AssegnazioneAdempimentoModel($this->dataMapper))->getValideNelPeriodo($studio, $dal, $al, $soggetto) as $assegnazione) {
            $adempimento = $assegnazione->adempimento;
            if ($adempimento->attivo === false) {
                continue;
            }
            $inizio = max($dal->format('Y-m-d'), $assegnazione->validoDal->format('Y-m-d'));
            $fine = $assegnazione->validoAl === null ? $al->format('Y-m-d') : min($al->format('Y-m-d'), $assegnazione->validoAl->format('Y-m-d'));
            foreach ($calendario->getTermini($adempimento, new SismaDate($inizio), new SismaDate($fine), $assegnazione->soggetto) as $termine) {
                $scadenze[] = new Scadenza(
                        modulo: ModuloSuite::gestioneStudio,
                        riferimento: $termine->getRiferimento($assegnazione->soggetto->id),
                        data: $termine->data,
                        descrizione: $adempimento->denominazione . ' - ' . $termine->getPeriodo(),
                        soggetto: $assegnazione->soggetto,
                        url: '/scadenzario/soggetto/soggetto/' . $assegnazione->soggetto->id,
                        dettaglio: $termine->proroga === null ? $adempimento->fonte : 'Prorogato: ' . $termine->proroga->provvedimento,
                        responsabile: $assegnazione->responsabile,
                );
            }
        }
        return $scadenze;
    }
}
