<?php

namespace Anagrafica\Tests\Forms;

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
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoDocumento;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Forms\ContoCorrenteForm;
use Anagrafica\Application\Forms\DatiCatastaliForm;
use Anagrafica\Application\Forms\DatiFiscaliForm;
use Anagrafica\Application\Forms\DocumentoIdentitaForm;
use Anagrafica\Application\Forms\ImmobileForm;
use Anagrafica\Application\Forms\IndirizzoForm;
use Anagrafica\Application\Forms\LegameForm;
use Anagrafica\Application\Forms\PossessoForm;
use Anagrafica\Application\Forms\RecapitoForm;
use Anagrafica\Application\Forms\SoggettoForm;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use SismaFramework\Orm\CustomTypes\SismaDate;

class FormsTest extends SuiteTestCase
{

    public function testSoggettoFormPersonaFisicaCalcoloCfEValidazioni(): void
    {
        $studio = $this->creaStudio(1);
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';
        $comune->validoDal = new SismaDate('1900-01-01');
        $comune->validoAl = null;

        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;

        // Con ormCache=false in SuiteTestCase:
        // 1. injectRequest -> allineaComuneNascita -> getEntityById(10)
        // 2. injectRequest -> allineaComuneNascita -> getByCodiceAl('H501')
        // 3. injectRequest -> calcolaCodiceFiscale -> getEntityById(10)
        // 4. FormValidator -> Parser::parseEntity(Comune, 10)
        // 5. customFilter -> esisteCodiceFiscale -> getByStudioAndCodiceFiscale -> []
        $this->testAdapter->accodaSelect(
            new TestResultSet([$comune]),
            new TestResultSet([$comune]),
            new TestResultSet([$comune]),
            new TestResultSet([$comune]),
            new TestResultSet([]),
        );

        $form = new SoggettoForm($soggetto, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'cognome' => 'Rossi',
            'nome' => 'Mario',
            'sesso' => 'M',
            'dataNascita' => '1980-01-01',
            'comuneNascita' => '10',
            'codiceFiscale' => '',
            'clienteStudio' => '1',
        ]));

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isCodiceFiscaleCalcolato());
        $risolto = $form->resolveEntity();
        $this->assertSame('RSSMRA80A01H501U', $risolto->codiceFiscale);
        $this->assertSame('ROSSI MARIO', $risolto->nominativo);

        // Società con codice fiscale numerico errato e dataCessazione < dataCostituzione
        $soc = new Soggetto($this->dataMapper);
        $soc->studio = $studio;
        $soc->tipoSoggetto = TipoSoggetto::societaCapitali;

        $formSoc = new SoggettoForm($soc, $this->dataMapper);
        $formSoc->handleRequest($this->creaRequest([
            'codiceFiscale' => '12345678901',
            'clienteStudio' => '1',
            'dataCostituzione' => '2022-01-01',
            'dataCessazione' => '2020-01-01',
        ]));
        $this->assertFalse($formSoc->isValid());
        $this->assertNotEmpty($formSoc->getFilterErrors()->codiceFiscaleCustomMessage);
        $this->assertNotEmpty($formSoc->getFilterErrors()->dataCessazioneCustomMessage);
    }

    public function testImmobileForm(): void
    {
        $studio = $this->creaStudio(1);
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->codiceCatastale = 'H501';

        $immobile = new Immobile($this->dataMapper);
        $immobile->studio = $studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;

        // Caso valido (nessun immobile duplicato)
        $this->testAdapter->accodaSelect(
            new TestResultSet([$comune]),
            new TestResultSet([]),
        );

        $form = new ImmobileForm($immobile, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'comune' => '10',
            'sezione' => ' a ',
            'foglio' => ' 12 ',
            'particella' => ' 345 ',
            'subalterno' => ' 7 ',
            'indirizzo' => 'Via   Roma',
        ]));

        $this->assertTrue($form->isValid());
        $risolto = $form->resolveEntity();
        $this->assertSame('A', $risolto->sezione);
        $this->assertSame('12', $risolto->foglio);
        $this->assertSame('Via Roma', $risolto->indirizzo);

        // Caso duplicato bloccato
        $esistente = new Immobile($this->dataMapper);
        $esistente->id = 99;
        $esistente->studio = $studio;
        $esistente->tipoCatasto = TipoCatasto::fabbricati;
        $esistente->comune = $comune;
        $esistente->sezione = 'A';
        $esistente->foglio = '12';
        $esistente->particella = '345';
        $esistente->subalterno = '7';

        $this->testAdapter->accodaSelect(
            new TestResultSet([$comune]),
            new TestResultSet([$esistente]),
        );

        $immDup = new Immobile($this->dataMapper);
        $immDup->studio = $studio;
        $immDup->tipoCatasto = TipoCatasto::fabbricati;
        $formDup = new ImmobileForm($immDup, $this->dataMapper);
        $formDup->handleRequest($this->creaRequest([
            'comune' => '10',
            'sezione' => 'A',
            'foglio' => '12',
            'particella' => '345',
            'subalterno' => '7',
        ]));
        $this->assertFalse($formDup->isValid());
        $this->assertNotEmpty($formDup->getFilterErrors()->particellaCustomMessage);
    }

    public function testDatiFiscaliForm(): void
    {
        $studio = $this->creaStudio(1);
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 5;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::societaCapitali;

        $ateco = new CodiceAteco($this->dataMapper);
        $ateco->id = 20;
        $ateco->classificazione = ClassificazioneAteco::ateco2025;
        $ateco->codice = '69.20.01';
        $ateco->livello = CodiceAteco::LIVELLO_SOTTOCATEGORIA;

        $df = new DatiFiscali($this->dataMapper);
        $df->soggetto = $soggetto;

        // Valido per società di capitali
        $this->testAdapter->accodaSelect(
            new TestResultSet([$ateco]),
            new TestResultSet([]), // getByPartitaIva
        );

        $form = new DatiFiscaliForm($df, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'validoDal' => '2025-05-01',
            'denominazione' => 'Alfa S.r.l.',
            'naturaGiuridica' => NaturaGiuridica::societaResponsabilitaLimitata->value,
            'partitaIva' => 'IT 01114601006',
            'codiceAteco' => '20',
            'enteNonCommerciale' => '0',
        ]));
        $this->assertTrue($form->isValid());
        $this->assertSame('01114601006', $form->resolveEntity()->partitaIva);

        // Invalido per persona fisica con denominazione senza tipoAttivita
        $pf = new Soggetto($this->dataMapper);
        $pf->id = 6;
        $pf->studio = $studio;
        $pf->tipoSoggetto = TipoSoggetto::personaFisica;
        $dfPf = new DatiFiscali($this->dataMapper);
        $dfPf->soggetto = $pf;

        $formPf = new DatiFiscaliForm($dfPf, $this->dataMapper);
        $formPf->handleRequest($this->creaRequest([
            'validoDal' => '2025-05-01',
            'denominazione' => 'Ditta Rossi',
        ]));
        $this->assertFalse($formPf->isValid());
        $this->assertNotEmpty($formPf->getFilterErrors()->denominazioneCustomMessage);
    }

    public function testDatiCatastaliForm(): void
    {
        $immobile = new Immobile($this->dataMapper);
        $immobile->tipoCatasto = TipoCatasto::fabbricati;

        $dc = new DatiCatastali($this->dataMapper);
        $dc->immobile = $immobile;

        $form = new DatiCatastaliForm($dc, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'validoDal' => '2024-01-01',
            'causale' => 'C',
            'categoria' => CategoriaCatastale::a2->value,
            'classe' => ' 3 ',
            'consistenza' => '5,5',
            'superficie' => '110',
            'rendita' => '1.250,50',
        ]));
        $this->assertTrue($form->isValid());
        $risolto = $form->resolveEntity();
        $this->assertSame(1250.50, $risolto->rendita);

        // Categoria F/1 con rendita -> errore
        $formErr = new DatiCatastaliForm($dc, $this->dataMapper);
        $formErr->handleRequest($this->creaRequest([
            'validoDal' => '2024-01-01',
            'causale' => 'C',
            'categoria' => CategoriaCatastale::f1->value,
            'rendita' => '100',
        ]));
        $this->assertFalse($formErr->isValid());
        $this->assertNotEmpty($formErr->getFilterErrors()->renditaCustomMessage);

        // Form combinato con il possesso: decorrenza vuota = inizio del possesso, indicata = quella
        $dcPossesso = new DatiCatastali($this->dataMapper);
        $dcPossesso->immobile = $immobile;
        $formPossesso = (new DatiCatastaliForm($dcPossesso, $this->dataMapper))->conDecorrenzaDelPossesso('possesso');
        $formPossesso->handleRequest($this->creaRequest([
            'validoDal' => '',
            'causale' => 'C',
            'categoria' => CategoriaCatastale::a2->value,
            'rendita' => '500',
            'possesso' => ['validoDal' => '2010-03-15'],
        ]));
        $this->assertTrue($formPossesso->isValid());
        $this->assertSame('2010-03-15', $formPossesso->resolveEntity()->validoDal->format('Y-m-d'));
        $dcIndicata = new DatiCatastali($this->dataMapper);
        $dcIndicata->immobile = $immobile;
        $formIndicata = (new DatiCatastaliForm($dcIndicata, $this->dataMapper))->conDecorrenzaDelPossesso('possesso');
        $formIndicata->handleRequest($this->creaRequest([
            'validoDal' => '2020-01-01',
            'causale' => 'E',
            'categoria' => CategoriaCatastale::a2->value,
            'rendita' => '500',
            'possesso' => ['validoDal' => '2010-03-15'],
        ]));
        $this->assertTrue($formIndicata->isValid());
        $this->assertSame('2020-01-01', $formIndicata->resolveEntity()->validoDal->format('Y-m-d'));
        $this->assertSame('E', $formIndicata->resolveEntity()->causale->value);

        // Terreni: niente variazioni per interventi edilizi; causale obbligatoria
        $terreno = new Immobile($this->dataMapper);
        $terreno->tipoCatasto = TipoCatasto::terreni;
        foreach (['E' => 'causaleCustomMessage', '' => null] as $causale => $messaggio) {
            $dcTerreno = new DatiCatastali($this->dataMapper);
            $dcTerreno->immobile = $terreno;
            $formTerreno = new DatiCatastaliForm($dcTerreno, $this->dataMapper);
            $formTerreno->handleRequest($this->creaRequest([
                'validoDal' => '2024-01-01',
                'causale' => $causale,
                'qualitaColtura' => 'SEMINATIVO',
                'redditoDominicale' => '10',
                'redditoAgrario' => '8',
            ]));
            $this->assertFalse($formTerreno->isValid());
            if ($messaggio !== null) {
                $this->assertNotEmpty($formTerreno->getFilterErrors()->$messaggio);
            }
        }
    }

    public function testIndirizzoLegamePossessoContoDocumentoRecapitoForms(): void
    {
        $studio = $this->creaStudio(1);
        $pf = new Soggetto($this->dataMapper);
        $pf->id = 1;
        $pf->studio = $studio;
        $pf->tipoSoggetto = TipoSoggetto::personaFisica;

        $italia = new Nazione($this->dataMapper);
        $italia->id = 1;
        $italia->codiceIso = 'IT';
        $italia->denominazione = 'Italia';

        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->denominazione = 'Roma';
        $comune->codiceCatastale = 'H501';

        // IndirizzoForm
        $ind = new Indirizzo($this->dataMapper);
        $ind->soggetto = $pf;
        $this->testAdapter->accodaSelect(
            new TestResultSet([$italia]), // getItalia in injectRequest
            new TestResultSet([$comune]), // Parser::parseEntity comune
            new TestResultSet([$italia]), // Parser::parseEntity nazione
        );
        $indForm = new IndirizzoForm($ind, $this->dataMapper);
        $indForm->handleRequest($this->creaRequest([
            'tipoIndirizzo' => TipoIndirizzo::residenza->value,
            'validoDal' => '2024-01-01',
            'indirizzo' => 'Via Nazionale',
            'cap' => '00184',
            'comune' => '10',
        ]));
        $this->assertTrue($indForm->isValid());

        // LegameForm
        $collegato = new Soggetto($this->dataMapper);
        $collegato->id = 2;
        $collegato->studio = $studio;
        $collegato->tipoSoggetto = TipoSoggetto::personaFisica;

        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $pf;
        $this->testAdapter->accodaSelect(new TestResultSet([$collegato]));
        $legameForm = new LegameForm($legame, $this->dataMapper);
        $legameForm->handleRequest($this->creaRequest([
            'soggettoCollegato' => '2',
            'ruolo' => RuoloLegame::collaboratoreFamiliare->value,
            'validoDal' => '2024-01-01',
            'quotaPartecipazione' => '49,00',
        ]));
        $this->assertTrue($legameForm->isValid());

        // PossessoForm con prefisso
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 5;
        $immobile->studio = $studio;
        $possesso = new Possesso($this->dataMapper);
        $possesso->soggetto = $pf;
        $possesso->immobile = $immobile;

        $possessoForm = (new PossessoForm($possesso, $this->dataMapper))->conPrefisso('possesso');
        $possessoForm->handleRequest($this->creaRequest([
            'possesso' => [
                'titolo' => TitoloPossesso::proprieta->value,
                'quota' => '50,00',
                'validoDal' => '2024-01-01',
                'utilizzo' => UtilizzoImmobile::abitazionePrincipale->value,
            ],
        ]));
        $this->assertTrue($possessoForm->isValid());
        $this->assertSame(50.0, $possessoForm->resolveEntity()->quota);

        // ContoCorrenteForm
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $pf;
        $ccForm = new ContoCorrenteForm($conto, $this->dataMapper);
        $ccForm->handleRequest($this->creaRequest([
            'iban' => 'IT60 X054 2811 1010 0000 0123 456',
            'descrizione' => 'Conto principale',
            'predefinito' => '1',
            'attivo' => '0',
        ]));
        $this->assertFalse($ccForm->isValid());
        $this->assertNotEmpty($ccForm->getFilterErrors()->predefinitoCustomMessage);

        // DocumentoIdentitaForm
        $doc = new DocumentoIdentita($this->dataMapper);
        $doc->soggetto = $pf;
        $docForm = new DocumentoIdentitaForm($doc, $this->dataMapper);
        $docForm->handleRequest($this->creaRequest([
            'tipoDocumento' => TipoDocumento::cartaIdentita->value,
            'numero' => 'ca 12345 aa',
            'dataRilascio' => '2024-05-01',
            'dataScadenza' => '2020-05-01',
        ]));
        $this->assertFalse($docForm->isValid());
        $this->assertNotEmpty($docForm->getFilterErrors()->dataScadenzaCustomMessage);

        // RecapitoForm
        $rec = new Recapito($this->dataMapper);
        $rec->soggetto = $pf;
        $recForm = new RecapitoForm($rec, $this->dataMapper);
        $recForm->handleRequest($this->creaRequest([
            'tipoRecapito' => TipoRecapito::email->value,
            'valore' => 'non-una-email',
            'principale' => '1',
        ]));
        $this->assertFalse($recForm->isValid());
        $this->assertNotEmpty($recForm->getFilterErrors()->valoreCustomMessage);
    }
}
