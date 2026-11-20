<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\PossessoModel;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\ContrattoLeasing;
use Contabilita\Application\Forms\ContrattoLeasingForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Contratti di locazione finanziaria dell'azienda, elencati nella pagina
 * dei cespiti.
 */
class ContrattoLeasingController extends ContabilitaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $contratto = new ContrattoLeasing($this->dataMapper);
        $contratto->studio = $soggetto->studio;
        $contratto->soggetto = $soggetto;
        return $this->gestisci($request, $contratto, 'Nuovo contratto di leasing');
    }

    public function update(Request $request, ContrattoLeasing $contratto): Response
    {
        $this->caricaAzienda($contratto->soggetto);
        return $this->gestisci($request, $contratto, 'Contratto di leasing: ' . $contratto->descrizione);
    }

    public function delete(Request $request, ContrattoLeasing $contratto): Response
    {
        $soggetto = $contratto->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($contratto);
        $this->aggiungiMessaggio('Contratto di leasing eliminato.');
        return $this->router->redirect('cespite/index/soggetto/' . $soggetto->id);
    }

    private function gestisci(Request $request, ContrattoLeasing $contratto, string $titolo): Response
    {
        $soggetto = $contratto->soggetto;
        $form = new ContrattoLeasingForm($contratto);
        if ($this->isInvioValido($request, $form)) {
            $contratto = $form->resolveEntity();
            $this->dataMapper->save($contratto);
            $this->aggiungiMessaggio('Contratto di leasing salvato.');
            return $this->router->redirect('cespite/index/soggetto/' . $soggetto->id);
        }
        $this->impostaVariabiliForm($form, $contratto);
        $this->vars['immobili'] = self::getImmobili($this->dataMapper, $soggetto);
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['Cespiti' => '/cespite/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('contrattoLeasing/form', $this->vars, $form->getResponseType());
    }

    /**
     * Immobili posseduti dall'azienda (Anagrafica), per id.
     *
     * @return array<int, string>
     */
    public static function getImmobili(DataMapper $dataMapper, Soggetto $soggetto): array
    {
        $immobili = [];
        foreach ((new PossessoModel($dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            $immobile = $possesso->immobile;
            $immobili[$immobile->id] = $immobile->getIdentificativo() . ' · ' . $immobile->getUbicazione();
        }
        return $immobili;
    }
}
