<?php

namespace Telonio\Application\Services\Aggiornamenti;

/**
 * Connessione al database con un utente che può modificarne la struttura
 * (root: l'utente dell'applicazione ha solo i permessi DML), per eseguire gli
 * script SQL dei moduli e tenere il registro degli aggiornamenti applicati.
 * La password è DB_ROOT_PASSWORD, già passata al container da .env.
 */
class DatabaseAmministrazione
{

    private ?\mysqli $connessione = null;

    public function __construct(
            private string $utente = 'root',
            private ?string $password = null,
            private ?string $database = null,
    ) {

    }

    public function getDatabase(): string
    {
        return $this->database ?? \Config\DATABASE_NAME;
    }

    /**
     * @return string[]
     */
    public function getTabelle(): array
    {
        return array_column($this->getConnessione()->query('SHOW TABLES')->fetch_all(), 0);
    }

    /**
     * @return string[] "<Modulo>/<script>" già registrati
     */
    public function getScriptEseguiti(): array
    {
        if (in_array('aggiornamento_database', $this->getTabelle(), true) === false) {
            return [];
        }
        return array_column($this->getConnessione()->query("SELECT CONCAT(`modulo`, '/', `script`) FROM `aggiornamento_database`")->fetch_all(), 0);
    }

    public function registra(string $modulo, string $script): void
    {
        $istruzione = $this->getConnessione()->prepare('INSERT IGNORE INTO `aggiornamento_database` (`modulo`, `script`, `eseguito_il`) VALUES (?, ?, NOW())');
        $istruzione->bind_param('ss', $modulo, $script);
        $istruzione->execute();
    }

    /**
     * Esegue uno script con più istruzioni e restituisce i risultati delle
     * SELECT (gli elenchi di controllo stampati dagli script di aggiornamento).
     *
     * @return array<int, array{colonne: string[], righe: array}>
     */
    public function esegui(string $sql): array
    {
        $connessione = $this->getConnessione();
        $risultati = [];
        $numero = 1;
        try {
            $connessione->multi_query($sql);
            do {
                $risultato = $connessione->store_result();
                if ($risultato instanceof \mysqli_result) {
                    if ($risultato->num_rows > 0) {
                        $risultati[] = ['colonne' => array_column($risultato->fetch_fields(), 'name'), 'righe' => $risultato->fetch_all()];
                    }
                    $risultato->free();
                }
                $numero++;
            } while ($connessione->more_results() && $connessione->next_result());
        } catch (\mysqli_sql_exception $errore) {
            while ($connessione->more_results() && @$connessione->next_result()) {
                // scarta i risultati rimasti per poter riusare la connessione
            }
            throw new \RuntimeException(sprintf('Errore all\'istruzione n. %d: %s', $numero, $errore->getMessage()), 0, $errore);
        }
        return $risultati;
    }

    private function getConnessione(): \mysqli
    {
        if ($this->connessione === null) {
            $password = $this->password ?? getenv('DB_ROOT_PASSWORD');
            if (($password === false) || ($password === '')) {
                throw new \RuntimeException('Password dell\'utente ' . $this->utente . ' non disponibile: impostare DB_ROOT_PASSWORD in .env.');
            }
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->connessione = new \mysqli(\Config\DATABASE_HOST, $this->utente, $password, $this->getDatabase(), (int) \Config\DATABASE_PORT);
            $this->connessione->set_charset('utf8mb4');
        }
        return $this->connessione;
    }
}
