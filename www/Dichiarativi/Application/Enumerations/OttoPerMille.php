<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Istituzioni beneficiarie dell'otto per mille dell'IRPEF (scheda per la
 * scelta della destinazione, modello Redditi PF 2026), con la posizione
 * della casella nel record B delle specifiche tecniche.
 */
enum OttoPerMille: string
{

    case stato = 'ST';
    case chiesaCattolica = 'CC';
    case avventisti = 'AV';
    case assembleeDiDio = 'AD';
    case valdesi = 'VA';
    case luterani = 'LU';
    case comunitaEbraiche = 'EB';
    case ortodossi = 'OR';
    case chiesaApostolica = 'AP';
    case battisti = 'BA';
    case buddhisti = 'BU';
    case induisti = 'IN';
    case sokaGakkai = 'SG';
    case chiesaInghilterra = 'CI';

    public function getLabel(): string
    {
        return match ($this) {
            self::stato => 'Stato',
            self::chiesaCattolica => 'Chiesa Cattolica',
            self::avventisti => 'Unione Chiese cristiane avventiste del 7° giorno',
            self::assembleeDiDio => 'Assemblee di Dio in Italia',
            self::valdesi => 'Chiesa Evangelica Valdese (Unione delle Chiese metodiste e valdesi)',
            self::luterani => 'Chiesa Evangelica Luterana in Italia',
            self::comunitaEbraiche => 'Unione Comunità Ebraiche Italiane',
            self::ortodossi => 'Sacra Arcidiocesi ortodossa d\'Italia ed Esarcato per l\'Europa Meridionale',
            self::chiesaApostolica => 'Chiesa apostolica in Italia',
            self::battisti => 'Unione Cristiana Evangelica Battista d\'Italia',
            self::buddhisti => 'Unione Buddhista Italiana',
            self::induisti => 'Unione Induista Italiana',
            self::sokaGakkai => 'Istituto Buddista Italiano Soka Gakkai',
            self::chiesaInghilterra => 'Associazione "Chiesa d\'Inghilterra"',
        };
    }

    /**
     * Posizione della casella nel record B.
     */
    public function getPosizione(): int
    {
        return 688 + array_search($this, self::cases(), true);
    }

    /**
     * Finalità della scelta per lo Stato (campo "Scelta Stato").
     *
     * @return array<int, string>
     */
    public static function getFinalitaStato(): array
    {
        return [1 => 'Fame nel mondo', 2 => 'Calamità', 3 => 'Edilizia scolastica', 4 => 'Assistenza ai rifugiati', 5 => 'Beni culturali', 6 => 'Prevenzione e recupero da tossicodipendenze e altre dipendenze patologiche'];
    }
}
