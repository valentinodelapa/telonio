<?php

namespace Anagrafica\Tests;

use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\DocumentoIdentita;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoDocumento;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;

class EntitiesAndEnumerationsTest extends SuiteTestCase
{

    public function testStoricizzabileTraitMetodi(): void
    {
        $dati = new DatiFiscali($this->dataMapper);
        $this->assertEquals((new SismaDate())->format('Y-m-d'), $dati->validoDal->format('Y-m-d'));
        $this->assertNull($dati->validoAl);
        $this->assertTrue($dati->isAperto());

        $dati->validoDal = new SismaDate('2022-01-01');
        $dati->validoAl = new SismaDate('2023-12-31');
        $this->assertFalse($dati->isAperto());
        $this->assertTrue($dati->isVigenteAl(new \DateTimeImmutable('2022-06-15')));
        $this->assertTrue($dati->isVigenteAl(new \DateTimeImmutable('2022-01-01')));
        $this->assertTrue($dati->isVigenteAl(new \DateTimeImmutable('2023-12-31')));
        $this->assertFalse($dati->isVigenteAl(new \DateTimeImmutable('2021-12-31')));
        $this->assertFalse($dati->isVigenteAl(new \DateTimeImmutable('2024-01-01')));
    }

    public function testSoggettoEntity(): void
    {
        $pf = new Soggetto($this->dataMapper);
        $pf->tipoSoggetto = TipoSoggetto::personaFisica;
        $pf->dataNascita = new SismaDate('1980-01-01');
        $this->assertTrue($pf->clienteStudio);
        $this->assertTrue($pf->isPersonaFisica());
        $this->assertFalse($pf->isCessato());
        $this->assertFalse($pf->isDeceduto());
        $this->assertSame('Persona fisica', $pf->getDescrizioneTipo());
        $this->assertSame($pf->dataNascita, $pf->getDataInizio());

        $pf->partitaIva = '01234567897';
        $pf->tipoAttivita = TipoAttivita::impresaIndividuale;
        $this->assertSame('Persona fisica · Impresa individuale', $pf->getDescrizioneTipo());

        $pf->dataDecesso = new SismaDate('2024-05-10');
        $this->assertTrue($pf->isDeceduto(new \DateTimeImmutable('2024-06-01')));
        $this->assertFalse($pf->isDeceduto(new \DateTimeImmutable('2024-05-01')));

        $pf->dataCessazione = new SismaDate('2024-05-10');
        $this->assertTrue($pf->isCessato(new \DateTimeImmutable('2024-06-01')));
        $this->assertFalse($pf->isCessato(new \DateTimeImmutable('2024-05-01')));

        $soc = new Soggetto($this->dataMapper);
        $soc->tipoSoggetto = TipoSoggetto::societaCapitali;
        $soc->dataCostituzione = new SismaDate('2015-03-10');
        $this->assertFalse($soc->isPersonaFisica());
        $this->assertSame('Società di capitali', $soc->getDescrizioneTipo());
        $this->assertSame($soc->dataCostituzione, $soc->getDataInizio());
    }

