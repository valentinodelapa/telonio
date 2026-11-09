<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Aliquote dell'addizionale comunale all'IRPEF del comune per l'anno
 * d'imposta (elenco dell'Agenzia delle Entrate): valgono per il saldo
 * dell'anno e per l'acconto dell'anno successivo.
 */
class AddizionaleComunale extends BaseEntity
{

    protected int $id;
    protected int $anno;
    protected string $codiceCatastale;
    protected ?float $aliquotaUnica = null;
    protected ?string $aliquote = null;
    protected ?int $tipologia = null;
    protected ?float $esenzione = null;
    protected ?string $tipizzazione = null;
    protected ?float $esenzioneCasi = null;
    protected ?string $casiParticolari = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isPerScaglioni(): bool
    {
        return $this->aliquote !== null;
    }

    /**
     * Scaglioni come quelli dell'IRPEF: quattro (15.000, 28.000, 50.000)
     * con la tipologia 1, tre (28.000, 50.000) con la tipologia 2.
     *
     * @return list<array{0: ?float, 1: float}> [limite, aliquota]
     */
    public function getScaglioni(): array
    {
        if ($this->aliquote === null) {
            return [[null, (float) $this->aliquotaUnica]];
        }
        $limiti = $this->tipologia === 2 ? [28000.0, 50000.0, null] : [15000.0, 28000.0, 50000.0, null];
        return array_map(fn(?float $limite, string $aliquota) => [$limite, (float) $aliquota], $limiti, explode('|', $this->aliquote));
    }
}
