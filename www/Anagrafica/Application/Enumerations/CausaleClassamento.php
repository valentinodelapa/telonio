<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Causale di una versione dei dati catastali. Serve a chi applica le
 * rendite con regole di decorrenza diverse dalla data catastale: per l'IMU
 * valgono le rendite vigenti al 1° gennaio, salvo le variazioni per
 * interventi edilizi, efficaci dall'ultimazione dei lavori o dall'utilizzo
 * (art. 1, c. 745, L. 160/2019).
 *
 * La correzione di un dato inserito male, o della rendita proposta
 * rettificata dall'Agenzia, non è una variazione: si rettifica la versione.
 * La rendita rettificata, una volta notificata, vale anche per i periodi
 * anteriori ancora aperti (Cass. SS.UU. n. 3160/2011; circolare Agenzia
 * delle Entrate n. 7/E del 2022).
 */
enum CausaleClassamento: string
{

    case costituzione = 'C';
    case interventiEdilizi = 'E';
    case altraVariazione = 'V';

    public function getLabel(): string
    {
        return match ($this) {
            self::costituzione => 'Costituzione o primo classamento registrato',
            self::interventiEdilizi => 'Variazione per interventi edilizi',
            self::altraVariazione => 'Altra variazione',
        };
    }

    public function getDescrizione(): string
    {
        return match ($this) {
            self::costituzione => 'Nuova unità o primo classamento conosciuto dell\'immobile',
            self::interventiEdilizi => 'Ampliamento, frazionamento, fusione, ristrutturazione, cambio di destinazione d\'uso: decorrenza dall\'ultimazione dei lavori o, se prima, dall\'utilizzo',
            self::altraVariazione => 'Revisione o classamento d\'ufficio, variazione di qualità o coltura e ogni variazione non dovuta a interventi edilizi',
        };
    }

    /**
     * Le variazioni per interventi edilizi riguardano solo i fabbricati.
     *
     * @return self[]
     */
    public static function getAmmesse(TipoCatasto $tipoCatasto): array
    {
        return $tipoCatasto === TipoCatasto::fabbricati ? self::cases() : [self::costituzione, self::altraVariazione];
    }
}
