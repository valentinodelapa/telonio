<?php

namespace Telonio\Application\Services\Aggiornamenti;

use Telonio\Application\Enumerations\CanaleAggiornamenti;

/**
 * Verifica se è disponibile una versione della suite più recente di quella
 * installata, nel canale configurato (CANALE_AGGIORNAMENTI in .env; se
 * assente, il canale della versione installata).
 *
 * Per l'avviso in scrivania l'elenco delle release è tenuto in cache per 12
 * ore (un'ora dopo un errore di rete), così GitHub viene interrogato di rado
 * e una connessione assente non rallenta le pagine. Il controllo si disattiva
 * con CONTROLLO_AGGIORNAMENTI=false.
 */
class ControlloAggiornamenti
{

    private const DURATA_CACHE = 43200;
    private const DURATA_CACHE_ERRORE = 3600;

    private Versione $versioneInstallata;
    private CanaleAggiornamenti $canale;
    private string $fileCache;

    public function __construct(
            private ArchivioRelease $archivio = new ArchivioRelease(),
            ?Versione $versioneInstallata = null,
            ?CanaleAggiornamenti $canale = null,
            ?string $fileCache = null,
            private ?bool $attivo = null,
    ) {
        $this->fileCache = $fileCache ?? \Config\REFERENCE_CACHE_DIRECTORY . 'aggiornamenti.json';
        $this->attivo ??= (getenv('CONTROLLO_AGGIORNAMENTI') === false) || filter_var(getenv('CONTROLLO_AGGIORNAMENTI'), FILTER_VALIDATE_BOOLEAN);
        try {
            $this->versioneInstallata = $versioneInstallata ?? self::leggiVersioneInstallata();
            $this->canale = $canale ?? self::leggiCanale() ?? CanaleAggiornamenti::perVersione($this->versioneInstallata);
        } catch (\Throwable) {
            // Configurazione non leggibile: l'avviso in scrivania si disattiva,
            // il comando "sisma aggiornamenti" segnala l'errore.
            $this->versioneInstallata = $versioneInstallata ?? Versione::daTesto('0.0.0');
            $this->canale = CanaleAggiornamenti::stabile;
            $this->attivo = false;
        }
    }

    public static function leggiVersioneInstallata(?string $fileComposer = null): Versione
    {
        $dati = json_decode((string) @file_get_contents($fileComposer ?? \Config\ROOT_PATH . 'composer.json'), true);
        return Versione::daTesto((string) ($dati['version'] ?? ''));
    }

    public static function leggiCanale(): ?CanaleAggiornamenti
    {
        $valore = trim((string) getenv('CANALE_AGGIORNAMENTI'));
        if ($valore === '') {
            return null;
        }
        return CanaleAggiornamenti::tryFrom(strtolower($valore))
                ?? throw new \InvalidArgumentException('CANALE_AGGIORNAMENTI non valido: ' . $valore . ' (alpha, beta, rc o stabile)');
    }

    public function getVersioneInstallata(): Versione
    {
        return $this->versioneInstallata;
    }

    public function getCanale(): CanaleAggiornamenti
    {
        return $this->canale;
    }

    public function isAttivo(): bool
    {
        return $this->attivo;
    }

    /**
     * Ultima release del canale successiva alla versione installata.
     *
     * @param Release[] $release
     */
    public function scegli(array $release, ?CanaleAggiornamenti $canale = null): ?Release
    {
        $canale ??= $this->canale;
        $scelta = null;
        foreach ($release as $candidata) {
            if ($canale->accetta($candidata->versione) && $candidata->versione->isSuccessivaA($this->versioneInstallata)
                    && (($scelta === null) || $candidata->versione->isSuccessivaA($scelta->versione))) {
                $scelta = $candidata;
            }
        }
        return $scelta;
    }

    /**
     * Interroga sempre GitHub e aggiorna la cache (riga di comando).
     */
    public function verifica(?CanaleAggiornamenti $canale = null): ?Release
    {
        $release = $this->archivio->getRelease();
        $this->scriviCache($release);
        return $this->scegli($release, $canale);
    }

    /**
     * Usa la cache se recente; senza connessione restituisce null (scrivania).
     */
    public function verificaConCache(int $adesso = 0): ?Release
    {
        if ($this->attivo === false) {
            return null;
        }
        $adesso = $adesso ?: time();
        $cache = $this->leggiCache();
        if (($cache !== null) && ($adesso - $cache['controllato'] < ($cache['errore'] ? self::DURATA_CACHE_ERRORE : self::DURATA_CACHE))) {
            return $this->scegli(array_map(Release::daArray(...), $cache['release']));
        }
        try {
            $release = $this->archivio->getRelease();
            $this->scriviCache($release, false, $adesso);
            return $this->scegli($release);
        } catch (\Throwable) {
            $this->scriviCache($cache === null ? [] : array_map(Release::daArray(...), $cache['release']), true, $adesso);
            return $cache === null ? null : $this->scegli(array_map(Release::daArray(...), $cache['release']));
        }
    }

    private function leggiCache(): ?array
    {
        $dati = is_file($this->fileCache) ? json_decode((string) file_get_contents($this->fileCache), true) : null;
        if ((is_array($dati) === false) || (isset($dati['controllato'], $dati['release']) === false)) {
            return null;
        }
        $dati['errore'] = (bool) ($dati['errore'] ?? false);
        return $dati;
    }

    /**
     * @param Release[] $release
     */
    private function scriviCache(array $release, bool $errore = false, int $adesso = 0): void
    {
        $contenuto = json_encode([
            'controllato' => $adesso ?: time(),
            'errore' => $errore,
            'release' => array_map(fn(Release $voce) => $voce->toArray(), $release),
        ]);
        @file_put_contents($this->fileCache, $contenuto, LOCK_EX);
    }
}
