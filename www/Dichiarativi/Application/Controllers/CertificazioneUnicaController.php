<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\CertificazioneUnica;
use Dichiarativi\Application\Entities\ValoreCertificazioneUnica;
use Dichiarativi\Application\Forms\CertificazioneUnicaForm;
use Dichiarativi\Application\Models\ValoreCertificazioneUnicaModel;
use Dichiarativi\Application\Services\Redditi\PuntiCertificazioneUnica;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio delle Certificazioni Uniche del contribuente: testata con il
 * sostituto d'imposta e punti della certificazione usati dal quadro RC.
 */
class CertificazioneUnicaController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $certificazione = new CertificazioneUnica($this->dataMapper);
        $certificazione->studio = $this->studio;
        $certificazione->soggetto = $soggetto;
        $certificazione->annoImposta = $anno;
        return $this->gestisci($request, $certificazione, [], 'Nuova Certificazione Unica');
    }

    public function update(Request $request, CertificazioneUnica $certificazioneUnica): Response
    {
        $this->autorizzaSoggetto($certificazioneUnica->soggetto);
        $valori = (new ValoreCertificazioneUnicaModel($this->dataMapper))->getValori($certificazioneUnica);
        return $this->gestisci($request, $certificazioneUnica, $valori, 'Modifica Certificazione Unica');
    }

    public function delete(Request $request, CertificazioneUnica $certificazioneUnica): Response
    {
        $this->autorizzaSoggetto($certificazioneUnica->soggetto);
        $this->verificaCsrf($request);
        $transazione = $this->dataMapper->startTransaction();
        (new ValoreCertificazioneUnicaModel($this->dataMapper))->deleteByCertificazioneUnica($certificazioneUnica);
        $this->dataMapper->delete($certificazioneUnica);
        $this->dataMapper->commitTransaction($transazione);
        $this->aggiungiMessaggio('Certificazione Unica eliminata.');
        return $this->vaiAlContribuente($certificazioneUnica->soggetto, $certificazioneUnica->annoImposta);
    }

    /**
     * @param array<int, float> $valori punti salvati
     */
    private function gestisci(Request $request, CertificazioneUnica $certificazione, array $valori, string $titolo): Response
    {
        $form = new CertificazioneUnicaForm($certificazione);
        $inseriti = is_array($request->input['punto'] ?? null) ? $request->input['punto'] : [];
        $erroriPunti = [];
        $letti = [];
        foreach (PuntiCertificazioneUnica::getPunti() as $punto) {
            $valore = PuntiCertificazioneUnica::leggi($punto, $inseriti[$punto] ?? null);
            if ($valore === false) {
                $erroriPunti[$punto] = true;
            } elseif ($valore !== null) {
                $letti[$punto] = $valore;
            }
        }
        if ($this->isInvioValido($request, $form) && (count($erroriPunti) === 0)) {
            $certificazione = $form->resolveEntity();
            $transazione = $this->dataMapper->startTransaction();
            $this->dataMapper->save($certificazione);
            $valoreModel = new ValoreCertificazioneUnicaModel($this->dataMapper);
            $valoreModel->deleteByCertificazioneUnica($certificazione);
            foreach ($letti as $punto => $importo) {
                $valore = new ValoreCertificazioneUnica($this->dataMapper);
                $valore->certificazioneUnica = $certificazione;
                $valore->punto = $punto;
                $valore->valore = $importo;
                $this->dataMapper->save($valore);
            }
            $this->dataMapper->commitTransaction($transazione);
            $this->aggiungiMessaggio('Certificazione Unica salvata.');
            return $this->vaiAlContribuente($certificazione->soggetto, $certificazione->annoImposta);
        }
        $inviato = $form->isSubmitted();
        $this->vars['formEntity'] = $inviato ? $form->getEntityDataToStandardEntity() : $certificazione;
        $this->vars['errors'] = $form->getFilterErrors();
        $testi = [];
        foreach (PuntiCertificazioneUnica::getPunti() as $punto) {
            if ($inviato) {
                $testi[$punto] = is_string($inseriti[$punto] ?? null) ? $inseriti[$punto] : '';
            } elseif (isset($valori[$punto])) {
                $testi[$punto] = PuntiCertificazioneUnica::isIntero($punto) ? (string) (int) $valori[$punto] : number_format($valori[$punto], 2, ',', '.');
            }
        }
        $this->vars['valoriPunti'] = $testi;
        $this->vars['erroriPunti'] = $erroriPunti;
        $this->vars['sezioniPunti'] = PuntiCertificazioneUnica::getSezioni();
        $this->vars['soggetto'] = $certificazione->soggetto;
        $this->vars['anno'] = $certificazione->annoImposta;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($certificazione->soggetto, $certificazione->annoImposta, $titolo);
        $risposta = count($erroriPunti) > 0 ? ResponseType::httpBadRequest : $form->getResponseType();
        return $this->render->generateView('certificazioneUnica/form', $this->vars, $risposta);
    }
}
