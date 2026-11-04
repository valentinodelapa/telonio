<?php

namespace Dichiarativi\Application\Entities;

use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Campo non posizionale di un modello di dichiarazione per anno del
 * modello (catalogo ricavato dalle specifiche tecniche dell'Agenzia delle
 * Entrate con "sisma specifiche-dichiarativi"): codice di otto caratteri
 * (quadro, rigo di tre cifre, colonna di tre caratteri), descrizione e
 * formato del campo-valore. Il formato manca solo dove le specifiche non
 * lo indicano: quei campi non si trasmettono.
 */
class CampoModello extends BaseEntity
{

    protected int $id;
    protected ModelloDichiarativo $modello;
    protected int $anno;
    protected string $codice;
    protected string $quadro;
    protected string $descrizione = '';
    protected ?FormatoCampo $formato = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Numero del rigo (es. 20 per VE020001).
     */
    public function getRigo(): int
    {
        return self::rigoDelCodice($this->codice);
    }

    /**
     * Colonna (es. "1" per VE020001, "10A" per VX00410A).
     */
    public function getColonna(): string
    {
        return ltrim(substr($this->codice, 5, 3), '0');
    }

    /**
     * Etichetta del rigo come nel modello (es. "VE20").
     */
    public function getEtichettaRigo(): string
    {
        return $this->quadro . $this->getRigo();
    }

    public static function rigoDelCodice(string $codice): int
    {
        return (int) substr($codice, 2, 3);
    }
}
