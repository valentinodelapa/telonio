<?php

namespace GestioneStudio\Tests;

use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use GestioneStudio\Application\Enumerations\FiltroStato;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use GestioneStudio\Application\Services\TermineEffettivo;
use GestioneStudio\Application\Services\VoceScadenzario;
use SismaFramework\Orm\CustomTypes\SismaDate;

class EntitiesAndEnumerationsTest extends GestioneStudioTestCase
{

    public function testTermineOrdinarioConUltimoGiornoEAnniSuccessivi(): void
    {
        $adempimento = $this->creaAdempimento();
        // LIPE del 4° trimestre: fine febbraio dell'anno successivo (bisestile e no)
        $lipe = $this->creaTermine($adempimento, 4, '4° trimestre', 2, 0, 1);
        $this->assertSame('2024-02-29', $lipe->getTermineOrdinario(2023)->format('Y-m-d'));
        $this->assertSame('2025-02-28', $lipe->getTermineOrdinario(2024)->format('Y-m-d'));
        $this->assertSame('fine febbraio dell\'anno successivo', $lipe->getDescrizioneTermine());

        // 29 febbraio in un anno non bisestile: ultimo giorno del mese
        $bisestile = $this->creaTermine($adempimento, 5, 'anno', 2, 29);
        $this->assertSame('2025-02-28', $bisestile->getTermineOrdinario(2025)->format('Y-m-d'));

        $redditi = $this->creaTermine($adempimento, 1, 'anno', 10, 31, 1);
        $this->assertSame('2026-10-31', $redditi->getTermineOrdinario(2025)->format('Y-m-d'));
        $this->assertSame('31 ottobre dell\'anno successivo', $redditi->getDescrizioneTermine());
        $this->assertSame('16 maggio', $this->creaTermine($adempimento, 2, '1° trimestre', 5, 16)->getDescrizioneTermine());
        $this->assertSame('30 giugno di 2 anni dopo', $this->creaTermine($adempimento, 3, 'anno', 6, 30, 2)->getDescrizioneTermine());
    }

    public function testTermineEffettivoPeriodoERiferimento(): void
    {
        $termine = $this->creaTermine($this->creaAdempimento(), 1, 'anno', 10, 31, 1);
        $effettivo = new TermineEffettivo($termine, 2025, new SismaDate('2026-11-02'), new SismaDate('2026-10-31'));
        $this->assertTrue($effettivo->isDifferito());
        $this->assertSame('anno 2025', $effettivo->getPeriodo());
        $this->assertSame('T1001/2025/S50', $effettivo->getRiferimento(50));
        $mensile = new TermineEffettivo($this->creaTermine($this->creaAdempimento(), 3, 'marzo', 4, 16), 2026, new SismaDate('2026-04-16'), new SismaDate('2026-04-16'));
        $this->assertFalse($mensile->isDifferito());
        $this->assertSame('marzo 2026', $mensile->getPeriodo());
    }

