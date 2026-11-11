<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\CertificazioneAutonomo;
use Dichiarativi\Application\Forms\CertificazioneAutonomoForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio delle Certificazioni Uniche del lavoro autonomo e dei redditi
 * diversi del contribuente (quadro RL).
 */
class CertificazioneAutonomoController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $certificazione = new CertificazioneAutonomo($this->dataMapper);
        $certificazione->studio = $this->studio;
        $certificazione->soggetto = $soggetto;
        $certificazione->annoImposta = self::normalizzaAnno($anno);
        return $this->gestisci($request, $certificazione, 'Nuova Certificazione Unica - lavoro autonomo');
    }

    public function update(Request $request, CertificazioneAutonomo $certificazioneAutonomo): Response
    {
        $this->autorizzaSoggetto($certificazioneAutonomo->soggetto);
        return $this->gestisci($request, $certificazioneAutonomo, 'Modifica Certificazione Unica - lavoro autonomo');
    }

    public function delete(Request $request, CertificazioneAutonomo $certificazioneAutonomo): Response
    {
        $this->autorizzaSoggetto($certificazioneAutonomo->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($certificazioneAutonomo);
        $this->aggiungiMessaggio('Certificazione eliminata.');
        return $this->vaiAlContribuente($certificazioneAutonomo->soggetto, $certificazioneAutonomo->annoImposta);
    }

    private function gestisci(Request $request, CertificazioneAutonomo $certificazione, string $titolo): Response
    {
        $form = new CertificazioneAutonomoForm($certificazione);
        if ($this->isInvioValido($request, $form)) {
            $certificazione = $form->resolveEntity();
            $this->dataMapper->save($certificazione);
            $this->aggiungiMessaggio('Certificazione salvata.');
            return $this->vaiAlContribuente($certificazione->soggetto, $certificazione->annoImposta);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $certificazione;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['causali'] = CertificazioneAutonomo::CAUSALI;
        $this->vars['soggetto'] = $certificazione->soggetto;
        $this->vars['anno'] = $certificazione->annoImposta;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($certificazione->soggetto, $certificazione->annoImposta, $titolo);
        return $this->render->generateView('certificazioneAutonomo/form', $this->vars, $form->getResponseType());
    }
}
