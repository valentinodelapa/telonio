<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Commercio con contabilità separate (art. 36, c. 3, per opzione) demo
 * per il 2025: ingrosso di macchine per l'edilizia, liquidato
 * mensilmente, e negozio di ferramenta, liquidato trimestralmente
 * (volume d'affari complessivo entro i limiti dei trimestrali); il
 * trimestre del negozio è liquidato con il mese di fine trimestre
 * (liquidazione anticipata), l'affitto del capannone è un acquisto
 * promiscuo ripartito 70/30; il secondo trimestre del negozio è liquidato a
 * parte (termine dei trimestrali, codice 6032). Dichiarazione IVA con un
 * modulo per attività.
 */
class DichiarazioneIvaGialli2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoBianchiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $gialli = $demo->creaSocieta($this->getEntityByFixtureName(StudioFixture::class), 'C044', '09111220209', 'Gialli Edilforniture S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata,
                '46.63.00', PeriodicitaIva::mensile, 'A794', 'Via Borgo Palazzo 40', '24125');
        $demo->legame($gialli, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($gialli, 2025);
        $ingrosso = $demo->attivita($gialli, 1, 'Commercio all\'ingrosso di macchine per l\'edilizia', '46.63.00', null, 2025);
        $negozio = $demo->attivita($gialli, 2, 'Negozio di ferramenta', '47.52.10', PeriodicitaIva::trimestrale, 2025);
        $demo->assegnaRegistro($gialli, TipoRegistroIva::vendite, '', $ingrosso);
        $demo->assegnaRegistro($gialli, TipoRegistroIva::acquisti, '', $ingrosso);
        $demo->assegnaRegistro($gialli, TipoRegistroIva::corrispettivi, '', $negozio);
        $venditeNegozio = $demo->sezionale($gialli, TipoRegistroIva::vendite, 'NG', 'Fatture del negozio', null);
        $venditeNegozio->attivitaIva = $negozio;
        $this->dataMapper->save($venditeNegozio);
        $acquistiNegozio = $demo->sezionale($gialli, TipoRegistroIva::acquisti, 'NG', 'Acquisti del negozio', null);
        $acquistiNegozio->attivitaIva = $negozio;
        $this->dataMapper->save($acquistiNegozio);
        $promiscui = $demo->sezionale($gialli, TipoRegistroIva::acquisti, 'PR', 'Acquisti promiscui', null);
        $imprese = $demo->controparte($gialli, 'Costruzioni Bergamasche S.p.A.', '09666770707', true);
        $privati = $demo->controparte($gialli, 'Clienti del negozio', null, true);
        $produttore = $demo->controparte($gialli, 'Macchine Edili Lombarde S.r.l.', '09777880817', false);
        $grossista = $demo->controparte($gialli, 'Ferramenta Distribuzione S.r.l.', '09888990919', false);
        $immobiliare = $demo->controparte($gialli, 'Immobiliare Orobica S.r.l.', '09999000996', false);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        foreach (['01', '04', '07', '10'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $produttore, $acquisto, '2025-' . $mese . '-15', 'ML/' . ($indice + 1), ['22' => 20000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $grossista, $acquisto, '2025-' . $mese . '-20', 'FD/' . ($indice + 1), ['22' => 6000.0], ['registro' => $acquistiNegozio]);
            // affitto del capannone: 70% all'ingrosso, 30% al negozio
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $immobiliare, $acquisto, '2025-' . $mese . '-05', 'AF/' . ($indice + 1), ['22' => 3000.0],
                    ['registro' => $promiscui, 'ripartizione' => [1 => 70.0, 2 => 30.0]]);
        }
        foreach (['02', '05', '08', '11'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $imprese, $vendita, '2025-' . $mese . '-25', (string) ($indice + 1), ['22' => 30000.0]);
        }
        foreach (['03', '06', '09', '12'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $privati, $vendita, '2025-' . $mese . '-28', 'NG/' . ($indice + 1), ['22' => 9000.0], ['registro' => $venditeNegozio]);
        }
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            // giugno senza anticipare il secondo trimestre del negozio, liquidato a parte
            $elaborazione->elabora($gialli, 2025, $mese, 0.0, false, 0.0, 0.0, $mese !== 6);
            if ($mese === 6) {
                $elaborazione->elaboraTrimestre($gialli, 2025, 2, 0.0, false);
            }
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $gialli->studio;
        $dichiarazione->soggetto = $gialli;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new VersamentiIva($this->dataMapper))->registraSaldo($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
