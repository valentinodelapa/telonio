<?php

namespace Imu\Application\Services\TabelleRiferimento;

/**
 * Elenco dei comuni nei quali i terreni agricoli sono esenti perché
 * ricadenti in aree montane o di collina, allegato alla circolare del
 * Ministero delle finanze n. 9 del 14 giugno 1993 (Gazzetta Ufficiale n. 141
 * del 18 giugno 1993, supplemento ordinario n. 53), richiamata dall'art. 1,
 * comma 758, lett. d), L. 160/2019 e ancora unico riferimento (risoluzione
 * n. 1/DF del 1° aprile 2026).
 *
 * Il testo è letto dalla Gazzetta Ufficiale (versione HTML dell'atto,
 * allegato diviso in parti): comuni per provincia, "<PD>" per quelli
 * parzialmente delimitati. Si ferma se il formato cambia.
 */
class CircolareTerreniMontani
{

    public const URL_ATTO = 'https://www.gazzettaufficiale.it/eli/id/1993/06/18/093A3466/sg';
    private const SITO = 'https://www.gazzettaufficiale.it';
    private const MINIMO_VOCI = 5000;
    private const MINIMO_PROVINCE = 80;

    /** @var \CurlHandle */
    private $curl;

    public function __construct(private ?string $cartellaCopia = null)
    {

    }

    /**
     * @return array<int, array{provincia: string, comune: string, parziale: bool}>
     */
    public function leggi(): array
    {
        $copia = $this->cartellaCopia === null ? null : rtrim($this->cartellaCopia, '/') . '/circolare-9-1993.txt';
        if (($copia !== null) && is_file($copia)) {
            $testo = file_get_contents($copia);
        } else {
            $testo = $this->scarica();
            if ($copia !== null) {
                file_put_contents($copia, $testo);
            }
        }
        return $this->interpreta($testo);
    }

    private function scarica(): string
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_COOKIEFILE => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Telonio tabelle-imu)']);
        $atto = $this->get(self::URL_ATTO);
        if (preg_match('~dataPubblicazioneGazzetta=([\d-]+)&(?:amp;)?atto\.codiceRedazionale=(\w+)~', $atto, $estremi) !== 1) {
            throw new \RuntimeException('Estremi dell\'atto non trovati nella Gazzetta Ufficiale.');
        }
        $albero = $this->get(self::SITO . '/atto/serie_generale/caricaAlberoArticoli/originario?atto.dataPubblicazioneGazzetta=' . $estremi[1] . '&atto.codiceRedazionale=' . $estremi[2]);
        preg_match_all('~<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>~s', $albero, $link, PREG_SET_ORDER);
        $testo = '';
        foreach ($link as [, $indirizzo, $etichetta]) {
            if (str_contains(strip_tags($etichetta), 'parte')) {
                $parte = str_replace('<PD>', '[PD]', $this->get(self::SITO . html_entity_decode($indirizzo)));
                $testo .= "\n" . html_entity_decode(strip_tags($parte), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        if ($testo === '') {
            throw new \RuntimeException('Parti dell\'allegato non trovate nella Gazzetta Ufficiale.');
        }
        return $testo;
    }

    private function get(string $url): string
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        $risposta = curl_exec($this->curl);
        if (($risposta === false) || (curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE) !== 200)) {
            throw new \RuntimeException('Download non riuscito: ' . $url);
        }
        usleep(200000);
        return $risposta;
    }

    private function interpreta(string $testo): array
    {
        if (mb_check_encoding($testo, 'UTF-8') === false) {
            $testo = mb_convert_encoding($testo, 'UTF-8', 'Windows-1252');
        }
        $provincia = null;
        $voci = [];
        foreach (explode("\n", str_replace("\r", '', $testo)) as $riga) {
            $riga = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $riga);
            if (($riga === '') || str_starts_with($riga, '_') || str_contains($riga, 'Annotazione') || str_starts_with($riga, '(') || ($riga === 'Legenda')) {
                continue;
            }
            if (preg_match('/^PROVINCIA (?:DI |DELL\'|DEL |DELLA )(.+)$/u', $riga, $intestazione) === 1) {
                $provincia = trim($intestazione[1]);
                continue;
            }
            if ($provincia === null) {
                continue;
            }
            $parziale = str_ends_with($riga, '[PD]');
            $nome = trim(str_replace('[PD]', '', $riga));
            if (preg_match('/^[A-Z0-9\'’ .\-\/()ÀÈÉÌÒÙ]+$/u', $nome) !== 1) {
                throw new \RuntimeException('Riga non riconosciuta nell\'elenco (provincia ' . $provincia . '): ' . $riga);
            }
            $voci[] = ['provincia' => $provincia, 'comune' => $nome, 'parziale' => $parziale];
        }
        $province = count(array_unique(array_column($voci, 'provincia')));
        if ((count($voci) < self::MINIMO_VOCI) || ($province < self::MINIMO_PROVINCE)) {
            throw new \RuntimeException(sprintf('Elenco incompleto: %d comuni in %d province.', count($voci), $province));
        }
        return $voci;
    }
}
