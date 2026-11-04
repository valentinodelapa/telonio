<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Liquidazione dell'IVA di gruppo (art. 73, c. 3, D.P.R. 633/1972; D.M.
 * 13 dicembre 1979, modificato dal D.M. 13 febbraio 2017): l'opzione
 * della controllante, comunicata nel quadro VG della dichiarazione IVA
 * presentata nel primo anno, vale fino a revoca; le controllate entrano o
 * escono dal 1° gennaio (PartecipanteGruppoIva). La maggioranza della
 * controllante posseduta da un soggetto che rinuncia alla procedura va nel
 * rigo VG1.
 */
class GruppoIva extends ReferencedEntity
{

    public const ORIGINE = 'Contabilita';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $controllante;
    protected int $annoInizio;
    protected ?int $annoRevoca = null;
    protected ?Soggetto $maggioranza = null;
    protected ?SismaDate $dataPossessoMaggioranza = null;
    protected ?float $percentualeMaggioranza = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isAttivoNel(int $anno): bool
    {
        return ($this->annoInizio <= $anno) && (($this->annoRevoca === null) || ($this->annoRevoca > $anno));
    }

    public function getEtichetta(): string
    {
        return 'Gruppo IVA ' . $this->controllante->nominativo . ' (dal ' . $this->annoInizio . ($this->annoRevoca === null ? '' : ', revocato dal ' . $this->annoRevoca) . ')';
    }

    public static function getRiferimento(int $anno, int $periodo): string
    {
        return 'iva-gruppo-' . $anno . '-' . $periodo;
    }
}
