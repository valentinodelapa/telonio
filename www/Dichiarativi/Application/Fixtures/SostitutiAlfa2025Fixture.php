<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Fixtures\ContoCorrenteAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\CausaleCu;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use Contabilita\Application\Services\RitenuteOperate;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Sostituti\Proposta770;
use Dichiarativi\Application\Services\Sostituti\PropostaCu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Alfa Consulting S.r.l. sostituto d'imposta nel 2025: compensi senza IVA
 * pagati al netto della ritenuta d'acconto (le liquidazioni IVA demo non
 * cambiano), Certificazione Unica 2026 e modello 770/2026 proposti:
 * - prestazione di lavoro autonomo occasionale (causale M) di Paolo
 *   Conti, 7.500 euro pagati il 20 maggio con la ritenuta del 20 per cento
 *   (1.500), versata con la delega di giugno già in archivio (con il
 *   credito Art-Bonus);
 * - diritti d'autore (causale B) di Giulia Ferrante, 2.000 euro pagati il
 *   15 ottobre con la ritenuta del 20 per cento sul 75 per cento (300),
 *   versata con la delega di novembre quietanzata;
 * - diritti d'autore (causale B) di Luca Marini, con meno di 35 anni:
 *   1.000 euro pagati il 15 ottobre con la ritenuta del 20 per cento sul
 *   60 per cento (120), nella stessa delega di novembre.
 *
 * Eseguita dopo le dichiarazioni Redditi e IRAP demo delle società, che
 * restano come proposte prima di questi costi (riproponendole li
 * comprendono).
 */
class SostitutiAlfa2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(ContoCorrenteAlfaFixture::class)
                ->addDependency(OperazioniAlfa2025Fixture::class)
                ->addDependency(RedditiSocieta2025Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $conti = $this->percipiente($alfa, 'CONTI', 'PAOLO', 'M', '1979-04-12', 'H501', 'Via Nomentana 210', '00162', 'Roma', 'RM', CausaleCu::occasionale);
        $ferrante = $this->percipiente($alfa, 'FERRANTE', 'GIULIA', 'F', '1985-09-03', 'F205', 'Via Solari 18', '20144', 'Milano', 'MI', CausaleCu::dirittiAutore);
        $this->paga($alfa, $conti, '2025-05-20', 'Prestazione occasionale di consulenza (ricevuta n. 1/2025)', 7500.0, 1500.0);
        $this->paga($alfa, $ferrante, '2025-10-15', 'Diritti d\'autore per il manuale tecnico', 2000.0, 300.0);
        $marini = $this->percipiente($alfa, 'MARINI', 'LUCA', 'M', '2001-02-10', 'A944', 'Via Zamboni 33', '40126', 'Bologna', 'BO', CausaleCu::dirittiAutore);
        $this->paga($alfa, $marini, '2025-10-15', 'Diritti d\'autore per le illustrazioni del catalogo', 1000.0, 120.0);
        // ritenute di ottobre: versamento generato dalla Contabilità, nella delega del 17 novembre (il 16 è domenica)
        (new RitenuteOperate($this->dataMapper))->registra($alfa, 2025, 10);
        $delega = new Delega($this->dataMapper);
        $delega->studio = $alfa->studio;
        $delega->soggetto = $alfa;
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteAlfaFixture::class);
        $delega->scadenza = new SismaDate('2025-11-17');
        $delega->descrizione = 'Ritenute di ottobre 2025';
        $delega->stato = StatoDelega::quietanzata;
        $delega->dataQuietanza = new SismaDate('2025-11-17');
        $this->dataMapper->save($delega);
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($alfa, RitenuteOperate::ORIGINE, RitenuteOperate::getRiferimento(2025, 10)) as $versamento) {
            $versamento->delega = $delega;
            $this->dataMapper->save($versamento);
        }
        $certificazione = $this->crea($alfa, ModelloDichiarativo::certificazioneUnica);
        (new PropostaCu($this->dataMapper))->proponi($certificazione);
        $dichiarazione = $this->crea($alfa, ModelloDichiarativo::modello770);
        (new Proposta770($this->dataMapper))->proponi($dichiarazione);
        $this->addEntity($dichiarazione);
    }

    private function percipiente(Soggetto $alfa, string $cognome, string $nome, string $sesso, string $nascita, string $comuneNascita, string $indirizzo, string $cap, string $comune, string $provincia, CausaleCu $causale): Controparte
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($alfa);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $alfa->studio;
        $controparte->soggetto = $alfa;
        $controparte->denominazione = ucwords(mb_strtolower($cognome . ' ' . $nome));
        $controparte->cognome = $cognome;
        $controparte->nome = $nome;
        $controparte->codiceFiscale = CodiceFiscale::calcola($cognome, $nome, $sesso, new \DateTimeImmutable($nascita), $comuneNascita);
        $controparte->indirizzo = $indirizzo;
        $controparte->cap = $cap;
        $controparte->comune = $comune;
        $controparte->provincia = $provincia;
        $controparte->fornitore = true;
        $controparte->causaleCu = $causale;
        $controparte->contoContropartita = (new ContoModel($this->dataMapper))->getPerCodice($piano, '22.01.0001');
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        return $controparte;
    }

    /**
     * Compenso pagato dalla banca al netto della ritenuta (registrazione
     * senza IVA con il percipiente come controparte).
     */
    private function paga(Soggetto $alfa, Controparte $percipiente, string $data, string $descrizione, float $compenso, float $ritenuta): void
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($alfa);
        $contoModel = new ContoModel($this->dataMapper);
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $alfa->studio;
        $registrazione->soggetto = $alfa;
        $registrazione->esercizioContabile = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($alfa, 2025);
        $registrazione->causale = CausaleContabile::giroconto;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->controparte = $percipiente;
        $registrazione->descrizione = $descrizione;
        $righe = [];
        foreach ([[$percipiente->contoContropartita, $compenso, 0.0], [$contoModel->getPerFunzione($piano, FunzioneConto::banca), 0.0, $compenso - $ritenuta],
            [$contoModel->getPerFunzione($piano, FunzioneConto::ritenuteDaVersare), 0.0, $ritenuta]] as [$conto, $dare, $avere]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conto;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, [], $righe);
    }

    private function crea(Soggetto $alfa, ModelloDichiarativo $modello): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $alfa->studio;
        $dichiarazione->soggetto = $alfa;
        $dichiarazione->modello = $modello;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }
}
