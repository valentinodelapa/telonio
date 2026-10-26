<?php

namespace Successioni\Application\BaseClasses;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Enumerations\ModuloSuite;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Security\Enumerations\AccessControlEntry;
use Successioni\Application\Entities\DichiarazioneSuccessione;

/**
 * Controller base del modulo Successioni: si entra dal defunto (soggetto
 * dell'Anagrafica con la data di decesso) e si lavora nella sua
 * dichiarazione; beneficiari e immobili sono quelli dell'Anagrafica.
 */
abstract class SuccessioniController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::successioni;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    protected function autorizzaDichiarazione(DichiarazioneSuccessione $dichiarazione): void
    {
        $this->autorizzaSoggetto($dichiarazione->defunto);
    }

    /**
     * Soggetto scelto in un form (beneficiario, dichiarante, ...): deve
     * essere dello studio.
     */
    protected function isDelloStudio(?Soggetto $soggetto): bool
    {
        return ($soggetto === null) || ($soggetto->studio->id === $this->studio->id);
    }

    protected function vaiAlDefunto(Soggetto $defunto): Response
    {
        return $this->router->redirect('defunto/show/soggetto/' . $defunto->id);
    }

    protected function vaiAllaDichiarazione(DichiarazioneSuccessione $dichiarazione, string $scheda = 'generale'): Response
    {
        return $this->router->redirect('dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/' . $scheda);
    }

    protected function impostaBriciole(DichiarazioneSuccessione $dichiarazione, ?string $pagina = null, ?string $scheda = null): void
    {
        $this->vars['briciole'] = ['Successioni' => '/defunto/index', $dichiarazione->defunto->nominativo => '/defunto/show/soggetto/' . $dichiarazione->defunto->id,
            'Dichiarazione' => $pagina === null ? null : '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . ($scheda === null ? '' : '/scheda/' . $scheda)]
                + ($pagina === null ? [] : [$pagina => null]);
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita): void
    {
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars['errors'] = $form->getFilterErrors();
    }

    /**
     * Le dichiarazioni presentate non si modificano: per correggerle si
     * riportano in bozza (se non trasmesse) o si presenta una sostitutiva.
     */
    protected function verificaModificabile(DichiarazioneSuccessione $dichiarazione, string $scheda = 'generale'): ?Response
    {
        if ($dichiarazione->isModificabile()) {
            return null;
        }
        $this->aggiungiMessaggio('La dichiarazione è presentata: per modificarla riportala in bozza.', 'warning');
        return $this->vaiAllaDichiarazione($dichiarazione, $scheda);
    }
}
