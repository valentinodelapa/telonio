<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;

/**
 * Valore catastale proposto per gli immobili (art. 34, c. 5, TUS: non è
 * soggetto a rettifica il valore non inferiore a quello catastale
 * determinato ai sensi dell'art. 52, c. 4, del testo unico dell'imposta di
 * registro):
 * - fabbricati: rendita rivalutata del 5% per il moltiplicatore della
 *   categoria (gruppi A e C/2, C/6, C/7: 120, ridotto a 110 per la prima
 *   casa; B e C/3, C/4, C/5: 140; A/10 e D: 60; C/1 ed E: 40,8);
 * - terreni non edificabili: reddito dominicale rivalutato del 25% per 90.
 *
 * Le aree edificabili vanno al valore venale. Il valore resta modificabile
 * nel bene: la procedura propone, lo studio conferma.
 */
class ValoreCatastale
{

    public const RIVALUTAZIONE_RENDITA = 1.05;
    public const RIVALUTAZIONE_REDDITO_DOMINICALE = 1.25;
    public const MOLTIPLICATORE_TERRENI = 90;

    /**
     * Moltiplicatore della categoria (null per le categorie senza rendita).
     */
    public static function getMoltiplicatore(CategoriaCatastale $categoria, bool $primaCasa): ?float
    {
        if ($categoria->haRendita() === false) {
            return null;
        }
        return match (true) {
            $categoria === CategoriaCatastale::a10 => 60.0,
            $categoria->getGruppo() === 'D' => 60.0,
            in_array($categoria, [CategoriaCatastale::c1], true), $categoria->getGruppo() === 'E' => 40.8,
            $categoria->getGruppo() === 'B', in_array($categoria, [CategoriaCatastale::c3, CategoriaCatastale::c4, CategoriaCatastale::c5], true) => 140.0,
            $primaCasa => 110.0,
            default => 120.0,
        };
    }

    /**
     * Valore della piena proprietà dell'intero immobile (null se mancano i
     * dati).
     */
    public static function getValoreIntero(DatiCatastali $datiCatastali, bool $terreno, bool $primaCasa = false): ?float
    {
        if ($terreno) {
            return $datiCatastali->redditoDominicale === null ? null : $datiCatastali->redditoDominicale * self::RIVALUTAZIONE_REDDITO_DOMINICALE * self::MOLTIPLICATORE_TERRENI;
        }
        if (($datiCatastali->categoria === null) || ($datiCatastali->rendita === null)) {
            return null;
        }
        $moltiplicatore = self::getMoltiplicatore($datiCatastali->categoria, $primaCasa);
        return $moltiplicatore === null ? null : $datiCatastali->rendita * self::RIVALUTAZIONE_RENDITA * $moltiplicatore;
    }

    /**
     * Valore della quota del defunto, arrotondato all'euro.
     */
    public static function getValoreQuota(DatiCatastali $datiCatastali, bool $terreno, float $quota, bool $primaCasa = false): ?int
    {
        $valore = self::getValoreIntero($datiCatastali, $terreno, $primaCasa);
        return $valore === null ? null : (int) round($valore * $quota);
    }
}
