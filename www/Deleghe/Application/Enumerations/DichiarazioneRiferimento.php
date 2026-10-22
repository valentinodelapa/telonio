<?php

namespace Deleghe\Application\Enumerations;

use Deleghe\Application\HelperClasses\Calendario;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazione il cui termine di presentazione delimita la riduzione a
 * 1/8 del ravvedimento (art. 13, c. 1, lett. b, D.Lgs. 472/1997): quella
 * "relativa all'anno nel corso del quale è stata commessa la violazione",
 * o un anno dalla violazione se non è prevista una dichiarazione periodica.
 *
 * I termini sono quelli ordinari (redditi, IRAP e 770: 31 ottobre; IVA: 30
 * aprile; IMU: 30 giugno dell'anno successivo), spostati al primo giorno
 * lavorativo; nel calcolo restano modificabili per i termini prorogati.
 */
enum DichiarazioneRiferimento: string
{

    case redditi = 'redditi';
    case iva = 'iva';
    case sostituti = '770';
    case imu = 'imu';
    case nessuna = 'nessuna';

    public function getLabel(): string
    {
        return match ($this) {
            self::redditi => 'Dichiarazione dei redditi / IRAP',
            self::iva => 'Dichiarazione IVA',
            self::sostituti => 'Modello 770',
            self::imu => 'Dichiarazione IMU',
            self::nessuna => 'Nessuna dichiarazione periodica (un anno)',
        };
    }

    /**
     * Termine della dichiarazione relativa all'anno della violazione.
     */
    public function getTermine(\DateTimeInterface $violazione): SismaDate
    {
        $anno = (int) $violazione->format('Y') + 1;
        $termine = match ($this) {
            self::redditi, self::sostituti => sprintf('%d-10-31', $anno),
            self::iva => sprintf('%d-04-30', $anno),
            self::imu => sprintf('%d-06-30', $anno),
            self::nessuna => (new \DateTimeImmutable($violazione->format('Y-m-d')))->modify('+1 year')->format('Y-m-d'),
        };
        return $this === self::nessuna ? new SismaDate($termine) : Calendario::primoGiornoLavorativo(new SismaDate($termine));
    }
}
