<?php

namespace Successioni\Application\Controllers;

use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PossessoModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\EstremoVoltura;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\DirittoAbitazione;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\RiduzioneSuccessioniPrecedenti;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDocumentoTitolo;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Enumerations\TipoUnita;
use Successioni\Application\Forms\BeneSuccessioneForm;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;
use Successioni\Application\Models\EstremoVolturaModel;
use Successioni\Application\Models\PassivitaSuccessioneModel;
use Successioni\Application\Models\UfficioRegistrazioneModel;
use Successioni\Application\Models\UfficioTrascrizioneModel;
use Successioni\Application\Services\ValoreCatastale;

/**
 * Beni dell'attivo ereditario e loro devoluzione ai beneficiari.
 */
class BeneSuccessioneController extends SuccessioniController
{

    public const RIGHE_AGGIUNTIVE = 2;

    public function create(Request $request, DichiarazioneSuccessione $dichiarazione, QuadroBene $quadro): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $dichiarazione;
        $bene->quadro = $quadro;
        $bene->ordine = (new BeneSuccessioneModel($this->dataMapper))->getProssimoOrdine($dichiarazione, $quadro);
        return $this->gestisci($request, $bene, 'Nuovo bene');
    }

    public function update(Request $request, BeneSuccessione $bene): Response
    {
        $this->autorizzaDichiarazione($bene->dichiarazioneSuccessione);
        if (($bloccata = $this->verificaModificabile($bene->dichiarazioneSuccessione, 'attivo')) !== null) {
            return $bloccata;
        }
        return $this->gestisci($request, $bene, $bene->getDescrizioneBreve());
    }

    private function gestisci(Request $request, BeneSuccessione $bene, string $titolo): Response
    {
        $dichiarazione = $bene->dichiarazioneSuccessione;
        $form = new BeneSuccessioneForm($bene);
        $immobili = $bene->quadro->isImmobile() ? $this->getImmobiliDefunto($dichiarazione, $bene->quadro) : [];
        if ($this->isInvioValido($request, $form)) {
            $bene = $form->resolveEntity();
            if ((($bene->immobile === null) || isset($immobili[$bene->immobile->id])) && (($bene->azienda === null) || ($bene->azienda->dichiarazioneSuccessione->id === $dichiarazione->id))) {
                if (isset($bene->id) && ($bene->discordanzaIntestatario === false)) {
                    foreach ((new EstremoVolturaModel($this->dataMapper))->getElenco($bene) as $estremo) {
                        $this->dataMapper->delete($estremo);
                    }
                }
                $this->dataMapper->save($bene);
                $this->aggiungiMessaggio('Bene salvato: verifica la devoluzione ai beneficiari.');
            }
            return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
        }
        $this->impostaVariabiliForm($form, $bene);
        $this->vars['bene'] = $bene;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['immobili'] = array_map(fn($immobile) => $immobile->getIdentificativo() . ' - ' . $immobile->getUbicazione(), $immobili);
        $this->vars['valoreProposto'] = $this->getValoreProposto($bene);
        $this->vars['diritti'] = $bene->quadro->getDirittiDefunto();
        $this->vars['tipologieTerreno'] = TipologiaTerreno::cases();
        $this->vars['dirittiAbitazione'] = DirittoAbitazione::cases();
        $this->vars['tipiTitolo'] = TipoTitolo::cases();
        $this->vars['tipiDocumento'] = TipoDocumentoTitolo::cases();
        $this->vars['tipiCespite'] = TipoCespite::cases();
        $this->vars['tipiUnita'] = TipoUnita::cases();
        $this->vars['aziende'] = [];
        foreach ((new BeneSuccessioneModel($this->dataMapper))->getByDichiarazioneSuccessioneAndQuadro($dichiarazione, QuadroBene::aziende) as $azienda) {
            $this->vars['aziende'][$azienda->id] = $azienda->descrizione;
        }
        $this->vars['pageTitle'] = $bene->quadro->getLabel();
        $this->impostaBriciole($dichiarazione, $titolo, 'attivo');
        return $this->render->generateView('beneSuccessione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Immobili posseduti dal defunto (in qualunque periodo) del catasto del
     * quadro.
     *
     * @return array<int, \Anagrafica\Application\Entities\Immobile>
     */
    private function getImmobiliDefunto(DichiarazioneSuccessione $dichiarazione, QuadroBene $quadro): array
    {
        $tipo = $quadro->isTerreno() ? TipoCatasto::terreni : TipoCatasto::fabbricati;
        $immobili = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($dichiarazione->defunto) as $possesso) {
            if (($possesso->immobile->tipoCatasto === $tipo) && ($possesso->immobile->isTavolare() === $quadro->isTavolare())) {
                $immobili[$possesso->immobile->id] = $possesso->immobile;
            }
        }
        return $immobili;
    }

    private function getValoreProposto(BeneSuccessione $bene): ?int
    {
        if ($bene->immobile === null) {
            return null;
        }
        $catasto = (new DatiCatastaliModel($this->dataMapper))->getVigenteAl($bene->immobile, $bene->dichiarazioneSuccessione->getDataApertura());
        return $catasto === null ? null : ValoreCatastale::getValoreQuota($catasto, $bene->quadro->isTerreno(), $bene->getQuota());
    }

    public function delete(Request $request, BeneSuccessione $bene): Response
    {
        $dichiarazione = $bene->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        if (((new PassivitaSuccessioneModel($this->dataMapper))->countByBeneSuccessione($bene) > 0) || ((new BeneSuccessioneModel($this->dataMapper))->countByAzienda($bene) > 0)) {
            $this->aggiungiMessaggio('Il bene è collegato a una passività (mutuo o debito) o a immobili aziendali: modifica prima quelli.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new DevoluzioneSuccessioneModel($this->dataMapper))->getElenco($bene) as $devoluzione) {
            $this->dataMapper->delete($devoluzione);
        }
        foreach ((new EstremoVolturaModel($this->dataMapper))->getElenco($bene) as $estremo) {
            $this->dataMapper->delete($estremo);
        }
        $this->dataMapper->delete($bene);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Bene eliminato.');
        return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
    }

    /**
     * Sezione devoluzione del bene: righe con beneficiario, quota, diritto,
     * valore della quota (calcolato se il diritto coincide con quello del
     * defunto), agevolazione e riduzione.
     */
    public function devoluzioni(Request $request, BeneSuccessione $bene): Response
    {
        $dichiarazione = $bene->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $beneficiari = [];
        foreach ((new BeneficiarioSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $beneficiario) {
            if ($beneficiario->isDestinatario()) {
                $beneficiari[$beneficiario->id] = $beneficiario;
            }
        }
        $devoluzioneModel = new DevoluzioneSuccessioneModel($this->dataMapper);
        $righe = [];
        $errori = [];
        if (isset($request->input['submitted'])) {
            $this->verificaCsrf($request);
            [$righe, $errori] = $this->leggiRighe($request, $bene, $beneficiari);
            if (count($errori) === 0) {
                $primaTransazione = $this->dataMapper->startTransaction();
                foreach ($devoluzioneModel->getElenco($bene) as $esistente) {
                    $this->dataMapper->delete($esistente);
                }
                foreach ($righe as $riga) {
                    $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
                    $devoluzione->beneSuccessione = $bene;
                    $devoluzione->beneficiarioSuccessione = $beneficiari[$riga['beneficiario']];
                    $devoluzione->quotaNumeratore = $riga['numeratore'];
                    $devoluzione->quotaDenominatore = $riga['denominatore'];
                    $devoluzione->codiceDiritto = CodiceDiritto::from($riga['diritto']);
                    $devoluzione->valoreQuota = $riga['valore'];
                    $devoluzione->agevolazione = Agevolazione::tryFrom($riga['agevolazione']);
                    $devoluzione->riduzione = RiduzioneSuccessioniPrecedenti::tryFrom($riga['riduzione']);
                    $this->dataMapper->save($devoluzione);
                }
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Devoluzione salvata.');
                return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
            }
        } else {
            foreach ($devoluzioneModel->getElenco($bene) as $devoluzione) {
                $righe[] = ['beneficiario' => $devoluzione->beneficiarioSuccessione->id, 'numeratore' => $devoluzione->quotaNumeratore, 'denominatore' => $devoluzione->quotaDenominatore,
                    'diritto' => $devoluzione->codiceDiritto->value, 'valore' => $devoluzione->valoreQuota, 'agevolazione' => $devoluzione->agevolazione?->value ?? '', 'riduzione' => $devoluzione->riduzione?->value ?? ''];
            }
        }
        for ($indice = 0; $indice < self::RIGHE_AGGIUNTIVE; $indice++) {
            $righe[] = ['beneficiario' => '', 'numeratore' => '', 'denominatore' => '', 'diritto' => $bene->codiceDiritto->value, 'valore' => null, 'agevolazione' => '', 'riduzione' => ''];
        }
        $this->vars['bene'] = $bene;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['beneficiari'] = $beneficiari;
        $this->vars['righe'] = $righe;
        $this->vars['erroriRighe'] = $errori;
        $this->vars['diritti'] = $bene->quadro->getDirittiDevoluzione();
        $this->vars['agevolazioni'] = $bene->quadro->getAgevolazioni();
        $this->vars['riduzioni'] = RiduzioneSuccessioniPrecedenti::cases();
        $this->vars['pageTitle'] = 'Devoluzione · ' . $bene->getDescrizioneBreve();
        $this->impostaBriciole($dichiarazione, 'Devoluzione', 'attivo');
        return $this->render->generateView('beneSuccessione/devoluzioni', $this->vars);
    }

    /**
     * Estremi degli atti di provenienza per la voltura di un immobile con i
     * dati dell'intestatario discordanti (quadro EI, sezione II): righe con
     * trascrizioni (ufficio, registro particolare, anno) e registrazioni
     * (ufficio, anno, volume, numero).
     */
    public function estremi(Request $request, BeneSuccessione $bene): Response
    {
        $dichiarazione = $bene->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        if (in_array($bene->quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true) === false) {
            $this->aggiungiMessaggio('Gli estremi di provenienza (quadro EI) si indicano solo per terreni e fabbricati del catasto ordinario.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
        }
        $estremoModel = new EstremoVolturaModel($this->dataMapper);
        $errori = [];
        $righe = [];
        if (isset($request->input['submitted'])) {
            $this->verificaCsrf($request);
            $trascrizioneModel = new UfficioTrascrizioneModel($this->dataMapper);
            $registrazioneModel = new UfficioRegistrazioneModel($this->dataMapper);
            foreach ((array) ($request->input['righe'] ?? []) as $indice => $dato) {
                $riga = ['tipo' => (string) ($dato['tipo'] ?? ''), 'ufficio' => strtoupper(trim((string) ($dato['ufficio'] ?? ''))), 'anno' => (int) ($dato['anno'] ?? 0),
                    'volume' => trim((string) ($dato['volume'] ?? '')), 'numero' => trim((string) ($dato['numero'] ?? ''))];
                if (($riga['ufficio'] === '') && ($riga['numero'] === '')) {
                    continue;
                }
                $riga['entitaUfficio'] = $riga['tipo'] === EstremoVoltura::TRASCRIZIONE ? ($trascrizioneModel->getByCodice($riga['ufficio'])[0] ?? null) : $registrazioneModel->getByCodiceUfficio($riga['ufficio']);
                if ($riga['entitaUfficio'] === null) {
                    $errori[$indice] = 'Codice ufficio non presente nelle tabelle delle specifiche.';
                } elseif (($riga['anno'] < 1800) || ($riga['anno'] > (int) date('Y'))) {
                    $errori[$indice] = 'Anno non valido.';
                } elseif ((preg_match('/^\d{1,6}$/', $riga['numero']) !== 1) || (($riga['tipo'] === EstremoVoltura::REGISTRAZIONE) && (preg_match('/^\d{1,5}$/', $riga['volume']) !== 1))) {
                    $errori[$indice] = $riga['tipo'] === EstremoVoltura::TRASCRIZIONE ? 'Registro particolare: fino a 6 cifre.' : 'Volume (fino a 5 cifre) e numero (fino a 6 cifre).';
                }
                $righe[$indice] = $riga;
            }
            if (count($errori) === 0) {
                $primaTransazione = $this->dataMapper->startTransaction();
                foreach ($estremoModel->getElenco($bene) as $esistente) {
                    $this->dataMapper->delete($esistente);
                }
                foreach ($righe as $riga) {
                    $estremo = new EstremoVoltura($this->dataMapper);
                    $estremo->beneSuccessione = $bene;
                    $estremo->tipo = $riga['tipo'];
                    $estremo->anno = $riga['anno'];
                    $estremo->numero = $riga['numero'];
                    if ($riga['tipo'] === EstremoVoltura::TRASCRIZIONE) {
                        $estremo->ufficioTrascrizione = $riga['entitaUfficio'];
                    } else {
                        $estremo->ufficioRegistrazione = $riga['entitaUfficio'];
                        $estremo->volume = $riga['volume'];
                    }
                    $this->dataMapper->save($estremo);
                }
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Estremi degli atti di provenienza salvati.');
                return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
            }
        } else {
            foreach ($estremoModel->getElenco($bene) as $estremo) {
                $righe[] = ['tipo' => $estremo->tipo, 'ufficio' => $estremo->isTrascrizione() ? $estremo->ufficioTrascrizione?->codice : $estremo->ufficioRegistrazione?->codice,
                    'anno' => $estremo->anno, 'volume' => $estremo->volume, 'numero' => $estremo->numero];
            }
        }
        for ($indice = 0; $indice < 3; $indice++) {
            $righe[] = ['tipo' => EstremoVoltura::TRASCRIZIONE, 'ufficio' => '', 'anno' => '', 'volume' => '', 'numero' => ''];
        }
        $this->vars['bene'] = $bene;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['righe'] = $righe;
        $this->vars['erroriRighe'] = $errori;
        $this->vars['pageTitle'] = 'Provenienza · ' . $bene->getDescrizioneBreve();
        $this->impostaBriciole($dichiarazione, 'Provenienza', 'attivo');
        return $this->render->generateView('beneSuccessione/estremi', $this->vars);
    }

    /**
     * @param array<int, \Successioni\Application\Entities\BeneficiarioSuccessione> $beneficiari
     * @return array{0: array, 1: array<int|string, string>} righe compilate ed errori per indice di riga
     */
    private function leggiRighe(Request $request, BeneSuccessione $bene, array $beneficiari): array
    {
        $righe = [];
        $errori = [];
        foreach ((array) ($request->input['righe'] ?? []) as $indice => $dato) {
            $riga = [
                'beneficiario' => (int) ($dato['beneficiario'] ?? 0),
                'numeratore' => (int) ($dato['numeratore'] ?? 0),
                'denominatore' => (int) ($dato['denominatore'] ?? 0),
                'diritto' => (string) ($dato['diritto'] ?? ''),
                'valore' => trim((string) ($dato['valore'] ?? '')),
                'agevolazione' => (string) ($dato['agevolazione'] ?? ''),
                'riduzione' => (string) ($dato['riduzione'] ?? ''),
            ];
            if (($riga['beneficiario'] === 0) && ($riga['numeratore'] === 0)) {
                continue;
            }
            $diritto = CodiceDiritto::tryFrom($riga['diritto']);
            $agevolazione = Agevolazione::tryFrom($riga['agevolazione']);
            $riga['valore'] = $riga['valore'] === '' ? null : (float) str_replace(',', '.', str_replace('.', '', $riga['valore']));
            if (isset($beneficiari[$riga['beneficiario']]) === false) {
                $errori[$indice] = 'Scegliere il beneficiario.';
            } elseif (($riga['numeratore'] <= 0) || ($riga['denominatore'] <= 0) || ($riga['numeratore'] > $riga['denominatore'])) {
                $errori[$indice] = 'Quota non valida.';
            } elseif (($diritto === null) || (in_array($diritto, $bene->quadro->getDirittiDevoluzione(), true) === false)) {
                $errori[$indice] = 'Diritto non ammesso.';
            } elseif (($agevolazione !== null) && (in_array($agevolazione, $bene->quadro->getAgevolazioni(), true) === false)) {
                $errori[$indice] = 'Agevolazione non ammessa nel quadro.';
            } elseif (($riga['riduzione'] !== '') && ($bene->valorePrecedente === null)) {
                $errori[$indice] = 'La riduzione per successioni precedenti richiede il valore nella successione precedente (dati del bene).';
            } elseif ($riga['valore'] === null) {
                if ($diritto !== $bene->codiceDiritto) {
                    $errori[$indice] = 'Diritto diverso da quello del defunto: indicare il valore della quota (coefficienti del TUS).';
                } else {
                    $riga['valore'] = round($bene->getValoreTotale() * $riga['numeratore'] / $riga['denominatore'], 2);
                }
            }
            $righe[$indice] = $riga;
        }
        if ((count($righe) === 0) && (count($errori) === 0)) {
            $errori['generale'] = 'Indicare almeno un beneficiario.';
        }
        return [$righe, $errori];
    }
}
