<?php

namespace Successioni\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Models\ContoCorrenteModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Anagrafica\Application\Models\RecapitoModel;
use Deleghe\Application\Models\IntermediarioModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\ImpegnoTrasmissione;
use Successioni\Application\Enumerations\ResidenzaEsteraDefunto;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Forms\DichiarazioneSuccessioneForm;
use Successioni\Application\Forms\PagamentoSuccessioneForm;
use Successioni\Application\Models\AllegatoSuccessioneModel;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;
use Successioni\Application\Models\DichiarazioneSuccessioneModel;
use Successioni\Application\Models\EstremoVolturaModel;
use Successioni\Application\Models\PassivitaSuccessioneModel;
use Successioni\Application\Models\RipartizionePassivitaModel;
use Successioni\Application\Services\ControlloDichiarazione;
use Successioni\Application\Services\CopiaDichiarazione;
use Successioni\Application\Services\DatiDichiarazione;
use Successioni\Application\Services\Liquidazione;
use Successioni\Application\Services\PianoVersamenti;
use Successioni\Application\Services\PropostaAttivo;
use Successioni\Application\Services\PropostaVolture;
use Successioni\Application\Services\Telematico\GeneratoreSuc;

/**
 * Dichiarazione di successione del defunto: frontespizio, beneficiari,
 * attivo e passivo, liquidazione con i versamenti F24 dei beneficiari,
 * allegati, file telematico e volture nell'Anagrafica.
 */
class DichiarazioneSuccessioneController extends SuccessioniController
{

    public const SCHEDE = ['generale', 'beneficiari', 'attivo', 'liquidazione', 'allegati', 'volture'];

