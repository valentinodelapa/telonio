<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\FatturaPa\LetturaFatturaPa;
use Contabilita\Application\Services\FatturaPa\PropostaDaFattura;
use Contabilita\Application\Services\ImpostaBolloFatture;
use Contabilita\Application\Services\RitenuteOperate;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ritenute d'acconto operate (versamento 1040 per mese) e imposta di bollo
 * sulle fatture elettroniche emesse (versamento per trimestre con i
 * differimenti sotto i 5.000 euro).
 */
class RitenuteBolloTest extends ContabilitaTestCase
{

    private const BOLLO = '<DatiBollo><BolloVirtuale>SI</BolloVirtuale><ImportoBollo>2.00</ImportoBollo></DatiBollo>';

    public function testTerminiDelleRitenute(): void
    {
        $this->assertSame('2026-03-16', RitenuteOperate::getScadenza(2026, 2)->format('Y-m-d'));
        $this->assertSame('2026-08-20', RitenuteOperate::getScadenza(2026, 7)->format('Y-m-d'), 'differimento al 20 agosto');
        $this->assertSame('2027-01-18', RitenuteOperate::getScadenza(2026, 12)->format('Y-m-d'), '16 gennaio 2027 di sabato');
        $this->assertSame('ritenute-2026-02', RitenuteOperate::getRiferimento(2026, 2));
        $this->assertStringContainsString('241/1997', RitenuteOperate::getNorma(2026, 11));
        $this->assertStringContainsString('33/2025', RitenuteOperate::getNorma(2026, 12), 'versamento nel 2027');
    }

    public function testRitenutePerMeseDaiPagamenti(): void
    {
        $erario = new Conto($this->dataMapper);
        $erario->id = 200000;
        $erario->pianoConti = $this->piano;
        $erario->codice = '13.05.0001';
        $erario->descrizione = 'Erario c/ritenute da versare';
        $erario->natura = NaturaConto::passivita;
        $erario->funzione = FunzioneConto::ritenuteDaVersare;
        $erario->livello = LivelloConto::sottoconto;
        $gamma = $this->creaControparte(7, 'Gamma Servizi', null, $this->conti['13.01.0001']);
        $riga = function (string $data, ?object $controparte, Conto $conto, float $dare, float $avere): RigaContabile {
            $registrazione = $this->creaRegistrazione(CausaleContabile::pagamento, $controparte, $data);
            $riga = new RigaContabile($this->dataMapper);
            $riga->registrazioneContabile = $registrazione;
            $riga->soggetto = $this->azienda;
            $riga->dataRegistrazione = new SismaDate($data);
            $riga->conto = $conto;
            $riga->dare = $dare;
            $riga->avere = $avere;
            return $riga;
        };
        $this->rispondiConti(['riga_contabile' => [
                $riga('2026-02-15', $gamma, $erario, 0.0, 160.0),
                $riga('2026-02-15', $gamma, $this->conti['BAN'], 0.0, 816.0),
                $riga('2026-02-27', $gamma, $erario, 0.0, 40.0),
                // versamento in F24 senza controparte: non è una ritenuta operata
                $riga('2026-03-16', null, $erario, 160.0, 0.0),
                $riga('2026-05-10', $gamma, $erario, 0.0, 100.0)]]);
        $mesi = (new RitenuteOperate($this->dataMapper))->getPerMese($this->azienda, 2026);
        $this->assertSame([2, 5], array_keys($mesi));
        $this->assertSame(200.0, $mesi[2]['importo']);
        $this->assertCount(2, $mesi[2]['righe']);
        $this->assertSame(100.0, $mesi[5]['importo']);
    }

    public function testTerminiEDifferimentiDelBollo(): void
    {
        // 2025: 31 maggio di sabato e 2 giugno festivo; 30 novembre di domenica; 28 febbraio 2026 di sabato
        $this->assertSame(['2025-06-03', '2025-09-30', '2025-12-01', '2026-03-02'],
                array_map(fn(int $trimestre) => ImpostaBolloFatture::getScadenza(2025, $trimestre)->format('Y-m-d'), [1, 2, 3, 4]));
        $this->assertSame('2028-02-29', ImpostaBolloFatture::getScadenza(2027, 4)->format('Y-m-d'), 'anno bisestile');
        // sotto i 5.000 euro primo e secondo trimestre nel termine del terzo, ciascuno con il suo codice
        $piano = ImpostaBolloFatture::pianoVersamenti(2025, [1 => 2.0, 2 => 2.0, 3 => 2.0, 4 => 2.0]);
        $this->assertSame(['2521', '2522', '2523', '2524'], array_column($piano, 'codice'));
        $this->assertSame(['2025-12-01', '2025-12-01', '2025-12-01', '2026-03-02'], array_map(fn(array $voce) => $voce['scadenza']->format('Y-m-d'), array_values($piano)));
        $this->assertSame([true, true, false, false], array_column($piano, 'differito'));
        // primo trimestre sotto soglia, primo e secondo insieme no: il primo nel termine del secondo
        $piano = ImpostaBolloFatture::pianoVersamenti(2025, [1 => 3000.0, 2 => 3000.0]);
        $this->assertSame(['2025-09-30', '2025-09-30'], array_map(fn(array $voce) => $voce['scadenza']->format('Y-m-d'), array_values($piano)));
        $this->assertSame([true, false], array_column($piano, 'differito'));
        // primo trimestre oltre soglia: termini ordinari
        $piano = ImpostaBolloFatture::pianoVersamenti(2025, [1 => 5000.0, 2 => 10.0, 3 => 0.0]);
        $this->assertSame([1, 2], array_keys($piano), 'senza i trimestri senza imposta');
        $this->assertSame('2025-06-03', $piano[1]['scadenza']->format('Y-m-d'));
        $this->assertSame('2025-09-30', $piano[2]['scadenza']->format('Y-m-d'));
        $this->assertSame('art. 17, c. 1-bis, D.L. 124/2019', ImpostaBolloFatture::getNormaDifferimento(new \DateTimeImmutable('2026-11-30')));
        $this->assertSame('art. 152, c. 2, D.Lgs. 123/2025', ImpostaBolloFatture::getNormaDifferimento(new \DateTimeImmutable('2027-03-01')));
    }

