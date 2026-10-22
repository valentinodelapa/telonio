<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Forms\DatiCatastaliForm;
use Anagrafica\Application\Models\DatiCatastaliModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class DatiCatastaliController extends AnagraficaController
{

    private const PROPRIETA_COPIATE = ['categoria', 'classe', 'consistenza', 'superficie', 'rendita', 'qualitaColtura', 'redditoDominicale', 'redditoAgrario'];

    /**
     * Variazione catastale: parte dai dati della versione più recente.
     */
    public function create(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $immobile;
        $precedente = (new DatiCatastaliModel($this->dataMapper))->getStorico($immobile)[0] ?? null;
        if ($precedente !== null) {
            foreach (self::PROPRIETA_COPIATE as $proprieta) {
                $datiCatastali->$proprieta = $precedente->$proprieta;
            }
        }
        return $this->gestisci($request, $datiCatastali, 'Variazione catastale', 'Variazione catastale registrata.');
    }

    public function update(Request $request, DatiCatastali $datiCatastali): Response
    {
        $this->autorizzaImmobile($datiCatastali->immobile);
        return $this->gestisci($request, $datiCatastali, 'Rettifica dati catastali', 'Dati catastali rettificati.');
    }

    public function delete(Request $request, DatiCatastali $datiCatastali): Response
    {
        $immobile = $datiCatastali->immobile;
        $this->autorizzaImmobile($immobile);
        $storico = (new DatiCatastaliModel($this->dataMapper))->getStorico($immobile);
        if (count($storico) === 1) {
            $this->verificaCsrf($request);
            $this->aggiungiMessaggio('Non è possibile eliminare l\'unica versione dei dati catastali: elimina l\'immobile.', 'danger');
            return $this->vaiAllImmobile($immobile);
        }
        return $this->eliminaVersione($request, $datiCatastali, $storico, fn() => $this->esitoImmobile($immobile, 'Versione dei dati catastali eliminata.'));
    }

    private function gestisci(Request $request, DatiCatastali $datiCatastali, string $titolo, string $messaggio): Response
    {
        $form = new DatiCatastaliForm($datiCatastali);
        $ambito = fn(DatiCatastali $versione) => (new DatiCatastaliModel($this->dataMapper))->getStorico($versione->immobile);
        $risposta = $this->registraVersione($request, $form, $datiCatastali, $ambito, fn(DatiCatastali $salvata) => $this->esitoImmobile($salvata->immobile, $messaggio));
        if ($risposta !== null) {
            return $risposta;
        }
        $immobile = $datiCatastali->immobile;
        $this->impostaVariabiliForm($form, $datiCatastali);
        $this->vars['immobile'] = $immobile;
        $this->vars['categorie'] = CategoriaCatastale::cases();
        $this->vars['nuovo'] = isset($datiCatastali->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, $titolo => null];
        return $this->render->generateView('datiCatastali/form', $this->vars, $form->getResponseType());
    }

    private function esitoImmobile(Immobile $immobile, string $messaggio): Response
    {
        $this->aggiungiMessaggio($messaggio);
        return $this->vaiAllImmobile($immobile);
    }
}
