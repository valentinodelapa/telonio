<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Models\ComuneModel;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Forms\ProspettoAliquoteForm;
use Imu\Application\Models\AliquotaDifferenziataModel;
use Imu\Application\Models\ProspettoAliquoteModel;
use Imu\Application\Services\AliquoteComune;
use Imu\Application\Services\TabelleRiferimento\ImportazioneProspetti;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Aliquote dei comuni degli immobili dello studio: prospetti pubblicati
 * dal Dipartimento delle Finanze (importati) e aliquote inserite dallo
 * studio, che prevalgono.
 */
class ProspettoAliquoteController extends ImuController
{

    public function index(int $anno = 0): Response
    {
        $anno = self::normalizzaAnno($anno);
        $prospettoModel = new ProspettoAliquoteModel($this->dataMapper);
        $aliquote = new AliquoteComune($this->studio, $this->dataMapper);
        $comuneModel = new ComuneModel($this->dataMapper);
        $comuni = [];
        foreach ((new ImportazioneProspetti($this->dataMapper))->getComuniImmobili($this->studio) as $codice) {
            $comuni[] = [
                'codice' => $codice,
                'comune' => $comuneModel->getByCodice($codice),
                'delloStudio' => $prospettoModel->getDelloStudio($this->studio, $codice, $anno),
                'pubblicato' => $prospettoModel->getPubblicato($codice, $anno),
                'applicato' => $aliquote->getProspetto($codice, $anno),
            ];
        }
        $this->vars['comuni'] = $comuni;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['importabile'] = $anno >= ImportazioneProspetti::PRIMO_ANNO;
        $this->vars['fattispecie'] = FattispecieImu::perAnno($anno);
        $this->vars['briciole'] = ['IMU' => '/contribuente-imu/index', 'Aliquote dei comuni ' . $anno => null];
        return $this->render->generateView('prospettoAliquote/index', $this->vars);
    }

    public function show(ProspettoAliquote $prospetto): Response
    {
        $this->autorizzaProspetto($prospetto, false);
        $this->vars['prospetto'] = $prospetto;
        $this->vars['differenziate'] = iterator_to_array((new AliquotaDifferenziataModel($this->dataMapper))->getElenco($prospetto));
        $this->vars['fattispecie'] = FattispecieImu::perAnno($prospetto->anno);
        $this->vars['pageTitle'] = 'Aliquote IMU ' . $prospetto->getEtichetta();
        $this->vars['briciole'] = ['IMU' => '/contribuente-imu/index', 'Aliquote dei comuni ' . $prospetto->anno => '/prospetto-aliquote/index/anno/' . $prospetto->anno, $prospetto->getEtichetta() => null];
        return $this->render->generateView('prospettoAliquote/show', $this->vars);
    }

    /**
     * Aliquote del comune inserite dallo studio, proposte dal prospetto
     * pubblicato o da quelle dello studio dell'anno precedente.
     */
    public function create(Request $request, string $codice, int $anno): Response
    {
        $anno = self::normalizzaAnno($anno);
        $codice = strtoupper($codice);
        $prospettoModel = new ProspettoAliquoteModel($this->dataMapper);
        $esistente = $prospettoModel->getDelloStudio($this->studio, $codice, $anno);
        if ($esistente !== null) {
            return $this->router->redirect('prospetto-aliquote/update/prospetto/' . $esistente->id);
        }
        $comune = (new ComuneModel($this->dataMapper))->getByCodice($codice);
        if ($comune === null) {
            $this->aggiungiMessaggio('Comune ' . $codice . ' non trovato.', 'danger');
            return $this->router->redirect('prospetto-aliquote/index/anno/' . $anno);
        }
        $prospetto = new ProspettoAliquote($this->dataMapper);
        $modello = $prospettoModel->getPubblicato($codice, $anno) ?? $prospettoModel->getDelloStudio($this->studio, $codice, $anno - 1);
        if ($modello !== null) {
            foreach (array_merge(ProspettoAliquoteForm::getCampiAliquota($anno), ['assimilazioneAnziani', 'detrazioneAbitazionePrincipale', 'importoMinimo', 'esenzioni']) as $proprieta) {
                $prospetto->$proprieta = $modello->$proprieta;
            }
        }
        $prospetto->studio = $this->studio;
        $prospetto->codiceCatastale = $codice;
        $prospetto->denominazioneComune = $comune->denominazione;
        $prospetto->anno = $anno;
        return $this->gestisci($request, $prospetto, 'Aliquote ' . $comune->denominazione . ' ' . $anno);
    }

