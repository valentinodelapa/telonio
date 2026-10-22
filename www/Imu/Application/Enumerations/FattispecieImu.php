<?php

namespace Imu\Application\Enumerations;

/**
 * Fattispecie di base dell'IMU (art. 1, commi 748-755, L. 160/2019): sono
 * le voci di base del prospetto delle aliquote (D.M. 7 luglio 2023,
 * allegato A) e determinano il codice tributo del versamento.
 *
 * Enum e non tabella: elenco fissato per legge, con comportamento
 * (aliquota di legge, codice tributo, colonna del prospetto).
 */
enum FattispecieImu: string
{

    case abitazionePrincipale = 'AP';
    case ruraliStrumentali = 'RS';
    case gruppoD = 'GD';
    case terreniAgricoli = 'TA';
    case areeFabbricabili = 'AF';
    case altriFabbricati = 'AL';
    case beniMerce = 'BM';

    /**
     * Aliquota riservata allo Stato sui fabbricati del gruppo D (comma 753).
     */
    public const ALIQUOTA_STATO_GRUPPO_D = 0.76;

    public function getLabel(): string
    {
        return match ($this) {
            self::abitazionePrincipale => 'Abitazione principale A/1, A/8, A/9 e pertinenze',
            self::ruraliStrumentali => 'Fabbricati rurali ad uso strumentale',
            self::gruppoD => 'Fabbricati del gruppo catastale D',
            self::terreniAgricoli => 'Terreni agricoli',
            self::areeFabbricabili => 'Aree fabbricabili',
            self::altriFabbricati => 'Altri fabbricati',
            self::beniMerce => 'Fabbricati merce',
        };
    }

    /**
     * Aliquota di base di legge (commi 748-754), applicata se per il comune
     * non è disponibile il prospetto.
     */
    public function getAliquotaBase(): float
    {
        return match ($this) {
            self::abitazionePrincipale => 0.5,
            self::ruraliStrumentali, self::beniMerce => 0.1,
            self::terreniAgricoli => 0.76,
            default => 0.86,
        };
    }

    /**
     * Codice tributo F24 della quota del comune (risoluzione 29/E/2020).
     */
    public function getCodiceTributo(): string
    {
        return match ($this) {
            self::abitazionePrincipale => '3912',
            self::ruraliStrumentali => '3913',
            self::terreniAgricoli => '3914',
            self::areeFabbricabili => '3916',
            self::altriFabbricati => '3918',
            self::gruppoD => '3930',
            self::beniMerce => '3939',
        };
    }

    /**
     * Codice tributo della quota dello Stato (solo gruppo D).
     */
    public function getCodiceTributoStato(): ?string
    {
        return $this === self::gruppoD ? '3925' : null;
    }

    /**
     * Proprietà di ProspettoAliquote con l'aliquota della fattispecie.
     */
    public function getProprietaProspetto(): string
    {
        return match ($this) {
            self::abitazionePrincipale => 'aliquotaAbitazionePrincipale',
            self::ruraliStrumentali => 'aliquotaRuraliStrumentali',
            self::gruppoD => 'aliquotaGruppoD',
            self::terreniAgricoli => 'aliquotaTerreniAgricoli',
            self::areeFabbricabili => 'aliquotaAreeFabbricabili',
            self::altriFabbricati => 'aliquotaAltriFabbricati',
            self::beniMerce => 'aliquotaBeniMerce',
        };
    }

    /**
     * Inizio della voce nel prospetto pubblicato (base e differenziate).
     */
    public function getVoceProspetto(): string
    {
        return match ($this) {
            self::abitazionePrincipale => 'Abitazione principale di categoria catastale A/1, A/8 e A/9 e relative pertinenze',
            self::ruraliStrumentali => 'Fabbricati rurali ad uso strumentale (inclusa la categoria catastale D/10)',
            self::gruppoD => 'Fabbricati appartenenti al gruppo catastale D (esclusa la categoria catastale D/10)',
            self::terreniAgricoli => 'Terreni agricoli',
            self::areeFabbricabili => 'Aree fabbricabili',
            self::altriFabbricati => 'Altri fabbricati (fabbricati diversi dall\'abitazione principale e dai fabbricati appartenenti al gruppo catastale D)',
            self::beniMerce => 'Fabbricati costruiti e destinati dall\'impresa costruttrice alla vendita',
        };
    }

    /**
     * Fattispecie che il prospetto prevede nell'anno: i fabbricati merce
     * sono esenti dal 2022 (comma 751).
     *
     * @return self[]
     */
    public static function perAnno(int $anno): array
    {
        return array_values(array_filter(self::cases(), fn(self $fattispecie) => ($fattispecie !== self::beniMerce) || ($anno < 2022)));
    }
}
