<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Enumerations\FattispecieImu;

/**
 * Verifica delle condizioni di un'aliquota differenziata del prospetto sui
 * dati dell'immobile, dalla descrizione delle condizioni (D.M. 6 settembre
 * 2024, allegato A):
 * - categorie catastali ("Categoria catastale: - A/2 ...", "Immobili di
 *   categoria C", "categoria A10, B, C"): devono comprendere la categoria
 *   dell'immobile;
 * - tipo di contratto e utilizzo (canone concordato ai sensi della L.
 *   431/1998, canone libero, comodato, a disposizione, utilizzo diretto):
 *   devono comprendere l'utilizzo del possesso in Anagrafica;
 * - le altre condizioni (requisiti del soggetto passivo, codice ATECO,
 *   destinazione d'uso, collocazione, limiti di numero o durata, ...) non
 *   si ricavano dai dati e restano da verificare.
 *
 * Un'aliquota è "verificata" quando ha almeno una condizione controllabile
 * e nessuna da verificare: il calcolo la applica in automatico,
 * segnalandolo; le altre compatibili sono solo suggerite.
 */
class CompatibilitaDifferenziate
{

    private const UTILIZZI = [
        '/art\.\s*2,\s*comma\s*3|art\.\s*5,\s*comma\s*[123]|canone\s+concordato|L\.\s*n?\.?\s*431\/1998.*concordat/iu' => [UtilizzoImmobile::locatoCanoneConcordato],
        '/canone\s+libero/iu' => [UtilizzoImmobile::locatoLiberoMercato],
        '/locat[ie]\s+o\s+concess[ie]\s+in\s+comodato\s+o\s+utilizzat[ie]\s+direttamente/iu' => [UtilizzoImmobile::locatoLiberoMercato, UtilizzoImmobile::locatoCanoneConcordato, UtilizzoImmobile::comodato, UtilizzoImmobile::strumentale],
        '/locazione\s+o\s+comodato/iu' => [UtilizzoImmobile::locatoLiberoMercato, UtilizzoImmobile::locatoCanoneConcordato, UtilizzoImmobile::comodato],
        '/comodato\s+d.uso\s+gratuito|in\s+comodato(?!\s+o)/iu' => [UtilizzoImmobile::comodato],
        '/a\s+disposizione|non\s+locat[ei]\s+e\s+non\s+concess[ei]/iu' => [UtilizzoImmobile::aDisposizione],
        '/utilizzat[ie]\s+direttamente\s+dal\s+soggetto\s+passivo/iu' => [UtilizzoImmobile::strumentale],
    ];
    private const DA_VERIFICARE = [
        'ONLUS' => 'requisiti del soggetto passivo (ONLUS o terzo settore)',
        'terzo settore' => 'requisiti del soggetto passivo (ONLUS o terzo settore)',
        'Requisiti' => 'requisiti del soggetto passivo',
        'Codice ATECO' => 'codice ATECO dell\'attività',
        'Destinazione d\'uso' => 'destinazione d\'uso',
        'Collocazione' => 'collocazione dell\'immobile',
        'Limitatamente' => 'limite al numero di immobili',
        'Fino a mesi' => 'durata dell\'agevolazione',
        'IACP' => 'assegnazione da IACP o enti di edilizia residenziale pubblica',
        'inagibil' => 'inagibilità',
        'Bottega' => 'bottega storica o artigiana',
        'contratto registrato' => 'contratto registrato',
        'Tipologia fabbricato' => 'tipologia del fabbricato',
        'Purche' => 'condizioni sull\'utilizzo da parte dell\'inquilino o comodatario',
        'Purché' => 'condizioni sull\'utilizzo da parte dell\'inquilino o comodatario',
        'parent' => 'grado di parentela del comodatario',
    ];