    public function testComuneENazioneECodiceAtecoEntities(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';
        $comune->validoDal = new SismaDate('1884-01-01');
        $comune->validoAl = null;
        $this->assertSame('Roma (RM)', $comune->getEtichetta());
        $this->assertTrue($comune->isVigenteAl(new \DateTimeImmutable('2025-01-01')));
        $this->assertFalse($comune->isSoppresso());

        $comune->validoDal = new SismaDate('1900-01-01');
        $comune->validoAl = new SismaDate('1990-12-31');
        $this->assertFalse($comune->isVigenteAl(new \DateTimeImmutable('2000-01-01')));
        $this->assertFalse($comune->isVigenteAl(new \DateTimeImmutable('1850-01-01')));
        $this->assertTrue($comune->isVigenteAl(new \DateTimeImmutable('1950-06-01')));
        $this->assertTrue($comune->isSoppresso());

        $nazione = new Nazione($this->dataMapper);
        $nazione->denominazione = 'Italia';
        $nazione->codiceIso = 'IT';
        $nazione->validoDal = new SismaDate('1861-03-17');
        $nazione->validoAl = null;
        $this->assertTrue($nazione->isItalia());
        $this->assertTrue($nazione->isVigenteAl(new \DateTimeImmutable('2025-01-01')));

        $francia = new Nazione($this->dataMapper);
        $francia->denominazione = 'Francia';
        $francia->codiceIso = 'FR';
        $francia->validoDal = new SismaDate('1900-01-01');
        $francia->validoAl = new SismaDate('2020-12-31');
        $this->assertFalse($francia->isItalia());
        $this->assertFalse($francia->isVigenteAl(new \DateTimeImmutable('2025-01-01')));

        $ateco = new CodiceAteco($this->dataMapper);
        $ateco->classificazione = ClassificazioneAteco::ateco2025;
        $ateco->codice = '69.20.01';
        $ateco->titolo = 'Attività dei dottori commercialisti';
        $ateco->livello = CodiceAteco::LIVELLO_SOTTOCATEGORIA;
        $this->assertTrue($ateco->isSottocategoria());
        $this->assertSame('69.20.01 - Attività dei dottori commercialisti', $ateco->getEtichetta());
    }

    public function testImmobileEDatiCatastaliEntities(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->denominazione = 'Milano';
        $comune->siglaProvincia = 'MI';
        $comune->codiceCatastale = 'F205';

        $immobile = new Immobile($this->dataMapper);
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->sezione = 'A';
        $immobile->foglio = '12';
        $immobile->particella = '345';
        $immobile->subalterno = '7';
        $immobile->indirizzo = 'Via Torino';
        $immobile->numeroCivico = '10';
        $immobile->piano = '2';
        $immobile->interno = '4';
        $immobile->partitaTavolare = '1234';

        $this->assertSame('Sez. A Fg. 12 Part. 345 Sub. 7', $immobile->getIdentificativo());
        $this->assertSame('Via Torino, 10 - piano 2 int. 4 - Milano (MI)', $immobile->getUbicazione());
        $this->assertSame('F|F205|A|12|345|7', $immobile->getChiaveCatastale());
        $this->assertTrue($immobile->isTavolare());

        $terreno = new Immobile($this->dataMapper);
        $terreno->tipoCatasto = TipoCatasto::terreni;
        $terreno->comune = $comune;
        $terreno->foglio = '5';
        $terreno->particella = '88';
        $this->assertFalse($terreno->isTavolare());
        $this->assertSame('Milano (MI)', $terreno->getUbicazione());

        $comuneBz = new Comune($this->dataMapper);
        $comuneBz->denominazione = 'Bolzano';
        $comuneBz->siglaProvincia = 'BZ';
        $comuneBz->codiceCatastale = 'A952';
        $terreno->comune = $comuneBz;
        $this->assertTrue($terreno->isTavolare());

        $datiFab = new DatiCatastali($this->dataMapper);
        $datiFab->categoria = CategoriaCatastale::a2;
        $datiFab->classe = '3';
        $datiFab->consistenza = 5.5;
        $datiFab->superficie = 110.0;
        $datiFab->rendita = 850.45;
        $this->assertTrue($datiFab->isAperto());
    }

