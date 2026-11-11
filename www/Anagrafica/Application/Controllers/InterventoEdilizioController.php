<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia;
use Anagrafica\Application\Forms\InterventoEdilizioForm;
use Anagrafica\Application\Models\PossessoModel;
use SismaFramework\Core\Exceptions\BadRequestException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio degli interventi edilizi dell'immobile per le detrazioni in rate
 * del quadro RP (recupero edilizio, colonnine, bonus mobili, risparmio
 * energetico). Si gestisce dalla scheda dell'immobile.
 */
class InterventoEdilizioController extends AnagraficaController
{

    public function create(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        if ($immobile->tipoCatasto !== TipoCatasto::fabbricati) {
            throw new BadRequestException('Gli interventi edilizi riguardano i fabbricati');
        }
        $intervento = new InterventoEdilizio($this->dataMapper);
        $intervento->immobile = $immobile;
        $intervento->tipo = TipoDetrazioneEdilizia::ristrutturazione;
        return $this->gestisci($request, $intervento, 'Nuovo intervento edilizio');
    }

    public function update(Request $request, InterventoEdilizio $interventoEdilizio): Response
    {
        $this->autorizzaImmobile($interventoEdilizio->immobile);
        return $this->gestisci($request, $interventoEdilizio, 'Modifica intervento edilizio');
    }

    public function delete(Request $request, InterventoEdilizio $interventoEdilizio): Response
    {
        $immobile = $interventoEdilizio->immobile;
        $this->autorizzaImmobile($immobile);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($interventoEdilizio);
        $this->aggiungiMessaggio('Intervento edilizio eliminato.');
        return $this->vaiAllImmobile($immobile);
    }

    private function gestisci(Request $request, InterventoEdilizio $intervento, string $titolo): Response
    {
        $form = new InterventoEdilizioForm($intervento);
        if ($this->isInvioValido($request, $form)) {
            $intervento = $form->resolveEntity();
            $this->dataMapper->save($intervento);
            $this->aggiungiMessaggio('Intervento edilizio salvato.');
            return $this->vaiAllImmobile($intervento->immobile);
        }
        $immobile = $intervento->immobile;
        $this->impostaVariabiliForm($form, $intervento);
        $this->vars['immobile'] = $immobile;
        $this->vars['tipi'] = TipoDetrazioneEdilizia::cases();
        $possessori = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoImmobile($immobile) as $possesso) {
            if ($possesso->soggetto->isPersonaFisica()) {
                $possessori[$possesso->soggetto->id] = $possesso->soggetto;
            }
        }
        $this->vars['possessori'] = array_values($possessori);
        $this->vars['pageTitle'] = $titolo;
        $this->vars['urlAnnulla'] = '/immobile/show/immobile/' . $immobile->id;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, $titolo => null];
        return $this->render->generateView('interventoEdilizio/form', $this->vars, $form->getResponseType());
    }
}
