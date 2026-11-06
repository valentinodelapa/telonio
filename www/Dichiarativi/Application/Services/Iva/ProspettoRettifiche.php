<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Models\RigaIvaModel;
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
 * in quinti (in decimi per i fabbricati) degli anni mancanti; rimanenze e servizi
 * non utilizzati si aggiungono a mano. Non si considerano ammortizzabili i beni di
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
     * Proposta per i beni ammortizzabili al cambio di regime: con la detrazione
     * che passa da ordinaria a forfetaria si restituisce (segno meno), nel
     * passaggio contrario si recupera. Tra due regimi speciali gli acquisti
     * non si detraggono mai e non c'è nulla da rettificare.
     *
     * @return array{totale: int, beni: array<int, array{riga: RigaIva, descrizione: string, anno: int, imposta: float, importo: float, escluso: bool}>}
     */
    public function getProposta(Soggetto $soggetto, int $anno, ?AttivitaIva $attivita = null): array
    {
        $proposta = ['totale' => 0, 'beni' => []];
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
        return self::totalizza($proposta);
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
        $proposta['totale'] = (int) round(array_sum(array_map(fn(array $bene) => $bene['escluso'] ? 0.0 : $bene['importo'], $proposta['beni'])));
        return $proposta;
    }

    private static function isRilevante(RigaIva $riga, ?AttivitaIva $attivita): bool
    {
        return ($riga->registroIva->tipo === TipoRegistroIva::acquisti) && $riga->isBeneAmmortizzabile() && (abs($riga->imponibile) > self::COSTO_MINIMO)
                && (($attivita === null) || ($riga->registroIva->attivitaIva === null) || ($riga->registroIva->attivitaIva->id === $attivita->id));
    }
}
