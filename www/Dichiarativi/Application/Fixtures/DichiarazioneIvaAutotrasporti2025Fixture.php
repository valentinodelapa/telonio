<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Autotrasportatore demo (sede a Bologna) trimestrale per natura
 * dell'attività (art. 74, c. 4, D.P.R. 633/1972; dal 2027 art. 139, c. 4,
 * D.Lgs. 10/2026) per il 2025: liquidazioni trimestrali senza gli interessi
 * dell'1%, quarto trimestre liquidato e versato entro il 16 febbraio 2026
 * (codice tributo 6034), comunicazioni periodiche dei primi tre trimestri
 * inviate e del quarto (trimestre "4") preparata ma non inviata;
 * dichiarazione IVA proposta con il quarto trimestre tra i versamenti
 * periodici (VL30) e nel quadro VH (rigo VH16), senza gli interessi
 * annuali del rigo VL36.
 */
class DichiarazioneIvaAutotrasporti2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoNeriFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $azienda = $demo->creaSocieta($studio, 'C076', '09990007602', 'Autotrasporti Emiliani S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '49.41.00', PeriodicitaIva::trimestrale,
                'A944', 'Via del Lavoro 12', '40127');
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($azienda, new SismaDate('2025-01-01'));
        $datiFiscali->trimestraleSpeciale = true;
        $this->dataMapper->save($datiFiscali);
        $demo->legame($azienda, $this->getEntityByFixtureName(SoggettoNeriFixture::class), RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($azienda, 2025);
        $logistica = $demo->controparte($azienda, 'Logistica Padana S.p.A.', '09990017601', true);
        $carburanti = $demo->controparte($azienda, 'Carburanti Emilia S.r.l.', '09990027600', false);
        $officina = $demo->controparte($azienda, 'Officina Veicoli Industriali S.n.c.', '09990037609', false);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        // trasporti fatturati a fine trimestre, gasolio e manutenzioni dei mezzi
        foreach ([1 => '2025-03-31', 2 => '2025-06-30', 3 => '2025-09-30', 4 => '2025-12-31'] as $trimestre => $data) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $logistica, $vendita, $data, (string) $trimestre, ['22' => 30000.0 + 2000.0 * $trimestre]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $carburanti, $acquisto, substr($data, 0, 8) . '15', 'CE/' . $trimestre, ['22' => 12000.0]);
        }
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $officina, $acquisto, '2025-10-20', 'OV/88', ['22' => 4000.0]);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($azienda, 2025, $trimestre, 0.0, false);
        }
        $preparazione = new PreparazioneLipe($this->dataMapper);
        foreach ([1 => ['2025-05-26', '25052612345678911'], 2 => ['2025-09-25', '25092512345678912'], 3 => ['2025-11-26', '25112612345678913']] as $trimestre => [$data, $protocollo]) {
            $comunicazione = $preparazione->prepara($azienda, 2025, $trimestre);
            $comunicazione->stato = StatoComunicazioneLipe::inviata;
            $comunicazione->dataInvio = new SismaDate($data);
            $comunicazione->protocollo = $protocollo;
            $this->dataMapper->save($comunicazione);
        }
        // quarto trimestre comunicato con il trimestre "4", preparato ma non inviato: si recupera nel quadro VH (rigo VH16)
        $preparazione->prepara($azienda, 2025, 4);
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $studio;
        $dichiarazione->soggetto = $azienda;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, new QuadriDichiarazione($dichiarazione, $this->dataMapper));
        $this->addEntity($dichiarazione);
    }
}
