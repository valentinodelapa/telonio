<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

/**
 * Lettura della tabella ANPR degli stati esteri (tabella 02, xlsx): codice
 * catastale Z (colonna CODAT, attribuito dall'Agenzia delle Entrate), codice
 * ISO alpha-3 e periodo di validità. Il codice alpha-2 viene ricavato dal
 * file di corrispondenza ISO del modulo.
 *
 * La tabella contiene gli stati attuali: gli stati cessati prima della sua
 * istituzione (es. Jugoslavia, URSS) non sono presenti.
 *
 * @see https://www.anagrafenazionale.interno.it/area-tecnica/tabelle-di-decodifica/
 */
class StatiEsteriAnpr
{

    public const URL = 'https://www.anagrafenazionale.interno.it/wp-content/uploads/tabella_2_statiesteri.xlsx';
    public const COLONNE = ['id_anpr', 'codice_iso', 'codice_iso3', 'codice_catastale', 'denominazione', 'valido_dal', 'valido_al'];
    private const COLONNE_FONTE = ['ID', 'DENOMINAZIONE', 'DATAINIZIOVALIDITA', 'DATAFINEVALIDITA', 'CODISO3166_1_ALPHA3', 'CODAT', 'TIPO'];
    private const TIPI_IMPORTATI = ['STATO', 'TERRITORIO', 'ALTRO'];
    private const ANNO_APERTO = '9999';

    public function __construct(private LettoreXlsx $lettoreXlsx = new LettoreXlsx())
    {

    }

    /**
     * @return array<int, array<string, string|int|null>> righe indicizzate per id_anpr
     */
    public function leggi(string $percorsoXlsx, string $percorsoIso): array
    {
        $iso = $this->leggiCorrispondenzaIso($percorsoIso);
        $righe = [];
        foreach ($this->lettoreXlsx->leggi($percorsoXlsx, null, self::COLONNE_FONTE) as $campi) {
            if (in_array($campi['TIPO'] ?? '', self::TIPI_IMPORTATI, true) === false) {
                continue;
            }
            $iso3 = strtoupper(trim($campi['CODISO3166_1_ALPHA3'] ?? ''));
            $codiceCatastale = strtoupper(trim($campi['CODAT'] ?? ''));
            $riga = [
                'id_anpr' => (int) $campi['ID'],
                'codice_iso' => $iso[$iso3] ?? null,
                'codice_iso3' => isset($iso[$iso3]) ? $iso3 : null,
                'codice_catastale' => preg_match('/^Z\d{3}$/', $codiceCatastale) === 1 ? $codiceCatastale : null,
                'denominazione' => trim($campi['DENOMINAZIONE']),
                'valido_dal' => self::convertiData($campi['DATAINIZIOVALIDITA'] ?? ''),
                'valido_al' => self::convertiData($campi['DATAFINEVALIDITA'] ?? ''),
            ];
            if (($riga['id_anpr'] === 0) || ($riga['denominazione'] === '') || ($riga['valido_dal'] === null)) {
                throw new \UnexpectedValueException('Riga non valida nella tabella stati esteri: ' . json_encode($campi, JSON_UNESCAPED_UNICODE));
            }
            $righe[$riga['id_anpr']] = $riga;
        }
        if (in_array('IT', array_column($righe, 'codice_iso'), true) === false) {
            throw new \UnexpectedValueException('Tabella stati esteri incompleta: Italia non presente.');
        }
        return $righe;
    }

    private function leggiCorrispondenzaIso(string $percorso): array
    {
        $iso = [];
        foreach (file($percorso, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (preg_match('/^([A-Z]{3});([A-Z]{2})$/', $linea, $parti) === 1) {
                $iso[$parti[1]] = $parti[2];
            }
        }
        return $iso;
    }

    /**
     * Le date sono testi "gg/mm/aaaa hh:mm:ss" oppure numeri seriali Excel.
     */
    private static function convertiData(string $valore): ?string
    {
        $valore = trim($valore);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $valore, $parti) === 1) {
            return $parti[3] === self::ANNO_APERTO ? null : $parti[3] . '-' . $parti[2] . '-' . $parti[1];
        } elseif (is_numeric($valore)) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) $valore . ' days')->format('Y-m-d');
        }
        return null;
    }
}
