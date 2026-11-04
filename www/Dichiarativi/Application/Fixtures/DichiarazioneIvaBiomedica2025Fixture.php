<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Contabilita\Application\Entities\ProrataIva;
use Contabilita\Application\Enumerations\CausaleContabile;
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
 * Produttore di apparecchi elettromedicali demo (mensile, sede a Bologna)
 * con un laboratorio di analisi: esportatore abituale che acquista i
 * componenti con dichiarazione d'intento (plafond solare dalle
 * esportazioni del 2024) ed effettua anche prestazioni sanitarie esenti.
 * Con il pro-rata la detrazione tiene conto dell'IVA non assolta sugli
 * acquisti con dichiarazione d'intento (VF35, all'aliquota teorica delle
 * righe IVA): pro-rata provvisorio del 75% nelle liquidazioni, percentuale
 * definitiva del 76% nella dichiarazione IVA.
 */
class DichiarazioneIvaBiomedica2025Fixture extends BaseFixture
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
        $biomedica = $demo->creaSocieta($this->getEntityByFixtureName(StudioFixture::class), 'C043', '09776655004', 'Biomedica Nord S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata,
                '26.60.01', PeriodicitaIva::mensile, 'A944', 'Via della Ricerca 7', '40129');
        $demo->legame($biomedica, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::amministratoreUnico);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        // esportazioni del 2024: plafond solare del 2025
        $esercizio2024 = $demo->apriContabilita($biomedica, 2024);
        $distributore = $demo->controparte($biomedica, 'MedTech Distribution Inc.', null, true);
        foreach (['2024-03-14' => 50000.0, '2024-07-10' => 50000.0, '2024-11-20' => 50000.0] as $data => $importo) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio2024, $distributore, $vendita, $data, 'E' . substr($data, 5, 2), ['N3.1' => $importo]);
        }
        $esercizio = $demo->apriEsercizio($biomedica, 2025);
        $clinica = $demo->controparte($biomedica, 'Clinica San Luca S.p.A.', '09887711006', true);
        $pazienti = $demo->controparte($biomedica, 'Pazienti del laboratorio', null, true);
        $componenti = $demo->controparte($biomedica, 'Elettronica Emiliana S.r.l.', '09665544003', false);
        $servizi = $demo->controparte($biomedica, 'Facility Service S.r.l.', '09554433002', false);
        foreach (['03', '06', '09', '12'] as $indice => $mese) {
            // apparecchi esportati, analisi di laboratorio esenti (art. 10, n. 18)
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $distributore, $vendita, '2025-' . $mese . '-20', 'E' . ($indice + 1), ['N3.1' => 40000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $pazienti, $vendita, '2025-' . $mese . '-28', 'L' . ($indice + 1), ['N4' => 15000.0]);
            // componenti senza IVA con la dichiarazione d'intento: l'IVA non assolta sarebbe stata al 22%
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $componenti, $acquisto, '2025-' . $mese . '-05', 'EE/' . ($indice + 1), ['N3.5' => 20000.0],
                    ['aliquotaTeorica' => 22.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $servizi, $acquisto, '2025-' . $mese . '-10', 'FS/' . ($indice + 1), ['22' => 10000.0]);
        }
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $clinica, $vendita, '2025-05-15', 'V1', ['22' => 15000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $clinica, $vendita, '2025-10-15', 'V2', ['22' => 15000.0]);
        $prorata = new ProrataIva($this->dataMapper);
        $prorata->studio = $biomedica->studio;
        $prorata->soggetto = $biomedica;
        $prorata->anno = 2025;
        $prorata->percentuale = 75;
        $prorata->note = 'Percentuale di detrazione definitiva 2024';
        $this->dataMapper->save($prorata);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            $elaborazione->elabora($biomedica, 2025, $mese, 0.0, false);
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $biomedica->studio;
        $dichiarazione->soggetto = $biomedica;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
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
