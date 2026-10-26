<?php

namespace Telonio\Application\HelperClasses;

use SismaFramework\Core\CustomTypes\FormFilterError;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Funzioni di supporto per le viste dei moduli della suite.
 */
class Html
{

    public static function e(mixed $valore): string
    {
        return htmlspecialchars((string) $valore, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function data(?\DateTimeInterface $data, string $formato = 'd/m/Y'): string
    {
        return $data === null ? '' : $data->format($formato);
    }

    /**
     * Valore di una proprietà dell'entità (o dei dati inviati dal form)
     * nel formato atteso da un campo <input>/<select>.
     */
    public static function valore(?object $origine, string $proprieta): string
    {
        if (($origine === null) || (isset($origine->$proprieta) === false)) {
            return '';
        }
        $valore = $origine->$proprieta;
        return self::e(match (true) {
            $valore instanceof BaseEntity => $valore->id,
            $valore instanceof SismaDate => $valore->format('Y-m-d'),
            $valore instanceof \DateTimeInterface => $valore->format('Y-m-d\TH:i'),
            $valore instanceof \BackedEnum => $valore->value,
            is_bool($valore) => $valore ? '1' : '0',
            default => $valore,
        });
    }

    public static function selezionato(?object $origine, string $proprieta, string|int $opzione): string
    {
        return self::valore($origine, $proprieta) === self::e($opzione) ? 'selected' : '';
    }

    public static function spuntato(?object $origine, string $proprieta): string
    {
        return self::valore($origine, $proprieta) === '1' ? 'checked' : '';
    }

    public static function classeErrore(FormFilterError $errori, string $proprieta): string
    {
        return ($errori->{$proprieta . 'Error'} || $errori->{$proprieta . 'CustomMessage'}) ? 'is-invalid' : '';
    }

    public static function messaggioErrore(FormFilterError $errori, string $proprieta, string $messaggioPredefinito): string
    {
        $messaggio = $errori->{$proprieta . 'CustomMessage'} ?: ($errori->{$proprieta . 'Error'} ? $messaggioPredefinito : '');
        return $messaggio === '' ? '' : '<div class="invalid-feedback">' . self::e($messaggio) . '</div>';
    }
}
