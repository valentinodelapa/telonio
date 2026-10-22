<?php

namespace Anagrafica\Application\Enumerations;

enum TipoSoggetto: string
{

    case personaFisica = 'PF';
    case societaPersone = 'SP';
    case societaCapitali = 'SC';
    case ente = 'EN';

    public function getLabel(): string
    {
        return match ($this) {
            self::personaFisica => 'Persona fisica',
            self::societaPersone => 'Società di persone',
            self::societaCapitali => 'Società di capitali',
            self::ente => 'Ente / associazione',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::personaFisica => 'bi-person',
            self::societaPersone => 'bi-people',
            self::societaCapitali => 'bi-buildings',
            self::ente => 'bi-bank',
        };
    }

    public function isPersonaFisica(): bool
    {
        return $this === self::personaFisica;
    }

    /**
     * @return TipoIndirizzo[]
     */
    public function getTipiIndirizzo(): array
    {
        return array_values(array_filter(TipoIndirizzo::cases(), fn(TipoIndirizzo $tipo) => $tipo->isApplicabile($this)));
    }

    /**
     * @return NaturaGiuridica[]
     */
    public function getNatureGiuridiche(): array
    {
        return array_values(array_filter(NaturaGiuridica::cases(), fn(NaturaGiuridica $natura) => $natura->getTipoSoggetto() === $this));
    }
}
