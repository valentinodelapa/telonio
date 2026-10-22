<?php

namespace Deleghe\Application\Controllers;

use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Models\CausaleContributoModel;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ricerche per l'autocompletamento dei codici nelle righe della delega
 * (sedi, causali): restituiscono il codice come identificativo, perché le
 * righe memorizzano il codice e non il riferimento alla tabella.
 */
class TabelleF24Controller extends DelegheController
{

    private const LIMITE = 20;

    public function voci(TabellaF24 $tabella, string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new CodiceTabellaF24Model($this->dataMapper))->cerca($tabella, trim($testo), self::LIMITE) as $voce) {
                $risultati[] = ['id' => $voce->codice, 'etichetta' => $voce->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }

    public function causali(string $ente = CausaleContributo::ENTE_INPS, string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 1) {
            foreach ((new CausaleContributoModel($this->dataMapper))->cerca($ente, trim($testo), self::LIMITE) as $causale) {
                $risultati[] = ['id' => $causale->causale, 'etichetta' => $causale->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }
}
