<?php

namespace GestioneStudio\Application\Services;

use CommSuite\Application\Entities\Studio;
use GestioneStudio\Application\Enumerations\FiltroStato;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Indicatori del modulo mostrati nella scrivania della suite: scadenze di
 * tutti i moduli ancora da completare.
 */
class RiepilogoScrivania
{

    public const GIORNI_SCADUTE = 365;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getIndicatori(Studio $studio): array
    {
        $oggi = new SismaDate();
        $voci = (new Scadenzario($this->dataMapper))->getVoci($studio, new SismaDate('-' . self::GIORNI_SCADUTE . ' days'), new SismaDate('+30 days'), filtro: FiltroStato::aperte);
        $conteggi = ['Scadute' => 0, 'Entro 7 giorni' => 0, 'Entro 30 giorni' => 0];
        $settimana = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');
        foreach ($voci as $voce) {
            if ($voce->isScaduta($oggi)) {
                $conteggi['Scadute']++;
                continue;
            }
            $conteggi['Entro 30 giorni']++;
            if ($voce->scadenza->data->format('Y-m-d') <= $settimana) {
                $conteggi['Entro 7 giorni']++;
            }
        }
        return $conteggi;
    }
}
