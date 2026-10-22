<?php

namespace Successioni\Application\Enumerations;

/**
 * Grado di parentela del beneficiario con il defunto (allegato 1 alle
 * istruzioni, tabella GradoParentela delle specifiche SUC13), con aliquota
 * e franchigia dell'imposta di successione (art. 7, D.Lgs. 346/1990).
 */
enum GradoParentela: string
{

    case coniuge = '01';
    case figlio = '02';
    case genitore = '03';
    case nipoteLineaRetta = '04';
    case nonnoPaterno = '5A';
    case nonnoMaterno = '5B';
    case pronipoteLineaRetta = '06';
    case bisavoPaterno = '7A';
    case bisavoMaterno = '7B';
    case figlioPronipote = '08';
    case trisavoPaterno = '9A';
    case trisavoMaterno = '9B';
    case fratelloGermano = '10';
    case fratelloUnilaterale = '11';
    case genitoreConiuge = '12';
    case cognato = '13';
    case nonnoConiuge = '14';
    case nipoteFratello = '15';
    case coniugeNipoteFratello = '15A';
    case zio = '16';
    case nipoteConiuge = '17';
    case zioConiuge = '18';
    case bisavoConiuge = '19';
    case figlioNipoteFratello = '20';
    case cugino = '21';
    case prozio = '22';
    case figlioNipoteConiuge = '23';
    case figlioConiuge = '24';
    case figlioCognato = '25';
    case nuoraGenero = '26';
    case nipoteDiNipote = '27';
    case figlioCugino = '28';
    case secondoCugino = '29';
    case fratelloBisavo = '30';
    case altroCugino = '31';
    case figlioSecondoCugino = '32';
    case nipoteTrisavo = '33';
    case nipoteCugino = '34';
    case estraneo = '35';
    case stato = '36';
    case entiTerritorialiTerzoSettore = '37';
    case entiFondazioniArt3c1 = '38';
    case entiFondazioniArt3c2 = '39';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::coniuge => 'Coniuge/parte dell\'unione civile',
            self::figlio => 'Figlio/a',
            self::genitore => 'Genitore',
            self::nipoteLineaRetta => 'Nipote in linea retta',
            self::nonnoPaterno => 'Nonno/a paterno/a',
            self::nonnoMaterno => 'Nonno/a materno/a',
            self::pronipoteLineaRetta => 'Pronipote in linea retta',
            self::bisavoPaterno => 'Bisavo paterno',
            self::bisavoMaterno => 'Bisavo materno',
            self::figlioPronipote => 'Figlio/a di pronipote in linea retta',
            self::trisavoPaterno => 'Trisavo paterno',
            self::trisavoMaterno => 'Trisavo materno',
            self::fratelloGermano => 'Fratello/sorella germano',
            self::fratelloUnilaterale => 'Fratello/sorella unilaterale',
            self::genitoreConiuge => 'Genitore del coniuge',
            self::cognato => 'Cognato/a',
            self::nonnoConiuge => 'Nonno/a del coniuge',
            self::nipoteFratello => 'Nipote (figlio/a di fratello/sorella)',
            self::coniugeNipoteFratello => 'Coniuge di nipote (figlio/a di fratello/sorella)',
            self::zio => 'Zio/a',
            self::nipoteConiuge => 'Nipote del coniuge (figlio/a di figlio)',
            self::zioConiuge => 'Zio/a del coniuge',
            self::bisavoConiuge => 'Bisavo del coniuge',
            self::figlioNipoteFratello => 'Figlio/a di nipote (figlio/a di figlio/a di fratello/sorella)',
            self::cugino => 'Cugino/a',
            self::prozio => 'Prozio/a',
            self::figlioNipoteConiuge => 'Figlio/a di nipote del coniuge',
            self::figlioConiuge => 'Figlio/a del coniuge',
            self::figlioCognato => 'Figlio/a del cognato/a',
            self::nuoraGenero => 'Nuora/genero',
            self::nipoteDiNipote => 'Nipote di nipote (in linea collaterale)',
            self::figlioCugino => 'Figlio/a di cugino/a',
            self::secondoCugino => 'Secondo cugino/a',
            self::fratelloBisavo => 'Fratello/sorella di bisavo',
            self::altroCugino => 'Altro cugino/a',
            self::figlioSecondoCugino => 'Figlio di secondo cugino/a',
            self::nipoteTrisavo => 'Nipote di trisavo',
            self::nipoteCugino => 'Nipote di cugino/a',
            self::estraneo => 'Estraneo/a',
            self::stato => 'Stato',
            self::entiTerritorialiTerzoSettore => 'Regioni, province, comuni, enti pubblici, ONLUS e terzo settore',
            self::entiFondazioniArt3c1 => 'Enti pubblici, fondazioni e associazioni riconosciute (art. 3, c. 1, TUS), partiti, enti religiosi',
            self::entiFondazioniArt3c2 => 'Enti pubblici, fondazioni e associazioni riconosciute (art. 3, c. 2, TUS)',
        };
    }

    /**
     * Coniuge e parenti in linea retta (aliquota 4%, franchigia di un
     * milione).
     */
    public function isLineaRetta(): bool
    {
        return in_array($this, [self::coniuge, self::figlio, self::genitore, self::nipoteLineaRetta, self::nonnoPaterno, self::nonnoMaterno, self::pronipoteLineaRetta,
            self::bisavoPaterno, self::bisavoMaterno, self::figlioPronipote, self::trisavoPaterno, self::trisavoMaterno], true);
    }

    public function isFratello(): bool
    {
        return in_array($this, [self::fratelloGermano, self::fratelloUnilaterale], true);
    }

    /**
     * Discendente del defunto: deve essere più giovane del defunto.
     */
    public function isDiscendente(): bool
    {
        return in_array($this, [self::figlio, self::nipoteLineaRetta, self::pronipoteLineaRetta, self::figlioPronipote], true);
    }

    /**
     * Ascendente del defunto: deve essere più anziano del defunto.
     */
    public function isAscendente(): bool
    {
        return in_array($this, [self::genitore, self::nonnoPaterno, self::nonnoMaterno, self::bisavoPaterno, self::bisavoMaterno, self::trisavoPaterno, self::trisavoMaterno], true);
    }

    /**
     * Affini: non succedono per legge.
     */
    public function isAffine(): bool
    {
        return in_array($this, [self::genitoreConiuge, self::cognato, self::nonnoConiuge, self::nipoteConiuge, self::zioConiuge, self::bisavoConiuge,
            self::figlioNipoteConiuge, self::figlioConiuge, self::figlioCognato, self::nuoraGenero], true);
    }

    /**
     * Stato ed enti (codici 36-39): non soggetti all'imposta di successione,
     * indicati con la denominazione.
     */
    public function isEnte(): bool
    {
        return in_array($this, [self::stato, self::entiTerritorialiTerzoSettore, self::entiFondazioniArt3c1, self::entiFondazioniArt3c2], true);
    }

    /**
     * Gradi ammessi nella successione per legge (esclusi affini, estranei
     * ed enti diversi dallo Stato).
     */
    public function isAmmessoPerLegge(): bool
    {
        return ($this->isAffine() === false) && (in_array($this, [self::estraneo, self::entiTerritorialiTerzoSettore, self::entiFondazioniArt3c1, self::entiFondazioniArt3c2], true) === false);
    }

    /**
     * Aliquota dell'imposta di successione in percentuale (art. 7, c. 1,
     * TUS; allegato 1 alle istruzioni): 4% coniuge e linea retta, 6%
     * fratelli, altri parenti fino al quarto grado e affini, 8% altri, 0 per
     * Stato ed enti.
     */
    public function getAliquota(): float
    {
        return match (true) {
            $this->isEnte() => 0.0,
            $this->isLineaRetta() => 4.0,
            $this->isFratello(), in_array($this, [self::genitoreConiuge, self::cognato, self::nonnoConiuge, self::nipoteFratello, self::coniugeNipoteFratello, self::zio,
                self::nipoteConiuge, self::zioConiuge, self::bisavoConiuge, self::figlioNipoteFratello, self::cugino, self::prozio, self::figlioNipoteConiuge,
                self::figlioConiuge, self::figlioCognato, self::nuoraGenero], true) => 6.0,
            default => 8.0,
        };
    }

    /**
     * Franchigia per beneficiario (art. 7, c. 1 e 2-bis, TUS); per le
     * persone con disabilità grave vale 1.500.000 euro qualunque sia il
     * grado (c. 2-bis).
     */
    public function getFranchigia(bool $disabile): int
    {
        return match (true) {
            $disabile => 1500000,
            $this->isLineaRetta() => 1000000,
            $this->isFratello() => 100000,
            default => 0,
        };
    }
}
