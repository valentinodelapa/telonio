<?php

namespace Dichiarativi\Application\Enumerations;

use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Modelli di dichiarazione gestiti dal modulo. Il valore è la sigla del
 * modello nel codice della fornitura telematica (record A, "IVA26",
 * "RPF26", ...); il catalogo dei campi è per modello e anno del modello
 * (l'anno successivo a quello d'imposta).
 *
 * Il modello ISA (indici sintetici di affidabilità fiscale) non ha una
 * fornitura propria: i suoi record P, Q ed R sono allegati a quelli della
 * dichiarazione Redditi; il catalogo è anche per codice dell'ISA.
 */
enum ModelloDichiarativo: string
{

    case iva = 'IVA';
    case redditiPf = 'RPF';
    case ivaTr = 'TR';
    case isa = 'ISA';
    case redditiSp = 'RSP';
    case redditiSc = 'RSC';
    case consolidato = 'CNM';
    case irap = 'IRA';
    case certificazioneUnica = 'CUR';
    case modello770 = '770';
    case redditiEnc = 'ENC';

    public function getLabel(): string
    {
        return match ($this) {
            self::iva => 'IVA annuale',
            self::redditiPf => 'Redditi Persone fisiche',
            self::ivaTr => 'Credito IVA trimestrale (rimborso o compensazione)',
            self::isa => 'Indici sintetici di affidabilità fiscale',
            self::redditiSp => 'Redditi Società di persone',
            self::redditiSc => 'Redditi Società di capitali',
            self::consolidato => 'Consolidato nazionale e mondiale',
            self::irap => 'Imposta regionale sulle attività produttive',
            self::certificazioneUnica => "Certificazione Unica (sostituto d'imposta)",
            self::modello770 => "Dichiarazione dei sostituti d'imposta (770)",
            self::redditiEnc => 'Redditi Enti non commerciali ed equiparati',
        };
    }

    public function getSigla(): string
    {
        return match ($this) {
            self::iva => 'IVA',
            self::redditiPf => 'Redditi PF',
            self::ivaTr => 'IVA TR',
            self::isa => 'ISA',
            self::redditiSp => 'Redditi SP',
            self::redditiSc => 'Redditi SC',
            self::consolidato => 'CNM',
            self::irap => 'IRAP',
            self::certificazioneUnica => 'CU',
            self::modello770 => '770',
            self::redditiEnc => 'Redditi ENC',
        };
    }

    /**
     * Modello già gestito dal modulo (proposta, controlli e file
     * telematico); gli altri hanno solo il catalogo dei campi.
     */
    public function isGestito(): bool
    {
        return in_array($this, [self::iva, self::ivaTr, self::redditiPf, self::redditiSp, self::redditiSc, self::consolidato, self::irap, self::certificazioneUnica, self::modello770, self::redditiEnc], true);
    }

    /**
     * Modelli dei sostituti d'imposta (Certificazione Unica e 770): niente
     * versamenti né crediti propri (le ritenute si versano mese per mese
     * dalla Contabilità), fornitura a record A, B, D (H per la CU) e Z.
     */
    public function isSostituti(): bool
    {
        return in_array($this, [self::certificazioneUnica, self::modello770], true);
    }

    /**
     * Modelli delle imposte sui redditi (Redditi PF, SP, SC, ENC,
     * Consolidato nazionale e mondiale) e IRAP: stessi termini di presentazione e
     * versamenti con le deleghe F24 (saldo, acconti, crediti); i modelli
     * Redditi hanno la fornitura a record A, B, C (P, Q, R dei modelli ISA)
     * e Z, l'IRAP la fornitura XML.
     */
    public function isRedditi(): bool
    {
        return in_array($this, [self::redditiPf, self::redditiSp, self::redditiSc, self::redditiEnc, self::consolidato, self::irap], true);
    }

