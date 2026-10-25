<?php

namespace Successioni\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\ComuneCatastaleTavolare;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\EstremoVoltura;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Entities\UfficioRegistrazione;
use Successioni\Application\Entities\UfficioTrascrizione;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\DirittoAbitazione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\ImpegnoTrasmissione;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\ResidenzaEsteraDefunto;
use Successioni\Application\Enumerations\RiduzioneSuccessioniPrecedenti;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoDocumentoTitolo;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Enumerations\TipoUnita;

class EntitiesAndEnumerationsTest extends SuiteTestCase
{

    public function testEnumerationsGradoParentela(): void
    {
        $this->assertTrue(GradoParentela::coniuge->isLineaRetta());
        $this->assertTrue(GradoParentela::figlio->isLineaRetta());
        $this->assertTrue(GradoParentela::figlio->isDiscendente());
        $this->assertFalse(GradoParentela::coniuge->isDiscendente());
        $this->assertTrue(GradoParentela::genitore->isAscendente());
        $this->assertTrue(GradoParentela::fratelloGermano->isFratello());
        $this->assertTrue(GradoParentela::fratelloUnilaterale->isFratello());
        $this->assertTrue(GradoParentela::cognato->isAffine());
        $this->assertTrue(GradoParentela::stato->isEnte());
        $this->assertTrue(GradoParentela::entiTerritorialiTerzoSettore->isEnte());
        $this->assertFalse(GradoParentela::estraneo->isAmmessoPerLegge());
        $this->assertFalse(GradoParentela::cognato->isAmmessoPerLegge());
        $this->assertTrue(GradoParentela::stato->isAmmessoPerLegge());
        $this->assertTrue(GradoParentela::cugino->isAmmessoPerLegge());

        $this->assertSame(0.0, GradoParentela::stato->getAliquota());
        $this->assertSame(4.0, GradoParentela::coniuge->getAliquota());
        $this->assertSame(6.0, GradoParentela::fratelloGermano->getAliquota());
        $this->assertSame(6.0, GradoParentela::cugino->getAliquota());
        $this->assertSame(8.0, GradoParentela::estraneo->getAliquota());

        $this->assertSame(1500000, GradoParentela::estraneo->getFranchigia(true));
        $this->assertSame(1000000, GradoParentela::figlio->getFranchigia(false));
        $this->assertSame(100000, GradoParentela::fratelloGermano->getFranchigia(false));
        $this->assertSame(0, GradoParentela::cugino->getFranchigia(false));

        foreach (GradoParentela::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
        }
    }

    public function testEnumerationsAgevolazioneAndCodiceDiritto(): void
    {
        $this->assertTrue(Agevolazione::beniCulturali->isEsenteSuccessione());
        $this->assertFalse(Agevolazione::primaCasa->isEsenteSuccessione());
        $this->assertSame(0.5, Agevolazione::beniCulturaliVincoloPosteriore->getRiduzione());
        $this->assertSame(0.4, Agevolazione::fondiRustici->getRiduzione());
        $this->assertSame(0.0, Agevolazione::primaCasa->getRiduzione());
        $this->assertTrue(Agevolazione::fondiRustici->hasValoreMassimoRiduzione());
        $this->assertTrue(Agevolazione::primaCasa->isPrimaCasa());
        $this->assertTrue(Agevolazione::isIpocatastaleProporzionale(null));
        $this->assertTrue(Agevolazione::isIpocatastaleProporzionale(Agevolazione::fondiRustici));
        $this->assertFalse(Agevolazione::isIpocatastaleProporzionale(Agevolazione::primaCasa));
        $this->assertTrue(Agevolazione::fondiMontani->isEsenteBollo());
        $this->assertTrue(Agevolazione::primaCasa->isEsclusiva());
        $this->assertFalse(Agevolazione::giovaniAgricoltori->isEsclusiva());

        foreach (Agevolazione::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
        }

        $this->assertSame(TitoloPossesso::proprieta, CodiceDiritto::proprieta->getTitoloPossesso());
        $this->assertSame(TitoloPossesso::nudaProprieta, CodiceDiritto::nudaProprieta->getTitoloPossesso());
        $this->assertSame(TitoloPossesso::usufrutto, CodiceDiritto::usufrutto->getTitoloPossesso());
        $this->assertNull(CodiceDiritto::servitu->getTitoloPossesso());

        $this->assertSame(CodiceDiritto::proprieta, CodiceDiritto::daTitoloPossesso(TitoloPossesso::proprieta));
        $this->assertSame(CodiceDiritto::nudaProprieta, CodiceDiritto::daTitoloPossesso(TitoloPossesso::nudaProprieta));
        $this->assertNull(CodiceDiritto::daTitoloPossesso(TitoloPossesso::usufrutto));
        $this->assertTrue(CodiceDiritto::usufrutto->isDirittoGodimento());
        $this->assertFalse(CodiceDiritto::proprieta->isDirittoGodimento());

        foreach (CodiceDiritto::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
        }
    }