    /**
     * Nuova dichiarazione in bozza con i dati proposti dall'Anagrafica:
     * stato civile, comune di residenza come luogo del decesso, intermediario
     * predefinito dello studio, immobili posseduti al decesso.
     */
    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        if (($soggetto->isPersonaFisica() === false) || ($soggetto->dataDecesso === null)) {
            $this->aggiungiMessaggio('Indicare prima la data di decesso nella scheda anagrafica del soggetto.', 'warning');
            return $this->vaiAlDefunto($soggetto);
        }
        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->studio = $this->studio;
        $dichiarazione->defunto = $soggetto;
        $dichiarazione->statoCivile = StatoCivileDefunto::daAnagrafica($soggetto->statoCivile);
        foreach ((new IndirizzoModel($this->dataMapper))->getVigentiAl($soggetto, $soggetto->dataDecesso) as $indirizzo) {
            if (($indirizzo->tipoIndirizzo === TipoIndirizzo::residenza) && ($indirizzo->comune !== null)) {
                $dichiarazione->comuneDecesso = $indirizzo->comune;
            }
        }
        $dichiarazione->intermediario = (new IntermediarioModel($this->dataMapper))->getPredefinito($this->studio);
        if ($dichiarazione->intermediario !== null) {
            $dichiarazione->impegno = ImpegnoTrasmissione::predispostaIntermediario;
            $dichiarazione->dataImpegno = new SismaDate();
        }
        $dichiarazione->tempisticaPagamento = $dichiarazione->isAutoliquidazione() ? TempisticaPagamento::nonContestuale : null;
        $dichiarazione->dataDichiarazione = new SismaDate();
        $this->dataMapper->save($dichiarazione);
        $proposta = new PropostaAttivo($this->dataMapper);
        $importati = $proposta->importaImmobili($dichiarazione);
        $this->aggiungiMessaggio(sprintf('Dichiarazione creata in bozza; immobili dall\'Anagrafica: %d.', $importati));
        foreach ($proposta->getAvvisi() as $avviso) {
            $this->aggiungiMessaggio($avviso, 'warning');
        }
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    public function show(DichiarazioneSuccessione $dichiarazione, string $scheda = 'generale'): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $scheda = in_array($scheda, self::SCHEDE, true) ? $scheda : 'generale';
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);
        $piano = new PianoVersamenti($this->dataMapper);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['dati'] = $dati;
        $this->vars['risultato'] = $risultato;
        $this->vars['errori'] = (new ControlloDichiarazione())->verifica($dati, $risultato);
        $this->vars['scheda'] = $scheda;
        $this->vars['schede'] = self::SCHEDE;
        if ($scheda === 'liquidazione') {
            $this->vars['rate'] = $piano->getRate($dichiarazione, $risultato);
            $this->vars['versamentiProposti'] = $piano->getVersamenti($dati, $risultato);
            $this->vars['versamentiRegistrati'] = $piano->getRegistrati($dichiarazione);
        }
        if ($scheda === 'volture') {
            $this->vars['volture'] = (new PropostaVolture($this->dataMapper))->proponi($dati);
        }
        $this->vars['pageTitle'] = $dichiarazione->getTitolo();
        $this->impostaBriciole($dichiarazione);
        return $this->render->generateView('dichiarazioneSuccessione/show', $this->vars);
    }

    public function update(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $form = new DichiarazioneSuccessioneForm($dichiarazione);
        if ($this->isInvioValido($request, $form)) {
            $dichiarazione = $form->resolveEntity();
            if ($this->isDelloStudio($dichiarazione->presentatore) && $this->isDelloStudio($dichiarazione->rappresentato)) {
                if (($dichiarazione->presentatore !== null) && ($dichiarazione->telefono === null) && ($dichiarazione->email === null)) {
                    $this->proponiRecapiti($dichiarazione);
                }
                $this->dataMapper->save($dichiarazione);
                $this->aggiungiMessaggio('Frontespizio salvato.');
            }
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->impostaVariabiliForm($form, $dichiarazione);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['tipiDichiarazione'] = TipoDichiarazioneSuccessione::cases();
        $this->vars['statiCivili'] = StatoCivileDefunto::cases();
        $this->vars['residenzeEstere'] = ResidenzaEsteraDefunto::cases();
        $this->vars['cariche'] = CaricaDichiarante::getGestite();
        $this->vars['impegni'] = ImpegnoTrasmissione::cases();
        $this->vars['intermediari'] = iterator_to_array((new IntermediarioModel($this->dataMapper))->getElenco($this->studio), false);
        $this->vars['pageTitle'] = 'Frontespizio';
        $this->impostaBriciole($dichiarazione, 'Frontespizio');
        return $this->render->generateView('dichiarazioneSuccessione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Telefono ed e-mail del dichiarante dai recapiti dell'Anagrafica.
     */
    private function proponiRecapiti(DichiarazioneSuccessione $dichiarazione): void
    {
        foreach ((new RecapitoModel($this->dataMapper))->getElenco($dichiarazione->presentatore) as $recapito) {
            if (in_array($recapito->tipoRecapito, [TipoRecapito::telefono, TipoRecapito::cellulare], true) && ($dichiarazione->telefono === null)) {
                $dichiarazione->telefono = mb_substr(preg_replace('/\D/', '', $recapito->valore), 0, 20);
            }
            if (($recapito->tipoRecapito === TipoRecapito::email) && ($dichiarazione->email === null)) {
                $dichiarazione->email = $recapito->valore;
            }
        }
    }

    public function pagamento(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'liquidazione')) !== null) {
            return $bloccata;
        }
        $form = new PagamentoSuccessioneForm($dichiarazione);
        if ($this->isInvioValido($request, $form)) {
            $dichiarazione = $form->resolveEntity();
            $this->dataMapper->save($dichiarazione);
            $this->aggiungiMessaggio('Dati del pagamento salvati: registra i versamenti F24 per aggiornarli in Deleghe.');
            return $this->vaiAllaDichiarazione($dichiarazione, 'liquidazione');
        }
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $this->impostaVariabiliForm($form, $dichiarazione);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['risultato'] = (new Liquidazione())->liquida($dati);
        $this->vars['conti'] = $this->getContiAmmessi($dichiarazione);
        $this->vars['tempistiche'] = TempisticaPagamento::cases();
        $this->vars['pageTitle'] = 'Pagamento delle imposte';
        $this->impostaBriciole($dichiarazione, 'Pagamento', 'liquidazione');
        return $this->render->generateView('dichiarazioneSuccessione/pagamento', $this->vars, $form->getResponseType());
    }

    /**
     * Conti del dichiarante, del rappresentato e dell'intermediario: le
     * specifiche ammettono solo questi intestatari.
     *
     * @return array<int, string>
     */
    private function getContiAmmessi(DichiarazioneSuccessione $dichiarazione): array
    {
        $conti = [];
        $contoModel = new ContoCorrenteModel($this->dataMapper);
        foreach (array_filter([$dichiarazione->presentatore, $dichiarazione->rappresentato, $dichiarazione->intermediario?->soggetto]) as $soggetto) {
            foreach ($contoModel->getAttivi($soggetto) as $conto) {
                $conti[$conto->id] = $soggetto->nominativo . ' - ' . $conto->getEtichetta();
            }
        }
        return $conti;
    }

    public function importaImmobili(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $proposta = new PropostaAttivo($this->dataMapper);
        $this->aggiungiMessaggio(sprintf('Immobili aggiunti dall\'Anagrafica: %d.', $proposta->importaImmobili($dichiarazione)));
        foreach ($proposta->getAvvisi() as $avviso) {
            $this->aggiungiMessaggio($avviso, 'warning');
        }
        return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
    }

    public function proponiDevoluzioni(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $proposta = new PropostaAttivo($this->dataMapper);
        $esito = $proposta->proponiDevoluzioni($dichiarazione);
        $this->aggiungiMessaggio(($esito ? 'Devoluzione per legge applicata a tutti i beni. ' : 'Devoluzione non proposta. ') . implode(' ', $proposta->getAvvisi()), $esito ? 'success' : 'warning');
        return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
    }

    /**
     * Registra i versamenti F24 dell'imposta di successione nell'archivio
     * di Deleghe F24 dei beneficiari.
     */
    public function versamenti(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);
        try {
            $differenze = (new PianoVersamenti($this->dataMapper))->registra($dati, $risultato);
            $this->aggiungiMessaggio('Versamenti F24 registrati nell\'archivio dei beneficiari in Deleghe F24.');
            foreach ($differenze as $differenza) {
                $this->aggiungiMessaggio($differenza, 'warning');
            }
        } catch (\InvalidArgumentException | \RuntimeException $errore) {
            $this->aggiungiMessaggio('Versamenti non registrati: ' . $errore->getMessage(), 'danger');
        }
        return $this->vaiAllaDichiarazione($dichiarazione, 'liquidazione');
    }

    /**
     * Segna la dichiarazione come presentata (data e protocollo) o la
     * riporta in bozza.
     */
    public function stato(Request $request, DichiarazioneSuccessione $dichiarazione, StatoDichiarazioneSuccessione $stato): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if ($stato === StatoDichiarazioneSuccessione::presentata) {
            $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
            $errori = (new ControlloDichiarazione())->verifica($dati, (new Liquidazione())->liquida($dati));
            if (count($errori) > 0) {
                $this->aggiungiMessaggio('Correggere prima la dichiarazione: ' . implode(' ', $errori), 'danger');
                return $this->vaiAllaDichiarazione($dichiarazione);
            }
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataPresentazione'] ?? ''));
            $dichiarazione->dataPresentazione = new SismaDate($data === false ? 'today' : $data->format('Y-m-d'));
            $protocollo = trim((string) ($request->input['protocollo'] ?? ''));
            $dichiarazione->protocollo = $protocollo === '' ? null : mb_substr($protocollo, 0, 50);
            foreach (['registrazioneVolume' => 5, 'registrazioneNumero' => 6] as $campo => $lunghezza) {
                $valore = preg_replace('/\D/', '', (string) ($request->input[$campo] ?? ''));
                $dichiarazione->$campo = $valore === '' ? null : substr($valore, 0, $lunghezza);
            }
        } else {
            $dichiarazione->dataPresentazione = null;
            $dichiarazione->protocollo = null;
        }
        $dichiarazione->stato = $stato;
        $this->dataMapper->save($dichiarazione);
        $this->aggiungiMessaggio('Dichiarazione ' . mb_strtolower($stato->getLabel()) . '.' . ($stato === StatoDichiarazioneSuccessione::presentata ? ' Puoi riportare le volture nell\'Anagrafica dalla scheda "Volture".' : ''));
        return $this->vaiAllaDichiarazione($dichiarazione, $stato === StatoDichiarazioneSuccessione::presentata ? 'volture' : 'generale');
    }

    /**
     * Nuova dichiarazione sostitutiva in bozza con i dati di quella
     * presentata.
     */
    public function sostitutiva(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if ($dichiarazione->stato !== StatoDichiarazioneSuccessione::presentata) {
            $this->aggiungiMessaggio('La sostitutiva si crea da una dichiarazione presentata.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $nuova = (new CopiaDichiarazione($this->dataMapper))->crea($dati, (new Liquidazione())->liquida($dati));
        $this->aggiungiMessaggio('Dichiarazione sostitutiva creata in bozza con le imposte della prima come già versate: verifica tipo ed estremi della prima nel frontespizio.');
        return $this->vaiAllaDichiarazione($nuova);
    }

    public function delete(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        if ((new DichiarazioneSuccessioneModel($this->dataMapper))->countBySostituita($dichiarazione) > 0) {
            $this->aggiungiMessaggio('La dichiarazione è sostituita da un\'altra: elimina prima la sostitutiva.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $defunto = $dichiarazione->defunto;
        $primaTransazione = $this->dataMapper->startTransaction();
        (new PianoVersamenti($this->dataMapper))->annulla($dichiarazione);
        foreach ((new AllegatoSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $allegato) {
            $contenuto = $allegato->contenutoAllegato;
            $this->dataMapper->delete($allegato);
            $this->dataMapper->delete($contenuto);
        }
        $ripartizioneModel = new RipartizionePassivitaModel($this->dataMapper);
        foreach ((new PassivitaSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $passivita) {
            foreach ($ripartizioneModel->getElenco($passivita) as $ripartizione) {
                $this->dataMapper->delete($ripartizione);
            }
            $this->dataMapper->delete($passivita);
        }
        $devoluzioneModel = new DevoluzioneSuccessioneModel($this->dataMapper);
        $estremoModel = new EstremoVolturaModel($this->dataMapper);
        $beni = iterator_to_array((new BeneSuccessioneModel($this->dataMapper))->getElenco($dichiarazione), false);
        usort($beni, fn($primo, $secondo) => ($primo->azienda === null) <=> ($secondo->azienda === null));
        foreach ($beni as $bene) {
            foreach ($devoluzioneModel->getElenco($bene) as $devoluzione) {
                $this->dataMapper->delete($devoluzione);
            }
            foreach ($estremoModel->getElenco($bene) as $estremo) {
                $this->dataMapper->delete($estremo);
            }
            $this->dataMapper->delete($bene);
        }
        foreach ((new BeneficiarioSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $beneficiario) {
            $this->dataMapper->delete($beneficiario);
        }
        $this->dataMapper->delete($dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Dichiarazione eliminata; rimossi anche i versamenti F24 non ancora in deleghe confermate.');
        return $this->vaiAlDefunto($defunto);
    }

    /**
     * File telematico SUC13, validato sullo schema dell'Agenzia.
     */
    public function scarica(DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);
        $errori = (new ControlloDichiarazione())->verifica($dati, $risultato);
        if (count($errori) > 0) {
            $this->aggiungiMessaggio('File non generato: ' . implode(' ', $errori), 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $xml = (new GeneratoreSuc())->genera($dati, $risultato);
        $erroriSchema = GeneratoreSuc::valida($xml);
        if (count($erroriSchema) > 0) {
            $this->aggiungiMessaggio('Il file non rispetta lo schema delle specifiche tecniche: ' . implode(' ', array_slice($erroriSchema, 0, 5)), 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->vars['contenuto'] = $xml;
        $this->vars['nomeFile'] = GeneratoreSuc::getNomeFile($dati);
        return $this->render->generateData('dichiarazioneSuccessione/scarica', $this->vars);
    }

    /**
     * Prospetto della liquidazione da consegnare agli eredi.
     */
    public function stampa(DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['dati'] = $dati;
        $this->vars['risultato'] = $risultato;
        $this->vars['rate'] = (new PianoVersamenti($this->dataMapper))->getRate($dichiarazione, $risultato);
        $this->vars['pageTitle'] = $dichiarazione->getTitolo();
        return $this->render->generateView('dichiarazioneSuccessione/stampa', $this->vars);
    }

    /**
     * Riporta nell'Anagrafica le volture proposte (dopo la presentazione).
     */
    public function volture(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if ($dichiarazione->stato !== StatoDichiarazioneSuccessione::presentata) {
            $this->aggiungiMessaggio('Le volture si riportano dopo la presentazione della dichiarazione.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione, 'volture');
        }
        try {
            $numero = (new PropostaVolture($this->dataMapper))->applica(new DatiDichiarazione($dichiarazione, $this->dataMapper));
            $this->aggiungiMessaggio(sprintf('Volture riportate nell\'Anagrafica: %d operazioni sui possessi.', $numero));
        } catch (\RuntimeException $errore) {
            $this->aggiungiMessaggio('Volture non riportate: ' . $errore->getMessage(), 'danger');
        }
        return $this->vaiAllaDichiarazione($dichiarazione, 'volture');
    }
}
