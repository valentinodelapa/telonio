<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Models\SoggettoModel;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\CanoneRai;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Irap\PropostaIrap;
use Dichiarativi\Application\Services\Isa\CalcoloIsa;
use Dichiarativi\Application\Services\Isa\ModelliIsa;
use Dichiarativi\Application\Services\Isa\MotoreIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiSocieta;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Modello Redditi SP 2026 demo di "Bar Centrale di Rossi e Neri s.n.c."
 * (anno 2025), in contabilità semplificata: quadro RG per cassa dalla
 * contabilità, redditi da imputare (quadro RN) e soci con le quote (quadro
 * RO: Mario Rossi e Paolo Neri al 50 per cento, entrambi soci
 * amministratori). Modello ISA EG37U (bar) con i dati del locale inseriti
 * a mano (quadri B e C), casella "Canone RAI" 2 con il prospetto del canone
 * speciale (rigo RS47), calcolato con il software ufficiale se installato;
 * altrimenti l'omessa presentazione nel frontespizio. Modello IRAP 2026
 * della società (quadro IP, art. 5-bis) con i versamenti in Deleghe.
 */
class RedditiBarCentrale2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(RedditiRossi2025Fixture::class)
                ->addDependency(StudioFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $bar = self::cerca($this->dataMapper, $studio, 'Bar Centrale');
        $rossi = self::cerca($this->dataMapper, $studio, 'ROSSI MARIO');
        $neri = $demo->persona($studio, 'C078', 'NREPLA75C15H501W', 'Neri', 'Paolo', Sesso::maschio, '1975-03-15', 'H501', 'Socio di Bar Centrale s.n.c.');
        $demo->legame($bar, $neri, RuoloLegame::socio, '2018-03-01', 50.0);
        // nelle s.n.c. l'amministrazione spetta ai soci: entrambi legali rappresentanti (Rossi firma le dichiarazioni)
        $demo->legame($bar, $rossi, RuoloLegame::legaleRappresentante);
        $demo->legame($bar, $neri, RuoloLegame::legaleRappresentante, '2018-03-02');

        $redditi = $this->crea($bar, ModelloDichiarativo::redditiSp);
        // televisore nel locale: casella "Canone RAI" 2 e prospetto del canone speciale nel quadro RS (rigo RS47)
        $redditi->canoneRai = CanoneRai::televisione;
        $this->dataMapper->save($redditi);
        $canone = new QuadriDichiarazione($redditi, $this->dataMapper);
        foreach (['RS047002' => '123456789', 'RS047003' => 'ROMA', 'RS047004' => 'RM', 'RS047005' => 'H501', 'RS047006' => 'VIA NAZIONALE 10',
            'RS047007' => '00184', 'RS047008' => 'D', 'RS047009' => '2025-01-31'] as $codice => $valore) {
            $canone->imposta($codice, $valore);
        }
        $canone->salva();
        (new PropostaRedditiSocieta($this->dataMapper))->proponi($redditi);
        $isa = null;
        if ((new MotoreIsa())->isDisponibile(2026)) {
            // bar a Roma: 30 posti interni, aperto 300 giorni, ricavi tutti dal locale e dalla somministrazione: caffetteria 50, cibo non
            // manipolato (panini, insalate) 30, pasticceria 20 per cento; i prezzi praticati
            $isa = (new ModelliIsa($this->dataMapper))->crea($redditi, 'RG');
            $quadri = new QuadriDichiarazione($isa, $this->dataMapper);
            foreach (['B0100101' => 'H501', 'B0100301' => 30, 'B0100501' => 300, 'B0100601' => 2, 'B0101201' => 100, 'C0100701' => 100,
                'C0101901' => 50, 'C0102201' => 30, 'C0102301' => 20,
                // prezzi praticati: caffè 1,20 e acqua minerale 1,00 euro
                'B0100801' => '1.20', 'B0100901' => '1.00'] as $codice => $valore) {
                if ($quadri->haCampo($codice)) {
                    $quadri->imposta($codice, $valore);
                }
            }
            $quadri->salva();
            (new CalcoloIsa($this->dataMapper))->calcola($isa);
            // senza le variabili precalcolate la dichiarazione si trasmette confermando la segnalazione del software
            $redditi->flagConferma = true;
        } else {
            $redditi->omessoIsa = true;
        }
        $this->dataMapper->save($redditi);
        (new PropostaRedditiSocieta($this->dataMapper))->proponi($redditi);

        $irap = $this->crea($bar, ModelloDichiarativo::irap);
        (new PropostaIrap($this->dataMapper))->proponi($irap);
        (new VersamentiRedditi($this->dataMapper))->registraVersamenti($irap, new QuadriDichiarazione($irap, $this->dataMapper));
        $this->addEntity($redditi);
    }

    private function crea(Soggetto $soggetto, ModelloDichiarativo $modello): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = $modello;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }

    /**
     * Soggetto demo dello studio per nominativo.
     */
    public static function cerca(\SismaFramework\Orm\HelperClasses\DataMapper $dataMapper, \Telonio\Application\Entities\Studio $studio, string $nominativo): Soggetto
    {
        foreach ((new SoggettoModel($dataMapper))->cerca($studio, $nominativo, null, null, null, 0, 5) as $soggetto) {
            if (stripos($soggetto->nominativo, $nominativo) !== false) {
                return $soggetto;
            }
        }
        throw new \RuntimeException('Soggetto demo non trovato: ' . $nominativo);
    }
}
