<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Società controllata che partecipa alla liquidazione IVA di gruppo dal 1°
 * gennaio dell'anno di ingresso fino all'anno precedente quello di uscita
 * o, se il controllo cessa in corso d'anno, fino al mese (o trimestre)
 * precedente quello della cessazione (art. 3 D.M. 13 dicembre 1979). Le
 * società che partecipano alla sola catena di controllo vanno nella
 * sezione II del quadro VG.
 */
class PartecipanteGruppoIva extends BaseEntity
{

    protected int $id;
    protected GruppoIva $gruppoIva;
    protected Soggetto $soggetto;
    protected int $annoIngresso;
    protected ?int $annoUscita = null;
    protected ?SismaDate $dataCessazione = null;
    protected ?SismaDate $dataPossesso = null;
    protected ?float $percentualePossesso = null;
    protected ?Soggetto $detentore = null;
    protected bool $detentoreEstero = false;
    protected bool $soloCatena = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Partecipa alla compensazione per almeno una parte dell'anno.
     */
    public function partecipaNel(int $anno): bool
    {
        return ($this->soloCatena === false) && $this->gruppoIva->isAttivoNel($anno) && ($this->annoIngresso <= $anno)
                && (($this->annoUscita === null) || ($this->annoUscita > $anno))
                && (($this->dataCessazione === null) || ((int) $this->dataCessazione->format('Y') >= $anno));
    }

    /**
     * Ultimo mese di controllo dell'anno (12 per l'intero anno): con la
     * cessazione in corso d'anno l'ultimo mese, o l'ultimo mese
     * dell'ultimo trimestre, precedente quello della cessazione.
     */
    public function getUltimoMese(int $anno, PeriodicitaIva $periodicita): int
    {
        if (($this->dataCessazione === null) || ((int) $this->dataCessazione->format('Y') > $anno)) {
            return 12;
        }
        $mese = (int) $this->dataCessazione->format('n');
        return $periodicita === PeriodicitaIva::trimestrale ? intdiv($mese - 1, 3) * 3 : $mese - 1;
    }

    /**
     * Il periodo di liquidazione (mese o trimestre) è nel periodo di
     * controllo.
     */
    public function isPeriodoControllato(int $anno, PeriodicitaIva $periodicita, int $periodo): bool
    {
        if ($this->partecipaNel($anno) === false) {
            return false;
        }
        $ultimoMese = $periodicita === PeriodicitaIva::trimestrale ? $periodo * 3 : $periodo;
        return $ultimoMese <= $this->getUltimoMese($anno, $periodicita);
    }
}
