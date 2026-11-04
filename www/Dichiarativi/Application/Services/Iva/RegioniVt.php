<?php

namespace Dichiarativi\Application\Services\Iva;

/**
 * Righi da VT2 a VT22 della dichiarazione IVA (ripartizione regionale
 * delle operazioni verso consumatori finali, per regione o provincia
 * autonoma del luogo di esercizio dell'attività) per sigla della
 * provincia, secondo l'elenco delle province e delle città metropolitane
 * dell'ISTAT (comprese le province sarde istituite dalla L.R. Sardegna
 * 9/2023).
 */
class RegioniVt
{

    /** @var array<int, array{0: string, 1: string[]}> rigo => [regione, sigle delle province] */
    public const RIGHI = [
        2 => ['Abruzzo', ['AQ', 'CH', 'PE', 'TE']],
        3 => ['Basilicata', ['MT', 'PZ']],
        4 => ['Bolzano', ['BZ']],
        5 => ['Calabria', ['CS', 'CZ', 'KR', 'RC', 'VV']],
        6 => ['Campania', ['AV', 'BN', 'CE', 'NA', 'SA']],
        7 => ['Emilia-Romagna', ['BO', 'FC', 'FE', 'MO', 'PC', 'PR', 'RA', 'RE', 'RN']],
        8 => ['Friuli-Venezia Giulia', ['GO', 'PN', 'TS', 'UD']],
        9 => ['Lazio', ['FR', 'LT', 'RI', 'RM', 'VT']],
        10 => ['Liguria', ['GE', 'IM', 'SP', 'SV']],
        11 => ['Lombardia', ['BG', 'BS', 'CO', 'CR', 'LC', 'LO', 'MB', 'MI', 'MN', 'PV', 'SO', 'VA']],
        12 => ['Marche', ['AN', 'AP', 'FM', 'MC', 'PU']],
        13 => ['Molise', ['CB', 'IS']],
        14 => ['Piemonte', ['AL', 'AT', 'BI', 'CN', 'NO', 'TO', 'VB', 'VC']],
        15 => ['Puglia', ['BA', 'BR', 'BT', 'FG', 'LE', 'TA']],
        16 => ['Sardegna', ['CA', 'CI', 'NU', 'OG', 'OR', 'OT', 'SS', 'SU', 'VS']],
        17 => ['Sicilia', ['AG', 'CL', 'CT', 'EN', 'ME', 'PA', 'RG', 'SR', 'TP']],
        18 => ['Toscana', ['AR', 'FI', 'GR', 'LI', 'LU', 'MS', 'PI', 'PO', 'PT', 'SI']],
        19 => ['Trento', ['TN']],
        20 => ['Umbria', ['PG', 'TR']],
        21 => ['Valle d\'Aosta', ['AO']],
        22 => ['Veneto', ['BL', 'PD', 'RO', 'TV', 'VE', 'VI', 'VR']],
    ];

    /**
     * Rigo del quadro VT per la sigla della provincia, null se sconosciuta.
     */
    public static function getRigo(?string $siglaProvincia): ?int
    {
        foreach (self::RIGHI as $rigo => [, $sigle]) {
            if (in_array(strtoupper((string) $siglaProvincia), $sigle, true)) {
                return $rigo;
            }
        }
        return null;
    }
}
