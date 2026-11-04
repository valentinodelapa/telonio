<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Fixtures\ClienteBetaAlfaFixture;
use Contabilita\Application\Fixtures\ClientePaAlfaFixture;
use Contabilita\Application\Fixtures\EsercizioAlfa2026Fixture;
use Contabilita\Application\Fixtures\FornitoreDeltaAlfaFixture;
use Contabilita\Application\Fixtures\FornitoreGammaAlfaFixture;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Esercizio 2025 demo di Alfa (mensile) per la dichiarazione IVA 2026:
 * prestazioni al 22% e alla PA in scissione dei pagamenti, acquisto di
 * macchine d'ufficio (bene ammortizzabile, VF29), manutenzione, spese
 * dell'auto detraibili al 40%, subappalto in inversione contabile (N6.3, rigo VJ12), libri
 * al 4% e assicurazione esente. Le operazioni sono scelte perché la
 * dichiarazione chiuda con il credito di 8.000 euro registrato in Deleghe
 * F24 (CreditoAlfaIvaFixture), che la dichiarazione adotta.
 */
class OperazioniAlfa2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClienteBetaAlfaFixture::class)
                ->addDependency(ClientePaAlfaFixture::class)
                ->addDependency(FornitoreGammaAlfaFixture::class)
                ->addDependency(FornitoreDeltaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $esercizio = new EsercizioContabile($this->dataMapper);
        $esercizio->studio = $alfa->studio;
        $esercizio->soggetto = $alfa;
        $esercizio->anno = 2025;
        $esercizio->dataInizio = new SismaDate('2025-01-01');
        $esercizio->dataFine = new SismaDate('2025-12-31');
        $this->dataMapper->save($esercizio);
        $beta = $this->getEntityByFixtureName(ClienteBetaAlfaFixture::class);
        $gamma = $this->getEntityByFixtureName(FornitoreGammaAlfaFixture::class);
        $delta = $this->getEntityByFixtureName(FornitoreDeltaAlfaFixture::class);
        $epsilon = $this->creaFornitoreInformatica($alfa);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $gamma, $acquisto, '2025-01-20', 'POL-25', ['N4' => 800.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $epsilon, $acquisto, '2025-02-12', 'E/31', ['22' => 60000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $beta, $vendita, '2025-03-31', '1', ['22' => 7500.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $gamma, $acquisto, '2025-05-15', 'G/77', ['22' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $beta, $vendita, '2025-06-30', '2', ['22' => 7500.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $this->getEntityByFixtureName(ClientePaAlfaFixture::class), $vendita, '2025-07-15', '3', ['22' => 5000.0],
                ['esigibilita' => EsigibilitaIva::scissionePagamenti]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $gamma, $acquisto, '2025-08-28', 'G/102', ['A22' => 2500.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $beta, $vendita, '2025-09-30', '4', ['22' => 7500.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $delta, $acquisto, '2025-10-20', 'D/18', ['RC22' => 1000.0], ['naturaInversione' => NaturaIva::inversioneSubappaltoEdile]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $gamma, $acquisto, '2025-11-14', 'G/130', ['4' => 2000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $beta, $vendita, '2025-12-31', '5', ['22' => 7500.0]);
        $this->addEntity($esercizio);
    }

    private function creaFornitoreInformatica($alfa): Controparte
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($alfa);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $alfa->studio;
        $controparte->soggetto = $alfa;
        $controparte->denominazione = 'Epsilon Informatica S.r.l.';
        $controparte->partitaIva = '05874321002';
        $controparte->codiceFiscale = '05874321002';
        $controparte->indirizzo = 'Via Tiburtina 1020';
        $controparte->cap = '00156';
        $controparte->comune = 'Roma';
        $controparte->provincia = 'RM';
        $controparte->fornitore = true;
        // macchine d'ufficio elettroniche: bene ammortizzabile (rigo VF29, campo 1)
        $controparte->contoContropartita = (new ContoModel($this->dataMapper))->getPerCodice($piano, '02.04.0002');
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        return $controparte;
    }
}
