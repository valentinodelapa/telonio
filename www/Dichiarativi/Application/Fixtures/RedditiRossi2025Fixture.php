<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\CanoneNonPercepito;
use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\PertinenzaLocata;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\VariazioneCanone;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\InterventiCasaRossiFixture;
use Anagrafica\Application\Fixtures\ResidenzaRossiFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Anagrafica\Application\Models\ComuneModel;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Entities\CertificazioneAutonomo;
use Dichiarativi\Application\Entities\CertificazioneUnica;
use Dichiarativi\Application\Entities\CessioneFinanziaria;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreCertificazioneUnica;
use Dichiarativi\Application\Enumerations\CinquePerMille;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero as Codice;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\OttoPerMille;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditi;
use Dichiarativi\Application\Services\Redditi\PuntiCertificazioneUnica as Punti;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Imu\Application\Fixtures\ImmobileMassimenoRossiFixture;
use Imu\Application\Fixtures\PossessoMassimenoRossiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Modello Redditi PF 2026 demo di Mario Rossi (anno 2025), in bozza:
 * - lavoratore dipendente con la Certificazione Unica del datore di lavoro
 *   (quadro RC, ritenute, addizionali trattenute, ulteriore detrazione);
 * - abitazione principale a Roma e casa di Massimeno locata a canone
 *   libero con la cedolare secca al 21 per cento (contratto con il box
 *   pertinenza locato insieme e l'aggiornamento ISTAT dal 1° luglio):
 *   quadri RB e LC;
 * - prospetto dei familiari proposto dai legami familiari: la coniuge
 *   Laura senza redditi (a carico), il figlio Marco di 22 anni studente
 *   (a carico, detrazione per i 12 mesi da 21 anni, al 100 per cento perché
 *   la coniuge è a carico) e la figlia Giulia di 15 anni (nel prospetto,
 *   senza detrazione: assegno unico);
 * - rate dei bonus edilizi dall'archivio degli interventi della casa di
 *   Roma (ristrutturazione 2021, facciata condominiale 2024, schermature
 *   solari 2023, bagno e mobili 2025): quadro RP, sezioni III e IV, righi
 *   RN14, RN15 e RN16;
 * - attività estere (quadro RW): conto corrente in Svizzera con gli
 *   interessi e la ritenuta svizzera, azioni statunitensi in un deposito
 *   titoli con i dividendi e l'imposta USA, metà di un appartamento a
 *   Nizza dal 10 luglio (IVIE) e cripto-attività; IVAFE, IVIE e imposta
 *   sulle cripto-attività con i versamenti; interessi del conto svizzero
 *   con l'opzione per la tassazione ordinaria (rigo RM31, col. 7) e la
 *   ritenuta svizzera nel quadro CE (credito del rigo RN29);
 * - canoni del 2024 della casa di Massimeno non percepiti, sfratto
 *   convalidato nel 2025: credito del rigo CR8 sulla cedolare secca;
 * - prestazione di lavoro autonomo occasionale (relazione a un convegno)
 *   certificata da un'università con la causale M, con le spese di
 *   viaggio: quadro RL, rigo RL15, e ritenuta nel rigo RN33;
 * - socio al 50 per cento di "Bar Centrale di Rossi e Neri s.n.c." in
 *   contabilità semplificata nella suite (fattura, acquisto, incasso e
 *   pagamento del 2025): quota di reddito nel quadro RH dalla
 *   contabilità della società;
 * - cessioni (quadro RT): azioni statunitensi in plusvalenza (sezione II)
 *   e bitcoin detenuti dal 2023 (sezione V, franchigia di 2.000 euro),
 *   con l'imposta sostitutiva del 26 per cento;
 * - scelte dell'otto per mille (Stato, edilizia scolastica) e del cinque
 *   per mille (ricerca sanitaria);
 * - saldi e acconti registrati in Deleghe F24.
 */
class RedditiRossi2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class)
                ->addDependency(ResidenzaRossiFixture::class)
                ->addDependency(ImmobileMassimenoRossiFixture::class)
                ->addDependency(PossessoMassimenoRossiFixture::class)
                ->addDependency(InterventiCasaRossiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $rossi = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $casa = $this->getEntityByFixtureName(ImmobileMassimenoRossiFixture::class);
        $box = $this->creaBox($rossi, $casa);

        $contratto = new ContrattoLocazione($this->dataMapper);
        $contratto->immobile = $casa;
        $contratto->tipo = TipoContrattoLocazione::canoneLibero;
        $contratto->conduttore = 'BIANCHI LUCA';
        $contratto->dataInizio = new SismaDate('2023-09-01');
        $contratto->canoneAnnuo = 6000.0;
        $contratto->cedolareSecca = true;
        $contratto->codiceIdentificativo = 'TNT23T001234000AB';
        $this->dataMapper->save($contratto);
        $pertinenza = new PertinenzaLocata($this->dataMapper);
        $pertinenza->contrattoLocazione = $contratto;
        $pertinenza->immobile = $box;
        $this->dataMapper->save($pertinenza);
        $variazione = new VariazioneCanone($this->dataMapper);
        $variazione->contrattoLocazione = $contratto;
        $variazione->validoDal = new SismaDate('2025-07-01');
        $variazione->canoneAnnuo = 6120.0;
        $variazione->note = 'Aggiornamento ISTAT';
        $this->dataMapper->save($variazione);
        // canoni del 2024 non pagati dal conduttore, sfratto convalidato nel 2025: credito del rigo CR8 sulla cedolare secca
        $morosita = new CanoneNonPercepito($this->dataMapper);
        $morosita->contrattoLocazione = $contratto;
        $morosita->anno = 2024;
        $morosita->importo = 1500.0;
        $morosita->dataConvalida = new SismaDate('2025-10-15');
        $morosita->note = 'Mensilità da ottobre a dicembre 2024 (rientrate in parte a gennaio 2025)';
        $this->dataMapper->save($morosita);

        $certificazione = new CertificazioneUnica($this->dataMapper);
        $certificazione->studio = $rossi->studio;
        $certificazione->soggetto = $rossi;
        $certificazione->annoImposta = 2025;
        $certificazione->codiceFiscaleSostituto = '01234560785';
        $certificazione->denominazioneSostituto = 'ALFA CONSULTING S.R.L.';
        $this->dataMapper->save($certificazione);
        foreach ([Punti::INDETERMINATO => 32000.0, Punti::GIORNI_LAVORO => 365, Punti::RITENUTE_IRPEF => 6150.0, Punti::ADDIZIONALE_REGIONALE => 520.0,
            Punti::ACCONTO_COMUNALE => 80.0, Punti::SALDO_COMUNALE => 190.0, Punti::ACCONTO_COMUNALE_SUCCESSIVO => 85.0, Punti::CODICE_TRATTAMENTO_INTEGRATIVO => 2,
            Punti::TIPOLOGIA_RIDUZIONE => 1, Punti::REDDITI_ART_49 => 32000.0, Punti::GIORNI_RIDUZIONE => 365, Punti::CODICE_SOMMA_ESENTE => 2] as $punto => $importo) {
            $valore = new ValoreCertificazioneUnica($this->dataMapper);
            $valore->certificazioneUnica = $certificazione;
            $valore->punto = $punto;
            $valore->valore = (float) $importo;
            $this->dataMapper->save($valore);
        }

        // famiglia: coniuge e figli
        $demo = new AziendaDemo($this->dataMapper);
        $laura = $demo->persona($rossi->studio, 'C071', 'RSSLRA82A41H501Z', 'Rossi', 'Laura', Sesso::femmina, '1982-01-01', 'H501', 'Coniuge di Mario Rossi');
        $marco = $demo->persona($rossi->studio, 'C072', 'RSSMRC03C15H501Z', 'Rossi', 'Marco', Sesso::maschio, '2003-03-15', 'H501', 'Figlio di Mario Rossi, studente');
        $giulia = $demo->persona($rossi->studio, 'C073', 'RSSGLI10P60H501I', 'Rossi', 'Giulia', Sesso::femmina, '2010-09-20', 'H501', 'Figlia di Mario Rossi');
        $demo->legame($rossi, $laura, RuoloLegame::coniuge, '2002-06-15');
        $demo->legame($rossi, $marco, RuoloLegame::figlio, '2003-03-15');
        $demo->legame($rossi, $giulia, RuoloLegame::figlio, '2010-09-20');

        // attività estere del 2025
        foreach ([
            [Codice::contiCorrenti, '071', 'Conto corrente UBS Lugano', 'UBS Switzerland AG', 100.0, 12000.0, 12000.0, ['valoreMassimo' => 20000.0, 'tipoProventi' => 'I', 'proventi' => 150.0, 'impostaEstera' => 52.5, 'tassazioneOrdinaria' => true]],
            [Codice::depositoTitoli, '069', 'Azioni statunitensi in deposito', 'Interactive Brokers LLC', 100.0, 36000.0, 40000.0, ['tipoProventi' => 'D', 'proventi' => 800.0, 'impostaEstera' => 120.0]],
            [Codice::immobili, '029', 'Appartamento a Nizza', null, 50.0, 200000.0, 200000.0, ['criterioValore' => 5, 'dataInizio' => new SismaDate('2025-07-10'), 'codiceQuadri' => 5]],
            [Codice::criptoAttivita, null, 'Bitcoin su portafoglio digitale', null, 100.0, 2500.0, 3000.0, []],
        ] as [$codice, $stato, $descrizione, $intermediario, $quota, $iniziale, $finale, $altri]) {
            $attivita = new AttivitaEstera($this->dataMapper);
            $attivita->studio = $rossi->studio;
            $attivita->soggetto = $rossi;
            $attivita->annoImposta = 2025;
            $attivita->codiceInvestimento = $codice;
            $attivita->codiceStatoEstero = $stato;
            $attivita->descrizione = $descrizione;
            $attivita->intermediario = $intermediario;
            $attivita->quota = $quota;
            $attivita->valoreIniziale = $iniziale;
            $attivita->valoreFinale = $finale;
            foreach ($altri as $campo => $valore) {
                $attivita->$campo = $valore;
            }
            $this->dataMapper->save($attivita);
        }

        // Certificazione Unica del lavoro autonomo occasionale
        $occasionale = new CertificazioneAutonomo($this->dataMapper);
        $occasionale->studio = $rossi->studio;
        $occasionale->soggetto = $rossi;
        $occasionale->annoImposta = 2025;
        $occasionale->codiceFiscaleSostituto = '80209930587';
        $occasionale->denominazioneSostituto = 'UNIVERSITA DEGLI STUDI DI ROMA LA SAPIENZA';
        $occasionale->causale = 'M';
        $occasionale->ammontareLordo = 2000.0;
        $occasionale->ritenuteAcconto = 400.0;
        $occasionale->speseInerenti = 150.0;
        $this->dataMapper->save($occasionale);

        // partecipazione in una società di persone in contabilità semplificata
        $bar = $demo->creaSocieta($rossi->studio, 'C075', '09990012008', 'Bar Centrale di Rossi e Neri s.n.c.', NaturaGiuridica::societaNomeCollettivo, '56.30.01',
                PeriodicitaIva::trimestrale, 'H501', 'Via Nazionale 10', '00184');
        $bar->tipoSoggetto = TipoSoggetto::societaPersone;
        $this->dataMapper->save($bar);
        $demo->variaDatiFiscali($bar, '2025-01-01', ['regimeFiscale' => RegimeFiscale::semplificato]);
        $demo->legame($bar, $rossi, RuoloLegame::socio, '2018-03-01', 50.0);
        $esercizioBar = $demo->apriContabilita($bar, 2025);
        $clienteBar = $demo->controparte($bar, 'Catering Eventi Roma S.r.l.', '09990012016', true);
        $fornitoreBar = $demo->controparte($bar, 'Torrefazione Laziale S.r.l.', '09990012024', false);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBar, $clienteBar, CausaleContabile::fatturaVendita, '2025-03-15', '1', ['10' => 30000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBar, $clienteBar, CausaleContabile::incasso, '2025-04-15', null, [], ['totale' => 33000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBar, $fornitoreBar, CausaleContabile::fatturaAcquisto, '2025-05-10', 'TL/88', ['22' => 12000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBar, $fornitoreBar, CausaleContabile::pagamento, '2025-06-10', null, [], ['totale' => 14640.0]);

        // cessioni in regime dichiarativo
        foreach ([[CessioneFinanziaria::TITOLI, '2025-05-20', 'Vendita di 50 azioni Apple Inc. (Interactive Brokers)', 12000.0, 9000.0, false],
            [CessioneFinanziaria::CRIPTO, '2025-11-03', 'Vendita di 0,04 bitcoin acquistati nel 2023', 3500.0, 1000.0, true]] as [$tipo, $data, $descrizione, $corrispettivo, $costo, $ante]) {
            $cessione = new CessioneFinanziaria($this->dataMapper);
            $cessione->studio = $rossi->studio;
            $cessione->soggetto = $rossi;
            $cessione->annoImposta = 2025;
            $cessione->tipo = $tipo;
            $cessione->dataCessione = new SismaDate($data);
            $cessione->descrizione = $descrizione;
            $cessione->corrispettivo = $corrispettivo;
            $cessione->costo = $costo;
            $cessione->anteriore2025 = $ante;
            $this->dataMapper->save($cessione);
        }

        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $rossi->studio;
        $dichiarazione->soggetto = $rossi;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        // scelte: otto per mille allo Stato per l'edilizia scolastica, cinque per mille alla ricerca sanitaria, due per mille non espresso
        $dichiarazione->ottoPerMille = OttoPerMille::stato;
        $dichiarazione->finalitaStato = 3;
        $dichiarazione->cinquePerMille = CinquePerMille::ricercaSanitaria;
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaRedditi($this->dataMapper))->proponi($dichiarazione, $quadri);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $versamenti = new VersamentiRedditi($this->dataMapper);
        $versamenti->registraVersamenti($dichiarazione, $quadri);
        $versamenti->registraCrediti($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }

    /**
     * Box di pertinenza della casa di Massimeno (C/6), locato con la casa.
     */
    private function creaBox($rossi, Immobile $casa): Immobile
    {
        $box = new Immobile($this->dataMapper);
        $box->studio = $rossi->studio;
        $box->tipoCatasto = TipoCatasto::fabbricati;
        $box->comune = (new ComuneModel($this->dataMapper))->getByCodice('F045');
        $box->foglio = $casa->foglio;
        $box->particella = $casa->particella;
        $box->subalterno = '9';
        $box->indirizzo = $casa->indirizzo;
        $box->descrizione = 'Box auto';
        $this->dataMapper->save($box);
        $dati = new DatiCatastali($this->dataMapper);
        $dati->immobile = $box;
        $dati->validoDal = new SismaDate('2005-01-01');
        $dati->causale = CausaleClassamento::costituzione;
        $dati->categoria = CategoriaCatastale::c6;
        $dati->classe = '2';
        $dati->consistenza = 15;
        $dati->rendita = 60.0;
        $this->dataMapper->save($dati);
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $box;
        $possesso->soggetto = $rossi;
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2005-01-01');
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $this->dataMapper->save($possesso);
        return $box;
    }
}
