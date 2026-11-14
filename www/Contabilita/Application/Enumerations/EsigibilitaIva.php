<?php

namespace Contabilita\Application\Enumerations;

use Contabilita\Application\HelperClasses\RiferimentiIva;

/**
 * Esigibilità dell'IVA del documento (campo EsigibilitaIVA della fattura
 * elettronica): immediata, differita (art. 6, c. 5, D.P.R. 633/1972; dal
 * 2027 art. 24, c. 5, D.Lgs. 10/2026), per cassa (art. 32-bis D.L.
 * 83/2012; dal 2027 art. 132 D.Lgs. 10/2026: nel file della fattura "D",
 * nelle registrazioni distinta per il limite di un anno e per gli
 * acquisti) o scissione dei pagamenti (art. 17-ter; dal 2027 art. 65), in
 * cui l'imposta è versata dal cessionario e non entra nella liquidazione
 * del cedente. L'etichetta cita la norma in vigore alla data indicata (di
 * default oggi).
 */
enum EsigibilitaIva: string
{

    case immediata = 'I';
    case differita = 'D';
    case scissionePagamenti = 'S';
    case perCassa = 'C';

    public function getLabel(\DateTimeInterface|int|null $quando = null): string
    {
        return match ($this) {
            self::immediata => 'Immediata',
            self::differita => 'Differita (' . RiferimentiIva::cita('esigibilitaDifferita', $quando) . ')',
            self::scissionePagamenti => 'Scissione dei pagamenti (' . RiferimentiIva::cita('scissionePagamenti', $quando) . ')',
            self::perCassa => 'Per cassa (' . RiferimentiIva::cita('ivaPerCassa', $quando) . ')',
        };
    }

    /**
     * L'imposta matura al pagamento del corrispettivo (Services\IvaSospesa).
     */
    public function isSospesa(): bool
    {
        return ($this === self::differita) || ($this === self::perCassa);
    }

    /**
     * Valore del campo EsigibilitaIVA della fattura elettronica.
     */
    public function getValoreFattura(): string
    {
        return $this === self::perCassa ? self::differita->value : $this->value;
    }
}
