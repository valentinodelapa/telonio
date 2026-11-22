<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\CausaleCu;
use Contabilita\Application\Enumerations\DestinazioneConto;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\CanoneRai;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Irap\PropostaIrap;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiEnc;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Ente non commerciale demo: Associazione Culturale Orizzonti di Bologna,
 * contabilità semplificata con l'attività commerciale separata per conti
 * (destinazione dei conti) e Redditi ENC 2026 e IRAP 2026 proposti.
 *
 * Nel 2025:
 * - attività commerciale: corsi a pagamento (30.000 euro, esenti IVA) con
 *   un docente occasionale (causale CU "M", 8.000 euro con la ritenuta del
 *   20 per cento);
 * - attività istituzionale: quote associative (20.000 euro), la segretaria
 *   dipendente (18.000 euro) e il relatore occasionale di una conferenza
 *   (2.000 euro con la ritenuta);
 * - energia elettrica della sede (5.000 euro) promiscua: deducibile al 60
 *   per cento (30.000 di ricavi commerciali su 50.000 di ricavi e
 *   proventi, art. 144, c. 4, TUIR).
 *
 * Redditi ENC: quadro RG 30.000 - 8.000 - 3.000 = 19.000, IRES 4.560.
 * IRAP, quadro IE: sezione I 18.000 + 2.000, sezione II 19.000, ulteriore
 * deduzione di 8.000 euro, valore netto 31.000.
 */
