<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Services\PeriodiIva;
use PHPUnit\Framework\TestCase;

class EnumerationsAndPeriodiTest extends TestCase
{

    public function testLivelloDalCodice(): void
    {
        $this->assertSame(LivelloConto::mastro, LivelloConto::daCodice('06'));
        $this->assertSame(LivelloConto::conto, LivelloConto::daCodice('06.01'));
        $this->assertSame(LivelloConto::sottoconto, LivelloConto::daCodice('06.01.0001'));
        $this->assertNull(LivelloConto::daCodice('6.1'));
        $this->assertNull(LivelloConto::daCodice('06.01.001'));
        $this->assertSame('06.01', LivelloConto::codicePadre('06.01.0001'));
        $this->assertNull(LivelloConto::codicePadre('06'));
        $this->assertSame(LivelloConto::conto, FunzioneConto::clienti->getLivello());
        $this->assertSame(LivelloConto::sottoconto, FunzioneConto::ivaVendite->getLivello());
    }

    public function testNatureEVociCee(): void
    {
        $this->assertTrue(NaturaConto::costo->isSaldoDare());
        $this->assertFalse(NaturaConto::ricavo->isSaldoDare());
        $this->assertTrue(NaturaConto::passivita->isPatrimoniale());
        $this->assertTrue(VoceCee::attivoCII1->isCompatibile(NaturaConto::attivita));
        $this->assertFalse(VoceCee::passivoD7->isCompatibile(NaturaConto::attivita));
        $this->assertTrue(VoceCee::economicoB7->isCompatibile(NaturaConto::costo));
        $this->assertFalse(VoceCee::economicoB7->isCompatibile(NaturaConto::sistema));
        $this->assertSame('C.II.5-bis Crediti tributari', VoceCee::attivoCII5Bis->getEtichetta());
        $this->assertCount(3, VoceCee::perSezione());
    }

    public function testCausali(): void
    {
        $this->assertSame(TipoRegistroIva::vendite, CausaleContabile::notaCreditoEmessa->getTipoRegistroIva());
        $this->assertSame(-1, CausaleContabile::notaCreditoRicevuta->getSegno());
        $this->assertSame(1, CausaleContabile::fatturaAcquisto->getSegno());
        $this->assertFalse(CausaleContabile::giroconto->isIva());
        $this->assertTrue(CausaleContabile::incasso->isControparteCliente());
        $this->assertFalse(CausaleContabile::pagamento->isControparteCliente());
        $this->assertNull(CausaleContabile::corrispettivi->isControparteCliente());
        $this->assertNotContains(CausaleContabile::liquidazioneIva, CausaleContabile::getManuali());
        $this->assertFalse(TipoRegistroIva::acquisti->isDebito());
    }

    public function testNatureIvaETipiDocumento(): void
    {
        $this->assertSame(NaturaIva::nonSoggetteAltri, NaturaIva::daFile('N2'));
        $this->assertSame(NaturaIva::inversioneAltri, NaturaIva::daFile('n6'));
        $this->assertSame(NaturaIva::inversioneSubappaltoEdile, NaturaIva::daFile('N6.3'));
        $this->assertNull(NaturaIva::daFile('N9'));
        $this->assertTrue(NaturaIva::inversioneFabbricati->isInversioneContabile());
        $this->assertFalse(NaturaIva::esenti->isInversioneContabile());
        $this->assertSame(CausaleContabile::notaCreditoEmessa, TipoDocumentoFattura::notaCredito->getCausale(DirezioneFattura::emessa));
        $this->assertSame(CausaleContabile::fatturaAcquisto, TipoDocumentoFattura::parcella->getCausale(DirezioneFattura::ricevuta));
        $this->assertTrue(TipoDocumentoFattura::integrazioneBeniIntracomunitari->isIntegrazione());
        $this->assertFalse(TipoDocumentoFattura::fatturaDifferita->isIntegrazione());
    }

    public function testMetodiAcconto(): void
    {
        $this->assertSame(880.0, MetodoAccontoIva::storico->getImporto(1000.0));
        $this->assertSame(1000.0, MetodoAccontoIva::analitico->getImporto(1000.0));
        $this->assertSame(0.0, MetodoAccontoIva::previsionale->getImporto(-5.0));
    }

    public function testPeriodiEScadenze(): void
    {
        $mensile = PeriodicitaIva::mensile;
        $trimestrale = PeriodicitaIva::trimestrale;
        $this->assertSame('2026-04-01', PeriodiIva::getInizio($trimestrale, 2026, 2)->format('Y-m-d'));
        $this->assertSame('2026-06-30', PeriodiIva::getFine($trimestrale, 2026, 2)->format('Y-m-d'));
        $this->assertSame('2026-02-28', PeriodiIva::getFine($mensile, 2026, 2)->format('Y-m-d'));
        $this->assertSame(3, PeriodiIva::getPeriodo($trimestrale, new \DateTimeImmutable('2026-09-30')));
        // luglio: 16 agosto, spostato al 20 agosto (art. 3-quater D.L. 16/2012)
        $this->assertSame('2026-08-20', PeriodiIva::getScadenza($mensile, 2026, 7)->format('Y-m-d'));
        // 16 maggio 2026 è sabato: lunedì 18
        $this->assertSame('2026-05-18', PeriodiIva::getScadenza($trimestrale, 2026, 1)->format('Y-m-d'));
        $this->assertSame('2026-08-20', PeriodiIva::getScadenza($trimestrale, 2026, 2)->format('Y-m-d'));
        $this->assertSame('2027-03-16', PeriodiIva::getScadenza($trimestrale, 2026, 4)->format('Y-m-d'));
        $this->assertSame('2027-01-18', PeriodiIva::getScadenza($mensile, 2026, 12)->format('Y-m-d'));
        $this->assertSame('6012', PeriodiIva::getCodiceTributo($mensile, 12));
        $this->assertSame('6032', PeriodiIva::getCodiceTributo($trimestrale, 2));
        $this->assertSame('6099', PeriodiIva::getCodiceTributo($trimestrale, 4));
        $this->assertSame('6035', PeriodiIva::getCodiceTributoAcconto($trimestrale));
        $this->assertSame('2026-12-28', PeriodiIva::getScadenzaAcconto(2026)->format('Y-m-d'));
    }

    public function testSogliaDiVersamento(): void
    {
        $this->assertTrue(PeriodiIva::isRinviabile(PeriodicitaIva::mensile, 10, 100.0, 2026));
        $this->assertFalse(PeriodiIva::isRinviabile(PeriodicitaIva::mensile, 10, 100.01, 2026));
        $this->assertFalse(PeriodiIva::isRinviabile(PeriodicitaIva::mensile, 11, 50.0, 2026), 'novembre si versa entro il 16 dicembre');
        $this->assertTrue(PeriodiIva::isRinviabile(PeriodicitaIva::trimestrale, 2, 80.0, 2026));
        $this->assertFalse(PeriodiIva::isRinviabile(PeriodicitaIva::trimestrale, 3, 80.0, 2026), 'terzo trimestre entro il 16 novembre');
        $this->assertFalse(PeriodiIva::isRinviabile(PeriodicitaIva::mensile, 3, 80.0, 2023), 'prima del 2024 la soglia era 25,82 euro');
        $this->assertTrue(PeriodiIva::isRinviabile(PeriodicitaIva::mensile, 3, 25.82, 2023));
    }
}
