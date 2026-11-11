<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia as Tipo;

/**
 * Codici ammessi, percentuale di detrazione, rateazione, numero della rata
 * e limite di spesa degli interventi sugli immobili secondo le specifiche
 * tecniche del modello Redditi PF 2026 (par. 13.1, tabelle "Limiti di spesa
 * e detrazione spettante ristrutturazione edilizia" e "Determinazione della
 * rata spettante", parti da A a F; par. 14.1-14.4 per il rigo RP56; par.
 * 15.2-15.12 per la sezione IV; controlli del rigo RP57).
 *
 * Le spese sono quelle sostenute dal 2016 (le rate delle spese precedenti
 * sono esaurite nel 2025); per il risparmio energetico la rideterminazione
 * delle rate delle spese del 2008 resta da indicare a mano nel quadro.
 */
class TabellaDetrazioniEdilizie
{

    public const ANNO_MINIMO = 2016;

    /** Percentuale delle tipologie da 5 a 11 senza la casella "Percentuale" fino al 2024 */
    private const SISMABONUS = [5 => 50.0, 6 => 70.0, 7 => 80.0, 8 => 75.0, 9 => 85.0, 10 => 75.0, 11 => 85.0];

    /**
     * Tipologie (col. 2 di RP41, col. 1 di RP56 e di RP61) ammesse per
     * l'anno della spesa.
     *
     * @return int[]
     */
    public static function tipologie(Tipo $tipo, int $anno): array
    {
        return match ($tipo) {
            Tipo::ristrutturazione => match (true) {
                $anno === 2016 => [4],
                $anno === 2017 => [],
                $anno <= 2019 => [12, 13],
                $anno === 2020 => [12, 13, 15],
                $anno === 2021 => [...range(5, 13), ...range(15, 20)],
                $anno === 2022 => [...range(5, 13), ...range(15, 22)],
                $anno <= 2024 => [...range(5, 13), ...range(16, 22)],
                default => [...range(5, 11), ...range(16, 23)],
            },
            Tipo::colonnine => array_values(array_filter([2, 3, 4, 5], fn(int $codice) => $codice === 2 ? ($anno >= 2019 && $anno <= 2021) : $anno >= 2021)),
            Tipo::risparmioEnergetico => array_values(array_filter([...range(1, 16), ...range(30, 33)], fn(int $codice) => match (true) {
                        $codice <= 6 => true,
                        $codice === 7 => $anno <= 2022,
                        $codice <= 9 => $anno >= 2017,
                        $codice <= 14 => $anno >= 2018,
                        $codice === 15 => $anno >= 2020 && $anno <= 2022,
                        $codice === 16 => $anno >= 2020,
                        default => $anno >= 2021,
                    })),
            Tipo::arredo => [],
        };
    }

    /**
     * La tipologia è obbligatoria (risparmio energetico e colonnine); per il
     * recupero edilizio "non compilata" indica gli interventi ordinari.
     */
    public static function tipologiaObbligatoria(Tipo $tipo): bool
    {
        return in_array($tipo, [Tipo::colonnine, Tipo::risparmioEnergetico], true);
    }

    /**
     * Codici della casella "Percentuale" ammessi per l'anno e la tipologia
     * (par. 13.1.1, 14.1 e 15.6).
     *
     * @return int[]
     */
    public static function codiciPercentuale(Tipo $tipo, int $anno, ?int $tipologia): array
    {
        $tra = fn(int|array ...$codici) => in_array($tipologia, array_merge(...array_map(fn(int|array $codice) => (array) $codice, $codici)), true);
        return match ($tipo) {
            Tipo::ristrutturazione => match (true) {
                $anno <= 2020 => [],
                $anno <= 2022 => $tra(range(5, 11), 20) ? [1] : [],
                $anno <= 2024 => $tra(range(5, 9), 16, 17, 18, 20) ? [1, 2] : [],
                default => array_merge($tra(range(5, 9), 16, 17, 18, 20) ? [1, 2] : [], ($tipologia === null) || $tra(range(5, 11), 19, 20) ? [3] : []),
            },
            Tipo::colonnine => match (true) {
                ($tipologia === 2) || ($anno <= 2022) => [],
                $anno === 2023 => [1, 2],
                $anno === 2024 => [1, 3],
                default => [1, 4],
            },
            Tipo::risparmioEnergetico => match (true) {
                $anno <= 2020 => [],
                $anno <= 2022 => $tra(range(2, 7), 12, 13, 14, 16) ? [1] : [],
                $anno <= 2024 => $tra(range(2, 6), 12, 13, 14, 16, range(30, 33)) ? [1, 2] : [],
                default => match (true) {
                    $tra(1, 8, 9, 10, 11) => [3],
                    $tra(range(30, 33)) => [1, 2],
                    $tra(range(2, 6), 12, 13, 14, 16) => [1, 2, 3],
                    default => [],
                },
            },
            Tipo::arredo => [],
        };
    }

