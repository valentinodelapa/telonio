<?php

namespace Telonio\Application\HelperClasses;

/**
 * Versioni installate da mostrare nell'interfaccia: quella della suite
 * (www/composer.json) e quelle dei moduli (<Modulo>/module.json). I file
 * sono letti una volta per richiesta.
 */
class Versioni
{

    private static array $letture = [];

    public static function getSuite(?string $radice = null): ?string
    {
        return self::leggi(($radice ?? \Config\ROOT_PATH) . 'composer.json');
    }

    public static function getModulo(string $modulo, ?string $radice = null): ?string
    {
        return self::leggi(($radice ?? \Config\ROOT_PATH) . $modulo . DIRECTORY_SEPARATOR . 'module.json');
    }

    public static function svuota(): void
    {
        self::$letture = [];
    }

    private static function leggi(string $file): ?string
    {
        if (array_key_exists($file, self::$letture) === false) {
            $dati = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $versione = is_array($dati) ? ($dati['version'] ?? null) : null;
            self::$letture[$file] = is_string($versione) && ($versione !== '') ? $versione : null;
        }
        return self::$letture[$file];
    }
}
