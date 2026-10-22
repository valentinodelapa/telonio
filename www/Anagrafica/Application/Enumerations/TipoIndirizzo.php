<?php

namespace Anagrafica\Application\Enumerations;

enum TipoIndirizzo: string
{

    case residenza = 'RES';
    case domicilioFiscale = 'DOM';
    case sedeLegale = 'SL';
    case sedeAmministrativa = 'SA';
    case sedeOperativa = 'SO';
    case corrispondenza = 'COR';

    public function getLabel(): string
    {
        return match ($this) {
            self::residenza => 'Residenza anagrafica',
            self::domicilioFiscale => 'Domicilio fiscale',
            self::sedeLegale => 'Sede legale',
            self::sedeAmministrativa => 'Sede amministrativa',
            self::sedeOperativa => 'Sede operativa',
            self::corrispondenza => 'Recapito per la corrispondenza',
        };
    }

    public function isApplicabile(TipoSoggetto $tipoSoggetto): bool
    {
        return match ($this) {
            self::residenza => $tipoSoggetto->isPersonaFisica(),
            self::sedeLegale, self::sedeAmministrativa => $tipoSoggetto->isPersonaFisica() === false,
            default => true,
        };
    }
}