    public function update(Request $request, ProspettoAliquote $prospetto): Response
    {
        $this->autorizzaProspetto($prospetto, true);
        return $this->gestisci($request, $prospetto, 'Aliquote ' . $prospetto->getEtichetta());
    }

    public function delete(Request $request, ProspettoAliquote $prospetto): Response
    {
        $this->autorizzaProspetto($prospetto, true);
        $this->verificaCsrf($request);
        $anno = $prospetto->anno;
        $this->dataMapper->delete($prospetto);
        $this->aggiungiMessaggio('Aliquote dello studio eliminate: si applica il prospetto pubblicato, se presente.');
        return $this->router->redirect('prospetto-aliquote/index/anno/' . $anno);
    }

    /**
     * Importa dal Dipartimento delle Finanze i prospetti dell'anno per i
     * comuni degli immobili dello studio.
     */
    public function importa(Request $request, int $anno): Response
    {
        $this->verificaCsrf($request);
        $anno = self::normalizzaAnno($anno);
        $importazione = new ImportazioneProspetti($this->dataMapper);
        try {
            $esito = $importazione->importa($anno, $importazione->getComuniImmobili($this->studio), fn(string $messaggio) => null);
            $this->aggiungiMessaggio(sprintf('Prospetti %d importati: %d; comuni senza prospetto pubblicato: %d.', $anno, $esito['importati'], count($esito['non_pubblicati'])));
            foreach ($esito['errori'] as $errore) {
                $this->aggiungiMessaggio($errore, 'danger');
            }
        } catch (\RuntimeException | \InvalidArgumentException $errore) {
            $this->aggiungiMessaggio('Importazione non riuscita: ' . $errore->getMessage(), 'danger');
        }
        return $this->router->redirect('prospetto-aliquote/index/anno/' . $anno);
    }

    private function gestisci(Request $request, ProspettoAliquote $prospetto, string $titolo): Response
    {
        $form = new ProspettoAliquoteForm($prospetto);
        if ($this->isInvioValido($request, $form)) {
            $prospetto = $form->resolveEntity();
            $this->dataMapper->save($prospetto);
            $this->aggiungiMessaggio('Aliquote salvate: ricalcola l\'imposta dei contribuenti interessati.');
            return $this->router->redirect('prospetto-aliquote/index/anno/' . $prospetto->anno);
        }
        $this->impostaVariabiliForm($form, $prospetto);
        $this->vars['prospetto'] = $prospetto;
        $this->vars['fattispecie'] = FattispecieImu::perAnno($prospetto->anno);
        $this->vars['pubblicato'] = (new ProspettoAliquoteModel($this->dataMapper))->getPubblicato($prospetto->codiceCatastale, $prospetto->anno);
        $this->vars['pageTitle'] = $titolo;
        $this->vars['briciole'] = ['IMU' => '/contribuente-imu/index', 'Aliquote dei comuni ' . $prospetto->anno => '/prospetto-aliquote/index/anno/' . $prospetto->anno, $titolo => null];
        return $this->render->generateView('prospettoAliquote/form', $this->vars, $form->getResponseType());
    }

    /**
     * I prospetti pubblicati sono di tutti gli studi (in sola lettura),
     * quelli inseriti da uno studio solo suoi.
     */
    private function autorizzaProspetto(ProspettoAliquote $prospetto, bool $modifica): void
    {
        if (($prospetto->studio === null) ? $modifica : ($prospetto->studio->id !== $this->studio->id)) {
            throw new AccessDeniedException('Prospetto non modificabile');
        }
    }
}
