<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Enumerations\AttivitaEnc;
use Imu\Application\Forms\UtilizzoMistoEncForm;
use Imu\Application\Models\UtilizzoMistoEncModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Utilizzo per l'anno di un immobile dell'ente non commerciale nelle
 * attività del comma 759, lett. g) (quadro B della dichiarazione ENC).
 */
class UtilizzoMistoEncController extends ImuController
{

    public function create(Request $request, Soggetto $soggetto, Immobile $immobile, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->autorizzaImmobile($immobile);
        $anno = self::normalizzaAnno($anno);
        $utilizzoModel = new UtilizzoMistoEncModel($this->dataMapper);
        $esistente = $utilizzoModel->getPerAnno($soggetto, $immobile, $anno);
        if ($esistente !== null) {
            return $this->router->redirect('utilizzo-misto-enc/update/utilizzo/' . $esistente->id);
        }
        $utilizzo = new UtilizzoMistoEnc($this->dataMapper);
        $precedente = $utilizzoModel->getPerAnno($soggetto, $immobile, $anno - 1);
        if ($precedente !== null) {
            foreach (array_merge(['attivita', 'esente', 'tipoConcessione', 'comodatario', 'note'], UtilizzoMistoEncForm::CAMPI_NUMERICI) as $proprieta) {
                $utilizzo->$proprieta = $precedente->$proprieta;
            }
        }
        $utilizzo->soggetto = $soggetto;
        $utilizzo->immobile = $immobile;
        $utilizzo->anno = $anno;
        return $this->gestisci($request, $utilizzo);
    }

    public function update(Request $request, UtilizzoMistoEnc $utilizzo): Response
    {
        $this->autorizzaSoggetto($utilizzo->soggetto);
        return $this->gestisci($request, $utilizzo);
    }

    public function delete(Request $request, UtilizzoMistoEnc $utilizzo): Response
    {
        $this->autorizzaSoggetto($utilizzo->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($utilizzo);
        $this->aggiungiMessaggio('Utilizzo eliminato: l\'immobile è interamente imponibile.');
        return $this->vaiAlContribuente($utilizzo->soggetto, $utilizzo->anno);
    }

    private function gestisci(Request $request, UtilizzoMistoEnc $utilizzo): Response
    {
        $form = new UtilizzoMistoEncForm($utilizzo);
        if ($this->isInvioValido($request, $form)) {
            $utilizzo = $form->resolveEntity();
            $this->dataMapper->save($utilizzo);
            $this->aggiungiMessaggio('Utilizzo salvato: ricalcola l\'imposta per aggiornare i versamenti.');
            return $this->vaiAlContribuente($utilizzo->soggetto, $utilizzo->anno);
        }
        $this->impostaVariabiliForm($form, $utilizzo);
        $this->vars['utilizzo'] = $utilizzo;
        $this->vars['attivita'] = AttivitaEnc::cases();
        $this->vars['attivitaScelte'] = $form->isSubmitted() ? (array) ($request->input['attivita'] ?? []) : array_map(fn(AttivitaEnc $attivita) => $attivita->value, $utilizzo->getAttivita());
        $this->vars['soggetto'] = $utilizzo->soggetto;
        $this->vars['immobile'] = $utilizzo->immobile;
        $this->vars['anno'] = $utilizzo->anno;
        $this->vars['pageTitle'] = 'Utilizzo ' . $utilizzo->anno . ' (ente non commerciale)';
        $this->impostaBricioleContribuente($utilizzo->soggetto, $utilizzo->anno, $this->vars['pageTitle']);
        return $this->render->generateView('utilizzoMistoEnc/form', $this->vars, $form->getResponseType());
    }
}
