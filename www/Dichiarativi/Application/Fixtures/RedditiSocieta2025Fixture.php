<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\OpzioneTassazione;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use Anagrafica\Application\Fixtures\ContoCorrenteAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\ContrattoLeasing;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CodiceTributoModel;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\PerditaPregressa;
use Dichiarativi\Application\Enumerations\CanoneRai;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Irap\PropostaIrap;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaConsolidato;
use Dichiarativi\Application\Services\Redditi\PropostaRedditi;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiSocieta;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Dichiarazioni demo 2026 (anno 2025) delle società di capitali, dalle
 * loro contabilità:
 * - Alfa Consulting S.r.l.: Redditi SC con l'IRES (quadri RF, RN, RX) e
 *   IRAP (quadro IC), con i versamenti in Deleghe;
 * - gruppo Verde: consolidato nazionale 2024-2026 con Verde Holding S.p.A.
 *   consolidante e Verde Distribuzione e Verde Servizi consolidate; ogni
 *   società compila il quadro GN, la holding il modello CNM con l'IRES del
 *   gruppo e i versamenti;
 * - Lumen Grafica S.r.l.: trasparenza fiscale delle s.r.l. con soci persone
 *   fisiche (art. 116 Tuir) 2025-2027, soci Laura Bianchi (70 per cento) e
 *   Mario Rossi (30 per cento): quadro TN e IRAP; la quota di Rossi va nel
 *   rigo RH5 della sua dichiarazione Redditi PF, riproposta con quella del
 *   bar di cui è socio (quadro RN di Redditi SP).
 */
