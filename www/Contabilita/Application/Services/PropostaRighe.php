<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta delle righe in partita doppia dalla causale, dalla controparte
 * e dalle righe IVA (o dal totale per incassi, pagamenti e versamenti):
 *
 * - vendite: cliente in dare per il totale, ricavi in avere per
 *   l'imponibile, IVA sulle vendite in avere; con la scissione dei
 *   pagamenti l'IVA è stornata dal cliente (non incassata né dovuta);
 * - corrispettivi: cassa in dare, ricavi e IVA in avere;
 * - acquisti: fornitore in avere per il totale, costi in dare per
 *   l'imponibile e l'IVA indetraibile, IVA sugli acquisti in dare per la
 *   parte detraibile; con l'inversione contabile il fornitore è in avere
 *   per il solo imponibile e l'imposta integrata va anche in avere
 *   sull'IVA delle vendite;
 * - incasso e pagamento: banca contro cliente o fornitore; versamento
 *   IVA: Erario c/IVA contro banca.
 *
 * Le note di credito hanno imponibile e imposta negativi: le righe
 * risultano nella sezione opposta. La contropartita è quella della riga
 * IVA, altrimenti quella abituale della controparte, altrimenti il conto
 * con funzione ricavi, corrispettivi o costi del piano.
 */
class PropostaRighe
{

    /** @var array<string, RigaContabile> */
    private array $righe = [];
    private ContoModel $contoModel;
    private PianoConti $piano;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {
        $this->contoModel = new ContoModel($this->dataMapper);
    }

    /**
     * @param RigaIva[] $righeIva
     * @return RigaContabile[] righe non salvate, senza registrazione
     * @throws \DomainException se manca un conto necessario (funzione non assegnata nel piano)
     */
    public function proponi(PianoConti $piano, RegistrazioneContabile $registrazione, array $righeIva): array
    {
        $this->piano = $piano;
        $this->righe = [];
        $controparte = $registrazione->controparte;
        $partitario = new Partitario($this->dataMapper);
        $causale = $registrazione->causale;
        switch ($causale) {
            case CausaleContabile::fatturaVendita:
            case CausaleContabile::notaCreditoEmessa:
                $contoCliente = $partitario->getConto($this->richiediControparte($controparte), true);
                $this->aggiungi($contoCliente, self::totale($righeIva), $controparte);
                $this->aggiungiImponibili($righeIva, $controparte?->contoContropartita, FunzioneConto::ricavi, false);
                $iva = self::somma($righeIva, fn(RigaIva $riga) => $riga->imposta);
                $this->aggiungi($this->conto(FunzioneConto::ivaVendite), -$iva);
                if ($registrazione->esigibilita === EsigibilitaIva::scissionePagamenti) {
                    $this->aggiungi($this->conto(FunzioneConto::ivaVendite), $iva);
                    $this->aggiungi($contoCliente, -$iva, $controparte);
                }
                break;
            case CausaleContabile::corrispettivi:
                $this->aggiungi($this->conto(FunzioneConto::cassa), self::totale($righeIva));
                $this->aggiungiImponibili($righeIva, null, $this->contoModel->getPerFunzione($piano, FunzioneConto::corrispettivi) === null ? FunzioneConto::ricavi : FunzioneConto::corrispettivi, false);
                $this->aggiungi($this->conto(FunzioneConto::ivaVendite), -self::somma($righeIva, fn(RigaIva $riga) => $riga->imposta));
                break;
            case CausaleContabile::fatturaAcquisto:
            case CausaleContabile::notaCreditoRicevuta:
                $contoFornitore = $partitario->getConto($this->richiediControparte($controparte), false);
                $debito = self::totaleDocumento($righeIva);
                $this->aggiungi($contoFornitore, -$debito, $controparte);
                $this->aggiungiImponibili($righeIva, $controparte?->contoContropartita, FunzioneConto::costi, true);
                $this->aggiungi($this->conto(FunzioneConto::ivaAcquisti), self::somma($righeIva, fn(RigaIva $riga) => $riga->getImpostaDetraibile()));
                $integrata = self::somma($righeIva, fn(RigaIva $riga) => $riga->codiceIva->inversioneContabile ? $riga->imposta : 0);
                if ($integrata != 0) {
                    $this->aggiungi($this->conto(FunzioneConto::ivaVendite), -$integrata);
                }
                break;
            case CausaleContabile::incasso:
                $importo = (float) $registrazione->totaleDocumento;
                $this->aggiungi($this->conto(FunzioneConto::banca), $importo);
                $this->aggiungi($partitario->getConto($this->richiediControparte($controparte), true), -$importo, $controparte);
                break;
            case CausaleContabile::pagamento:
                $importo = (float) $registrazione->totaleDocumento;
                $this->aggiungi($partitario->getConto($this->richiediControparte($controparte), false), $importo, $controparte);
                $this->aggiungi($this->conto(FunzioneConto::banca), -$importo);
                break;
            case CausaleContabile::versamentoIva:
                $importo = (float) $registrazione->totaleDocumento;
                $this->aggiungi($this->conto(FunzioneConto::erarioIva), $importo);
                $this->aggiungi($this->conto(FunzioneConto::banca), -$importo);
                break;
            default:
                break;
        }
        return array_values(array_filter($this->righe, fn(RigaContabile $riga) => ($riga->dare != 0) || ($riga->avere != 0)));
    }

