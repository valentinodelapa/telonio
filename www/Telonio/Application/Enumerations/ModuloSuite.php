<?php

namespace Telonio\Application\Enumerations;

use Telonio\Application\HelperClasses\Versioni;

/**
 * Applicativi della suite. Ciascuno corrisponde a un modulo SismaFramework
 * registrato in MODULE_FOLDERS; quelli non ancora disponibili compaiono
 * nel menu come "in arrivo".
 */
enum ModuloSuite: string
{

    case anagrafica = 'Anagrafica';
    case deleghe = 'Deleghe';
    case imu = 'Imu';
    case successioni = 'Successioni';
    case contabilita = 'Contabilita';
    case dichiarativi = 'Dichiarativi';
    case bilancio = 'Bilancio';
    case parcellazione = 'Parcellazione';
    case gestioneStudio = 'GestioneStudio';

    public function getLabel(): string
    {
        return match ($this) {
            self::anagrafica => 'Anagrafica',
            self::deleghe => 'Deleghe F24',
            self::imu => 'IMU',
            self::successioni => 'Successioni',
            self::contabilita => 'Contabilità',
            self::dichiarativi => 'Dichiarazioni fiscali',
            self::bilancio => 'Bilancio',
            self::parcellazione => 'Parcelle e fatture',
            self::gestioneStudio => 'Gestione studio',
        };
    }

    public function getDescrizione(): string
    {
        return match ($this) {
            self::anagrafica => 'Archivio unico dei soggetti dello studio, condiviso da tutti gli applicativi',
            self::deleghe => 'Deleghe di pagamento F24, compensazioni e versamenti generati dagli altri applicativi',
            self::imu => 'Calcolo IMU sugli immobili dell\'anagrafica, aliquote dei comuni e versamenti di acconto e saldo',
            self::successioni => 'Dichiarazioni di successione e volture catastali, autoliquidazione e file telematico',
            self::contabilita => 'Contabilità ordinaria, semplificata e forfetaria, registri IVA e cespiti',
            self::dichiarativi => 'Redditi, IVA annuale, 770 e invii telematici',
            self::bilancio => 'Bilancio CEE, nota integrativa e analisi di bilancio',
            self::parcellazione => 'Parcelle, fatture elettroniche e incassi dello studio',
            self::gestioneStudio => 'Scadenzario, pratiche, attività e controllo di gestione',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::anagrafica => 'bi-people',
            self::deleghe => 'bi-credit-card-2-front',
            self::imu => 'bi-house-gear',
            self::successioni => 'bi-diagram-3',
            self::contabilita => 'bi-journal-text',
            self::dichiarativi => 'bi-file-earmark-text',
            self::bilancio => 'bi-bar-chart',
            self::parcellazione => 'bi-receipt',
            self::gestioneStudio => 'bi-calendar-check',
        };
    }

    public function getUrl(): string
    {
        return match ($this) {
            self::anagrafica => '/soggetto/index',
            self::deleghe => '/contribuente/index',
            self::imu => '/contribuente-imu/index',
            self::successioni => '/defunto/index',
            self::gestioneStudio => '/scadenzario/index',
            default => '#',
        };
    }

    public function isDisponibile(): bool
    {
        return in_array($this->value, \Config\MODULE_FOLDERS, true);
    }

    /**
     * Versione installata del modulo (module.json); null se non disponibile.
     */
    public function getVersione(): ?string
    {
        return $this->isDisponibile() ? Versioni::getModulo($this->value) : null;
    }
}
