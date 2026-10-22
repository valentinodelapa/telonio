<?php

namespace CommSuite\Application\HelperClasses;

/**
 * Il framework risolve le viste nel modulo del controller corrente: i moduli
 * applicativi includono il layout comune della suite tramite questo percorso.
 *
 * Uso in una vista: <?php require Layout::percorso('header'); ?>
 */
class Layout
{

    public static function percorso(string $parte): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $parte . '.php';
    }
}
