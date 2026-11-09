<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Partiti politici ammessi al due per mille dell'IRPEF: tabella dell'ultima
 * pagina delle istruzioni del modello Redditi PF 2026 (fascicolo 1, p. 183),
 * per l'anno d'imposta 2025. L'elenco cambia ogni anno con il modello.
 */
class PartitiDuePerMille
{

    public const PARTITI = [
        2025 => [
            'S48' => 'Azione', 'S56' => 'Campobase', 'A10' => 'Centro Democratico', 'A52' => 'Coraggio Italia', 'A58' => 'Democrazia Cristiana con Rotondi',
            'A41' => 'Democrazia Solidale – Demo. S', 'B30' => 'Europa Verde - Verdi', 'C12' => 'Fratelli d\'Italia – Alleanza Nazionale', 'C31' => 'Italia dei Valori',
            'C46' => 'Italia Viva', 'D13' => 'Lega Nord per l\'Indipendenza della Padania', 'D43' => 'Lega per Salvini Premier', 'D57' => 'L\'Italia c\'è',
            'E14' => 'Movimento Associativo Italiani all\'Estero – MAIE', 'E54' => 'Movimento 5 Stelle', 'F15' => 'Movimento politico Forza Italia', 'E50' => 'Noi Moderati',
            'K18' => 'Partito Autonomista Trentino Tirolese', 'M20' => 'Partito Democratico', 'R22' => 'Partito Socialista Italiano', 'R45' => '+ Europa', 'S36' => 'Possibile',
            'S47' => 'Radicali italiani', 'T44' => 'Sinistra Italiana', 'U37' => 'Stella Alpina', 'U55' => 'Sud chiama Nord', 'W26' => 'Südtiroler Volkspartei',
            'Y29' => 'UDC – Unione di Centro', 'Z38' => 'Unione Sudamericana Emigrati Italiani', 'Y27' => 'Union Valdôtaine', 'Y60' => 'Verdi del Sudtirolo', 'Y59' => 'Volt Italia',
        ],
    ];

    /**
     * @return array<string, string> codice => partito (vuoto se l'anno non è gestito)
     */
    public static function getPartiti(int $anno): array
    {
        $partiti = self::PARTITI[$anno] ?? [];
        asort($partiti);
        return $partiti;
    }
}
