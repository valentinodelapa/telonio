<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Punti della Certificazione Unica 2026 (lavoro dipendente e assimilati)
 * riportati nel quadro RC del modello Redditi PF 2026, con i numeri citati
 * dalle istruzioni (fascicolo 1, quadro RC, sezioni I, II, III e V). Gli
 * importi sono in euro con i centesimi, i giorni e i codici interi.
 */
final class PuntiCertificazioneUnica
{

    public const INDETERMINATO = 1;
    public const DETERMINATO = 2;
    public const PENSIONE = 3;
    public const ALTRI_ASSIMILATI = 4;
    public const ASSEGNI_CONIUGE = 5;
    public const GIORNI_LAVORO = 6;
    public const GIORNI_PENSIONE = 7;
    public const RITENUTE_IRPEF = 21;
    public const ADDIZIONALE_REGIONALE = 22;
    public const ACCONTO_COMUNALE = 26;
    public const SALDO_COMUNALE = 27;
    public const ACCONTO_COMUNALE_SUCCESSIVO = 29;
    public const CODICE_TRATTAMENTO_INTEGRATIVO = 390;
    public const TRATTAMENTO_INTEGRATIVO = 391;
    public const TIPOLOGIA_RIDUZIONE = 718;
    public const REDDITI_ART_49 = 719;
    public const REDDITI_SPORTIVI = 720;
    public const GIORNI_RIDUZIONE = 721;
    public const CODICE_SOMMA_ESENTE = 724;
    public const SOMMA_ESENTE = 725;

    /** Punti con giorni o codici (interi) */
    private const INTERI = [self::GIORNI_LAVORO, self::GIORNI_PENSIONE, self::CODICE_TRATTAMENTO_INTEGRATIVO, self::TIPOLOGIA_RIDUZIONE, self::GIORNI_RIDUZIONE, self::CODICE_SOMMA_ESENTE];

    /**
     * Punti per sezione del form, nell'ordine della certificazione.
     *
     * @return array<string, array<int, string>>
     */
    public static function getSezioni(): array
    {
        return [
            'Redditi' => [
                self::INDETERMINATO => 'Lavoro dipendente e assimilati a tempo indeterminato',
                self::DETERMINATO => 'Lavoro dipendente e assimilati a tempo determinato',
                self::PENSIONE => 'Redditi di pensione',
                self::ALTRI_ASSIMILATI => 'Altri redditi assimilati',
                self::ASSEGNI_CONIUGE => 'Assegni periodici corrisposti dal coniuge',
                self::GIORNI_LAVORO => 'Giorni di lavoro dipendente',
                self::GIORNI_PENSIONE => 'Giorni di pensione',
            ],
            'Ritenute e addizionali' => [
                self::RITENUTE_IRPEF => 'Ritenute IRPEF',
                self::ADDIZIONALE_REGIONALE => 'Addizionale regionale all\'IRPEF',
                self::ACCONTO_COMUNALE => 'Addizionale comunale, acconto dell\'anno',
                self::SALDO_COMUNALE => 'Addizionale comunale, saldo dell\'anno',
                self::ACCONTO_COMUNALE_SUCCESSIVO => 'Addizionale comunale, acconto dell\'anno successivo',
            ],
            'Riduzione della pressione fiscale' => [
                self::CODICE_TRATTAMENTO_INTEGRATIVO => 'Trattamento integrativo, codice (1 erogato, 2 non erogato)',
                self::TRATTAMENTO_INTEGRATIVO => 'Trattamento integrativo erogato',
                self::TIPOLOGIA_RIDUZIONE => 'Tipologia (1 con redditi dell\'art. 49 Tuir, 2 senza)',
                self::REDDITI_ART_49 => 'Redditi di lavoro dipendente (art. 49 Tuir)',
                self::REDDITI_SPORTIVI => 'Redditi di lavoro sportivo dilettantistico',
                self::GIORNI_RIDUZIONE => 'Giorni di lavoro dipendente',
                self::CODICE_SOMMA_ESENTE => 'Somma che non concorre al reddito, codice (1 erogata, 2 non erogata)',
                self::SOMMA_ESENTE => 'Somma che non concorre al reddito erogata',
            ],
        ];
    }

    /**
     * @return int[]
     */
    public static function getPunti(): array
    {
        $punti = [];
        foreach (self::getSezioni() as $sezione) {
            array_push($punti, ...array_keys($sezione));
        }
        return $punti;
    }

    public static function isIntero(int $punto): bool
    {
        return in_array($punto, self::INTERI, true);
    }

    /**
     * Valore inserito nel form ("1.234,56", "1234.56", "365"): null se
     * vuoto, false se non valido.
     */
    public static function leggi(int $punto, mixed $testo): float|false|null
    {
        if (is_string($testo) === false) {
            return null;
        }
        $testo = str_replace(' ', '', trim($testo));
        if ($testo === '') {
            return null;
        }
        if (str_contains($testo, ',')) {
            $testo = str_replace(',', '.', str_replace('.', '', $testo));
        }
        if ((is_numeric($testo) === false) || ((float) $testo < 0)) {
            return false;
        }
        $valore = (float) $testo;
        if (self::isIntero($punto) && (floor($valore) !== $valore)) {
            return false;
        }
        if (in_array($punto, [self::GIORNI_LAVORO, self::GIORNI_PENSIONE, self::GIORNI_RIDUZIONE], true) && ($valore > 365)) {
            return false;
        }
        return round($valore, 2);
    }
}
