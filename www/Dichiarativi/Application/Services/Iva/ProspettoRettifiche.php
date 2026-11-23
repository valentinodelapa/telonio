<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Attivita;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Services\AttivitaSeparate;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Prospetto D delle istruzioni IVA: i cinque righi delle rettifiche della
 * detrazione (art. 19-bis2 e art. 19, c. 1), il cui totale va nel rigo VF70.
 *
 * Il passaggio tra il regime ordinario e un regime speciale agricolo comporta
 * la rettifica dell'art. 19-bis2, c. 3, abrogato dal 1° gennaio 2027 (art. 9
 * D.Lgs. 186/2025 e art. 4, c. 12-octies, D.L. 200/2025, salve le rettifiche in
 * corso e i comportamenti precedenti al 1° marzo 2026). Va nel rigo 3, come nelle
 * istruzioni fino al modello 2025: il modello 2026 lo riserva al forfetario perché
 * è precedente alla proroga. La suite propone i beni ammortizzabili dai registri,
 * in quinti (in decimi per i fabbricati) degli anni mancanti, e le rimanenze dei
 * beni non ancora ceduti o utilizzati: il valore è il saldo dei conti delle
 * rimanenze (voci C.I.1-4 dell'attivo) alla fine dell'anno precedente, l'imposta
 * è una stima con l'aliquota media degli acquisti di beni (voce B.6 del conto
 * economico) dello stesso anno, perché la contabilità non lega le rimanenze alle
 * fatture d'acquisto. Valore e aliquota si correggono a mano e restano salvati
 * sul rigo 3; i servizi non utilizzati si aggiungono a mano. Con le attività
 * separate contano solo le righe dell'attività del modulo. Non si considerano
 * ammortizzabili i beni di
 * costo unitario fino a 516,46 euro e quelli con coefficiente di ammortamento oltre
 * il 25% (c. 5): la suite non conosce il coefficiente e li segna l'operatore sulla
 * proposta, restano in elenco ma fuori dal totale.
 */
class ProspettoRettifiche
{

    public const RIGHI = [
        1 => 'Rettifica per variazioni dell\'utilizzo di beni non ammortizzabili (c. 1)',
        2 => 'Rettifica per variazione dell\'utilizzo di beni ammortizzabili (c. 2)',
        3 => 'Rettifica per regime forfetario e mutamenti di regime (c. 3)',
        4 => 'Rettifica per variazione del pro-rata (c. 4)',
        5 => 'Variazione della detrazione relativa ad acquisti dell\'anno precedente (art. 19, c. 1)',
    ];

    /** Ultimo anno in cui il passaggio di regime comporta la rettifica */
    public const ULTIMO_ANNO_RETTIFICA = 2026;
    public const PERIODO = 5;
    public const PERIODO_FABBRICATI = 10;
    public const COSTO_MINIMO = 516.46;
    public const VOCI_RIMANENZE = [VoceCee::attivoCI1, VoceCee::attivoCI2, VoceCee::attivoCI3, VoceCee::attivoCI4];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Parte da rettificare dell'imposta di un bene: gli anni mancanti al
     * compimento del periodo, a partire da quello di entrata in funzione.
     */
    public static function calcolaBene(float $imposta, int $annoAcquisto, int $anno, int $periodo = self::PERIODO): float
    {
        $mancanti = $periodo - ($anno - $annoAcquisto);
        return ($anno <= $annoAcquisto) || ($mancanti <= 0) ? 0.0 : round($imposta * $mancanti / $periodo, 2);
    }

    /**
     * Proposta per i beni ammortizzabili e le rimanenze al cambio di regime:
     * con la detrazione che passa da ordinaria a forfetaria si restituisce
     * (segno meno), nel passaggio contrario si recupera. Tra due regimi
     * speciali gli acquisti non si detraggono mai e non c'è nulla da
     * rettificare. Valore e aliquota delle rimanenze indicati a mano
     * prevalgono sulla proposta.
     *
     * @return array{totale: int, beni: array<int, array{riga: RigaIva, descrizione: string, anno: int, imposta: float, importo: float, escluso: bool}>, rimanenze: ?array{segno: int, valoreProposto: float, aliquotaProposta: ?float, valore: float, aliquota: ?float, imposta: float, importo: float}}
     */
    public function getProposta(Soggetto $soggetto, int $anno, ?Attivita $attivita = null, ?float $valoreRimanenze = null, ?float $aliquotaRimanenze = null): array
    {
        $proposta = ['totale' => 0, 'beni' => [], 'rimanenze' => null];
        $separate = new AttivitaSeparate($this->dataMapper);
        $ora = $separate->getRegimeDetrazione($soggetto, $anno, $attivita);
        $prima = $separate->getRegimeDetrazione($soggetto, $anno - 1, $attivita);
        if (($anno > self::ULTIMO_ANNO_RETTIFICA) || (($ora === null) === ($prima === null))) {
            return $proposta;
        }
        $segno = $prima === null ? -1 : 1;
        $righe = (new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate(($anno - self::PERIODO_FABBRICATI + 1) . '-01-01'), new SismaDate(($anno - 1) . '-12-31'));
        foreach ($righe as $riga) {
            if (self::isRilevante($riga, $attivita) === false) {
                continue;
            }
            $annoAcquisto = (int) $riga->dataCompetenzaIva->format('Y');
            if ((($separate->getRegimeDetrazione($soggetto, $annoAcquisto, $attivita) === null) === ($prima === null)) === false) {
                continue;
            }
            $periodo = $riga->conto?->voceCee === VoceCee::attivoBII1 ? self::PERIODO_FABBRICATI : self::PERIODO;
            $imposta = $prima === null ? $riga->getImpostaDetraibile() : round($riga->imposta * $riga->detraibilita / 100, 2);
            $importo = self::calcolaBene($imposta, $annoAcquisto, $anno, $periodo);
            if ($importo > 0) {
                $proposta['beni'][] = ['riga' => $riga, 'descrizione' => $riga->registrazioneContabile->getDescrizioneCompleta(), 'anno' => $annoAcquisto, 'imposta' => $imposta, 'importo' => $segno * $importo, 'escluso' => $riga->coefficienteElevato];
            }
        }
        $proposta['rimanenze'] = ['segno' => $segno, 'valoreProposto' => $this->getValoreRimanenze($soggetto, $anno - 1, $attivita), 'aliquotaProposta' => self::getAliquotaMedia($righe, $anno - 1, $attivita, $prima === null)];
        return self::impostaRimanenze($proposta, $valoreRimanenze, $aliquotaRimanenze);
    }

