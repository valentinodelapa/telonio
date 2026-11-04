<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Forms\RegistrazioneContabileForm;
use Contabilita\Application\Forms\RigaContabileForm;
use Contabilita\Application\Forms\RigaIvaForm;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\ControparteModel;
use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\CorrispettiviMensili;
use Contabilita\Application\Services\FatturaPa\PropostaDaFattura;
use Contabilita\Application\Services\PropostaRighe;
use Contabilita\Application\Services\RegimeIva;
use Contabilita\Application\Services\SalvataggioRegistrazione;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Prima nota dell'azienda: elenco delle registrazioni dell'esercizio e
 * form della registrazione con testata, righe IVA e righe in partita
 * doppia. "Proponi le righe" compone la partita doppia dalla causale e
 * dalle righe IVA (Services\PropostaRighe), "Aggiungi righe" aggiunge
 * righe vuote; il salvataggio controlla l'insieme
 * (Services\ControlloRegistrazione). Le registrazioni delle fatture
 * elettroniche partono dalla proposta della fattura.
 */
class RegistrazioneController extends ContabilitaController
{

    private const RISULTATI_PER_PAGINA = 30;
    private const RIGHE_VUOTE = 2;

    public function index(Soggetto $soggetto, int $anno = 0, string $causale = '', string $testo = '', string $dal = '', string $al = '', int $pagina = 1): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        if ($esercizio === null) {
            return $this->vaiAllAzienda($soggetto);
        }
        $filtroCausale = CausaleContabile::tryFrom($causale);
        $dataDal = $dal === '' ? null : self::data($dal, $esercizio->dataInizio->format('Y-m-d'));
        $dataAl = $al === '' ? null : self::data($al, $esercizio->dataFine->format('Y-m-d'));
        $model = new RegistrazioneContabileModel($this->dataMapper);
        $totale = $model->conta($esercizio, $filtroCausale, $testo, $dataDal, $dataAl);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $this->vars['registrazioni'] = iterator_to_array($model->cerca($esercizio, $filtroCausale, $testo, $dataDal, $dataAl, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA), false);
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['filtroCausale'] = $filtroCausale;
        $this->vars['testo'] = $testo;
        $this->vars['dal'] = $dataDal;
        $this->vars['al'] = $dataAl;
        $this->vars['causali'] = CausaleContabile::cases();
        $this->vars['causaliManuali'] = CausaleContabile::getManuali();
        $this->vars['sezioneAzienda'] = 'primaNota';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Prima nota ' . $esercizio->getEtichetta();
        $this->impostaBriciole($soggetto, $esercizio->anno, 'Prima nota');
        return $this->render->generateView('registrazione/index', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto, int $anno = 0, string $causale = 'GIR'): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        $tipo = CausaleContabile::tryFrom($causale) ?? CausaleContabile::giroconto;
        if (($esercizio === null) || $esercizio->chiuso || ($tipo === CausaleContabile::liquidazioneIva)) {
            $this->aggiungiMessaggio($esercizio === null ? 'Aprire prima un esercizio.' : ($esercizio->chiuso ? 'L\'esercizio è chiuso.' : 'La liquidazione IVA si registra dalle liquidazioni periodiche.'), 'warning');
            return $this->vaiAllAzienda($soggetto, $esercizio?->anno);
        }
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->causale = $tipo;
        $oggi = new SismaDate(date('Y-m-d'));
        $registrazione->dataRegistrazione = $esercizio->comprende($oggi) ? $oggi : new SismaDate(min($esercizio->dataFine->format('Y-m-d'), max($esercizio->dataInizio->format('Y-m-d'), $oggi->format('Y-m-d'))));
        if ($tipo->isIva()) {
            $registrazione->registroIva = (new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, $tipo->getTipoRegistroIva())[0] ?? null;
            $registrazione->dataCompetenzaIva = $registrazione->dataRegistrazione;
        }
        return $this->gestisci($request, $registrazione, [], [], 'Nuova registrazione: ' . mb_strtolower($tipo->getLabel()));
    }

    public function update(Request $request, RegistrazioneContabile $registrazione): Response
    {
        $this->caricaAzienda($registrazione->soggetto);
        if (($registrazione->origine === CorrispettiviMensili::ORIGINE) && $registrazione->isModificabile()) {
            $data = $registrazione->dataRegistrazione;
            return $this->router->redirect('corrispettivi/mese/registro/' . $registrazione->registroIva->id . '/anno/' . $data->format('Y') . '/mese/' . (int) $data->format('n'));
        }
        if (($registrazione->isModificabile() === false) || $registrazione->isGenerata()) {
            $this->aggiungiMessaggio($registrazione->isGenerata() ? 'La registrazione è generata dalla liquidazione IVA: si aggiorna rielaborando la liquidazione.' : 'L\'esercizio è chiuso.', 'warning');
            return $this->router->redirect('registrazione/show/registrazione/' . $registrazione->id);
        }
        $blocco = (new StampeDefinitive($this->dataMapper))->getBlocco($registrazione);
        if ($blocco !== null) {
            $this->aggiungiMessaggio($blocco, 'warning');
            return $this->router->redirect('registrazione/show/registrazione/' . $registrazione->id);
        }
        $this->getEsercizio($registrazione->soggetto, $registrazione->esercizioContabile->anno);
        $segno = $registrazione->causale->getSegno();
        $righeIva = [];
        foreach ((new RigaIvaModel($this->dataMapper))->getRighe($registrazione) as $riga) {
            $righeIva[] = self::datiRigaIva($riga, $segno);
        }
        $righe = [];
        foreach ((new RigaContabileModel($this->dataMapper))->getRighe($registrazione) as $riga) {
            $righe[] = self::datiRiga($riga);
        }
        return $this->gestisci($request, $registrazione, $righeIva, $righe, 'Registrazione n. ' . $registrazione->numero);
    }

    /**
     * Registrazione proposta dalla fattura elettronica, da confermare.
     */
    public function fattura(Request $request, FatturaElettronica $fattura): Response
    {
        $soggetto = $fattura->soggetto;
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        if ($fattura->stato !== StatoFatturaElettronica::daRegistrare) {
            $this->aggiungiMessaggio('La fattura è ' . mb_strtolower($fattura->stato->getLabel()) . '.', 'warning');
            return $this->router->redirect('fattura-elettronica/show/fattura/' . $fattura->id);
        }
        $proposta = new PropostaDaFattura($this->dataMapper);
        try {
            [$registrazione, $righeIva] = $proposta->proponi($fattura);
        } catch (\DomainException | \UnexpectedValueException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->router->redirect('fattura-elettronica/show/fattura/' . $fattura->id);
        }
        if ($registrazione->esercizioContabile->chiuso) {
            $this->aggiungiMessaggio('L\'esercizio ' . $registrazione->esercizioContabile->getEtichetta() . ' è chiuso.', 'warning');
            return $this->router->redirect('fattura-elettronica/show/fattura/' . $fattura->id);
        }
        $this->getEsercizio($soggetto, $registrazione->esercizioContabile->anno);
        $datiIva = array_map(fn(RigaIva $riga) => self::datiRigaIva($riga, $registrazione->causale->getSegno()), $righeIva);
        $righe = [];
        try {
            foreach ((new PropostaRighe($this->dataMapper))->proponi($this->piano, $registrazione, $righeIva) as $riga) {
                $righe[] = self::datiRiga($riga);
            }
        } catch (\DomainException $errore) {
            $proposta->avvisi[] = $errore->getMessage();
        }
        foreach (array_merge($proposta->avvisi, $fattura->avvisi === null ? [] : explode("\n", $fattura->avvisi)) as $avviso) {
            $this->vars['avvisiProposta'][] = $avviso;
        }
        return $this->gestisci($request, $registrazione, $datiIva, $righe, 'Registrazione della fattura n. ' . $fattura->numero, $fattura);
    }

    public function show(RegistrazioneContabile $registrazione): Response
    {
        $soggetto = $registrazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->getEsercizio($soggetto, $registrazione->esercizioContabile->anno);
        $this->vars['registrazione'] = $registrazione;
        $this->vars['righeIva'] = iterator_to_array((new RigaIvaModel($this->dataMapper))->getRighe($registrazione), false);
        $this->vars['righe'] = iterator_to_array((new RigaContabileModel($this->dataMapper))->getRighe($registrazione), false);
        $this->vars['fattura'] = (new FatturaElettronicaModel($this->dataMapper))->getPerRegistrazione($registrazione);
        $this->vars['sezioneAzienda'] = 'primaNota';
        $this->vars['pageTitle'] = 'Registrazione n. ' . $registrazione->numero;
        $this->impostaBriciole($soggetto, $registrazione->esercizioContabile->anno, 'Registrazione n. ' . $registrazione->numero,
                ['Prima nota' => '/registrazione/index/soggetto/' . $soggetto->id . '/anno/' . $registrazione->esercizioContabile->anno]);
        return $this->render->generateView('registrazione/show', $this->vars);
    }

    public function delete(Request $request, RegistrazioneContabile $registrazione): Response
    {
        $soggetto = $registrazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $anno = $registrazione->esercizioContabile->anno;
        if ($registrazione->isModificabile() === false) {
            $this->aggiungiMessaggio('L\'esercizio è chiuso: la registrazione non si elimina.', 'warning');
            return $this->router->redirect('registrazione/show/registrazione/' . $registrazione->id);
        }
        try {
            (new SalvataggioRegistrazione($this->dataMapper))->elimina($registrazione);
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'warning');
            return $this->router->redirect('registrazione/show/registrazione/' . $registrazione->id);
        }
        $this->aggiungiMessaggio('Registrazione n. ' . $registrazione->numero . ' eliminata' . ($registrazione->causale->isIva() ? ': rielaborare la liquidazione IVA del periodo.' : '.'));
        return $this->router->redirect('registrazione/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    /**
     * @param array<int, array<string, mixed>> $datiIva righe IVA da mostrare (importi del documento)
     * @param array<int, array<string, mixed>> $datiRighe righe in partita doppia da mostrare
     */
    private function gestisci(Request $request, RegistrazioneContabile $registrazione, array $datiIva, array $datiRighe, string $titolo, ?FatturaElettronica $fattura = null): Response
    {
        $soggetto = $registrazione->soggetto;
        $chiaveProtocollo = SalvataggioRegistrazione::chiaveProtocollo($registrazione);
        $form = new RegistrazioneContabileForm($registrazione);
        $erroriRighe = [];
        $righeVuote = self::RIGHE_VUOTE;
        $form->handleRequest(clone $request);
        if ($form->isSubmitted()) {
            $this->verificaCsrf($request);
            $azione = (string) ($request->input['azione'] ?? 'salva');
            $testataValida = $form->isValid();
            [$righeIva, $datiIva, $ivaValide] = $this->leggiRigheIva($request, $registrazione, $erroriRighe);
            [$righe, $datiRighe, $righeValide] = $this->leggiRighe($request, $erroriRighe);
            if ($testataValida) {
                $registrazione = $form->resolveEntity();
            }
            if ($azione === 'aggiungi') {
                $righeVuote += 3;
            } elseif ($azione === 'proponi') {
                if ($testataValida && $ivaValide) {
                    try {
                        $datiRighe = array_map(fn(RigaContabile $riga) => self::datiRiga($riga), (new PropostaRighe($this->dataMapper))->proponi($this->piano, $registrazione, $righeIva));
                        $this->vars['messaggioProposta'] = count($datiRighe) > 0 ? 'Righe in partita doppia proposte: verificarle e salvare.' : 'Nessuna riga da proporre per la causale.';
                    } catch (\DomainException $errore) {
                        $erroriRighe[] = $errore->getMessage();
                    }
                } else {
                    $erroriRighe[] = 'Correggere la testata e le righe IVA per proporre le righe.';
                }
            } elseif ($testataValida && $ivaValide && $righeValide) {
                $errori = (new ControlloRegistrazione())->verifica($this->piano, $registrazione, $righeIva, $righe);
                foreach ($errori as $campo => $messaggio) {
                    if (in_array($campo, ['righe', 'righeIva'], true)) {
                        $erroriRighe[] = $messaggio;
                    } else {
                        $form->getFilterErrors()->{$campo . 'CustomMessage'} = $messaggio;
                    }
                }
                if (count($errori) === 0) {
                    try {
                        $registrazione = (new SalvataggioRegistrazione($this->dataMapper))->salva($registrazione, $righeIva, $righe, $chiaveProtocollo, $fattura);
                    } catch (\DomainException $errore) {
                        $errori['dataRegistrazione'] = $errore->getMessage();
                        $form->getFilterErrors()->dataRegistrazioneCustomMessage = $errore->getMessage();
                    }
                }
                if (count($errori) === 0) {
                    $this->aggiungiMessaggio('Registrazione n. ' . $registrazione->numero . ' salvata' . ($registrazione->protocolloIva === null ? '' : ' (protocollo IVA ' . $registrazione->protocolloIva . ')') . '.');
                    if ($azione === 'salvaNuova') {
                        return $this->router->redirect('registrazione/create/soggetto/' . $soggetto->id . '/anno/' . $registrazione->esercizioContabile->anno . '/causale/' . $registrazione->causale->value);
                    }
                    if ($fattura !== null) {
                        return $this->router->redirect('fattura-elettronica/index/soggetto/' . $soggetto->id . '/stato/D');
                    }
                    return $this->router->redirect('registrazione/show/registrazione/' . $registrazione->id);
                }
            }
        }
        $this->impostaVariabiliForm($form, $registrazione);
        $this->vars['registrazione'] = $registrazione;
        $this->vars['fattura'] = $fattura;
        $this->vars['datiIva'] = array_merge($datiIva, array_fill(0, $registrazione->causale->isIva() ? max(1, $righeVuote - (count($datiIva) > 0 ? 1 : 0)) : 0, []));
        $this->vars['datiRighe'] = array_merge($datiRighe, array_fill(0, $righeVuote + (count($datiRighe) === 0 ? 2 : 0), []));
        $this->vars['erroriRighe'] = $erroriRighe;
        $this->vars['registri'] = $registrazione->causale->isIva() ? iterator_to_array((new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, $registrazione->causale->getTipoRegistroIva()), false) : [];
        $this->vars['codiciIva'] = (new CodiceIvaModel($this->dataMapper))->getElenco($this->studio);
        $this->vars['qualifiche'] = QualificaOperazione::cases();
        $this->vars['attivitaSeparate'] = (new AttivitaSeparate($this->dataMapper))->getAttivita($soggetto, (int) $registrazione->dataRegistrazione->format('Y'));
        $this->vars['natureInversione'] = array_values(array_filter(NaturaIva::cases(), fn(NaturaIva $natura) => $natura->isInversioneContabile()));
        $this->vars['esigibilita'] = EsigibilitaIva::cases();
        $this->vars['ventilazione'] = (new RegimeIva($this->dataMapper))->isVentilazione($soggetto, (int) $registrazione->dataRegistrazione->format('Y'))
                || (count(array_filter($this->vars['datiIva'], fn(array $riga) => in_array($riga['rivendita'] ?? '', ['1', '2'], true))) > 0);
        $controparte = $registrazione->controparte;
        $this->vars['etichettaControparte'] = $controparte?->getEtichetta();
        $this->vars['avvisiProposta'] ??= [];
        $this->vars['messaggioProposta'] ??= null;
        $this->vars['sezioneAzienda'] = 'primaNota';
        $this->vars['pageTitle'] = $titolo;
        $anno = $registrazione->esercizioContabile->anno;
        $this->impostaBriciole($soggetto, $anno, $titolo, ['Prima nota' => '/registrazione/index/soggetto/' . $soggetto->id . '/anno/' . $anno]);
        return $this->render->generateView('registrazione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Righe IVA inviate: una RigaIvaForm per riga non vuota, con il segno
     * della causale applicato agli importi.
     *
     * @param string[] $errori
     * @return array{0: RigaIva[], 1: array, 2: bool}
     */
    private function leggiRigheIva(Request $request, RegistrazioneContabile $registrazione, array &$errori): array
    {
        $segno = $registrazione->causale->getSegno();
        $righe = $dati = [];
        $valide = true;
        $contoModel = new ContoModel($this->dataMapper);
        foreach (self::righeInviate($request, 'righeIva') as $indiceRiga => $input) {
            $numero = $indiceRiga;
            if (RigaIvaForm::isVuota($input)) {
                continue;
            }
            $form = new RigaIvaForm(new RigaIva($this->dataMapper), $this->dataMapper);
            $richiesta = clone $request;
            $richiesta->input = $input + ['submitted' => '1'];
            $form->handleRequest($richiesta);
            $conto = ctype_digit((string) ($input['conto'] ?? '')) ? $contoModel->getEntityById((int) $input['conto']) : null;
            $dati[] = $input + ['contoEtichetta' => $conto?->getEtichetta()];
            if ($form->isValid() === false) {
                $errori[] = 'Riga IVA ' . ($numero + 1) . ': indicare codice IVA e importi validi.';
                $valide = false;
                continue;
            }
            $riga = $form->resolveEntity();
            if (($riga->conto !== null) && ($riga->conto->pianoConti->id !== $this->piano->id)) {
                $errori[] = 'Riga IVA ' . ($numero + 1) . ': contropartita di un altro piano dei conti.';
                $valide = false;
                continue;
            }
            if (trim((string) ($input['imposta'] ?? '')) === '') {
                $riga->imposta = $riga->codiceIva->calcolaImposta($riga->imponibile);
            }
            if (trim((string) ($input['detraibilita'] ?? '')) === '') {
                $riga->detraibilita = $riga->codiceIva->detraibilita;
            }
            if ($registrazione->causale->getTipoRegistroIva() !== TipoRegistroIva::acquisti) {
                $riga->rivendita = false;
                $riga->altriProdotti = false;
                $riga->naturaInversione = null;
                $riga->aliquotaTeorica = null;
            } else {
                $riga->qualifica = null;
            }
            // acquisti promiscui tra attività separate: quote per numero di attività (solo nei registri senza attività)
            $riga->ripartizione = null;
            $attivita = (new AttivitaSeparate($this->dataMapper))->getAttivita($registrazione->soggetto, (int) $registrazione->dataRegistrazione->format('Y'));
            if (($registrazione->causale->getTipoRegistroIva() === TipoRegistroIva::acquisti) && (count($attivita) > 0) && ($registrazione->registroIva?->attivitaIva === null)) {
                $quote = [];
                foreach ((is_array($input['ripartizione'] ?? null) ? $input['ripartizione'] : []) as $numeroAttivita => $percentuale) {
                    $percentuale = str_replace(',', '.', trim((string) $percentuale));
                    if (($percentuale !== '') && is_numeric($percentuale) && ((float) $percentuale > 0)) {
                        $quote[(int) $numeroAttivita] = (float) $percentuale;
                    }
                }
                $errore = AttivitaSeparate::verificaRipartizione($quote, $attivita);
                if ($errore !== null) {
                    $errori[] = 'Riga IVA ' . ($indiceRiga + 1) . ': ' . lcfirst($errore);
                    $valide = false;
                    continue;
                }
                $riga->ripartizione = RigaIva::scriviRipartizione($quote);
            }
            $riga->imponibile = round($riga->imponibile * $segno, 2);
            $riga->imposta = round($riga->imposta * $segno, 2);
            $righe[] = $riga;
        }
        return [$righe, $dati, $valide];
    }

    /**
     * @param string[] $errori
     * @return array{0: RigaContabile[], 1: array, 2: bool}
     */
    private function leggiRighe(Request $request, array &$errori): array
    {
        $righe = $dati = [];
        $valide = true;
        $contoModel = new ContoModel($this->dataMapper);
        $controparteModel = new ControparteModel($this->dataMapper);
        foreach (self::righeInviate($request, 'righe') as $numero => $input) {
            if (RigaContabileForm::isVuota($input)) {
                continue;
            }
            $form = new RigaContabileForm(new RigaContabile($this->dataMapper), $this->dataMapper);
            $richiesta = clone $request;
            $richiesta->input = $input + ['submitted' => '1'];
            $form->handleRequest($richiesta);
            $conto = ctype_digit((string) ($input['conto'] ?? '')) ? $contoModel->getEntityById((int) $input['conto']) : null;
            $controparte = ctype_digit((string) ($input['controparte'] ?? '')) ? $controparteModel->getEntityById((int) $input['controparte']) : null;
            $dati[] = $input + ['contoEtichetta' => $conto?->getEtichetta(), 'controparteEtichetta' => $controparte?->getEtichetta()];
            if ($form->isValid() === false) {
                $errori[] = 'Riga ' . ($numero + 1) . ': indicare il sottoconto e un importo valido.';
                $valide = false;
                continue;
            }
            $riga = $form->resolveEntity();
            if (($riga->controparte !== null) && ($riga->controparte->soggetto->id !== $this->piano->soggetto->id)) {
                $errori[] = 'Riga ' . ($numero + 1) . ': controparte di un\'altra azienda.';
                $valide = false;
                continue;
            }
            $righe[] = $riga;
        }
        return [$righe, $dati, $valide];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function righeInviate(Request $request, string $nome): array
    {
        $righe = $request->input[$nome] ?? [];
        if (is_array($righe) === false) {
            return [];
        }
        return array_values(array_filter($righe, fn($riga) => is_array($riga)));
    }

    private static function datiRigaIva(RigaIva $riga, int $segno): array
    {
        return ['codiceIva' => (string) $riga->codiceIva->id, 'imponibile' => self::importo($riga->imponibile * $segno), 'imposta' => self::importo($riga->imposta * $segno),
            'detraibilita' => self::importo($riga->detraibilita), 'rivendita' => $riga->rivendita ? ($riga->altriProdotti ? '2' : '1') : '', 'naturaInversione' => $riga->naturaInversione?->value ?? '', 'qualifica' => $riga->qualifica?->value ?? '', 'aliquotaTeorica' => $riga->aliquotaTeorica === null ? '' : self::importo($riga->aliquotaTeorica), 'ripartizione' => array_map(fn(float $percentuale) => self::importo($percentuale), $riga->getRipartizione()), 'conto' => $riga->conto === null ? '' : (string) $riga->conto->id, 'contoEtichetta' => $riga->conto?->getEtichetta()];
    }

    private static function datiRiga(RigaContabile $riga): array
    {
        return ['conto' => (string) $riga->conto->id, 'contoEtichetta' => $riga->conto->getEtichetta(), 'controparte' => $riga->controparte === null ? '' : (string) $riga->controparte->id,
            'controparteEtichetta' => $riga->controparte?->getEtichetta(), 'dare' => $riga->dare > 0 ? self::importo($riga->dare) : '', 'avere' => $riga->avere > 0 ? self::importo($riga->avere) : '',
            'descrizione' => (string) $riga->descrizione];
    }

    private static function importo(float $valore): string
    {
        return number_format(round($valore, 2), 2, ',', '');
    }
}