    public function testEnumerationsQuadroBeneAndOthers(): void
    {
        $this->assertTrue(QuadroBene::terreni->isImmobile());
        $this->assertTrue(QuadroBene::terreni->isTerreno());
        $this->assertTrue(QuadroBene::fabbricati->isFabbricato());
        $this->assertTrue(QuadroBene::terreniTavolare->isTavolare());
        $this->assertFalse(QuadroBene::titoli->isImmobile());

        $this->assertSame(QuadroBene::terreni, QuadroBene::perImmobile(true, false));
        $this->assertSame(QuadroBene::terreniTavolare, QuadroBene::perImmobile(true, true));
        $this->assertSame(QuadroBene::fabbricati, QuadroBene::perImmobile(false, false));
        $this->assertSame(QuadroBene::fabbricatiTavolare, QuadroBene::perImmobile(false, true));

        foreach (QuadroBene::cases() as $quadro) {
            $this->assertNotEmpty($quadro->getLabel());
            $this->assertGreaterThan(0, $quadro->getRighiPerModulo());
            $this->assertNotEmpty($quadro->getDirittiDefunto());
            $this->assertNotEmpty($quadro->getDirittiDevoluzione());
            $this->assertNotEmpty($quadro->getAgevolazioni());
            $this->assertNotEmpty($quadro->getVoceRiepilogo());
        }

        $this->assertTrue(CaricaDichiarante::erede->isBeneficiario());
        $this->assertTrue(CaricaDichiarante::rappresentanteErede->richiedeRappresentato());
        $this->assertNotEmpty(CaricaDichiarante::getGestite());
        foreach (CaricaDichiarante::cases() as $c) {
            $this->assertNotEmpty($c->getLabel());
        }

        $this->assertSame(Agevolazione::primaCasa, DirittoAbitazione::primaCasaPrincipale->getAgevolazione());
        $this->assertNull(DirittoAbitazione::senzaPrimaCasa->getAgevolazione());
        $this->assertFalse(DirittoAbitazione::primaCasaPrincipale->isRinunciatario());
        $this->assertTrue(DirittoAbitazione::rinunciatarioPrincipale->isRinunciatario());
        foreach (DirittoAbitazione::cases() as $d) {
            $this->assertNotEmpty($d->getLabel());
        }

        foreach (ImpegnoTrasmissione::cases() as $i) {
            $this->assertNotEmpty($i->getLabel());
        }
        foreach (ResidenzaEsteraDefunto::cases() as $r) {
            $this->assertNotEmpty($r->getLabel());
        }
        $this->assertSame(0.5, RiduzioneSuccessioniPrecedenti::unAnno->getRiduzione());
        $this->assertSame(0.1, RiduzioneSuccessioniPrecedenti::cinqueAnni->getRiduzione());
        foreach (RiduzioneSuccessioniPrecedenti::cases() as $r) {
            $this->assertNotEmpty($r->getLabel());
        }

        $this->assertTrue(StatoCivileDefunto::coniugatoComunione->ammetteConiuge());
        $this->assertFalse(StatoCivileDefunto::vedovo->ammetteConiuge());
        $this->assertSame(StatoCivileDefunto::coniugatoComunione, StatoCivileDefunto::daAnagrafica(StatoCivile::coniugato));
        $this->assertNull(StatoCivileDefunto::daAnagrafica(null));
        foreach (StatoCivileDefunto::cases() as $s) {
            $this->assertNotEmpty($s->getLabel());
        }

        foreach (StatoDichiarazioneSuccessione::cases() as $s) {
            $this->assertNotEmpty($s->getLabel());
            $this->assertNotEmpty($s->getClasseBadge());
        }
        foreach (TempisticaPagamento::cases() as $t) {
            $this->assertNotEmpty($t->getLabel());
        }
        foreach (TipoAllegato::cases() as $a) {
            $this->assertNotEmpty($a->getLabel());
            $this->assertNotEmpty($a->getElemento());
        }
        $this->assertTrue(TipoBeneficiario::erede->isErede());
        $this->assertFalse(TipoBeneficiario::legatario->isErede());
        $this->assertNotEmpty(TipoBeneficiario::getGestiti());
        foreach (TipoBeneficiario::cases() as $b) {
            $this->assertNotEmpty($b->getLabel());
        }
        foreach (TipoCespite::cases() as $c) {
            $this->assertNotEmpty($c->getLabel());
        }
        $this->assertFalse(TipoDichiarazioneSuccessione::prima->isSostitutiva());
        $this->assertTrue(TipoDichiarazioneSuccessione::sostitutivaConTrascrizione->isSostitutiva());
        $this->assertTrue(TipoDichiarazioneSuccessione::prima->hasQuadri());
        $this->assertFalse(TipoDichiarazioneSuccessione::integrativaDocumentazione->hasQuadri());
        foreach (TipoDichiarazioneSuccessione::cases() as $td) {
            $this->assertNotEmpty($td->getLabel());
        }
        foreach (TipoDocumentoTitolo::cases() as $tdt) {
            $this->assertNotEmpty($tdt->getLabel());
        }
        foreach (TipologiaTerreno::cases() as $tt) {
            $this->assertNotEmpty($tt->getLabel());
        }
        $this->assertTrue(TipoPassivita::mutui->richiedeCespite());
        $this->assertFalse(TipoPassivita::speseFunerarie->richiedeCespite());
        $this->assertTrue(TipoPassivita::mutui->hasQuota());
        $this->assertFalse(TipoPassivita::legatoOnere->hasQuota());
        foreach (TipoPassivita::cases() as $tp) {
            $this->assertNotEmpty($tp->getLabel());
        }
        $this->assertTrue(TipoTitolo::esente->isEsente());
        $this->assertFalse(TipoTitolo::quotato->isEsente());
        foreach (TipoTitolo::cases() as $tt) {
            $this->assertNotEmpty($tt->getLabel());
        }
        foreach (TipoUnita::cases() as $tu) {
            $this->assertNotEmpty($tu->getLabel());
            $this->assertNotEmpty($tu->getDimensione());
        }
    }

