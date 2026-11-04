<?php

namespace Dichiarativi\Application\Services\Telematico;

use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\CampoModello;

/**
 * Record a struttura variabile delle forniture delle dichiarazioni (record
 * "D" dell'IVA, "C" dei Redditi): 89 caratteri posizionali (tipo record,
 * codice fiscale del contribuente, progressivo modulo), una tabella di 75
 * elementi da 24 caratteri (campo-codice di 8 e campo-valore di 16) con i
 * soli campi valorizzati, 8 caratteri non utilizzati e i caratteri di
 * controllo. I campi di un modulo che non entrano in un record proseguono
 * in record successivi con lo stesso progressivo; i testi lunghi occupano
 * più elementi con lo stesso codice (FormatoCampo::formatta).
 */
class RecordQuadri
{

    public const ELEMENTI_PER_RECORD = 75;
    public const LUNGHEZZA_ELEMENTO = 24;
    private const INIZIO_ELEMENTI = 89;

    public const PARTE_POSIZIONALE = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'spazioUtente' => [26, 3, 'AN'],
        'filler' => [29, 25, 'AN'],
        'identificativoDichiarazione' => [54, 20, 'AN'],
        'produttoreSoftware' => [74, 16, 'AN'],
    ];

    /**
     * @param array<int, array<string, string>> $valori per modulo e codice, in forma canonica
     * @param array<string, CampoModello> $catalogo
     * @return string[] record della fornitura
     * @throws \InvalidArgumentException se un campo non è nel catalogo o non ha formato
     */
    public static function genera(string $tipo, string $codiceFiscale, array $valori, array $catalogo): array
    {
        ksort($valori);
        $record = [];
        foreach ($valori as $modulo => $campi) {
            ksort($campi);
            $elementi = [];
            foreach ($campi as $codice => $valore) {
                if ($valore === '') {
                    continue;
                }
                $formato = ($catalogo[$codice] ?? throw new \InvalidArgumentException('Campo ' . $codice . ' non previsto dal modello.'))->formato
                        ?? throw new \InvalidArgumentException('Campo ' . $codice . ': formato non indicato dalle specifiche, non trasmissibile.');
                foreach ($formato->formatta($valore) as $campoValore) {
                    $elementi[] = $codice . $campoValore;
                }
            }
            foreach (array_chunk($elementi, self::ELEMENTI_PER_RECORD) as $blocco) {
                $record[] = self::componi($tipo, $codiceFiscale, $modulo, implode('', $blocco));
            }
        }
        return $record;
    }

    private static function componi(string $tipo, string $codiceFiscale, int $modulo, string $elementi): string
    {
        $posizionale = (string) (new RecordF24(self::PARTE_POSIZIONALE))
                        ->imposta('tipoRecord', $tipo)
                        ->imposta('codiceFiscale', $codiceFiscale)
                        ->imposta('progressivoModulo', $modulo);
        $tabella = str_pad($elementi, self::ELEMENTI_PER_RECORD * self::LUNGHEZZA_ELEMENTO, ' ');
        return substr_replace($posizionale, $tabella, self::INIZIO_ELEMENTI, strlen($tabella));
    }
}
