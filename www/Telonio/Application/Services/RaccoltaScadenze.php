<?php

namespace Telonio\Application\Services;

use Telonio\Application\Entities\Studio;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Interfaces\FonteScadenze;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Raccoglie le scadenze esposte dai moduli disponibili: per ciascun modulo
 * registrato in MODULE_FOLDERS la classe
 * <Modulo>\Application\Services\ScadenzeModulo, se implementa FonteScadenze.
 * Un nuovo modulo entra nello scadenzario solo dichiarando la sua fonte.
 */
class RaccoltaScadenze
{

    /**
     * @param array<string, FonteScadenze>|null $fonti fonti per modulo; null = scoperte dai moduli disponibili
     */
    public function __construct(private DataMapper $dataMapper = new DataMapper(), private ?array $fonti = null)
    {

    }

    /**
     * @return array<string, FonteScadenze> per valore di ModuloSuite
     */
    public function getFonti(): array
    {
        if ($this->fonti === null) {
            $this->fonti = [];
            foreach (ModuloSuite::cases() as $modulo) {
                $classe = self::getClasseFonte($modulo);
                if ($modulo->isDisponibile() && class_exists($classe) && is_subclass_of($classe, FonteScadenze::class)) {
                    $this->fonti[$modulo->value] = new $classe($this->dataMapper);
                }
            }
        }
        return $this->fonti;
    }

    public static function getClasseFonte(ModuloSuite $modulo): string
    {
        return $modulo->value . '\\Application\\Services\\ScadenzeModulo';
    }

    /**
     * Scadenze di tutti i moduli (o di quello indicato) nel periodo, per
     * data e descrizione.
     *
     * @return Scadenza[]
     */
    public function raccogli(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null, ?ModuloSuite $modulo = null): array
    {
        $scadenze = [];
        foreach ($this->getFonti() as $valore => $fonte) {
            if (($modulo === null) || ($modulo->value === $valore)) {
                array_push($scadenze, ...$fonte->getScadenze($studio, $dal, $al, $soggetto));
            }
        }
        usort($scadenze, fn(Scadenza $a, Scadenza $b) => [$a->data->format('Y-m-d'), $a->descrizione] <=> [$b->data->format('Y-m-d'), $b->descrizione]);
        return $scadenze;
    }
}
