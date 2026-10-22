<?php

namespace Successioni\Application\Controllers;

use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Services\Telematico\GeneratoreSuc;
use Successioni\Application\Services\VerificaAllegato;

/**
 * Allegati del quadro EG: caricamento con il controllo del formato (PDF/A,
 * TIFF), scaricamento ed eliminazione.
 */
class AllegatoSuccessioneController extends SuccessioniController
{

    public function create(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'allegati')) !== null) {
            return $bloccata;
        }
        $file = $request->files['file'] ?? null;
        $tipo = TipoAllegato::tryFrom((string) ($request->input['tipoAllegato'] ?? ''));
        if (($tipo === null) || (is_array($file) === false) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || (is_uploaded_file($file['tmp_name']) === false)) {
            $this->aggiungiMessaggio('Scegliere il tipo di documento e il file da allegare (massimo 5 MB).', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione, 'allegati');
        }
        $contenuto = file_get_contents($file['tmp_name']);
        $nomeFile = basename((string) $file['name']);
        $esito = (new VerificaAllegato())->verifica($contenuto, $nomeFile);
        if ($esito['errore'] !== null) {
            $this->aggiungiMessaggio($nomeFile . ': ' . $esito['errore'], 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione, 'allegati');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $contenutoAllegato = new ContenutoAllegato($this->dataMapper);
        $contenutoAllegato->contenuto = base64_encode($contenuto);
        $this->dataMapper->save($contenutoAllegato);
        $allegato = new AllegatoSuccessione($this->dataMapper);
        $allegato->dichiarazioneSuccessione = $dichiarazione;
        $allegato->tipoAllegato = $tipo;
        $allegato->nomeFile = mb_substr($nomeFile, 0, 100);
        $descrizione = GeneratoreSuc::testo((string) ($request->input['descrizione'] ?? ''), 100);
        $allegato->descrizione = $descrizione === '' ? null : $descrizione;
        $allegato->tipoMime = $esito['tipoMime'];
        $allegato->dimensione = strlen($contenuto);
        $allegato->contenutoAllegato = $contenutoAllegato;
        $this->dataMapper->save($allegato);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Allegato caricato: ' . $nomeFile . '.');
        return $this->vaiAllaDichiarazione($dichiarazione, 'allegati');
    }

    public function scarica(AllegatoSuccessione $allegato): Response
    {
        $this->autorizzaDichiarazione($allegato->dichiarazioneSuccessione);
        $this->vars['contenuto'] = base64_decode($allegato->contenutoAllegato->contenuto);
        $this->vars['nomeFile'] = $allegato->nomeFile;
        $this->vars['tipoMime'] = $allegato->tipoMime === 'application/pdf' ? 'application/pdf' : 'image/tiff';
        return $this->render->generateData('allegatoSuccessione/scarica', $this->vars);
    }

    public function delete(Request $request, AllegatoSuccessione $allegato): Response
    {
        $dichiarazione = $allegato->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'allegati')) !== null) {
            return $bloccata;
        }
        $contenuto = $allegato->contenutoAllegato;
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->delete($allegato);
        $this->dataMapper->delete($contenuto);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Allegato eliminato.');
        return $this->vaiAllaDichiarazione($dichiarazione, 'allegati');
    }
}
