<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Deleghe\Application\Models\IntermediarioModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Services\AttivitaSeparate;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Entities\RettificaDetrazione;
use Dichiarativi\Application\Entities\SottoscrizioneControllo;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
use Dichiarativi\Application\Enumerations\CinquePerMille;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\OttoPerMille;
use Dichiarativi\Application\Services\Redditi\PartitiDuePerMille;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Forms\DichiarazioneForm;
use Dichiarativi\Application\Models\CampoModelloModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\ModuloDichiarazioneModel;
use Dichiarativi\Application\Models\QuadroModelloModel;
use Dichiarativi\Application\Models\RettificaDetrazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Models\ValoreDichiarazioneModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\ControlliTr;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Redditi\ControlliRedditi;
use Dichiarativi\Application\Services\Redditi\PropostaRedditi;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Dichiarativi\Application\Services\Iva\PropostaTr;
use Dichiarativi\Application\Services\Iva\ProspettoRettifiche;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\Iva\VersamentiTr;
use Dichiarativi\Application\Services\ModelliContribuente;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Telematico\GeneratoreIva;
use Dichiarativi\Application\Services\Telematico\GeneratoreTr;
use Dichiarativi\Application\Services\Telematico\GeneratoreRedditi;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazione del contribuente: creazione con la proposta dai dati della
 * suite, quadri (valori proposti e corretti a mano), frontespizio,
 * controlli, versamenti e crediti per Deleghe F24, stato di
 * presentazione, file telematico e stampa di controllo.
 */
class DichiarazioneController extends DichiarativiController
{

