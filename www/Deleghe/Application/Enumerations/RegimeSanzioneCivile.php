<?php

namespace Deleghe\Application\Enumerations;

/**
 * Regimi delle sanzioni civili per i contributi INPS e i premi INAIL (art.
 * 116, c. 8 e seguenti, L. 388/2000, come esposti dall'INPS):
 * - omissione (importi rilevabili da denunce o registrazioni): tasso di
 *   riferimento + 5,5 punti annui, tetto 40%; con pagamento spontaneo in
 *   unica soluzione entro 120 giorni, il solo tasso di riferimento;
 * - evasione denunciata spontaneamente entro 12 mesi: pagamento entro 30
 *   giorni dalla denuncia tasso + 5,5 punti, entro 90 giorni tasso + 7,5
 *   punti, tetto 40%;
 * - evasione: 30% annuo, tetto 60%.
 */
enum RegimeSanzioneCivile: string
{

    case omissione = 'O';
    case evasioneDenunciataTrentaGiorni = 'T';
    case evasioneDenunciataNovantaGiorni = 'N';
    case evasione = 'E';

    public const GIORNI_RIDUZIONE_SPONTANEA = 120;

    public function getLabel(): string
    {
        return match ($this) {
            self::omissione => 'Omissione (importi risultanti da denunce o registrazioni)',
            self::evasioneDenunciataTrentaGiorni => 'Evasione denunciata spontaneamente, pagamento entro 30 giorni dalla denuncia',
            self::evasioneDenunciataNovantaGiorni => 'Evasione denunciata spontaneamente, pagamento entro 90 giorni dalla denuncia',
            self::evasione => 'Evasione',
        };
    }

    /**
     * Punti annui da aggiungere al tasso di riferimento; null per la misura
     * fissa dell'evasione.
     */
    public function getMaggiorazione(bool $spontaneoEntro120Giorni): ?float
    {
        return match ($this) {
            self::omissione => $spontaneoEntro120Giorni ? 0.0 : 5.5,
            self::evasioneDenunciataTrentaGiorni => 5.5,
            self::evasioneDenunciataNovantaGiorni => 7.5,
            self::evasione => null,
        };
    }

    public function getMisuraFissa(): float
    {
        return 30.0;
    }

    /**
     * Tetto della sanzione in percentuale dei contributi.
     */
    public function getTetto(): float
    {
        return $this === self::evasione ? 60.0 : 40.0;
    }
}
