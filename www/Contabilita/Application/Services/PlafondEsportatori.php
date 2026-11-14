<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Plafond degli esportatori abituali (art. 8, c. 1, lett. c, e c. 2,
 * D.P.R. 633/1972, art. 1 D.L. 746/1983 e art. 2, c. 2, L. 28/1997; dal 2027
 * artt. 45, c. 1, lett. d, 46 e 47 D.Lgs. 10/2026): acquisti e importazioni senza IVA con la dichiarazione
 * d'intento (natura N3.5) entro l'ammontare delle esportazioni e operazioni
 * assimilate (N3.1-N3.4) registrate nell'anno solare precedente (metodo
 * solare) o nei dodici mesi precedenti (metodo mensile), al netto degli
 * acquisti con plafond degli stessi mesi.
 *
 * Dati per mese dai registri IVA: utilizzi dalla data del documento di
 * acquisto (momento di effettuazione), separando le importazioni; volume
 * d'affari ed esportazioni dalla data di competenza delle vendite. Lo
 * status di esportatore abituale richiede che le operazioni dell'anno
 * precedente superino il 10% del volume d'affari. Il metodo (casella 3 del
 * VC14 per il mensile) si sceglie sull'esercizio contabile dell'anno.
 *
 * Gli stessi dati compongono il quadro VC della dichiarazione IVA
 * (modulo Dichiarativi).
 */
class PlafondEsportatori
{

