<?php

namespace Successioni\Application\Enumerations;

/**
 * Quadri dell'attivo ereditario, nell'ordine del modello: terreni e
 * fabbricati del catasto ordinario (EB, EC) e del catasto tavolare (EL,
 * EM) dall'archivio immobili dell'Anagrafica, aziende (EN), azioni,
 * obbligazioni, titoli e quote (EO), aeromobili (EP), navi e imbarcazioni
 * (EQ), rendite, crediti e altri beni (ER), compresi conti e depositi.
 */
enum QuadroBene: string
{

    case terreni = 'EB';
    case fabbricati = 'EC';
    case terreniTavolare = 'EL';
    case fabbricatiTavolare = 'EM';
    case aziende = 'EN';
    case titoli = 'EO';
    case aeromobili = 'EP';
    case navi = 'EQ';
    case altriBeni = 'ER';

    public function getLabel(): string
    {
        return match ($this) {
            self::terreni => 'Quadro EB - Terreni',
            self::fabbricati => 'Quadro EC - Fabbricati',
            self::terreniTavolare => 'Quadro EL - Terreni (catasto tavolare)',
            self::fabbricatiTavolare => 'Quadro EM - Fabbricati (catasto tavolare)',
            self::aziende => 'Quadro EN - Aziende',
            self::titoli => 'Quadro EO - Azioni, obbligazioni, altri titoli e quote sociali',
            self::aeromobili => 'Quadro EP - Aeromobili',
            self::navi => 'Quadro EQ - Navi e imbarcazioni',
            self::altriBeni => 'Quadro ER - Rendite, crediti e altri beni',
        };
    }

    public function isImmobile(): bool
    {
        return in_array($this, [self::terreni, self::fabbricati, self::terreniTavolare, self::fabbricatiTavolare], true);
    }

    public function isTerreno(): bool
    {
        return in_array($this, [self::terreni, self::terreniTavolare], true);
    }

    public function isFabbricato(): bool
    {
        return in_array($this, [self::fabbricati, self::fabbricatiTavolare], true);
    }

    public function isTavolare(): bool
    {
        return in_array($this, [self::terreniTavolare, self::fabbricatiTavolare], true);
    }

    /**
     * Quadro dell'immobile secondo il catasto (ordinario o tavolare).
     */
    public static function perImmobile(bool $terreno, bool $tavolare): self
    {
        return match (true) {
            $terreno && $tavolare => self::terreniTavolare,
            $terreno => self::terreni,
            $tavolare => self::fabbricatiTavolare,
            default => self::fabbricati,
        };
    }

    /**
     * Righi per modulo del quadro nel modello (e nel file telematico).
     */
    public function getRighiPerModulo(): int
    {
        return match ($this) {
            self::fabbricatiTavolare, self::aeromobili => 1,
            self::terreni, self::fabbricati, self::terreniTavolare, self::aziende, self::navi => 2,
            self::titoli => 3,
            self::altriBeni => 4,
        };
    }

    /**
     * Codici diritto del defunto ammessi nel quadro.
     *
     * @return CodiceDiritto[]
     */
    public function getDirittiDefunto(): array
    {
        return match ($this) {
            self::terreni, self::terreniTavolare => [CodiceDiritto::proprieta, CodiceDiritto::proprietaArea, CodiceDiritto::nudaProprieta, CodiceDiritto::nudaProprietaArea,
                CodiceDiritto::concedente, CodiceDiritto::enfiteuta, CodiceDiritto::superficie, CodiceDiritto::servitu, CodiceDiritto::oneriReali],
            self::fabbricati, self::fabbricatiTavolare => [CodiceDiritto::proprieta, CodiceDiritto::proprietaSuperficiaria, CodiceDiritto::nudaProprieta, CodiceDiritto::nudaProprietaSuperficiaria,
                CodiceDiritto::concedente, CodiceDiritto::enfiteuta, CodiceDiritto::servitu, CodiceDiritto::oneriReali],
            default => [CodiceDiritto::proprieta, CodiceDiritto::nudaProprieta],
        };
    }

    /**
     * Codici diritto ammessi nella devoluzione ai beneficiari.
     *
     * @return CodiceDiritto[]
     */
    public function getDirittiDevoluzione(): array
    {
        return $this->isImmobile() ? array_values(array_filter(CodiceDiritto::cases(), fn(CodiceDiritto $codice) => $codice !== CodiceDiritto::servitu))
                : [CodiceDiritto::proprieta, CodiceDiritto::nudaProprieta, CodiceDiritto::uso, CodiceDiritto::usufrutto];
    }

    /**
     * Agevolazioni e riduzioni ammesse nella devoluzione del quadro (per i
     * beni aziendali solo D, G e Q).
     *
     * @return Agevolazione[]
     */
    public function getAgevolazioni(): array
    {
        return match ($this) {
            self::terreni, self::terreniTavolare => [Agevolazione::beniCulturali, Agevolazione::compendioUnicoMontano, Agevolazione::aziendeQuote, Agevolazione::compendioUnico,
                Agevolazione::giovaniAgricoltori, Agevolazione::fondiMontani, Agevolazione::boschiMontani, Agevolazione::beniCulturaliVincoloPosteriore,
                Agevolazione::aziendeMontane, Agevolazione::fondiRustici],
            self::fabbricati, self::fabbricatiTavolare => [Agevolazione::beniCulturali, Agevolazione::aziendeQuote, Agevolazione::giovaniAgricoltori, Agevolazione::inagibiliSisma,
                Agevolazione::fondiMontani, Agevolazione::primaCasa, Agevolazione::primaCasaContiguo, Agevolazione::pertinenzaPrimaCasa,
                Agevolazione::pertinenzaSenzaPrincipale, Agevolazione::impresaArtigianaFamiliare, Agevolazione::beniCulturaliVincoloPosteriore,
                Agevolazione::aziendeMontane, Agevolazione::fondiRustici],
            self::aziende => [Agevolazione::aziendeQuote, Agevolazione::giovaniAgricoltori, Agevolazione::aziendeMontane],
            self::titoli => [Agevolazione::aziendeQuote, Agevolazione::aziendeMontane],
            self::aeromobili, self::navi => [Agevolazione::beniCulturali, Agevolazione::beniCulturaliVincoloPosteriore, Agevolazione::aziendeMontane],
            self::altriBeni => [Agevolazione::beniCulturali, Agevolazione::beniCulturaliVincoloPosteriore],
        };
    }

    /**
     * Voce del quadro EE in cui confluisce il valore.
     */
    public function getVoceRiepilogo(): string
    {
        return match ($this) {
            self::terreni, self::fabbricati, self::terreniTavolare, self::fabbricatiTavolare => 'immobili',
            self::aziende => 'aziende',
            self::titoli => 'titoli',
            self::aeromobili, self::navi => 'aereiNavi',
            self::altriBeni => 'altriBeni',
        };
    }
}
