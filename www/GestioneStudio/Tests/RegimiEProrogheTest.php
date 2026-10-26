<?php

namespace GestioneStudio\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\TestResultSet;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use GestioneStudio\Application\Services\CalendarioAdempimenti;
use GestioneStudio\Application\Services\RequisitiProroga;
use GestioneStudio\Application\Services\ScadenzeModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;

class RegimiEProrogheTest extends GestioneStudioTestCase
{

    /** @var array<int, DatiFiscali> per id del soggetto */
    private array $datiFiscali = [];

    /** @var Legame[] */
    private array $legami = [];
    private array $termini = [];
    private array $proroghe = [];
    private array $assegnazioni = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind): TestResultSet {
            return match (true) {
                str_contains($cmd, 'FROM `dati_fiscali`') => new TestResultSet(isset($this->datiFiscali[$bind[0]]) ? [$this->datiFiscali[$bind[0]]] : []),
                str_contains($cmd, 'FROM `legame`') => new TestResultSet(array_filter($this->legami, fn(Legame $legame) => $legame->soggettoCollegato->id === $bind[0])),
                str_contains($cmd, 'FROM `termine_adempimento`') => new TestResultSet($this->termini),
                str_contains($cmd, 'FROM `proroga_adempimento`') => new TestResultSet(array_filter($this->proroghe, fn($proroga) => ($proroga->anno === $bind[1]) && (($proroga->studio !== null) === str_contains($cmd, '`studio_id` = ?')))),
                str_contains($cmd, 'FROM `assegnazione_adempimento`') => new TestResultSet($this->assegnazioni),
                default => new TestResultSet([]),
            };
        });
    }

    private function creaDati(Soggetto $soggetto, ?RegimeFiscale $regime, ?PeriodicitaIva $periodicita = null, bool $isa = false): DatiFiscali
    {
        $dati = new DatiFiscali($this->dataMapper);
        $dati->id = $soggetto->id;
        $dati->soggetto = $soggetto;
        $dati->validoDal = new SismaDate('2020-01-01');
        $dati->partitaIva = $regime === null ? null : '01114601006';
        $dati->regimeFiscale = $regime;
        $dati->periodicitaIva = $periodicita;
        $dati->soggettoIsa = $isa;
        return $this->datiFiscali[$soggetto->id] = $dati;
    }

    private function creaLegame(Soggetto $partecipata, Soggetto $socio, RuoloLegame $ruolo): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->id = count($this->legami) + 1;
        $legame->soggetto = $partecipata;
        $legame->soggettoCollegato = $socio;
        $legame->ruolo = $ruolo;
        $legame->validoDal = new SismaDate('2020-01-01');
        $this->legami[] = $legame;
    }

    public function testRequisitiDeiSoggettiIsaEDeiLoroSoci(): void
    {
        $fineAnno = new SismaDate('2025-12-31');
        $requisiti = new RequisitiProroga($this->dataMapper);
        $isa = $this->creaSoggetto(1, TipoSoggetto::personaFisica);
        $this->creaDati($isa, RegimeFiscale::semplificato, PeriodicitaIva::trimestrale, true);
        $forfetario = $this->creaSoggetto(2);
        $this->creaDati($forfetario, RegimeFiscale::forfetario);
        $ordinario = $this->creaSoggetto(3, TipoSoggetto::societaCapitali);
        $this->creaDati($ordinario, RegimeFiscale::ordinario, PeriodicitaIva::mensile);
        $privato = $this->creaSoggetto(4);
        $this->creaDati($privato, null);

        $this->assertTrue($requisiti->isSoddisfatta(CondizioneProroga::tutti, $privato, $fineAnno));
        $this->assertTrue($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $isa, $fineAnno));
        $this->assertTrue($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $forfetario, $fineAnno));
        $this->assertFalse($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $ordinario, $fineAnno));
        $this->assertFalse($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $privato, $fineAnno));

        // soci: società di persone ISA sì, società di capitali solo in trasparenza
        $snc = $this->creaSoggetto(5, TipoSoggetto::societaPersone);
        $this->creaDati($snc, RegimeFiscale::semplificato, PeriodicitaIva::trimestrale, true);
        $socioSnc = $this->creaSoggetto(6);
        $this->creaDati($socioSnc, null);
        $this->creaLegame($snc, $socioSnc, RuoloLegame::socio);
        $this->assertTrue($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $socioSnc, $fineAnno));

        $srl = $this->creaSoggetto(7, TipoSoggetto::societaCapitali);
        $this->creaDati($srl, RegimeFiscale::ordinario, PeriodicitaIva::mensile, true);
        $socioSrl = $this->creaSoggetto(8);
        $this->creaDati($socioSrl, null);
        $this->creaLegame($srl, $socioSrl, RuoloLegame::socio);
        $this->assertFalse((new RequisitiProroga($this->dataMapper))->isSoddisfatta(CondizioneProroga::soggettiIsa, $socioSrl, $fineAnno));
        $this->datiFiscali[7]->trasparenzaFiscale = true;
        $this->assertTrue((new RequisitiProroga($this->dataMapper))->isSoddisfatta(CondizioneProroga::soggettiIsa, $socioSrl, $fineAnno));

        // l'amministratore non partecipa al reddito; il collaboratore dell'impresa familiare sì
        $amministratore = $this->creaSoggetto(9);
        $this->creaDati($amministratore, null);
        $this->creaLegame($snc, $amministratore, RuoloLegame::amministratoreUnico);
        $this->assertFalse($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $amministratore, $fineAnno));
        $collaboratore = $this->creaSoggetto(10);
        $this->creaDati($collaboratore, null);
        $this->creaLegame($isa, $collaboratore, RuoloLegame::collaboratoreFamiliare);
        $this->assertTrue($requisiti->isSoddisfatta(CondizioneProroga::soggettiIsa, $collaboratore, $fineAnno));
    }

    public function testProrogaCondizionataSoloPerISoggettiConIRequisiti(): void
    {
        $adempimento = $this->creaAdempimento(15, 'SALDO_PRIMO_ACCONTO');
        $termine = $this->creaTermine($adempimento, 1, 'anno', 6, 30, 1);
        $this->termini = [$termine];
        $proroga = $this->creaProroga($termine, 2025, '2026-07-20');
        $proroga->condizione = CondizioneProroga::soggettiIsa;
        $this->proroghe = [$proroga];
        $isa = $this->creaSoggetto(1);
        $this->creaDati($isa, RegimeFiscale::semplificato, PeriodicitaIva::trimestrale, true);
        $ordinario = $this->creaSoggetto(3, TipoSoggetto::societaCapitali);
        $this->creaDati($ordinario, RegimeFiscale::ordinario, PeriodicitaIva::mensile);

        $calendario = new CalendarioAdempimenti($this->studioPredefinito, $this->dataMapper);
        $this->assertSame('2026-07-20', $calendario->getTermine($termine, 2025, $isa)->data->format('Y-m-d'));
        $this->assertSame('2026-06-30', $calendario->getTermine($termine, 2025, $ordinario)->data->format('Y-m-d'));
        // senza soggetto (calendario dello studio) vale il termine generale
        $this->assertSame('2026-06-30', $calendario->getTermine($termine, 2025)->data->format('Y-m-d'));

        $this->assegnazioni = [$this->creaAssegnazione($isa, $adempimento, '2025-01-01', null, 1), $this->creaAssegnazione($ordinario, $adempimento, '2025-01-01', null, 2)];
        $scadenze = (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioPredefinito, new SismaDate('2026-06-01'), new SismaDate('2026-07-31'));
        $perSoggetto = [];
        foreach ($scadenze as $scadenza) {
            $perSoggetto[$scadenza->soggetto->id] = $scadenza;
        }
        $this->assertSame('2026-07-20', $perSoggetto[1]->data->format('Y-m-d'));
        $this->assertStringStartsWith('Prorogato:', $perSoggetto[1]->dettaglio);
        $this->assertSame('2026-06-30', $perSoggetto[3]->data->format('Y-m-d'));
    }

    public function testProposteDagliAdempimentiConIRegimiFiscali(): void
    {
        $mensile = $this->creaSoggetto(1, TipoSoggetto::societaCapitali, '01114601006');
        $datiMensile = $this->creaDati($mensile, RegimeFiscale::ordinario, PeriodicitaIva::mensile, true);
        $datiMensile->sostitutoImposta = true;
        $forfetario = $this->creaSoggetto(2, TipoSoggetto::personaFisica, '01114601006');
        $forfetario->tipoAttivita = TipoAttivita::lavoroAutonomo;
        $datiForfetario = $this->creaDati($forfetario, RegimeFiscale::forfetario);
        $datiForfetario->tipoAttivita = TipoAttivita::lavoroAutonomo;
        $privato = $this->creaSoggetto(3);
        $datiPrivato = $this->creaDati($privato, null);

        $this->assertTrue(AmbitoAdempimento::ivaMensile->isProponibile($mensile, $datiMensile));
        $this->assertFalse(AmbitoAdempimento::ivaTrimestrale->isProponibile($mensile, $datiMensile));
        $this->assertTrue(AmbitoAdempimento::liquidazioniIva->isProponibile($mensile, $datiMensile));
        $this->assertFalse(AmbitoAdempimento::liquidazioniIva->isProponibile($forfetario, $datiForfetario));
        $this->assertTrue(AmbitoAdempimento::soggettiIva->isProponibile($forfetario, $datiForfetario));
        $this->assertFalse(AmbitoAdempimento::ivaMensile->isProponibile($forfetario, $datiForfetario));
        $this->assertTrue(AmbitoAdempimento::sostitutiImposta->isProponibile($mensile, $datiMensile));
        $this->assertFalse(AmbitoAdempimento::sostitutiImposta->isProponibile($forfetario, $datiForfetario));
        $this->assertTrue(AmbitoAdempimento::personeFisicheSenzaPartitaIva->isProponibile($privato, $datiPrivato));
        $this->assertFalse(AmbitoAdempimento::personeFisicheSenzaPartitaIva->isProponibile($forfetario, $datiForfetario));
        $this->assertFalse(AmbitoAdempimento::suRichiesta->isProponibile($mensile, $datiMensile));
        // senza dati fiscali: le liquidazioni si propongono ai titolari di partita IVA
        $this->assertTrue(AmbitoAdempimento::liquidazioniIva->isProponibile($forfetario));
        $this->assertFalse(AmbitoAdempimento::ivaMensile->isProponibile($forfetario));
    }
}
