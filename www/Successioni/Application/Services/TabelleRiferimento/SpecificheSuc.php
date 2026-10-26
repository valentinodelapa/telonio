<?php

namespace Successioni\Application\Services\TabelleRiferimento;

use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\DirittoAbitazione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\ResidenzaEsteraDefunto;
use Successioni\Application\Enumerations\RiduzioneSuccessioniPrecedenti;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoDocumentoTitolo;
use Successioni\Application\Enumerations\TipoImpostaCredito;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Enumerations\TipoUnita;

/**
 * Specifiche tecniche della dichiarazione di successione (fornitura SUC13)
 * pubblicate dall'Agenzia delle Entrate: archivio ZIP con gli XML Schema.
 *
 * - aggiorna gli XSD del modulo (Application/Assets/xsd), usati per
 *   validare il file telematico prima del controllo con il software
 *   dell'Agenzia;
 * - verifica che i codici degli enum del modulo coincidano con le
 *   enumerazioni dell'XSD (gradi di parentela, codici diritto,
 *   agevolazioni, ...): se l'Agenzia aggiunge o toglie codici, il comando
 *   segnala gli enum da aggiornare;
 * - legge la tabella degli uffici di registrazione (codice e descrizione,
 *   nella documentazione dell'enumerazione UfficiDiRegistrazione).
 *
 * @see https://www.agenziaentrate.gov.it/portale/schede/dichiarazioni/dichiarazione-di-successione/specifiche-tecniche-dichiarazione-di-successione
 */
class SpecificheSuc
{

    public const URL_PAGINA = 'https://www.agenziaentrate.gov.it/portale/schede/dichiarazioni/dichiarazione-di-successione/specifiche-tecniche-dichiarazione-di-successione';
    public const XSD_FORNITURA = 'SUC/xsd/fornituraSUC13_v1.xsd';
    public const XSD_REGISTRO = 'registro/xsd/typesReg_v2.xsd';
    public const COLONNE_UFFICI = ['codice', 'denominazione', 'periodo'];
    public const COLONNE_CODICE_DENOMINAZIONE = ['codice', 'denominazione'];

    /**
     * Enum del modulo e tipi dell'XSD con gli stessi codici (più tipi se
     * l'enum li riunisce; valori da escludere dal confronto).
     */
    private const CONFRONTI = [
        GradoParentela::class => ['tipi' => ['GradoParentela'], 'esclusi' => []],
        CodiceDiritto::class => ['tipi' => ['CodiciDirittoConAsterisco', 'CodiciDirittoSenzaAsterisco', 'CodiciDirittoTerreni'], 'esclusi' => []],
        Agevolazione::class => ['tipi' => ['CodiciAgevolazioni', 'CodiciRiduzioni25AltriComma'], 'esclusi' => []],
        TipoBeneficiario::class => ['tipi' => ['TipoSoggetto'], 'esclusi' => []],
        CaricaDichiarante::class => ['tipi' => ['CodiciCarica'], 'esclusi' => []],
        StatoCivileDefunto::class => ['tipi' => ['StatoCivile'], 'esclusi' => []],
        ResidenzaEsteraDefunto::class => ['tipi' => ['ResidenzaEstera'], 'esclusi' => []],
        TipoPassivita::class => ['tipi' => ['CodiciPassivita'], 'esclusi' => []],
        TipoCespite::class => ['tipi' => ['TipoCespite'], 'esclusi' => []],
        TipoTitolo::class => ['tipi' => ['Titoli_Type'], 'esclusi' => []],
        TipoDocumentoTitolo::class => ['tipi' => ['TipoDocumento'], 'esclusi' => []],
        DirittoAbitazione::class => ['tipi' => ['DirittoAbitazione_Type'], 'esclusi' => []],
        RiduzioneSuccessioniPrecedenti::class => ['tipi' => ['CodiciRiduzioni25comma1'], 'esclusi' => []],
        TempisticaPagamento::class => ['tipi' => ['TempisticaPagamento'], 'esclusi' => []],
        TipoDichiarazioneSuccessione::class => ['tipi' => ['DichiarazioneSostitutiva'], 'esclusi' => ['0']],
        TipologiaTerreno::class => ['tipi' => ['TerrenoEdificabile_Type'], 'esclusi' => []],
        TipoUnita::class => ['tipi' => ['TipoUnita_Type'], 'esclusi' => []],
        TipoImpostaCredito::class => ['tipi' => ['TipoImposta_Type'], 'esclusi' => []],
    ];

    public static function getCartellaXsd(): string
    {
        return dirname(__DIR__, 2) . '/Assets/xsd';
    }

