<?php

namespace CommSuite\Application\Services;

use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\ModuloSuite;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Scadenza esposta da un modulo (FonteScadenze).
 *
 * Il riferimento identifica la scadenza nel modulo in modo stabile (anche
 * se la data cambia, es. per una proroga): lo scadenzario vi collega lo
 * stato di lavorazione dello studio. La data è il termine effettivo, già
 * spostato al primo giorno lavorativo. Completata indica che il modulo
 * considera l'adempimento eseguito (es. delega trasmessa, dichiarazione
 * presentata).
 */
final class Scadenza
{

    public const LUNGHEZZA_RIFERIMENTO = 100;

    /**
     * @param BaseEntity|null $soggetto soggetto dell'Anagrafica a cui si riferisce
     * @param Utente|null $responsabile operatore dello studio indicato dal modulo
     */
    public function __construct(
            public readonly ModuloSuite $modulo,
            public readonly string $riferimento,
            public readonly SismaDate $data,
            public readonly string $descrizione,
            public readonly ?BaseEntity $soggetto = null,
            public readonly ?float $importo = null,
            public readonly ?string $url = null,
            public readonly bool $completata = false,
            public readonly ?string $dettaglio = null,
            public readonly ?Utente $responsabile = null,
    ) {
        if (($riferimento === '') || (strlen($riferimento) > self::LUNGHEZZA_RIFERIMENTO)) {
            throw new \InvalidArgumentException('Riferimento della scadenza vuoto o più lungo di ' . self::LUNGHEZZA_RIFERIMENTO . ' caratteri.');
        }
    }

    public function getChiave(): string
    {
        return $this->modulo->value . ':' . $this->riferimento;
    }
}
