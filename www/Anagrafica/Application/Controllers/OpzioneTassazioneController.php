<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\OpzioneTassazione;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Forms\OpzioneTassazioneForm;
use SismaFramework\Core\Exceptions\BadRequestException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Opzioni della società di capitali per la tassazione di gruppo
 * (consolidato nazionale, trasparenza fiscale), nella scheda dei dati
 * fiscali.
 */
class OpzioneTassazioneController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        if ($soggetto->tipoSoggetto !== TipoSoggetto::societaCapitali) {
            throw new BadRequestException('Opzioni di tassazione di gruppo previste solo per le società di capitali');
        }
        $opzione = new OpzioneTassazione($this->dataMapper);
        $opzione->soggetto = $soggetto;
        $opzione->annoInizio = (int) date('Y');
        $opzione->annoFine = $opzione->annoInizio + 2;
        return $this->gestisci($request, $opzione, 'Nuova opzione di tassazione di gruppo');
    }

    public function update(Request $request, OpzioneTassazione $opzioneTassazione): Response
    {
        $this->autorizza($opzioneTassazione->soggetto);
        return $this->gestisci($request, $opzioneTassazione, 'Modifica opzione di tassazione di gruppo');
    }

    public function delete(Request $request, OpzioneTassazione $opzioneTassazione): Response
    {
        $soggetto = $opzioneTassazione->soggetto;
        $this->autorizza($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($opzioneTassazione);
        $this->aggiungiMessaggio('Opzione eliminata.');
        return $this->vaiAllaScheda($soggetto, 'fiscali');
    }

    private function gestisci(Request $request, OpzioneTassazione $opzione, string $titolo): Response
    {
        $form = new OpzioneTassazioneForm($opzione);
        if ($this->isInvioValido($request, $form)) {
            $opzione = $form->resolveEntity();
            $this->dataMapper->save($opzione);
            $this->aggiungiMessaggio('Opzione salvata.');
            return $this->vaiAllaScheda($opzione->soggetto, 'fiscali');
        }
        $this->impostaVariabiliForm($form, $opzione);
        $this->vars['soggetto'] = $opzione->soggetto;
        $this->vars['tipiOpzione'] = TipoOpzioneTassazione::cases();
        $this->vars['nuovo'] = isset($opzione->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($opzione->soggetto, $titolo);
        return $this->render->generateView('opzioneTassazione/form', $this->vars, $form->getResponseType());
    }
}
