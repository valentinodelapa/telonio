<?php

namespace CommSuite\Application\Interfaces;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Services\Scadenza;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenze che un modulo della suite espone allo scadenzario dello studio.
 *
 * Il modulo la implementa con la classe
 * <Modulo>\Application\Services\ScadenzeModulo, costruita con il
 * DataMapper: chi possiede il dato ne espone le scadenze, chi le raccoglie
 * (CommSuite\Application\Services\RaccoltaScadenze) non dipende dal
 * modulo. Le scadenze si calcolano al momento dai dati del modulo, che
 * resta l'unica fonte: lo scadenzario ne conserva solo lo stato di
 * lavorazione, collegato a modulo e riferimento.
 */
interface FonteScadenze
{

    public function __construct(DataMapper $dataMapper);

    /**
     * Scadenze dello studio con data da $dal ad $al inclusi.
     *
     * @param BaseEntity|null $soggetto soggetto dell'Anagrafica
     *        (Anagrafica\Application\Entities\Soggetto): solo le sue scadenze
     * @return Scadenza[]
     */
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array;
}
