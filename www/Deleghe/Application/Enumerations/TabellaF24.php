<?php

namespace Deleghe\Application\Enumerations;

/**
 * Tabelle di decodifica del modello F24 importate dall'archivio
 * dell'Agenzia delle Entrate (CodiceTabellaF24). Le tabelle T0-T6 servono
 * anche come "codice territoriale" nel campo rateazione e nel codice ente
 * della sezione IMU.
 */
enum TabellaF24: string
{

    case regioni = 'T0';
    case entiLocali = 'T1';
    case province = 'T2';
    case provinceDirittiCamerali = 'T6';
    case sediInps = 'SINP';
    case sediInail = 'SINA';
    case sediEnpals = 'SENP';
    case altriEnti = 'ENTI';
    case tipiElide = 'TELI';
    case uffici = 'UFFI';
    case formatiMatricolaInps = 'FINP';

    public function getLabel(): string
    {
        return match ($this) {
            self::regioni => 'T0 - Regioni e province autonome',
            self::entiLocali => 'T1 - Enti locali',
            self::province => 'T2 - Sigle delle province',
            self::provinceDirittiCamerali => 'T6 - Province per diritti camerali',
            self::sediInps => 'Sedi INPS',
            self::sediInail => 'Sedi INAIL',
            self::sediEnpals => 'Sedi ENPALS',
            self::altriEnti => 'Altri enti previdenziali e assicurativi',
            self::tipiElide => 'Tipi di versamento F24 ELIDE',
            self::uffici => 'Uffici finanziari',
            self::formatiMatricolaInps => 'Formati della matricola INPS',
        };
    }

    /**
     * Tabella indicata nei formalismi dei codici tributo ("T0", "T4", ...);
     * null per T4 (comuni, da Anagrafica) e per le tabelle non gestite.
     */
    public static function daTerritoriale(string $sigla): ?self
    {
        return match ($sigla) {
            'T0' => self::regioni,
            'T1' => self::entiLocali,
            'T2' => self::province,
            'T6' => self::provinceDirittiCamerali,
            default => null,
        };
    }
}
