<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Ventilazione dei corrispettivi (art. 24, c. 3, D.P.R. 633/1972; art. 3
 * D.M. 24 febbraio 1973): i corrispettivi del periodo registrati senza
 * aliquota (codici IVA di ventilazione) si ripartiscono in proporzione agli
 * acquisti di beni destinati alla rivendita, comprensivi dell'imposta e
 * distinti per aliquota, registrati dall'inizio dell'anno alla fine del
 * periodo ("rapporti progressivi"); dalla quota di ciascuna aliquota si
 * scorpora l'imposta (art. 27, c. 4). Con la dichiarazione annuale i
 * corrispettivi dell'anno si ripartiscono con i rapporti di fine anno e la
 * differenza con l'imposta delle liquidazioni si versa o si recupera lì.
 *
 * Gli acquisti senza imposta (natura) concorrono ai rapporti con la quota
 * corrispondente, senza imposta; per l'aliquota si usa il codice IVA delle
 * vendite con la stessa aliquota o natura.
 */
class VentilazioneCorrispettivi
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Ripartizione dei corrispettivi da ventilare con data di competenza
     * da $dal ad $al.
     *
     * @throws \DomainException se ci sono corrispettivi da ventilare senza acquisti per la rivendita nell'anno
     */
    public function calcola(Soggetto $soggetto, SismaDate $dal, SismaDate $al): RisultatoVentilazione
    {
        $risultato = new RisultatoVentilazione();
        $inizioAnno = new SismaDate($al->format('Y') . '-01-01');
        $acquisti = [];
        $codici = [];
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $inizioAnno, $al) as $riga) {
            if (($riga->registroIva->tipo === TipoRegistroIva::corrispettivi) && $riga->codiceIva->ventilazione
                    && ($riga->dataCompetenzaIva->format('Y-m-d') >= $dal->format('Y-m-d'))) {
                $risultato->corrispettivi += $riga->imponibile + $riga->imposta;
            } elseif (($riga->registroIva->tipo === TipoRegistroIva::acquisti) && $riga->rivendita) {
                $chiave = self::chiave($riga);
                $acquisti[$chiave] = ($acquisti[$chiave] ?? 0.0) + $riga->imponibile + $riga->imposta;
                $codici[$chiave] ??= $riga->codiceIva;
            }
        }
        $risultato->corrispettivi = round($risultato->corrispettivi, 2);
        $totaleAcquisti = round(array_sum($acquisti), 2);
        $risultato->acquisti = $totaleAcquisti;
        if ($risultato->corrispettivi == 0) {
            return $risultato;
        }
        if ($totaleAcquisti <= 0) {
            throw new \DomainException('Corrispettivi da ventilare senza acquisti di beni per la rivendita registrati dall\'inizio dell\'anno: indicare gli acquisti "per rivendita" nel registro degli acquisti.');
        }
        ksort($acquisti);
        $chiavi = array_keys($acquisti);
        $ripartito = 0.0;
        foreach ($chiavi as $indice => $chiave) {
            $quota = $indice === array_key_last($chiavi) ? round($risultato->corrispettivi - $ripartito, 2) : round($risultato->corrispettivi * $acquisti[$chiave] / $totaleAcquisti, 2);
            $ripartito = round($ripartito + $quota, 2);
            $codice = $this->getCodiceVendite($soggetto, $codici[$chiave]);
            [$imponibile, $imposta] = CorrispettiviMensili::scorpora($codice, $quota);
            $risultato->righe[] = ['codice' => $codice, 'acquisti' => round($acquisti[$chiave], 2), 'percentuale' => round($acquisti[$chiave] / $totaleAcquisti * 100, 2),
                'corrispettivi' => $quota, 'imponibile' => $imponibile, 'imposta' => $imposta];
            $risultato->imposta += $imposta;
        }
        $risultato->imposta = round($risultato->imposta, 2);
        return $risultato;
    }

    /**
     * Aliquota (o natura) dell'acquisto.
     */
    private static function chiave(RigaIva $riga): string
    {
        $codice = $riga->codiceIva;
        return $codice->natura !== null ? 'N|' . $codice->natura->value : sprintf('A|%06.2f', $codice->aliquota);
    }

    /**
     * Codice IVA delle vendite con l'aliquota o la natura dell'acquisto
     * (senza indetraibilità né integrazione).
     */
    private function getCodiceVendite(Soggetto $soggetto, CodiceIva $acquisto): CodiceIva
    {
        if (($acquisto->inversioneContabile === false) && ($acquisto->detraibilita >= 100)) {
            return $acquisto;
        }
        return (new CodiceIvaModel($this->dataMapper))->trova($soggetto->studio, $acquisto->aliquota, $acquisto->natura) ?? $acquisto;
    }
}
