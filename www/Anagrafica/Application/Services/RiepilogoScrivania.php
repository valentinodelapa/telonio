<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ImmobileModel;
use Anagrafica\Application\Models\SoggettoModel;
use CommSuite\Application\Entities\Studio;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Indicatori del modulo mostrati nella scrivania della suite.
 */
class RiepilogoScrivania
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getIndicatori(Studio $studio): array
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        return [
            'Clienti' => $soggettoModel->countByStudioAndClienteStudio($studio, true),
            'Persone fisiche' => $soggettoModel->countByStudioAndTipoSoggetto($studio, TipoSoggetto::personaFisica),
            'Totale soggetti' => $soggettoModel->countByStudio($studio),
            'Immobili' => (new ImmobileModel($this->dataMapper))->countByStudio($studio),
        ];
    }
}
