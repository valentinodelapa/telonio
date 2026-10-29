<?php

namespace Telonio\Application\Services\Aggiornamenti;

/**
 * Elenco delle release della suite dall'API pubblica di GitHub. Sono
 * considerate solo le release pubblicate, con tag in formato semver e con
 * l'archivio zip della versione e la sua impronta SHA-256.
 *
 * @see https://docs.github.com/rest/releases/releases#list-releases
 */
class ArchivioRelease
{

    public const URL = 'https://api.github.com/repos/valentinodelapa/telonio/releases?per_page=100';
    private const TIMEOUT = 5;

    public function __construct(private string $url = self::URL)
    {

    }

    /**
     * @return Release[]
     */
    public function getRelease(): array
    {
        return $this->leggi($this->scarica());
    }

    /**
     * @return Release[]
     */
    public function leggi(string $json): array
    {
        $dati = json_decode($json, true);
        if (is_array($dati) === false || array_is_list($dati) === false) {
            throw new \UnexpectedValueException('Risposta inattesa dall\'elenco delle release.');
        }
        $release = [];
        foreach ($dati as $voce) {
            $versione = Versione::tenta((string) ($voce['tag_name'] ?? ''));
            if (($versione === null) || ($voce['draft'] ?? true)) {
                continue;
            }
            $archivio = $this->cercaAllegato($voce['assets'] ?? [], $voce['tag_name'] . '.zip');
            if (($archivio === null) || (preg_match('/^sha256:([0-9a-f]{64})$/', (string) ($archivio['digest'] ?? ''), $impronta) !== 1)) {
                continue;
            }
            $attestazione = $this->cercaAllegato($voce['assets'], $voce['tag_name'] . '.zip.intoto.jsonl');
            $release[] = new Release($versione, $voce['tag_name'], (string) $voce['html_url'], substr((string) $voce['published_at'], 0, 10),
                    $archivio['browser_download_url'], $impronta[1], $attestazione['browser_download_url'] ?? null);
        }
        return $release;
    }

    private function cercaAllegato(array $allegati, string $finale): ?array
    {
        foreach ($allegati as $allegato) {
            if (str_ends_with((string) ($allegato['name'] ?? ''), $finale) && str_starts_with((string) ($allegato['browser_download_url'] ?? ''), 'https://')) {
                return $allegato;
            }
        }
        return null;
    }

    private function scarica(): string
    {
        $contesto = stream_context_create(['http' => [
                'timeout' => self::TIMEOUT,
                'user_agent' => 'Telonio',
                'header' => "Accept: application/vnd.github+json\r\n",
                'ignore_errors' => false,
        ]]);
        $contenuto = @file_get_contents($this->url, false, $contesto);
        if ($contenuto === false) {
            throw new \RuntimeException('Elenco delle release non raggiungibile: ' . $this->url);
        }
        return $contenuto;
    }
}
