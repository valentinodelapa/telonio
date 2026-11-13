<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\ModalitaPagamento;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Rata della partita di un documento: piano dei pagamenti con scadenza,
 * importo e modalità, letto dai dati di pagamento della fattura
 * elettronica, dai termini abituali della controparte o indicato nella
 * prima nota. Gli abbinamenti restano sulla partita e il residuo si
 * ripartisce sulle rate in ordine di scadenza (Services\ScadenzePagamento).
 * La partita senza rate vale come rata unica alla sua scadenza. Il soggetto
 * è copiato dalla partita per leggere lo scadenzario con una sola query.
 */
class ScadenzaPartita extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected Partita $partita;
    protected int $numero = 1;
    protected SismaDate $dataScadenza;
    protected float $importo = 0.0;
    protected ?ModalitaPagamento $modalita = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
