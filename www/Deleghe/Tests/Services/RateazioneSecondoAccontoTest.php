<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\RateazioneSecondoAcconto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\RateazioneService;
use Deleghe\Application\Services\TabelleRiferimento\SorgentiModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;

class RateazioneSecondoAccontoTest extends SuiteTestCase
{

    private int $prossimoId = 100;

    private function creaNorma(): RateazioneSecondoAcconto
    {
        $norma = new RateazioneSecondoAcconto($this->dataMapper);
        $norma->id = 2;
        $norma->periodoImposta = 2024;
        $norma->primaRata = new SismaDate('2025-01-16');
        $norma->numeroRate = 5;
        $norma->codici = '4034 1791 1841';
        $norma->norma = 'Art. 7-quater D.L. 155/2024';
        return $norma;
    }

    private function creaCodice(string $codice, string $formatoRateazione = '0000'): CodiceTributo
    {
        $codiceTributo = new CodiceTributo($this->dataMapper);
        $codiceTributo->id = $this->prossimoId++;
        $codiceTributo->sezione = SezioneCodiceTributo::erario;
        $codiceTributo->codice = $codice;
        $codiceTributo->descrizione = 'Codice ' . $codice;
        $codiceTributo->modalita = ModalitaUtilizzo::debito;
        $codiceTributo->formatoRateazione = $formatoRateazione;
        $codiceTributo->annoRichiesto = true;
        return $codiceTributo;
    }