class RedditiSocieta2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(ContoCorrenteAlfaFixture::class)
                ->addDependency(SoggettoBianchiFixture::class)
                ->addDependency(OperazioniAlfa2025Fixture::class)
                ->addDependency(LiquidazioniAlfa2025Fixture::class)
                ->addDependency(DichiarazioneIvaGruppoVerde2025Fixture::class)
                ->addDependency(DichiarazioneIvaCassa2025Fixture::class)
                ->addDependency(RedditiBarCentrale2025Fixture::class)
                ->addDependency(StudioFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $versamenti = new VersamentiRedditi($this->dataMapper);
        $societa = new PropostaRedditiSocieta($this->dataMapper);

        // Alfa Consulting: IRES e IRAP
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        // perdite residue a fine 2024 (dichiarazione 2025 fuori dalla suite): riportate nel rigo RN4 e nel prospetto RS44
        $this->perdite($alfa, 2024, TipoPerdita::impresa, 3000, 0, 'Prospetto RS44 del modello Redditi SC 2025');
        $this->creditoArtBonus($alfa);
        // costi indeducibili ai fini IRAP (variazione IRAP dei conti del piano): compenso dell'amministratore (IC43) e IMU dell'ufficio (IC46)
        $this->registraCosto($alfa, '22.01.0009', "Compenso dell'amministratore 2025", 6000.0);
        $this->registraCosto($alfa, '27.01.0006', "IMU 2025 dell'ufficio", 800.0);
        // autovettura in leasing dal 1° luglio 2024 (quattro anni): quota interessi nel rigo IC44, costo della concedente nel prospetto di comodo
        $leasing = new ContrattoLeasing($this->dataMapper);
        $leasing->studio = $alfa->studio;
        $leasing->soggetto = $alfa;
        $leasing->descrizione = 'Autovettura aziendale';
        $leasing->concedente = 'Leasing Lombarda S.p.A.';
        $leasing->numeroContratto = 'LL-2024-0456';
        $leasing->dataInizio = new SismaDate('2024-07-01');
        $leasing->dataFine = new SismaDate('2028-06-30');
        $leasing->costoConcedente = 30000.0;
        $leasing->totaleCanoni = 33000.0;
        $leasing->prezzoRiscatto = 300.0;
        $this->dataMapper->save($leasing);
        $redditiAlfa = $this->crea($alfa, ModelloDichiarativo::redditiSc, true);
        $societa->proponi($redditiAlfa);
        $versamenti->registraVersamenti($redditiAlfa, new QuadriDichiarazione($redditiAlfa, $this->dataMapper));
        $this->irap($alfa);

        // gruppo Verde: consolidato nazionale
        $holding = RedditiBarCentrale2025Fixture::cerca($this->dataMapper, $studio, 'Verde Holding');
        foreach ([$holding, RedditiBarCentrale2025Fixture::cerca($this->dataMapper, $studio, 'Verde Distribuzione'), RedditiBarCentrale2025Fixture::cerca($this->dataMapper, $studio, 'Verde Servizi')] as $membro) {
            $this->opzione($membro, TipoOpzioneTassazione::consolidatoNazionale, 2024, $holding);
            // partecipanti al gruppo IVA Verde: causa di esclusione ISA 14, che richiede comunque il modello (qui non preparato)
            $redditiMembro = $this->crea($membro, ModelloDichiarativo::redditiSc, true);
            $causa = new QuadriDichiarazione($redditiMembro, $this->dataMapper);
            $causa->imposta('RF001002', 14);
            $causa->salva();
            $societa->proponi($redditiMembro);
            $this->irap($membro);
        }
        // perdite del consolidato residue a fine 2024 (primo anno del consolidato, modello CNM 2025 fuori dalla suite)
        $this->perdite($holding, 2024, TipoPerdita::consolidato, 10000, 0, 'Rigo CS1 del modello CNM 2025');
        $consolidato = $this->crea($holding, ModelloDichiarativo::consolidato);
        (new PropostaConsolidato($this->dataMapper))->proponi($consolidato);
        $versamenti->registraVersamenti($consolidato, new QuadriDichiarazione($consolidato, $this->dataMapper));

        // Lumen Grafica: trasparenza (art. 116) con soci persone fisiche
        $lumen = RedditiBarCentrale2025Fixture::cerca($this->dataMapper, $studio, 'Lumen Grafica');
        $demo = new AziendaDemo($this->dataMapper);
        $demo->legame($lumen, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::socio, '2018-03-01', 70.0);
        $rossi = RedditiBarCentrale2025Fixture::cerca($this->dataMapper, $studio, 'ROSSI MARIO');
        $demo->legame($lumen, $rossi, RuoloLegame::socio, '2018-03-01', 30.0);
        $this->opzione($lumen, TipoOpzioneTassazione::trasparenzaRistretta, 2025, null);
        $societa->proponi($this->crea($lumen, ModelloDichiarativo::redditiSc, true));
        $this->irap($lumen);

        // Mario Rossi: quadro RH dalle dichiarazioni del bar (RN) e di Lumen Grafica (TN), versamenti di nuovo
        $redditiRossi = (new DichiarazioneModel($this->dataMapper))->getPerModello($rossi, ModelloDichiarativo::redditiPf, 2025);
        $quadriRossi = new QuadriDichiarazione($redditiRossi, $this->dataMapper);
        (new PropostaRedditi($this->dataMapper))->proponi($redditiRossi, $quadriRossi);
        $versamenti->registraVersamenti($redditiRossi, new QuadriDichiarazione($redditiRossi, $this->dataMapper));
        $this->addEntity($consolidato);
    }

    /**
     * Dichiarazione demo; per Redditi SC senza apparecchi radiotelevisivi
     * (casella "Canone RAI" 3) e con l'omessa presentazione del modello ISA
     * (i modelli ISA delle società non sono preparati nei dati demo).
     */
    private function crea(Soggetto $soggetto, ModelloDichiarativo $modello, bool $redditiSc = false): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = $modello;
        $dichiarazione->anno = 2025;
        if ($redditiSc) {
            $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
            $dichiarazione->omessoIsa = true;
        }
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }

    private function irap(Soggetto $soggetto): void
    {
        $irap = $this->crea($soggetto, ModelloDichiarativo::irap);
        (new PropostaIrap($this->dataMapper))->proponi($irap);
        (new VersamentiRedditi($this->dataMapper))->registraVersamenti($irap, new QuadriDichiarazione($irap, $this->dataMapper));
    }

    /**
     * Credito d'imposta Art-Bonus (codice credito A3, codice tributo 6842)
     * maturato nel 2024 per 3.000 euro, in archivio in Deleghe, compensato
     * per 1.000 euro con la delega di giugno 2025 delle ritenute: nel quadro
     * RU residuo iniziale 3.000, utilizzo 1.000, residuo 2.000.
     */
    private function creditoArtBonus(Soggetto $alfa): void
    {
        $codici = new CodiceTributoModel($this->dataMapper);
        $credito = new Credito($this->dataMapper);
        $credito->studio = $alfa->studio;
        $credito->soggetto = $alfa;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = $codici->getByCodice(SezioneCodiceTributo::erario, '6842');
        $credito->annoRiferimento = 2024;
        $credito->importo = 3000.00;
        $credito->utilizzabileDal = new SismaDate('2025-01-01');
        $credito->descrizione = 'Credito Art-Bonus (erogazione liberale del 2024)';
        $this->dataMapper->save($credito);
        $delega = new Delega($this->dataMapper);
        $delega->studio = $alfa->studio;
        $delega->soggetto = $alfa;
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteAlfaFixture::class);
        $delega->scadenza = new SismaDate('2025-06-16');
        $delega->descrizione = 'Ritenute di maggio 2025 con il credito Art-Bonus';
        $delega->stato = StatoDelega::quietanzata;
        $delega->dataQuietanza = new SismaDate('2025-06-16');
        $this->dataMapper->save($delega);
        foreach ([['1040', '0005', 1500.00, 0.0, null], ['6842', null, 0.0, 1000.00, $credito]] as $ordine => [$tributo, $rateazione, $debito, $importoCredito, $collegato]) {
            $riga = new Versamento($this->dataMapper);
            $riga->delega = $delega;
            $riga->studio = $alfa->studio;
            $riga->soggetto = $alfa;
            $riga->scadenza = $delega->scadenza;
            $riga->sezione = SezioneF24::erario;
            $riga->ordine = $ordine + 1;
            $riga->codiceTributo = $codici->getByCodice(SezioneCodiceTributo::erario, $tributo);
            $riga->rateazione = $rateazione;
            $riga->annoRiferimento = $tributo === '6842' ? 2024 : 2025;
            $riga->importoDebito = $debito;
            $riga->importoCredito = $importoCredito;
            $riga->credito = $collegato;
            $this->dataMapper->save($riga);
        }
    }

    /**
     * Costo pagato dalla banca nell'esercizio 2025 (registrazione senza IVA).
     */
    private function registraCosto(Soggetto $azienda, string $codiceConto, string $descrizione, float $importo): void
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($azienda);
        $conti = new ContoModel($this->dataMapper);
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $azienda->studio;
        $registrazione->soggetto = $azienda;
        $registrazione->esercizioContabile = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($azienda, 2025);
        $registrazione->causale = CausaleContabile::giroconto;
        $registrazione->dataRegistrazione = new SismaDate('2025-06-30');
        $registrazione->descrizione = $descrizione;
        $righe = [];
        foreach ([[$conti->getPerCodice($piano, $codiceConto), $importo, 0.0], [$conti->getPerFunzione($piano, FunzioneConto::banca), 0.0, $importo]] as [$conto, $dare, $avere]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conto;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, [], $righe);
    }

    private function perdite(Soggetto $soggetto, int $anno, TipoPerdita $tipo, int $limitata, int $piena, string $note): void
    {
        $perdita = new PerditaPregressa($this->dataMapper);
        $perdita->studio = $soggetto->studio;
        $perdita->soggetto = $soggetto;
        $perdita->anno = $anno;
        $perdita->tipo = $tipo;
        $perdita->limitata = $limitata;
        $perdita->piena = $piena;
        $perdita->note = $note;
        $this->dataMapper->save($perdita);
    }

    private function opzione(Soggetto $soggetto, TipoOpzioneTassazione $tipo, int $inizio, ?Soggetto $consolidante): void
    {
        $opzione = new OpzioneTassazione($this->dataMapper);
        $opzione->soggetto = $soggetto;
        $opzione->tipoOpzione = $tipo;
        $opzione->consolidante = $consolidante;
        $opzione->annoInizio = $inizio;
        $opzione->annoFine = $inizio + 2;
        $this->dataMapper->save($opzione);
    }
}