    /**
     * Applica alla proposta valore e aliquota delle rimanenze (null: quelli
     * proposti) e ricalcola l'imposta da rettificare e il totale.
     */
    public static function impostaRimanenze(array $proposta, ?float $valore, ?float $aliquota): array
    {
        if ($proposta['rimanenze'] === null) {
            return $proposta;
        }
        $rimanenze = $proposta['rimanenze'];
        $rimanenze['valore'] = $valore ?? $rimanenze['valoreProposto'];
        $rimanenze['aliquota'] = $aliquota ?? $rimanenze['aliquotaProposta'];
        $rimanenze['imposta'] = $rimanenze['aliquota'] === null ? 0.0 : round($rimanenze['valore'] * $rimanenze['aliquota'] / 100, 2);
        $rimanenze['importo'] = $rimanenze['segno'] * $rimanenze['imposta'];
        $proposta['rimanenze'] = $rimanenze;
        return self::totalizza($proposta);
    }

    /**
     * Se c'è un cambio di regime da rettificare (beni o rimanenze).
     */
    public static function haProposta(array $proposta): bool
    {
        return (count($proposta['beni']) > 0) || ($proposta['rimanenze'] !== null);
    }

    /**
     * Saldo dei conti delle rimanenze alla fine dell'anno, senza la scrittura
     * di chiusura: le rimanenze finali, cioè i beni presenti al cambio.
     */
    private function getValoreRimanenze(Soggetto $soggetto, int $anno, ?Attivita $attivita): float
    {
        $valore = 0.0;
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            if (($riga->registrazioneContabile->origine !== ChiusuraEsercizio::ORIGINE) && in_array($riga->conto->voceCee, self::VOCI_RIMANENZE, true)
                    && ($riga->attivita?->id === $attivita?->id)) {
                $valore += $riga->dare - $riga->avere;
            }
        }
        return max(0.0, round($valore, 2));
    }

    /**
     * Aliquota media degli acquisti di beni dell'anno (voce B.6): imposta
     * detratta, o detraibile se l'anno era in regime speciale, sull'imponibile.
     *
     * @param iterable<RigaIva> $righe
     */
    private static function getAliquotaMedia(iterable $righe, int $anno, ?Attivita $attivita, bool $ordinario): ?float
    {
        $imponibile = $imposta = 0.0;
        foreach ($righe as $riga) {
            if (($riga->registroIva->tipo === TipoRegistroIva::acquisti) && ($riga->conto?->voceCee === VoceCee::economicoB6) && ((int) $riga->dataCompetenzaIva->format('Y') === $anno)
                    && (($attivita === null) || ($riga->registroIva->attivita === null) || ($riga->registroIva->attivita->id === $attivita->id))) {
                $imponibile += $riga->imponibile;
                $imposta += $ordinario ? $riga->getImpostaDetraibile() : round($riga->imposta * $riga->detraibilita / 100, 2);
            }
        }
        return $imponibile > 0 ? round($imposta / $imponibile * 100, 2) : null;
    }

    /**
     * Segna sui beni della proposta il coefficiente oltre il 25% e restituisce
     * la proposta aggiornata. Solo i beni in proposta: gli identificativi
     * ricevuti non sono mai usati per caricare righe.
     *
     * @param array{totale: int, beni: array} $proposta
     * @param array<int|string, mixed> $esclusi per identificativo della riga IVA
     */
    public function salvaEsclusi(array $proposta, array $esclusi): array
    {
        foreach ($proposta['beni'] as $indice => $bene) {
            $escluso = isset($esclusi[$bene['riga']->id]);
            if ($escluso !== $bene['escluso']) {
                $bene['riga']->coefficienteElevato = $escluso;
                $this->dataMapper->save($bene['riga']);
                $proposta['beni'][$indice]['escluso'] = $escluso;
            }
        }
        return self::totalizza($proposta);
    }

    private static function totalizza(array $proposta): array
    {
        $proposta['totale'] = (int) round(array_sum(array_map(fn(array $bene) => $bene['escluso'] ? 0.0 : $bene['importo'], $proposta['beni'])) + ($proposta['rimanenze']['importo'] ?? 0.0));
        return $proposta;
    }

    private static function isRilevante(RigaIva $riga, ?Attivita $attivita): bool
    {
        return ($riga->registroIva->tipo === TipoRegistroIva::acquisti) && $riga->isBeneAmmortizzabile() && (abs($riga->imponibile) > self::COSTO_MINIMO)
                && (($attivita === null) || ($riga->registroIva->attivita === null) || ($riga->registroIva->attivita->id === $attivita->id));
    }
}
