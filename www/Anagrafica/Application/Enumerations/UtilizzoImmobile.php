<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Utilizzo dell'immobile da parte del possessore, rilevante per i redditi
 * fondiari e l'IMU. È storicizzato con il possesso: un cambio di utilizzo
 * si registra come variazione.
 */
enum UtilizzoImmobile: string
{

    case abitazionePrincipale = 'AP';
    case pertinenzaAbitazionePrincipale = 'PE';
    case locatoLiberoMercato = 'LL';
    case locatoCanoneConcordato = 'LC';
    case comodato = 'CO';
    case aDisposizione = 'DI';
    case strumentale = 'ST';
    case altro = 'AL';

    public function getLabel(): string
    {
        return match ($this) {
            self::abitazionePrincipale => 'Abitazione principale',
            self::pertinenzaAbitazionePrincipale => 'Pertinenza dell\'abitazione principale',
            self::locatoLiberoMercato => 'Locato a canone libero',
            self::locatoCanoneConcordato => 'Locato a canone concordato',
            self::comodato => 'Comodato d\'uso gratuito',
            self::aDisposizione => 'A disposizione',
            self::strumentale => 'Strumentale all\'attività',
            self::altro => 'Altro utilizzo',
        };
    }
}