    /** Operazioni che formano il plafond (esportazioni e assimilate, cessioni intracomunitarie, San Marino) */
    public const NATURE_PLAFOND = ['N3.1', 'N3.2', 'N3.3', 'N3.4'];
    public const NATURA_UTILIZZO = 'N3.5';
    public const QUOTA_STATUS = 0.10;
    private const MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{acquisti: array<int, float>, importazioni: array<int, float>, volume: array<int, float>, esportazioni: array<int, float>}
     */
    public function getDati(Soggetto $soggetto, int $anno): array
    {
        $dati = ['acquisti' => array_fill(1, 12, 0.0), 'importazioni' => array_fill(1, 12, 0.0), 'volume' => array_fill(1, 12, 0.0), 'esportazioni' => array_fill(1, 12, 0.0)];
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            $natura = $riga->codiceIva->natura?->value;
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                if ($natura === self::NATURA_UTILIZZO) {
                    $data = $riga->registrazioneContabile->dataDocumento ?? $riga->dataCompetenzaIva;
                    if ((int) $data->format('Y') === $anno) {
                        $dati[$riga->registrazioneContabile->importazione ? 'importazioni' : 'acquisti'][(int) $data->format('n')] += $riga->imponibile;
                    }
                }
                continue;
            }
            $mese = (int) $riga->dataCompetenzaIva->format('n');
            // volume d'affari senza le operazioni dell'art. 21, c. 6-bis (non soggette), le escluse e le cessioni di beni ammortizzabili
            if ((($natura === null) || (in_array($natura, ['N1', 'N2.1', 'N2.2', 'N7'], true) === false)) && ($riga->qualifica !== QualificaOperazione::beneAmmortizzabile)) {
                $dati['volume'][$mese] += $riga->imponibile;
            }
            if (in_array($natura, self::NATURE_PLAFOND, true)) {
                $dati['esportazioni'][$mese] += $riga->imponibile;
            }
        }
        return $dati;
    }

    /**
     * L'azienda ha scelto il metodo mensile per l'anno (esercizio contabile).
     */
    public function isMensile(Soggetto $soggetto, int $anno): bool
    {
        return (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno)?->plafondMensile ?? false;
    }

    /**
     * Plafond disponibile al 1° gennaio: operazioni dell'anno precedente
     * (solare) o dei dodici mesi precedenti al netto degli utilizzi (mensile).
     */
    public static function getPlafondIniziale(array $precedente, bool $mensile): float
    {
        $esportazioni = array_sum($precedente['esportazioni']);
        return max(0.0, round($mensile ? $esportazioni - array_sum($precedente['acquisti']) - array_sum($precedente['importazioni']) : $esportazioni, 2));
    }

    /**
     * Status di esportatore abituale dall'anno precedente: null senza
     * volume d'affari registrato.
     */
    public static function isEsportatoreAbituale(array $precedente): ?bool
    {
        $volume = array_sum($precedente['volume']);
        return $volume > 0 ? array_sum($precedente['esportazioni']) > $volume * self::QUOTA_STATUS : null;
    }

    /**
     * Plafond mese per mese: disponibile all'inizio del mese, utilizzato nel
     * mese, residuo e superamento.
     *
     * @return array<int, array{disponibile: float, utilizzato: float, residuo: float, superato: bool}>
     */
    public static function prospetto(array $corrente, array $precedente, bool $mensile, float $plafondAnnuale): array
    {
        $prospetto = [];
        $utilizzatoAnno = 0.0;
        for ($mese = 1; $mese <= 12; $mese++) {
            $utilizzoMese = round($corrente['acquisti'][$mese] + $corrente['importazioni'][$mese], 2);
            if ($mensile) {
                $disponibile = 0.0;
                for ($indietro = 1; $indietro <= 12; $indietro++) {
                    $riferimento = $mese - $indietro;
                    $dati = $riferimento >= 1 ? $corrente : $precedente;
                    $indice = $riferimento >= 1 ? $riferimento : $riferimento + 12;
                    $disponibile += $dati['esportazioni'][$indice] - $dati['acquisti'][$indice] - $dati['importazioni'][$indice];
                }
            } else {
                $disponibile = $plafondAnnuale - $utilizzatoAnno;
            }
            $disponibile = round(max(0.0, $disponibile), 2);
            $prospetto[$mese] = ['disponibile' => $disponibile, 'utilizzato' => $utilizzoMese, 'residuo' => round($disponibile - $utilizzoMese, 2),
                'superato' => ($utilizzoMese > 0) && ($utilizzoMese > $disponibile + 0.005)];
            $utilizzatoAnno += $utilizzoMese;
        }
        return $prospetto;
    }

    /**
     * Primo superamento del plafond fino al mese compreso: messaggio o null.
     */
    public static function verifica(array $prospetto, int $finoAlMese = 12): ?string
    {
        foreach ($prospetto as $mese => $voce) {
            if ($mese > $finoAlMese) {
                break;
            }
            if ($voce['superato']) {
                return sprintf('Plafond superato a %s (utilizzati € %s, disponibili € %s): regolarizzare gli acquisti oltre il limite (circolare n. 50/E del 2002, risoluzione n. 16/E del 2017).',
                        self::MESI[$mese - 1], number_format($voce['utilizzato'], 0, ',', '.'), number_format($voce['disponibile'], 0, ',', '.'));
            }
        }
        return null;
    }

    /**
     * Prospetto dell'anno dell'azienda con il metodo dell'esercizio.
     *
     * @return array{mensile: bool, iniziale: float, status: ?bool, mesi: array<int, array{disponibile: float, utilizzato: float, residuo: float, superato: bool}>, utilizzi: bool}
     */
    public function riepiloga(Soggetto $soggetto, int $anno): array
    {
        $corrente = $this->getDati($soggetto, $anno);
        $precedente = $this->getDati($soggetto, $anno - 1);
        $mensile = $this->isMensile($soggetto, $anno);
        $iniziale = self::getPlafondIniziale($precedente, $mensile);
        return ['mensile' => $mensile, 'iniziale' => $iniziale, 'status' => self::isEsportatoreAbituale($precedente),
            'mesi' => self::prospetto($corrente, $precedente, $mensile, $iniziale),
            'utilizzi' => (array_sum($corrente['acquisti']) + array_sum($corrente['importazioni'])) > 0];
    }
}
