<?php

namespace Dichiarativi\Application\BaseClasses;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Dichiarativi\Application\Entities\Dichiarazione;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Security\Enumerations\AccessControlEntry;
use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Enumerations\ModuloSuite;

/**
 * Controller base del modulo: si lavora per contribuente (soggetto
 * dell'Anagrafica) e anno d'imposta.
 */
abstract class DichiarativiController extends SuiteController
{

    /** Primo anno d'imposta gestito (modelli 2026) */
    public const PRIMO_ANNO = 2025;

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::dichiarativi;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    /**
     * Anno d'imposta richiesto, di default quello precedente (le
     * dichiarazioni si presentano l'anno dopo).
     */
    protected static function normalizzaAnno(int $anno): int
    {
        $corrente = (int) date('Y');
        return ($anno < self::PRIMO_ANNO) || ($anno >= $corrente) ? max(self::PRIMO_ANNO, $corrente - 1) : $anno;
    }

    /**
     * @return int[] anni d'imposta selezionabili, dal più recente
     */
    protected static function getAnni(): array
    {
        return range(max(self::PRIMO_ANNO, (int) date('Y') - 1), self::PRIMO_ANNO);
    }

    protected function vaiAlContribuente(Soggetto $soggetto, int $anno): Response
    {
        return $this->router->redirect('contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    protected function vaiAllaDichiarazione(Dichiarazione $dichiarazione, string $quadro = '', int $modulo = 1): Response
    {
        return $this->router->redirect('dichiarazione/show/dichiarazione/' . $dichiarazione->id . ($quadro === '' ? '' : '/quadro/' . $quadro) . ($modulo > 1 ? '/modulo/' . $modulo : ''));
    }

    protected function impostaBricioleContribuente(Soggetto $soggetto, int $anno, ?string $pagina = null): void
    {
        $this->vars['briciole'] = ['Dichiarazioni' => '/contribuente-dichiarativi/index/anno/' . $anno,
            $soggetto->nominativo . ' · anno ' . $anno => $pagina === null ? null : '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno]
                + ($pagina === null ? [] : [$pagina => null]);
    }
}
