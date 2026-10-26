<?php

namespace GestioneStudio\Application\BaseClasses;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Models\UtenteModel;
use GestioneStudio\Application\Entities\Adempimento;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Security\Enumerations\AccessControlEntry;

/**
 * Controller base del modulo: lo scadenzario è dello studio (vista
 * principale per periodo e per operatore), con la scheda del singolo
 * cliente per gli adempimenti a cui è tenuto.
 */
abstract class GestioneStudioController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::gestioneStudio;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    /**
     * Gli adempimenti del calendario fiscale sono di tutti gli studi in sola
     * lettura, quelli di uno studio solo suoi.
     */
    protected function autorizzaAdempimento(Adempimento $adempimento, bool $modifica): void
    {
        if (($adempimento->studio === null) ? $modifica : ($adempimento->studio->id !== $this->studio->id)) {
            throw new AccessDeniedException('Adempimento non accessibile');
        }
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita): void
    {
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars['errors'] = $form->getFilterErrors();
    }

    /**
     * @return array<int, string> operatori attivi dello studio per la scelta del responsabile
     */
    protected function getOperatori(): array
    {
        $operatori = [];
        foreach ((new UtenteModel($this->dataMapper))->getElenco($this->studio) as $utente) {
            if ($utente->attivo) {
                $operatori[$utente->id] = $utente->getNomeCompleto();
            }
        }
        return $operatori;
    }

    protected function isOperatoreDelloStudio(?Utente $utente): bool
    {
        return ($utente === null) || ($utente->studio->id === $this->studio->id);
    }

    /**
     * Anno richiesto, di default quello in corso, entro gli anni gestiti
     * (i cinque precedenti e il successivo).
     */
    protected static function normalizzaAnno(int $anno): int
    {
        $corrente = (int) date('Y');
        return ($anno < $corrente - 5) || ($anno > $corrente + 1) ? $corrente : $anno;
    }

    /**
     * @return int[] anni selezionabili, dal più recente
     */
    protected static function getAnni(): array
    {
        return range((int) date('Y') + 1, (int) date('Y') - 5);
    }

    protected function impostaBriciole(array $voci): void
    {
        $this->vars['briciole'] = [ModuloSuite::gestioneStudio->getLabel() => '/scadenzario/index'] + $voci;
    }
}
