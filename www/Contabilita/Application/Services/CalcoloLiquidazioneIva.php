<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\ProrataIvaModel;
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
 * liquidazione. Con le attività separate (art. 36) il calcolo è per
 * attività: righe dei suoi registri, quote degli acquisti promiscui e
 * pro-rata dell'attività.
 */
class CalcoloLiquidazioneIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function calcola(Soggetto $soggetto, SismaDate $dal, SismaDate $al, ?AttivitaIva $attivita = null): RisultatoLiquidazione
    {
        $risultato = new RisultatoLiquidazione();
        $differite = $nonRipartite = [];
        $anno = (int) $dal->format('Y');
        $separate = new AttivitaSeparate($this->dataMapper);
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $dal, $al) as $riga) {
            $quota = 1.0;
            if ($attivita !== null) {
                $quote = $separate->getQuote($riga, $soggetto, $anno);
                if (count($quote) === 0) {
                    $nonRipartite[$riga->registrazioneContabile->id] = $riga->registrazioneContabile->getDescrizioneCompleta();
                    continue;
                }
                if (isset($quote[$attivita->numero]) === false) {
                    continue;
                }
                $quota = $quote[$attivita->numero];
            }
            $registrazione = $riga->registrazioneContabile;
            $tipo = $riga->registroIva->tipo;
            $chiave = $riga->registroIva->getEtichetta() . '|' . $riga->codiceIva->codice;
            $voce = $risultato->riepilogo[$chiave] ??= ['registro' => $riga->registroIva->getEtichetta(), 'tipo' => $tipo, 'codice' => $riga->codiceIva->getEtichetta(),
                'imponibile' => 0.0, 'imposta' => 0.0, 'esigibile' => 0.0, 'detraibile' => 0.0];
            $voce['imponibile'] += $riga->imponibile * $quota;
            $voce['imposta'] += $riga->imposta * $quota;
            if ($tipo === TipoRegistroIva::acquisti) {
                $voce['detraibile'] += $riga->getImpostaDetraibile() * $quota;
                if ($riga->codiceIva->inversioneContabile) {
                    $voce['esigibile'] += $riga->imposta * $quota;
                }
            } elseif ($registrazione->esigibilita === EsigibilitaIva::immediata) {
                $voce['esigibile'] += $riga->imposta * $quota;
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
        $prorata = (new ProrataIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno, $attivita);
        if (($prorata !== null) && ($prorata->percentuale < 100)) {
            $intera = $risultato->ivaDetraibile;
            $risultato->ivaDetraibile = $prorata->applica($intera);
            $risultato->percentualeDetrazione = $prorata->percentuale;
            $risultato->avvisi[] = sprintf('Pro-rata provvisorio del %d%%: IVA detraibile ridotta da € %s a € %s (conguaglio nella dichiarazione annuale).', $prorata->percentuale,
                    number_format($intera, 2, ',', '.'), number_format($risultato->ivaDetraibile, 2, ',', '.'));
        }
        if ((count($nonRipartite) > 0) && ($attivita !== null) && ($attivita->numero === array_key_first($separate->getAttivita($soggetto, $anno)))) {
            $risultato->avvisi[] = 'Acquisti promiscui senza ripartizione tra le attività separate, esclusi dalla liquidazione: ' . implode('; ', $nonRipartite) . '.';
        }
        if (count($differite) > 0) {
            $risultato->avvisi[] = 'IVA a esigibilità differita esclusa (diventa esigibile all\'incasso, da liquidare a parte): ' . implode('; ', $differite) . '.';
        }
        return $risultato;
    }
}
