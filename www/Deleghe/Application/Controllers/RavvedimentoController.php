<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Models\ContoCorrenteModel;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\DichiarazioneRiferimento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Forms\RavvedimentoForm;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\DelegaModel;
use Deleghe\Application\Models\RegolaRavvedimentoModel;
use Deleghe\Application\Services\CalcoloRavvedimento;
use Deleghe\Application\Services\RavvedimentoService;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ravvedimento operoso dei versamenti omessi o tardivi: calcolo della
 * sanzione ridotta e degli interessi e generazione delle righe nella delega
 * (data di versamento = scadenza della delega).
 */
class RavvedimentoController extends DelegheController
{

    private const SEZIONI = [SezioneF24::erario, SezioneF24::regioni, SezioneF24::tributiLocali];

    /**
     * Con $versamento il ravvedimento regolarizza un versamento omesso
     * dell'archivio: i dati del tributo sono ripresi da lì.
     */
    public function create(Request $request, Delega $delega, SezioneF24 $sezione = SezioneF24::erario, ?Versamento $versamento = null): Response
    {
        $this->autorizza($delega);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        if ((in_array($sezione, self::SEZIONI, true) === false) || ($delega->modello->ammetteSezione($sezione) === false)) {
            $this->aggiungiMessaggio('Ravvedimento non disponibile per la sezione ' . $sezione->getLabel() . '.', 'warning');
            return $this->vaiAllaDelega($delega);
        }
        $ravvedimento = new Ravvedimento($this->dataMapper);
        $ravvedimento->delega = $delega;
        $ravvedimento->sezione = $sezione;
        if (($versamento !== null) && $versamento->isDaVersare() && ($versamento->soggetto->id === $delega->soggetto->id) && ($versamento->sezione === $sezione)) {
            $ravvedimento->versamentoOmesso = $versamento;
            foreach (['codiceTributo', 'codiceEnte', 'rateazione', 'annoRiferimento', 'acconto', 'saldo', 'numeroImmobili'] as $campo) {
                $ravvedimento->$campo = $versamento->$campo;
            }
            $ravvedimento->importoTributo = $versamento->importoDebito;
            $ravvedimento->scadenzaOriginale = $versamento->scadenza;
        }
        return $this->gestisci($request, $ravvedimento, 'Ravvedimento operoso: ' . $sezione->getLabel());
    }

    /**
     * Ravvedimento di un versamento scaduto dell'archivio: con la data di
     * versamento scelta apre la delega in bozza di quella data (creata se
     * manca) e il calcolo con i dati del versamento.
     */
    public function daVersamento(Request $request, Versamento $versamento): Response
    {
        $this->autorizzaSoggetto($versamento->soggetto);
        $this->verificaCsrf($request);
        $data = self::leggiData((string) ($request->input['scadenza'] ?? ''));
        if (($versamento->isDaVersare() === false) || (in_array($versamento->sezione, self::SEZIONI, true) === false) || ($versamento->importoDebito <= 0)) {
            $this->aggiungiMessaggio('Il ravvedimento si calcola per i versamenti a debito da versare delle sezioni Erario, Regioni e IMU.', 'warning');
            return $this->vaiAlContribuente($versamento->soggetto);
        }
        if (($data === null) || ($data->format('Y-m-d') <= $versamento->scadenza->format('Y-m-d'))) {
            $this->aggiungiMessaggio('Indicare una data di versamento successiva alla scadenza originale.', 'danger');
            return $this->vaiAlContribuente($versamento->soggetto);
        }
        $delega = null;
        foreach ((new DelegaModel($this->dataMapper))->getPerScadenza($versamento->soggetto, $data) as $esistente) {
            if ($esistente->isModificabile() && $esistente->modello->ammetteSezione($versamento->sezione)) {
                $delega = $esistente;
                break;
            }
        }
        if ($delega === null) {
            $delega = new Delega($this->dataMapper);
            $delega->studio = $versamento->studio;
            $delega->soggetto = $versamento->soggetto;
            $delega->scadenza = $data;
            $delega->contoAddebito = (new ContoCorrenteModel($this->dataMapper))->getPredefinito($versamento->soggetto);
            $this->dataMapper->save($delega);
        }
        return $this->router->redirect('ravvedimento/create/delega/' . $delega->id . '/sezione/' . $versamento->sezione->value . '/versamento/' . $versamento->id);
    }

