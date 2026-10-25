<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\Periodicita;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Forms\VersamentoForm;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\CopiaVersamenti;
use Deleghe\Application\Services\GenerazioneDeleghe;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

/**
 * Versamenti del contribuente: inseriti nell'archivio (da versare, con la
 * loro data) o direttamente in una delega in bozza. I versamenti generati da
 * altri moduli non si modificano qui (verrebbero sovrascritti al ricalcolo):
 * si correggono nel modulo di origine, oppure si eliminano.
 */
class VersamentoController extends DelegheController
{

    /**
     * Origini dei versamenti calcolati nella delega: non tornano
     * nell'archivio, si eliminano con il calcolo.
     */
    private const ORIGINI_DELEGA = [Ravvedimento::ORIGINE, SanzioneCivile::ORIGINE];

    /**
     * Nuovo versamento nell'archivio del contribuente.
     */
    public function create(Request $request, Soggetto $soggetto, SezioneF24 $sezione): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $this->studio;
        $versamento->soggetto = $soggetto;
        $versamento->sezione = $sezione;
        $versamento->scadenza = self::prossimaScadenza();
        if ($sezione->getSezioneCodici() !== null) {
            $versamento->annoRiferimento = (int) $versamento->scadenza->format('Y');
        }
        return $this->gestisci($request, $versamento, 'Nuovo versamento: ' . $sezione->getLabel());
    }

    /**
     * Nuovo versamento direttamente in una delega in bozza.
     */
    public function aggiungi(Request $request, Delega $delega, SezioneF24 $sezione): Response
    {
        $this->autorizza($delega);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        if ($delega->modello->ammetteSezione($sezione) === false) {
            $this->aggiungiMessaggio('La sezione ' . $sezione->getLabel() . ' non è prevista nel ' . $delega->modello->getLabel() . '.', 'warning');
            return $this->vaiAllaDelega($delega);
        }
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $delega->studio;
        $versamento->soggetto = $delega->soggetto;
        $versamento->delega = $delega;
        $versamento->scadenza = $delega->scadenza;
        $versamento->sezione = $sezione;
        if ($sezione->getSezioneCodici() !== null) {
            $versamento->annoRiferimento = (int) $delega->scadenza->format('Y');
        }
        return $this->gestisci($request, $versamento, 'Nuovo rigo: ' . $sezione->getLabel());
    }

    public function update(Request $request, Versamento $versamento): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        if (($versamento->delega !== null) && (($bloccata = $this->verificaModificabile($versamento->delega)) !== null)) {
            return $bloccata;
        }
        if ($versamento->isGenerata() || ($versamento->credito !== null)) {
            $this->aggiungiMessaggio($versamento->credito !== null ? 'L\'utilizzo del credito si modifica eliminandolo e compensando di nuovo.'
                                : 'Il versamento è generato dal modulo ' . $versamento->origine . ': correggilo lì, verrà aggiornato.', 'warning');
            return $this->tornaDalVersamento($versamento);
        }
        return $this->gestisci($request, $versamento, 'Modifica versamento: ' . $versamento->sezione->getLabel());
    }

    private function gestisci(Request $request, Versamento $versamento, string $titolo): Response
    {
        $form = new VersamentoForm($versamento);
        if ($this->isInvioValido($request, $form)) {
            $versamento = $form->resolveEntity();
            $primaTransazione = $this->dataMapper->startTransaction();
            if (($versamento->delega !== null) && (isset($versamento->id) === false)) {
                $versamento->ordine = (new VersamentoModel($this->dataMapper))->getProssimoOrdine($versamento->delega, $versamento->sezione);
            }
            $this->dataMapper->save($versamento);
            if ($versamento->delega !== null) {
                $versamento->delega->dataModifica = new SismaDateTime();
                $this->dataMapper->save($versamento->delega);
            }
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio($versamento->delega === null ? 'Versamento salvato tra quelli da versare.' : 'Rigo salvato.');
            return $this->tornaDalVersamento($versamento);
        }
        $this->impostaVariabiliForm($form, $versamento);
        $this->vars['versamento'] = $versamento;
        $this->vars['sezione'] = $versamento->sezione;
        // dopo un invio con errori il codice è quello inviato, non quello dell'entità
        $codiceTributo = $this->vars['formEntity']->codiceTributo ?? null;
        $this->vars['codiceTributoCorrente'] = $codiceTributo?->getEtichetta();
        $tabelle = new CodiceTabellaF24Model($this->dataMapper);
        $this->vars['regioni'] = $versamento->sezione === SezioneF24::regioni ? $tabelle->getVoci(TabellaF24::regioni) : [];
        $this->vars['altriEnti'] = $versamento->sezione === SezioneF24::altriEnti ? $tabelle->getVoci(TabellaF24::altriEnti) : [];
        $this->vars['tipiElide'] = $versamento->sezione === SezioneF24::elide ? $tabelle->getVoci(TabellaF24::tipiElide) : [];
        $this->vars['formatoRateazione'] = $codiceTributo?->getDescrizioneRateazione();
        $this->vars['descrizioneIdentificativo'] = $codiceTributo?->codiceIdentificativoEnte;
        $this->vars['pageTitle'] = $titolo;
        if ($versamento->delega === null) {
            $this->impostaBricioleContribuente($versamento->soggetto, $titolo);
        } else {
            $this->impostaBriciole($versamento->delega, $titolo);
        }
        return $this->render->generateView('versamento/form', $this->vars, $form->getResponseType());
    }

    /**
     * Copia del versamento nell'archivio: form già compilato con la data al
     * mese successivo e i riferimenti aggiornati (CopiaVersamenti).
     */
    public function copia(Request $request, Versamento $versamento): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        if ($versamento->credito !== null) {
            $this->aggiungiMessaggio('Gli utilizzi dei crediti non si copiano: si compensano nella delega.', 'warning');
            return $this->tornaDalVersamento($versamento);
        }
        $copia = (new CopiaVersamenti($this->dataMapper))->copia($versamento, 1) ?? (new CopiaVersamenti($this->dataMapper))->copia($versamento, 1, 0);
        return $this->gestisci($request, $copia, 'Copia versamento: ' . $versamento->sezione->getLabel());
    }

    /**
     * Ripetizione del versamento a cadenza regolare: anteprima delle copie
     * con data e riferimenti, poi creazione nell'archivio.
     */
    public function ripeti(Request $request, Versamento $versamento, string $periodicita = 'M', int $volte = 3): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        if ($versamento->credito !== null) {
            $this->aggiungiMessaggio('Gli utilizzi dei crediti non si ripetono: si compensano nella delega.', 'warning');
            return $this->tornaDalVersamento($versamento);
        }
        $cadenza = Periodicita::tryFrom($periodicita) ?? Periodicita::mensile;
        $volte = min(max(1, $volte), CopiaVersamenti::MASSIMO_RIPETIZIONI);
        $copiaVersamenti = new CopiaVersamenti($this->dataMapper);
        $copie = $copiaVersamenti->ripeti($versamento, $cadenza, $volte);
        $errori = $copiaVersamenti->verifica($copie);
        if (($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verificaCsrf($request);
            if ((count($copie) > 0) && (count($errori) === 0)) {
                $copiaVersamenti->salva($copie);
                $this->aggiungiMessaggio(sprintf('Creati %d versamenti da versare, dal %s al %s.', count($copie), $copie[0]->scadenza->format('d/m/Y'), end($copie)->scadenza->format('d/m/Y')));
                return $this->vaiAlContribuente($versamento->soggetto);
            }
            $this->aggiungiMessaggio('Correggi il versamento di partenza: alcune copie non sono valide.', 'danger');
        }
        $this->vars['versamento'] = $versamento;
        $this->vars['copie'] = $copie;
        $this->vars['erroriCopie'] = $errori;
        $this->vars['cadenza'] = $cadenza;
        $this->vars['cadenze'] = Periodicita::cases();
        $this->vars['volte'] = $volte;
        $this->vars['massimoRipetizioni'] = CopiaVersamenti::MASSIMO_RIPETIZIONI;
        $this->vars['pageTitle'] = 'Ripeti versamento';
        $this->impostaBricioleContribuente($versamento->soggetto, 'Ripeti versamento');
        return $this->render->generateView('versamento/ripeti', $this->vars);
    }

    /**
     * Toglie il versamento dalla delega in bozza: torna tra quelli da
     * versare. Gli utilizzi dei crediti e i versamenti calcolati nella
     * delega (ravvedimento, sanzioni civili) si eliminano.
     */
    public function rimuovi(Request $request, Versamento $versamento): Response
    {
        $delega = $versamento->delega;
        $this->autorizzaSoggetto($versamento->soggetto);
        $this->verificaCsrf($request);
        if ($delega === null) {
            return $this->vaiAlContribuente($versamento->soggetto);
        }
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        if (($versamento->credito !== null) || in_array($versamento->origine, self::ORIGINI_DELEGA, true)) {
            $this->aggiungiMessaggio('Questo rigo appartiene alla delega: si elimina.', 'warning');
            return $this->vaiAllaDelega($delega);
        }
        $versamento->delega = null;
        $this->dataMapper->save($versamento);
        $this->aggiornaDetrazioneImu($delega, $versamento);
        $this->aggiungiMessaggio('Versamento tolto dalla delega: è tra quelli da versare del contribuente.');
        return $this->vaiAllaDelega($delega);
    }

    public function delete(Request $request, Versamento $versamento): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        $this->verificaCsrf($request);
        if (($versamento->delega !== null) && (($bloccata = $this->verificaModificabile($versamento->delega)) !== null)) {
            return $bloccata;
        }
        $risposta = $this->tornaDalVersamento($versamento);
        $delega = $versamento->delega;
        $this->dataMapper->delete($versamento);
        if ($delega !== null) {
            $this->aggiornaDetrazioneImu($delega, $versamento);
        }
        $this->aggiungiMessaggio($versamento->isGenerata() ? 'Versamento eliminato: un nuovo calcolo nel modulo ' . $versamento->origine . ' lo riproporrà.' : 'Versamento eliminato.');
        return $risposta;
    }

    /**
     * Se il versamento uscito dalla delega riportava la detrazione IMU, la
     * detrazione della delega si ricalcola sui righi rimasti.
     */
    private function aggiornaDetrazioneImu(Delega $delega, Versamento $uscito): void
    {
        if ($uscito->detrazioneImu !== null) {
            (new GenerazioneDeleghe($this->dataMapper))->aggiornaDetrazioneImu($delega, true);
            $this->dataMapper->save($delega);
        }
    }
}
