<?php

namespace Dichiarativi\Application\Services;

use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\GaranziaRimborsoModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\HelperClasses\Calendario;
use Telonio\Application\Interfaces\FonteScadenze;
use Telonio\Application\Services\Scadenza;

/**
 * Scadenze del modulo per lo scadenzario dello studio: le dichiarazioni
 * predisposte, con il termine ordinario di presentazione telematica,
 * completate quando presentate, e le garanzie dei rimborsi, completate quando
 * svincolate. Saldi e crediti sono nell'archivio di Deleghe F24 e compaiono
 * da lì.
 */
class ScadenzeModulo implements FonteScadenze
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        $model = new DichiarazioneModel($this->dataMapper);
        $scadenze = [];
        for ($anno = (int) $dal->format('Y') - 1; $anno <= (int) $al->format('Y') - 1; $anno++) {
            $dichiarazioni = $soggetto === null ? $model->getPerStudio($studio, $anno) : $model->getBySoggettoAndAnno($soggetto, $anno);
            foreach ($dichiarazioni as $dichiarazione) {
                $data = Calendario::termineFiscale($dichiarazione->getScadenza(), false);
                if (($data->format('Y-m-d') < $dal->format('Y-m-d')) || ($data->format('Y-m-d') > $al->format('Y-m-d'))) {
                    continue;
                }
                $scadenze[] = new Scadenza(
                        modulo: ModuloSuite::dichiarativi,
                        riferimento: 'dichiarazione-' . $dichiarazione->id,
                        data: $data,
                        descrizione: 'Dichiarazione ' . $dichiarazione->getDescrizione(),
                        soggetto: $dichiarazione->soggetto,
                        url: '/dichiarazione/show/dichiarazione/' . $dichiarazione->id,
                        completata: $dichiarazione->stato === StatoDichiarazione::presentata,
                        dettaglio: $dichiarazione->stato->getLabel(),
                );
            }
        }
        return array_merge($scadenze, $this->getScadenzeGaranzie($studio, $dal, $al, $soggetto));
    }

    /**
     * @return Scadenza[]
     */
    private function getScadenzeGaranzie(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto): array
    {
        $model = new GaranziaRimborsoModel($this->dataMapper);
        $scadenze = [];
        foreach ($soggetto === null ? $model->getPerStudio($studio) : $model->getElenco($soggetto) as $garanzia) {
            if (($garanzia->dataScadenza === null) || ($garanzia->dataScadenza->format('Y-m-d') < $dal->format('Y-m-d')) || ($garanzia->dataScadenza->format('Y-m-d') > $al->format('Y-m-d'))) {
                continue;
            }
            $scadenze[] = new Scadenza(
                    modulo: ModuloSuite::dichiarativi,
                    riferimento: 'garanzia-' . $garanzia->id,
                    data: $garanzia->dataScadenza,
                    descrizione: 'Scadenza garanzia del rimborso IVA ' . $garanzia->annoImposta,
                    soggetto: $garanzia->soggetto,
                    url: '/contribuente-dichiarativi/show/soggetto/' . $garanzia->soggetto->id . '/anno/' . $garanzia->annoImposta,
                    completata: $garanzia->isSvincolata(),
                    dettaglio: $garanzia->tipo->getLabel(),
            );
        }
        return $scadenze;
    }
}
