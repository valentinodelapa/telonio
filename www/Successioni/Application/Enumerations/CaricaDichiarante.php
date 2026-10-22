<?php

namespace Successioni\Application\Enumerations;

/**
 * Codice di carica di chi presenta la dichiarazione (tabella CodiciCarica
 * delle specifiche SUC13).
 */
enum CaricaDichiarante: string
{

    case erede = '1';
    case legatario = '2';
    case rappresentanteLegatario = '3';
    case rappresentanteErede = '4';
    case curatoreEreditaGiacente = '5';
    case amministratoreEredita = '6';
    case esecutoreTestamentario = '7';
    case immessoPossessoBeniAssente = '8';
    case trustee = '9';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::erede => 'Erede, chiamato all\'eredità',
            self::legatario => 'Legatario',
            self::rappresentanteLegatario => 'Rappresentante legale o tutore di legatario',
            self::rappresentanteErede => 'Rappresentante legale, tutore, erede dell\'erede',
            self::curatoreEreditaGiacente => 'Curatore dell\'eredità giacente',
            self::amministratoreEredita => 'Amministratore dell\'eredità',
            self::esecutoreTestamentario => 'Esecutore testamentario',
            self::immessoPossessoBeniAssente => 'Immesso nel possesso temporaneo dei beni dell\'assente',
            self::trustee => 'Trustee o suo rappresentante',
        };
    }

    /**
     * Il dichiarante deve essere tra i beneficiari del quadro EA.
     */
    public function isBeneficiario(): bool
    {
        return in_array($this, [self::erede, self::legatario], true);
    }

    /**
     * Va indicato il codice fiscale del soggetto rappresentato.
     */
    public function richiedeRappresentato(): bool
    {
        return in_array($this, [self::rappresentanteLegatario, self::rappresentanteErede], true);
    }

    /**
     * Cariche gestite dalla procedura: trust, eredità giacente e assenza
     * hanno quadri e controlli propri, non ancora gestiti.
     *
     * @return self[]
     */
    public static function getGestite(): array
    {
        return [self::erede, self::legatario, self::rappresentanteLegatario, self::rappresentanteErede, self::amministratoreEredita, self::esecutoreTestamentario];
    }
}