    public function testEntities(): void
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-03-15');

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;
        $this->assertInstanceOf(SismaDateTime::class, $dichiarazione->dataInserimento);
        $this->assertTrue($dichiarazione->isModificabile());
        $this->assertTrue($dichiarazione->isAutoliquidazione());
        $this->assertSame('2026-03-15', $dichiarazione->getScadenza()->format('Y-m-d'));
        $this->assertSame('2026-06-13', $dichiarazione->getScadenzaPagamento()->format('Y-m-d'));
        $this->assertSame('dichiarazione-1', $dichiarazione->getRiferimentoVersamenti());
        $this->assertStringContainsString('ROSSI MARIO', $dichiarazione->getTitolo());

        $dichiarazione->stato = StatoDichiarazioneSuccessione::presentata;
        $this->assertFalse($dichiarazione->isModificabile());

        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->ordine = 4;
        $this->assertSame(2, $beneficiario->getModulo());
        $this->assertSame(1, $beneficiario->getRigo());
        $this->assertSame('EA1 mod. 2', $beneficiario->getRiferimento());
        $this->assertTrue($beneficiario->isDestinatario());
        $beneficiario->rinuncia = true;
        $this->assertFalse($beneficiario->isDestinatario());

        $comune = new Comune($this->dataMapper);
        $comune->denominazione = 'ROMA';
        $immobile = new Immobile($this->dataMapper);
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->foglio = '10';
        $immobile->particella = '20';
        $immobile->comune = $comune;

