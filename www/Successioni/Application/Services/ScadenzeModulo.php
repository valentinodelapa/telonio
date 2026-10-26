<?php

namespace Successioni\Application\Services;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\HelperClasses\Calendario;
use CommSuite\Application\Interfaces\FonteScadenze;
use CommSuite\Application\Services\Scadenza;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Models\DichiarazioneSuccessioneModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenze del modulo per lo scadenzario dello studio: termine di
 * presentazione delle dichiarazioni di successione (dodici mesi
 * dall'apertura o dalla decorrenza indicata, art. 31 TUS), riferite al
 * defunto e completate quando presentate. I versamenti degli eredi sono
 * nell'archivio di Deleghe F24 e compaiono da lì.
 */
class ScadenzeModulo implements FonteScadenze
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        $model = new DichiarazioneSuccessioneModel($this->dataMapper);
        $dichiarazioni = $soggetto === null ? $model->getByStudio($studio) : $model->getByStudioAndDefunto($studio, $soggetto);
        $scadenze = [];
        foreach ($dichiarazioni as $dichiarazione) {
            $data = Calendario::termineFiscale($dichiarazione->getScadenza(), false);
            if (($data->format('Y-m-d') < $dal->format('Y-m-d')) || ($data->format('Y-m-d') > $al->format('Y-m-d'))) {
                continue;
            }
            $scadenze[] = new Scadenza(
                    modulo: ModuloSuite::successioni,
                    riferimento: 'dichiarazione-' . $dichiarazione->id,
                    data: $data,
                    descrizione: 'Dichiarazione di successione (' . $dichiarazione->tipoDichiarazione->getLabel() . ')',
                    soggetto: $dichiarazione->defunto,
                    url: '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id,
                    completata: $dichiarazione->stato === StatoDichiarazioneSuccessione::presentata,
                    dettaglio: $dichiarazione->stato->getLabel(),
            );
        }
        return $scadenze;
    }
}
