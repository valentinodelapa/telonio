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
 *
 * I campi dei modelli ISA sono anche per codice dell'ISA (i quadri degli
 * elementi specifici cambiano da un ISA all'altro) e hanno il codice
 * strutturato in modo diverso: quadro di tre caratteri (es. "F01"), rigo
 * di tre cifre, colonna di due (F0102001 = rigo F20, colonna 1); il quadro
 * è la lettera iniziale.
 */
class CampoModello extends BaseEntity
{

    protected int $id;
    protected ModelloDichiarativo $modello;
    protected int $anno;
    protected string $isa = '';
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
        return $this->modello === ModelloDichiarativo::isa ? (int) substr($this->codice, 3, 3) : self::rigoDelCodice($this->codice);
    }

    /**
     * Colonna (es. "1" per VE020001, "10A" per VX00410A, "2" per F0101702).
     */
    public function getColonna(): string
    {
        return ltrim($this->modello === ModelloDichiarativo::isa ? substr($this->codice, 6, 2) : substr($this->codice, 5, 3), '0');
    }

    /**
     * Etichetta del rigo come nel modello (es. "VE20", per gli ISA "F20",
     * "B03" e, per le variabili precalcolate, "IDF001").
     */
    public function getEtichettaRigo(): string
    {
        if ($this->modello === ModelloDichiarativo::isa) {
            return ctype_digit(substr($this->codice, 1, 2)) ? $this->quadro . sprintf('%02d', $this->getRigo()) : substr($this->codice, 0, 6);
        }
        return $this->quadro . $this->getRigo();
    }

    public static function rigoDelCodice(string $codice): int
    {
        return (int) substr($codice, 2, 3);
    }
}
