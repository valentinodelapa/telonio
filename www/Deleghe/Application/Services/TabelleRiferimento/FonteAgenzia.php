<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

/**
 * Accesso alle pagine del servizio "Ricerca codici tributo" dell'Agenzia
 * delle Entrate. Con una cartella di copia le pagine scaricate vengono
 * salvate e, se già presenti, rilette da lì: l'import si può ripetere
 * senza nuovi download (es. per rigenerare lo script SQL).
 *
 * @see https://www1.agenziaentrate.gov.it/servizi/codici/ricerca/
 */
class FonteAgenzia
{

    public const URL = 'https://www1.agenziaentrate.gov.it/servizi/codici/ricerca/';
    private const PAUSA_MICROSECONDI = 200000;

    private int $scaricate = 0;

    public function __construct(private ?string $cartellaCopia = null)
    {
        if (($cartellaCopia !== null) && (is_dir($cartellaCopia) === false) && (mkdir($cartellaCopia, 0775, true) === false)) {
            throw new \RuntimeException('Impossibile creare la cartella ' . $cartellaCopia);
        }
    }

    /**
     * @param array<string, string> $parametri parametri del form (POST) o della query (GET)
     */
    public function leggi(string $pagina, array $parametri, bool $post = false): string
    {
        $file = $this->cartellaCopia === null ? null : $this->cartellaCopia . '/' . preg_replace('/\W+/', '-', $pagina . '-' . http_build_query($parametri)) . '.html';
        if (($file !== null) && is_file($file)) {
            return file_get_contents($file);
        }
        if ($this->scaricate++ > 0) {
            usleep(self::PAUSA_MICROSECONDI);
        }
        $url = self::URL . $pagina . ($post ? '' : '?' . http_build_query($parametri));
        $opzioni = ['timeout' => 60, 'user_agent' => 'Mozilla/5.0 (Telonio codici-tributo)'];
        if ($post) {
            $opzioni += ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($parametri)];
        }
        $html = @file_get_contents($url, false, stream_context_create(['http' => $opzioni]));
        if ($html === false) {
            throw new \RuntimeException('Download non riuscito: ' . $url . ' ' . http_build_query($parametri));
        }
        if ($file !== null) {
            file_put_contents($file, $html);
        }
        return $html;
    }

    public function getScaricate(): int
    {
        return $this->scaricate;
    }
}
