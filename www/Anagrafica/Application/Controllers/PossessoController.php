<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Forms\PossessoForm;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\ControlloQuote;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use SismaFramework\Core\Exceptions\BadRequestException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Possessi (titolo, quota, utilizzo) storicizzati: si gestiscono sia dalla
 * scheda del soggetto sia da quella dell'immobile; il parametro "ritorno"
 * indica a quale tornare.
 */
class PossessoController extends AnagraficaController
{

    private const RITORNO_SOGGETTO = 'soggetto';
    private const RITORNO_IMMOBILE = 'immobile';

    /**
     * Da un immobile (/possesso/create/immobile/ID) si sceglie il soggetto,
     * da un soggetto (/possesso/create/soggetto/ID) l'immobile esistente.
     */
    public function create(Request $request, ?Immobile $immobile = null, ?Soggetto $soggetto = null): Response
    {
        if (($immobile === null) === ($soggetto === null)) {
            throw new BadRequestException('Indicare l\'immobile oppure il soggetto');
        }
        $possesso = new Possesso($this->dataMapper);
        if ($immobile !== null) {
            $this->autorizzaImmobile($immobile);
            $possesso->immobile = $immobile;
        } else {
            $this->autorizza($soggetto);
            $possesso->soggetto = $soggetto;
        }
        return $this->gestisci($request, $possesso, $immobile !== null ? self::RITORNO_IMMOBILE : self::RITORNO_SOGGETTO, 'Nuovo possesso', 'Possesso registrato.');
    }

    public function update(Request $request, Possesso $possesso, string $ritorno = self::RITORNO_SOGGETTO): Response
    {
        $this->autorizzaImmobile($possesso->immobile);
        return $this->gestisci($request, $possesso, $ritorno, 'Rettifica possesso', 'Possesso rettificato.');
    }

    public function delete(Request $request, Possesso $possesso, string $ritorno = self::RITORNO_SOGGETTO): Response
    {
        $this->autorizzaImmobile($possesso->immobile);
        $ambito = (new PossessoModel($this->dataMapper))->getAmbito($possesso);
        return $this->eliminaVersione($request, $possesso, $ambito, fn(Possesso $eliminato) => $this->esito($eliminato, $ritorno, 'Possesso eliminato.'));
    }

    private function gestisci(Request $request, Possesso $possesso, string $ritorno, string $titolo, string $messaggio): Response
    {
        $form = new PossessoForm($possesso);
        $possessoModel = new PossessoModel($this->dataMapper);
        $risposta = $this->registraVersione($request, $form, $possesso,
                fn(Possesso $versione) => $possessoModel->getAmbito($versione),
                fn(Possesso $salvato) => $this->esito($salvato, $ritorno, $messaggio),
                ['quota' => fn(Possesso $versione) => (new ControlloQuote($this->dataMapper))->verifica($versione)]);
        if ($risposta !== null) {
            return $risposta;
        }
        $this->impostaVariabiliForm($form, $possesso);
        $this->vars['possesso'] = $possesso;
        $this->vars['ritorno'] = $ritorno;
        $this->vars['titoli'] = TitoloPossesso::cases();
        $this->vars['utilizzi'] = UtilizzoImmobile::cases();
        $this->vars['nuovo'] = isset($possesso->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['urlAnnulla'] = $this->getUrlRitorno($possesso, $ritorno);
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', $titolo => null];
        return $this->render->generateView('possesso/form', $this->vars, $form->getResponseType());
    }

    private function esito(Possesso $possesso, string $ritorno, string $messaggio): Response
    {
        (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($possesso->soggetto);
        $this->aggiungiMessaggio($messaggio);
        return $this->router->redirect(ltrim($this->getUrlRitorno($possesso, $ritorno), '/'));
    }

    private function getUrlRitorno(Possesso $possesso, string $ritorno): string
    {
        if (($ritorno === self::RITORNO_IMMOBILE) && isset($possesso->immobile)) {
            return '/immobile/show/immobile/' . $possesso->immobile->id;
        }
        return '/soggetto/show/soggetto/' . $possesso->soggetto->id . '/scheda/immobili';
    }
}
