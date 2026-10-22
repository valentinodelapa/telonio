<?php

namespace Imu\Application\Entities;

use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Aliquota differenziata del prospetto: una fattispecie di base con le
 * condizioni stabilite dal comune (categoria, utilizzo, tipo di contratto,
 * requisiti del soggetto, ...), con la descrizione come riportata nel
 * prospetto. Le condizioni non si verificano in automatico: lo studio
 * sceglie l'aliquota per l'immobile (AliquotaApplicata).
 */
class AliquotaDifferenziata extends ReferencedEntity
{

    protected int $id;
    protected ProspettoAliquote $prospettoAliquote;
    protected ?FattispecieImu $fattispecie = null;
    protected string $descrizione;
    protected float $aliquota;
    protected int $ordine = 0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Descrizione su una riga, abbreviata per gli elenchi.
     */
    public function getEtichetta(int $lunghezza = 140): string
    {
        $testo = preg_replace('/\s+/', ' ', $this->descrizione);
        return number_format($this->aliquota, 2, ',', '') . '% - ' . (mb_strlen($testo) > $lunghezza ? mb_substr($testo, 0, $lunghezza - 1) . '…' : $testo);
    }
}
