<?php

namespace Contabilita\Application\BaseClasses;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Security\Enumerations\AccessControlEntry;
use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Enumerations\ModuloSuite;

/**
 * Controller base del modulo: si lavora per azienda (soggetto
 * dell'Anagrafica con la contabilità aperta) ed esercizio. I piani dei
 * conti modello e i codici IVA sono dello studio (o della suite, in sola
 * lettura).
 */
abstract class ContabilitaController extends SuiteController
{

    protected ?PianoConti $piano = null;

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::contabilita;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizzaSoggetto(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    /**
     * Autorizza l'azienda e ne carica il piano dei conti: null se la
     * contabilità non è aperta.
     */
    protected function caricaAzienda(Soggetto $soggetto): ?PianoConti
    {
        $this->autorizzaSoggetto($soggetto);
        $this->piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['piano'] = $this->piano;
        return $this->piano;
    }

    /**
     * Elemento del modello o dello studio: in modifica solo quelli dello
     * studio.
     */
    protected function autorizzaStudio(?BaseEntity $titolare, bool $modifica = false): void
    {
        if ((($titolare === null) && $modifica) || (($titolare !== null) && ($titolare->id !== $this->studio->id))) {
            throw new AccessDeniedException($modifica ? 'Gli elementi della suite non si modificano: copiarli nello studio.' : 'Elemento di un altro studio.');
        }
    }

    /**
     * Esercizio dell'anno richiesto o, in mancanza, l'ultimo; null se non
     * ce ne sono.
     */
    protected function getEsercizio(Soggetto $soggetto, int $anno): ?EsercizioContabile
    {
        $model = new EsercizioContabileModel($this->dataMapper);
        $esercizio = $anno > 0 ? $model->getPerAnno($soggetto, $anno) : null;
        $esercizio ??= $model->getPerData($soggetto, new \DateTimeImmutable()) ?? $model->getUltimo($soggetto);
        $this->vars['esercizio'] = $esercizio;
        $this->vars['esercizi'] = iterator_to_array($model->getElenco($soggetto), false);
        $this->vars['anno'] = $esercizio?->anno ?? (int) date('Y');
        return $esercizio;
    }

    protected function vaiAllAzienda(Soggetto $soggetto, ?int $anno = null): Response
    {
        return $this->router->redirect('azienda/show/soggetto/' . $soggetto->id . ($anno === null ? '' : '/anno/' . $anno));
    }

    /**
     * Briciole: Contabilità > azienda (anno) > pagina.
     */
    protected function impostaBriciole(Soggetto $soggetto, ?int $anno = null, ?string $pagina = null, array $intermedie = []): void
    {
        $urlAzienda = '/azienda/show/soggetto/' . $soggetto->id . ($anno === null ? '' : '/anno/' . $anno);
        $this->vars['briciole'] = ['Contabilità' => '/azienda/index', $soggetto->nominativo . ($anno === null ? '' : ' ' . $anno) => $pagina === null ? null : $urlAzienda]
                + $intermedie + ($pagina === null ? [] : [$pagina => null]);
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita): void
    {
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars['errors'] = $form->getFilterErrors();
    }

    /**
     * Pagina per le aziende senza contabilità aperta.
     */
    protected function contabilitaNonAperta(Soggetto $soggetto): Response
    {
        $this->aggiungiMessaggio('La contabilità di ' . $soggetto->nominativo . ' non è aperta.', 'warning');
        return $this->vaiAllAzienda($soggetto);
    }

    /**
     * Data dalla richiesta (AAAA-MM-GG) o quella indicata.
     */
    protected static function data(string $valore, string $predefinita): SismaDate
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valore);
        return new SismaDate(($data !== false) && ($data->format('Y-m-d') === $valore) ? $valore : $predefinita);
    }
}
