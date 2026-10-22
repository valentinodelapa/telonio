<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Diritto reale (o titolo equiparato) con cui un soggetto possiede un
 * immobile.
 */
enum TitoloPossesso: string
{

    case proprieta = 'PRO';
    case nudaProprieta = 'NUD';
    case usufrutto = 'USU';
    case abitazione = 'ABI';
    case uso = 'USO';
    case superficie = 'SUP';
    case enfiteusi = 'ENF';
    case locazioneFinanziaria = 'LEA';

    public function getLabel(): string
    {
        return match ($this) {
            self::proprieta => 'Proprietà',
            self::nudaProprieta => 'Nuda proprietà',
            self::usufrutto => 'Usufrutto',
            self::abitazione => 'Diritto di abitazione',
            self::uso => 'Diritto d\'uso',
            self::superficie => 'Diritto di superficie',
            self::enfiteusi => 'Enfiteusi',
            self::locazioneFinanziaria => 'Locazione finanziaria (leasing)',
        };
    }

    /**
     * Componenti del diritto pieno coperte dal titolo: la somma delle quote
     * di ciascuna componente non può superare il 100%. La piena proprietà
     * comprende sia la titolarità (dominio) sia il godimento; la nuda
     * proprietà solo il dominio; usufrutto, uso e abitazione solo il
     * godimento.
     *
     * @return string[]
     */
    public function getComponenti(): array
    {
        return match ($this) {
            self::proprieta => ['dominio', 'godimento'],
            self::nudaProprieta => ['dominio'],
            self::usufrutto, self::abitazione, self::uso => ['godimento'],
            default => [$this->value],
        };
    }
}
