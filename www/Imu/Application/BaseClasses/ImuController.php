<?php

namespace Imu\Application\BaseClasses;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Permissions\ImmobilePermission;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Anagrafica\Application\Services\StoricoService;
use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Enumerations\ModuloSuite;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Security\Enumerations\AccessControlEntry;

/**
 * Controller base del modulo IMU: si lavora per contribuente (soggetto
 * dell'Anagrafica) e anno; gli immobili sono quelli dell'Anagrafica.
 */
abstract class ImuController extends SuiteController
{

    public const PRIMO_ANNO = 2020;

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::imu;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    protected function autorizzaImmobile(Immobile $immobile): void
    {
        ImmobilePermission::isAllowed($immobile, AccessControlEntry::allow, $this->utente);
    }

    protected function vaiAlContribuente(Soggetto $soggetto, int $anno): Response
    {
        return $this->router->redirect('contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    protected function impostaBricioleContribuente(Soggetto $soggetto, int $anno, ?string $pagina = null): void
    {
        $this->vars['briciole'] = ['IMU' => '/contribuente-imu/index', $soggetto->nominativo . ' ' . $anno => $pagina === null ? null : '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno]
                + ($pagina === null ? [] : [$pagina => null]);
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita): void
    {
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars['errors'] = $form->getFilterErrors();
    }

    /**
     * Anno richiesto, di default quello in corso, entro gli anni gestiti
     * (dal 2020, "nuova IMU" della L. 160/2019).
     */
    protected static function normalizzaAnno(int $anno): int
    {
        $corrente = (int) date('Y');
        return $anno < self::PRIMO_ANNO ? $corrente : min($anno, $corrente + 1);
    }

    /**
     * @return int[] anni selezionabili, dal più recente
     */
    protected static function getAnni(): array
    {
        return range((int) date('Y') + 1, self::PRIMO_ANNO);
    }

    /**
     * Inserimento o rettifica di una versione storicizzata (come in
     * Anagrafica): validazione, coerenza temporale nell'ambito, salvataggio
     * con adeguamento delle versioni adiacenti.
     *
     * @return bool true se salvata
     */
    protected function registraVersione(Request $request, BaseForm $form, Storicizzabile&BaseEntity $versione, callable $ambito): bool
    {
        $decorrenzaOriginale = isset($versione->id) ? $versione->validoDal : null;
        if ($this->isInvioValido($request, $form) === false) {
            return false;
        }
        $versione = $form->resolveEntity();
        $storicoService = new StoricoService($this->dataMapper);
        $errore = $storicoService->verifica($versione, $ambito($versione));
        if ($errore !== null) {
            $form->getFilterErrors()->validoDalCustomMessage = $errore;
            return false;
        }
        $storicoService->registra($versione, $ambito($versione), $decorrenzaOriginale);
        return true;
    }

    protected function annullaVersione(Request $request, Storicizzabile&BaseEntity $versione, iterable $ambito): void
    {
        $this->verificaCsrf($request);
        (new StoricoService($this->dataMapper))->annulla($versione, $ambito);
    }
}