    /**
     * @return array{compatibile: bool, verificata: bool, daVerificare: string[]}
     */
    public static function verifica(AliquotaDifferenziata $differenziata, FattispecieImu $fattispecie, ?CategoriaCatastale $categoria, ?UtilizzoImmobile $utilizzo, bool $ente = true, bool $inagibile = true): array
    {
        $testo = preg_replace('/\s+/u', ' ', $differenziata->descrizione);
        $esito = ['compatibile' => true, 'verificata' => false, 'daVerificare' => []];
        if (($differenziata->fattispecie !== null) && ($differenziata->fattispecie !== $fattispecie)) {
            return ['compatibile' => false, 'verificata' => false, 'daVerificare' => []];
        }
        $vincoli = 0;
        $categorie = self::getCategorie($testo);
        if (count($categorie) > 0) {
            $vincoli++;
            $esito['compatibile'] = ($categoria !== null) && (in_array($categoria->value, $categorie, true) || in_array($categoria->getGruppo(), $categorie, true));
        }
        $utilizzi = self::getUtilizzi($testo);
        if (count($utilizzi) > 0) {
            $vincoli++;
            $esito['compatibile'] = $esito['compatibile'] && ($utilizzo !== null) && in_array($utilizzo, $utilizzi, true);
        }
        // condizioni che i dati escludono: requisiti di ONLUS/terzo settore per chi non è un ente, inagibilità
        if ((($ente === false) && (preg_match('/ONLUS|terzo settore/iu', $testo) === 1)) || (($inagibile === false) && (mb_stripos($testo, 'inagibil') !== false))) {
            return ['compatibile' => false, 'verificata' => false, 'daVerificare' => []];
        }
        foreach (self::DA_VERIFICARE as $parola => $condizione) {
            if (mb_stripos($testo, $parola) !== false) {
                $esito['daVerificare'][$condizione] = $condizione;
            }
        }
        if (isset($esito['daVerificare']['requisiti del soggetto passivo (ONLUS o terzo settore)'])) {
            unset($esito['daVerificare']['requisiti del soggetto passivo']);
        }
        $esito['daVerificare'] = array_values($esito['daVerificare']);
        $esito['verificata'] = $esito['compatibile'] && ($vincoli > 0) && (count($esito['daVerificare']) === 0);
        return $esito;
    }

    /**
     * @return string[] categorie ("A/2") o gruppi ("C")
     */
    private static function getCategorie(string $testo): array
    {
        $categorie = [];
        preg_match_all('/-\s*([A-F])\/(\d{1,2})\b/u', $testo, $elenco, PREG_SET_ORDER);
        foreach ($elenco as [, $gruppo, $numero]) {
            $categorie[] = $gruppo . '/' . (int) $numero;
        }
        if (preg_match('/Immobili di categoria ([A-F0-9, ]+?)(?:\s+-|\s+Categoria|$)/u', $testo, $gruppi) === 1) {
            foreach (preg_split('/\s*,\s*/', trim($gruppi[1])) as $voce) {
                if (preg_match('/^([A-F])(\d{1,2})$/', $voce, $parti) === 1) {
                    $categorie[] = $parti[1] . '/' . (int) $parti[2];
                } elseif (preg_match('/^[A-F]$/', $voce) === 1) {
                    $categorie[] = $voce;
                }
            }
        }
        return array_values(array_unique($categorie));
    }

    /**
     * @return UtilizzoImmobile[]
     */
    private static function getUtilizzi(string $testo): array
    {
        // intestazione generica del gruppo: conta solo il tipo di contratto che segue
        $testo = preg_replace('/Abitazione locata o in comodato/iu', '', $testo);
        $utilizzi = [];
        foreach (self::UTILIZZI as $espressione => $ammessi) {
            if (preg_match($espressione, $testo) === 1) {
                foreach ($ammessi as $ammesso) {
                    $utilizzi[$ammesso->value] = $ammesso;
                }
            }
        }
        return array_values($utilizzi);
    }
}
