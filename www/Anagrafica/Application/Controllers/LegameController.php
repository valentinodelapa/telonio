<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Forms\LegameForm;
use Anagrafica\Application\Models\LegameModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class LegameController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $soggetto;
        return $this->gestisci($request, $legame, 'Nuovo soggetto collegato', 'Collegamento registrato.');
    }

    public function update(Request $request, Legame $legame): Response
    {
        $this->autorizza($legame->soggetto);
        return $this->gestisci($request, $legame, 'Rettifica collegamento', 'Collegamento rettificato.');
    }

    public function delete(Request $request, Legame $legame): Response
    {
        $this->autorizza($legame->soggetto);
        return $this->annullaVersione($request, $legame, $this->getAmbito($legame), 'legami', 'Collegamento eliminato.');
    }

    /**
     * Lo stesso soggetto non può ricoprire due volte lo stesso ruolo nello
     * stesso periodo.
     */
    private function getAmbito(Legame $legame): iterable
    {
        return (new LegameModel($this->dataMapper))->getBySoggettoAndSoggettoCollegatoAndRuolo($legame->soggetto, $legame->soggettoCollegato, $legame->ruolo);
    }

    private function gestisci(Request $request, Legame $legame, string $titolo, string $messaggio): Response
    {
        $form = new LegameForm($legame);
        $risposta = $this->salvaVersione($request, $form, $legame, $this->getAmbito(...), 'legami', $messaggio);
        if ($risposta !== null) {
            return $risposta;
        }
        $this->impostaVariabiliForm($form, $legame);
        $this->vars['soggetto'] = $legame->soggetto;
        $this->vars['ruoli'] = RuoloLegame::cases();
        $this->vars['nuovo'] = isset($legame->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($legame->soggetto, $titolo);
        return $this->render->generateView('legame/form', $this->vars, $form->getResponseType());
    }
}
