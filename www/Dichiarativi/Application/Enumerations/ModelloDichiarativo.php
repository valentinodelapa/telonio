<?php

namespace Dichiarativi\Application\Enumerations;

use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Modelli di dichiarazione gestiti dal modulo. Il valore è la sigla del
 * modello nel codice della fornitura telematica (record A, "IVA26",
 * "RPF26", ...); il catalogo dei campi è per modello e anno del modello
 * (l'anno successivo a quello d'imposta).
 */
enum ModelloDichiarativo: string
{

    case iva = 'IVA';
    case redditiPf = 'RPF';
    case ivaTr = 'TR';

    public function getLabel(): string
    {
        return match ($this) {
            self::iva => 'IVA annuale',
            self::redditiPf => 'Redditi Persone fisiche',
            self::ivaTr => 'Credito IVA trimestrale (rimborso o compensazione)',
        };
    }

    public function getSigla(): string
    {
        return match ($this) {
            self::iva => 'IVA',
            self::redditiPf => 'Redditi PF',
            self::ivaTr => 'IVA TR',
        };
    }

    /**
     * Modello già gestito dal modulo (proposta, controlli e file
     * telematico); gli altri hanno solo il catalogo dei campi.
     */
    public function isGestito(): bool
    {
        return in_array($this, [self::iva, self::ivaTr], true);
    }

    /**
     * Modelli trimestrali (con il trimestre nella dichiarazione).
     */
    public function isTrimestrale(): bool
    {
        return $this === self::ivaTr;
    }

    /** Ultima versione delle specifiche del modello IVA TR nel catalogo */
    public const ANNO_TR = 2025;

    /**
     * Anno del modello (e del catalogo dei campi) per l'anno d'imposta:
     * l'anno successivo per le dichiarazioni annuali, per il modello IVA TR
     * la versione delle specifiche in vigore (IVT25, dalle istanze del
     * primo trimestre 2025).
     */
    public static function getAnnoModello(int $annoImposta, ?self $modello = null): int
    {
        return $modello === self::ivaTr ? max(2025, min($annoImposta, self::ANNO_TR)) : $annoImposta + 1;
    }

    /**
     * Codice della fornitura nel record A (es. "IVA26", "IVT25").
     */
    public function getCodiceFornitura(int $annoImposta): string
    {
        return $this === self::ivaTr ? 'IVT' . substr((string) self::getAnnoModello($annoImposta, $this), -2)
                : $this->value . substr((string) self::getAnnoModello($annoImposta), -2);
    }

    /**
     * Tipo dei record a struttura variabile con i quadri.
     */
    public function getTipoRecordQuadri(): string
    {
        return match ($this) {
            self::iva => 'D',
            self::redditiPf, self::ivaTr => 'C',
        };
    }

    /**
     * Termine ordinario di presentazione telematica (art. 8, c. 1, e art.
     * 2, c. 2, D.P.R. 322/1998): 30 aprile per l'IVA, 31 ottobre per i
     * redditi dell'anno successivo; lo slittamento al primo giorno
     * lavorativo è applicato dallo scadenzario.
     */
    public function getScadenza(int $annoImposta, ?int $trimestre = null): SismaDate
    {
        // modello IVA TR: ultimo giorno del mese successivo al trimestre
        if ($this === self::ivaTr) {
            return new SismaDate((new \DateTimeImmutable(sprintf('%04d-%02d-01', $annoImposta, ($trimestre ?? 1) * 3 + 1)))->format('Y-m-t'));
        }
        return new SismaDate(($annoImposta + 1) . match ($this) {
            self::iva => '-04-30',
            self::redditiPf => '-10-31',
            self::ivaTr => '-04-30',
        });
    }

    /**
     * Quadri mostrati nella dichiarazione, nell'ordine del modello.
     *
     * @return string[]
     */
    public function getQuadri(): array
    {
        return match ($this) {
            self::iva => ['VA', 'VE', 'VF', 'VJ', 'VH', 'VL', 'VT', 'VX'],
            self::redditiPf => ['RA', 'RB', 'RC', 'RN', 'RV', 'RX'],
            self::ivaTr => ['TA', 'TB', 'TC', 'TD'],
        };
    }
}
