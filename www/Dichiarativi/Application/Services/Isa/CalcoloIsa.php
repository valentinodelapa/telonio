<?php

namespace Dichiarativi\Application\Services\Isa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Telematico\GeneratoreIsa;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Calcolo del modello ISA con il software ufficiale (MotoreIsa): dai
 * record d'ingresso (GeneratoreIsa) all'esito, conservato nella
 * dichiarazione:
 *
 * - calcolo_isa: i record P, Q ed R restituiti, con i risultati nei campi
 *   "I" del record Q, da allegare al file Redditi;
 * - esito_isa: l'esito in XML (punteggio, indicatori elementari di
 *   affidabilità e di anomalia, probabilità dei modelli di business);
 * - report_isa: i messaggi del software (controlli sui dati);
 * - impronta_isa: l'impronta dei record d'ingresso, per riconoscere un
 *   calcolo superato da modifiche successive dei quadri.
 *
 * Con errori bloccanti il software non elabora la posizione: restano solo
 * i messaggi.
 *
 * Il software in modalità batch toglie l'accettazione della proposta di
 * concordato preventivo biennale (P10) prima del calcolo e non la rimette
 * nel file restituito (nel software interattivo l'accettazione segue la
 * lettura della proposta): con l'adesione (ConcordatoIsa) la si aggiunge ai
 * record Q restituiti, dopo gli altri campi del quadro P.
 */
