<?php

namespace GestioneStudio\Application\Enumerations;

/**
 * Soggetti a cui si applica una proroga (Services\RequisitiProroga).
 *
 * - tutti: proroga generale;
 * - soggettiIsa: proroghe dei versamenti per i soggetti che applicano gli
 *   ISA (anche con cause di esclusione), i forfetari, i contribuenti in
 *   regime di vantaggio e chi partecipa a società, associazioni e imprese
 *   con questi requisiti (artt. 5, 115 e 116 TUIR), come quella dell'art.
 *   1-sexies D.L. 63/2026. Il limite dei ricavi o compensi del decreto di
 *   approvazione degli ISA è compreso nell'indicazione "applica gli ISA"
 *   dei dati fiscali.
 */
enum CondizioneProroga: string
{

    case tutti = 'TUT';
    case soggettiIsa = 'ISA';

    public function getLabel(): string
    {
        return match ($this) {
            self::tutti => 'Tutti',
            self::soggettiIsa => 'Soggetti ISA, forfetari e loro soci',
        };
    }
}
