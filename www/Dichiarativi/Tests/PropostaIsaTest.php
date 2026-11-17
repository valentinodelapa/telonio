<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\IndiceIsa;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Isa\PropostaIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Telonio\Tests\SuiteTestCase;

/**
 * Proposta del modello ISA dalla dichiarazione Redditi: frontespizio,
 * quadro F dal quadro RG (corrispondenze dei controlli di coerenza ISA -
 * Redditi), quadro H dal quadro RE, solo i campi presenti nel modello
 * dell'ISA; quadri di un modello ISA dal catalogo.
 */
class PropostaIsaTest extends SuiteTestCase
{

    /**
     * @param array<string, string> $campi codice => formato
     * @return array<string, CampoModello>
     */
    private function catalogo(ModelloDichiarativo $modello, array $campi): array
    {
        $catalogo = [];
        foreach ($campi as $codice => $formato) {
            $campo = new CampoModello($this->dataMapper);
            $campo->modello = $modello;
            $campo->anno = 2026;
            $campo->isa = $modello === ModelloDichiarativo::isa ? 'EM01A' : '';
            $campo->codice = $codice;
            $campo->quadro = $modello === ModelloDichiarativo::isa ? substr($codice, 0, 1) : substr($codice, 0, 2);
            $campo->descrizione = $codice;
            $campo->formato = FormatoCampo::from($formato);
            $catalogo[$codice] = $campo;
        }
        return $catalogo;
    }

    /**
     * @return array{Dichiarazione, Dichiarazione}
     */
    private function dichiarazioni(): array
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'FERRI LUCIA';
        $soggetto->codiceFiscale = 'FRRLCU78E52H501I';
        $redditi = new Dichiarazione($this->dataMapper);
        $redditi->studio = $soggetto->studio;
        $redditi->soggetto = $soggetto;
        $redditi->modello = ModelloDichiarativo::redditiPf;
        $redditi->anno = 2025;
        $isa = new Dichiarazione($this->dataMapper);
        $isa->studio = $soggetto->studio;
        $isa->soggetto = $soggetto;
        $isa->modello = ModelloDichiarativo::isa;
        $isa->anno = 2025;
        $isa->isa = 'EM01A';
        $isa->dichiarazioneRedditi = $redditi;
        return [$redditi, $isa];
    }

    public function testQuadroFDalQuadroRg(): void
    {
        [$redditi, $isa] = $this->dichiarazioni();
        $quadriRedditi = new QuadriDichiarazione($redditi, $this->dataMapper, $this->catalogo(ModelloDichiarativo::redditiPf, [
            'RG001001' => 'AN', 'RG002002' => 'NP', 'RG013001' => 'NP', 'RG015001' => 'NP', 'RG018001' => 'NP', 'RG019001' => 'NP',
            'RG022001' => 'N2', 'RG022002' => 'NP', 'RG022003' => 'N2', 'RG022004' => 'NP', 'RG028002' => 'NU', 'RG038002' => 'NP',
        ]));
        foreach (['RG001001' => '47.11.02', 'RG002002' => 182000, 'RG013001' => 15000, 'RG015001' => 120000, 'RG018001' => 2500, 'RG019001' => 500,
            'RG022001' => 27, 'RG022002' => 800, 'RG022003' => 99, 'RG022004' => 1200, 'RG028002' => 36500, 'RG038002' => 16000] as $codice => $valore) {
            $quadriRedditi->imposta($codice, $valore);
        }
        // modello senza il rigo F02: il campo non si propone
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper, $this->catalogo(ModelloDichiarativo::isa, [
            'Y0100101' => 'CA', 'Y0100301' => 'CAT', 'F0100101' => 'VAL11', 'F0100801' => 'VAL11', 'F0100901' => 'VAL11', 'F0101001' => 'VAL11',
            'F0101501' => 'VAL11', 'F0101503' => 'VAL11', 'F0101704' => 'VAL11', 'F0102001' => 'VALS11', 'F0102901' => 'CB', 'F0102902' => 'CB',
            'H0100201' => 'VAL11', 'B0100301' => 'NU11',
        ]));
        $quadri->imposta('B0100301', 60);
        $this->assertSame('RG', PropostaIsa::getQuadroRedditi($quadri, $quadriRedditi));
        $avvisi = (new PropostaIsa($this->dataMapper))->proponi($isa, $quadri, false, $quadriRedditi);
        $this->assertSame('471102', $quadri->get('Y0100101'));
        $this->assertSame('182000', $quadri->get('F0100101'));
        $this->assertSame('15000', $quadri->get('F0100801'));
        $this->assertSame('16000', $quadri->get('F0100901'));
        $this->assertSame('120000', $quadri->get('F0101001'));
        // ammortamenti e beni di costo non superiore a 516,46 euro (RG18 + RG19), di cui RG19
        $this->assertSame('3000', $quadri->get('F0101501'));
        $this->assertSame('500', $quadri->get('F0101503'));
        // F17 campo 4: solo le maggiorazioni (codice 27), non gli altri componenti negativi (codice 99)
        $this->assertSame('800', $quadri->get('F0101704'));
        $this->assertSame('36500', $quadri->get('F0102001'));
        $this->assertSame('1', $quadri->get('F0102902'));
        $this->assertNull($quadri->get('F0102901'));
        // nessun quadro H per un'impresa; il valore inserito a mano resta
        $this->assertNull($quadri->get('H0100201'));
        $this->assertSame('60', $quadri->get('B0100301'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'quadro RG')));
    }

    public function testQuadroHDalQuadroRe(): void
    {
        [$redditi, $isa] = $this->dichiarazioni();
        $quadriRedditi = new QuadriDichiarazione($redditi, $this->dataMapper, $this->catalogo(ModelloDichiarativo::redditiPf, [
            'RE001001' => 'AN', 'RE002002' => 'NP', 'RE011001' => 'NP', 'RE011002' => 'NP', 'RE011003' => 'NP', 'RE020001' => 'NP',
        ]));
        foreach (['RE001001' => '69.20.01', 'RE002002' => 90000, 'RE011001' => 1000, 'RE011002' => 500, 'RE011003' => 20000, 'RE020001' => 35000] as $codice => $valore) {
            $quadriRedditi->imposta($codice, $valore);
        }
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper, $this->catalogo(ModelloDichiarativo::isa, [
            'Y0100101' => 'CA', 'H0100201' => 'VAL11', 'H0101101' => 'VAL11', 'H0102001' => 'VAL11',
        ]));
        $this->assertSame('RE', PropostaIsa::getQuadroRedditi($quadri, $quadriRedditi));
        (new PropostaIsa($this->dataMapper))->proponi($isa, $quadri, false, $quadriRedditi);
        $this->assertSame('692001', $quadri->get('Y0100101'));
        $this->assertSame('90000', $quadri->get('H0100201'));
        // H11 = RE11 col. 3 - col. 1 - col. 2 (spese per lavoro dipendente senza le maggiorazioni)
        $this->assertSame('18500', $quadri->get('H0101101'));
        $this->assertSame('35000', $quadri->get('H0102001'));
        $this->assertSame(['H', 'Y'], $quadri->getQuadriCompilati());
    }

    public function testCodiciEAttivitaIsa(): void
    {
        $this->assertSame('F0102001', PropostaIsa::codice('F', 20, 1));
        $this->assertSame('H0101902', PropostaIsa::codice('H', 19, 2));
        $indice = new IndiceIsa($this->dataMapper);
        $indice->attivita = '47.11.01 47.11.02';
        $this->assertSame(['47.11.01', '47.11.02'], $indice->getCodiciAttivita());
    }
}
