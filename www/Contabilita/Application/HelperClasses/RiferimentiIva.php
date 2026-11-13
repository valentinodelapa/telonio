<?php

namespace Contabilita\Application\HelperClasses;

/**
 * Riferimenti normativi dell'IVA citati dal modulo, con la norma da
 * applicare secondo la data: fino al 31 dicembre 2026 il D.P.R. 633/1972
 * e le leggi speciali, dal 1° gennaio 2027 il Testo unico IVA (D.Lgs. 19
 * gennaio 2026, n. 10, art. 171) e il testo unico adempimenti e
 * accertamento (D.Lgs. 5 agosto 2026, n. 141, art. 368).
 *
 * Le corrispondenze sono quelle indicate sotto la rubrica di ciascun
 * articolo dei testi unici (G.U. n. 24 del 30 gennaio 2026, S.O. n. 4;
 * G.U. n. 181 del 6 agosto 2026, S.O. n. 28). I regolamenti (D.P.R.
 * 100/1998 sulle liquidazioni periodiche, D.P.R. 542/1999 sui
 * trimestrali) restano in vigore e sono richiamati dai testi unici.
 */
final class RiferimentiIva
{

    public const DECORRENZA_TESTI_UNICI = '2027-01-01';
    private const TU_IVA = 'D.Lgs. 10/2026';
    private const DPR = 'D.P.R. 633/1972';

    /** chiave => [norma fino al 2026, norma dal 2027] */
    private const RIFERIMENTI = [
        'registroVendite' => ['art. 23 ' . self::DPR, 'art. 86 ' . self::TU_IVA],
        'registroAcquisti' => ['art. 25 ' . self::DPR, 'art. 88 ' . self::TU_IVA],
        'registroCorrispettivi' => ['art. 24 ' . self::DPR, 'art. 87 ' . self::TU_IVA],
        'tenutaRegistri' => ['art. 39 ' . self::DPR, 'art. 95 ' . self::TU_IVA],
        'esigibilitaDifferita' => ['art. 6, c. 5, ' . self::DPR, 'art. 24, c. 5, ' . self::TU_IVA],
        'scissionePagamenti' => ['art. 17-ter ' . self::DPR, 'art. 65 ' . self::TU_IVA],
        'ventilazione' => ['art. 24, c. 3, ' . self::DPR, 'art. 87, c. 3, ' . self::TU_IVA],
        'scorporoCorrispettivi' => ['art. 27, c. 4, ' . self::DPR, 'art. 109, c. 2, ' . self::TU_IVA],
        'attivitaSeparate' => ['art. 36 ' . self::DPR, 'art. 63 ' . self::TU_IVA],
        'gruppoIva' => ['art. 73, c. 3, ' . self::DPR, 'art. 91, c. 3, ' . self::TU_IVA],
        'prorata' => ['art. 19, c. 5, e art. 19-bis ' . self::DPR, 'art. 56, c. 5, e art. 57 ' . self::TU_IVA],
        'regimeAgricolo' => ['art. 34 ' . self::DPR, 'art. 133 ' . self::TU_IVA],
        'esoneroAgricolo' => ['art. 34, c. 6, ' . self::DPR, 'art. 133, c. 5, ' . self::TU_IVA],
        'trimestraliSpeciali' => ['art. 74, c. 4, ' . self::DPR, 'art. 139, c. 4, ' . self::TU_IVA],
        'noteVariazione' => ['art. 26 ' . self::DPR, 'art. 92 ' . self::TU_IVA],
        'acconto' => ['art. 6, c. 2, L. 405/1990', 'art. 111 ' . self::TU_IVA],
        'ivaPerCassa' => ['art. 32-bis D.L. 83/2012', 'art. 132 ' . self::TU_IVA],
        'lipe' => ['art. 21-bis D.L. 78/2010', 'art. 51 D.Lgs. 141/2026'],
    ];

    public static function isTestiUnici(\DateTimeInterface|int|null $quando = null): bool
    {
        if (is_int($quando)) {
            return $quando >= (int) substr(self::DECORRENZA_TESTI_UNICI, 0, 4);
        }
        return ($quando ?? new \DateTimeImmutable())->format('Y-m-d') >= self::DECORRENZA_TESTI_UNICI;
    }

    /**
     * Norma in vigore alla data (o per l'anno) indicata; di default oggi.
     *
     * @throws \InvalidArgumentException se la chiave non esiste
     */
    public static function cita(string $chiave, \DateTimeInterface|int|null $quando = null): string
    {
        return self::coppia($chiave)[self::isTestiUnici($quando) ? 1 : 0];
    }

    /**
     * Entrambe le norme, per i testi che valgono a cavallo della
     * decorrenza (aiuti, documentazione): "art. 23 D.P.R. 633/1972; dal
     * 2027 art. 86 D.Lgs. 10/2026".
     */
    public static function entrambe(string $chiave): string
    {
        [$previgente, $testoUnico] = self::coppia($chiave);
        return $previgente . '; dal 2027 ' . $testoUnico;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function coppia(string $chiave): array
    {
        return self::RIFERIMENTI[$chiave] ?? throw new \InvalidArgumentException('Riferimento normativo sconosciuto: ' . $chiave);
    }
}
