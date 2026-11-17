<?php

namespace Dichiarativi\Application\Services\Isa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Concordato preventivo biennale (CPB) 2026-2027 nel modello ISA (quadro
 * P): con le dichiarazioni del contribuente di possedere i requisiti (P01)
 * e di non avere cause di esclusione (P02) il software di calcolo elabora
 * la proposta dell'Agenzia delle Entrate (redditi e valori della
 * produzione IRAP proposti per il 2026 e il 2027, P06-P09, con i redditi
 * rilevanti P04 e P05); l'adesione è l'accettazione della proposta (P10).
 *
 * Senza l'accettazione il quadro P non può essere compilato (allegato 2 al
 * provvedimento del 27 febbraio 2026, controllo 4.1.1): chi non aderisce
 * toglie i dati del quadro prima di allegare il modello. Con il quadro P
 * compilato sono obbligatori anche i suoi campi chiave: codice dell'ISA
 * (P0000001), codice attività (P0000002) e tipologia di reddito
 * (P0000003, 1 impresa, 2 lavoro autonomo).
 */
class ConcordatoIsa
{

    /** Campi della proposta e dei redditi rilevanti, copiati dal calcolo nell'adesione */
    public const PROPOSTA = ['P0100401', 'P0100501', 'P0100601', 'P0100701', 'P0100801', 'P0100901'];
    public const ACCETTAZIONE = 'P0101001';
    /** Campi del quadro P (escluse le chiavi P000000x) */
    private const QUADRO = ['P0100101', 'P0100201', 'P0100301', 'P0100401', 'P0100501', 'P0100601', 'P0100701', 'P0100801', 'P0100901', 'P0101001'];
    /** Campi chiave del quadro P: codice dell'ISA, codice attività, tipologia di reddito */
    public const CHIAVI = ['P0000001', 'P0000002', 'P0000003'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Proposta di CPB del calcolo: redditi e valori della produzione IRAP
     * proposti per il 2026 e il 2027 (campi P06-P09 restituiti dal
     * software), null se il calcolo non ha una proposta.
     *
     * @return array<string, string>|null codice => valore canonico
     */
    public static function getProposta(Dichiarazione $dichiarazione): ?array
    {
        if ($dichiarazione->calcoloIsa === null) {
            return null;
        }
        $proposta = [];
        foreach (self::PROPOSTA as $codice) {
            if (preg_match('/' . $codice . '(.{18})/', $dichiarazione->calcoloIsa, $parti) === 1) {
                $valore = rtrim(rtrim(str_replace(',', '.', trim($parti[1])), '0'), '.');
                $proposta[$codice] = $valore === '' ? '0' : $valore;
            }
        }
        return ((float) ($proposta['P0100601'] ?? 0) + (float) ($proposta['P0100701'] ?? 0)) > 0 ? $proposta : null;
    }

    public static function isAccettata(QuadriDichiarazione $quadri): bool
    {
        return $quadri->get(self::ACCETTAZIONE, 1) !== null;
    }

    /**
     * Quadro P compilato senza l'accettazione della proposta (non ammesso
     * nel modello allegato).
     */
    public static function isSenzaAccettazione(QuadriDichiarazione $quadri): bool
    {
        if (self::isAccettata($quadri)) {
            return false;
        }
        foreach (self::QUADRO as $codice) {
            if ($quadri->haCampo($codice) && ($quadri->get($codice, 1) !== null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adesione: la proposta del calcolo nel quadro P con l'accettazione.
     *
     * @throws \DomainException senza una proposta calcolata
     */
    public function aderisci(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true): void
    {
        $proposta = self::getProposta($dichiarazione) ?? throw new \DomainException('Nessuna proposta di concordato nel calcolo: indicare nel quadro P i requisiti (P01) e l\'assenza di cause di esclusione (P02) e calcolare il modello.');
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        foreach ($proposta as $codice => $valore) {
            if ($quadri->haCampo($codice)) {
                $quadri->imposta($codice, $valore === '0' ? null : $valore, 1, false);
            }
        }
        $quadri->imposta(self::ACCETTAZIONE, true, 1, false);
        $chiavi = ['P0000001' => $dichiarazione->isa, 'P0000002' => $quadri->get('Y0100101', 1), 'P0000003' => $quadri->haCampo('F0100101') ? 1 : 2];
        foreach ($chiavi as $codice => $valore) {
            if ($quadri->haCampo($codice)) {
                $quadri->imposta($codice, $valore, 1, false);
            }
        }
        if ($salva) {
            $quadri->salva();
        }
    }

    /**
     * Rinuncia o revoca dell'adesione: il quadro P si svuota.
     */
    public function rinuncia(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true): void
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        foreach (array_merge(self::QUADRO, self::CHIAVI) as $codice) {
            if ($quadri->haCampo($codice)) {
                $quadri->imposta($codice, null, 1, false);
            }
        }
        if ($salva) {
            $quadri->salva();
        }
    }
}
