<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\OpzioneTassazione;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\CanoneRai;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
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
