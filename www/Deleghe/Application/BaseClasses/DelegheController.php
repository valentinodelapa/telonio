<?php

namespace Deleghe\Application\BaseClasses;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\SoggettoPermission;
use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Enumerations\ModuloSuite;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Permissions\DelegaPermission;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Security\Enumerations\AccessControlEntry;

abstract class DelegheController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::deleghe;
    }

    /**
     * Blocca l'accesso alle deleghe di altri studi (AccessDeniedException).
     */
    protected function autorizza(Delega $delega): void
    {
        DelegaPermission::isAllowed($delega, AccessControlEntry::allow, $this->utente);
    }

    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    /**
     * Le righe e la testata si modificano solo in bozza.
     */
    protected function verificaModificabile(Delega $delega): ?Response
    {
        if ($delega->isModificabile()) {
            return null;
        }
        $this->aggiungiMessaggio('La delega è ' . mb_strtolower($delega->stato->getLabel()) . ': per modificarla riportala in bozza.', 'warning');
        return $this->vaiAllaDelega($delega);
    }

    protected function vaiAllaDelega(Delega $delega): Response
    {
        return $this->router->redirect('delega/show/delega/' . $delega->id);
    }

    protected function vaiAlContribuente(Soggetto $soggetto, string $vista = 'versamenti'): Response
    {
        return $this->router->redirect('contribuente/show/soggetto/' . $soggetto->id . ($vista === 'versamenti' ? '' : '/vista/' . $vista));
    }

    /**
     * Pagina da cui proviene il versamento: la sua delega o l'archivio del
     * contribuente.
     */
    protected function tornaDalVersamento(Versamento $versamento): Response
    {
        return $versamento->delega === null ? $this->vaiAlContribuente($versamento->soggetto) : $this->vaiAllaDelega($versamento->delega);
    }

    protected function impostaBriciole(Delega $delega, ?string $pagina = null): void
    {
        $titolo = 'F24 ' . $delega->scadenza->format('d/m/Y');
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', $delega->soggetto->nominativo => '/contribuente/show/soggetto/' . $delega->soggetto->id]
                + ($pagina === null ? [$titolo => null] : [$titolo => '/delega/show/delega/' . $delega->id, $pagina => null]);
    }

    protected function impostaBricioleContribuente(Soggetto $soggetto, string $pagina): void
    {
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', $soggetto->nominativo => '/contribuente/show/soggetto/' . $soggetto->id, $pagina => null];
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita): void
    {
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars['errors'] = $form->getFilterErrors();
    }

    /**
     * Prima scadenza ordinaria dei versamenti unitari (il 16 del mese) da
     * oggi in poi.
     */
    protected static function prossimaScadenza(): SismaDate
    {
        $oggi = new \DateTimeImmutable('today');
        $scadenza = $oggi->setDate((int) $oggi->format('Y'), (int) $oggi->format('m'), 16);
        return new SismaDate(($scadenza < $oggi ? $scadenza->modify('+1 month') : $scadenza)->format('Y-m-d'));
    }

    protected static function leggiData(string $data): ?SismaDate
    {
        $dataLetta = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        return $dataLetta === false ? null : new SismaDate($dataLetta->format('Y-m-d'));
    }
}
