<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditi;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Modello Redditi PF 2026 demo di Sara Gallo (anno 2025), grafica web nel
 * regime forfetario dal 2022, in bozza: quadro LM con i compensi incassati
 * nel 2025 (la fattura di novembre è incassata a gennaio 2026), il
 * coefficiente del 78 per cento del codice ATECO 2007 (74.10.21, prima del
 * passaggio ad ATECO 2025), i contributi della Gestione separata versati
 * con l'F24 di giugno e l'imposta sostitutiva del 5 per cento per i primi
 * cinque anni dell'attività (casella del rigo LM21 barrata).
 */
class RedditiGallo2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $demo = new AziendaDemo($this->dataMapper);
        $gallo = $demo->persona($studio, 'C070', 'GLLSRA88E50A944C', 'Gallo', 'Sara', Sesso::femmina, '1988-05-10', 'A944', 'Grafica web nel regime forfetario');
        $gallo->clienteStudio = true;
        $gallo->partitaIva = '01234980587';
        $this->dataMapper->save($gallo);
        $ateco = new CodiceAtecoModel($this->dataMapper);
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $gallo;
        $dati->validoDal = new SismaDate('2022-03-01');
        $dati->tipoAttivita = TipoAttivita::lavoroAutonomo;
        $dati->partitaIva = '01234980587';
        $dati->codiceAteco = $ateco->getByCodice(ClassificazioneAteco::ateco2022, '74.10.21');
        $dati->descrizioneAttivita = 'Grafica di pagine web';
        $dati->regimeFiscale = RegimeFiscale::forfetario;
        $dati->dataInizioAttivita = new SismaDate('2022-03-01');
        $this->dataMapper->save($dati);
        $demo->variaDatiFiscali($gallo, '2025-04-01', ['codiceAteco' => $ateco->getByCodice(ClassificazioneAteco::ateco2025, '74.12.01')]);
        $residenza = new Indirizzo($this->dataMapper);
        $residenza->soggetto = $gallo;
        $residenza->tipoIndirizzo = TipoIndirizzo::residenza;
        $residenza->validoDal = new SismaDate('2015-09-01');
        $residenza->indirizzo = 'Via Saragozza';
        $residenza->numeroCivico = '12';
        $residenza->cap = '40123';
        $residenza->comune = (new ComuneModel($this->dataMapper))->getByCodice('A944');
        $residenza->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($residenza);

        // compensi fatturati senza IVA (N2.2) e incassi
        $esercizio = $demo->apriContabilita($gallo, 2025);
        $cliente = $demo->controparte($gallo, 'Studio Architetti Associati', '09991230070', true);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, CausaleContabile::fatturaVendita, '2025-02-10', '1', ['N2.2' => 4000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, CausaleContabile::incasso, '2025-03-10', null, [], ['totale' => 4000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, CausaleContabile::fatturaVendita, '2025-06-15', '2', ['N2.2' => 6000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, CausaleContabile::incasso, '2025-07-30', null, [], ['totale' => 6000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, CausaleContabile::fatturaVendita, '2025-11-20', '3', ['N2.2' => 5000.0]);

        // contributi della Gestione separata versati a giugno
        $delega = new Delega($this->dataMapper);
        $delega->studio = $studio;
        $delega->soggetto = $gallo;
        $delega->scadenza = new SismaDate('2025-06-30');
        $delega->descrizione = 'Contributi Gestione separata';
        $delega->stato = StatoDelega::quietanzata;
        $delega->dataQuietanza = new SismaDate('2025-06-30');
        $this->dataMapper->save($delega);
        $versamento = new Versamento($this->dataMapper);
        $versamento->delega = $delega;
        $versamento->studio = $studio;
        $versamento->soggetto = $gallo;
        $versamento->scadenza = $delega->scadenza;
        $versamento->sezione = SezioneF24::inps;
        $versamento->ordine = 1;
        $versamento->codiceSede = '1300';
        $versamento->causale = 'PXX';
        $versamento->posizione = 'GLLSRA88E50A944C';
        $versamento->periodoDa = '01/2024';
        $versamento->periodoA = '12/2024';
        $versamento->importoDebito = 1500.0;
        $this->dataMapper->save($versamento);

        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $studio;
        $dichiarazione->soggetto = $gallo;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        // requisiti del regime e della nuova attività attestati dalla contribuente
        $quadri->imposta('LM021001', true);
        $quadri->imposta('LM021002', true);
        $quadri->imposta('LM021003', true);
        $quadri->salva();
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaRedditi($this->dataMapper))->proponi($dichiarazione, $quadri);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new VersamentiRedditi($this->dataMapper))->registraVersamenti($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
