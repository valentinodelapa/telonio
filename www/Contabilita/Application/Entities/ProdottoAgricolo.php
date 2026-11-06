<?php

namespace Contabilita\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Prodotto agricolo o ittico con la percentuale di compensazione (art. 34):
 * tabella della suite, aggiornata da "sisma tabelle-contabilita". La validità
 * per anni serve ai prodotti con percentuali variate nel tempo; senza anni
 * vale sempre.
 */
class ProdottoAgricolo extends BaseEntity
{

    protected int $id;
    protected ?string $voce = null;
    protected string $descrizione;
    protected float $percentuale = 0.0;
    protected ?int $dalAnno = null;
    protected ?int $alAnno = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isValidoNel(int $anno): bool
    {
        return (($this->dalAnno === null) || ($this->dalAnno <= $anno)) && (($this->alAnno === null) || ($this->alAnno >= $anno));
    }
}