    /**
     * La casella "Percentuale" è obbligatoria: tipologie da 16 a 18 dal 2024
     * (recupero edilizio), codici da 3 a 5 dal 2023 (colonnine), tipi da 30
     * a 33 dal 2023 (risparmio energetico).
     */
    public static function codicePercentualeObbligatorio(Tipo $tipo, int $anno, ?int $tipologia): bool
    {
        return match ($tipo) {
            Tipo::ristrutturazione => ($anno >= 2024) && in_array($tipologia, [16, 17, 18], true),
            Tipo::colonnine => ($anno >= 2023) && ($tipologia !== 2),
            Tipo::risparmioEnergetico => ($anno >= 2023) && ($tipologia >= 30),
            Tipo::arredo => false,
        };
    }

    /**
     * Percentuale di detrazione dalle tabelle delle specifiche, null se la
     * combinazione non è ammessa.
     */
    public static function percentuale(Tipo $tipo, int $anno, ?int $tipologia, ?int $codicePercentuale): ?float
    {
        if (($codicePercentuale !== null) && (in_array($codicePercentuale, self::codiciPercentuale($tipo, $anno, $tipologia), true) === false)) {
            return null;
        }
        if (($codicePercentuale === null) && self::codicePercentualeObbligatorio($tipo, $anno, $tipologia)) {
            return null;
        }
        if (($tipologia !== null) && (in_array($tipologia, self::tipologie($tipo, $anno), true) === false)) {
            return null;
        }
        return match ($tipo) {
            Tipo::ristrutturazione => self::percentualeRistrutturazione($anno, $tipologia, $codicePercentuale),
            Tipo::colonnine => self::percentualeColonnine($anno, $tipologia, $codicePercentuale),
            Tipo::risparmioEnergetico => self::percentualeRisparmioEnergetico($anno, $tipologia, $codicePercentuale),
            Tipo::arredo => 50.0,
        };
    }

    /**
     * Rateazioni ammesse (la prima è quella ordinaria): per le spese del 2022
     * del superbonus la scelta tra 4 rate e 10 rate dal 2023, per quelle del
     * 2023 le 10 rate con la casella "Opzione 2023".
     *
     * @return int[]
     */
    public static function rateazioni(Tipo $tipo, int $anno, ?int $tipologia, ?int $codicePercentuale, bool $opzione2023 = false): array
    {
        if ($anno >= 2024) {
            return [10];
        }
        $tra = fn(int|array ...$codici) => in_array($tipologia, array_merge(...array_map(fn(int|array $codice) => (array) $codice, $codici)), true);
        $quattroODieci = $anno === 2023 ? [$opzione2023 ? 10 : 4] : [4, 10];
        return match ($tipo) {
            Tipo::ristrutturazione => match (true) {
                $anno <= 2020 => [10],
                $anno === 2021 => match (true) {
                    $tra(range(5, 11), 16, 17, 18, 19) => [5],
                    $tra(20) => $codicePercentuale === 1 ? [5] : [10],
                    default => [10],
                },
                $anno === 2022 => match (true) {
                    $tra(range(5, 11)) => $codicePercentuale === 1 ? [4, 10] : [5],
                    $tra(16, 17, 18) => [4, 10],
                    $tra(19) => [5, 10],
                    $tra(20) => $codicePercentuale === 1 ? [4, 10] : [10],
                    $tra(21, 22) => [5],
                    default => [10],
                },
                default => match (true) {
                    $tra(range(5, 9), 16, 17, 18, 20) && ($codicePercentuale !== null) => $quattroODieci,
                    $tra(range(5, 11), 21, 22) => [5],
                    default => [10],
                },
            },
            Tipo::colonnine => match (true) {
                $tipologia === 2 => [10],
                $anno <= 2021 => [5],
                default => $quattroODieci,
            },
            Tipo::risparmioEnergetico => self::percentuale($tipo, $anno, $tipologia, $codicePercentuale) >= 90.0 && ($anno >= 2021) && ($tipologia !== 15)
                ? match ($anno) {
                    2021 => [5],
                    default => $quattroODieci,
                }
                : [10],
            Tipo::arredo => [10],
        };
    }

    /**
     * Numero della rata nell'anno d'imposta (col. 8 di RP41, col. 7 di RP61,
     * col. 1 di RP57), null se le rate sono finite o non ancora iniziate:
     * le spese del 2022 ripartite in 10 rate dal 2023 (superbonus e
     * tipologia 19) sono alla terza rata nel 2025.
     */
    public static function numeroRata(Tipo $tipo, int $anno, ?int $tipologia, ?int $codicePercentuale, int $rateazione, int $annoImposta): ?int
    {
        $numero = $annoImposta - $anno + 1;
        if (($anno === 2022) && ($rateazione === 10) && self::isDifferita2022($tipo, $tipologia, $codicePercentuale)) {
            $numero--;
        }
        return ($numero >= 1) && ($numero <= $rateazione) ? $numero : null;
    }