    public function update(Request $request, Ravvedimento $ravvedimento): Response
    {
        $this->autorizza($ravvedimento->delega);
        if (($bloccata = $this->verificaModificabile($ravvedimento->delega)) !== null) {
            return $bloccata;
        }
        return $this->gestisci($request, $ravvedimento, 'Ravvedimento operoso: ' . $ravvedimento->sezione->getLabel());
    }

    private function gestisci(Request $request, Ravvedimento $ravvedimento, string $titolo): Response
    {
        $form = new RavvedimentoForm($ravvedimento);
        if ($this->isInvioValido($request, $form)) {
            $ravvedimento = $form->resolveEntity();
            $this->applicaRegola($ravvedimento, isset($request->input['codiceSanzione']) && ($request->input['codiceSanzione'] !== ''), isset($request->input['codiceInteressi']) && ($request->input['codiceInteressi'] !== ''));
            try {
                (new RavvedimentoService($this->dataMapper))->registra($ravvedimento);
                $this->aggiungiMessaggio(sprintf('Ravvedimento calcolato: %d giorni di ritardo, sanzione € %s, interessi € %s.', $ravvedimento->giorniRitardo,
                                number_format($ravvedimento->sanzione, 2, ',', '.'), number_format($ravvedimento->interessi, 2, ',', '.')));
                return $this->vaiAllaDelega($ravvedimento->delega);
            } catch (DelegaNonModificabileException | \InvalidArgumentException | \UnexpectedValueException $errore) {
                $this->vars['erroreCalcolo'] = $errore->getMessage();
            }
        }
        $this->impostaVariabiliForm($form, $ravvedimento);
        $delega = $ravvedimento->delega;
        $this->vars['delega'] = $delega;
        $this->vars['sezione'] = $ravvedimento->sezione;
        $dati = $this->vars['formEntity'];
        $this->vars['etichette'] = [];
        foreach (['codiceTributo', 'codiceSanzione', 'codiceInteressi'] as $campo) {
            $this->vars['etichette'][$campo] = isset($dati->$campo) ? $dati->$campo?->getEtichetta() : null;
        }
        $this->vars['codiciConRegola'] = $this->descriviRegole();
        $this->vars['decorrenzaRiforma'] = CalcoloRavvedimento::DECORRENZA_RIFORMA;
        $this->vars['circostanze'] = CircostanzaRavvedimento::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($delega, 'Ravvedimento');
        return $this->render->generateView('ravvedimento/form', $this->vars, $form->getResponseType());
    }

    /**
     * Completa i dati non indicati con la regola del tributo: codici di
     * sanzione e interessi (null = sommati al tributo) e termine della
     * dichiarazione per la riduzione a 1/8. Senza regola e senza termine
     * vale un anno dalla violazione.
     */
    private function applicaRegola(Ravvedimento $ravvedimento, bool $sanzioneIndicata, bool $interessiIndicati): void
    {
        $regola = (new RegolaRavvedimentoModel($this->dataMapper))->getRegola($ravvedimento->codiceTributo);
        $codici = new CodiceTributoModel($this->dataMapper);
        $sezione = $ravvedimento->codiceTributo->sezione;
        if (($regola !== null) && ($sanzioneIndicata === false)) {
            $ravvedimento->codiceSanzione = $regola->codiceSanzione === null ? null : $codici->getByCodice($sezione, $regola->codiceSanzione);
        }
        if (($regola !== null) && ($interessiIndicati === false)) {
            $ravvedimento->codiceInteressi = $regola->codiceInteressi === null ? null : $codici->getByCodice($sezione, $regola->codiceInteressi);
        }
        if ($ravvedimento->termineDichiarazione === null) {
            $ravvedimento->termineDichiarazione = ($regola?->dichiarazione ?? DichiarazioneRiferimento::nessuna)->getTermine($ravvedimento->scadenzaOriginale);
        }
    }

    /**
     * Codici con la regola di ravvedimento, per l'aiuto del form.
     */
    private function descriviRegole(): string
    {
        $codici = [];
        foreach ((new RegolaRavvedimentoModel($this->dataMapper))->getEntityCollection(null, ['codice' => 'ASC']) as $regola) {
            $codici[] = $regola->codice;
        }
        return implode(', ', $codici);
    }

    public function delete(Request $request, Ravvedimento $ravvedimento): Response
    {
        $delega = $ravvedimento->delega;
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        (new RavvedimentoService($this->dataMapper))->elimina($ravvedimento);
        $this->aggiungiMessaggio('Ravvedimento eliminato con le sue righe.');
        return $this->vaiAllaDelega($delega);
    }
}
