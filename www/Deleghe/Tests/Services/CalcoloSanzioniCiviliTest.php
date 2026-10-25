<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\TassoRiferimento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Services\CalcoloSanzioniCivili;
use Deleghe\Application\Services\SanzioniCiviliService;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CalcoloSanzioniCiviliTest extends SuiteTestCase
{

    /**
     * @return TassoRiferimento[]
     */
    private function creaSerieTassiBce(): array
    {
        $t1 = new TassoRiferimento($this->dataMapper);
        $t1->id = 1;
        $t1->validoDal = new SismaDate('2024-01-01');
        $t1->tasso = 4.50;

        $t2 = new TassoRiferimento($this->dataMapper);
        $t2->id = 2;
        $t2->validoDal = new SismaDate('2024-06-12');
        $t2->tasso = 4.25;

        return [$t1, $t2];
    }

    public function testOmissioneSpontaneaEntro120GiorniEOltre(): void
    {
        $calcolo = new CalcoloSanzioniCivili($this->dataMapper);

        // Omissione spontanea entro 120 giorni: solo tasso BCE (maggiorazione 0)
        // Dal 17/05/2024 al 25/06/2024 (40 giorni):
        // - 17/05..11/06 = 26 gg al 4.50%
        // - 12/06..25/06 = 14 gg al 4.25%
        $this->testAdapter->accodaSelect($this->creaSerieTassiBce());
        $esitoSpontaneo = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-05-16'),
            new SismaDate('2024-06-25'),
            RegimeSanzioneCivile::omissione,
            true
        );

        $this->assertSame(40, $esitoSpontaneo['giorni']);
        $this->assertFalse($esitoSpontaneo['tetto']);
        $this->assertCount(2, $esitoSpontaneo['periodi']);
        $this->assertSame(4.5, $esitoSpontaneo['periodi'][0]['tasso']);
        $this->assertSame(4.25, $esitoSpontaneo['periodi'][1]['tasso']);
        $atteso = round(1000.0 * (4.50 * 26 + 4.25 * 14) / 100 / 365, 2);
        $this->assertSame($atteso, $esitoSpontaneo['sanzione']);

        // Omissione non spontanea (o oltre 120 giorni): tasso BCE + 5.5 punti
        $this->testAdapter->accodaSelect($this->creaSerieTassiBce());
        $esitoOrdinario = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-05-16'),
            new SismaDate('2024-06-25'),
            RegimeSanzioneCivile::omissione,
            false
        );
        $this->assertSame(10.0, $esitoOrdinario['periodi'][0]['tasso']);
        $this->assertSame(9.75, $esitoOrdinario['periodi'][1]['tasso']);
    }

    public function testEvasioneETettoMassimo40e60Percento(): void
    {
        $calcolo = new CalcoloSanzioniCivili($this->dataMapper);

        // Omissione con ritardo molto lungo: scatta il tetto del 40%
        $this->testAdapter->accodaSelect($this->creaSerieTassiBce());
        $esitoTettoOmissione = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-01-16'),
            new SismaDate('2029-01-16'),
            RegimeSanzioneCivile::omissione,
            false
        );
        $this->assertTrue($esitoTettoOmissione['tetto']);
        $this->assertSame(400.00, $esitoTettoOmissione['sanzione']);

        // Evasione ordinaria: misura fissa 30% annuo (non interroga TassoRiferimentoModel), tetto 60%
        $esitoEvasioneBreve = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-01-16'),
            new SismaDate('2024-07-16'),
            RegimeSanzioneCivile::evasione,
            false
        );
        $this->assertFalse($esitoEvasioneBreve['tetto']);
        $this->assertSame(30.0, $esitoEvasioneBreve['periodi'][0]['tasso']);

        // Evasione oltre 2 anni: scatta il tetto del 60%
        $esitoEvasioneTetto = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-01-16'),
            new SismaDate('2027-01-16'),
            RegimeSanzioneCivile::evasione,
            false
        );
        $this->assertTrue($esitoEvasioneTetto['tetto']);
        $this->assertSame(600.00, $esitoEvasioneTetto['sanzione']);
    }

    public function testCalcoloSanzioniCiviliEccezioni(): void
    {
        $calcolo = new CalcoloSanzioniCivili($this->dataMapper);

        try {
            $calcolo->calcola(500.0, new SismaDate('2024-05-16'), new SismaDate('2024-05-10'), RegimeSanzioneCivile::omissione, true);
            $this->fail('Attesa InvalidArgumentException per ritardo non positivo');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ritardo', $e->getMessage());
        }

        $this->testAdapter->accodaSelect([]);
        $this->expectException(\UnexpectedValueException::class);
        $calcolo->calcola(500.0, new SismaDate('2024-05-16'), new SismaDate('2024-06-16'), RegimeSanzioneCivile::omissione, true);
    }

    public function testSanzioniCiviliServiceRegistraEdElimina(): void
    {
        $studio = $this->creaStudio();
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 10;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'Rossi Mario';

        $delega = new Delega($this->dataMapper);
        $delega->id = 55;
        $delega->studio = $studio;
        $delega->soggetto = $soggetto;
        $delega->modello = ModelloF24::ordinario;
        $delega->stato = StatoDelega::bozza;
        $delega->scadenza = new SismaDate('2024-06-25');

        $sedeInps = new CodiceTabellaF24($this->dataMapper);
        $sedeInps->tabella = TabellaF24::sediInps;
        $sedeInps->codice = '7000';
        $sedeInps->descrizione = 'Roma';

        $causale = new CausaleContributo($this->dataMapper);
        $causale->ente = CausaleContributo::ENTE_INPS;
        $causale->causale = 'SLAA';
        $causale->descrizione = 'Sanzioni autonomi';
        $causale->modalita = ModalitaUtilizzo::debito;
        $causale->periodoDa = ObbligoCampo::obbligatorio;
        $causale->periodoA = ObbligoCampo::obbligatorio;

        $sanzione = new SanzioneCivile($this->dataMapper);
        $sanzione->delega = $delega;
        $sanzione->sezione = SezioneF24::inps;
        $sanzione->riferimento = 'IVS I rata 2024';
        $sanzione->importoContributi = 1000.00;
        $sanzione->scadenzaOriginale = new SismaDate('2024-05-16');
        $sanzione->regime = RegimeSanzioneCivile::omissione;
        $sanzione->spontaneo = true;
        $sanzione->causale = 'SLAA';
        $sanzione->codiceSede = '7000';
        $sanzione->posizione = '12345678';
        $sanzione->periodoDa = '01/2024';
        $sanzione->periodoA = '03/2024';

        // 1) TassoRiferimentoModel::getSerie
        // 2) ControlloDelega::verificaInps -> getVoce(sediInps)
        // 3) ControlloDelega::verificaInps -> getCausale(INPS, SLAA)
        // 4) VersamentoModel::getGenerati
        // 5) VersamentoModel::getProssimoOrdine
        // 6) GenerazioneDeleghe::aggiornaDetrazioneImu
        $this->testAdapter->accodaSelect($this->creaSerieTassiBce());
        $this->testAdapter->accodaSelect([$sedeInps]);
        $this->testAdapter->accodaSelect([$causale]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);

        $service = new SanzioniCiviliService($this->dataMapper);
        $service->registra($sanzione);

        $this->assertSame(40, $sanzione->giorniRitardo);
        $this->assertGreaterThan(0, $sanzione->sanzione);
        $this->assertFalse($sanzione->tettoApplicato);

        // Elimina
        $this->testAdapter->accodaSelect([]);
        $service->elimina($sanzione);
        $this->assertNotEmpty($this->testAdapter->queryEseguite);
    }
}
