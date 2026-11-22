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
use Contabilita\Application\Entities\Attivita;
use Contabilita\Application\Enumerations\NaturaAttivita;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
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
 * contabilità semplificata con due attività (Attivita): commerciale (corsi
 * di fotografia) e istituzionale; IVA trimestrale con le liquidazioni e il
 * modello IVA 2026, Redditi ENC 2026 e IRAP 2026 proposti.
 *
 * Nel 2025:
 * - attività commerciale: corsi a pagamento (due fatture da 15.000 euro più
 *   IVA, incassate) con un docente occasionale (causale CU "M", 8.000 euro
 *   con la ritenuta del 20 per cento) e il materiale per i corsi (1.000 euro
 *   più IVA, registro degli acquisti dell'attività);
 * - attività istituzionale: quote associative (20.000 euro), la segretaria
 *   dipendente (18.000 euro) e il relatore occasionale di una conferenza
 *   (2.000 euro con la ritenuta);
 * - energia elettrica della sede comune alle due attività (5.000 euro più
 *   IVA, sezionale degli acquisti comuni): 60 per cento all'attività
 *   commerciale per l'IVA (detraibili 660 euro, art. 19-ter D.P.R.
 *   633/1972) e per i redditi; i 440 euro di IVA indetraibile sono costo.
 *
 * Redditi ENC: quadro RG 30.000 - 1.000 - 3.264 - 8.000 = 17.736, IRES
 * 4.257. IRAP, quadro IE: sezione I 18.000 + 2.000, sezione II 17.736,
 * ulteriore deduzione di 8.000 euro, valore netto 29.736. IVA dovuta per
 * l'anno: 6.600 - 880 = 5.720.
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
        $esercizio = $demo->apriContabilita($ente, 2025);
        $commerciale = $demo->attivita($ente, 1, 'Corsi di fotografia', '85.52.09', null, 2015);
        $istituzionale = $this->attivitaIstituzionale($ente);
        $demo->assegnaRegistro($ente, TipoRegistroIva::vendite, '', $commerciale);
        $demo->assegnaRegistro($ente, TipoRegistroIva::acquisti, '', $commerciale);
        $comuni = $demo->sezionale($ente, TipoRegistroIva::acquisti, 'CO', 'Acquisti comuni all\'attività commerciale e istituzionale', null);
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($ente);
        $conti = $this->attivitaConti($piano, $commerciale, $istituzionale);
        $banca = (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::banca);
        $ritenute = (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::ritenuteDaVersare);
        // attività commerciale: fatture dei corsi incassate, materiale per i corsi pagato
        $partecipanti = $demo->controparte($ente, 'Partecipanti ai corsi di fotografia', null, true, '20.01.0002');
        foreach (['2025-03-31' => '1', '2025-10-31' => '2'] as $data => $numero) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $partecipanti, CausaleContabile::fatturaVendita, $data, $numero, ['22' => 15000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $partecipanti, CausaleContabile::incasso, $data, null, [], ['totale' => 18300.0]);
        }
        $fornitore = $demo->controparte($ente, 'Foto Forniture S.r.l.', '09990030372', false, '21.01.0003');
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $fornitore, CausaleContabile::fatturaAcquisto, '2025-04-10', 'FF/112', ['22' => 1000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $fornitore, CausaleContabile::pagamento, '2025-04-30', null, [], ['totale' => 1220.0]);
        // energia elettrica della sede: acquisto comune, 60 per cento all'attività commerciale (criterio oggettivo: superficie dell'aula dei corsi)
        $energia = $demo->controparte($ente, 'Energia Emilia S.p.A.', '09990040371', false, '22.01.0002');
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $energia, CausaleContabile::fatturaAcquisto, '2025-06-30', 'EE/2025/6', ['22' => 5000.0],
                ['registro' => $comuni, 'ripartizione' => [1 => 60.0, 2 => 40.0]]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $energia, CausaleContabile::pagamento, '2025-07-15', null, [], ['totale' => 6100.0]);
        // attività istituzionale (conti con l'attività istituzionale) e docente dei corsi (conto commerciale)
        $this->registra($ente, '2025-02-28', 'Quote associative 2025', null, [[$banca, 20000.0, 0.0], [$conti['quote'], 0.0, 20000.0]]);
        $this->registra($ente, '2025-12-31', 'Retribuzioni della segretaria', null, [[$conti['salari'], 18000.0, 0.0], [$banca, 0.0, 18000.0]]);
        $relatore = $this->percipiente($ente, 'GALLI', 'ROBERTO', 'M', '1968-11-04', 'F205', $conti['occasionaliIstituzionali']);
        $this->registra($ente, '2025-05-10', 'Relazione alla conferenza annuale', $relatore, [[$conti['occasionaliIstituzionali'], 2000.0, 0.0], [$banca, 0.0, 1600.0], [$ritenute, 0.0, 400.0]]);
        $docente = $this->percipiente($ente, 'VITALI', 'CHIARA', 'F', '1990-03-22', 'A944', $conti['occasionaliCommerciali']);
        $this->registra($ente, '2025-11-15', 'Docenza dei corsi di fotografia', $docente, [[$conti['occasionaliCommerciali'], 8000.0, 0.0], [$banca, 0.0, 6400.0], [$ritenute, 0.0, 1600.0]]);
        // liquidazioni IVA trimestrali e dichiarazione IVA 2026
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($periodo = 1; $periodo <= PeriodiIva::getNumeroPeriodi(PeriodicitaIva::trimestrale); $periodo++) {
            $elaborazione->elabora($ente, 2025, $periodo, 0.0, false);
        }
        $iva = $this->crea($ente, ModelloDichiarativo::iva);
        (new PropostaIva($this->dataMapper))->proponi($iva);
        (new VersamentiIva($this->dataMapper))->registraSaldo($iva, new QuadriDichiarazione($iva, $this->dataMapper));
        $versamenti = new VersamentiRedditi($this->dataMapper);
        $redditi = $this->crea($ente, ModelloDichiarativo::redditiEnc);
        (new PropostaRedditiEnc($this->dataMapper))->proponi($redditi);
        $versamenti->registraVersamenti($redditi, new QuadriDichiarazione($redditi, $this->dataMapper));
        $irap = $this->crea($ente, ModelloDichiarativo::irap);
        (new PropostaIrap($this->dataMapper))->proponi($irap);
        $versamenti->registraVersamenti($irap, new QuadriDichiarazione($irap, $this->dataMapper));
        $this->addEntity($redditi);
    }

    private function attivitaIstituzionale(Soggetto $ente): Attivita
    {
        $attivita = new Attivita($this->dataMapper);
        $attivita->studio = $ente->studio;
        $attivita->soggetto = $ente;
        $attivita->numero = 2;
        $attivita->descrizione = 'Attività culturale istituzionale';
        $attivita->natura = NaturaAttivita::istituzionale;
        $attivita->dalAnno = 2015;
        $this->dataMapper->save($attivita);
        return $attivita;
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
        $dati->periodicitaIva = PeriodicitaIva::trimestrale;
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
     * Attività predefinita dei conti dell'ente: ricavi dei corsi, materiale
     * e compensi dei docenti all'attività commerciale; quote associative e
     * compensi dei relatori (nuovi sottoconti) e salari all'attività
     * istituzionale; energia elettrica senza attività (righe comuni).
     *
     * @return array<string, Conto>
     */
    private function attivitaConti(PianoConti $piano, Attivita $commerciale, Attivita $istituzionale): array
    {
        $model = new ContoModel($this->dataMapper);
        $conti = [
            'corsi' => $model->getPerCodice($piano, '20.01.0002'),
            'materiale' => $model->getPerCodice($piano, '21.01.0003'),
            'occasionaliCommerciali' => $model->getPerCodice($piano, '22.01.0010'),
            'salari' => $model->getPerCodice($piano, '24.01.0001'),
        ];
        foreach (['corsi' => $commerciale, 'materiale' => $commerciale, 'occasionaliCommerciali' => $commerciale, 'salari' => $istituzionale] as $chiave => $attivita) {
            $conti[$chiave]->attivita = $attivita;
            $this->dataMapper->save($conti[$chiave]);
        }
        $conti['quote'] = $this->sottoconto($piano, '20.02', '20.02.0005', 'Quote associative', NaturaConto::ricavo, VoceCee::economicoA5, $istituzionale);
        $conti['occasionaliIstituzionali'] = $this->sottoconto($piano, '22.01', '22.01.0011', 'Compensi occasionali dell\'attività istituzionale', NaturaConto::costo, VoceCee::economicoB7, $istituzionale);
        return $conti;
    }

    private function sottoconto(PianoConti $piano, string $superiore, string $codice, string $descrizione, NaturaConto $natura, VoceCee $voce, Attivita $attivita): Conto
    {
        $conto = new Conto($this->dataMapper);
        $conto->pianoConti = $piano;
        $conto->parentConto = (new ContoModel($this->dataMapper))->getPerCodice($piano, $superiore);
        $conto->codice = $codice;
        $conto->descrizione = $descrizione;
        $conto->livello = LivelloConto::sottoconto;
        $conto->natura = $natura;
        $conto->voceCee = $voce;
        $conto->attivita = $attivita;
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
        if (in_array($modello, [ModelloDichiarativo::redditiEnc, ModelloDichiarativo::iva], true)) {
            $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
            $dichiarazione->omessoIsa = true;
        }
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }
}