        $bene = new BeneSuccessione($this->dataMapper);
        $bene->quadro = QuadroBene::fabbricati;
        $bene->immobile = $immobile;
        $bene->quotaNumeratore = 1.0;
        $bene->quotaDenominatore = 2;
        $bene->valore = 50000;
        $bene->valoreEsente = 10000;
        $this->assertSame(0.5, $bene->getQuota());
        $this->assertSame(60000, $bene->getValoreTotale());
        $this->assertStringContainsString('ROMA', $bene->getDescrizioneBreve());
        $this->assertFalse($bene->isAziendale());

        $beneAereo = new BeneSuccessione($this->dataMapper);
        $beneAereo->quadro = QuadroBene::aeromobili;
        $beneAereo->marcaModello = 'CESSNA';
        $beneAereo->numeroIscrizione = 'I-ABCD';
        $this->assertSame('CESSNA I-ABCD', $beneAereo->getDescrizioneBreve());

        $beneNave = new BeneSuccessione($this->dataMapper);
        $beneNave->quadro = QuadroBene::navi;
        $beneNave->tipoUnita = TipoUnita::vela;
        $beneNave->ufficioIscrizione = 'GE';
        $beneNave->numeroIscrizione = '123';
        $this->assertStringContainsString('GE 123', $beneNave->getDescrizioneBreve());

        $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
        $devoluzione->quotaNumeratore = 1;
        $devoluzione->quotaDenominatore = 3;
        $this->assertEqualsWithDelta(1 / 3, $devoluzione->getQuota(), 0.0001);
        $this->assertSame('1/3', $devoluzione->getQuotaTesto());

        $ripartizione = new RipartizionePassivita($this->dataMapper);
        $ripartizione->quotaNumeratore = 1;
        $ripartizione->quotaDenominatore = 4;
        $this->assertSame(0.25, $ripartizione->getQuota());

        $ufficioReg = new UfficioRegistrazione($this->dataMapper);
        $ufficioReg->codice = 'TG2';
        $ufficioReg->denominazione = 'ROMA 2';
        $ufficioReg->periodo = 'dal 2000';
        $this->assertSame('TG2 - ROMA 2 (dal 2000)', $ufficioReg->getEtichetta());

        $ufficioTrasc = new UfficioTrascrizione($this->dataMapper);
        $ufficioTrasc->codice = 'RM10';
        $ufficioTrasc->denominazione = 'ROMA 1';
        $this->assertSame('RM10 - ROMA 1', $ufficioTrasc->getEtichetta());

        $estremo = new EstremoVoltura($this->dataMapper);
        $estremo->tipo = EstremoVoltura::TRASCRIZIONE;
        $estremo->ufficioTrascrizione = $ufficioTrasc;
        $estremo->numero = '1234';
        $estremo->anno = 2010;
        $this->assertTrue($estremo->isTrascrizione());
        $this->assertStringContainsString('RM10', $estremo->getDescrizione());

        $estremo->tipo = EstremoVoltura::REGISTRAZIONE;
        $estremo->ufficioRegistrazione = $ufficioReg;
        $estremo->volume = '99';
        $this->assertFalse($estremo->isTrascrizione());
        $this->assertStringContainsString('TG2', $estremo->getDescrizione());

        $allegato = new AllegatoSuccessione($this->dataMapper);
        $this->assertInstanceOf(SismaDateTime::class, $allegato->dataInserimento);

        $contenuto = new ContenutoAllegato($this->dataMapper);
        $contenuto->contenuto = 'YmFzZTY0';
        $this->assertSame('YmFzZTY0', $contenuto->contenuto);

        $comuneTav = new ComuneCatastaleTavolare($this->dataMapper);
        $comuneTav->codice = '001';
        $comuneTav->denominazione = 'ALA';
        $this->assertSame('001', $comuneTav->codice);

        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->valore = 1000;
        $this->assertSame(1000, $passivita->valore);
    }
}
