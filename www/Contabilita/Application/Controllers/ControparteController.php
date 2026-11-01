<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Forms\ControparteForm;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\ControparteModel;
use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Clienti e fornitori dell'azienda, con i sottoconti del partitario
 * aperti al salvataggio.
 */
class ControparteController extends ContabilitaController
{

    private const RISULTATI_PER_PAGINA = 25;

    public function index(Soggetto $soggetto, string $testo = '', int $pagina = 1): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $model = new ControparteModel($this->dataMapper);
        $totale = $model->conta($soggetto, $testo);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $this->vars['controparti'] = iterator_to_array($model->cerca($soggetto, $testo, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA), false);
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->getEsercizio($soggetto, 0);
        $this->vars['sezioneAzienda'] = 'controparti';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Clienti e fornitori';
        $this->impostaBriciole($soggetto, null, 'Clienti e fornitori');
        return $this->render->generateView('controparte/index', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $soggetto->studio;
        $controparte->soggetto = $soggetto;
        $controparte->cliente = true;
        return $this->gestisci($request, $controparte, 'Nuovo cliente o fornitore');
    }

    public function update(Request $request, Controparte $controparte): Response
    {
        $this->caricaAzienda($controparte->soggetto);
        return $this->gestisci($request, $controparte, $controparte->denominazione);
    }

    public function delete(Request $request, Controparte $controparte): Response
    {
        $soggetto = $controparte->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $rigaModel = new RigaContabileModel($this->dataMapper);
        if (((new RegistrazioneContabileModel($this->dataMapper))->countByControparte($controparte) > 0) || ($rigaModel->countByControparte($controparte) > 0)
                || (($controparte->contoCliente !== null) && ($rigaModel->contaPerConto($controparte->contoCliente) > 0))
                || (($controparte->contoFornitore !== null) && ($rigaModel->contaPerConto($controparte->contoFornitore) > 0))) {
            $this->aggiungiMessaggio('La controparte ha registrazioni: non si elimina.', 'warning');
            return $this->router->redirect('controparte/update/controparte/' . $controparte->id);
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new FatturaElettronicaModel($this->dataMapper))->getByControparte($controparte) as $fattura) {
            $fattura->controparte = null;
            $this->dataMapper->save($fattura);
        }
        $conti = [$controparte->contoCliente, $controparte->contoFornitore];
        $this->dataMapper->delete($controparte);
        foreach (array_filter($conti) as $conto) {
            $this->dataMapper->delete($conto);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Controparte eliminata con i suoi sottoconti.');
        return $this->router->redirect('controparte/index/soggetto/' . $soggetto->id);
    }

    /**
     * Ricerca delle controparti dell'azienda, per l'autocompletamento.
     */
    public function cerca(Soggetto $soggetto, string $testo = ''): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new ControparteModel($this->dataMapper))->cerca($soggetto, $testo, 0, 15) as $controparte) {
                $risultati[] = ['id' => $controparte->id, 'etichetta' => $controparte->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }

    private function gestisci(Request $request, Controparte $controparte, string $titolo): Response
    {
        $soggetto = $controparte->soggetto;
        $form = new ControparteForm($controparte);
        if ($this->isInvioValido($request, $form)) {
            $controparte = $form->resolveEntity();
            if (($controparte->cognome !== null) && ($controparte->nome !== null) && ($controparte->denominazione === '')) {
                $controparte->denominazione = $controparte->cognome . ' ' . $controparte->nome;
            }
            $primaTransazione = $this->dataMapper->startTransaction();
            $this->dataMapper->save($controparte);
            try {
                (new Partitario($this->dataMapper))->allinea($controparte);
                foreach ([$controparte->contoCliente, $controparte->contoFornitore] as $conto) {
                    if (($conto !== null) && ($conto->descrizione !== mb_substr($controparte->denominazione, 0, 150))) {
                        $conto->descrizione = mb_substr($controparte->denominazione, 0, 150);
                        $this->dataMapper->save($conto);
                    }
                }
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Controparte salvata.');
            } catch (\DomainException $errore) {
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Controparte salvata, sottoconto non aperto: ' . $errore->getMessage(), 'warning');
            }
            return $this->router->redirect('controparte/index/soggetto/' . $soggetto->id);
        }
        $this->impostaVariabiliForm($form, $controparte);
        $this->vars['controparte'] = $controparte;
        $this->vars['contropartite'] = array_values(array_filter(iterator_to_array((new ContoModel($this->dataMapper))->getMovimentabili($this->piano), false), fn($conto) => $conto->natura->isEconomico()));
        $this->vars['codiciIva'] = (new CodiceIvaModel($this->dataMapper))->getElenco($this->studio);
        $this->vars['nazioni'] = $this->getNazioni();
        $this->vars['pageTitle'] = $titolo;
        $this->getEsercizio($soggetto, 0);
        $this->impostaBriciole($soggetto, null, $titolo, ['Clienti e fornitori' => '/controparte/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('controparte/form', $this->vars, $form->getResponseType());
    }

    /**
     * @return array<int, string> nazioni attuali per id
     */
    private function getNazioni(): array
    {
        $nazioni = [];
        foreach ((new NazioneModel($this->dataMapper))->getByValidoAl(null, null, ['denominazione' => 'ASC']) as $nazione) {
            $nazioni[$nazione->id] = $nazione->denominazione . ($nazione->codiceIso === null ? '' : ' (' . $nazione->codiceIso . ')');
        }
        return $nazioni;
    }
}