class RedditiEnc2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SostitutiAlfa2025Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $demo = new AziendaDemo($this->dataMapper);
        $ente = $this->creaEnte($studio);
        $presidente = $demo->persona($studio, 'C081', CodiceFiscale::calcola('MORETTI', 'ELENA', 'F', new \DateTimeImmutable('1972-06-18'), 'A944'),
                'MORETTI', 'ELENA', Sesso::femmina, '1972-06-18', 'A944', 'Presidente dell\'Associazione Culturale Orizzonti');
        $demo->legame($ente, $presidente, RuoloLegame::legaleRappresentante, '2015-02-01');
        $demo->apriContabilita($ente, 2025);
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($ente);
        $conti = $this->destinazioni($piano);
        $banca = (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::banca);
        $ritenute = (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::ritenuteDaVersare);
        $this->registra($ente, '2025-02-28', 'Quote associative 2025', null, [[$banca, 20000.0, 0.0], [$conti['quote'], 0.0, 20000.0]]);
        $this->registra($ente, '2025-03-31', 'Corsi di fotografia, primo semestre', null, [[$banca, 15000.0, 0.0], [$conti['corsi'], 0.0, 15000.0]]);
        $this->registra($ente, '2025-10-31', 'Corsi di fotografia, secondo semestre', null, [[$banca, 15000.0, 0.0], [$conti['corsi'], 0.0, 15000.0]]);
        $this->registra($ente, '2025-06-30', 'Energia elettrica della sede', null, [[$conti['energia'], 5000.0, 0.0], [$banca, 0.0, 5000.0]]);
        $this->registra($ente, '2025-12-31', 'Retribuzioni della segretaria', null, [[$conti['salari'], 18000.0, 0.0], [$banca, 0.0, 18000.0]]);
        $relatore = $this->percipiente($ente, 'GALLI', 'ROBERTO', 'M', '1968-11-04', 'F205', $conti['occasionaliIstituzionali']);
        $this->registra($ente, '2025-05-10', 'Relazione alla conferenza annuale', $relatore, [[$conti['occasionaliIstituzionali'], 2000.0, 0.0], [$banca, 0.0, 1600.0], [$ritenute, 0.0, 400.0]]);
        $docente = $this->percipiente($ente, 'VITALI', 'CHIARA', 'F', '1990-03-22', 'A944', $conti['occasionaliCommerciali']);
        $this->registra($ente, '2025-11-15', 'Docenza dei corsi di fotografia', $docente, [[$conti['occasionaliCommerciali'], 8000.0, 0.0], [$banca, 0.0, 6400.0], [$ritenute, 0.0, 1600.0]]);
        $versamenti = new VersamentiRedditi($this->dataMapper);
        $redditi = $this->crea($ente, ModelloDichiarativo::redditiEnc);
        (new PropostaRedditiEnc($this->dataMapper))->proponi($redditi);
        $versamenti->registraVersamenti($redditi, new QuadriDichiarazione($redditi, $this->dataMapper));
        $irap = $this->crea($ente, ModelloDichiarativo::irap);
        (new PropostaIrap($this->dataMapper))->proponi($irap);
        $versamenti->registraVersamenti($irap, new QuadriDichiarazione($irap, $this->dataMapper));
        $this->addEntity($redditi);
    }

    private function creaEnte($studio): Soggetto
    {
        $ente = new Soggetto($this->dataMapper);
        $ente->studio = $studio;
        $ente->tipoSoggetto = TipoSoggetto::ente;
        $ente->codice = 'C080';
        $ente->codiceFiscale = '97612345674';
        $ente->partitaIva = '09990020589';
        $ente->nominativo = 'Associazione Culturale Orizzonti';
        $ente->clienteStudio = true;
        $ente->dataCostituzione = new SismaDate('2015-02-01');
        $this->dataMapper->save($ente);
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $ente;
        $dati->validoDal = new SismaDate('2015-02-01');
        $dati->denominazione = $ente->nominativo;
        $dati->naturaGiuridica = NaturaGiuridica::associazione;
        $dati->partitaIva = '09990020589';
        $dati->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '85.52.09');
        $dati->regimeFiscale = RegimeFiscale::semplificato;
        $dati->enteNonCommerciale = true;
        $this->dataMapper->save($dati);
        $sede = new Indirizzo($this->dataMapper);
        $sede->soggetto = $ente;
        $sede->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $sede->validoDal = new SismaDate('2015-02-01');
        $sede->indirizzo = 'Via Mascarella 48';
        $sede->cap = '40126';
        $sede->comune = (new ComuneModel($this->dataMapper))->getByCodice('A944');
        $sede->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($sede);
        return $ente;
    }

    /**
     * Destinazione dei conti dell'ente: ricavi dei corsi e compensi dei
     * docenti commerciali, quote associative (nuovo sottoconto), salari e
     * compensi dei relatori (nuovo sottoconto) istituzionali, energia
     * elettrica promiscua.
     *
     * @return array<string, Conto>
     */
    private function destinazioni(PianoConti $piano): array
    {
        $model = new ContoModel($this->dataMapper);
        $conti = [
            'corsi' => $model->getPerCodice($piano, '20.01.0002'),
            'occasionaliCommerciali' => $model->getPerCodice($piano, '22.01.0010'),
            'energia' => $model->getPerCodice($piano, '22.01.0002'),
            'salari' => $model->getPerCodice($piano, '24.01.0001'),
        ];
        foreach (['corsi' => DestinazioneConto::commerciale, 'occasionaliCommerciali' => DestinazioneConto::commerciale, 'energia' => DestinazioneConto::promiscuo, 'salari' => DestinazioneConto::istituzionale] as $chiave => $destinazione) {
            $conti[$chiave]->destinazione = $destinazione;
            $this->dataMapper->save($conti[$chiave]);
        }
        $conti['quote'] = $this->sottoconto($piano, '20.02', '20.02.0005', 'Quote associative', NaturaConto::ricavo, VoceCee::economicoA5);
        $conti['occasionaliIstituzionali'] = $this->sottoconto($piano, '22.01', '22.01.0011', 'Compensi occasionali dell\'attività istituzionale', NaturaConto::costo, VoceCee::economicoB7);
        return $conti;
    }

    private function sottoconto(PianoConti $piano, string $superiore, string $codice, string $descrizione, NaturaConto $natura, VoceCee $voce): Conto
    {
        $conto = new Conto($this->dataMapper);
        $conto->pianoConti = $piano;
        $conto->parentConto = (new ContoModel($this->dataMapper))->getPerCodice($piano, $superiore);
        $conto->codice = $codice;
        $conto->descrizione = $descrizione;
        $conto->livello = LivelloConto::sottoconto;
        $conto->natura = $natura;
        $conto->voceCee = $voce;
        $conto->destinazione = DestinazioneConto::istituzionale;
        $this->dataMapper->save($conto);
        return $conto;
    }

    private function percipiente(Soggetto $ente, string $cognome, string $nome, string $sesso, string $nascita, string $comune, Conto $contropartita): Controparte
    {
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $ente->studio;
        $controparte->soggetto = $ente;
        $controparte->denominazione = ucwords(mb_strtolower($cognome . ' ' . $nome));
        $controparte->cognome = $cognome;
        $controparte->nome = $nome;
        $controparte->codiceFiscale = CodiceFiscale::calcola($cognome, $nome, $sesso, new \DateTimeImmutable($nascita), $comune);
        $controparte->fornitore = true;
        $controparte->causaleCu = CausaleCu::occasionale;
        $controparte->contoContropartita = $contropartita;
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        return $controparte;
    }

    /**
     * Registrazione senza IVA (giroconto) con le righe [conto, dare, avere].
     *
     * @param array<array{0: Conto, 1: float, 2: float}> $movimenti
     */
    private function registra(Soggetto $ente, string $data, string $descrizione, ?Controparte $controparte, array $movimenti): void
    {
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $ente->studio;
        $registrazione->soggetto = $ente;
        $registrazione->esercizioContabile = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($ente, 2025);
        $registrazione->causale = CausaleContabile::giroconto;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->controparte = $controparte;
        $registrazione->descrizione = $descrizione;
        $righe = [];
        foreach ($movimenti as [$conto, $dare, $avere]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conto;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, [], $righe);
    }

    /**
     * Dichiarazione senza apparecchi radiotelevisivi (casella "Canone RAI"
     * 3) e con l'omessa presentazione del modello ISA, come le società demo.
     */
    private function crea(Soggetto $ente, ModelloDichiarativo $modello): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $ente->studio;
        $dichiarazione->soggetto = $ente;
        $dichiarazione->modello = $modello;
        $dichiarazione->anno = 2025;
        if ($modello === ModelloDichiarativo::redditiEnc) {
            $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
            $dichiarazione->omessoIsa = true;
        }
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }
}
