<?php

namespace Anagrafica\Application\BaseClasses;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Permissions\ImmobilePermission;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use Anagrafica\Application\Services\StoricoService;
use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Enumerations\ModuloSuite;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Security\Enumerations\AccessControlEntry;

abstract class AnagraficaController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return ModuloSuite::anagrafica;
    }

    /**
     * Blocca l'accesso ai soggetti di altri studi (AccessDeniedException).
     */
    protected function autorizza(Soggetto $soggetto): void
    {
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $this->utente);
    }

    protected function vaiAllaScheda(Soggetto $soggetto, string $scheda = 'generale'): Response
    {
        return $this->router->redirect('soggetto/show/soggetto/' . $soggetto->id . '/scheda/' . $scheda);
    }

    protected function autorizzaImmobile(Immobile $immobile): void
    {
        ImmobilePermission::isAllowed($immobile, AccessControlEntry::allow, $this->utente);
    }

    protected function vaiAllImmobile(Immobile $immobile): Response
    {
        return $this->router->redirect('immobile/show/immobile/' . $immobile->id);
    }

    protected function impostaBriciole(Soggetto $soggetto, string $pagina): void
    {
        $this->vars['briciole'] = [
            'Anagrafica' => '/soggetto/index',
            $soggetto->nominativo => '/soggetto/show/soggetto/' . $soggetto->id,
            $pagina => null,
        ];
    }

    protected function impostaVariabiliForm(BaseForm $form, BaseEntity $entita, string $chiave = 'formEntity', string $chiaveErrori = 'errors'): void
    {
        $this->vars[$chiave] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $entita;
        $this->vars[$chiaveErrori] = $form->getFilterErrors();
    }

    /**
     * Flusso comune di inserimento/rettifica di una versione storicizzata:
     * validazione del form, verifica della coerenza temporale nell'ambito,
     * salvataggio con adeguamento delle versioni adiacenti.
     *
     * @param callable(BaseEntity): iterable $ambito versioni che non possono
     *        sovrapporsi a quella indicata
     * @param callable(BaseEntity): Response $esito eseguito dopo il
     *        salvataggio (sincronizzazioni, messaggio, redirect)
     * @param array<string, callable(BaseEntity): ?string> $verifiche
     *        controlli aggiuntivi sulla versione risolta, per campo del form
     * @return Response|null esito se salvato, null se il form va (ri)mostrato
     */
    protected function registraVersione(Request $request, BaseForm $form, Storicizzabile&BaseEntity $versione, callable $ambito, callable $esito, array $verifiche = []): ?Response
    {
        $decorrenzaOriginale = isset($versione->id) ? $versione->validoDal : null;
        if ($this->isInvioValido($request, $form) === false) {
            return null;
        }
        $versione = $form->resolveEntity();
        $storicoService = new StoricoService($this->dataMapper);
        $errori = ['validoDal' => $storicoService->verifica($versione, $ambito($versione))];
        foreach ($verifiche as $campo => $verifica) {
            $errori[$campo] ??= $verifica($versione);
        }
        $errori = array_filter($errori);
        if (count($errori) > 0) {
            foreach ($errori as $campo => $errore) {
                $form->getFilterErrors()->{$campo . 'CustomMessage'} = $errore;
            }
            return null;
        }
        $storicoService->registra($versione, $ambito($versione), $decorrenzaOriginale);
        return $esito($versione);
    }

    /**
     * Elimina una versione storicizzata ricucendo lo storico (vedi
     * StoricoService::annulla) ed esegue l'esito sulla versione eliminata.
     */
    protected function eliminaVersione(Request $request, Storicizzabile&BaseEntity $versione, iterable $ambito, callable $esito): Response
    {
        $this->verificaCsrf($request);
        (new StoricoService($this->dataMapper))->annulla($versione, $ambito);
        return $esito($versione);
    }

    /**
     * Esito per i dati storicizzati del soggetto: aggiorna i valori correnti
     * del soggetto e torna alla scheda indicata.
     */
    protected function esitoSoggetto(Soggetto $soggetto, string $scheda, string $messaggio): Response
    {
        (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto);
        $this->aggiungiMessaggio($messaggio);
        return $this->vaiAllaScheda($soggetto, $scheda);
    }

    protected function salvaVersione(Request $request, BaseForm $form, Storicizzabile&BaseEntity $versione, callable $ambito, string $scheda, string $messaggio): ?Response
    {
        return $this->registraVersione($request, $form, $versione, $ambito, fn($salvata) => $this->esitoSoggetto($salvata->soggetto, $scheda, $messaggio));
    }

    protected function annullaVersione(Request $request, Storicizzabile&BaseEntity $versione, iterable $ambito, string $scheda, string $messaggio): Response
    {
        $soggetto = $versione->soggetto;
        return $this->eliminaVersione($request, $versione, $ambito, fn() => $this->esitoSoggetto($soggetto, $scheda, $messaggio));
    }
}
