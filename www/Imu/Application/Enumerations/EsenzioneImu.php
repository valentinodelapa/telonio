<?php

namespace Imu\Application\Enumerations;

/**
 * Esenzioni oggettive dell'immobile (art. 1, commi 758 e 759, L.
 * 160/2019) che non si ricavano dai dati catastali: le categorie del
 * gruppo E sono esenti per categoria, l'abitazione principale per
 * utilizzo, i terreni condotti da coltivatori diretti e IAP per
 * agevolazione del possessore.
 */
enum EsenzioneImu: string
{

    case entiPubblici = 'EP';
    case terreniMontani = 'TM';
    case proprietaCollettiva = 'PC';
    case usiCulturali = 'UC';
    case culto = 'CU';
    case santaSede = 'SS';
    case statiEsteri = 'SE';
    case entiNonCommerciali = 'EN';
    case occupatoAbusivamente = 'OA';
    case comodatoEnti = 'CE';
    case altra = 'AL';

    public function getLabel(): string
    {
        return match ($this) {
            self::entiPubblici => 'Immobile di Stato, comune, regione o altro ente pubblico destinato ai compiti istituzionali',
            self::terreniMontani => 'Terreno in comune montano, collinare o isola minore',
            self::proprietaCollettiva => 'Terreno a proprietà collettiva indivisibile e inusucapibile',
            self::usiCulturali => 'Fabbricato con destinazione ad usi culturali',
            self::culto => 'Fabbricato destinato esclusivamente al culto e sue pertinenze',
            self::santaSede => 'Fabbricato della Santa Sede (Trattato del Laterano)',
            self::statiEsteri => 'Fabbricato di Stati esteri o organizzazioni internazionali',
            self::entiNonCommerciali => 'Immobile di ente non commerciale destinato ad attività non commerciali',
            self::occupatoAbusivamente => 'Immobile occupato abusivamente e denunciato',
            self::comodatoEnti => 'Immobile in comodato al comune o a enti (esenzione comunale)',
            self::altra => 'Altra esenzione',
        };
    }

    public function getRiferimento(): string
    {
        return match ($this) {
            self::entiPubblici => 'comma 759, lett. a), L. 160/2019',
            self::terreniMontani => 'comma 758, lett. b) e d), L. 160/2019',
            self::proprietaCollettiva => 'comma 758, lett. c), L. 160/2019',
            self::usiCulturali => 'comma 759, lett. c), L. 160/2019',
            self::culto => 'comma 759, lett. d), L. 160/2019',
            self::santaSede => 'comma 759, lett. e), L. 160/2019',
            self::statiEsteri => 'comma 759, lett. f), L. 160/2019',
            self::entiNonCommerciali => 'comma 759, lett. g), L. 160/2019',
            self::occupatoAbusivamente => 'comma 759, lett. g-bis), L. 160/2019',
            self::comodatoEnti => 'comma 777, lett. e), L. 160/2019 (regolamento comunale)',
            self::altra => 'vedi note',
        };
    }
}
