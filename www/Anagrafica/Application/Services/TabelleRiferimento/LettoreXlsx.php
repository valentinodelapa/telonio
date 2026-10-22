<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

/**
 * Lettura minimale di un foglio xlsx (Office Open XML) senza librerie
 * esterne: righe indicizzate per intestazione di colonna (leggi) o per
 * posizione (leggiRighe, per i fogli con titoli o intestazioni su più
 * righe).
 */
class LettoreXlsx
{

    /**
     * @param string|null $nomeFoglio null = primo foglio
     * @param string[] $colonneRichieste intestazioni obbligatorie
     * @return array<array<string, string>>
     */
    public function leggi(string $percorso, ?string $nomeFoglio, array $colonneRichieste): array
    {
        $intestazione = null;
        $righe = [];
        foreach ($this->leggiRighe($percorso, $nomeFoglio) as $celle) {
            if ($intestazione === null) {
                $intestazione = array_map(fn($nome) => trim(preg_replace('/\s+/', ' ', $nome)), $celle);
                $mancanti = array_diff($colonneRichieste, $intestazione);
                if (count($mancanti) > 0) {
                    throw new \UnexpectedValueException('Tracciato di ' . basename($percorso) . ' cambiato, colonne mancanti: ' . implode(', ', $mancanti));
                }
                continue;
            }
            $campi = [];
            foreach ($intestazione as $colonna => $nome) {
                $campi[$nome] = trim($celle[$colonna] ?? '');
            }
            $righe[] = $campi;
        }
        return $righe;
    }

    /**
     * Righe del foglio con le celle per indice di colonna (0 = A); le righe
     * vuote nel file sono omesse.
     *
     * @return array<int, array<int, string>>
     */
    public function leggiRighe(string $percorso, ?string $nomeFoglio = null): array
    {
        $archivio = new \ZipArchive();
        if ($archivio->open($percorso) !== true) {
            throw new \RuntimeException('Impossibile aprire ' . $percorso);
        }
        $stringhe = $this->leggiStringhe($archivio);
        $xmlFoglio = $archivio->getFromName($this->percorsoFoglio($archivio, $nomeFoglio));
        $archivio->close();
        $righe = [];
        foreach (simplexml_load_string($xmlFoglio)->sheetData->row as $riga) {
            $celle = [];
            foreach ($riga->c as $cella) {
                $valore = (string) $cella->v;
                $celle[self::indiceColonna((string) $cella['r'])] = match ((string) $cella['t']) {
                    's' => $stringhe[(int) $valore],
                    'inlineStr' => (string) $cella->is->t,
                    default => $valore,
                };
            }
            $righe[] = $celle;
        }
        return $righe;
    }

    private static function indiceColonna(string $riferimento): int
    {
        $indice = 0;
        foreach (str_split(preg_replace('/\d+/', '', $riferimento)) as $lettera) {
            $indice = $indice * 26 + (ord($lettera) - 64);
        }
        return $indice - 1;
    }

    private function leggiStringhe(\ZipArchive $archivio): array
    {
        $stringhe = [];
        $xml = $archivio->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            foreach (simplexml_load_string($xml)->si as $stringa) {
                $stringhe[] = implode('', array_map('strval', $stringa->xpath('.//*[local-name()="t"]')));
            }
        }
        return $stringhe;
    }

    /**
     * Risolve il nome del foglio nel file XML tramite le relazioni del
     * workbook (l'ordine dei file non coincide sempre con quello dei fogli).
     */
    private function percorsoFoglio(\ZipArchive $archivio, ?string $nomeFoglio): string
    {
        $workbook = simplexml_load_string($archivio->getFromName('xl/workbook.xml'));
        $relazioni = simplexml_load_string($archivio->getFromName('xl/_rels/workbook.xml.rels'));
        foreach ($workbook->sheets->sheet as $foglio) {
            if (($nomeFoglio === null) || ((string) $foglio['name'] === $nomeFoglio)) {
                $idRelazione = (string) $foglio->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($relazioni->Relationship as $relazione) {
                    if ((string) $relazione['Id'] === $idRelazione) {
                        return 'xl/' . ltrim(str_replace('/xl/', '', (string) $relazione['Target']), '/');
                    }
                }
            }
        }
        throw new \UnexpectedValueException('Foglio "' . $nomeFoglio . '" non trovato');
    }
}
