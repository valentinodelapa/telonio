<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Finalità del cinque per mille dell'IRPEF (scheda per la scelta della
 * destinazione, modello Redditi PF 2026), con le posizioni nel record B
 * della casella e del codice fiscale del beneficiario (le attività sociali
 * del comune di residenza non lo prevedono).
 */
enum CinquePerMille: string
{

    case terzoSettore = 'TS';
    case ricercaScientifica = 'RS';
    case ricercaSanitaria = 'RN';
    case beniCulturali = 'BC';
    case comuneResidenza = 'CO';
    case sportDilettantistico = 'SD';
    case areeProtette = 'AP';

    public function getLabel(): string
    {
        return match ($this) {
            self::terzoSettore => 'Sostegno degli enti del Terzo settore',
            self::ricercaScientifica => 'Finanziamento della ricerca scientifica e dell\'università',
            self::ricercaSanitaria => 'Finanziamento della ricerca sanitaria',
            self::beniCulturali => 'Tutela, promozione e valorizzazione dei beni culturali e paesaggistici',
            self::comuneResidenza => 'Attività sociali del comune di residenza',
            self::sportDilettantistico => 'Associazioni sportive dilettantistiche',
            self::areeProtette => 'Enti gestori delle aree protette',
        };
    }

    public function getPosizione(): int
    {
        return match ($this) {
            self::terzoSettore => 704,
            self::ricercaScientifica => 716,
            self::ricercaSanitaria => 728,
            self::beniCulturali => 740,
            self::comuneResidenza => 752,
            self::sportDilettantistico => 753,
            self::areeProtette => 765,
        };
    }

    /**
     * Posizione del codice fiscale del beneficiario (null se non previsto).
     */
    public function getPosizioneCodiceFiscale(): ?int
    {
        return $this === self::comuneResidenza ? null : ($this === self::sportDilettantistico ? 754 : $this->getPosizione() + 1);
    }
}
