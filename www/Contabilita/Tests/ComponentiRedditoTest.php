<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\RighiRedditi;
use Telonio\Tests\TestResultSet;

/**
 * Componenti del reddito per cassa (FIFO degli incassi sulle fatture della
 * controparte) e per competenza; righi della dichiarazione dei conti.
 */
class ComponentiRedditoTest extends ContabilitaTestCase
{

    private int $idRiga = 1;
    private int $idRegistrazione = 1;

    private function registra(CausaleContabile $causale, string $data, array $movimenti, bool $conControparte = true): array
    {
        $controparte = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null);
        $registrazione = $this->creaRegistrazione($causale, $conControparte ? $controparte : null, $data);
        $registrazione->id = $this->idRegistrazione++;
        $righe = [];
        foreach ($movimenti as [$conto, $dare, $avere]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->id = $this->idRiga++;
            $riga->registrazioneContabile = $registrazione;
            $riga->soggetto = $this->azienda;
            $riga->dataRegistrazione = $registrazione->dataRegistrazione;
            $riga->conto = $conto;
            $riga->controparte = ($conto === $this->conti['06.01.0001']) ? $controparte : null;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        return $righe;
    }

    /**
     * Movimenti dell'azienda senza partite aperte (registrazioni anteriori
     * alle partite: chiusura dalla più vecchia).
     */
    private function rispondiSenzaPartite(array $righe): void
    {
        $this->testAdapter->impostaSelectCallback(fn(string $cmd) => new TestResultSet(preg_match('/FROM\W+(partita|abbinamento)\W/', $cmd) === 1 ? [] : $righe));
    }

    public function testIncassiSulleFattureDallaPiuVecchia(): void
    {
        $cliente = $this->conti['06.01.0001'];
        $ricavi = $this->conti[FunzioneConto::ricavi->value];
        $ricavi->voceCee = VoceCee::economicoA1;
        $banca = $this->conti[FunzioneConto::banca->value];
        $iva = $this->conti[FunzioneConto::ivaVendite->value];
        $consulenze = $this->conti['22.01.0001'];
        $consulenze->voceCee = VoceCee::economicoB7;
        $righe = array_merge(
                $this->registra(CausaleContabile::fatturaVendita, '2024-11-10', [[$cliente, 1220, 0], [$ricavi, 0, 1000], [$iva, 0, 220]]),
                $this->registra(CausaleContabile::incasso, '2025-01-20', [[$banca, 610, 0], [$cliente, 0, 610]]),
                $this->registra(CausaleContabile::fatturaVendita, '2025-03-10', [[$cliente, 2440, 0], [$ricavi, 0, 2000], [$iva, 0, 440]]),
                $this->registra(CausaleContabile::incasso, '2025-06-30', [[$banca, 1830, 0], [$cliente, 0, 1830]]),
                // spesa pagata direttamente: concorre alla data
                $this->registra(CausaleContabile::giroconto, '2025-05-05', [[$consulenze, 300, 0], [$banca, 0, 300]], false),
                $this->registra(CausaleContabile::incasso, '2026-01-15', [[$banca, 1220, 0], [$cliente, 0, 1220]]),
        );
        $servizio = new ComponentiReddito($this->dataMapper);
        // 2025: la prima fattura tutta (610 + 610), la seconda per metà (1.220 di 2.440)
        $this->assertEqualsWithDelta([1 => 1.0, 3 => 0.5], $servizio->getQuoteIncassate($righe, 2025), 0.0001);
        $this->assertEqualsWithDelta([3 => 0.5], $servizio->getQuoteIncassate($righe, 2026), 0.0001);

        $this->rispondiSenzaPartite($righe);
        $componenti = $servizio->perCassa($this->azienda, 2025, false);
        $this->assertSame(2000.0, $componenti['RG2']);
        $this->assertSame(300.0, $componenti['RG22:99']);
        $this->assertArrayHasKey('partite', $servizio->getAvvisi());
        // professionista: compensi nel quadro RE
        $this->assertSame(2000.0, $servizio->perCassa($this->azienda, 2025, true)['RE2']);

        // per competenza: ricavi e costi registrati nell'anno
        $nel2025 = array_values(array_filter($righe, fn(RigaContabile $riga) => $riga->dataRegistrazione->format('Y') === '2025'));
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet($nel2025));
        $this->assertSame(['ricavi' => 2000.0, 'costi' => 300.0, 'utile' => 1700.0, 'variazioni' => []], $servizio->perCompetenza($this->azienda, 2025));

        // costo deducibile all'80 per cento (es. telefonia): nel quadro RG la parte deducibile, nel quadro RF la variazione del 20 per cento
        $consulenze->percentualeDeducibile = 80.0;
        $this->assertSame(['ricavi' => 2000.0, 'costi' => 300.0, 'utile' => 1700.0, 'variazioni' => ['RF31:99' => 60.0]], $servizio->perCompetenza($this->azienda, 2025));
        $consulenze->variazioneRedditi = 'RF18';
        $this->assertSame(['RF18' => 60.0], $servizio->perCompetenza($this->azienda, 2025)['variazioni']);
        $this->rispondiSenzaPartite($righe);
        $this->assertSame(240.0, $servizio->perCassa($this->azienda, 2025, false)['RG22:99']);

        // ricavi imponibili al 90 per cento: nel quadro RG la parte imponibile, nel quadro RF la variazione in diminuzione del 10 per cento
        $ricavi->percentualeDeducibile = 90.0;
        $this->assertSame(1800.0, $servizio->perCassa($this->azienda, 2025, false)['RG2']);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet($nel2025));
        $this->assertEquals(['RF18' => 60.0, 'RF55:99' => 200.0], $servizio->perCompetenza($this->azienda, 2025)['variazioni']);
    }

    public function testNotaDiCreditoChiudeLaFattura(): void
    {
        $cliente = $this->conti['06.01.0001'];
        $ricavi = $this->conti[FunzioneConto::ricavi->value];
        $righe = array_merge(
                $this->registra(CausaleContabile::fatturaVendita, '2025-02-01', [[$cliente, 1000, 0], [$ricavi, 0, 1000]]),
                $this->registra(CausaleContabile::notaCreditoEmessa, '2025-02-10', [[$cliente, 0, 400], [$ricavi, 400, 0]]),
                $this->registra(CausaleContabile::incasso, '2025-03-01', [[$this->conti[FunzioneConto::banca->value], 300, 0], [$cliente, 0, 300]]),
        );
        // la nota chiude 400 senza incasso (nota e storno si compensano), l'incasso 300: 70% della fattura
        $this->assertEqualsWithDelta([1 => 0.7], (new ComponentiReddito($this->dataMapper))->getQuoteIncassate($righe, 2025), 0.0001);
    }

    public function testRighiPredefiniti(): void
    {
        $this->assertSame('RG15', RighiRedditi::getPredefinito(VoceCee::economicoB6, false));
        $this->assertSame('RG16', RighiRedditi::getPredefinito(VoceCee::economicoB9A, false));
        $this->assertSame('RE11', RighiRedditi::getPredefinito(VoceCee::economicoB9A, true));
        $this->assertSame('RG22:4', RighiRedditi::getPredefinito(VoceCee::economicoC17, false));
        $this->assertNull(RighiRedditi::getPredefinito(VoceCee::attivoA, false));
        $this->assertTrue(RighiRedditi::isValido('RG22:13'));
        $this->assertFalse(RighiRedditi::isValido('RG99'));
        $this->assertSame(['RG22', 3], RighiRedditi::scomponi('RG22:3'));
    }
}
