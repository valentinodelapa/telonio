<?php

namespace Imu\Tests\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\CoefficienteFabbricatiD;
use Imu\Application\Entities\CostoContabileImu;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Services\CompatibilitaDifferenziate;
use Imu\Application\Services\ContestoAliquota;
use Imu\Application\Services\MesiPossesso;
use Imu\Application\Services\ValoreImmobile;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

class MesiPossessoAndValoreImmobileTest extends SuiteTestCase
{

    public function testMesiPossessoAttribuzione(): void
    {
        // Anno intero
        $mesi = MesiPossesso::attribuisci(2025, [
            'intero' => [new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31')],
        ]);
        $this->assertSame(range(1, 12), $mesi['intero']);

        // Trasferimento a metà mese di giugno (30 giorni): cedente 1-15 (15 gg, non fino a fine mese), acquirente 16-30 (15 gg, fino a fine mese)
        $cedente = MesiPossesso::attribuisci(2025, [
            0 => [new \DateTimeImmutable('2025-06-01'), new \DateTimeImmutable('2025-06-15')],
        ]);
        $this->assertSame([], $cedente[0]);

        $acquirente = MesiPossesso::attribuisci(2025, [
            0 => [new \DateTimeImmutable('2025-06-16'), new \DateTimeImmutable('2025-06-30')],
        ]);
        $this->assertSame([6], $acquirente[0]);

        // Variazione interna nello stesso possesso a metà mese: a parità di giorni prevale il secondo periodo
        $variazione = MesiPossesso::attribuisci(2025, [
            'primo' => [new \DateTimeImmutable('2025-04-01'), new \DateTimeImmutable('2025-04-15')],
            'secondo' => [new \DateTimeImmutable('2025-04-16'), new \DateTimeImmutable('2025-04-30')],
        ]);
        $this->assertSame([], $variazione['primo']);
        $this->assertSame([4], $variazione['secondo']);

        // Mese di 31 giorni (gennaio): 16 giorni > metà
        $gennaio = MesiPossesso::attribuisci(2025, [
            'p1' => [new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-16')],
        ]);
        $this->assertSame([1], $gennaio['p1']);
    }

    public function testValoreImmobileFabbricatoTerrenoEContabile(): void
    {
        $dati = new DatiCatastali($this->dataMapper);
        $dati->causale = CausaleClassamento::costituzione;
        $this->assertSame(0.0, ValoreImmobile::fabbricato($dati));

        // Categoria A/2 (moltiplicatore 160), rendita 1000 => 1000 * 1.05 * 160 = 168000
        $dati->categoria = CategoriaCatastale::a2;
        $dati->rendita = 1000.0;
        $this->assertSame(168000.0, ValoreImmobile::fabbricato($dati));

        // Categoria C/1 (moltiplicatore 55), rendita 1000 => 1000 * 1.05 * 55 = 57750
        $dati->categoria = CategoriaCatastale::c1;
        $this->assertSame(57750.0, ValoreImmobile::fabbricato($dati));

        // Terreno con RD 100 => 100 * 1.25 * 135 = 16875
        $dati->redditoDominicale = 100.0;
        $this->assertSame(16875.0, ValoreImmobile::terreno($dati));

        // Contabile con ripiego all'anno precedente e costi antecedenti al 1982
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 10;

        $c1982 = new CoefficienteFabbricatiD($this->dataMapper);
        $c1982->annoImposta = 2024;
        $c1982->annoCosto = 1982;
        $c1982->coefficiente = 3.0;

        $c2020 = new CoefficienteFabbricatiD($this->dataMapper);
        $c2020->annoImposta = 2024;
        $c2020->annoCosto = 2020;
        $c2020->coefficiente = 1.2;

        $c2024 = new CoefficienteFabbricatiD($this->dataMapper);
        $c2024->annoImposta = 2024;
        $c2024->annoCosto = 2024;
        $c2024->coefficiente = 1.05;

        $costo1980 = new CostoContabileImu($this->dataMapper);
        $costo1980->anno = 1980;
        $costo1980->importo = 10000.0;

        $costo2020 = new CostoContabileImu($this->dataMapper);
        $costo2020->anno = 2020;
        $costo2020->importo = 50000.0;

        $costo2025 = new CostoContabileImu($this->dataMapper);
        $costo2025->anno = 2025;
        $costo2025->importo = 20000.0;

        // Anno 2025 non ha coefficienti, ripiega su 2024; acquistoNellAnno = false => ignora costo 2025
        $this->testAdapter->accodaSelect(
            new TestResultSet([]), // 2025 vuoto
            new TestResultSet([$c1982, $c2020, $c2024]), // 2024
            new TestResultSet([$costo1980, $costo2020, $costo2025])
        );
        $esito = (new ValoreImmobile($this->dataMapper))->contabile($immobile, 2025, false);
        // 10000 * 3.0 + 50000 * 1.2 = 30000 + 60000 = 90000
        $this->assertSame(['valore' => 90000.0, 'annoDecreto' => 2024, 'costi' => 2], $esito);

        // Con acquistoNellAnno = true, include anche costo 2025 rivalutato con coeff 2024 (1.05) => +21000 = 111000
        $this->testAdapter->accodaSelect(
            new TestResultSet([]),
            new TestResultSet([$c1982, $c2020, $c2024]),
            new TestResultSet([$costo1980, $costo2020, $costo2025])
        );
        $esitoAcquisto = (new ValoreImmobile($this->dataMapper))->contabile($immobile, 2025, true);
        $this->assertSame(['valore' => 111000.0, 'annoDecreto' => 2024, 'costi' => 3], $esitoAcquisto);
    }

    public function testCompatibilitaDifferenziate(): void
    {
        $diff = new AliquotaDifferenziata($this->dataMapper);
        $diff->fattispecie = FattispecieImu::altriFabbricati;
        $diff->aliquota = 0.56;
        $diff->descrizione = 'Abitazione locata o in comodato - Tipo contratto: Locazione ai sensi dell\'art. 2, comma 3, della Legge n.431/1998 e s.m.i. Categoria catastale: - A/2 - A/3';

        // Categoria e utilizzo corrispondenti, nessuna condizione da verificare => verificata = true
        $esito = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::altriFabbricati, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::locatoCanoneConcordato));
        $this->assertTrue($esito['compatibile']);
        $this->assertTrue($esito['verificata']);
        $this->assertSame([], $esito['daVerificare']);

        // Fattispecie diversa => incompatibile
        $esitoFattispecie = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::gruppoD, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::locatoCanoneConcordato));
        $this->assertFalse($esitoFattispecie['compatibile']);

        // Categoria diversa => incompatibile
        $esitoCat = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::altriFabbricati, new ContestoAliquota(CategoriaCatastale::c1, UtilizzoImmobile::locatoCanoneConcordato));
        $this->assertFalse($esitoCat['compatibile']);

        // Condizioni da verificare (es. ONLUS, parentela, contratto registrato) e gruppi di categorie
        $diffCond = new AliquotaDifferenziata($this->dataMapper);
        $diffCond->fattispecie = FattispecieImu::altriFabbricati;
        $diffCond->aliquota = 0.46;
        $diffCond->descrizione = 'Immobili di categoria A10, B, C concessi in comodato d\'uso gratuito a parenti con contratto registrato - Requisiti del soggetto passivo: ONLUS';

        // Per persona fisica ($ente = false) viene esclusa perché richiede ONLUS
        $esitoNonEnte = CompatibilitaDifferenziate::verifica($diffCond, FattispecieImu::altriFabbricati, new ContestoAliquota(CategoriaCatastale::c2, UtilizzoImmobile::comodato, false, true));
        $this->assertFalse($esitoNonEnte['compatibile']);

        // Per ente ($ente = true) è compatibile ma ha condizioni da verificare => verificata = false
        $esitoEnte = CompatibilitaDifferenziate::verifica($diffCond, FattispecieImu::altriFabbricati, new ContestoAliquota(CategoriaCatastale::c2, UtilizzoImmobile::comodato, true, true));
        $this->assertTrue($esitoEnte['compatibile']);
        $this->assertFalse($esitoEnte['verificata']);
        $this->assertContains('requisiti del soggetto passivo (ONLUS o terzo settore)', $esitoEnte['daVerificare']);
        $this->assertContains('contratto registrato', $esitoEnte['daVerificare']);
        $this->assertContains('grado di parentela del comodatario', $esitoEnte['daVerificare']);
    }

    private function verificaDescrizione(string $descrizione, ContestoAliquota $contesto): array
    {
        $differenziata = new AliquotaDifferenziata($this->dataMapper);
        $differenziata->fattispecie = FattispecieImu::altriFabbricati;
        $differenziata->aliquota = 0.76;
        $differenziata->descrizione = $descrizione;
        return CompatibilitaDifferenziate::verifica($differenziata, FattispecieImu::altriFabbricati, $contesto);
    }

    public function testCompatibilitaDifferenziatePensionatoEstero(): void
    {
        $testo = "Aliquota per l'unica unità immobiliare posseduta dai cittadini italiani pensionati, non residenti nel territorio dello stato e titolari di pensione maturata in regime di convenzione con l'Italia a titolo di proprietà o usufrutto in Italia, a condizione che non risulti locata o comunque occupata";
        $this->assertFalse($this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::aDisposizione))['compatibile']);
        $esito = $this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::aDisposizione, pensionatoEstero: true));
        $this->assertTrue($esito['verificata']);

        // "pensioni" nella descrizione di una categoria non è una condizione sul pensionato
        $this->assertTrue($this->verificaDescrizione("Categoria catastale:\n- D/2 Alberghi e pensioni (con fine di lucro)", new ContestoAliquota(CategoriaCatastale::d2))['verificata']);
    }

    public function testCompatibilitaDifferenziateComodatoParenti(): void
    {
        $testo = "Aliquota per l'abitazione concessa in comodato gratuito, ai parenti in linea retta, che la utilizzano come abitazione principale";
        // comodato senza l'agevolazione: la parentela resta da verificare
        $esito = $this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::comodato));
        $this->assertTrue($esito['compatibile']);
        $this->assertFalse($esito['verificata']);
        $this->assertContains('grado di parentela del comodatario', $esito['daVerificare']);
        $this->assertFalse($this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::aDisposizione))['compatibile']);
        // con l'agevolazione è verificata, anche se il possesso non indica il comodato
        $this->assertTrue($this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a2, null, comodatoParenti: true))['verificata']);

        // testo del prospetto: ipotesi del comma 747, lett. c) e ipotesi diverse
        $prospetto = "Abitazione locata o in comodato\n- Tipo contratto: Comodato d'uso gratuito\n- Condizioni locatario/comodatario: Parenti - Sino al primo grado (%s all'art. 1, comma 747, lett. c), della legge n. 160 del 2019)\n- Destinazione d'uso: Purche' l'affittuario/comodatario la utilizzi come abitazione principale.";
        $diLegge = sprintf($prospetto, 'ipotesi di cui');
        $diverse = sprintf($prospetto, 'ipotesi diverse da quella di cui');
        $this->assertTrue($this->verificaDescrizione($diLegge, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::comodato, comodatoParenti: true))['verificata']);
        $this->assertFalse($this->verificaDescrizione($diverse, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::comodato, comodatoParenti: true))['compatibile']);
        $this->assertTrue($this->verificaDescrizione($diverse, new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::comodato))['compatibile']);
        // il secondo grado non è coperto dall'agevolazione di legge
        $this->assertFalse($this->verificaDescrizione('Abitazione concessa in comodato ai parenti di secondo grado', new ContestoAliquota(CategoriaCatastale::a2, null, comodatoParenti: true))['verificata']);
    }

    public function testCompatibilitaDifferenziateAlloggiIacp(): void
    {
        $testo = "Alloggi regolarmente assegnati dagli IACP o dagli enti di\nedilizia residenziale pubblica aventi le stesse finalità";
        $this->assertFalse($this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a3))['compatibile']);
        $this->assertTrue($this->verificaDescrizione($testo, new ContestoAliquota(CategoriaCatastale::a3, alloggioIacp: true))['verificata']);
    }

    /**
     * Una voce non riconosciuta non si perde e non lascia applicare
     * l'aliquota in automatico, anche se le altre condizioni sono verificate.
     */
    public function testCompatibilitaDifferenziateVociNonRiconosciute(): void
    {
        $contesto = new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::locatoLiberoMercato);
        $esito = $this->verificaDescrizione('Abitazione destinata a struttura turistico-ricettiva', $contesto);
        $this->assertTrue($esito['compatibile']);
        $this->assertFalse($esito['verificata']);
        $this->assertSame(['condizione del prospetto: Abitazione destinata a struttura turistico-ricettiva'], $esito['daVerificare']);

        $conVoce = "Abitazione locata o in comodato\n- Tipo contratto: Locazione a canone libero\n- Potenza impianti produzione energia, Superiore a (kW): 200";
        $esitoVoce = $this->verificaDescrizione($conVoce, $contesto);
        $this->assertFalse($esitoVoce['verificata']);
        $this->assertSame(['condizione del prospetto: Potenza impianti produzione energia, Superiore a (kW): 200'], $esitoVoce['daVerificare']);

        $conEta = "Abitazione locata o in comodato\n- Tipo contratto: Locazione a canone libero\n- Persona fisica di eta' non superiore ad anni: 35";
        $this->assertSame(['età del soggetto'], $this->verificaDescrizione($conEta, $contesto)['daVerificare']);

        // voci del prospetto riconosciute: art. 2, comma 1, e locazione generica
        $this->assertTrue($this->verificaDescrizione("- Tipo contratto: Locazione ai sensi dell'art. 2, comma 1, della Legge n. 431/1998 e s.m.i.", $contesto)['verificata']);
        $this->assertTrue($this->verificaDescrizione('- Tipo contratto: Locazione', $contesto)['verificata']);
        $this->assertFalse($this->verificaDescrizione('- Tipo contratto: Locazione', new ContestoAliquota(CategoriaCatastale::a2, UtilizzoImmobile::comodato))['compatibile']);
    }
}
