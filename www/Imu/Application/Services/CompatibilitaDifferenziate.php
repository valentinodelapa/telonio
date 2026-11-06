<?php

namespace Imu\Application\Services;

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
 * - comodato ai parenti, pensionato all'estero e alloggi IACP: si
 *   confrontano con le agevolazioni e le caratteristiche IMU dell'immobile
 *   (ContestoAliquota);
 * - le altre condizioni (requisiti del soggetto passivo, codice ATECO,
 *   destinazione d'uso, collocazione, limiti di numero o durata, ...) non
 *   si ricavano dai dati e restano da verificare.
 *
 * La descrizione si legge voce per voce: una voce che non si riconosce resta
 * da verificare con il suo testo, così un'aliquota con una condizione ignota
 * non si applica in automatico né si perde.
 *
 * Un'aliquota è "verificata" quando ha almeno una condizione controllabile
 * e nessuna da verificare: il calcolo la applica in automatico,
 * segnalandolo; le altre compatibili sono solo suggerite.
 */
class CompatibilitaDifferenziate
{

    private const UTILIZZI = [
        '/art\.\s*2,\s*comma\s*3|art\.\s*5,\s*comma\s*[123]|canone\s+concordato|L\.\s*n?\.?\s*431\/1998.*concordat/iu' => [UtilizzoImmobile::locatoCanoneConcordato],
        '/canone\s+libero|art\.\s*2,\s*comma\s*1\b/iu' => [UtilizzoImmobile::locatoLiberoMercato],
        '/Tipo\s+contratto:\s*Locazione(?!\s+(?:ai|a|o)\s)/iu' => [UtilizzoImmobile::locatoLiberoMercato, UtilizzoImmobile::locatoCanoneConcordato],
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
        'inagibil' => 'inagibilità',
        'Bottega' => 'bottega storica o artigiana',
        'contratto registrato' => 'contratto registrato',
        'Tipologia fabbricato' => 'tipologia del fabbricato',
        'Purche' => 'condizioni sull\'utilizzo da parte dell\'inquilino o comodatario',
        'Purché' => 'condizioni sull\'utilizzo da parte dell\'inquilino o comodatario',
        'parent' => 'grado di parentela del comodatario',
        'studenti' => 'contratto a studenti universitari',
        'Durata del contratto' => 'durata del contratto',
        'Persona fisica di eta' => 'età del soggetto',
        'non titolare di propriet' => 'locatario o comodatario senza altri immobili',
        'determinate zone' => 'collocazione dell\'immobile',
    ];

    /** Voci che descrivono il gruppo o le categorie, già controllate */
    private const VOCI_STRUTTURA = '/^(?:Abitazione\s+locata\s+o\s+in\s+comodato|Abitazione\s+a\s+disposizione|Immobili\s+di\s+categoria\s+[A-F0-9, ]+(?:\s+Categoria\s+catastale:?)?|Categoria\s+catastale:?|[A-F]\/\d+\b.*|Sino\s+al\s+\w+\s+grado.*)$/iu';
    private const PENSIONATO = '/pensionat|titolar\w*\s+di\s+pensione|\bAIRE\b/iu';
    private const IACP = '/IACP|edilizia\s+residenziale\s+pubblica/iu';
    private const COMODATO_DIVERSO = '/ipotesi\s+diverse\s+da\s+quella/iu';
    private const UTILIZZO_INQUILINO = 'condizioni sull\'utilizzo da parte dell\'inquilino o comodatario';
    private const COMODATO_DI_LEGGE = ['grado di parentela del comodatario', 'contratto registrato', self::UTILIZZO_INQUILINO];
    private const LUNGHEZZA_VOCE = 90;

    /**
     * @return array{compatibile: bool, verificata: bool, daVerificare: string[]}
     */
    public static function verifica(AliquotaDifferenziata $differenziata, FattispecieImu $fattispecie, ContestoAliquota $contesto): array
    {
        $testo = preg_replace('/\s+/u', ' ', $differenziata->descrizione);
        $esito = ['compatibile' => true, 'verificata' => false, 'daVerificare' => []];
        if ((($differenziata->fattispecie !== null) && ($differenziata->fattispecie !== $fattispecie)) || self::isEsclusa($testo, $contesto)) {
            return ['compatibile' => false, 'verificata' => false, 'daVerificare' => []];
        }
        $vincoli = 0;
        $categorie = self::getCategorie($testo);
        if (count($categorie) > 0) {
            $vincoli++;
            $categoria = $contesto->categoria;
            $esito['compatibile'] = ($categoria !== null) && (in_array($categoria->value, $categorie, true) || in_array($categoria->getGruppo(), $categorie, true));
        }
        $utilizzi = self::getUtilizzi($testo);
        if (count($utilizzi) > 0) {
            $vincoli++;
            $utilizzo = $contesto->comodatoParenti ? UtilizzoImmobile::comodato : $contesto->utilizzo;
            $esito['compatibile'] = $esito['compatibile'] && ($utilizzo !== null) && in_array($utilizzo, $utilizzi, true);
        }
        if ((preg_match(self::PENSIONATO, self::senzaCategorie($testo)) === 1) || (preg_match(self::IACP, $testo) === 1)) {
            $vincoli++;
        }
        $esito['daVerificare'] = self::getDaVerificare($testo, $contesto);
        $esito['verificata'] = $esito['compatibile'] && ($vincoli > 0) && (count($esito['daVerificare']) === 0);
        return $esito;
    }

    /**
     * Condizioni che i dati escludono: requisiti di ONLUS o terzo settore
     * per chi non è un ente, inagibilità, pensionato all'estero e alloggio
     * IACP non indicati; il comodato con i requisiti di legge esclude
     * l'aliquota per le ipotesi diverse dal comma 747, lett. c).
     */
    private static function isEsclusa(string $testo, ContestoAliquota $contesto): bool
    {
        return (($contesto->ente === false) && (preg_match('/ONLUS|terzo settore/iu', $testo) === 1))
                || (($contesto->inagibile === false) && (mb_stripos($testo, 'inagibil') !== false))
                || (($contesto->pensionatoEstero === false) && (preg_match(self::PENSIONATO, self::senzaCategorie($testo)) === 1))
                || (($contesto->alloggioIacp === false) && (preg_match(self::IACP, $testo) === 1))
                || ($contesto->comodatoParenti && (preg_match(self::COMODATO_DIVERSO, $testo) === 1));
    }

    /**
     * @return string[]
     */
    private static function getDaVerificare(string $testo, ContestoAliquota $contesto): array
    {
        $condizioni = [];
        foreach (self::getVoci($testo) as $voce) {
            $etichette = [];
            foreach (self::DA_VERIFICARE as $parola => $condizione) {
                if (mb_stripos($voce, $parola) !== false) {
                    $etichette[$condizione] = $condizione;
                }
            }
            // "Purché l'affittuario la utilizzi come abitazione principale" non è una destinazione dell'immobile
            if (isset($etichette[self::UTILIZZO_INQUILINO])) {
                unset($etichette['destinazione d\'uso']);
            }
            if ((count($etichette) === 0) && (preg_match(self::VOCI_STRUTTURA, $voce) !== 1) && (self::isVoceControllata($voce) === false)) {
                $etichette[] = 'condizione del prospetto: ' . self::accorcia($voce);
            }
            $condizioni += array_combine($etichette, $etichette);
        }
        if (isset($condizioni['requisiti del soggetto passivo (ONLUS o terzo settore)'])) {
            unset($condizioni['requisiti del soggetto passivo']);
        }
        // l'agevolazione del comma 747, lett. c) presuppone parentela di primo grado, contratto registrato e abitazione principale
        if ($contesto->comodatoParenti && (preg_match('/secondo\s+grado/iu', $testo) !== 1)) {
            $condizioni = array_diff_key($condizioni, array_flip(self::COMODATO_DI_LEGGE));
        }
        return array_values($condizioni);
    }

    private static function isVoceControllata(string $voce): bool
    {
        if ((preg_match(self::PENSIONATO, $voce) === 1) || (preg_match(self::IACP, $voce) === 1)) {
            return true;
        }
        foreach (array_keys(self::UTILIZZI) as $espressione) {
            if (preg_match($espressione, $voce) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Voci della descrizione: nel prospetto iniziano con un trattino.
     *
     * @return string[]
     */
    private static function getVoci(string $testo): array
    {
        $voci = array_map('trim', preg_split('/(?:^|\s)-\s+/u', $testo));
        return array_values(array_filter(array_map(fn(string $voce) => rtrim($voce, " :."), $voci), fn(string $voce) => $voce !== ''));
    }

    private static function senzaCategorie(string $testo): string
    {
        return preg_replace('/-\s*[A-F]\/\d{1,2}\b[^-]*/u', '', $testo);
    }

    private static function accorcia(string $voce): string
    {
        return mb_strlen($voce) > self::LUNGHEZZA_VOCE ? mb_substr($voce, 0, self::LUNGHEZZA_VOCE) . '…' : $voce;
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
