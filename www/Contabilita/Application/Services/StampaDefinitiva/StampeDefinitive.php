<?php

namespace Contabilita\Application\Services\StampaDefinitiva;

use Contabilita\Application\Services\Ammortamenti;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Entities\StampaDefinitiva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Models\StampaDefinitivaModel;
use Contabilita\Application\Services\SaldiContabili;
use Contabilita\Application\Services\StampaRegistroIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Stampe definitive del libro giornale e dei registri IVA:
 *
 * - il periodo di una nuova stampa parte dal giorno dopo la fine della
 *   precedente (o dall'inizio dell'esercizio per il giornale, dal 1°
 *   gennaio per i registri IVA) e non va oltre la fine dell'esercizio o
 *   dell'anno; le pagine proseguono la numerazione dell'anno;
 * - si annulla solo l'ultima stampa del libro;
 * - le registrazioni con data (giornale) o competenza IVA (registro)
 *   in un periodo stampato non si creano, modificano o eliminano più,
 *   comprese le integrazioni degli acquisti in inversione contabile
 *   riportate nel registro delle vendite.
 */
class StampeDefinitive
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Inizio del periodo della prossima stampa e data massima.
     *
     * @return array{0: SismaDate, 1: SismaDate}
     */
    public function getPeriodoSuccessivo(Soggetto $soggetto, TipoStampaDefinitiva $tipo, ?RegistroIva $registro, int $anno): array
    {
        if ($tipo !== TipoStampaDefinitiva::registroIva) {
            $esercizio = $this->getEsercizio($soggetto, $anno);
            [$inizio, $fine] = [$esercizio->dataInizio, $esercizio->dataFine];
        } else {
            [$inizio, $fine] = [new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')];
        }
        $ultima = (new StampaDefinitivaModel($this->dataMapper))->getUltima($soggetto, $tipo, $registro, $anno);
        return [$ultima === null ? $inizio : self::giornoDopo($ultima->al), $fine];
    }

    /**
     * Registra la stampa definitiva del libro fino ad $al.
     *
     * @throws \DomainException se il periodo non è stampabile
     */
    public function registra(Soggetto $soggetto, TipoStampaDefinitiva $tipo, ?RegistroIva $registro, int $anno, SismaDate $al): StampaDefinitiva
    {
        if (($tipo === TipoStampaDefinitiva::registroIva) && (($registro === null) || ($registro->soggetto->id !== $soggetto->id))) {
            throw new \DomainException('Registro IVA non valido.');
        }
        $registro = $tipo === TipoStampaDefinitiva::registroIva ? $registro : null;
        [$dal, $fine] = $this->getPeriodoSuccessivo($soggetto, $tipo, $registro, $anno);
        if ($dal->format('Y-m-d') > $fine->format('Y-m-d')) {
            throw new \DomainException('Il periodo è già stampato per intero.');
        }
        if (($al->format('Y-m-d') < $dal->format('Y-m-d')) || ($al->format('Y-m-d') > $fine->format('Y-m-d'))) {
            throw new \DomainException('La stampa va dal ' . $dal->format('d/m/Y') . ' (giorno dopo la stampa precedente) a una data entro il ' . $fine->format('d/m/Y') . '.');
        }
        if ($al->format('Y-m-d') >= date('Y-m-d')) {
            throw new \DomainException('Si stampano in definitiva solo i periodi già conclusi (fino a ieri).');
        }
        if (($tipo === TipoStampaDefinitiva::registroBeni) && ($al->format('Y-m-d') !== $fine->format('Y-m-d'))) {
            throw new \DomainException('Il registro dei beni ammortizzabili si stampa per l\'intero esercizio, fino al ' . $fine->format('d/m/Y') . '.');
        }
        $model = new StampaDefinitivaModel($this->dataMapper);
        $ultima = $model->getUltima($soggetto, $tipo, $registro, $anno);
        $stampa = new StampaDefinitiva($this->dataMapper);
        $stampa->studio = $soggetto->studio;
        $stampa->soggetto = $soggetto;
        $stampa->tipo = $tipo;
        $stampa->anno = $anno;
        $stampa->esercizioContabile = $tipo === TipoStampaDefinitiva::registroIva ? null : $this->getEsercizio($soggetto, $anno);
        $stampa->registroIva = $registro;
        $stampa->dal = $dal;
        $stampa->al = $al;
        $stampa->primaPagina = $ultima === null ? 1 : $ultima->ultimaPagina + 1;
        $stampa->ultimaPagina = $stampa->primaPagina + max(1, count($this->getPagine($stampa))) - 1;
        $this->dataMapper->save($stampa);
        return $stampa;
    }

    /**
     * @throws \DomainException se non è l'ultima stampa del libro
     */
    public function annulla(StampaDefinitiva $stampa): void
    {
        $ultima = (new StampaDefinitivaModel($this->dataMapper))->getUltima($stampa->soggetto, $stampa->tipo, $stampa->registroIva, $stampa->anno);
        if ($ultima?->id !== $stampa->id) {
            throw new \DomainException('Si annulla solo l\'ultima stampa definitiva del libro.');
        }
        $this->dataMapper->delete($stampa);
    }

    /**
     * Pagine della stampa (anche non ancora registrata, per l'anteprima).
     *
     * @return array<int, array{numero: int, parti: array<int, array<string, mixed>>}>
     */
    public function getPagine(StampaDefinitiva $stampa): array
    {
        $blocchi = match ($stampa->tipo) {
            TipoStampaDefinitiva::giornale => $this->bloccoGiornale($stampa),
            TipoStampaDefinitiva::registroIva => $this->blocchiRegistro($stampa),
            TipoStampaDefinitiva::registroBeni => $this->bloccoBeni($stampa),
        };
        return Impaginazione::impagina($blocchi, $stampa->righePerPagina, $stampa->primaPagina);
    }

    /**
     * Motivo per cui la registrazione (con i valori attuali) non si può
     * creare, modificare o eliminare; null se è libera.
     *
     * @param RigaIva[]|null $righeIva righe IVA (lette dal database se null)
     */
    public function getBlocco(RegistrazioneContabile $registrazione, ?array $righeIva = null): ?string
    {
        $model = new StampaDefinitivaModel($this->dataMapper);
        $soggetto = $registrazione->soggetto;
        $esercizio = $registrazione->esercizioContabile;
        $giornale = $model->getUltima($soggetto, TipoStampaDefinitiva::giornale, null, $esercizio->anno);
        $data = $registrazione->dataRegistrazione->format('Y-m-d');
        if (($giornale !== null) && ($giornale->al->format('Y-m-d') >= $data)) {
            return 'Il libro giornale è stampato in definitiva fino al ' . $giornale->al->format('d/m/Y') . ': le registrazioni fino a quella data non si modificano.';
        }
        if (($registrazione->registroIva === null) || ($registrazione->dataCompetenzaIva === null) || ($registrazione->causale->isIva() === false)) {
            return null;
        }
        $competenza = $registrazione->dataCompetenzaIva;
        $anno = (int) $competenza->format('Y');
        $registri = [$registrazione->registroIva];
        if ($registrazione->registroIva->tipo === TipoRegistroIva::acquisti) {
            $righeIva ??= isset($registrazione->id) ? iterator_to_array((new RigaIvaModel($this->dataMapper))->getRighe($registrazione), false) : [];
            if (count(array_filter($righeIva, fn(RigaIva $riga) => $riga->codiceIva->inversioneContabile)) > 0) {
                foreach ((new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, TipoRegistroIva::vendite) as $vendite) {
                    if ($vendite->sezionale === '') {
                        $registri[] = $vendite;
                    }
                }
            }
        }
        foreach ($registri as $registro) {
            $stampa = $model->getUltima($soggetto, TipoStampaDefinitiva::registroIva, $registro, $anno);
            if (($stampa !== null) && ($stampa->al->format('Y-m-d') >= $competenza->format('Y-m-d'))) {
                return 'Il registro ' . $registro->getEtichetta() . ' è stampato in definitiva fino al ' . $stampa->al->format('d/m/Y') . ': i documenti con competenza fino a quella data non si modificano.';
            }
        }
        return null;
    }

    private function getEsercizio(Soggetto $soggetto, int $anno): EsercizioContabile
    {
        return (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno)
                ?? throw new \DomainException('Esercizio ' . $anno . ' non aperto.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloccoGiornale(StampaDefinitiva $stampa): array
    {
        $saldi = new SaldiContabili($this->dataMapper);
        $esercizio = $stampa->esercizioContabile ?? $this->getEsercizio($stampa->soggetto, $stampa->anno);
        $riporto = [4 => 0.0, 5 => 0.0];
        if ($stampa->dal->format('Y-m-d') > $esercizio->dataInizio->format('Y-m-d')) {
            $precedente = $saldi->giornale($stampa->soggetto, $esercizio->dataInizio, self::giornoPrima($stampa->dal));
            $riporto = [4 => $precedente['dare'], 5 => $precedente['avere']];
        }
        $giornale = $saldi->giornale($stampa->soggetto, $stampa->dal, $stampa->al);
        $righe = [];
        foreach ($giornale['registrazioni'] as ['registrazione' => $registrazione, 'righe' => $righeRegistrazione]) {
            foreach ($righeRegistrazione as $indice => $riga) {
                $prima = $indice === 0;
                $descrizione = $prima ? $registrazione->getDescrizioneCompleta() . ($registrazione->controparte === null ? '' : ' - ' . $registrazione->controparte->denominazione) : (string) $riga->descrizione;
                $righe[] = ['celle' => [$prima ? (string) $registrazione->numero : '', $prima ? $registrazione->dataRegistrazione->format('d/m/Y') : '', $descrizione,
                        $riga->conto->getEtichetta(), $riga->dare == 0 ? null : $riga->dare, $riga->avere == 0 ? null : $riga->avere], 'classe' => $prima ? 'inizio-registrazione' : null];
            }
        }
        if (count($righe) === 0) {
            $righe[] = ['celle' => ['', '', 'Nessuna registrazione nel periodo.', '', null, null]];
        }
        $righe[] = ['celle' => ['', '', 'Totale progressivo dell\'esercizio', '', round($riporto[4] + $giornale['dare'], 2), round($riporto[5] + $giornale['avere'], 2)], 'classe' => 'totale', 'senzaRiporto' => true];
        return [['titolo' => null, 'riporto' => $riporto, 'righe' => $righe, 'colonne' => [
                    ['etichetta' => 'N.', 'numero' => false, 'larghezza' => 5], ['etichetta' => 'Data', 'numero' => false, 'larghezza' => 8],
                    ['etichetta' => 'Descrizione', 'numero' => false, 'larghezza' => 37], ['etichetta' => 'Conto', 'numero' => false, 'larghezza' => 28],
                    ['etichetta' => 'Dare', 'numero' => true, 'larghezza' => 11], ['etichetta' => 'Avere', 'numero' => true, 'larghezza' => 11]]]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocchiRegistro(StampaDefinitiva $stampa): array
    {
        $registro = $stampa->registroIva;
        $dati = (new StampaRegistroIva($this->dataMapper))->dati($registro, $stampa->dal, $stampa->al);
        $acquisti = $registro->tipo === TipoRegistroIva::acquisti;
        $colonne = [['etichetta' => 'Prot.', 'numero' => false, 'larghezza' => 5], ['etichetta' => 'Registrazione', 'numero' => false, 'larghezza' => 11],
            ['etichetta' => 'Documento', 'numero' => false, 'larghezza' => 17], ['etichetta' => 'Competenza', 'numero' => false, 'larghezza' => 7],
            ['etichetta' => 'Controparte', 'numero' => false, 'larghezza' => $acquisti ? 18 : 24], ['etichetta' => 'Codice IVA', 'numero' => false, 'larghezza' => 12],
            ['etichetta' => 'Imponibile', 'numero' => true, 'larghezza' => 9], ['etichetta' => 'Imposta', 'numero' => true, 'larghezza' => 8]];
        if ($acquisti) {
            $colonne[] = ['etichetta' => 'Detraibile', 'numero' => true, 'larghezza' => 6];
        }
        $colonne[] = ['etichetta' => 'Totale', 'numero' => true, 'larghezza' => 9];
        $righe = [];
        foreach ($dati['documenti'] as ['registrazione' => $registrazione, 'righe' => $righeDocumento]) {
            foreach ($righeDocumento as $indice => $riga) {
                $prima = $indice === 0;
                $celle = [$prima ? (string) $registrazione->protocolloIva : '', $prima ? $registrazione->dataRegistrazione->format('d/m/Y') . ' n. ' . $registrazione->numero : '',
                    $prima ? $registrazione->causale->getLabel() . ($registrazione->numeroDocumento === null ? '' : ' n. ' . $registrazione->numeroDocumento) . ($registrazione->dataDocumento === null ? '' : ' del ' . $registrazione->dataDocumento->format('d/m/Y')) : '',
                    $prima ? $registrazione->dataCompetenzaIva->format('d/m/Y') : '',
                    $prima ? trim(($registrazione->controparte?->denominazione ?? '') . ' ' . ($registrazione->controparte?->partitaIva ?? $registrazione->controparte?->codiceFiscale ?? '')) : '',
                    $riga->codiceIva->getEtichetta() . ($riga->rivendita ? ($riga->altriProdotti ? ' (rivendita, altri prodotti)' : ' (rivendita)') : ''), $riga->imponibile, $riga->imposta];
                if ($acquisti) {
                    $celle[] = $riga->getImpostaDetraibile();
                }
                $celle[] = $prima ? round((float) $registrazione->totaleDocumento * $registrazione->causale->getSegno(), 2) : null;
                $righe[] = ['celle' => $celle, 'classe' => $prima ? 'inizio-registrazione' : null];
            }
        }
        if (count($righe) === 0) {
            $righe[] = ['celle' => array_merge(['', '', 'Nessun documento nel periodo.'], array_fill(0, count($colonne) - 3, null))];
        }
        $blocchi = [['titolo' => null, 'colonne' => $colonne, 'righe' => $righe]];
        foreach ($dati['documenti'] as ['registrazione' => $registrazione, 'giornalieri' => $giornalieri]) {
            if (count($giornalieri) === 0) {
                continue;
            }
            $codici = array_unique(array_merge(...array_map('array_keys', array_values($giornalieri))));
            sort($codici);
            $righeGiorni = [];
            foreach ($giornalieri as $giorno => $perCodice) {
                $importi = array_map(fn(string $codice) => isset($perCodice[$codice]) ? $perCodice[$codice]->importo : null, $codici);
                $righeGiorni[] = ['celle' => array_merge([(new \DateTimeImmutable($giorno))->format('d/m/Y')], $importi, [round(array_sum($importi), 2)])];
            }
            $larghezza = (int) floor(80 / (count($codici) + 1));
            $blocchi[] = ['titolo' => 'Corrispettivi giornalieri (comprensivi dell\'imposta) - registrazione n. ' . $registrazione->numero . ', protocollo ' . $registrazione->protocolloIva,
                'colonne' => array_merge([['etichetta' => 'Giorno', 'numero' => false, 'larghezza' => 20]], array_map(fn(string $codice) => ['etichetta' => $codice, 'numero' => true, 'larghezza' => $larghezza], $codici),
                        [['etichetta' => 'Totale', 'numero' => true, 'larghezza' => $larghezza]]), 'righe' => $righeGiorni];
        }
        $righeTotali = [];
        $somme = ['imponibile' => 0.0, 'imposta' => 0.0, 'detraibile' => 0.0];
        foreach ($dati['totali'] as $voce) {
            $righeTotali[] = ['celle' => array_merge([$voce['codice'], $voce['imponibile'], $voce['imposta']], $acquisti ? [$voce['detraibile'], $voce['rivendita'] == 0 ? null : $voce['rivendita']] : [])];
            foreach ($somme as $campo => $valore) {
                $somme[$campo] = round($valore + $voce[$campo], 2);
            }
        }
        $righeTotali[] = ['celle' => array_merge(['Totale', $somme['imponibile'], $somme['imposta']], $acquisti ? [$somme['detraibile'], round(array_sum(array_column($dati['totali'], 'rivendita')), 2)] : []), 'classe' => 'totale'];
        $blocchi[] = ['titolo' => 'Totali per codice IVA', 'righe' => $righeTotali, 'colonne' => array_merge([['etichetta' => 'Codice IVA', 'numero' => false, 'larghezza' => 40],
                ['etichetta' => 'Imponibile', 'numero' => true, 'larghezza' => 15], ['etichetta' => 'Imposta', 'numero' => true, 'larghezza' => 15]],
                    $acquisti ? [['etichetta' => 'Detraibile', 'numero' => true, 'larghezza' => 15], ['etichetta' => 'Per rivendita (IVA compresa)', 'numero' => true, 'larghezza' => 15]] : [])];
        if (count($dati['integrazioni']) > 0) {
            $righeIntegrazioni = [];
            foreach ($dati['integrazioni'] as $riga) {
                $registrazione = $riga->registrazioneContabile;
                $righeIntegrazioni[] = ['celle' => [$registrazione->dataRegistrazione->format('d/m/Y') . ' n. ' . $registrazione->numero . ' (prot. acquisti ' . $registrazione->protocolloIva . ')',
                        trim(($registrazione->numeroDocumento ?? '') . ' ' . ($registrazione->dataDocumento?->format('d/m/Y') ?? '')), $registrazione->controparte?->denominazione ?? '',
                        $riga->codiceIva->getEtichetta(), $riga->imponibile, $riga->imposta]];
            }
            $righeIntegrazioni[] = ['celle' => ['Totale', '', '', '', $dati['totaliIntegrazioni']['imponibile'], $dati['totaliIntegrazioni']['imposta']], 'classe' => 'totale'];
            $blocchi[] = ['titolo' => 'Integrazioni dell\'imposta sugli acquisti (inversione contabile, intracomunitari, esteri)', 'righe' => $righeIntegrazioni, 'colonne' => [
                    ['etichetta' => 'Registrazione', 'numero' => false, 'larghezza' => 24], ['etichetta' => 'Documento', 'numero' => false, 'larghezza' => 16],
                    ['etichetta' => 'Fornitore', 'numero' => false, 'larghezza' => 26], ['etichetta' => 'Codice IVA', 'numero' => false, 'larghezza' => 14],
                    ['etichetta' => 'Imponibile', 'numero' => true, 'larghezza' => 10], ['etichetta' => 'Imposta', 'numero' => true, 'larghezza' => 10]]];
        }
        return $blocchi;
    }

    /**
     * Registro dei beni ammortizzabili dell'esercizio: per bene costo,
     * coefficiente, fondo iniziale, quota, fondo finale, residuo e
     * dismissione.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bloccoBeni(StampaDefinitiva $stampa): array
    {
        $esercizio = $stampa->esercizioContabile ?? $this->getEsercizio($stampa->soggetto, $stampa->anno);
        $righe = [];
        $totali = [4 => 0.0, 6 => 0.0, 7 => 0.0, 8 => 0.0, 9 => 0.0];
        foreach ((new Ammortamenti($this->dataMapper))->calcola($stampa->soggetto, $esercizio) as $voce) {
            $cespite = $voce['cespite'];
            $celle = [$cespite->codice, $cespite->descrizione, $cespite->contoCespite->getEtichetta(), $cespite->dataAcquisto->format('d/m/Y'), $cespite->costo,
                number_format($cespite->percentuale, 2, ',', '.') . ($voce['primo'] && $cespite->riduzionePrimoEsercizio && ($cespite->deduzioneIntegrale === false) ? ' (1/2)' : ''),
                $voce['fondoInizio'], $voce['quota'], $voce['fondoFine'], $voce['residuo'],
                $voce['dismesso'] ? 'Dismesso il ' . $cespite->dataDismissione->format('d/m/Y') . ($voce['plusvalenza'] >= 0 ? ', plusvalenza ' : ', minusvalenza ') . number_format(abs($voce['plusvalenza']), 2, ',', '.') : ''];
            foreach (array_keys($totali) as $indice) {
                $totali[$indice] = round($totali[$indice] + (float) $celle[$indice], 2);
            }
            $righe[] = ['celle' => $celle];
        }
        if (count($righe) === 0) {
            $righe[] = ['celle' => ['', 'Nessun bene ammortizzabile nell\'esercizio.', '', '', null, '', null, null, null, null, '']];
        }
        $righe[] = ['celle' => ['', 'Totale', '', '', $totali[4], '', $totali[6], $totali[7], $totali[8], $totali[9], ''], 'classe' => 'totale'];
        return [['titolo' => null, 'righe' => $righe, 'colonne' => [
                    ['etichetta' => 'Codice', 'numero' => false, 'larghezza' => 5], ['etichetta' => 'Descrizione', 'numero' => false, 'larghezza' => 18],
                    ['etichetta' => 'Conto', 'numero' => false, 'larghezza' => 16], ['etichetta' => 'Acquisto', 'numero' => false, 'larghezza' => 7],
                    ['etichetta' => 'Costo', 'numero' => true, 'larghezza' => 8], ['etichetta' => 'Coeff.', 'numero' => false, 'larghezza' => 5],
                    ['etichetta' => 'Fondo iniziale', 'numero' => true, 'larghezza' => 8], ['etichetta' => 'Quota', 'numero' => true, 'larghezza' => 7],
                    ['etichetta' => 'Fondo finale', 'numero' => true, 'larghezza' => 8], ['etichetta' => 'Residuo', 'numero' => true, 'larghezza' => 8],
                    ['etichetta' => 'Note', 'numero' => false, 'larghezza' => 10]]]];
    }

    private static function giornoDopo(SismaDate $data): SismaDate
    {
        return new SismaDate((new \DateTimeImmutable($data->format('Y-m-d')))->modify('+1 day')->format('Y-m-d'));
    }

    private static function giornoPrima(SismaDate $data): SismaDate
    {
        return new SismaDate((new \DateTimeImmutable($data->format('Y-m-d')))->modify('-1 day')->format('Y-m-d'));
    }
}
