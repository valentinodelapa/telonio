<?php

namespace Contabilita\Application\Services\FatturaPa;

use Contabilita\Application\Entities\FatturaElettronica;

/**
 * Esito dell'importazione di uno o più file di fatture elettroniche.
 */
class EsitoImportazione
{

    /** @var FatturaElettronica[] */
    public array $fatture = [];

    /** @var string[] file o documenti già importati */
    public array $doppie = [];

    /** @var string[] controparti create */
    public array $nuoveControparti = [];

    /** @var string[] */
    public array $errori = [];
}
