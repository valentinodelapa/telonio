<?php

namespace Successioni\Application\Enumerations;

/**
 * Agevolazioni e riduzioni della sezione devoluzione (allegato 2 alle
 * istruzioni; tabelle CodiciAgevolazioni e CodiciRiduzioni25AltriComma
 * delle specifiche SUC13), con gli effetti sulle imposte:
 * - imposta di successione: esenzione (A, C, D, E, G, H, N) o riduzione
 *   dell'imposta sulla quota (L 50%, Q 40%, R e F 40% fino a 103.291
 *   euro di valore), secondo i passaggi di calcolo dell'Agenzia;
 * - imposte ipotecaria e catastale (quadro EF, sezioni I e II):
 *   proporzionali, fisse (G, M, prima casa) o non dovute (C, D, E, H).
 */
enum Agevolazione: string
{

    case beniCulturali = 'A';
    case compendioUnicoMontano = 'C';
    case aziendeQuote = 'D';
    case compendioUnico = 'E';
    case giovaniAgricoltori = 'G';
    case inagibiliSisma = 'H';
    case fondiMontani = 'M';
    case boschiMontani = 'N';
    case primaCasa = 'P';
    case primaCasaContiguo = 'Z';
    case pertinenzaPrimaCasa = 'X';
    case pertinenzaSenzaPrincipale = 'Y';
    case impresaArtigianaFamiliare = 'F';
    case beniCulturaliVincoloPosteriore = 'L';
    case aziendeMontane = 'Q';
    case fondiRustici = 'R';

    public const VALORE_MASSIMO_RIDUZIONE = 103291.0;

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::beniCulturali => 'Beni culturali (vincolo anteriore all\'apertura della successione)',
            self::compendioUnicoMontano => 'Terreni agricoli "compendio unico" in zone montane',
            self::aziendeQuote => 'Aziende, quote sociali e azioni (passaggio generazionale)',
            self::compendioUnico => 'Terreni agricoli "compendio unico"',
            self::giovaniAgricoltori => 'Terreni/aziende agricole a giovani imprenditori agricoli professionali',
            self::inagibiliSisma => 'Immobili demoliti o inagibili per eventi sismici o calamitosi',
            self::fondiMontani => 'Fondi rustici in territori montani',
            self::boschiMontani => 'Boschi in terreni montani',
            self::primaCasa => 'Abitazione "prima casa"',
            self::primaCasaContiguo => 'Abitazione "prima casa" - immobile contiguo',
            self::pertinenzaPrimaCasa => 'Pertinenza "prima casa" (con l\'immobile principale)',
            self::pertinenzaSenzaPrincipale => 'Pertinenza "prima casa" (senza l\'immobile principale)',
            self::impresaArtigianaFamiliare => 'Immobili di impresa artigiana familiare (riduzione)',
            self::beniCulturaliVincoloPosteriore => 'Beni culturali con vincolo posteriore (riduzione)',
            self::aziendeMontane => 'Aziende e quote in comuni montani sotto i 5.000 abitanti (riduzione)',
            self::fondiRustici => 'Fondi rustici (riduzione)',
        };
    }

    /**
     * La quota non concorre all'imposta di successione.
     */
    public function isEsenteSuccessione(): bool
    {
        return in_array($this, [self::beniCulturali, self::compendioUnicoMontano, self::aziendeQuote, self::compendioUnico, self::giovaniAgricoltori,
            self::inagibiliSisma, self::boschiMontani], true);
    }

    /**
     * Percentuale di riduzione dell'imposta di successione sulla quota (0
     * se l'agevolazione non è una riduzione).
     */
    public function getRiduzione(): float
    {
        return match ($this) {
            self::beniCulturaliVincoloPosteriore => 0.5,
            self::aziendeMontane, self::fondiRustici, self::impresaArtigianaFamiliare => 0.4,
            default => 0.0,
        };
    }

    /**
     * La riduzione si calcola al massimo su 103.291 euro di valore (fondi
     * rustici e impresa artigiana familiare).
     */
    public function hasValoreMassimoRiduzione(): bool
    {
        return in_array($this, [self::fondiRustici, self::impresaArtigianaFamiliare], true);
    }

    public function isPrimaCasa(): bool
    {
        return in_array($this, [self::primaCasa, self::primaCasaContiguo, self::pertinenzaPrimaCasa, self::pertinenzaSenzaPrincipale], true);
    }

    /**
     * Le imposte ipotecaria e catastale si applicano in misura
     * proporzionale (2% e 1%).
     */
    public static function isIpocatastaleProporzionale(?self $agevolazione): bool
    {
        return ($agevolazione === null) || in_array($agevolazione, [self::beniCulturali, self::beniCulturaliVincoloPosteriore, self::fondiRustici, self::impresaArtigianaFamiliare,
                    self::boschiMontani, self::aziendeMontane], true);
    }

    /**
     * Esente dall'imposta di bollo per le circoscrizioni (quadro EF,
     * sezione IV).
     */
    public function isEsenteBollo(): bool
    {
        return in_array($this, [self::compendioUnicoMontano, self::fondiMontani, self::giovaniAgricoltori, self::compendioUnico, self::inagibiliSisma], true);
    }

    /**
     * Agevolazioni che escludono ogni altra sullo stesso bene (le altre, F,
     * G, Q e R, possono coesistere).
     */
    public function isEsclusiva(): bool
    {
        return in_array($this, [self::beniCulturali, self::compendioUnicoMontano, self::aziendeQuote, self::compendioUnico, self::beniCulturaliVincoloPosteriore,
            self::fondiMontani, self::boschiMontani, self::primaCasa, self::primaCasaContiguo, self::pertinenzaPrimaCasa, self::pertinenzaSenzaPrincipale], true);
    }
}
