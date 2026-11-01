<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * IVA esigibile e detraibile delle registrazioni con data di competenza
 * nel periodo:
 *
 * - vendite e corrispettivi: imposta a debito, salvo la scissione dei
 *   pagamenti (dovuta dal cessionario) e l'esigibilità differita, esclusa
 *   con un avviso perché diventa esigibile all'incasso, che la procedura
 *   non segue ancora;
 * - acquisti: imposta detraibile (al netto della parte indetraibile) e,
 *   per i codici con inversione contabile, la stessa imposta anche a
 *   debito (integrazione, artt. 17 D.P.R. 633/1972 e 46-47 D.L.
 *   331/1993).
 *
 * Il riepilogo per registro e codice IVA è quello della stampa della
 * liquidazione.
 */
class CalcoloLiquidazioneIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function calcola(Soggetto $soggetto, SismaDate $dal, SismaDate $al): RisultatoLiquidazione
    {
        $risultato = new RisultatoLiquidazione();
        $differite = [];
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $dal, $al) as $riga) {
            $registrazione = $riga->registrazioneContabile;
            $tipo = $riga->registroIva->tipo;
            $chiave = $riga->registroIva->getEtichetta() . '|' . $riga->codiceIva->codice;
            $voce = $risultato->riepilogo[$chiave] ??= ['registro' => $riga->registroIva->getEtichetta(), 'tipo' => $tipo, 'codice' => $riga->codiceIva->getEtichetta(),
                'imponibile' => 0.0, 'imposta' => 0.0, 'esigibile' => 0.0, 'detraibile' => 0.0];
            $voce['imponibile'] += $riga->imponibile;
            $voce['imposta'] += $riga->imposta;
            if ($tipo === TipoRegistroIva::acquisti) {
                $voce['detraibile'] += $riga->getImpostaDetraibile();
                if ($riga->codiceIva->inversioneContabile) {
                    $voce['esigibile'] += $riga->imposta;
                }
            } elseif ($registrazione->esigibilita === EsigibilitaIva::immediata) {
                $voce['esigibile'] += $riga->imposta;
            } elseif ($registrazione->esigibilita === EsigibilitaIva::differita) {
                $differite[$registrazione->id] = $registrazione->getDescrizioneCompleta();
            }
            $risultato->riepilogo[$chiave] = $voce;
        }
        foreach ($risultato->riepilogo as $chiave => $voce) {
            foreach (['imponibile', 'imposta', 'esigibile', 'detraibile'] as $campo) {
                $risultato->riepilogo[$chiave][$campo] = round($voce[$campo], 2);
            }
            $risultato->ivaEsigibile += $voce['esigibile'];
            $risultato->ivaDetraibile += $voce['detraibile'];
        }
        ksort($risultato->riepilogo);
        $risultato->ivaEsigibile = round($risultato->ivaEsigibile, 2);
        $risultato->ivaDetraibile = round($risultato->ivaDetraibile, 2);
        if (count($differite) > 0) {
            $risultato->avvisi[] = 'IVA a esigibilità differita esclusa (diventa esigibile all\'incasso, da liquidare a parte): ' . implode('; ', $differite) . '.';
        }
        return $risultato;
    }
}
