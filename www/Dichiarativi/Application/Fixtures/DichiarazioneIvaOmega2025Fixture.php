<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Entities\SottoscrizioneControllo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Esportatore abituale demo (mensile, sede a Torino) per il 2025:
 * esportazioni e cessioni intracomunitarie, acquisti con dichiarazione
 * d'intento e importazioni (quadro VC con il plafond mensile e le colonne
 * dell'anno precedente, VF28), 
 * comunicazioni periodiche non inviate (quadro VH); incorporazione dal 1°
 * luglio di una società di logistica (trimestrale, sede a Genova) proposta
 * dalla sua contabilità nel modulo 2 (VA1); credito oltre 5.000 euro con la
 * sottoscrizione del collegio sindacale al posto del visto. Modello IVA TR
 * del terzo trimestre (operazioni non imponibili oltre il 25%): parte a
 * rimborso (VL20) e parte in compensazione, utilizzata in F24 (VL22); non
 * riportata nelle liquidazioni successive. Rimborso annuale nel rigo VX4
 * con la causale proposta.
 */
class DichiarazioneIvaOmega2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(SoggettoNeriFixture::class)
                ->addDependency(SoggettoBianchiFixture::class)
                ->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $omega = $demo->creaSocieta($studio, 'C041', '09445560601', 'Omega Export S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '46.64.99', PeriodicitaIva::mensile,
                'L219', 'Corso Francia 100', '10143');
        $demo->legame($omega, $this->getEntityByFixtureName(SoggettoRossiFixture::class), RuoloLegame::amministratoreUnico);
        $demo->legame($omega, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::sindaco);
        $demo->legame($omega, $this->getEntityByFixtureName(SoggettoVerdiFixture::class), RuoloLegame::sindaco);
        $logistica = $demo->creaSocieta($studio, 'C042', '09556670702', 'Omega Logistica S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '52.25.09', PeriodicitaIva::trimestrale,
                'D969', 'Via Buozzi 5', '16126');
        $demo->legame($logistica, $this->getEntityByFixtureName(SoggettoNeriFixture::class), RuoloLegame::amministratoreUnico);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        // Omega: esportazioni del 2024 (plafond solare del 2025) e operazioni del 2025
        $esercizio2024 = $demo->apriContabilita($omega, 2024);
        $atlas = $demo->controparte($omega, 'Atlas Trading LLC', null, true);
        $muller = $demo->controparte($omega, 'Müller Maschinen GmbH', 'DE123456789', true);
        $impianti = $demo->controparte($omega, 'Beta Impianti S.r.l.', '04123650402', true);
        $acciai = $demo->controparte($omega, 'Acciai Nord S.p.A.', '09667780804', false);
        $servizi = $demo->controparte($omega, 'Servizi Industriali S.r.l.', '09778890906', false);
        foreach (['2024-04-15' => 40000.0, '2024-08-20' => 30000.0, '2024-11-10' => 30000.0] as $data => $importo) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio2024, $atlas, $vendita, $data, 'E' . substr($data, 5, 2), ['N3.1' => $importo]);
        }
        $esercizio = $demo->apriEsercizio($omega, 2025);
        // plafond con il metodo mensile, scelto sull'esercizio (liquidazioni e casella 3 del VC14)
        $esercizio->plafondMensile = true;
        $this->dataMapper->save($esercizio);
        foreach (['2025-02-20' => 30000.0, '2025-06-18' => 25000.0, '2025-10-22' => 25000.0] as $data => $importo) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $atlas, $vendita, $data, 'E' . substr($data, 5, 2), ['N3.1' => $importo]);
        }
        foreach (['2025-05-12' => 20000.0, '2025-09-30' => 20000.0] as $data => $importo) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $muller, $vendita, $data, 'C' . substr($data, 5, 2), ['N3.2' => $importo]);
        }
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $impianti, $vendita, '2025-12-05', 'V12', ['22' => 10000.0]);
        foreach (['2025-01-25', '2025-04-22', '2025-07-15', '2025-10-14'] as $indice => $data) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $acciai, $acquisto, $data, 'AN/' . ($indice + 1), ['N3.5' => 15000.0]);
        }
        // importazioni: componenti dalla Svizzera con la dichiarazione d'intento in dogana (VC colonna 2) e utensili con l'IVA pagata in dogana (VF28)
        $dogana = $demo->controparte($omega, 'Agenzia delle Dogane - Ufficio di Torino', null, false);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $dogana, $acquisto, '2025-03-18', 'BD/101', ['N3.5' => 10000.0], ['importazione' => true, 'aliquotaTeorica' => 22.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $dogana, $acquisto, '2025-09-09', 'BD/245', ['22' => 5000.0], ['importazione' => true]);
        foreach (['2025-02-12', '2025-04-14', '2025-06-12', '2025-08-11', '2025-11-12'] as $indice => $data) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $servizi, $acquisto, $data, 'SI/' . ($indice + 1), ['22' => 10000.0]);
        }
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            $elaborazione->elabora($omega, 2025, $mese, 0.0, false);
        }
        // credito del terzo trimestre con il modello IVA TR: non si riporta a ottobre, le liquidazioni successive si rielaborano
        $tr = $demo->presentaTr($omega, 2025, 3, ['TD006001' => 1300.0, 'TD007001' => 2000.0], '2025-10-28');
        $demo->compensaCreditoTr($tr, 2000.0, '2025-11-17');
        // Logistica: operazioni del primo semestre, fino all'incorporazione
        $esercizioLogistica = $demo->apriContabilita($logistica, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioLogistica, $demo->controparte($logistica, 'Servizi Industriali S.r.l.', '09778890906', false), $acquisto,
                '2025-02-15', 'SI/40', ['22' => 10000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioLogistica, $demo->controparte($logistica, 'Omega Export S.r.l.', '09445560601', true), $vendita,
                '2025-05-20', '1', ['22' => 9000.0]);
        $elaborazione->elabora($logistica, 2025, 1, 0.0, false);
        $elaborazione->elabora($logistica, 2025, 2, 0.0, false);
        // dichiarazione dell'incorporante con il modulo dell'incorporata e il collegio sindacale
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $studio;
        $dichiarazione->soggetto = $omega;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $modulo = new ModuloDichiarazione($this->dataMapper);
        $modulo->dichiarazione = $dichiarazione;
        $modulo->numero = 2;
        $modulo->tipo = TipoModulo::soggettoTrasformato;
        $modulo->soggettoFonte = $logistica;
        $modulo->descrizione = 'Omega Logistica S.r.l., incorporata dal 1° luglio 2025';
        $modulo->dataOperazione = new SismaDate('2025-07-01');
        $this->dataMapper->save($modulo);
        foreach ((new Frontespizio($this->dataMapper))->getOrganoControllo($omega) as $indice => [$tipo, $componente]) {
            $sottoscrizione = new SottoscrizioneControllo($this->dataMapper);
            $sottoscrizione->dichiarazione = $dichiarazione;
            $sottoscrizione->ordine = $indice + 1;
            $sottoscrizione->tipoSoggetto = $tipo;
            $sottoscrizione->soggetto = $componente;
            $this->dataMapper->save($sottoscrizione);
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $quadri->imposta('VX004001', 4000);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
