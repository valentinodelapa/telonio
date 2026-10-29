<?php

namespace Deleghe\Application\Controllers;

use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Forms\SanzioneCivileForm;
use Deleghe\Application\Services\GenerazioneDeleghe;
use Deleghe\Application\Services\SanzioniCiviliService;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Sanzioni civili per contributi INPS e premi INAIL versati in ritardo con
 * la delega: calcolo e, con la causale di versamento, rigo nella delega.
 */
class SanzioneCivileController extends DelegheController
{

    private const SEZIONI = [SezioneF24::inps, SezioneF24::inail];

    /**
     * Con $versamento i contributi sono un versamento scaduto dell'archivio
     * (es. una rata INPS non pagata), che passa nella delega con il calcolo.
     */
    public function create(Request $request, Delega $delega, SezioneF24 $sezione = SezioneF24::inps, ?Versamento $versamento = null): Response
    {
        $this->autorizza($delega);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        if ((in_array($sezione, self::SEZIONI, true) === false) || ($delega->modello->ammetteSezione($sezione) === false)) {
            $this->aggiungiMessaggio('Sanzioni civili disponibili per le sezioni INPS e INAIL del modello.', 'warning');
            return $this->vaiAllaDelega($delega);
        }
        $sanzioneCivile = new SanzioneCivile($this->dataMapper);
        $sanzioneCivile->delega = $delega;
        $sanzioneCivile->sezione = $sezione;
        if (($versamento !== null) && $versamento->isDaVersare() && ($versamento->soggetto->id === $delega->soggetto->id) && ($versamento->sezione === $sezione)) {
            $sanzioneCivile->versamentoTardivo = $versamento;
            $sanzioneCivile->riferimento = mb_substr($versamento->descrizione ?? ($versamento->getCodice() . ' ' . ($versamento->periodoDa ?? '')), 0, 100);
            $sanzioneCivile->importoContributi = $versamento->importoDebito;
            $sanzioneCivile->scadenzaOriginale = $versamento->scadenza;
        }
        return $this->gestisci($request, $sanzioneCivile, 'Sanzioni civili: ' . $sezione->getLabel());
    }

    /**
     * Contributi scaduti dell'archivio (INPS o INAIL) versati in ritardo: con
     * la data di versamento scelta apre la delega in bozza di quella data
     * (creata se manca) e il calcolo delle sanzioni civili sui contributi.
     */
    public function daVersamento(Request $request, Versamento $versamento): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        $this->verificaCsrf($request);
        $data = self::leggiData((string) ($request->input['scadenza'] ?? ''));
        if (($versamento->isDaVersare() === false) || (in_array($versamento->sezione, self::SEZIONI, true) === false) || ($versamento->importoDebito <= 0)) {
            $this->aggiungiMessaggio('Il versamento tardivo con le sanzioni civili riguarda i contributi INPS e i premi INAIL da versare.', 'warning');
            return $this->vaiAlContribuente($versamento->soggetto);
        }
        if (($data === null) || ($data->format('Y-m-d') <= $versamento->scadenza->format('Y-m-d'))) {
            $this->aggiungiMessaggio('Indicare una data di versamento successiva alla scadenza originale.', 'danger');
            return $this->vaiAlContribuente($versamento->soggetto);
        }
        $delega = (new GenerazioneDeleghe($this->dataMapper))->trovaOCreaBozza($versamento, $data);
        return $this->router->redirect('sanzione-civile/create/delega/' . $delega->id . '/sezione/' . $versamento->sezione->value . '/versamento/' . $versamento->id);
    }

    public function update(Request $request, SanzioneCivile $sanzioneCivile): Response
    {
        $this->autorizza($sanzioneCivile->delega);
        if (($bloccata = $this->verificaModificabile($sanzioneCivile->delega)) !== null) {
            return $bloccata;
        }
        return $this->gestisci($request, $sanzioneCivile, 'Sanzioni civili: ' . $sanzioneCivile->sezione->getLabel());
    }

    private function gestisci(Request $request, SanzioneCivile $sanzioneCivile, string $titolo): Response
    {
        $form = new SanzioneCivileForm($sanzioneCivile);
        if ($this->isInvioValido($request, $form)) {
            $sanzioneCivile = $form->resolveEntity();
            try {
                (new SanzioniCiviliService($this->dataMapper))->registra($sanzioneCivile);
                $this->aggiungiMessaggio(sprintf('Sanzioni civili calcolate: %d giorni di ritardo, € %s%s.', $sanzioneCivile->giorniRitardo, number_format($sanzioneCivile->sanzione, 2, ',', '.'),
                                $sanzioneCivile->causale === null ? ' (prospetto: nessun rigo in delega)' : ''));
                return $this->vaiAllaDelega($sanzioneCivile->delega);
            } catch (DelegaNonModificabileException | \InvalidArgumentException | \UnexpectedValueException $errore) {
                $this->vars['erroreCalcolo'] = $errore->getMessage();
            }
        }
        $this->impostaVariabiliForm($form, $sanzioneCivile);
        $this->vars['delega'] = $sanzioneCivile->delega;
        $this->vars['sezione'] = $sanzioneCivile->sezione;
        $this->vars['regimi'] = RegimeSanzioneCivile::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($sanzioneCivile->delega, 'Sanzioni civili');
        return $this->render->generateView('sanzioneCivile/form', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, SanzioneCivile $sanzioneCivile): Response
    {
        $delega = $sanzioneCivile->delega;
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        (new SanzioniCiviliService($this->dataMapper))->elimina($sanzioneCivile);
        $this->aggiungiMessaggio('Sanzioni civili eliminate.');
        return $this->vaiAllaDelega($delega);
    }
}
