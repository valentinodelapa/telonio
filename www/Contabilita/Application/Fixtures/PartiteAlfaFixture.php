<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Partite aperte demo di Alfa, con i modi di chiusura senza solo denaro:
 * - fattura n. 4 a Beta (3.904): incassata con RiBa il 28/2, insoluta il
 *   5/3 (la partita si riapre), incassata il 20/3 con un abbuono di 4 euro;
 * - acconto di 1.000 euro di Beta il 25/3, senza fatture aperte;
 * - fattura n. 2 al Comune in scissione dei pagamenti: la partita è al
 *   netto dell'IVA, incassata il 20/2;
 * - fattura G/12 di Gamma (800 + IVA): pagata il 15/2 al netto della
 *   ritenuta d'acconto del 20 per cento (816 di bonifico e 160 di
 *   ritenuta da versare);
 * - fattura n. 5 a Delta del 10/4, compensata il 30/4 con il debito
 *   verso Delta per la fattura D/45.
 */
class PartiteAlfaFixture extends BaseFixture
{

    private EsercizioContabile $esercizio;

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClienteBetaAlfaFixture::class)
                ->addDependency(ClientePaAlfaFixture::class)
                ->addDependency(FornitoreGammaAlfaFixture::class)
                ->addDependency(FornitoreDeltaAlfaFixture::class)
                ->addDependency(FatturaVenditaAlfaFebbraioFixture::class)
                ->addDependency(FatturaSplitAlfaFixture::class)
                ->addDependency(FatturaAcquistoAlfaFixture::class)
                ->addDependency(AcquistoReverseChargeAlfaFixture::class)
                ->addDependency(IncassoBetaAlfaFixture::class)
                ->addDependency(LiquidazioneAlfaMarzoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->esercizio = $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class);
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($this->esercizio->soggetto);
        $contoModel = new ContoModel($this->dataMapper);
        $banca = $contoModel->getPerFunzione($piano, FunzioneConto::banca);
        $arrotondamenti = $contoModel->getPerFunzione($piano, FunzioneConto::arrotondamentiPassivi);
        $ritenute = $contoModel->getPerFunzione($piano, FunzioneConto::ritenuteDaVersare);
        $partitario = new Partitario($this->dataMapper);
        $beta = $this->getEntityByFixtureName(ClienteBetaAlfaFixture::class);
        $contoBeta = $partitario->getConto($beta, true);
        $comune = $this->getEntityByFixtureName(ClientePaAlfaFixture::class);
        $gamma = $this->getEntityByFixtureName(FornitoreGammaAlfaFixture::class);
        $delta = $this->getEntityByFixtureName(FornitoreDeltaAlfaFixture::class);

        // abbinamenti espliciti: le partite del 2025 possono essere ancora aperte
        $this->registra(CausaleContabile::incasso, '2026-02-28', $beta, 'RiBa fattura n. 4', [[$banca, 3904, 0, null], [$contoBeta, 0, 3904, $beta]],
                [$this->chiave($beta, $contoBeta) => self::scelta([$this->partita($beta, '4')->id => ['DEN' => 3904.0]])], 3904.0);
        $this->registra(CausaleContabile::giroconto, '2026-03-05', $beta, 'RiBa insoluta fattura n. 4', [[$contoBeta, 3904, 0, $beta], [$banca, 0, 3904, null]],
                [$this->chiave($beta, $contoBeta) => self::scelta([], false, $this->partita($beta, '4')->id)]);
        $insoluto = $this->partita($beta, null);
        $this->registra(CausaleContabile::incasso, '2026-03-20', $beta, 'Bonifico fattura n. 4 con abbuono', [[$banca, 3900, 0, null], [$arrotondamenti, 4, 0, null], [$contoBeta, 0, 3904, $beta]],
                [$this->chiave($beta, $contoBeta) => self::scelta([$insoluto->id => ['DEN' => 3900.0, 'ABB' => 4.0]])], 3900.0);
        // acconto senza fatture
        $this->registra(CausaleContabile::incasso, '2026-03-25', $beta, 'Acconto sulla fornitura di aprile', [[$banca, 1000, 0, null], [$contoBeta, 0, 1000, $beta]],
                [$this->chiave($beta, $contoBeta) => self::scelta([], true)], 1000.0);
        // scissione dei pagamenti: il Comune paga l'imponibile
        $contoComune = $partitario->getConto($comune, true);
        $this->registra(CausaleContabile::incasso, '2026-02-20', $comune, 'Incasso fattura n. 2 (scissione dei pagamenti)', [[$banca, 500, 0, null], [$contoComune, 0, 500, $comune]],
                [$this->chiave($comune, $contoComune) => self::scelta([$this->partita($comune, '2')->id => ['DEN' => 500.0]])], 500.0);
        // pagamento al netto della ritenuta d'acconto
        $contoGamma = $partitario->getConto($gamma, false);
        $this->registra(CausaleContabile::pagamento, '2026-02-15', $gamma, 'Pagamento fattura G/12 al netto della ritenuta', [[$contoGamma, 976, 0, $gamma], [$banca, 0, 816, null], [$ritenute, 0, 160, null]],
                [$this->chiave($gamma, $contoGamma) => self::scelta([$this->partita($gamma, 'G/12')->id => ['DEN' => 816.0, 'RIT' => 160.0]])], 816.0);
        // compensazione tra il credito verso Delta (fattura n. 5) e il debito (fattura D/45)
        RegistrazioneDocumentoIva::registra($this->dataMapper, $this->esercizio, $delta, CausaleContabile::fatturaVendita, '2026-04-10', '5', ['22' => 500.0]);
        $debito = $partitario->getConto($delta, false);
        $credito = $partitario->getConto($delta, true);
        $this->addEntity($this->registra(CausaleContabile::giroconto, '2026-04-30', $delta, 'Compensazione fattura n. 5 con fattura D/45', [[$debito, 610, 0, $delta], [$credito, 0, 610, $delta]],
                        [$this->chiave($delta, $debito) => self::scelta([$this->partita($delta, 'D/45')->id => ['CMP' => 610.0]]),
                            $this->chiave($delta, $credito) => self::scelta([$this->partita($delta, '5')->id => ['CMP' => 610.0]])]));
    }

    private function chiave(Controparte $controparte, Conto $conto): string
    {
        return $controparte->id . '-' . $conto->id;
    }

    private static function scelta(array $abbinamenti, bool $acconto = false, ?int $riapre = null): array
    {
        return ['abbinamenti' => $abbinamenti, 'acconto' => $acconto, 'riapre' => $riapre];
    }

    /**
     * Partita dell'esercizio della controparte per numero del documento
     * (null = l'insoluto).
     */
    private function partita(Controparte $controparte, ?string $numero): Partita
    {
        foreach ((new PartitaModel($this->dataMapper))->getPerControparte($controparte) as $partita) {
            if (($partita->numeroDocumento === $numero) && ($partita->registrazioneContabile->esercizioContabile->id === $this->esercizio->id)) {
                return $partita;
            }
        }
        throw new \DomainException('Partita ' . $numero . ' di ' . $controparte->denominazione . ' non trovata.');
    }

    /**
     * @param array<int, array{0: Conto, 1: float, 2: float, 3: ?Controparte}> $movimenti
     */
    private function registra(CausaleContabile $causale, string $data, Controparte $controparte, string $descrizione, array $movimenti, ?array $abbinamenti = null, ?float $totale = null): RegistrazioneContabile
    {
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $this->esercizio->soggetto->studio;
        $registrazione->soggetto = $this->esercizio->soggetto;
        $registrazione->esercizioContabile = $this->esercizio;
        $registrazione->causale = $causale;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->controparte = $controparte;
        $registrazione->descrizione = $descrizione;
        $registrazione->totaleDocumento = $totale;
        $righe = [];
        foreach ($movimenti as [$conto, $dare, $avere, $controparteRiga]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conto;
            $riga->controparte = $controparteRiga;
            $riga->dare = (float) $dare;
            $riga->avere = (float) $avere;
            $righe[] = $riga;
        }
        return (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, [], $righe, null, null, $abbinamenti);
    }
}
