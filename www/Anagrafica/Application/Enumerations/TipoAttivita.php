<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Attività economica esercitata da una persona fisica titolare di partita
 * IVA. L'impresa individuale non è un soggetto distinto dal suo titolare
 * (stesso codice fiscale): è un dato storicizzato della persona fisica.
 */
enum TipoAttivita: string
{

    case impresaIndividuale = 'IMP';
    case impresaFamiliare = 'IFA';
    case lavoroAutonomo = 'LAV';

    public function getLabel(): string
    {
        return match ($this) {
            self::impresaIndividuale => 'Impresa individuale',
            self::impresaFamiliare => 'Impresa familiare',
            self::lavoroAutonomo => 'Lavoratore autonomo / professionista',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::impresaIndividuale, self::impresaFamiliare => 'bi-shop',
            self::lavoroAutonomo => 'bi-briefcase',
        };
    }

    public function isImpresa(): bool
    {
        return $this !== self::lavoroAutonomo;
    }
}