    /**
     * Crea la dichiarazione del modello per l'anno e ne propone i quadri.
     */
    public function crea(Request $request, Soggetto $soggetto, string $modello, int $anno, int $trimestre = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $anno = self::normalizzaAnno($anno);
        $modello = ModelloDichiarativo::tryFrom($modello);
        $trimestre = ($modello?->isTrimestrale() ?? false) ? $trimestre : null;
        if (($trimestre !== null) && (($trimestre < 1) || ($trimestre > 3))) {
            $this->aggiungiMessaggio('Trimestre non valido: il modello IVA TR si presenta per i primi tre trimestri.', 'warning');
            return $this->vaiAlContribuente($soggetto, $anno);
        }
        $model = new DichiarazioneModel($this->dataMapper);
        if (($modello === null) || ($modello->isGestito() === false)) {
            $this->aggiungiMessaggio('Modello di dichiarazione non ancora gestito.', 'warning');
            return $this->vaiAlContribuente($soggetto, $anno);
        }
        if (($esistente = $model->getPerModello($soggetto, $modello, $anno, $trimestre)) !== null) {
            return $this->vaiAllaDichiarazione($esistente);
        }
        $motivo = (new ModelliContribuente($this->dataMapper))->getModelli($soggetto, $anno)[$modello->value];
        if ($motivo !== null) {
            $this->aggiungiMessaggio($motivo, 'warning');
            return $this->vaiAlContribuente($soggetto, $anno);
        }
        if ((new CampoModelloModel($this->dataMapper))->contaPerModello($modello, ModelloDichiarativo::getAnnoModello($anno, $modello)) === 0) {
            $this->aggiungiMessaggio(sprintf('Catalogo dei campi del modello %s %d non installato: eseguire "sisma tabelle-dichiarativi".', $modello->getSigla(), ModelloDichiarativo::getAnnoModello($anno, $modello)), 'danger');
            return $this->vaiAlContribuente($soggetto, $anno);
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = $modello;
        $dichiarazione->anno = $anno;
        $dichiarazione->trimestre = $trimestre;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $this->proponiQuadri($dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Dichiarazione ' . $dichiarazione->getDescrizione() . ' proposta dai dati della suite: verificare quadri e avvisi.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    public function show(Dichiarazione $dichiarazione, string $quadro = '', int $modulo = 1): Response
    {
        $this->autorizza($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $moduli = $this->getModuli($dichiarazione, $quadri);
        $modulo = isset($moduli[$modulo]) ? $modulo : 1;
        $quadri->usaModulo($modulo);
        $elencoQuadri = $this->getElencoQuadri($dichiarazione, $quadri, $modulo);
        $quadro = isset($elencoQuadri[$quadro]) ? $quadro : array_key_first($elencoQuadri);
        $controlli = $this->getControlli($dichiarazione);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['quadri'] = $quadri;
        $this->vars['elencoQuadri'] = $elencoQuadri;
        $this->vars['quadro'] = $quadro;
        $this->vars['righe'] = $this->getRighe($quadri, $quadro, false);
        $this->vars['modulo'] = $modulo;
        $this->vars['moduliDichiarazione'] = $moduli;
        $this->vars['altriQuadri'] = array_diff_key((new QuadroModelloModel($this->dataMapper))->getDenominazioni($dichiarazione->modello, $dichiarazione->getAnnoModello()), $elencoQuadri);
        $this->vars['tipiModulo'] = array_values(array_filter(TipoModulo::cases(), fn(TipoModulo $tipo) => in_array($tipo, [TipoModulo::principale, TipoModulo::societaGruppo], true) === false));
        $this->vars['sottoscrizioni'] = (new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione);
        $this->vars['tipiSottoscrizione'] = SottoscrizioneControllo::SOGGETTI;
        $this->vars['modalitaSaldo'] = ModalitaSaldo::cases();
        $this->vars['dataDifferimento'] = VersamentiIva::getDataDifferimento($dichiarazione);
        $this->vars['errori'] = $controlli->getErrori($dichiarazione, $quadri);
        $this->vars['segnalazioni'] = $controlli->getSegnalazioni($quadri);
        $quadri->usaModulo($modulo);
        $this->vars['avvertenze'] = $controlli->getAvvertenze($quadri, $dichiarazione);
        $this->vars['versamenti'] = iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($dichiarazione->soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento()), false);
        $this->vars['credito'] = match (true) {
            $dichiarazione->modello->isRedditi() => null,
            $dichiarazione->modello->isTrimestrale() => (new VersamentiTr($this->dataMapper))->getCredito($dichiarazione),
            default => (new VersamentiIva($this->dataMapper))->getCredito($dichiarazione),
        };
        $this->vars['riepilogoRedditi'] = $dichiarazione->modello->isRedditi() ? PropostaRedditi::getRiepilogo($quadri) : [];
        $this->vars['naturaGiuridica'] = $dichiarazione->naturaGiuridica === null ? null : (Frontespizio::TABELLA_NATURA_GIURIDICA[$dichiarazione->naturaGiuridica] ?? null);
        $this->vars['pageTitle'] = $dichiarazione->getDescrizione() . ' · ' . $dichiarazione->soggetto->nominativo;
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $dichiarazione->getDescrizione());
        return $this->render->generateView('dichiarazione/show', $this->vars);
    }

    /**
     * Modifica dei campi di un quadro: i valori cambiati diventano manuali
     * (la proposta non li sovrascrive), "ripristina" li riporta al valore
     * proposto; poi i quadri sono ricalcolati.
     */
    public function quadro(Request $request, Dichiarazione $dichiarazione, string $quadro, int $modulo = 1): Response
    {
        $this->autorizza($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $moduli = $this->getModuli($dichiarazione);
        $denominazioni = (new QuadroModelloModel($this->dataMapper))->getDenominazioni($dichiarazione->modello, $dichiarazione->getAnnoModello());
        if ((isset($denominazioni[$quadro]) === false) || (isset($moduli[$modulo]) === false)) {
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $quadri->usaModulo($modulo);
        $errori = [];
        if (($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verificaCsrf($request);
            $valori = is_array($request->input['valori'] ?? null) ? $request->input['valori'] : [];
            $ripristina = is_array($request->input['ripristina'] ?? null) ? $request->input['ripristina'] : [];
            $modificati = 0;
            foreach ($this->getRighe($quadri, $quadro, true) as $riga) {
                $codice = $riga['campo']->codice;
                if (in_array($codice, $ripristina, true)) {
                    $quadri->ripristina($codice);
                    $modificati++;
                    continue;
                }
                $inviato = $riga['campo']->formato?->isCasella() ? (isset($valori[$codice]) ? '1' : '') : (string) ($valori[$codice] ?? '');
                try {
                    $normalizzato = $riga['campo']->formato?->normalizza($inviato);
                } catch (\InvalidArgumentException $errore) {
                    $errori[$codice] = $errore->getMessage();
                    continue;
                }
                $attuale = $quadri->get($codice);
                if ($normalizzato !== (($attuale === '') ? null : $attuale)) {
                    $quadri->imposta($codice, $inviato);
                    $modificati++;
                }
            }
            if (count($errori) === 0) {
                $primaTransazione = $this->dataMapper->startTransaction();
                $quadri->salva();
                $this->proponiQuadri($dichiarazione);
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio($modificati === 0 ? 'Nessun campo modificato.' : 'Quadro ' . $quadro . ' salvato e totali ricalcolati (' . $modificati . ' campi modificati).');
                return $this->vaiAllaDichiarazione($dichiarazione, $quadro, $modulo);
            }
        }
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['quadri'] = $quadri;
        $this->vars['quadro'] = $quadro;
        $this->vars['modulo'] = $modulo;
        $this->vars['etichettaModulo'] = $moduli[$modulo];
        $this->vars['denominazioneQuadro'] = $denominazioni[$quadro];
        $this->vars['righe'] = $this->getRighe($quadri, $quadro, true);
        $this->vars['erroriCampi'] = $errori;
        $this->vars['inviati'] = $request->input['valori'] ?? null;
        $this->vars['pageTitle'] = 'Quadro ' . $quadro . ' · ' . $dichiarazione->getDescrizione();
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('dichiarazione/quadro', $this->vars);
    }

    /**
     * Prospetto D delle rettifiche della detrazione: il totale va nel rigo
     * VF70 del modulo, ricalcolando i quadri.
     */
    public function rettifiche(Request $request, Dichiarazione $dichiarazione, int $modulo = 1): Response
    {
        $this->autorizza($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $moduli = $this->getModuli($dichiarazione);
        if (($dichiarazione->modello->isTrimestrale()) || (isset($moduli[$modulo]) === false)) {
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $model = new RettificaDetrazioneModel($this->dataMapper);
        $esistenti = $model->getRighi($dichiarazione, $modulo);
        $errori = [];
        $prospetto = new ProspettoRettifiche($this->dataMapper);
        $proposta = $prospetto->getProposta($dichiarazione->soggetto, $dichiarazione->anno, $this->getAttivitaModulo($dichiarazione, $modulo));
        if (($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verificaCsrf($request);
            $importi = is_array($request->input['importi'] ?? null) ? $request->input['importi'] : [];
            $note = is_array($request->input['note'] ?? null) ? $request->input['note'] : [];
            $valori = [];
            foreach (array_keys(ProspettoRettifiche::RIGHI) as $rigo) {
                $testo = str_replace(['.', ','], ['', '.'], trim((string) ($importi[$rigo] ?? '')));
                if (($testo !== '') && (is_numeric($testo) === false)) {
                    $errori[$rigo] = 'Importo non valido nel rigo ' . $rigo . '.';
                }
                $valori[$rigo] = $testo === '' ? null : (float) $testo;
            }
            if (count($errori) === 0) {
                $primaTransazione = $this->dataMapper->startTransaction();
                $aggiornata = $prospetto->salvaEsclusi($proposta, is_array($request->input['esclusi'] ?? null) ? $request->input['esclusi'] : []);
                // il rigo 3 segue la proposta finché non è stato cambiato a mano
                if (($valori[3] !== null) && (count($proposta['beni']) > 0) && (abs($valori[3] - $proposta['totale']) < 0.005)) {
                    $valori[3] = (float) $aggiornata['totale'];
                }
                foreach ($valori as $rigo => $valore) {
                    $this->salvaRettifica($dichiarazione, $modulo, $rigo, $valore, trim((string) ($note[$rigo] ?? '')), $esistenti[$rigo] ?? null);
                }
                $this->proponiQuadri($dichiarazione);
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Prospetto D salvato e quadri ricalcolati.');
                return $this->vaiAllaDichiarazione($dichiarazione, 'VF', $modulo);
            }
        }
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['modulo'] = $modulo;
        $this->vars['etichettaModulo'] = $moduli[$modulo];
        $this->vars['righi'] = ProspettoRettifiche::RIGHI;
        $this->vars['esistenti'] = $esistenti;
        $this->vars['proposta'] = $proposta;
        $this->vars['erroriCampi'] = $errori;
        $this->vars['pageTitle'] = 'Prospetto D · ' . $dichiarazione->getDescrizione();
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('dichiarazione/rettifiche', $this->vars);
    }

    /**
     * Attività separata del modulo: la prima è il modulo 1.
     */
    private function getAttivitaModulo(Dichiarazione $dichiarazione, int $modulo): ?AttivitaIva
    {
        if ($modulo === 1) {
            $attivita = (new AttivitaSeparate($this->dataMapper))->getAttivita($dichiarazione->soggetto, $dichiarazione->anno);
            return count($attivita) === 0 ? null : reset($attivita);
        }
        foreach ((new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione) as $moduloDichiarazione) {
            if ($moduloDichiarazione->numero === $modulo) {
                return $moduloDichiarazione->attivitaIva;
            }
        }
        return null;
    }

    private function salvaRettifica(Dichiarazione $dichiarazione, int $modulo, int $rigo, ?float $importo, string $nota, ?RettificaDetrazione $rettifica): void
    {
        if ($importo === null) {
            if ($rettifica !== null) {
                $this->dataMapper->delete($rettifica);
            }
            return;
        }
        $rettifica ??= new RettificaDetrazione($this->dataMapper);
        $rettifica->dichiarazione = $dichiarazione;
        $rettifica->modulo = $modulo;
        $rettifica->rigo = $rigo;
        $rettifica->importo = $importo;
        $rettifica->nota = $nota === '' ? null : mb_substr($nota, 0, 255);
        $this->dataMapper->save($rettifica);
    }

    /**
     * Ripropone i quadri dai dati della suite (i valori manuali restano).
     */
    public function proponi(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $this->proponiQuadri($dichiarazione);
        $this->aggiungiMessaggio('Quadri riproposti dai dati della suite; i valori inseriti a mano sono stati mantenuti.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    public function update(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $form = new DichiarazioneForm($dichiarazione);
        if ($this->isInvioValido($request, $form)) {
            $dichiarazione = $form->resolveEntity();
            foreach ([$dichiarazione->dichiarante, $dichiarazione->intermediario?->soggetto, $dichiarazione->vistoProfessionista, $dichiarazione->vistoCaf, $dichiarazione->vistoResponsabileCaf] as $collegato) {
                if (($collegato !== null) && ($collegato->studio->id !== $this->studio->id)) {
                    return $this->vaiAllaDichiarazione($dichiarazione);
                }
            }
            $this->dataMapper->save($dichiarazione);
            $this->aggiungiMessaggio('Frontespizio salvato.');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $dichiarazione;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['tipologie'] = TipologiaDichiarazione::cases();
        $this->vars['codiciCarica'] = CodiceCaricaIva::cases();
        $this->vars['impegni'] = ImpegnoPresentazione::cases();
        $this->vars['nature'] = Frontespizio::TABELLA_NATURA_GIURIDICA;
        $this->vars['istituzioniOttoPerMille'] = OttoPerMille::cases();
        $this->vars['finalitaStato'] = OttoPerMille::getFinalitaStato();
        $this->vars['finalitaCinquePerMille'] = CinquePerMille::cases();
        $partiti = [];
        foreach (PartitiDuePerMille::getPartiti($dichiarazione->anno) as $codice => $partito) {
            $partiti[$codice] = $partito . ' (' . $codice . ')';
        }
        $this->vars['partitiDuePerMille'] = $partiti;
        $this->vars['intermediari'] = iterator_to_array((new IntermediarioModel($this->dataMapper))->getElenco($this->studio));
        $this->vars['pageTitle'] = 'Frontespizio · ' . $dichiarazione->getDescrizione();
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('dichiarazione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Registra in Deleghe F24 il saldo da versare e il credito da
     * compensare (oltre 5.000 euro solo alla presentazione).
     */
    public function versamenti(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if ($dichiarazione->modello->isRedditi()) {
            $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
            $servizio = new VersamentiRedditi($this->dataMapper);
            try {
                $primaTransazione = $this->dataMapper->startTransaction();
                $messaggi = array_merge($servizio->registraVersamenti($dichiarazione, $quadri), $servizio->registraCrediti($dichiarazione, $quadri));
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio(implode(' ', $messaggi), 'info');
            } catch (\InvalidArgumentException $errore) {
                $this->aggiungiMessaggio('Versamenti non registrati: ' . $errore->getMessage(), 'danger');
            }
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $servizio = new VersamentiIva($this->dataMapper);
        $modalita = ModalitaSaldo::tryFrom((string) ($request->input['modalitaSaldo'] ?? '')) ?? $dichiarazione->modalitaSaldo;
        $rate = (int) ($request->input['numeroRate'] ?? 0);
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataDifferimento'] ?? ''));
        $dichiarazione->modalitaSaldo = $modalita;
        $dichiarazione->numeroRate = $rate > 1 ? $rate : null;
        $dichiarazione->dataDifferimento = ($modalita === ModalitaSaldo::differito) && ($data !== false) ? new SismaDate($data->format('Y-m-d')) : null;
        $dichiarazione->maggiorazioneUlteriore = ($modalita === ModalitaSaldo::differito) && isset($request->input['maggiorazioneUlteriore']);
        try {
            $primaTransazione = $this->dataMapper->startTransaction();
            $this->dataMapper->save($dichiarazione);
            $messaggi = $dichiarazione->modello->isTrimestrale() ? (new VersamentiTr($this->dataMapper))->registraCredito($dichiarazione, $quadri)
                    : array_merge($servizio->registraSaldo($dichiarazione, $quadri), $servizio->registraCredito($dichiarazione, $quadri), $servizio->registraProrata($dichiarazione, $quadri));
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio(implode(' ', $messaggi), 'info');
        } catch (\InvalidArgumentException $errore) {
            $this->aggiungiMessaggio('Versamenti non registrati: ' . $errore->getMessage(), 'danger');
        }
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    /**
     * Segna la dichiarazione come presentata (data e protocollo, credito in
     * Deleghe) o la riporta in bozza.
     */
    public function stato(Request $request, Dichiarazione $dichiarazione, StatoDichiarazione $stato): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $servizio = new VersamentiIva($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        if ($stato === StatoDichiarazione::presentata) {
            $controlli = $this->getControlli($dichiarazione);
            $errori = $controlli->getErrori($dichiarazione, $quadri);
            if ((count($controlli->getSegnalazioni($quadri)) > 0) && ($dichiarazione->flagConferma === false)) {
                $errori[] = 'Dati non coerenti da correggere o confermare nel frontespizio.';
            }
            if (count($errori) > 0) {
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Correggere prima la dichiarazione: ' . implode(' ', $errori), 'danger');
                return $this->vaiAllaDichiarazione($dichiarazione);
            }
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataPresentazione'] ?? ''));
            $dichiarazione->dataPresentazione = new SismaDate($data === false ? 'today' : $data->format('Y-m-d'));
            $protocollo = trim((string) ($request->input['protocollo'] ?? ''));
            $dichiarazione->protocollo = $protocollo === '' ? null : mb_substr($protocollo, 0, 50);
            $dichiarazione->stato = $stato;
            $this->dataMapper->save($dichiarazione);
            $messaggi = match (true) {
                $dichiarazione->modello->isRedditi() => (new VersamentiRedditi($this->dataMapper))->registraCrediti($dichiarazione, $quadri),
                $dichiarazione->modello->isTrimestrale() => (new VersamentiTr($this->dataMapper))->registraCredito($dichiarazione, $quadri),
                default => array_merge($servizio->registraCredito($dichiarazione, $quadri), $servizio->registraProrata($dichiarazione, $quadri)),
            };
        } else {
            $dichiarazione->dataPresentazione = null;
            $dichiarazione->protocollo = null;
            $dichiarazione->stato = $stato;
            $this->dataMapper->save($dichiarazione);
            $messaggi = match (true) {
                $dichiarazione->modello->isRedditi() => (new VersamentiRedditi($this->dataMapper))->annullaCrediti($dichiarazione),
                $dichiarazione->modello->isTrimestrale() => (new VersamentiTr($this->dataMapper))->annullaCredito($dichiarazione),
                default => $servizio->annullaCredito($dichiarazione),
            };
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio(trim('Dichiarazione ' . mb_strtolower($stato->getLabel()) . '. ' . implode(' ', $messaggi)));
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    public function delete(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $primaTransazione = $this->dataMapper->startTransaction();
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento(), []);
        $messaggi = match (true) {
            $dichiarazione->modello->isRedditi() => (new VersamentiRedditi($this->dataMapper))->annullaCrediti($dichiarazione),
            $dichiarazione->modello->isTrimestrale() => (new VersamentiTr($this->dataMapper))->annullaCredito($dichiarazione),
            default => (new VersamentiIva($this->dataMapper))->annullaCredito($dichiarazione),
        };
        foreach ((new ValoreDichiarazioneModel($this->dataMapper))->getValori($dichiarazione) as $valore) {
            $this->dataMapper->delete($valore);
        }
        foreach ((new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione) as $modulo) {
            $this->dataMapper->delete($modulo);
        }
        foreach ((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione) as $sottoscrizione) {
            $this->dataMapper->delete($sottoscrizione);
        }
        $this->dataMapper->delete($dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio(trim('Dichiarazione eliminata. ' . implode(' ', array_merge($messaggi, $esito->differenze))));
        return $this->vaiAlContribuente($soggetto, $anno);
    }

    public function scarica(Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        try {
            $this->vars['contenuto'] = match (true) {
                $dichiarazione->modello->isRedditi() => (new GeneratoreRedditi($this->dataMapper))->genera($dichiarazione),
                $dichiarazione->modello->isTrimestrale() => (new GeneratoreTr($this->dataMapper))->genera($dichiarazione),
                default => (new GeneratoreIva($this->dataMapper))->genera($dichiarazione),
            };
        } catch (\InvalidArgumentException | \LengthException $errore) {
            $this->aggiungiMessaggio('File non generato: ' . $errore->getMessage(), 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->vars['nomeFile'] = match (true) {
            $dichiarazione->modello->isRedditi() => GeneratoreRedditi::getNomeFile($dichiarazione),
            $dichiarazione->modello->isTrimestrale() => GeneratoreTr::getNomeFile($dichiarazione),
            default => GeneratoreIva::getNomeFile($dichiarazione),
        };
        return $this->render->generateData('dichiarazione/scarica', $this->vars);
    }

    public function stampa(Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $sezioni = [];
        foreach ($this->getModuli($dichiarazione) as $modulo => $etichetta) {
            $quadri->usaModulo($modulo);
            foreach ($this->getElencoQuadri($dichiarazione, $quadri, $modulo) as $sigla => $denominazione) {
                $righe = $this->getRighe($quadri, $sigla, false);
                if (count($righe) > 0) {
                    $sezioni[] = ['modulo' => $modulo, 'etichetta' => $etichetta, 'quadro' => $sigla, 'denominazione' => $denominazione, 'righe' => $righe];
                }
            }
        }
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['sezioni'] = $sezioni;
        $this->vars['naturaGiuridica'] = $dichiarazione->naturaGiuridica === null ? null : (Frontespizio::TABELLA_NATURA_GIURIDICA[$dichiarazione->naturaGiuridica] ?? null);
        $this->vars['pageTitle'] = $dichiarazione->getDescrizione() . ' · ' . $dichiarazione->soggetto->nominativo;
        return $this->render->generateView('dichiarazione/stampa', $this->vars);
    }

    private function proponiQuadri(Dichiarazione $dichiarazione): void
    {
        if ($dichiarazione->modello === ModelloDichiarativo::iva) {
            (new PropostaIva($this->dataMapper))->proponi($dichiarazione);
        } elseif ($dichiarazione->modello === ModelloDichiarativo::ivaTr) {
            (new PropostaTr($this->dataMapper))->proponi($dichiarazione);
        } elseif ($dichiarazione->modello === ModelloDichiarativo::redditiPf) {
            (new PropostaRedditi($this->dataMapper))->proponi($dichiarazione);
        }
    }

    /**
     * Quadri della dichiarazione: quelli del modello e quelli compilati a
     * mano, con la denominazione.
     *
     * @return array<string, string>
     */
    private function getElencoQuadri(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, int $modulo = 1): array
    {
        $denominazioni = (new QuadroModelloModel($this->dataMapper))->getDenominazioni($dichiarazione->modello, $dichiarazione->getAnnoModello());
        $predefiniti = match (true) {
            ($modulo === 1) || $dichiarazione->modello->isTrimestrale() => $dichiarazione->modello->getQuadri(),
            $dichiarazione->modello->isRedditi() => [],
            default => array_diff($dichiarazione->modello->getQuadri(), PropostaIva::QUADRI_PRIMO_MODULO, ['VH']),
        };
        $elenco = [];
        foreach (array_unique(array_merge($predefiniti, array_diff($quadri->getQuadriCompilati($modulo), ['V1']))) as $sigla) {
            $elenco[$sigla] = $denominazioni[$sigla] ?? $sigla;
        }
        return $elenco;
    }

    /**
     * Campi del quadro con il valore: tutti quelli del catalogo con
     * $tutti, altrimenti i soli compilati.
     *
     * @return list<array{campo: \Dichiarativi\Application\Entities\CampoModello, valore: ?string, manuale: bool}>
     */
    private function getRighe(QuadriDichiarazione $quadri, string $quadro, bool $tutti): array
    {
        $righe = [];
        foreach ($quadri->getCatalogo() as $codice => $campo) {
            if (($campo->quadro !== $quadro) || (($tutti === false) && ($quadri->get($codice) === null))) {
                continue;
            }
            $righe[] = ['campo' => $campo, 'valore' => $quadri->get($codice), 'manuale' => $quadri->isManuale($codice)];
        }
        return $righe;
    }

    /**
     * Aggiunge un modulo: attività separata, soggetto partecipante a
     * un'operazione straordinaria (con la contabilità da cui proporlo) o
     * prima frazione d'anno.
     */
    public function aggiungiModulo(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $tipo = TipoModulo::tryFrom((string) ($request->input['tipo'] ?? ''));
        $fonte = ctype_digit((string) ($request->input['soggettoFonte'] ?? '')) ? (new SoggettoModel($this->dataMapper))->getEntityById((int) $request->input['soggettoFonte']) : null;
        if (($tipo === null) || ($tipo === TipoModulo::principale) || (($fonte !== null) && ($fonte->studio->id !== $this->studio->id))) {
            $this->aggiungiMessaggio('Tipo di modulo o soggetto non validi.', 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        if (($tipo === TipoModulo::soggettoTrasformato) && ($fonte === null)) {
            $this->aggiungiMessaggio('Indicare il soggetto partecipante all\'operazione straordinaria (dall\'Anagrafica).', 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $esistenti = (new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione);
        $modulo = new ModuloDichiarazione($this->dataMapper);
        $modulo->dichiarazione = $dichiarazione;
        $modulo->numero = max(array_merge([1], array_map(fn(ModuloDichiarazione $esistente) => $esistente->numero, $esistenti))) + 1;
        $modulo->tipo = $tipo;
        $modulo->soggettoFonte = $tipo->hasFonte() ? $fonte : null;
        $descrizione = trim((string) ($request->input['descrizione'] ?? ''));
        $modulo->descrizione = $descrizione === '' ? null : mb_substr($descrizione, 0, 255);
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataOperazione'] ?? ''));
        $modulo->dataOperazione = ($tipo === TipoModulo::soggettoTrasformato) && ($data !== false) ? new SismaDate($data->format('Y-m-d')) : null;
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($modulo);
        $this->proponiQuadri($dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio($modulo->getEtichetta() . ' aggiunto' . ($modulo->soggettoFonte === null ? ': compilarne i quadri.' : ' e proposto dalla contabilità del soggetto.'));
        return $this->vaiAllaDichiarazione($dichiarazione, '', $modulo->numero);
    }

    /**
     * Elimina un modulo oltre al primo con i suoi valori; i moduli
     * successivi sono rinumerati.
     */
    public function eliminaModulo(Request $request, Dichiarazione $dichiarazione, int $numero): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $moduli = (new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione);
        foreach ($moduli as $modulo) {
            if (($modulo->numero === $numero) && ($modulo->attivitaIva !== null)) {
                $this->aggiungiMessaggio('Il modulo dell\'attività separata segue le attività della Contabilità: indicare lì l\'ultimo anno dell\'attività.', 'warning');
                return $this->vaiAllaDichiarazione($dichiarazione);
            }
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($moduli as $modulo) {
            if ($modulo->numero === $numero) {
                $quadri->eliminaModulo($numero);
                $this->dataMapper->delete($modulo);
            }
        }
        $quadri->salva();
        foreach ((new ValoreDichiarazioneModel($this->dataMapper))->getValori($dichiarazione) as $valore) {
            if ($valore->modulo > $numero) {
                $valore->modulo--;
                $this->dataMapper->save($valore);
            }
        }
        foreach ($moduli as $modulo) {
            if ($modulo->numero > $numero) {
                $modulo->numero--;
                $this->dataMapper->save($modulo);
            }
        }
        $this->proponiQuadri($dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Modulo ' . $numero . ' eliminato.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    /**
     * Aggiunge un componente dell'organo di controllo che sottoscrive la
     * dichiarazione.
     */
    public function aggiungiSottoscrizione(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        $tipo = (int) ($request->input['tipoSoggetto'] ?? 0);
        $soggetto = ctype_digit((string) ($request->input['soggetto'] ?? '')) ? (new SoggettoModel($this->dataMapper))->getEntityById((int) $request->input['soggetto']) : null;
        $esistenti = (new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione);
        if ((isset(SottoscrizioneControllo::SOGGETTI[$tipo]) === false) || ($soggetto === null) || ($soggetto->studio->id !== $this->studio->id) || (count($esistenti) >= SottoscrizioneControllo::MASSIMO)) {
            $this->aggiungiMessaggio('Componente non valido o già cinque componenti indicati.', 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->salvaSottoscrizione($dichiarazione, $tipo, $soggetto, count($esistenti) + 1);
        $this->aggiungiMessaggio('Componente dell\'organo di controllo aggiunto.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    /**
     * Propone l'organo di controllo dai legami dell'Anagrafica: revisore
     * (o società di revisione) e sindaci in carica.
     */
    public function proponiSottoscrizioni(Request $request, Dichiarazione $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        $model = new SottoscrizioneControlloModel($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($model->getSottoscrizioni($dichiarazione) as $sottoscrizione) {
            $this->dataMapper->delete($sottoscrizione);
        }
        $ordine = 0;
        foreach ((new Frontespizio($this->dataMapper))->getOrganoControllo($dichiarazione->soggetto) as [$tipo, $componente]) {
            if ($ordine < SottoscrizioneControllo::MASSIMO) {
                $this->salvaSottoscrizione($dichiarazione, $tipo, $componente, ++$ordine);
            }
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio($ordine === 0 ? 'Nessun revisore o sindaco in carica nei legami dell\'Anagrafica.' : 'Organo di controllo proposto dall\'Anagrafica (' . $ordine . ' componenti).');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    public function eliminaSottoscrizione(Request $request, SottoscrizioneControllo $sottoscrizione): Response
    {
        $dichiarazione = $sottoscrizione->dichiarazione;
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->delete($sottoscrizione);
        foreach ((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione) as $indice => $altra) {
            $altra->ordine = $indice + 1;
            $this->dataMapper->save($altra);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Componente dell\'organo di controllo tolto.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    private function salvaSottoscrizione(Dichiarazione $dichiarazione, int $tipo, Soggetto $soggetto, int $ordine): void
    {
        $sottoscrizione = new SottoscrizioneControllo($this->dataMapper);
        $sottoscrizione->dichiarazione = $dichiarazione;
        $sottoscrizione->ordine = $ordine;
        $sottoscrizione->tipoSoggetto = $tipo;
        $sottoscrizione->soggetto = $soggetto;
        $this->dataMapper->save($sottoscrizione);
    }

    /**
     * Moduli della dichiarazione con l'etichetta, dal primo.
     *
     * @return array<int, string>
     */
    private function getModuli(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): array
    {
        $moduli = [1 => 'Modulo 1 · ' . TipoModulo::principale->getLabel()];
        foreach ((new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione) as $modulo) {
            $moduli[$modulo->numero] = $modulo->getEtichetta();
        }
        // quadri VG e VS successivi al primo (Mod. n. 2, 3, ...) senza un modulo della dichiarazione
        foreach ($quadri?->getModuli() ?? [] as $numero) {
            if (isset($moduli[$numero]) === false) {
                $moduli[$numero] = 'Quadri n. ' . $numero . ' · ' . implode(', ', array_filter($quadri->getQuadriCompilati($numero), fn(string $quadro) => $quadro !== 'V1'));
            }
        }
        ksort($moduli);
        return $moduli;
    }

    /**
     * Controlli del modello della dichiarazione.
     */
    private function getControlli(Dichiarazione $dichiarazione): ControlliIva|ControlliTr|ControlliRedditi
    {
        return match (true) {
            $dichiarazione->modello->isRedditi() => new ControlliRedditi($this->dataMapper),
            $dichiarazione->modello->isTrimestrale() => new ControlliTr($this->dataMapper),
            default => new ControlliIva($this->dataMapper),
        };
    }

    private function autorizza(Dichiarazione $dichiarazione): void
    {
        $this->autorizzaSoggetto($dichiarazione->soggetto);
    }

    private function verificaModificabile(Dichiarazione $dichiarazione): ?Response
    {
        if ($dichiarazione->isModificabile()) {
            return null;
        }
        $this->aggiungiMessaggio('La dichiarazione è presentata: per modificarla riportala in bozza (per la correttiva o l\'integrativa cambiane poi il tipo nel frontespizio).', 'warning');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }
}
