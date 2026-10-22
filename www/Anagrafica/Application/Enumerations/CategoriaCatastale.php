<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Categorie del catasto dei fabbricati (quadro generale delle categorie,
 * DPR 1142/1949 e successive integrazioni del gruppo F).
 *
 * Enum e non tabella: vocabolario chiuso fissato per legge, stabile nel
 * tempo, con comportamento (gruppo, presenza della rendita).
 */
enum CategoriaCatastale: string
{

    case a1 = 'A/1';
    case a2 = 'A/2';
    case a3 = 'A/3';
    case a4 = 'A/4';
    case a5 = 'A/5';
    case a6 = 'A/6';
    case a7 = 'A/7';
    case a8 = 'A/8';
    case a9 = 'A/9';
    case a10 = 'A/10';
    case a11 = 'A/11';
    case b1 = 'B/1';
    case b2 = 'B/2';
    case b3 = 'B/3';
    case b4 = 'B/4';
    case b5 = 'B/5';
    case b6 = 'B/6';
    case b7 = 'B/7';
    case b8 = 'B/8';
    case c1 = 'C/1';
    case c2 = 'C/2';
    case c3 = 'C/3';
    case c4 = 'C/4';
    case c5 = 'C/5';
    case c6 = 'C/6';
    case c7 = 'C/7';
    case d1 = 'D/1';
    case d2 = 'D/2';
    case d3 = 'D/3';
    case d4 = 'D/4';
    case d5 = 'D/5';
    case d6 = 'D/6';
    case d7 = 'D/7';
    case d8 = 'D/8';
    case d9 = 'D/9';
    case d10 = 'D/10';
    case e1 = 'E/1';
    case e2 = 'E/2';
    case e3 = 'E/3';
    case e4 = 'E/4';
    case e5 = 'E/5';
    case e6 = 'E/6';
    case e7 = 'E/7';
    case e8 = 'E/8';
    case e9 = 'E/9';
    case f1 = 'F/1';
    case f2 = 'F/2';
    case f3 = 'F/3';
    case f4 = 'F/4';
    case f5 = 'F/5';
    case f6 = 'F/6';
    case f7 = 'F/7';

    public function getDescrizione(): string
    {
        return match ($this) {
            self::a1 => 'Abitazioni di tipo signorile',
            self::a2 => 'Abitazioni di tipo civile',
            self::a3 => 'Abitazioni di tipo economico',
            self::a4 => 'Abitazioni di tipo popolare',
            self::a5 => 'Abitazioni di tipo ultrapopolare',
            self::a6 => 'Abitazioni di tipo rurale',
            self::a7 => 'Abitazioni in villini',
            self::a8 => 'Abitazioni in ville',
            self::a9 => 'Castelli, palazzi di eminenti pregi artistici o storici',
            self::a10 => 'Uffici e studi privati',
            self::a11 => 'Abitazioni ed alloggi tipici dei luoghi',
            self::b1 => 'Collegi, convitti, ricoveri, orfanotrofi, ospizi, conventi, seminari, caserme',
            self::b2 => 'Case di cura ed ospedali senza fine di lucro',
            self::b3 => 'Prigioni e riformatori',
            self::b4 => 'Uffici pubblici',
            self::b5 => 'Scuole e laboratori scientifici',
            self::b6 => 'Biblioteche, pinacoteche, musei, gallerie, accademie',
            self::b7 => 'Cappelle ed oratori non destinati all\'esercizio pubblico del culto',
            self::b8 => 'Magazzini sotterranei per depositi di derrate',
            self::c1 => 'Negozi e botteghe',
            self::c2 => 'Magazzini e locali di deposito',
            self::c3 => 'Laboratori per arti e mestieri',
            self::c4 => 'Fabbricati e locali per esercizi sportivi senza fine di lucro',
            self::c5 => 'Stabilimenti balneari e di acque curative senza fine di lucro',
            self::c6 => 'Stalle, scuderie, rimesse, autorimesse',
            self::c7 => 'Tettoie chiuse od aperte',
            self::d1 => 'Opifici',
            self::d2 => 'Alberghi e pensioni',
            self::d3 => 'Teatri, cinematografi, sale per concerti e spettacoli',
            self::d4 => 'Case di cura ed ospedali con fine di lucro',
            self::d5 => 'Istituti di credito, cambio e assicurazione',
            self::d6 => 'Fabbricati e locali per esercizi sportivi con fine di lucro',
            self::d7 => 'Fabbricati per le speciali esigenze di un\'attività industriale',
            self::d8 => 'Fabbricati per le speciali esigenze di un\'attività commerciale',
            self::d9 => 'Edifici galleggianti o sospesi, ponti privati soggetti a pedaggio',
            self::d10 => 'Fabbricati per funzioni produttive connesse alle attività agricole',
            self::e1 => 'Stazioni per servizi di trasporto terrestri, marittimi ed aerei',
            self::e2 => 'Ponti comunali e provinciali soggetti a pedaggio',
            self::e3 => 'Costruzioni e fabbricati per speciali esigenze pubbliche',
            self::e4 => 'Recinti chiusi per speciali esigenze pubbliche',
            self::e5 => 'Fabbricati costituenti fortificazioni e loro dipendenze',
            self::e6 => 'Fari, semafori, torri per l\'orologio comunale',
            self::e7 => 'Fabbricati destinati all\'esercizio pubblico dei culti',
            self::e8 => 'Fabbricati e costruzioni nei cimiteri',
            self::e9 => 'Edifici a destinazione particolare non compresi nel gruppo E',
            self::f1 => 'Area urbana',
            self::f2 => 'Unità collabenti',
            self::f3 => 'Unità in corso di costruzione',
            self::f4 => 'Unità in corso di definizione',
            self::f5 => 'Lastrico solare',
            self::f6 => 'Fabbricato in attesa di dichiarazione',
            self::f7 => 'Infrastrutture di reti pubbliche di comunicazione',
        };
    }

    public function getLabel(): string
    {
        return $this->value . ' - ' . $this->getDescrizione();
    }

    public function getGruppo(): string
    {
        return $this->value[0];
    }

    /**
     * Le categorie fittizie del gruppo F non hanno rendita catastale.
     */
    public function haRendita(): bool
    {
        return $this->getGruppo() !== 'F';
    }

    public function isAbitativa(): bool
    {
        return ($this->getGruppo() === 'A') && ($this !== self::a10);
    }
}
