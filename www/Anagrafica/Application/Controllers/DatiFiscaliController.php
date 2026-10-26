<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Forms\DatiFiscaliForm;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class DatiFiscaliController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $soggetto;
        $precedenti = (new DatiFiscaliModel($this->dataMapper))->getStorico($soggetto);
        if (isset($precedenti[0])) {
            $this->copiaDati($precedenti[0], $datiFiscali);
            $this->proponiRaccordoAteco($datiFiscali);
        }
        return $this->gestisci($request, $datiFiscali, 'Nuova variazione dei dati fiscali', 'Variazione registrata.');
    }

    public function update(Request $request, DatiFiscali $datiFiscali): Response
    {
        $this->autorizza($datiFiscali->soggetto);
        return $this->gestisci($request, $datiFiscali, 'Rettifica dati fiscali', 'Dati fiscali rettificati.');
    }

    public function delete(Request $request, DatiFiscali $datiFiscali): Response
    {
        $soggetto = $datiFiscali->soggetto;
        $this->autorizza($soggetto);
        $storico = (new DatiFiscaliModel($this->dataMapper))->getStorico($soggetto);
        if (($soggetto->isPersonaFisica() === false) && (count($storico) === 1)) {
            $this->verificaCsrf($request);
            $this->aggiungiMessaggio('Non è possibile eliminare l\'unica versione dei dati fiscali di una società o di un ente.', 'danger');
            return $this->vaiAllaScheda($soggetto, 'fiscali');
        }
        return $this->annullaVersione($request, $datiFiscali, $storico, 'fiscali', 'Versione dei dati fiscali eliminata.');
    }

    private function gestisci(Request $request, DatiFiscali $datiFiscali, string $titolo, string $messaggio): Response
    {
        $form = new DatiFiscaliForm($datiFiscali);
        $ambito = fn(DatiFiscali $versione) => (new DatiFiscaliModel($this->dataMapper))->getStorico($versione->soggetto);
        $risposta = $this->salvaVersione($request, $form, $datiFiscali, $ambito, 'fiscali', $messaggio);
        if ($risposta !== null) {
            return $risposta;
        }
        $this->impostaVariabiliForm($form, $datiFiscali);
        $this->vars['soggetto'] = $datiFiscali->soggetto;
        $this->vars['tipiAttivita'] = TipoAttivita::cases();
        $this->vars['nuovo'] = isset($datiFiscali->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($datiFiscali->soggetto, $titolo);
        return $this->render->generateView('datiFiscali/form', $this->vars, $form->getResponseType());
    }

    /**
     * Se il codice copiato appartiene a una classificazione non più in
     * vigore alla decorrenza proposta, lo sostituisce con il raccordo ISTAT
     * (da confermare: alcune corrispondenze non sono uno a uno).
     */
    private function proponiRaccordoAteco(DatiFiscali $datiFiscali): void
    {
        $codiceAteco = $datiFiscali->codiceAteco;
        if (($codiceAteco === null) || ($codiceAteco->classificazione === ClassificazioneAteco::vigenteAl($datiFiscali->validoDal))) {
            return;
        }
        $raccordo = (new CodiceAtecoModel($this->dataMapper))->getRaccordo($codiceAteco);
        $datiFiscali->codiceAteco = $raccordo;
        $this->vars['atecoConvertitoDa'] = $codiceAteco;
        $this->vars['atecoRaccordoTrovato'] = $raccordo !== null;
    }

    /**
     * Una variazione parte dai dati della versione più recente: l'utente
     * modifica solo ciò che è cambiato.
     */
    private function copiaDati(DatiFiscali $origine, DatiFiscali $destinazione): void
    {
        foreach (['denominazione', 'naturaGiuridica', 'tipoAttivita', 'partitaIva', 'codiceAteco', 'descrizioneAttivita', 'enteNonCommerciale', 'regimeFiscale', 'periodicitaIva', 'soggettoIsa', 'sostitutoImposta', 'trasparenzaFiscale'] as $proprieta) {
            $destinazione->$proprieta = $origine->$proprieta;
        }
    }
}