    /**
     * Limite di spesa per unità immobiliare e anno, null se le specifiche
     * non lo fissano per la singola spesa (risparmio energetico: limiti di
     * detrazione per tipo di intervento).
     */
    public static function limiteSpesa(Tipo $tipo, int $anno, ?int $tipologia, bool $maggiorazioneSisma = false): ?float
    {
        return match ($tipo) {
            Tipo::ristrutturazione => match (true) {
                in_array($tipologia, [12, 13], true) => 5000.0,
                in_array($tipologia, [16, 17], true) => 48000.0,
                in_array($tipologia, [21, 22], true) => 50000.0,
                $maggiorazioneSisma && in_array($tipologia, range(5, 11), true) => 144000.0,
                in_array($tipologia, [15, 18, 19, 20, 23], true) => 96000.0,
                default => 96000.0,
            },
            Tipo::colonnine => match ($tipologia) {
                2, 3 => 3000.0,
                4 => 4000.0,
                default => null,
            },
            Tipo::arredo => match (true) {
                $anno === 2021 => 16000.0,
                $anno === 2023 => 8000.0,
                $anno >= 2024 => 5000.0,
                default => 10000.0,
            },
            Tipo::risparmioEnergetico => null,
        };
    }

    private static function isDifferita2022(Tipo $tipo, ?int $tipologia, ?int $codicePercentuale): bool
    {
        return match ($tipo) {
            Tipo::ristrutturazione => in_array($tipologia, [16, 17, 18, 19], true) || (in_array($tipologia, [...range(5, 11), 20], true) && ($codicePercentuale === 1)),
            Tipo::colonnine => $tipologia !== 2,
            Tipo::risparmioEnergetico => (self::percentuale($tipo, 2022, $tipologia, $codicePercentuale) ?? 0.0) >= 110.0,
            Tipo::arredo => false,
        };
    }

    private static function percentualeRistrutturazione(int $anno, ?int $tipologia, ?int $codice): ?float
    {
        if ($anno >= 2025) {
            return match (true) {
                in_array($tipologia, [21, 22], true) => 75.0,
                $tipologia === 23 => 50.0,
                default => match ($codice) {
                    1 => 110.0,
                    2 => 65.0,
                    3 => 50.0,
                    default => 36.0,
                },
            };
        }
        return match (true) {
            $tipologia === null => 50.0,
            $tipologia === 4 => 65.0,
            in_array($tipologia, [12, 13], true) => 36.0,
            $tipologia === 15 => $anno === 2022 ? 60.0 : 90.0,
            in_array($tipologia, [21, 22], true) => 75.0,
            $tipologia === 19 => 50.0,
            in_array($tipologia, [16, 17, 18], true) => match (true) {
                $anno <= 2022 => 110.0,
                $codice === 1 => 110.0,
                $codice === 2 => $anno === 2023 ? 90.0 : 70.0,
                default => 50.0,
            },
            default => match ($codice) {
                1 => 110.0,
                2 => $anno === 2023 ? 90.0 : 70.0,
                default => $tipologia === 20 ? 50.0 : self::SISMABONUS[$tipologia],
            },
        };
    }

    private static function percentualeColonnine(int $anno, ?int $tipologia, ?int $codice): ?float
    {
        if ($tipologia === 2) {
            return 50.0;
        }
        return match (true) {
            $anno <= 2022 => 110.0,
            $codice === 1 => 110.0,
            $codice === 2 => 90.0,
            $codice === 3 => 70.0,
            default => 65.0,
        };
    }

    private static function percentualeRisparmioEnergetico(int $anno, ?int $tipologia, ?int $codice): ?float
    {
        $base = match (true) {
            in_array($tipologia, [5, 6, 12, 13], true) => $anno <= 2017 ? 65.0 : 50.0,
            $tipologia === 8 => 70.0,
            $tipologia === 9 => 75.0,
            $tipologia === 10 => 80.0,
            $tipologia === 11 => 85.0,
            $tipologia === 15 => $anno === 2022 ? 60.0 : 90.0,
            $tipologia >= 30 => null,
            default => 65.0,
        };
        if ($anno >= 2025) {
            return match ($codice) {
                1 => 110.0,
                2 => 65.0,
                3 => 50.0,
                default => $tipologia >= 30 ? null : 36.0,
            };
        }
        return match ($codice) {
            1 => 110.0,
            2 => $anno === 2023 ? 90.0 : 70.0,
            default => ($tipologia >= 30) && ($anno <= 2022) ? 110.0 : $base,
        };
    }
}
