<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use Contabilita\Application\Entities\ProrataIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Poliambulatorio demo (trimestrale, sede a Milano) con prestazioni
 * sanitarie esenti e certificati medico-legali imponibili per il 2025:
 * pro-rata provvisorio del 15% nelle liquidazioni, percentuale definitiva
 * del 15% nella dichiarazione IVA (VF34 con gli interessi attivi esenti
 * occasionali, VF37) registrata come provvisoria del 2026, cessione di un
 * ecografo usato (VE40), quadro VT con i certificati ai privati in
 * Lombardia e, dal sezionale dell'ambulatorio di Piacenza, in
 * Emilia-Romagna, saldo con gli interessi dei trimestrali versato in cinque
 * rate, visto di conformità del CAF.
 */
class DichiarazioneIvaPoliambulatorio2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoNeriFixture::class)
                ->addDependency(SoggettoBianchiFixture::class)
                ->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $sole = $demo->creaSocieta($this->getEntityByFixtureName(StudioFixture::class), 'C040', '06987612006', 'Poliambulatorio Sole S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata,
                '86.22.03', PeriodicitaIva::trimestrale, 'F205', 'Via Larga 12', '20122');
        $demo->legame($sole, $this->getEntityByFixtureName(SoggettoNeriFixture::class), RuoloLegame::amministratoreUnico);
        $demo->legame($sole, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::sindaco);
        $demo->legame($sole, $this->getEntityByFixtureName(SoggettoVerdiFixture::class), RuoloLegame::sindaco);
        $esercizio = $demo->apriContabilita($sole, 2025);
        // ambulatorio di Piacenza con il suo sezionale delle fatture: privati nel VT dell'Emilia-Romagna
        $piacenza = $demo->sedeOperativa($sole, 'G535', 'Via Cavour 18', '29121');
        $sezionalePiacenza = $demo->sezionale($sole, TipoRegistroIva::vendite, 'PC', 'Fatture emesse - ambulatorio di Piacenza', $piacenza);
        $pazienti = $demo->controparte($sole, 'Pazienti privati', null, true);
        $assicurazione = $demo->controparte($sole, 'Delta Assicurazioni S.p.A.', '09112233003', true);
        $forniture = $demo->controparte($sole, 'Medical Supply S.r.l.', '09223344004', false, '02.03.0001');
        $laboratorio = $demo->controparte($sole, 'Lab Service S.r.l.', '09334455004', false);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $forniture, $acquisto, '2025-02-10', 'MS/12', ['22' => 20000.0]);
        foreach (['03', '06', '09', '12'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $laboratorio, $acquisto, '2025-' . $mese . '-10', 'LS/' . ($indice + 1), ['22' => 10000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $pazienti, $vendita, '2025-' . $mese . '-28', (string) ($indice + 1), ['N4' => 30000.0]);
        }
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $pazienti, $vendita, '2025-11-15', '5', ['22' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $pazienti, $vendita, '2025-12-15', '6', ['22' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $assicurazione, $vendita, '2025-12-20', '7', ['22' => 8000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $pazienti, $vendita, '2025-12-18', 'PC/1', ['22' => 3000.0], ['registro' => $sezionalePiacenza]);
        // interessi attivi su un deposito (esente occasionale, n. 1) e cessione di un ecografo usato (bene ammortizzabile, VE40)
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $assicurazione, $vendita, '2025-12-30', '8', ['N4' => 500.0], ['qualifica' => QualificaOperazione::esenteOccasionale]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($sole, 'Studio Medico Rossi', '04123654008', true), $vendita, '2025-10-10', '9', ['22' => 2000.0],
                ['qualifica' => QualificaOperazione::beneAmmortizzabile]);
        $prorata = new ProrataIva($this->dataMapper);
        $prorata->studio = $sole->studio;
        $prorata->soggetto = $sole;
        $prorata->anno = 2025;
        $prorata->percentuale = 15;
        $prorata->note = 'Percentuale di detrazione definitiva 2024';
        $this->dataMapper->save($prorata);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
            $elaborazione->elabora($sole, 2025, $trimestre, 0.0, false);
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $sole->studio;
        $dichiarazione->soggetto = $sole;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        $dichiarazione->modalitaSaldo = ModalitaSaldo::rate;
        $dichiarazione->numeroRate = 5;
        // visto del CAF a cui lo studio si appoggia
        $dichiarazione->vistoCaf = $demo->creaSocieta($sole->studio, 'F001', '09889900008', 'CAF Imprese Lombardia S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '69.20.01',
                PeriodicitaIva::mensile, 'F205', 'Via Vittor Pisani 22', '20124');
        $dichiarazione->vistoCaf->clienteStudio = false;
        $this->dataMapper->save($dichiarazione->vistoCaf);
        $dichiarazione->vistoResponsabileCaf = $demo->persona($sole->studio, 'F002', 'CLMNNA75D45F205G', 'Colombo', 'Anna', Sesso::femmina, '1975-04-05', 'F205', 'Responsabile dell\'assistenza fiscale del CAF Imprese Lombardia');
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        $versamenti = new VersamentiIva($this->dataMapper);
        $versamenti->registraSaldo($dichiarazione, $quadri);
        $versamenti->registraProrata($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
