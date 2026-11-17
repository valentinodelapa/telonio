<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Ammortamenti;
use Contabilita\Application\Services\Partitario;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\CorrispettiviMensili;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Esercizio 2025 demo del negozio Ferri, chiuso, con gli acquisti per la
 * rivendita (anche di altri prodotti) e i corrispettivi di dicembre: base
 * della verifica dei limiti della ventilazione per il 2026 (artt. 4 e 5
 * D.M. 24 febbraio 1973). Le tre fatture d'acquisto sono pagate con
 * bonifico a dicembre, abbinate alle partite: in contabilità semplificata
 * (per cassa) i costi entrano nel quadro RG e nel quadro F del modello ISA.
 * Nel registro dei cespiti il banco frigo comprato nel 2023 (6.000 euro,
 * 15 per cento, ammortizzato nel 2023 e nel 2024), con la quota del 2025:
 * valore dei beni strumentali e ammortamenti del modello ISA.
 */
class StoricoFerri2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class)
                ->addDependency(FornitoreServiziFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ferri = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $esercizio = new EsercizioContabile($this->dataMapper);
        $esercizio->studio = $ferri->studio;
        $esercizio->soggetto = $ferri;
        $esercizio->anno = 2025;
        $esercizio->dataInizio = new SismaDate('2025-01-01');
        $esercizio->dataFine = new SismaDate('2025-12-31');
        $this->dataMapper->save($esercizio);
        $grossista = $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $grossista, CausaleContabile::fatturaAcquisto, '2025-11-10', '2210', ['4' => 4000.0, '10' => 9000.0, '22' => 3000.0], ['rivendita' => true]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $grossista, CausaleContabile::fatturaAcquisto, '2025-11-20', '2251', ['22' => 1800.0], ['altriProdotti' => true]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $this->getEntityByFixtureName(FornitoreServiziFerriFixture::class), CausaleContabile::fatturaAcquisto, '2025-12-05', 'G/118', ['22' => 600.0]);
        $registro = (new RegistroIvaModel($this->dataMapper))->getPerTipo($ferri, TipoRegistroIva::corrispettivi)[0];
        $ventilare = (new CodiceIvaModel($this->dataMapper))->getPerCodice($ferri->studio, 'VEN');
        $importi = [];
        for ($giorno = new \DateTimeImmutable('2025-12-01'); (int) $giorno->format('n') === 12; $giorno = $giorno->modify('+1 day')) {
            if ((int) $giorno->format('w') !== 0) {
                $importi[$giorno->format('Y-m-d')][$ventilare->id] = 650.0 + ((int) $giorno->format('j') * 41) % 260;
            }
        }
        (new CorrispettiviMensili($this->dataMapper))->registra($registro, 2025, 12, $importi, [$ventilare->id => $ventilare]);
        // pagamenti dei fornitori con bonifico, abbinati alle fatture
        $banca = (new ContoModel($this->dataMapper))->getPerFunzione((new PianoContiModel($this->dataMapper))->getPerAzienda($ferri), FunzioneConto::banca);
        $partitario = new Partitario($this->dataMapper);
        foreach ([['2025-12-10', $grossista, '2210', 17720.0], ['2025-12-20', $grossista, '2251', 2196.0], ['2025-12-30', $this->getEntityByFixtureName(FornitoreServiziFerriFixture::class), 'G/118', 732.0]] as [$data, $fornitore, $numero, $importo]) {
            $this->paga($esercizio, $partitario, $banca, $fornitore, $data, $numero, $importo);
        }
        // registro dei cespiti: banco frigo del 2023 con il fondo dei due esercizi precedenti, quota del 2025 registrata
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($ferri);
        $conti = new ContoModel($this->dataMapper);
        $banco = new Cespite($this->dataMapper);
        $banco->studio = $ferri->studio;
        $banco->soggetto = $ferri;
        $banco->codice = '001';
        $banco->descrizione = 'Banco frigo';
        $banco->contoCespite = $conti->getPerCodice($piano, '02.03.0001');
        $banco->contoFondo = $conti->getPerCodice($piano, '03.02.0003');
        $banco->contoAmmortamento = $conti->getPerCodice($piano, '25.01.0002');
        $banco->dataAcquisto = new SismaDate('2023-03-01');
        $banco->costo = 6000.0;
        $banco->percentuale = 15.0;
        $banco->fondoIniziale = 1350.0;
        $banco->esercizioFondoIniziale = 2024;
        $this->dataMapper->save($banco);
        (new Ammortamenti($this->dataMapper))->registra($ferri, $esercizio);
        $esercizio->chiuso = true;
        $this->addEntity($esercizio);
    }

    /**
     * Pagamento con bonifico della fattura del fornitore, abbinato alla sua
     * partita.
     */
    private function paga(EsercizioContabile $esercizio, Partitario $partitario, \Contabilita\Application\Entities\Conto $banca, Controparte $fornitore, string $data, string $numero, float $importo): void
    {
        $conto = $partitario->getConto($fornitore, false);
        $partita = null;
        foreach ((new PartitaModel($this->dataMapper))->getPerControparte($fornitore) as $candidata) {
            if (($candidata->numeroDocumento === $numero) && ($candidata->registrazioneContabile->esercizioContabile->id === $esercizio->id)) {
                $partita = $candidata;
            }
        }
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $esercizio->soggetto->studio;
        $registrazione->soggetto = $esercizio->soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->causale = CausaleContabile::pagamento;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->controparte = $fornitore;
        $registrazione->descrizione = 'Bonifico fattura n. ' . $numero;
        $registrazione->totaleDocumento = $importo;
        $righe = [];
        foreach ([[$conto, $importo, 0.0, $fornitore], [$banca, 0.0, $importo, null]] as [$contoRiga, $dare, $avere, $controparteRiga]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $contoRiga;
            $riga->controparte = $controparteRiga;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, [], $righe, null, null,
                [$fornitore->id . '-' . $conto->id => ['abbinamenti' => [$partita?->id => ['DEN' => $importo]], 'acconto' => false, 'riapre' => null]]);
    }
}