    public function testIndirizzoContoDocumentoRecapitoPossessoLegameEntities(): void
    {
        $italia = new Nazione($this->dataMapper);
        $italia->denominazione = 'Italia';
        $italia->codiceIso = 'IT';

        $comune = new Comune($this->dataMapper);
        $comune->denominazione = 'Torino';
        $comune->siglaProvincia = 'TO';
        $comune->codiceCatastale = 'L219';

        $ind = new Indirizzo($this->dataMapper);
        $ind->indirizzo = 'Corso Francia';
        $ind->numeroCivico = '15';
        $ind->frazione = 'Centro';
        $ind->presso = 'Studio Rossi';
        $ind->cap = '10138';
        $ind->comune = $comune;
        $ind->nazione = $italia;
        $this->assertSame('Corso Francia, 15', $ind->getRiga1());
        $this->assertSame('10138 Centro - Torino (TO)', $ind->getRiga2());

        $francia = new Nazione($this->dataMapper);
        $francia->denominazione = 'Francia';
        $francia->codiceIso = 'FR';

        $indEstero = new Indirizzo($this->dataMapper);
        $indEstero->indirizzo = 'Rue de Rivoli';
        $indEstero->localitaEstera = 'Parigi';
        $indEstero->nazione = $francia;
        $this->assertSame('Rue de Rivoli', $indEstero->getRiga1());
        $this->assertSame('Parigi - Francia', $indEstero->getRiga2());

        $conto = new ContoCorrente($this->dataMapper);
        $conto->iban = 'IT60X0542811101000000123456';
        $conto->descrizione = 'Banca Intesa';
        $this->assertTrue($conto->attivo);
        $this->assertFalse($conto->predefinito);
        $this->assertTrue($conto->isItaliano());
        $this->assertSame('IT60 X054 2811 1010 0000 0123 456', $conto->getIbanFormattato());
        $this->assertSame('IT60 X054 2811 1010 0000 0123 456 - Banca Intesa', $conto->getEtichetta());

        $doc = new DocumentoIdentita($this->dataMapper);
        $this->assertFalse($doc->isScaduto());
        $doc->dataScadenza = new SismaDate('2020-01-01');
        $this->assertTrue($doc->isScaduto());
        $doc->dataScadenza = new SismaDate('2099-01-01');
        $this->assertFalse($doc->isScaduto());

        $rec = new Recapito($this->dataMapper);
        $this->assertFalse($rec->principale);

        $possesso = new Possesso($this->dataMapper);
        $possesso->validoDal = new SismaDate('2020-01-01');
        $this->assertTrue($possesso->isAperto());

        $legame = new Legame($this->dataMapper);
        $legame->validoDal = new SismaDate('2020-01-01');
        $this->assertTrue($legame->isAperto());
    }

