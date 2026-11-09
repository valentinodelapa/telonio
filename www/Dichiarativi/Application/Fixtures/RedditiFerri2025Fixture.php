<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Fixtures\SoggettoFerriFixture;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Fixtures\StoricoFerri2025Fixture;
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

/**
 * Modello Redditi PF 2026 demo di Lucia Ferri (anno 2025), in bozza:
 * negozio in contabilità semplificata con il reddito del quadro RG per
 * cassa dalle registrazioni della Contabilità (fatture incassate e pagate
 * nell'anno), IRPEF con la detrazione per gli altri redditi, addizionali
 * dalla residenza a Roma e versamenti registrati in Deleghe F24. Il negozio
 * è un'impresa familiare con il figlio Paolo collaboratore al 30 per cento
 * (legame dell'Anagrafica): prospetto di imputazione del quadro RS e quote
 * del rigo RG33. Il conto delle spese telefoniche è deducibile all'80 per
 * cento.
 */
class RedditiFerri2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(StoricoFerri2025Fixture::class)
                ->addDependency(DichiarazioneIvaFerri2025Fixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ferri = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $residenza = new Indirizzo($this->dataMapper);
        $residenza->soggetto = $ferri;
        $residenza->tipoIndirizzo = TipoIndirizzo::residenza;
        $residenza->validoDal = new SismaDate('2012-05-01');
        $residenza->indirizzo = 'Via Tuscolana';
        $residenza->numeroCivico = '45';
        $residenza->cap = '00181';
        $residenza->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $residenza->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($residenza);

        // impresa familiare con il figlio collaboratore
        $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($ferri, new SismaDate('2025-12-31'));
        $dati->tipoAttivita = TipoAttivita::impresaFamiliare;
        $this->dataMapper->save($dati);
        $demo = new AziendaDemo($this->dataMapper);
        $paolo = $demo->persona($ferri->studio, 'C074', 'FRRPLA98D10H501L', 'Ferri', 'Paolo', Sesso::maschio, '1998-04-10', 'H501', 'Figlio di Lucia Ferri, collaboratore nel negozio');
        $demo->legame($ferri, $paolo, RuoloLegame::collaboratoreFamiliare, '2025-04-01', 30.0);

        // spese telefoniche deducibili all'80 per cento (art. 102, comma 9, Tuir)
        $telefono = (new ContoModel($this->dataMapper))->getPerCodice((new PianoContiModel($this->dataMapper))->getPerAzienda($ferri), '22.01.0003');
        $telefono->percentualeDeducibile = 80.0;
        $telefono->variazioneRedditi = 'RF31:99';
        $this->dataMapper->save($telefono);

        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $ferri->studio;
        $dichiarazione->soggetto = $ferri;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaRedditi($this->dataMapper))->proponi($dichiarazione, $quadri);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new VersamentiRedditi($this->dataMapper))->registraVersamenti($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