    public function testRiepilogoDelBolloEFattureDaVerificare(): void
    {
        $beta = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null);
        $righe = [];
        $id = 500;
        foreach ([['2026-01-20', CausaleContabile::fatturaVendita, 'N4', 30000.0, 2.0], ['2026-02-10', CausaleContabile::fatturaVendita, 'N4', 150.0, 2.0],
            ['2026-04-15', CausaleContabile::fatturaVendita, 'N4', 100.0, null], ['2026-05-15', CausaleContabile::fatturaVendita, 'N4', 50.0, null],
            ['2026-06-15', CausaleContabile::fatturaVendita, '22', 1000.0, null], ['2026-07-15', CausaleContabile::fatturaAcquisto, 'N4', 900.0, null],
            ['2026-10-01', CausaleContabile::notaCreditoEmessa, 'N4', 200.0, 2.0]] as [$data, $causale, $codice, $imponibile, $bollo]) {
            $registrazione = $this->creaRegistrazione($causale, $beta, $data);
            $registrazione->id = $id++;
            $registrazione->impostaBollo = $bollo;
            $righe[] = $this->creaRigaIva($codice, $imponibile, $causale->getSegno(), null, $registrazione);
        }
        $this->rispondiConti(['riga_iva' => $righe]);
        $riepilogo = (new ImpostaBolloFatture($this->dataMapper))->riepiloga($this->azienda, 2026);
        $this->assertSame(['fatture' => 2, 'importo' => 4.0], $riepilogo['trimestri'][1]);
        $this->assertSame(['fatture' => 0, 'importo' => 0.0], $riepilogo['trimestri'][2]);
        $this->assertSame(['fatture' => 1, 'importo' => 2.0], $riepilogo['trimestri'][4], 'anche le note di credito con il bollo');
        $this->assertCount(1, $riepilogo['daVerificare'], 'esente oltre 77,47 euro senza bollo; non sotto soglia, non imponibile, non acquisto');
        $this->assertSame(502, $riepilogo['daVerificare'][0]['registrazione']->id);
        $this->assertSame(100.0, $riepilogo['daVerificare'][0]['senzaIva']);
    }

    public function testBolloDallaFatturaElettronica(): void
    {
        $xml = str_replace('<Numero>45/2026</Numero>', '<Numero>45/2026</Numero>' . self::BOLLO, FatturaPaTest::xml());
        $documento = (new LetturaFatturaPa())->leggi($xml);
        $this->assertSame(2.0, $documento->getCorpo(1)->bollo);
        $this->assertNull($documento->getCorpo(2)->bollo);
        $senzaImporto = str_replace('<ImportoBollo>2.00</ImportoBollo>', '', $xml);
        $this->assertSame(ImpostaBolloFatture::IMPORTO, (new LetturaFatturaPa())->leggi($senzaImporto)->getCorpo(1)->bollo, 'importo facoltativo nel tracciato');

        // proposta della registrazione: il bollo solo sulle fatture emesse
        $this->rispondiConti(['registro_iva' => [$this->registri['V']], 'esercizio_contabile' => [$this->esercizio], 'codice_iva' => array_values($this->codiciIva)]);
        $fattura = new FatturaElettronica($this->dataMapper);
        $fattura->studio = $this->studioPredefinito;
        $fattura->soggetto = $this->azienda;
        $fattura->direzione = DirezioneFattura::emessa;
        $fattura->tipoDocumento = TipoDocumentoFattura::fattura;
        $fattura->numero = '45/2026';
        $fattura->data = new SismaDate('2026-02-05');
        $fattura->denominazioneControparte = 'Bianchi';
        $fattura->contenuto = str_replace('<Numero>45/2026</Numero>', '<Numero>45/2026</Numero>' . self::BOLLO, FatturaPaTest::xml('01234560785', '04123451009', 'TD01',
                        '<DatiRiepilogo><AliquotaIVA>0.00</AliquotaIVA><Natura>N4</Natura><ImponibileImporto>500.00</ImponibileImporto><Imposta>0.00</Imposta></DatiRiepilogo>'));
        [$registrazione] = (new PropostaDaFattura($this->dataMapper))->proponi($fattura);
        $this->assertSame(2.0, $registrazione->impostaBollo);
    }

    public function testBolloSoloSulleVendite(): void
    {
        $gamma = $this->creaControparte(7, 'Gamma Servizi', null, $this->conti['13.01.0001']);
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, $gamma);
        $acquisto->impostaBollo = 2.0;
        $errori = (new ControlloRegistrazione())->verifica($this->piano, $acquisto, [$this->creaRigaIva('N4', 500.0)], []);
        $this->assertArrayHasKey('impostaBollo', $errori);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null));
        $vendita->impostaBollo = -2.0;
        $this->assertArrayHasKey('impostaBollo', (new ControlloRegistrazione())->verifica($this->piano, $vendita, [$this->creaRigaIva('N4', 500.0)], []));
        $vendita->impostaBollo = 2.0;
        $this->assertArrayNotHasKey('impostaBollo', (new ControlloRegistrazione())->verifica($this->piano, $vendita, [$this->creaRigaIva('N4', 500.0)], []));
    }
}