    /**
     * Archivio ZIP delle specifiche: file locale, URL o (null) il link più
     * recente nella pagina delle specifiche tecniche.
     */
    public function scarica(?string $fonte, string $destinazione): string
    {
        if (($fonte !== null) && is_file($fonte)) {
            return $fonte;
        }
        if (($fonte !== null) && (preg_match('#^https?://#', $fonte) !== 1)) {
            throw new \RuntimeException('Archivio non trovato: ' . $fonte);
        }
        $url = $fonte ?? $this->trovaLink();
        $contenuto = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (Telonio specifiche-successioni)']]));
        if (($contenuto === false) || (str_starts_with($contenuto, 'PK') === false)) {
            throw new \RuntimeException('Download dell\'archivio delle specifiche non riuscito: ' . $url);
        }
        file_put_contents($destinazione, $contenuto);
        return $destinazione;
    }

    private function trovaLink(): string
    {
        $html = @file_get_contents(self::URL_PAGINA, false, stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'Mozilla/5.0 (Telonio specifiche-successioni)']]));
        if (($html === false) || (preg_match_all('#href="([^"]*/SUC13_(\d{8})\.zip[^"]*)"#', $html, $link, PREG_SET_ORDER) === 0)) {
            throw new \RuntimeException('Link all\'archivio SUC13 non trovato nella pagina ' . self::URL_PAGINA);
        }
        usort($link, fn(array $a, array $b) => strcmp($b[2], $a[2]));
        $url = html_entity_decode($link[0][1]);
        return str_starts_with($url, 'http') ? $url : 'https://www.agenziaentrate.gov.it' . $url;
    }

    /**
     * Estrae gli XSD dall'archivio nella cartella indicata, mantenendo la
     * struttura (gli schemi si importano con percorsi relativi).
     *
     * @return string[] file estratti
     */
    public function estrai(string $archivio, string $cartella): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivio) !== true) {
            throw new \RuntimeException('Archivio non leggibile: ' . $archivio);
        }
        $estratti = [];
        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $nome = $zip->getNameIndex($indice);
            if ((str_ends_with($nome, '.xsd') === false) || str_contains($nome, '..')) {
                continue;
            }
            $file = $cartella . '/' . $nome;
            if ((is_dir(dirname($file)) === false) && (mkdir(dirname($file), 0775, true) === false)) {
                throw new \RuntimeException('Impossibile creare la cartella ' . dirname($file));
            }
            file_put_contents($file, $zip->getFromIndex($indice));
            $estratti[] = $nome;
        }
        $zip->close();
        if (in_array(self::XSD_FORNITURA, $estratti, true) === false) {
            throw new \RuntimeException('L\'archivio non contiene ' . self::XSD_FORNITURA . ': tracciato cambiato.');
        }
        return $estratti;
    }

    /**
     * Differenze tra i codici degli enum del modulo e quelli dell'XSD.
     *
     * @return string[] una riga per enum da aggiornare
     */
    public function verificaEnum(string $cartella): array
    {
        $enumerazioni = $this->leggiEnumerazioni($cartella);
        $differenze = [];
        foreach (self::CONFRONTI as $classe => ['tipi' => $tipi, 'esclusi' => $esclusi]) {
            $xsd = [];
            foreach ($tipi as $tipo) {
                if (isset($enumerazioni[$tipo]) === false) {
                    $differenze[] = $this->nomeBreve($classe) . ': tipo ' . $tipo . ' non trovato nell\'XSD.';
                    continue 2;
                }
                array_push($xsd, ...$enumerazioni[$tipo]);
            }
            $modulo = array_diff(array_map(fn(\BackedEnum $caso) => $caso->value, $classe::cases()), $esclusi);
            $mancanti = array_diff(array_unique($xsd), $modulo);
            $inEccesso = array_diff($modulo, $xsd);
            if ((count($mancanti) > 0) || (count($inEccesso) > 0)) {
                $differenze[] = sprintf('%s: %s%s', $this->nomeBreve($classe), count($mancanti) > 0 ? 'nuovi codici ' . implode(', ', $mancanti) . ' ' : '', count($inEccesso) > 0 ? 'codici non più presenti ' . implode(', ', $inEccesso) : '');
            }
        }
        return $differenze;
    }

    /**
     * @return array<string, string[]> valori per nome del tipo semplice
     */
    private function leggiEnumerazioni(string $cartella): array
    {
        $enumerazioni = [];
        foreach ([self::XSD_FORNITURA, self::XSD_REGISTRO] as $file) {
            $documento = $this->caricaXsd($cartella . '/' . $file);
            $xpath = new \DOMXPath($documento);
            $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
            foreach ($xpath->query('/xs:schema/xs:simpleType[@name]') as $tipo) {
                $valori = [];
                foreach ($xpath->query('.//xs:enumeration', $tipo) as $valore) {
                    $valori[] = $valore->getAttribute('value');
                }
                if (count($valori) > 0) {
                    $enumerazioni[$tipo->getAttribute('name')] = $valori;
                }
            }
        }
        return $enumerazioni;
    }

    /**
     * Uffici di registrazione: codice, denominazione e periodo di
     * operatività, dalla documentazione dell'enumerazione (una riga per
     * ufficio, es. "795 Bologna 1 (dal 01/07/1997 al 01/02/2009)").
     *
     * @return array<array{codice: string, denominazione: string, periodo: ?string}>
     */
    public function leggiUffici(string $cartella): array
    {
        $documento = $this->caricaXsd($cartella . '/' . self::XSD_REGISTRO);
        $xpath = new \DOMXPath($documento);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $tipo = $xpath->query('/xs:schema/xs:simpleType[@name="UfficiDiRegistrazione"]')->item(0);
        if ($tipo === null) {
            throw new \RuntimeException('Enumerazione UfficiDiRegistrazione non trovata: tracciato cambiato.');
        }
        $codici = [];
        foreach ($xpath->query('.//xs:enumeration', $tipo) as $valore) {
            $codici[$valore->getAttribute('value')] = true;
        }
        $uffici = [];
        foreach (preg_split('/\R/', (string) $xpath->query('xs:annotation/xs:documentation', $tipo)->item(0)?->textContent) as $riga) {
            if (preg_match('/^\s*([0-9A-Z]{3,4})\s+(.+?)\s*(?:\(([^)]*)\))?\s*$/u', $riga, $parti) !== 1) {
                continue;
            }
            $uffici[$parti[1]] = ['codice' => $parti[1], 'denominazione' => mb_substr($parti[2], 0, 150), 'periodo' => ($parti[3] ?? '') === '' ? null : $parti[3]];
        }
        $senzaDescrizione = array_diff_key($codici, $uffici);
        if ((count($codici) < 500) || (count($senzaDescrizione) > 0)) {
            throw new \RuntimeException(sprintf('Tabella degli uffici incompleta: %d codici, %d senza descrizione.', count($codici), count($senzaDescrizione)));
        }
        return array_values(array_intersect_key($uffici, $codici));
    }

    /**
     * Servizi di pubblicità immobiliare (UfficiDiTrascrizione): una riga per
     * ufficio con la denominazione e il codice in fondo (es. "ACQUI TERME
     * AL10").
     *
     * @return array<array{codice: string, denominazione: string}>
     */
    public function leggiUfficiTrascrizione(string $cartella): array
    {
        $righe = [];
        foreach ($this->leggiDocumentazione($cartella, 'UfficiDiTrascrizione', $codici) as $riga) {
            if (preg_match('/^\s*(.+?)\s+([A-Z]{2}\d{2})\s*$/u', $riga, $parti) === 1) {
                $righe[$parti[2]] = ['codice' => $parti[2], 'denominazione' => $parti[1]];
            }
        }
        return $this->verificaCompleta('uffici di trascrizione', $righe, $codici, 100);
    }

    /**
     * Comuni catastali di Trento e Bolzano (ComuniCatastaliTNBZ): nome e
     * numero, codice di tre cifre.
     *
     * @return array<array{codice: string, denominazione: string}>
     */
    public function leggiComuniCatastali(string $cartella): array
    {
        $righe = [];
        foreach ($this->leggiDocumentazione($cartella, 'ComuniCatastaliTNBZ', $codici) as $riga) {
            if (preg_match('/^\s*([^\t]+?)\t+(\d+)/u', $riga, $parti) === 1) {
                $codice = sprintf('%03d', (int) $parti[2]);
                $righe[$codice] = ['codice' => $codice, 'denominazione' => mb_substr(trim($parti[1]), 0, 80)];
            }
        }
        return $this->verificaCompleta('comuni catastali', $righe, $codici, 600);
    }

    /**
     * @return string[] righe della documentazione dell'enumerazione
     */
    private function leggiDocumentazione(string $cartella, string $tipo, ?array &$codici): array
    {
        $xpath = new \DOMXPath($this->caricaXsd($cartella . '/' . self::XSD_REGISTRO));
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $nodo = $xpath->query('/xs:schema/xs:simpleType[@name="' . $tipo . '"]')->item(0);
        if ($nodo === null) {
            throw new \RuntimeException('Enumerazione ' . $tipo . ' non trovata: tracciato cambiato.');
        }
        $codici = [];
        foreach ($xpath->query('.//xs:enumeration', $nodo) as $valore) {
            $codici[$valore->getAttribute('value')] = true;
        }
        return preg_split('/\R/', (string) $xpath->query('xs:annotation/xs:documentation', $nodo)->item(0)?->textContent);
    }

    private function verificaCompleta(string $tabella, array $righe, array $codici, int $minimo): array
    {
        $senzaDescrizione = array_diff_key($codici, $righe);
        if ((count($codici) < $minimo) || (count($senzaDescrizione) > 0)) {
            throw new \RuntimeException(sprintf('Tabella dei %s incompleta: %d codici, %d senza descrizione.', $tabella, count($codici), count($senzaDescrizione)));
        }
        return array_values(array_intersect_key($righe, $codici));
    }

    private function caricaXsd(string $file): \DOMDocument
    {
        $documento = new \DOMDocument();
        if ((is_file($file) === false) || ($documento->load($file) === false)) {
            throw new \RuntimeException('XSD non leggibile: ' . $file);
        }
        return $documento;
    }

    private function nomeBreve(string $classe): string
    {
        return substr($classe, strrpos($classe, '\\') + 1);
    }
}