    public function testEnumerationsComplete(): void
    {
        foreach (CategoriaCatastale::cases() as $cat) {
            $this->assertNotEmpty($cat->getDescrizione());
            $this->assertNotEmpty($cat->getLabel());
            $this->assertContains($cat->getGruppo(), ['A', 'B', 'C', 'D', 'E', 'F']);
        }
        $this->assertTrue(CategoriaCatastale::a2->isAbitativa());
        $this->assertFalse(CategoriaCatastale::a10->isAbitativa());
        $this->assertFalse(CategoriaCatastale::f1->haRendita());
        $this->assertTrue(CategoriaCatastale::a2->haRendita());

        $this->assertSame(ClassificazioneAteco::ateco2022, ClassificazioneAteco::vigenteAl(new \DateTimeImmutable('2025-03-31')));
        $this->assertSame(ClassificazioneAteco::ateco2025, ClassificazioneAteco::vigenteAl(new \DateTimeImmutable('2025-04-01')));
        $this->assertNotEmpty(ClassificazioneAteco::ateco2025->getLabel());

        foreach (NaturaGiuridica::cases() as $nat) {
            $this->assertNotEmpty($nat->getLabel());
            $this->assertInstanceOf(TipoSoggetto::class, $nat->getTipoSoggetto());
        }
        $this->assertSame(TipoSoggetto::societaPersone, NaturaGiuridica::societaNomeCollettivo->getTipoSoggetto());
        $this->assertSame(TipoSoggetto::societaCapitali, NaturaGiuridica::societaResponsabilitaLimitata->getTipoSoggetto());
        $this->assertSame(TipoSoggetto::ente, NaturaGiuridica::associazione->getTipoSoggetto());

        foreach (RuoloLegame::cases() as $ruolo) {
            $this->assertNotEmpty($ruolo->getLabel());
        }
        $this->assertTrue(RuoloLegame::socio->prevedeQuota());
        $this->assertFalse(RuoloLegame::amministratoreUnico->prevedeQuota());

        $this->assertSame('Maschio', Sesso::maschio->getLabel());
        $this->assertSame('Femmina', Sesso::femmina->getLabel());

        foreach (StatoCivile::cases() as $sc) {
            $this->assertNotEmpty($sc->getLabel());
        }

        foreach (TipoAttivita::cases() as $ta) {
            $this->assertNotEmpty($ta->getLabel());
            $this->assertNotEmpty($ta->getIcona());
        }
        $this->assertTrue(TipoAttivita::impresaIndividuale->isImpresa());
        $this->assertFalse(TipoAttivita::lavoroAutonomo->isImpresa());

        foreach (TipoCatasto::cases() as $tc) {
            $this->assertNotEmpty($tc->getLabel());
            $this->assertNotEmpty($tc->getIcona());
        }

        foreach (TipoDocumento::cases() as $td) {
            $this->assertNotEmpty($td->getLabel());
        }

        foreach (TipoIndirizzo::cases() as $ti) {
            $this->assertNotEmpty($ti->getLabel());
        }
        $this->assertTrue(TipoIndirizzo::residenza->isApplicabile(TipoSoggetto::personaFisica));
        $this->assertFalse(TipoIndirizzo::residenza->isApplicabile(TipoSoggetto::societaCapitali));
        $this->assertTrue(TipoIndirizzo::sedeLegale->isApplicabile(TipoSoggetto::societaCapitali));
        $this->assertFalse(TipoIndirizzo::sedeLegale->isApplicabile(TipoSoggetto::personaFisica));

        foreach (TipoRecapito::cases() as $tr) {
            $this->assertNotEmpty($tr->getLabel());
            $this->assertNotEmpty($tr->getIcona());
        }
        $this->assertTrue(TipoRecapito::email->isIndirizzoEmail());
        $this->assertTrue(TipoRecapito::pec->isIndirizzoEmail());
        $this->assertFalse(TipoRecapito::telefono->isIndirizzoEmail());
        $this->assertSame('mailto:info@test.it', TipoRecapito::email->getCollegamento('info@test.it'));
        $this->assertSame('tel:+390212345', TipoRecapito::telefono->getCollegamento('+39 02 12345'));
        $this->assertSame('https://esempio.it', TipoRecapito::sitoWeb->getCollegamento('esempio.it'));
        $this->assertSame('http://esempio.it', TipoRecapito::sitoWeb->getCollegamento('http://esempio.it'));
        $this->assertNull(TipoRecapito::fax->getCollegamento('0212345'));

        foreach (TipoSoggetto::cases() as $ts) {
            $this->assertNotEmpty($ts->getLabel());
            $this->assertNotEmpty($ts->getIcona());
            $this->assertNotEmpty($ts->getTipiIndirizzo());
        }
        $this->assertEmpty(TipoSoggetto::personaFisica->getNatureGiuridiche());
        $this->assertNotEmpty(TipoSoggetto::societaCapitali->getNatureGiuridiche());

        foreach (TitoloPossesso::cases() as $tp) {
            $this->assertNotEmpty($tp->getLabel());
            $this->assertNotEmpty($tp->getComponenti());
        }
        $this->assertSame(['dominio', 'godimento'], TitoloPossesso::proprieta->getComponenti());
        $this->assertSame(['dominio'], TitoloPossesso::nudaProprieta->getComponenti());
        $this->assertSame(['godimento'], TitoloPossesso::usufrutto->getComponenti());

        foreach (UtilizzoImmobile::cases() as $ui) {
            $this->assertNotEmpty($ui->getLabel());
        }
    }
}