    /**
     * Modelli Redditi delle società (SP e SC) e degli enti non commerciali
     * (ENC), con il frontespizio dei soggetti diversi dalle persone fisiche.
     */
    public function isRedditiSocieta(): bool
    {
        return in_array($this, [self::redditiSp, self::redditiSc, self::redditiEnc], true);
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
     * Codice della fornitura nel record A (es. "IVA26", "IVT25"; Redditi
     * ENC "RNC26").
     */
    public function getCodiceFornitura(int $annoImposta): string
    {
        return match ($this) {
            self::ivaTr => 'IVT' . substr((string) self::getAnnoModello($annoImposta, $this), -2),
            self::redditiEnc => 'RNC' . substr((string) self::getAnnoModello($annoImposta), -2),
            default => $this->value . substr((string) self::getAnnoModello($annoImposta), -2),
        };
    }

    /**
     * Tipo dei record a struttura variabile con i quadri.
     */
    public function getTipoRecordQuadri(): string
    {
        return match ($this) {
            self::iva => 'D',
            self::redditiPf, self::ivaTr, self::redditiSp, self::redditiSc, self::consolidato, self::redditiEnc => 'C',
            // fornitura XML: nessun record a struttura variabile
            self::irap => '',
            self::isa => 'Q',
            // CU: dati del percipiente nel record D, lavoro autonomo nel record H; 770: prospetti ST, SV, SX nel record D, SS nel record J
            self::certificazioneUnica => 'H',
            self::modello770 => 'D',
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
            self::redditiPf, self::isa, self::redditiSp, self::redditiSc, self::consolidato, self::irap, self::redditiEnc => '-10-31',
            self::ivaTr => '-04-30',
            // CU: 16 marzo, termine di consegna al percipiente e di invio (30 aprile per le sole certificazioni di lavoro autonomo abituale e provvigioni non occasionali, istruzioni CU 2026 par. 2.1); 770: 31 ottobre
            self::certificazioneUnica => '-03-16',
            self::modello770 => '-10-31',
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
            self::redditiPf => ['RA', 'RB', 'RC', 'LC', 'RN', 'RV', 'RX'],
            self::ivaTr => ['TA', 'TB', 'TC', 'TD'],
            // frontespizio, personale, unità locali, elementi specifici, dati contabili, concordato preventivo biennale
            self::isa => ['Y', 'A', 'B', 'C', 'D', 'E', 'F', 'H', 'P'],
            self::redditiSp => ['RA', 'RB', 'RD', 'RE', 'RF', 'RG', 'RH', 'RJ', 'RL', 'RM', 'RN', 'RO', 'RP', 'RQ', 'RS', 'RT', 'RU', 'RV', 'RW', 'RX', 'AC', 'CP', 'DI', 'FC', 'OP', 'TR'],
            // consolidato nazionale (NF, NX, NI, crediti esteri NR, NE, NC), mondiale (MF, MX, MR, ME, MC), imposta (CC, CN), prospetti (CK, CS)
            // società di persone (IP), di capitali (IC), enti (IE), amministrazioni (IK), ripartizione e versamenti (IR), prospetti (IS)
            self::irap => ['IP', 'IC', 'IE', 'IK', 'IR', 'IS'],
            self::consolidato => ['NF', 'NX', 'NI', 'NR', 'NE', 'NC', 'MF', 'MX', 'MR', 'ME', 'MC', 'CC', 'CN', 'CK', 'CS'],
            // CU: dati anagrafici del percipiente (DA), lavoro autonomo, provvigioni e redditi diversi (AU)
            self::certificazioneUnica => ['DA', 'AU'],
            // 770: versamenti delle ritenute (ST, SV), riepilogo dei crediti e delle compensazioni (SX), riepilogo dei dati (SS)
            self::modello770 => ['ST', 'SV', 'SX', 'SS'],
            // terreni, fabbricati, impresa (RF, RG), partecipazioni, altri redditi, IRES, amministratori, prospetti, crediti, risultato
            self::redditiEnc => ['RA', 'RB', 'RC', 'RD', 'RE', 'RF', 'RG', 'RH', 'RL', 'RM', 'RN', 'RO', 'RQ', 'RS', 'RT', 'RU', 'RW', 'RX', 'RZ', 'AC', 'CE', 'CP', 'DI', 'FC', 'OP', 'PN', 'TR', 'VO'],
            self::redditiSc => ['RA', 'RB', 'RF', 'RH', 'RI', 'RJ', 'RK', 'RL', 'RM', 'RN', 'RO', 'RQ', 'RS', 'RT', 'RU', 'RV', 'RX', 'RZ', 'AC', 'CE', 'CP', 'DI', 'FC', 'GC', 'GN', 'NI', 'OP', 'PN', 'TN', 'TR'],
        };
    }
}
