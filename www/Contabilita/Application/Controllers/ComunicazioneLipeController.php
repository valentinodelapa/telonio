<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Forms\ComunicazioneLipeForm;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\ModuloLipeModel;
use Contabilita\Application\Services\Lipe\GeneratoreLipe;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\PeriodiIva;
use Deleghe\Application\Models\IntermediarioModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Comunicazioni delle liquidazioni periodiche IVA dell'azienda: elenco dei
 * trimestri dell'anno, preparazione dalle liquidazioni, frontespizio,
 * file da trasmettere e stato dell'invio.
 */
class ComunicazioneLipeController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $anno = $anno > 0 ? $anno : (int) date('Y');
        $this->getEsercizio($soggetto, $anno);
        $trimestri = [];
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $trimestri[$trimestre] = PeriodiIva::getScadenzaLipe($anno, $trimestre);
        }
        $this->vars['annoIva'] = $anno;
        $this->vars['trimestri'] = $trimestri;
        $this->vars['comunicazioni'] = (new ComunicazioneLipeModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $this->vars['anniIva'] = range((int) date('Y') + 1, (int) date('Y') - 5);
        $this->vars['sezioneAzienda'] = 'liquidazioni';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Comunicazioni liquidazioni IVA ' . $anno;
        $this->impostaBriciole($soggetto, null, 'Comunicazioni liquidazioni IVA ' . $anno, ['Liquidazioni IVA ' . $anno => '/liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno]);
        return $this->render->generateView('comunicazioneLipe/index', $this->vars);
    }

    /**
     * Prepara (o ripropone, se in bozza) la comunicazione del trimestre.
     */
    public function prepara(Request $request, Soggetto $soggetto, int $anno, int $trimestre): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        try {
            $comunicazione = (new PreparazioneLipe($this->dataMapper))->prepara($soggetto, $anno, $trimestre);
            $this->aggiungiMessaggio('Comunicazione del ' . $comunicazione->getDescrizione() . ' preparata dalle liquidazioni.');
            return $this->vaiAllaComunicazione($comunicazione);
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->router->redirect('comunicazione-lipe/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
        }
    }

    public function show(ComunicazioneLipe $comunicazione): Response
    {
        $soggetto = $comunicazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->getEsercizio($soggetto, $comunicazione->anno);
        $this->vars['comunicazione'] = $comunicazione;
        $this->vars['moduliLipe'] = iterator_to_array((new ModuloLipeModel($this->dataMapper))->getModuli($comunicazione));
        $this->vars['errori'] = (new GeneratoreLipe($this->dataMapper))->getErrori($comunicazione);
        $this->vars['scadenzaLipe'] = PeriodiIva::getScadenzaLipe($comunicazione->anno, $comunicazione->trimestre);
        $this->vars['sezioneAzienda'] = 'liquidazioni';
        $this->vars['pageTitle'] = 'Comunicazione liquidazioni IVA · ' . $comunicazione->getDescrizione();
        $this->impostaBriciole($soggetto, null, $comunicazione->getDescrizione(), ['Comunicazioni liquidazioni IVA ' . $comunicazione->anno => '/comunicazione-lipe/index/soggetto/' . $soggetto->id . '/anno/' . $comunicazione->anno]);
        return $this->render->generateView('comunicazioneLipe/show', $this->vars);
    }

    public function update(Request $request, ComunicazioneLipe $comunicazione): Response
    {
        $soggetto = $comunicazione->soggetto;
        $this->caricaAzienda($soggetto);
        if (($bloccata = $this->verificaModificabile($comunicazione)) !== null) {
            return $bloccata;
        }
        $form = new ComunicazioneLipeForm($comunicazione);
        if ($this->isInvioValido($request, $form)) {
            $comunicazione = $form->resolveEntity();
            if ((($comunicazione->dichiarante !== null) && ($comunicazione->dichiarante->studio->id !== $this->studio->id))
                    || (($comunicazione->intermediario !== null) && ($comunicazione->intermediario->studio->id !== $this->studio->id))) {
                return $this->vaiAllaComunicazione($comunicazione);
            }
            $this->dataMapper->save($comunicazione);
            $this->aggiungiMessaggio('Frontespizio salvato.');
            return $this->vaiAllaComunicazione($comunicazione);
        }
        $this->impostaVariabiliForm($form, $comunicazione);
        $this->getEsercizio($soggetto, $comunicazione->anno);
        $this->vars['comunicazione'] = $comunicazione;
        $this->vars['codiciCarica'] = CodiceCaricaIva::cases();
        $this->vars['impegni'] = ImpegnoPresentazione::cases();
        $this->vars['intermediari'] = iterator_to_array((new IntermediarioModel($this->dataMapper))->getElenco($this->studio));
        $this->vars['pageTitle'] = 'Frontespizio · ' . $comunicazione->getDescrizione();
        $this->impostaBriciole($soggetto, null, 'Frontespizio', ['Comunicazioni liquidazioni IVA ' . $comunicazione->anno => '/comunicazione-lipe/index/soggetto/' . $soggetto->id . '/anno/' . $comunicazione->anno,
            $comunicazione->getDescrizione() => '/comunicazione-lipe/show/comunicazione/' . $comunicazione->id]);
        return $this->render->generateView('comunicazioneLipe/form', $this->vars, $form->getResponseType());
    }

    /**
     * Segna la comunicazione come inviata (data e protocollo) o la riporta in bozza.
     */
    public function stato(Request $request, ComunicazioneLipe $comunicazione, StatoComunicazioneLipe $stato): Response
    {
        $this->caricaAzienda($comunicazione->soggetto);
        $this->verificaCsrf($request);
        if ($stato === StatoComunicazioneLipe::inviata) {
            $errori = (new GeneratoreLipe($this->dataMapper))->getErrori($comunicazione);
            if (count($errori) > 0) {
                $this->aggiungiMessaggio('Correggere prima la comunicazione: ' . implode(' ', $errori), 'danger');
                return $this->vaiAllaComunicazione($comunicazione);
            }
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataInvio'] ?? ''));
            $comunicazione->dataInvio = new SismaDate($data === false ? 'today' : $data->format('Y-m-d'));
            $protocollo = trim((string) ($request->input['protocollo'] ?? ''));
            $comunicazione->protocollo = $protocollo === '' ? null : mb_substr($protocollo, 0, 50);
        } else {
            $comunicazione->dataInvio = null;
            $comunicazione->protocollo = null;
        }
        $comunicazione->stato = $stato;
        $this->dataMapper->save($comunicazione);
        $this->aggiungiMessaggio('Comunicazione ' . mb_strtolower($stato->getLabel()) . '.');
        return $this->vaiAllaComunicazione($comunicazione);
    }

    public function scarica(ComunicazioneLipe $comunicazione): Response
    {
        $this->caricaAzienda($comunicazione->soggetto);
        try {
            $this->vars['contenuto'] = (new GeneratoreLipe($this->dataMapper))->genera($comunicazione);
        } catch (\InvalidArgumentException | \UnexpectedValueException $errore) {
            $this->aggiungiMessaggio('File non generato: ' . $errore->getMessage(), 'danger');
            return $this->vaiAllaComunicazione($comunicazione);
        }
        $this->vars['nomeFile'] = GeneratoreLipe::getNomeFile($comunicazione);
        return $this->render->generateData('comunicazioneLipe/scarica', $this->vars);
    }

    public function delete(Request $request, ComunicazioneLipe $comunicazione): Response
    {
        $soggetto = $comunicazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($comunicazione)) !== null) {
            return $bloccata;
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new ModuloLipeModel($this->dataMapper))->getModuli($comunicazione) as $modulo) {
            $this->dataMapper->delete($modulo);
        }
        $this->dataMapper->delete($comunicazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Comunicazione eliminata.');
        return $this->router->redirect('comunicazione-lipe/index/soggetto/' . $soggetto->id . '/anno/' . $comunicazione->anno);
    }

    private function verificaModificabile(ComunicazioneLipe $comunicazione): ?Response
    {
        if ($comunicazione->isModificabile()) {
            return null;
        }
        $this->aggiungiMessaggio('La comunicazione è inviata: per modificarla riportala in bozza.', 'warning');
        return $this->vaiAllaComunicazione($comunicazione);
    }

    private function vaiAllaComunicazione(ComunicazioneLipe $comunicazione): Response
    {
        return $this->router->redirect('comunicazione-lipe/show/comunicazione/' . $comunicazione->id);
    }
}
