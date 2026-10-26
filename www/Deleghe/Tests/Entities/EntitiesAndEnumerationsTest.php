<?php

namespace Deleghe\Tests\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Entities\InvioTelematico;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\RegolaRavvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\TassoInteresseLegale;
use Deleghe\Application\Entities\TassoRiferimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\DichiarazioneRiferimento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\Periodicita;
use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

class EntitiesAndEnumerationsTest extends SuiteTestCase
{

    private function creaSoggetto(): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 10;
        $soggetto->studio = $this->creaStudio();
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'Rossi Mario';
        return $soggetto;
    }

    public function testDelegaEntityAndDelegaNonModificabileException(): void
    {
        $delega = new Delega($this->dataMapper);
        $delega->id = 1;
        $delega->studio = $this->creaStudio();
        $delega->soggetto = $this->creaSoggetto();
        $delega->scadenza = new SismaDate('2025-06-16');

        $this->assertSame(ModelloF24::ordinario, $delega->modello);
        $this->assertSame(StatoDelega::bozza, $delega->stato);
        $this->assertInstanceOf(SismaDateTime::class, $delega->dataInserimento);
        $this->assertTrue($delega->isModificabile());

        $delega->stato = StatoDelega::confermata;
        $this->assertFalse($delega->isModificabile());

        $eccezione = new DelegaNonModificabileException($delega);
        $this->assertSame($delega, $eccezione->delega);
        $this->assertStringContainsString('16/06/2025', $eccezione->getMessage());
        $this->assertStringContainsString('Rossi Mario', $eccezione->getMessage());
        $this->assertStringContainsString('confermata', $eccezione->getMessage());
    }

    public function testVersamentoEntity(): void
    {
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->codice = '4001';
        $tributo->descrizione = 'IRPEF Saldo';

        $versamento = new Versamento($this->dataMapper);
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = $tributo;
        $versamento->importoDebito = 250.0;

        $this->assertFalse($versamento->isGenerata());
        $this->assertTrue($versamento->isDaVersare());
        $this->assertTrue($versamento->isModificabile());
        $this->assertSame('4001', $versamento->getCodice());
        $this->assertSame('IRPEF Saldo', $versamento->getDescrizione());
        $this->assertFalse($versamento->hasSecondoSoggettoRichiesto());

        $versamento->origine = 'Imu';
        $versamento->codiceFiscaleCoobbligato = 'VRDGPP70A01H501K';
        $this->assertTrue($versamento->isGenerata());
        $this->assertTrue($versamento->hasSecondoSoggettoRichiesto());

        $delega = new Delega($this->dataMapper);
        $delega->stato = StatoDelega::trasmessa;
        $versamento->delega = $delega;
        $this->assertFalse($versamento->isDaVersare());
        $this->assertFalse($versamento->isModificabile());

        $versamentoInps = new Versamento($this->dataMapper);
        $versamentoInps->causale = 'DM10';
        $this->assertSame('DM10', $versamentoInps->getCodice());
        $this->assertSame('', $versamentoInps->getDescrizione());
    }

    public function testCreditoEntity(): void
    {
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->codice = '6099';
        $tributo->descrizione = 'IVA annuale';

        $credito = new Credito($this->dataMapper);
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = $tributo;
        $credito->annoRiferimento = 2024;
        $credito->descrizione = 'Dichiarazione IVA';
        $credito->importo = 5000.0;

        $this->assertInstanceOf(SismaDateTime::class, $credito->dataInserimento);
        $this->assertSame('6099', $credito->getCodice());
        $this->assertSame('Erario 6099 2024 - Dichiarazione IVA', $credito->getEtichetta());
        $this->assertTrue($credito->isUtilizzabile(new SismaDate('2025-01-16')));

        $credito->utilizzabileDal = new SismaDate('2025-05-10');
        $this->assertFalse($credito->isUtilizzabile(new SismaDate('2025-05-09')));
        $this->assertTrue($credito->isUtilizzabile(new SismaDate('2025-05-10')));
        $this->assertTrue($credito->isUtilizzabile(new SismaDate('2025-06-16')));
    }

    public function testCodiceTributoCausaleECodiceTabellaF24Entities(): void
    {
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->sezione = SezioneCodiceTributo::erario;
        $tributo->codice = '1001';
        $tributo->descrizione = 'Ritenute su retribuzioni';
        $this->assertSame('1001 - Ritenute su retribuzioni', $tributo->getEtichetta());
        $this->assertFalse($tributo->isCreditoPa());
        $this->assertFalse($tributo->isImu());
        $this->assertSame([], $tributo->getTabelleEnte());

        $tributo->codice = CodiceTributo::CODICE_CREDITI_PA;
        $this->assertTrue($tributo->isCreditoPa());

        $tributo->tipoTributo = 'IMU';
        $tributo->tabelleEnte = 'T4,T2';
        $this->assertTrue($tributo->isImu());
        $this->assertSame(['T4', 'T2'], $tributo->getTabelleEnte());

        $formati = [
            '0000' => 'non va compilato',
            '00MM' => 'mese di riferimento (00MM)',
            'NNRR' => 'rata e numero di rate (NNRR)',
            'FFFF' => 'rata e numero di rate (NNRR), facoltativo',
            '00T0' => 'codice della regione (00RR)',
            '00T1' => 'codice dell\'ente locale (tabella T1)',
            '00T2' => 'sigla della provincia (00PP)',
            '00T3' => 'codice dell\'ente territoriale (tabella T3)',
            '00T4' => 'codice catastale del comune',
            'XXXX' => 'XXXX',
        ];
        foreach ($formati as $formato => $descrizioneAttesa) {
            $tributo->formatoRateazione = $formato;
            $this->assertSame($descrizioneAttesa, $tributo->getDescrizioneRateazione());
        }

        $causale = new CausaleContributo($this->dataMapper);
        $causale->ente = CausaleContributo::ENTE_INPS;
        $causale->causale = 'DM10';
        $causale->descrizione = 'Dipendenti';
        $this->assertSame([], $causale->getFormatiMatricola());
        $causale->formatiMatricola = '1,2';
        $this->assertSame(['1', '2'], $causale->getFormatiMatricola());
        $this->assertSame('DM10 - Dipendenti', $causale->getEtichetta());

        $voceTabella = new CodiceTabellaF24($this->dataMapper);
        $voceTabella->tabella = TabellaF24::regioni;
        $voceTabella->codice = '08';
        $voceTabella->descrizione = 'Lazio';
        $this->assertSame('08 - Lazio', $voceTabella->getEtichetta());
    }

    public function testIntermediarioInvioTelematicoRavvedimentoSanzioneETassiEntities(): void
    {
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->soggetto = $this->creaSoggetto();
        $this->assertSame('Rossi Mario - RSSMRA80A01H501U', $intermediario->getEtichetta());

        $invio = new InvioTelematico($this->dataMapper);
        $invio->numeroDeleghe = 2;
        $invio->totale = 1230.50;
        $this->assertInstanceOf(SismaDateTime::class, $invio->dataCreazione);
        $this->assertFalse($invio->addebitoIntermediario);

        $ravvedimento = new Ravvedimento($this->dataMapper);
        $ravvedimento->id = 42;
        $ravvedimento->importoTributo = 1000.0;
        $ravvedimento->sanzione = 12.50;
        $ravvedimento->interessi = 2.05;
        $ravvedimento->dettaglioInteressi = json_encode([['dal' => '2025-01-17', 'al' => '2025-02-15', 'giorni' => 30, 'tasso' => 2.0, 'interessi' => 2.05]]);
        $this->assertSame('ravvedimento-42', $ravvedimento->getRiferimentoOrigine());
        $this->assertSame(1014.55, $ravvedimento->getTotale());
        $this->assertCount(1, $ravvedimento->getPeriodiInteressi());

        $regola = new RegolaRavvedimento($this->dataMapper);
        $regola->sezione = SezioneCodiceTributo::erario;
        $regola->codice = '4001';
        $regola->codiceSanzione = '8901';
        $regola->codiceInteressi = '1989';
        $regola->dichiarazione = DichiarazioneRiferimento::redditi;
        $regola->fonte = 'Ris. 109/E';
        $this->assertSame('8901', $regola->codiceSanzione);

        $sanzione = new SanzioneCivile($this->dataMapper);
        $sanzione->id = 7;
        $sanzione->dettaglio = json_encode([['dal' => '2025-01-17', 'al' => '2025-02-15', 'giorni' => 30, 'tasso' => 3.15, 'sanzione' => 2.59]]);
        $this->assertSame('sanzione-civile-7', $sanzione->getRiferimentoOrigine());
        $this->assertCount(1, $sanzione->getPeriodi());

        $tassoLegale = new TassoInteresseLegale($this->dataMapper);
        $tassoLegale->validoDal = new SismaDate('2025-01-01');
        $tassoLegale->tasso = 2.0;
        $tassoLegale->provvedimento = 'D.M. 10/12/2024';
        $this->assertSame(2.0, $tassoLegale->tasso);

        $tassoRif = new TassoRiferimento($this->dataMapper);
        $tassoRif->validoDal = new SismaDate('2025-02-05');
        $tassoRif->tasso = 2.90;
        $this->assertSame(2.90, $tassoRif->tasso);
    }

    public function testEnumerationsMethods(): void
    {
        // CircostanzaRavvedimento
        foreach (CircostanzaRavvedimento::cases() as $case) {
            $this->assertNotEmpty($case->getLabel());
        }
        $this->assertNull(CircostanzaRavvedimento::nessuna->getRiduzione(true));
        $this->assertSame(6, CircostanzaRavvedimento::schemaAtto->getRiduzione(true));
        $this->assertSame(5, CircostanzaRavvedimento::verbale->getRiduzione(false));
        $this->assertSame(4, CircostanzaRavvedimento::schemaAttoDopoVerbale->getRiduzione(true));

        // CodiceIdentificativo
        foreach (CodiceIdentificativo::cases() as $case) {
            $this->assertStringStartsWith($case->value . ' - ', $case->getLabel());
        }
        $this->assertTrue(CodiceIdentificativo::erede->isAddebitoSulSecondoSoggetto());
        $this->assertFalse(CodiceIdentificativo::defunto->isAddebitoSulSecondoSoggetto());
        $this->assertTrue(CodiceIdentificativo::accollante->isSoloServiziAgenzia());
        $this->assertFalse(CodiceIdentificativo::erede->isSoloServiziAgenzia());

        // DichiarazioneRiferimento
        foreach (DichiarazioneRiferimento::cases() as $case) {
            $this->assertNotEmpty($case->getLabel());
        }
        $violazione = new SismaDate('2024-06-17');
        $this->assertSame('2025-10-31', DichiarazioneRiferimento::redditi->getTermine($violazione)->format('Y-m-d'));
        $this->assertSame('2025-04-30', DichiarazioneRiferimento::iva->getTermine($violazione)->format('Y-m-d'));
        $this->assertSame('2025-06-30', DichiarazioneRiferimento::imu->getTermine($violazione)->format('Y-m-d'));
        $this->assertSame('2025-06-17', DichiarazioneRiferimento::nessuna->getTermine($violazione)->format('Y-m-d'));

        // ModalitaUtilizzo
        $this->assertTrue(ModalitaUtilizzo::debito->ammetteDebito());
        $this->assertFalse(ModalitaUtilizzo::debito->ammetteCredito());
        $this->assertFalse(ModalitaUtilizzo::credito->ammetteDebito());
        $this->assertTrue(ModalitaUtilizzo::credito->ammetteCredito());
        $this->assertTrue(ModalitaUtilizzo::entrambi->ammetteDebito());
        $this->assertTrue(ModalitaUtilizzo::entrambi->ammetteCredito());

        // ModelloF24
        foreach (ModelloF24::cases() as $modello) {
            $this->assertNotEmpty($modello->getLabel());
            $this->assertNotEmpty($modello->getSezioni());
        }
        $this->assertTrue(ModelloF24::ordinario->ammetteSezione(SezioneF24::inps));
        $this->assertFalse(ModelloF24::semplificato->ammetteSezione(SezioneF24::inps));
        $this->assertTrue(ModelloF24::ordinario->ammetteCrediti());
        $this->assertFalse(ModelloF24::elide->ammetteCrediti());
        $this->assertSame('A', ModelloF24::ordinario->getTipoTelematico());
        $this->assertSame('A', ModelloF24::semplificato->getTipoTelematico());
        $this->assertSame('8', ModelloF24::elide->getTipoTelematico());
        $this->assertSame('B', ModelloF24::accise->getTipoTelematico());

        // ObbligoCampo & Periodicita
        $this->assertTrue(ObbligoCampo::obbligatorio->isAmmesso());
        $this->assertTrue(ObbligoCampo::facoltativo->isAmmesso());
        $this->assertFalse(ObbligoCampo::nonPrevisto->isAmmesso());
        $this->assertSame(1, Periodicita::mensile->getMesi());
        $this->assertSame(3, Periodicita::trimestrale->getMesi());
        $this->assertSame(6, Periodicita::semestrale->getMesi());
        $this->assertSame(12, Periodicita::annuale->getMesi());
        $this->assertNotEmpty(Periodicita::mensile->getLabel());

        // RegimeSanzioneCivile
        foreach (RegimeSanzioneCivile::cases() as $regime) {
            $this->assertNotEmpty($regime->getLabel());
        }
        $this->assertSame(0.0, RegimeSanzioneCivile::omissione->getMaggiorazione(true));
        $this->assertSame(5.5, RegimeSanzioneCivile::omissione->getMaggiorazione(false));
        $this->assertSame(5.5, RegimeSanzioneCivile::evasioneDenunciataTrentaGiorni->getMaggiorazione(true));
        $this->assertSame(7.5, RegimeSanzioneCivile::evasioneDenunciataNovantaGiorni->getMaggiorazione(true));
        $this->assertNull(RegimeSanzioneCivile::evasione->getMaggiorazione(true));
        $this->assertSame(30.0, RegimeSanzioneCivile::evasione->getMisuraFissa());
        $this->assertSame(40.0, RegimeSanzioneCivile::omissione->getTetto());
        $this->assertSame(60.0, RegimeSanzioneCivile::evasione->getTetto());

        // SezioneCodiceTributo & SezioneF24 & StatoDelega & TabellaF24
        foreach (SezioneCodiceTributo::cases() as $sec) {
            $this->assertNotEmpty($sec->getLabel());
        }
        foreach (SezioneF24::cases() as $sez) {
            $this->assertNotEmpty($sez->getLabel());
            $this->assertNotEmpty($sez->getIcona());
            $this->assertGreaterThan(0, $sez->getRigheModello());
            $this->assertNotEmpty($sez->getCampi());
        }
        $this->assertTrue(SezioneF24::erario->usaCampo('codiceTributo'));
        $this->assertFalse(SezioneF24::erario->usaCampo('posizione'));
        $this->assertSame(SezioneCodiceTributo::erario, SezioneF24::erario->getSezioneCodici());
        $this->assertNull(SezioneF24::inps->getSezioneCodici());

        foreach (StatoDelega::cases() as $stato) {
            $this->assertNotEmpty($stato->getLabel());
            $this->assertNotEmpty($stato->getClasseBadge());
            $this->assertNotEmpty($stato->getPassaggi());
        }
        $this->assertSame('Conferma', StatoDelega::confermata->getAzione(StatoDelega::bozza));
        $this->assertSame('Annulla invio', StatoDelega::confermata->getAzione(StatoDelega::trasmessa));
        $this->assertSame('Riapri', StatoDelega::bozza->getAzione(StatoDelega::confermata));
        $this->assertSame('Segna come trasmessa', StatoDelega::trasmessa->getAzione(StatoDelega::confermata));
        $this->assertSame('Annulla quietanza', StatoDelega::trasmessa->getAzione(StatoDelega::quietanzata));
        $this->assertSame('Registra quietanza', StatoDelega::quietanzata->getAzione(StatoDelega::trasmessa));

        foreach (TabellaF24::cases() as $tab) {
            $this->assertNotEmpty($tab->getLabel());
        }
        $this->assertSame(TabellaF24::regioni, TabellaF24::daTerritoriale('T0'));
        $this->assertSame(TabellaF24::entiLocali, TabellaF24::daTerritoriale('T1'));
        $this->assertSame(TabellaF24::province, TabellaF24::daTerritoriale('T2'));
        $this->assertSame(TabellaF24::provinceDirittiCamerali, TabellaF24::daTerritoriale('T6'));
        $this->assertNull(TabellaF24::daTerritoriale('T4'));
    }

    public function testCircostanzaRavvedimentoPreRiformaLanciaEccezionePerSchemaAtto(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CircostanzaRavvedimento::schemaAtto->getRiduzione(false);
    }
}