    public function testAmbitoProponeInBaseAiDatiDellAnagrafica(): void
    {
        $persona = $this->creaSoggetto();
        $ditta = $this->creaSoggetto(51, TipoSoggetto::personaFisica, '01234567897');
        $ditta->tipoAttivita = TipoAttivita::impresaIndividuale;
        $professionista = $this->creaSoggetto(52, TipoSoggetto::personaFisica, '01234567897');
        $professionista->tipoAttivita = TipoAttivita::lavoroAutonomo;
        $srl = $this->creaSoggetto(53, TipoSoggetto::societaCapitali, '01234567897');
        $snc = $this->creaSoggetto(54, TipoSoggetto::societaPersone, '01234567897');
        $ente = $this->creaSoggetto(55, TipoSoggetto::ente);

        $this->assertTrue(AmbitoAdempimento::personeFisiche->isProponibile($persona));
        $this->assertFalse(AmbitoAdempimento::personeFisiche->isProponibile($srl));
        $this->assertTrue(AmbitoAdempimento::soggettiIva->isProponibile($professionista));
        $this->assertFalse(AmbitoAdempimento::soggettiIva->isProponibile($persona));
        $this->assertTrue(AmbitoAdempimento::imprese->isProponibile($ditta));
        $this->assertTrue(AmbitoAdempimento::imprese->isProponibile($snc));
        $this->assertFalse(AmbitoAdempimento::imprese->isProponibile($professionista));
        $this->assertTrue(AmbitoAdempimento::artigianiCommercianti->isProponibile($ditta));
        $this->assertFalse(AmbitoAdempimento::artigianiCommercianti->isProponibile($srl));
        $this->assertTrue(AmbitoAdempimento::societaEnti->isProponibile($ente));
        $this->assertFalse(AmbitoAdempimento::societaEnti->isProponibile($ditta));
        $this->assertTrue(AmbitoAdempimento::societaCapitali->isProponibile($srl));
        $this->assertTrue(AmbitoAdempimento::societaPersone->isProponibile($snc));
        $this->assertTrue(AmbitoAdempimento::enti->isProponibile($ente));
        $this->assertTrue(AmbitoAdempimento::tutti->isProponibile($ente));
        // la qualifica di sostituto d'imposta non è in Anagrafica: mai proposto
        $this->assertFalse(AmbitoAdempimento::sostitutiImposta->isProponibile($srl));

        // deceduti e cessati: nessuna proposta
        $persona->dataDecesso = new SismaDate('2025-03-01');
        $srl->dataCessazione = new SismaDate('2025-12-31');
        $this->assertFalse(AmbitoAdempimento::tutti->isProponibile($persona));
        $this->assertFalse(AmbitoAdempimento::societaCapitali->isProponibile($srl));

        foreach (AmbitoAdempimento::cases() as $ambito) {
            $this->assertNotSame('', $ambito->getLabel());
        }
    }

    public function testStatiFiltriECategorie(): void
    {
        $this->assertSame([StatoLavorazione::daFare, StatoLavorazione::inLavorazione], StatoLavorazione::aperti());
        $this->assertTrue(FiltroStato::aperte->isIncluso(StatoLavorazione::inLavorazione));
        $this->assertFalse(FiltroStato::aperte->isIncluso(StatoLavorazione::nonDovuta));
        $this->assertTrue(FiltroStato::chiuse->isIncluso(StatoLavorazione::completata));
        $this->assertFalse(FiltroStato::chiuse->isIncluso(StatoLavorazione::daFare));
        $this->assertTrue(FiltroStato::tutte->isIncluso(StatoLavorazione::daFare));
        foreach (StatoLavorazione::cases() as $stato) {
            $this->assertNotSame('', $stato->getLabel() . $stato->getClasseBadge());
        }
        foreach (FiltroStato::cases() as $filtro) {
            $this->assertNotSame('', $filtro->getLabel());
        }
        foreach (CategoriaAdempimento::cases() as $categoria) {
            $this->assertStringStartsWith('bi-', $categoria->getIcona());
            $this->assertNotSame('', $categoria->getLabel());
        }
    }

    public function testAdempimentoVisibilitaEStorico(): void
    {
        $calendario = $this->creaAdempimento();
        $proprio = $this->creaAdempimento(1000, 'INTERNO', true);
        $altroStudio = $this->creaStudio(2, 'Altro studio', '80012345676');
        $this->assertTrue($calendario->isDelCalendario());
        $this->assertTrue($calendario->isVisibile($altroStudio));
        $this->assertFalse($proprio->isDelCalendario());
        $this->assertTrue($proprio->isVisibile($this->studioPredefinito));
        $this->assertFalse($proprio->isVisibile($altroStudio));

        $assegnazione = $this->creaAssegnazione($this->creaSoggetto(), $calendario, '2025-01-01', '2025-12-31');
        $this->assertTrue($assegnazione->isVigenteAl(new SismaDate('2025-06-30')));
        $this->assertFalse($assegnazione->isVigenteAl(new SismaDate('2026-01-01')));
        $this->assertFalse($assegnazione->isAperto());
    }

    public function testCodificaDelRiferimentoNellUrl(): void
    {
        foreach (['T1001/2025/S8', 'S12/2026-11-16', 'dichiarazione-3', 'àèì/+=?'] as $riferimento) {
            $codificato = VoceScadenzario::codifica($riferimento);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $codificato);
            $this->assertSame($riferimento, VoceScadenzario::decodifica($codificato));
        }
        $this->assertNull(VoceScadenzario::decodifica('***'));
    }
}
