<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\StampaDefinitiva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Services\StampaDefinitiva\Impaginazione;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Orm\CustomTypes\SismaDate;

class StampeDefinitiveTest extends ContabilitaTestCase
{

    private function stampa(TipoStampaDefinitiva $tipo, string $dal, string $al, int $prima, int $ultima, int $id = 1): StampaDefinitiva
    {
        $stampa = new StampaDefinitiva($this->dataMapper);
        $stampa->id = $id;
        $stampa->studio = $this->studioPredefinito;
        $stampa->soggetto = $this->azienda;
        $stampa->tipo = $tipo;
        $stampa->anno = 2026;
        $stampa->registroIva = $tipo === TipoStampaDefinitiva::registroIva ? $this->registri['V'] : null;
        $stampa->esercizioContabile = $tipo === TipoStampaDefinitiva::giornale ? $this->esercizio : null;
        $stampa->dal = new SismaDate($dal);
        $stampa->al = new SismaDate($al);
        $stampa->primaPagina = $prima;
        $stampa->ultimaPagina = $ultima;
        return $stampa;
    }

    public function testImpaginazioneConRiporti(): void
    {
        $colonne = [['etichetta' => 'Descrizione', 'numero' => false, 'larghezza' => 80], ['etichetta' => 'Importo', 'numero' => true, 'larghezza' => 20]];
        $righe = array_map(fn(int $numero) => ['celle' => ['Riga ' . $numero, 10.0]], range(1, 25));
        $pagine = Impaginazione::impagina([['titolo' => null, 'colonne' => $colonne, 'righe' => $righe, 'riporto' => [1 => 100.0]],
                    ['titolo' => 'Totali', 'colonne' => $colonne, 'righe' => [['celle' => ['Totale', 350.0]]]]], 10, 7);
        $this->assertSame([7, 8, 9, 10], array_column($pagine, 'numero'));
        $prima = $pagine[0]['parti'][0];
        $this->assertSame([1 => 100.0], $prima['riporto'], 'riporto iniziale diverso da zero');
        $this->assertCount(7, $prima['righe'], '10 righe: intestazione, riporto, 7 righe, a riportare');
        $this->assertSame([1 => 170.0], $prima['aRiportare']);
        $this->assertSame([1 => 170.0], $pagine[1]['parti'][0]['riporto']);
        $righeStampate = 0;
        foreach ($pagine as $pagina) {
            foreach ($pagina['parti'] as $parte) {
                $linee = ($parte['titolo'] === null ? 0 : 1) + 1 + ($parte['riporto'] === null ? 0 : 1) + count($parte['righe']) + ($parte['aRiportare'] === null ? 0 : 1);
                $righeStampate += $parte['titolo'] === 'Totali' ? 0 : count($parte['righe']);
                $this->assertLessThanOrEqual(10, $linee);
            }
        }
        $this->assertSame(25, $righeStampate);
        $ultima = end($pagine);
        $this->assertSame('Totali', end($ultima['parti'])['titolo'], 'il blocco dei totali entra nell\'ultima pagina o in una nuova');
        $this->assertCount(1, Impaginazione::impagina([['titolo' => null, 'colonne' => $colonne, 'righe' => []]], 10, 1), 'anche un periodo vuoto ha la sua pagina');
    }

    public function testContinuitaDelleStampe(): void
    {
        $servizio = new StampeDefinitive($this->dataMapper);
        $precedente = $this->stampa(TipoStampaDefinitiva::giornale, '2026-01-01', '2026-01-31', 1, 4);
        $this->rispondiConti(['stampa_definitiva' => [$precedente], 'esercizio_contabile' => [$this->esercizio]]);
        [$dal, $fine] = $servizio->getPeriodoSuccessivo($this->azienda, TipoStampaDefinitiva::giornale, null, 2026);
        $this->assertSame('2026-02-01', $dal->format('Y-m-d'));
        $this->assertSame('2026-12-31', $fine->format('Y-m-d'));
        foreach (['2026-01-15' => 'giorno dopo', '2027-01-10' => 'giorno dopo', date('Y-m-d') => 'conclusi'] as $al => $messaggio) {
            try {
                $servizio->registra($this->azienda, TipoStampaDefinitiva::giornale, null, 2026, new SismaDate($al));
                $this->fail('Stampa registrata fino al ' . $al);
            } catch (\DomainException $errore) {
                $this->assertStringContainsString($messaggio, $errore->getMessage());
            }
        }
        $stampa = $servizio->registra($this->azienda, TipoStampaDefinitiva::giornale, null, 2026, new SismaDate('2026-02-28'));
        $this->assertSame(5, $stampa->primaPagina, 'la numerazione prosegue');
        $this->assertSame(5, $stampa->ultimaPagina);

        $registro = $this->stampa(TipoStampaDefinitiva::registroIva, '2026-01-01', '2026-03-31', 1, 2, 2);
        $this->rispondiConti(['stampa_definitiva' => [$precedente, $registro]]);
        [$dal] = $servizio->getPeriodoSuccessivo($this->azienda, TipoStampaDefinitiva::registroIva, $this->registri['A'], 2026);
        $this->assertSame('2026-01-01', $dal->format('Y-m-d'), 'ogni registro ha la sua sequenza');
        $this->rispondiConti(['stampa_definitiva' => [$precedente, $this->stampa(TipoStampaDefinitiva::giornale, '2026-02-01', '2026-02-28', 5, 5, 3)]]);
        try {
            $servizio->annulla($precedente);
            $this->fail('Annullata una stampa non ultima');
        } catch (\DomainException) {
        }
        $this->rispondiConti(['stampa_definitiva' => [$precedente]]);
        $servizio->annulla($precedente);
        $this->assertNotEmpty(array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'DELETE') && str_contains($query['cmd'], '`stampa_definitiva`')));
    }

    public function testRegistrazioniBloccate(): void
    {
        $servizio = new StampeDefinitive($this->dataMapper);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-03-20');
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2026-03-20');
        $this->rispondiConti(['stampa_definitiva' => [$this->stampa(TipoStampaDefinitiva::giornale, '2026-01-01', '2026-02-28', 1, 3)]]);
        $this->assertNull($servizio->getBlocco($vendita));
        $vendita->dataRegistrazione = new SismaDate('2026-02-10');
        $this->assertStringContainsString('libro giornale', $servizio->getBlocco($vendita));

        $vendita->dataRegistrazione = new SismaDate('2026-03-20');
        $this->rispondiConti(['stampa_definitiva' => [$this->stampa(TipoStampaDefinitiva::registroIva, '2026-01-01', '2026-03-31', 1, 2)], 'registro_iva' => [$this->registri['V']]]);
        $this->assertStringContainsString('registro', $servizio->getBlocco($vendita));
        $this->assertNull($servizio->getBlocco($acquisto, [$this->creaRigaIva('22', 100.0)]));
        $this->assertNotNull($servizio->getBlocco($acquisto, [$this->creaRigaIva('RC22', 100.0)]), 'l\'integrazione è nel registro delle vendite stampato');
    }
}