    private function creaVersamento(string $codice, float $importo, int $anno = 2024, string $scadenza = '2024-12-02', TipoSoggetto $tipo = TipoSoggetto::personaFisica): Versamento
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = $tipo;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'Rossi Mario';
        $versamento = new Versamento($this->dataMapper);
        $versamento->id = $this->prossimoId++;
        $versamento->studio = $this->studioPredefinito;
        $versamento->soggetto = $soggetto;
        $versamento->scadenza = new SismaDate($scadenza);
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = $this->creaCodice($codice, in_array($codice, ['4001', '1792'], true) ? 'NNRR' : '0000');
        $versamento->annoRiferimento = $anno;
        $versamento->importoDebito = $importo;
        $versamento->descrizione = 'Secondo acconto ' . $anno;
        return $versamento;
    }

    public function testVersamentiCompresiNellaNorma(): void
    {
        $norma = $this->creaNorma();
        $this->assertSame(['4034', '1791', '1841'], $norma->getCodici());
        $this->assertTrue($norma->comprende($this->creaVersamento('1791', 1000)));
        // altro periodo d'imposta, codice non compreso, società, già al termine differito
        $this->assertFalse($norma->comprende($this->creaVersamento('1791', 1000, 2025)));
        $this->assertFalse($norma->comprende($this->creaVersamento('4001', 1000)));
        $this->assertFalse($norma->comprende($this->creaVersamento('1791', 1000, 2024, '2024-12-02', TipoSoggetto::societaCapitali)));
        $this->assertFalse($norma->comprende($this->creaVersamento('1791', 1000, 2024, '2025-01-16')));
    }

    public function testDateELimitiDellaNorma(): void
    {
        $norma = $this->creaNorma();
        $scadenza = new SismaDate('2024-12-02');
        $this->assertSame('2025-01-16', CalcoloRateazione::getPrimaRata($scadenza, $norma)->format('Y-m-d'));
        $this->assertSame('2024-12-02', CalcoloRateazione::getPrimaRata($scadenza)->format('Y-m-d'));
        $this->assertSame(1, CalcoloRateazione::getMinimoRate($norma));
        $this->assertSame(5, CalcoloRateazione::getMassimoRate($norma->primaRata, $norma));

        $date = CalcoloRateazione::getDateProposte($norma->primaRata, 9, $norma);
        $this->assertSame(['2025-01-16', '2025-02-16', '2025-03-16', '2025-04-16', '2025-05-16'], array_map(fn($data) => $data->format('Y-m-d'), $date));

        // unica soluzione al termine differito o fino a cinque rate
        $this->assertSame([], CalcoloRateazione::verificaDate($scadenza, [new SismaDate('2025-01-16')], $norma));
        $this->assertSame([], CalcoloRateazione::verificaDate($scadenza, array_slice($date, 0, 2), $norma));
        $this->assertSame(['Indicare almeno una rata.'], CalcoloRateazione::verificaDate($scadenza, [], $norma));
        $errori = CalcoloRateazione::verificaDate($scadenza, [new SismaDate('2024-12-02'), new SismaDate('2025-01-16'), new SismaDate('2025-02-16'),
                    new SismaDate('2025-03-16'), new SismaDate('2025-04-16'), new SismaDate('2025-06-16')], $norma);
        $this->assertContains('La prima rata (o l\'unica soluzione) si versa al termine differito dalla norma (16/01/2025).', $errori);
        $this->assertContains('La norma consente al più 5 rate.', $errori);
        $this->assertContains('La rata 6 supera il 16/05/2025, termine del pagamento rateale.', $errori);
    }

    public function testRateDelSecondoAccontoSenzaRateazioneConInteressi(): void
    {
        $norma = $this->creaNorma();
        $codiceInteressi = $this->creaCodice('1668');
        $calcolo = new CalcoloRateazione($this->dataMapper, fn() => $codiceInteressi);
        $date = CalcoloRateazione::getDateProposte($norma->primaRata, 5, $norma);
        $rate = $calcolo->calcola([$this->creaVersamento('1791', 1000.00)], $date);

        $sintesi = array_map(fn(Versamento $rata) => [$rata->scadenza->format('Y-m-d'), $rata->getCodice(), $rata->rateazione, $rata->annoRiferimento, $rata->importoDebito], $rate);
        // rateazione vuota (codici 0000); interessi 0,33% al mese dalla seconda rata; 16/02 e 16/03/2025 domenica: versamento il lunedì
        $this->assertSame([
            ['2025-01-16', '1791', null, 2024, 200.0],
            ['2025-02-17', '1791', null, 2024, 200.0],
            ['2025-02-17', '1668', null, 2024, 0.66],
            ['2025-03-17', '1791', null, 2024, 200.0],
            ['2025-03-17', '1668', null, 2024, 1.32],
            ['2025-04-16', '1791', null, 2024, 200.0],
            ['2025-04-16', '1668', null, 2024, 1.98],
            ['2025-05-16', '1791', null, 2024, 200.0],
            ['2025-05-16', '1668', null, 2024, 2.64],
        ], $sintesi);
        $this->assertSame('Rata 2/5 Secondo acconto 2024', $rate[1]->descrizione);
        $this->assertSame(2, CalcoloRateazione::getNumeroRata($rate[2]));

        // unica soluzione al 16 gennaio: nessun interesse
        $unica = $calcolo->calcola([$this->creaVersamento('4034', 750.00)], [$norma->primaRata]);
        $this->assertCount(1, $unica);
        $this->assertSame(750.0, $unica[0]->importoDebito);
        $this->assertNull($unica[0]->rateazione);
    }

    public function testNormaDelPianoNonMescolaSecondoAccontoEAltriVersamenti(): void
    {
        $service = new RateazioneService($this->dataMapper);
        $norma = $this->creaNorma();
        $acconto = $this->creaVersamento('1791', 1000.00);
        $cedolare = $this->creaVersamento('1841', 300.00);
        $saldo = $this->creaVersamento('1792', 500.00);
        $nonCompreso = $this->creaVersamento('4034', 400.00, 2025);

        // le norme si leggono una volta per servizio
        $this->testAdapter->accodaSelect([$norma]);
        $this->assertSame($norma, $service->getNormaPiano([$acconto, $cedolare]));
        $this->assertTrue($service->isRateizzabile($acconto));
        $this->assertTrue($service->isRateizzabile($saldo));
        $this->assertFalse($service->isRateizzabile($nonCompreso));
        $this->assertNull($service->getNormaPiano([$saldo]));
        $this->expectExceptionMessage('Il secondo acconto differito dalla norma straordinaria si rateizza separatamente dagli altri versamenti.');
        $service->getNormaPiano([$acconto, $saldo]);
    }

    public function testSorgenteDelleNorme(): void
    {
        $codici = array_fill_keys(['E|4034', 'E|1791', 'E|1794', 'E|1841', 'E|4045', 'E|4048'], true);
        $norme = (new SorgentiModulo())->leggiRateazioniSecondoAcconto($codici);
        $this->assertSame(['2023', '2024'], array_column($norme, 'periodo_imposta'));
        $this->assertSame('2024-01-16', $norme[0]['prima_rata']);
        $this->assertSame('5', $norme[1]['numero_rate']);

        $this->expectException(\UnexpectedValueException::class);
        (new SorgentiModulo())->leggiRateazioniSecondoAcconto(array_fill_keys(['E|4034'], true));
    }
}
