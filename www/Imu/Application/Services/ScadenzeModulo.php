<?php

namespace Imu\Application\Services;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\HelperClasses\Calendario;
use CommSuite\Application\Interfaces\FonteScadenze;
use CommSuite\Application\Services\Scadenza;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Models\DichiarazioneImuModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenze del modulo per lo scadenzario dello studio: le dichiarazioni
 * IMU registrate, con termine al 30 giugno dell'anno successivo (art. 1,
 * c. 769, L. 160/2019), completate quando presentate. Acconto e saldo
 * sono versamenti dell'archivio di Deleghe F24 e compaiono da lì.
 */
class ScadenzeModulo implements FonteScadenze
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        $model = new DichiarazioneImuModel($this->dataMapper);
        $scadenze = [];
        for ($anno = (int) $dal->format('Y') - 1; $anno <= (int) $al->format('Y') - 1; $anno++) {
            $dichiarazioni = $soggetto === null ? $model->getByStudioAndAnno($studio, $anno) : $model->getBySoggettoAndAnno($soggetto, $anno);
            foreach ($dichiarazioni as $dichiarazione) {
                $data = Calendario::termineFiscale($dichiarazione->getScadenza(), false);
                if (($data->format('Y-m-d') < $dal->format('Y-m-d')) || ($data->format('Y-m-d') > $al->format('Y-m-d'))) {
                    continue;
                }
                $scadenze[] = new Scadenza(
                        modulo: ModuloSuite::imu,
                        riferimento: 'dichiarazione-' . $dichiarazione->id,
                        data: $data,
                        descrizione: 'Dichiarazione IMU ' . $anno . ' - ' . $dichiarazione->denominazioneComune,
                        soggetto: $dichiarazione->soggetto,
                        url: '/dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id,
                        completata: $dichiarazione->stato === StatoDichiarazione::presentata,
                        dettaglio: $dichiarazione->stato->getLabel(),
                );
            }
        }
        return $scadenze;
    }
}
