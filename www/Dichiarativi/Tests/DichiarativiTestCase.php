<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Contabilita\Tests\ContabilitaTestCase;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Entità di prova del modulo: l'azienda di Contabilità con i suoi dati
 * fiscali, la dichiarazione IVA 2025 e il catalogo reale dei campi del
 * modello IVA 2026 letto dalle sorgenti del modulo.
 */
abstract class DichiarativiTestCase extends ContabilitaTestCase
{

    /** @var array<string, CampoModello>|null */
    private static ?array $catalogoIva = null;

    /** @var array<string, CampoModello>|null */
    private static ?array $catalogoTr = null;

    protected function getCatalogoIva(): array
    {
        if (self::$catalogoIva === null) {
            self::$catalogoIva = [];
            foreach ((new TabelleDichiarativi())->leggi()['campi'] as $riga) {
                if (($riga['modello'] !== 'IVA') || ($riga['anno'] !== 2026)) {
                    continue;
                }
                $campo = new CampoModello($this->dataMapper);
                $campo->modello = ModelloDichiarativo::iva;
                $campo->anno = 2026;
                $campo->codice = $riga['codice'];
                $campo->quadro = $riga['quadro'];
                $campo->descrizione = $riga['descrizione'];
                $campo->formato = $riga['formato'] === null ? null : FormatoCampo::from($riga['formato']);
                self::$catalogoIva[$campo->codice] = $campo;
            }
        }
        return self::$catalogoIva;
    }

    protected function getCatalogoTr(): array
    {
        if (self::$catalogoTr === null) {
            self::$catalogoTr = [];
            foreach ((new TabelleDichiarativi())->leggi()['campi'] as $riga) {
                if (($riga['modello'] !== 'TR') || ($riga['anno'] !== ModelloDichiarativo::ANNO_TR)) {
                    continue;
                }
                $campo = new CampoModello($this->dataMapper);
                $campo->modello = ModelloDichiarativo::ivaTr;
                $campo->anno = ModelloDichiarativo::ANNO_TR;
                $campo->codice = $riga['codice'];
                $campo->quadro = $riga['quadro'];
                $campo->descrizione = $riga['descrizione'];
                $campo->formato = $riga['formato'] === null ? null : FormatoCampo::from($riga['formato']);
                self::$catalogoTr[$campo->codice] = $campo;
            }
        }
        return self::$catalogoTr;
    }

    protected function creaDichiarazione(int $anno = 2025): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $this->studioPredefinito;
        $dichiarazione->soggetto = $this->azienda;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = $anno;
        return $dichiarazione;
    }

    protected function creaQuadri(?Dichiarazione $dichiarazione = null): QuadriDichiarazione
    {
        return new QuadriDichiarazione($dichiarazione ?? $this->creaDichiarazione(), $this->dataMapper, $this->getCatalogoIva());
    }

    protected function creaDatiFiscali(PeriodicitaIva $periodicita = PeriodicitaIva::mensile): DatiFiscali
    {
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $this->azienda;
        $dati->validoDal = new SismaDate('2020-01-01');
        $dati->partitaIva = '01234560785';
        $dati->naturaGiuridica = NaturaGiuridica::societaResponsabilitaLimitata;
        $dati->regimeFiscale = RegimeFiscale::ordinario;
        $dati->periodicitaIva = $periodicita;
        return $dati;
    }
}