    /**
     * @param RigaIva[] $righeIva
     */
    private function aggiungiImponibili(array $righeIva, ?Conto $abituale, FunzioneConto $funzione, bool $acquisto): void
    {
        foreach ($righeIva as $riga) {
            // il conto abituale resta sulla riga: dice se l'acquisto è un bene ammortizzabile
            $riga->conto ??= $abituale;
            $conto = $riga->conto ?? $this->conto($funzione);
            $importo = $acquisto ? $riga->imponibile + $riga->getImpostaIndetraibile() : -$riga->imponibile;
            $this->aggiungi($conto, $importo);
        }
    }

    private function richiediControparte(?Controparte $controparte): Controparte
    {
        return $controparte ?? throw new \DomainException('Indicare il cliente o il fornitore.');
    }

    private function conto(FunzioneConto $funzione): Conto
    {
        return $this->contoModel->getPerFunzione($this->piano, $funzione) ?? throw new \DomainException('Nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".');
    }

    /**
     * Aggiunge l'importo al conto: positivo in dare, negativo in avere;
     * gli importi sullo stesso conto e nella stessa sezione si sommano.
     */
    private function aggiungi(Conto $conto, float $importo, ?Controparte $controparte = null): void
    {
        $importo = round($importo, 2);
        if ($importo == 0) {
            return;
        }
        $chiave = $conto->codice . '|' . ($importo > 0 ? 'D' : 'A');
        if (isset($this->righe[$chiave]) === false) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conto;
            $riga->controparte = $controparte;
            $riga->ordine = count($this->righe) + 1;
            $this->righe[$chiave] = $riga;
        }
        if ($importo > 0) {
            $this->righe[$chiave]->dare = round($this->righe[$chiave]->dare + $importo, 2);
        } else {
            $this->righe[$chiave]->avere = round($this->righe[$chiave]->avere - $importo, 2);
        }
    }

    /**
     * @param RigaIva[] $righeIva
     */
    public static function totale(array $righeIva): float
    {
        return self::somma($righeIva, fn(RigaIva $riga) => $riga->imponibile + $riga->imposta);
    }

    /**
     * Totale del documento: imponibile e imposta, esclusa l'imposta
     * integrata dal cessionario (inversione contabile), che non è nel
     * documento del fornitore.
     *
     * @param RigaIva[] $righeIva
     */
    public static function totaleDocumento(array $righeIva): float
    {
        return self::somma($righeIva, fn(RigaIva $riga) => $riga->imponibile + ($riga->codiceIva->inversioneContabile ? 0 : $riga->imposta));
    }

    /**
     * @param RigaIva[] $righeIva
     */
    private static function somma(array $righeIva, callable $valore): float
    {
        $totale = 0.0;
        foreach ($righeIva as $riga) {
            $totale += $valore($riga);
        }
        return round($totale, 2);
    }
}