class CalcoloIsa
{

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private MotoreIsa $motore = new MotoreIsa())
    {

    }

    /**
     * @return bool true se il software ha elaborato il modello
     * @throws \InvalidArgumentException se i record non si possono generare
     */
    public function calcola(Dichiarazione $dichiarazione): bool
    {
        $ingresso = (new GeneratoreIsa($this->dataMapper))->genera($dichiarazione);
        $esito = $this->motore->esito($dichiarazione->getAnnoModello(), $ingresso);
        $elaborato = ($esito['output'] !== '') && (str_contains($esito['report'], 'Isa non elaborato') === false);
        $dichiarazione->reportIsa = trim(str_replace('Elaborazione terminata.', '', str_replace("\r\n", "\n", $esito['report']))) ?: null;
        $accettata = (new QuadriDichiarazione($dichiarazione, $this->dataMapper))->get(ConcordatoIsa::ACCETTAZIONE, 1) !== null;
        $dichiarazione->calcoloIsa = $elaborato ? ($accettata ? self::aggiungiAccettazione($esito['output']) : $esito['output']) : null;
        $dichiarazione->esitoIsa = $elaborato ? self::utf8($esito['xml']) : null;
        $dichiarazione->improntaIsa = $elaborato ? hash('sha256', $ingresso) : null;
        $this->dataMapper->save($dichiarazione);
        return $elaborato;
    }

    /**
     * Calcolo presente e fatto sui dati attuali dei quadri.
     */
    public function isAggiornato(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): bool
    {
        if (($dichiarazione->calcoloIsa === null) || ($dichiarazione->improntaIsa === null)) {
            return false;
        }
        try {
            return hash('sha256', (new GeneratoreIsa($this->dataMapper))->genera($dichiarazione, $quadri)) === $dichiarazione->improntaIsa;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Punteggio di affidabilità (campo IIISAAFF) del calcolo, es. "9,81".
     */
    public static function getPunteggio(Dichiarazione $dichiarazione): ?string
    {
        if (($dichiarazione->calcoloIsa === null) || (preg_match('/IIISAAFF(.{18})/', $dichiarazione->calcoloIsa, $parti) !== 1)) {
            return null;
        }
        return ltrim(trim($parti[1]), '0') === '' ? '0' : preg_replace('/^0(\d)/', '$1', trim($parti[1]));
    }

    /**
     * Messaggi del software sui dati: tipo di controllo ("***" bloccante,
     * "***C" bloccante confermabile, "*" segnalazione), codice e testo.
     *
     * @return list<array{tipo: string, codice: string, messaggio: string}>
     */
    public static function getMessaggi(Dichiarazione $dichiarazione): array
    {
        $messaggi = [];
        $tipo = '';
        $righe = array_values(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) $dichiarazione->reportIsa))));
        foreach ($righe as $indice => $riga) {
            if (preg_match('/^\((\*+C?)\)$/', $riga, $parti) === 1) {
                $tipo = $parti[1];
            } elseif ((preg_match('/ - Errore (\d+)$/', $riga, $parti) === 1) && isset($righe[$indice + 1])) {
                $messaggi[] = ['tipo' => $tipo, 'codice' => $parti[1], 'messaggio' => $righe[$indice + 1]];
            }
        }
        return $messaggi;
    }

    /**
     * Esito leggibile del calcolo dall'XML del software.
     *
     * @return array{punteggio: ?string, affidabilita: list<array{codice: string, descrizione: string, punteggio: string}>, anomalie: list<array{codice: string, descrizione: string, punteggio: string}>, modelliBusiness: list<array{descrizione: string, probabilita: string}>}|null
     */
    public static function getEsito(Dichiarazione $dichiarazione): ?array
    {
        if ($dichiarazione->esitoIsa === null) {
            return null;
        }
        $documento = new \DOMDocument();
        if (@$documento->loadXML($dichiarazione->esitoIsa) === false) {
            return null;
        }
        $xpath = new \DOMXPath($documento);
        $testo = fn(\DOMNode $nodo, string $figlio) => trim((string) $xpath->evaluate('string(' . $figlio . ')', $nodo));
        $indicatori = function (string $percorso) use ($xpath, $testo): array {
            $elenco = [];
            foreach ($xpath->query($percorso) as $nodo) {
                $codice = $testo($nodo, 'codice');
                if (($codice === '') || ($testo($nodo, 'punteggioString') === '')) {
                    continue;
                }
                $elenco[$codice] = ['codice' => $codice, 'descrizione' => $testo($nodo, 'descrizione') ?: $testo($nodo, 'nome'), 'punteggio' => $testo($nodo, 'punteggioString')];
            }
            return array_values($elenco);
        };
        $modelli = [];
        foreach ($xpath->query('//quadroProbabilitaAssegnazioneMoB/probabilitaAssegnazioneMobBean') as $nodo) {
            $modelli[] = ['descrizione' => $testo($nodo, 'progressivo') . ' - ' . $testo($nodo, 'descrizione'), 'probabilita' => $testo($nodo, 'valoreFormattato')];
        }
        usort($modelli, fn(array $a, array $b) => (float) str_replace(',', '.', $b['probabilita']) <=> (float) str_replace(',', '.', $a['probabilita']));
        return [
            'punteggio' => trim((string) $xpath->evaluate('string(//quadroIndicatoreSintetico/ISA/punteggioString)')) ?: null,
            'affidabilita' => $indicatori('//sezioneIndicatoriElementariAffidabilita//listaIndicatoriAffidabilita'),
            'anomalie' => $indicatori('//sezioneIndicatoriElementariAnomalia//*[codice]'),
            'modelliBusiness' => $modelli,
        ];
    }

    /**
     * Accettazione della proposta di concordato (P10) nei record Q
     * restituiti dal software: dopo l'ultimo campo del quadro P (o prima
     * dei risultati "I"), con i record Q ricomposti a 68 coppie.
     */
    public static function aggiungiAccettazione(string $elaborato): string
    {
        $righe = explode("\r\n", $elaborato);
        $coppie = [];
        $testa = null;
        $posizione = null;
        foreach ($righe as $riga) {
            if (str_starts_with($riga, 'Q')) {
                $testa ??= substr($riga, 0, 108);
                for ($inizio = 108; $inizio < 1876; $inizio += 26) {
                    $coppia = substr($riga, $inizio, 26);
                    if (trim(substr($coppia, 0, 8)) !== '') {
                        $coppie[] = $coppia;
                    }
                }
            }
        }
        if (($testa === null) || (count(array_filter($coppie, fn(string $coppia) => str_starts_with($coppia, ConcordatoIsa::ACCETTAZIONE))) > 0)) {
            return $elaborato;
        }
        foreach ($coppie as $indice => $coppia) {
            if (str_starts_with($coppia, 'P') || (($posizione === null) && str_starts_with($coppia, 'I'))) {
                $posizione = $indice + (str_starts_with($coppia, 'P') ? 1 : 0);
            }
        }
        array_splice($coppie, $posizione ?? count($coppie), 0, [ConcordatoIsa::ACCETTAZIONE . str_pad('1', 18, ' ', STR_PAD_LEFT)]);
        $recordQ = [];
        foreach (array_chunk($coppie, GeneratoreIsa::COPPIE_Q) as $gruppo) {
            $recordQ[] = str_pad($testa . implode('', $gruppo), 1876) . str_repeat(' ', 21) . 'A';
        }
        $risultato = [];
        $inseriti = false;
        foreach ($righe as $riga) {
            if (str_starts_with($riga, 'Q')) {
                if ($inseriti === false) {
                    array_push($risultato, ...$recordQ);
                    $inseriti = true;
                }
                continue;
            }
            $risultato[] = $riga;
        }
        return implode("\r\n", $risultato);
    }

    /**
     * L'XML del software dichiara UTF-8 ma contiene caratteri accentati in
     * Windows-1252: lo si riporta in UTF-8.
     */
    private static function utf8(string $xml): string
    {
        return mb_check_encoding($xml, 'UTF-8') ? $xml : mb_convert_encoding($xml, 'UTF-8', 'Windows-1252');
    }
}
