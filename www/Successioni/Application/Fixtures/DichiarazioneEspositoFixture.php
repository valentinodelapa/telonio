<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\ImpegnoTrasmissione;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Models\UfficioRegistrazioneModel;

/**
 * Dichiarazione di successione demo di Giuseppe Esposito: devoluzione per legge e per testamento (legato dei titoli al nipote), imposta autoliquidata a rate.
 */
class DichiarazioneEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
        $this->addDependency(SoggettoEspositoGiuseppeFixture::class);
        $this->addDependency(SoggettoEspositoMarcoFixture::class);
        $this->addDependency(ContoCorrenteEspositoMarcoFixture::class);
        $this->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $dichiarazione->defunto = $this->getEntityByFixtureName(SoggettoEspositoGiuseppeFixture::class);
        $dichiarazione->devoluzioneLegge = true;
        $dichiarazione->devoluzioneTestamento = true;
        $dichiarazione->testamentoPubblicoUfficiale = 'Notaio Paolo De Luca';
        $dichiarazione->testamentoDataPubblicazione = new SismaDate('2025-10-20');
        $dichiarazione->testamentoUfficio = (new UfficioRegistrazioneModel($this->dataMapper))->getByCodiceUfficio('TE8');
        $dichiarazione->testamentoSerie = '1T';
        $dichiarazione->testamentoNumero = '15432';
        $dichiarazione->testamentoDataRegistrazione = new SismaDate('2025-10-27');
        $dichiarazione->comuneDecesso = (new ComuneModel($this->dataMapper))->getByCodice('F839');
        $dichiarazione->statoCivile = StatoCivileDefunto::coniugatoComunione;
        $dichiarazione->presentatore = $this->getEntityByFixtureName(SoggettoEspositoMarcoFixture::class);
        $dichiarazione->codiceCarica = CaricaDichiarante::erede;
        $dichiarazione->telefono = '0815551234';
        $dichiarazione->email = 'marco.esposito@example.it';
        $dichiarazione->intermediario = $this->getEntityByFixtureName(IntermediarioStudioFixture::class);
        $dichiarazione->impegno = ImpegnoTrasmissione::predispostaIntermediario;
        $dichiarazione->dataImpegno = new SismaDate('2026-03-02');
        $dichiarazione->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteEspositoMarcoFixture::class);
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::nonContestuale;
        $dichiarazione->numeroRate = 4;
        $dichiarazione->acconto = 600;
        $dichiarazione->dataVersamento = new SismaDate('2026-03-16');
        $dichiarazione->primaCasaOpzione = 'R';
        $dichiarazione->primaCasaAltraCasa = 'N';
        $dichiarazione->dataDichiarazione = new SismaDate('2026-03-02');
        $this->addEntity($dichiarazione);
    }
}
