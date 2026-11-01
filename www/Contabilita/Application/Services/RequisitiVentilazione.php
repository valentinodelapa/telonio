<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Limiti della ventilazione dei corrispettivi verificati sui registri
 * dell'anno precedente (D.M. 24 febbraio 1973): superati, la ventilazione
 * non si applica nell'anno.
 *
 * - art. 4: acquisti di "altri prodotti" (beni per la rivendita diversi da
 *   quelli delle categorie dell'art. 1) non oltre il 50% del totale degli
 *   acquisti e delle importazioni registrati;
 * - art. 5: cessioni documentate da fattura non oltre il 20% del volume
 *   d'affari (vendite fatturate e corrispettivi al netto dell'imposta),
 *   escluse in entrambi le cessioni di immobili e di beni strumentali
 *   (righe con contropartita patrimoniale).
 *
 * Se l'anno precedente non è in contabilità la verifica non è possibile.
 */
class RequisitiVentilazione
{

    public const LIMITE_ALTRI_PRODOTTI = 50.0;
    public const LIMITE_FATTURE = 20.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{anno: int, disponibile: bool, acquisti: float, altriProdotti: float, percentualeAltri: float, fatture: float, volumeAffari: float, percentualeFatture: float, superati: string[]}
     */
    public function verifica(Soggetto $soggetto, int $anno): array
    {
        $precedente = $anno - 1;
        $dal = new SismaDate($precedente . '-01-01');
        $al = new SismaDate($precedente . '-12-31');
        $acquisti = $altri = $fatture = $corrispettivi = 0.0;
        $righe = 0;
        $ventilare = false;
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $dal, $al) as $riga) {
            $righe++;
            $patrimoniale = in_array($riga->conto?->natura, [NaturaConto::attivita, NaturaConto::passivita], true);
            switch ($riga->registroIva->tipo) {
                case TipoRegistroIva::acquisti:
                    $acquisti += $riga->imponibile;
                    $altri += $riga->altriProdotti ? $riga->imponibile : 0.0;
                    break;
                case TipoRegistroIva::vendite:
                    $fatture += $patrimoniale ? 0.0 : $riga->imponibile;
                    break;
                default:
                    if ($riga->codiceIva->ventilazione) {
                        $ventilare = true;
                    } elseif ($patrimoniale === false) {
                        $corrispettivi += $riga->imponibile;
                    }
            }
        }
        if ($ventilare) {
            try {
                foreach ((new VentilazioneCorrispettivi($this->dataMapper))->calcola($soggetto, $dal, $al)->righe as $voce) {
                    $corrispettivi += $voce['imponibile'];
                }
            } catch (\DomainException) {
                // senza acquisti per la rivendita i corrispettivi da ventilare restano fuori dal volume d'affari
            }
        }
        $volumeAffari = round($fatture + $corrispettivi, 2);
        $risultato = ['anno' => $precedente, 'disponibile' => $righe > 0, 'acquisti' => round($acquisti, 2), 'altriProdotti' => round($altri, 2),
            'percentualeAltri' => $acquisti > 0 ? round($altri / $acquisti * 100, 2) : 0.0, 'fatture' => round($fatture, 2), 'volumeAffari' => $volumeAffari,
            'percentualeFatture' => $volumeAffari > 0 ? round($fatture / $volumeAffari * 100, 2) : 0.0, 'superati' => []];
        if ($risultato['percentualeAltri'] > self::LIMITE_ALTRI_PRODOTTI) {
            $risultato['superati'][] = sprintf('nel %d gli acquisti di altri prodotti sono il %s%% del totale (limite 50%%, art. 4 D.M. 24 febbraio 1973)', $precedente, number_format($risultato['percentualeAltri'], 2, ',', '.'));
        }
        if ($risultato['percentualeFatture'] > self::LIMITE_FATTURE) {
            $risultato['superati'][] = sprintf('nel %d le cessioni fatturate sono il %s%% del volume d\'affari (limite 20%%, art. 5 D.M. 24 febbraio 1973)', $precedente, number_format($risultato['percentualeFatture'], 2, ',', '.'));
        }
        return $risultato;
    }
}
