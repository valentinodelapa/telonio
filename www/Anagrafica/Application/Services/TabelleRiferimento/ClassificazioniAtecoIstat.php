<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Enumerations\ClassificazioneAteco;

/**
 * Lettura delle classificazioni ATECO pubblicate dall'ISTAT:
 * - ATECO 2025: struttura completa con codice padre;
 * - ATECO 2007 aggiornamento 2022: dalla tabella operativa di
 *   riclassificazione verso ATECO 2025, che fornisce anche il raccordo.
 *
 * @see https://www.istat.it/classificazione/ateco-2025/
 */
class ClassificazioniAtecoIstat
{

    public const URL_ATECO_2025 = 'https://www.istat.it/wp-content/uploads/2024/12/StrutturaATECO-2025-IT-EN-1.xlsx';
    public const URL_RICLASSIFICAZIONE_2022 = 'https://www.istat.it/wp-content/uploads/2026/07/Aggiornamento-2026-Tabella-operativa-riclassificazione-ATECO-2022-ATECO-2025.xlsx';
    public const COLONNE = ['classificazione', 'codice', 'livello', 'codice_padre', 'titolo', 'codice_raccordo'];
    private const FOGLIO_2025 = 'ATECO 2025 Struttura';
    private const FOGLIO_2022 = 'Tabella operativa';
    private const LIVELLO_SOTTOCATEGORIA = 6;
    private const MINIMO_SOTTOCATEGORIE = 1000;

    public function __construct(private LettoreXlsx $lettoreXlsx = new LettoreXlsx())
    {

    }

    /**
     * @return array<array<string, string|int|null>>
     */
    public function leggi(string $percorsoAteco2025, string $percorsoRiclassificazione2022): array
    {
        $ateco2025 = $this->leggiAteco2025($percorsoAteco2025);
        $sottocategorie2025 = array_flip(array_column(array_filter($ateco2025, fn($riga) => $riga['livello'] === self::LIVELLO_SOTTOCATEGORIA), 'codice'));
        $ateco2022 = $this->leggiAteco2022($percorsoRiclassificazione2022, $sottocategorie2025);
        foreach ([$ateco2025, $ateco2022] as $classificazione) {
            if (count(array_filter($classificazione, fn($riga) => $riga['livello'] === self::LIVELLO_SOTTOCATEGORIA)) < self::MINIMO_SOTTOCATEGORIE) {
                throw new \UnexpectedValueException('Classificazione ATECO incompleta.');
            }
        }
        return array_merge($ateco2022, $ateco2025);
    }

    private function leggiAteco2025(string $percorso): array
    {
        $righe = [];
        foreach ($this->lettoreXlsx->leggi($percorso, self::FOGLIO_2025, ['CODICE_ATECO_2025', 'TITOLO_ITALIANO_ATECO_2025', 'GERARCHIA_ATECO_2025', 'CODICE_PADRE_ATECO_2025']) as $campi) {
            if ($campi['CODICE_ATECO_2025'] === '') {
                continue;
            }
            $righe[] = $this->generaRiga(ClassificazioneAteco::ateco2025, $campi['CODICE_ATECO_2025'], (int) $campi['GERARCHIA_ATECO_2025'], $campi['CODICE_PADRE_ATECO_2025'], $campi['TITOLO_ITALIANO_ATECO_2025'], null);
        }
        return $righe;
    }

    /**
     * Il padre si ricava dall'ordine dell'albero (ogni codice segue il suo
     * antenato più vicino). Il raccordo è la sottocategoria ATECO 2025
     * indicata dal sistema camerale o, in mancanza, quella rappresentativa:
     * è un suggerimento da confermare, non una conversione automatica.
     */
    private function leggiAteco2022(string $percorso, array $sottocategorie2025): array
    {
        $righe = [];
        $ultimoPerLivello = [];
        foreach ($this->lettoreXlsx->leggi($percorso, self::FOGLIO_2022, ['CODICE_ATECO_2022', 'TITOLO_ATECO_2022', 'GERARCHIA_ATECO_2022', 'CODICE_ATECO_2025_RAPPRESENTATIVO', 'CODICE_ATECO_2025_RAPPRESENTATIVO_SISTEMA_CAMERALE']) as $campi) {
            if ($campi['CODICE_ATECO_2022'] === '') {
                continue;
            }
            $livello = (int) $campi['GERARCHIA_ATECO_2022'];
            $ultimoPerLivello[$livello] = $campi['CODICE_ATECO_2022'];
            $raccordo = null;
            if ($livello === self::LIVELLO_SOTTOCATEGORIA) {
                foreach ([$campi['CODICE_ATECO_2025_RAPPRESENTATIVO_SISTEMA_CAMERALE'], $campi['CODICE_ATECO_2025_RAPPRESENTATIVO']] as $candidato) {
                    if (isset($sottocategorie2025[$candidato])) {
                        $raccordo = $candidato;
                        break;
                    }
                }
            }
            $righe[] = $this->generaRiga(ClassificazioneAteco::ateco2022, $campi['CODICE_ATECO_2022'], $livello, $ultimoPerLivello[$livello - 1] ?? null, $campi['TITOLO_ATECO_2022'], $raccordo);
        }
        return $righe;
    }

    private function generaRiga(ClassificazioneAteco $classificazione, string $codice, int $livello, ?string $codicePadre, string $titolo, ?string $raccordo): array
    {
        if (($livello < 1) || ($livello > self::LIVELLO_SOTTOCATEGORIA) || ($titolo === '')) {
            throw new \UnexpectedValueException('Riga ATECO non valida: ' . $classificazione->value . ' ' . $codice);
        }
        return [
            'classificazione' => $classificazione->value,
            'codice' => $codice,
            'livello' => $livello,
            'codice_padre' => ($codicePadre === null || $codicePadre === '') ? null : $codicePadre,
            'titolo' => $titolo,
            'codice_raccordo' => $raccordo,
        ];
    }
}
