<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\HelperClasses\RiferimentiIva;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\FatturaPa\GeneratoreAutofattura;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Norme citate secondo la data: D.P.R. 633/1972 fino al 2026, Testo unico
 * IVA (D.Lgs. 10/2026) e testo unico adempimenti (D.Lgs. 141/2026) dal
 * 2027; codici IVA validi fino a una data.
 */
class RiferimentiIvaTest extends ContabilitaTestCase
{

    public function testCitazioniPerData(): void
    {
        $this->assertSame('art. 24, c. 3, D.P.R. 633/1972', RiferimentiIva::cita('ventilazione', new \DateTimeImmutable('2026-12-31')));
        $this->assertSame('art. 87, c. 3, D.Lgs. 10/2026', RiferimentiIva::cita('ventilazione', new \DateTimeImmutable('2027-01-01')));
        $this->assertSame('art. 21-bis D.L. 78/2010', RiferimentiIva::cita('lipe', 2026));
        $this->assertSame('art. 51 D.Lgs. 141/2026', RiferimentiIva::cita('lipe', 2027));
        $this->assertSame('art. 23 D.P.R. 633/1972; dal 2027 art. 86 D.Lgs. 10/2026', RiferimentiIva::entrambe('registroVendite'));
        $this->assertSame('art. 88 D.Lgs. 10/2026', TipoRegistroIva::acquisti->getNorma(2027));
        $this->assertSame('art. 24 D.P.R. 633/1972', TipoRegistroIva::corrispettivi->getNorma(new SismaDate('2026-06-30')));
        $this->assertSame('art. 95 D.Lgs. 10/2026', TipoStampaDefinitiva::registroIva->getNorma(2027));
        $this->assertSame('art. 2215 c.c.; art. 2219 c.c.', TipoStampaDefinitiva::giornale->getNorma(2027));
        $this->assertSame('Scissione dei pagamenti (art. 65 D.Lgs. 10/2026)', EsigibilitaIva::scissionePagamenti->getLabel(2027));
        $this->assertSame('Differita (art. 6, c. 5, D.P.R. 633/1972)', EsigibilitaIva::differita->getLabel(new SismaDate('2026-03-01')));
        $this->assertStringEndsWith('art. 34, c. 6, D.P.R. 633/1972', GeneratoreAutofattura::getCausale(new SismaDate('2026-07-01')));
        $this->assertStringEndsWith('art. 133, c. 5, D.Lgs. 10/2026', GeneratoreAutofattura::getCausale(new SismaDate('2027-02-01')));
        $this->expectException(\InvalidArgumentException::class);
        RiferimentiIva::cita('inesistente');
    }

    public function testCodiceValidoFinoAUnaData(): void
    {
        $cellulari = new CodiceIva($this->dataMapper);
        $cellulari->id = 35;
        $cellulari->codice = 'N6.5';
        $cellulari->descrizione = 'Inversione contabile, telefoni cellulari';
        $cellulari->natura = NaturaIva::tryFrom('N6.5');
        $cellulari->norma = 'art. 17, c. 6, lett. b), D.P.R. 633/1972';
        $cellulari->normaTestoUnico = 'art. 64, c. 6, lett. e), e c. 8, D.Lgs. 10/2026';
        $cellulari->validoFino = new SismaDate('2026-12-31');
        $this->codiciIva['N6.5'] = $cellulari;
        $this->assertTrue($cellulari->isValidoAl(new SismaDate('2026-12-31')));
        $this->assertFalse($cellulari->isValidoAl(new SismaDate('2027-01-01')));
        $this->assertSame('art. 17, c. 6, lett. b), D.P.R. 633/1972', $cellulari->getNorma(new SismaDate('2026-12-31')));
        $this->assertSame('art. 64, c. 6, lett. e), e c. 8, D.Lgs. 10/2026', $cellulari->getNorma(new SismaDate('2027-01-01')));

        $controllo = new ControlloRegistrazione();
        $beta = $this->creaControparte(1, 'Beta', $this->conti['06.01.0001'], null);
        $righe = [$this->riga('06.01.0001', 100, 0), $this->riga('RIC', 0, 100)];
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $beta, '2026-12-31');
        $vendita->totaleDocumento = 100.0;
        $this->assertSame([], $controllo->verifica($this->piano, $vendita, [$this->creaRigaIva('N6.5', 100.0)], $righe));
        $this->esercizio->dataFine = new SismaDate('2027-12-31');
        $gennaio = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $beta, '2027-01-05');
        $gennaio->totaleDocumento = 100.0;
        $errori = $controllo->verifica($this->piano, $gennaio, [$this->creaRigaIva('N6.5', 100.0)], $righe);
        $this->assertStringContainsString('fino al 31/12/2026 (art. 64, c. 6, lett. e), e c. 8, D.Lgs. 10/2026)', $errori['righeIva']);
    }

    private function riga(string $codice, float $dare, float $avere): RigaContabile
    {
        $riga = new RigaContabile($this->dataMapper);
        $riga->conto = $this->conti[$codice];
        $riga->dare = $dare;
        $riga->avere = $avere;
        return $riga;
    }
}
