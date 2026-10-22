<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Models\IntermediarioModel;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Enumerations\TipologiaDichiarazione;
use Imu\Application\Forms\DichiarazioneImuForm;
use Imu\Application\Models\RigoDichiarazioneImuModel;
use Imu\Application\Services\PropostaDichiarazione;
use Imu\Application\Services\Telematico\GeneratoreDichiarazioneImu;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazioni IMU del contribuente (una per anno e comune): proposta dai
 * dati, frontespizio, quadri, stato di presentazione, file telematico e
 * stampa di controllo.
 */
class DichiarazioneImuController extends ImuController
{

    /**
     * Propone (o ripropone, se in bozza) le dichiarazioni dell'anno.
     */
    public function proponi(Request $request, Soggetto $soggetto, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $anno = self::normalizzaAnno($anno);
        $proposte = (new PropostaDichiarazione($this->dataMapper))->proponi($soggetto, $anno);
        $this->aggiungiMessaggio(count($proposte) === 0
                ? 'Nessuna variazione da dichiarare per il ' . $anno . ' (le dichiarazioni già presentate non vengono modificate).'
                : sprintf('Dichiarazioni IMU %d proposte: %s.', $anno, implode(', ', array_map(fn(DichiarazioneImu $dichiarazione) => $dichiarazione->denominazioneComune, $proposte))));
        return $this->vaiAlContribuente($soggetto, $anno);
    }

    public function show(DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $generatore = new GeneratoreDichiarazioneImu($this->dataMapper);
        $righi = iterator_to_array((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione));
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['righi'] = $righi;
        $this->vars['utilizzi'] = array_map(fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'B' ? $generatore->getUtilizzo($dichiarazione, $rigo) : null, $righi);
        $this->vars['errori'] = $generatore->getErrori($dichiarazione);
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['pageTitle'] = 'Dichiarazione IMU ' . $dichiarazione->anno . ' · ' . $dichiarazione->denominazioneComune;
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('dichiarazioneImu/show', $this->vars);
    }

    public function update(Request $request, DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $form = new DichiarazioneImuForm($dichiarazione);
        if ($this->isInvioValido($request, $form)) {
            $dichiarazione = $form->resolveEntity();
            if (($dichiarazione->dichiarante !== null) && ($dichiarazione->dichiarante->studio->id !== $this->studio->id)) {
                return $this->vaiAllaDichiarazione($dichiarazione);
            }
            $this->dataMapper->save($dichiarazione);
            $this->aggiungiMessaggio('Dichiarazione salvata.');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->impostaVariabiliForm($form, $dichiarazione);
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['tipologie'] = TipologiaDichiarazione::cases();
        $this->vars['codiciCarica'] = CodiceCarica::cases();
        $this->vars['intermediari'] = iterator_to_array((new IntermediarioModel($this->dataMapper))->getElenco($this->studio));
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['pageTitle'] = 'Frontespizio · ' . $dichiarazione->denominazioneComune . ' ' . $dichiarazione->anno;
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('dichiarazioneImu/form', $this->vars, $form->getResponseType());
    }

    /**
     * Ricalcola il quadro C (ENC) dal calcolo e dai versamenti dell'anno.
     */
    public function quadroC(Request $request, DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if ((($bloccata = $this->verificaModificabile($dichiarazione)) === null) && $dichiarazione->isEnc()) {
            (new PropostaDichiarazione($this->dataMapper))->determinaQuadroC($dichiarazione);
            $this->dataMapper->save($dichiarazione);
            $this->aggiungiMessaggio('Quadro C aggiornato dal calcolo e dai versamenti dell\'anno.');
        }
        return $bloccata ?? $this->vaiAllaDichiarazione($dichiarazione);
    }

    /**
     * Segna la dichiarazione come presentata (data e protocollo) o la
     * riporta in bozza.
     */
    public function stato(Request $request, DichiarazioneImu $dichiarazione, StatoDichiarazione $stato): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if ($stato === StatoDichiarazione::presentata) {
            $errori = (new GeneratoreDichiarazioneImu($this->dataMapper))->getErrori($dichiarazione);
            if (count($errori) > 0) {
                $this->aggiungiMessaggio('Correggere prima la dichiarazione: ' . implode(' ', $errori), 'danger');
                return $this->vaiAllaDichiarazione($dichiarazione);
            }
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['dataPresentazione'] ?? ''));
            $dichiarazione->dataPresentazione = new SismaDate($data === false ? 'today' : $data->format('Y-m-d'));
            $protocollo = trim((string) ($request->input['protocollo'] ?? ''));
            $dichiarazione->protocollo = $protocollo === '' ? null : mb_substr($protocollo, 0, 50);
        } else {
            $dichiarazione->dataPresentazione = null;
            $dichiarazione->protocollo = null;
        }
        $dichiarazione->stato = $stato;
        $this->dataMapper->save($dichiarazione);
        $this->aggiungiMessaggio('Dichiarazione ' . mb_strtolower($stato->getLabel()) . '.');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    /**
     * Nuova dichiarazione sostitutiva con i dati di quella presentata.
     */
    public function sostitutiva(Request $request, DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        $primaTransazione = $this->dataMapper->startTransaction();
        $nuova = new DichiarazioneImu($this->dataMapper);
        foreach (['studio', 'soggetto', 'anno', 'codiceComune', 'denominazioneComune', 'modello', 'dichiarante', 'codiceCarica', 'intermediario', 'annotazioni',
    'impostaDovuta', 'eccedenzaPrecedente', 'eccedenzaCompensata', 'rateVersate', 'creditoRimborso', 'creditoCompensazione'] as $proprieta) {
            $nuova->$proprieta = $dichiarazione->$proprieta;
        }
        $nuova->tipologia = TipologiaDichiarazione::sostitutiva;
        $nuova->stato = StatoDichiarazione::bozza;
        $nuova->dataDichiarazione = new SismaDate();
        $nuova->dataImpegno = $nuova->intermediario === null ? null : new SismaDate();
        $this->dataMapper->save($nuova);
        foreach ((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione) as $rigo) {
            $copia = new RigoDichiarazioneImu($this->dataMapper);
            foreach (['immobile', 'quadro', 'numeroOrdine', 'progressivoImmobile', 'caratteristica', 'valore', 'percentualePossesso', 'detrazione', 'equiparazione', 'riduzione', 'esenzione',
        'acquisto', 'cessione', 'altro', 'altroDescrizione', 'dataVariazione', 'inizioTermine', 'agenziaEntrate', 'estremiTitolo', 'nonUtilizzabileTipo', 'nonUtilizzabileAutorita', 'nonUtilizzabileData', 'motivo'] as $proprieta) {
                $copia->$proprieta = $rigo->$proprieta;
            }
            $copia->dichiarazioneImu = $nuova;
            $this->dataMapper->save($copia);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Dichiarazione sostitutiva creata in bozza: correggila e trasmettila integralmente.');
        return $this->vaiAllaDichiarazione($nuova);
    }

    public function delete(Request $request, DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione)) !== null) {
            return $bloccata;
        }
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        foreach ((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione) as $rigo) {
            $this->dataMapper->delete($rigo);
        }
        $this->dataMapper->delete($dichiarazione);
        $this->aggiungiMessaggio('Dichiarazione eliminata.');
        return $this->vaiAlContribuente($soggetto, $anno);
    }

    public function scarica(DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        try {
            $this->vars['contenuto'] = (new GeneratoreDichiarazioneImu($this->dataMapper))->genera($dichiarazione);
        } catch (\InvalidArgumentException | \LengthException $errore) {
            $this->aggiungiMessaggio('File non generato: ' . $errore->getMessage(), 'danger');
            return $this->vaiAllaDichiarazione($dichiarazione);
        }
        $this->vars['nomeFile'] = GeneratoreDichiarazioneImu::getNomeFile($dichiarazione);
        return $this->render->generateData('dichiarazioneImu/scarica', $this->vars);
    }

    public function stampa(DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizza($dichiarazione);
        $generatore = new GeneratoreDichiarazioneImu($this->dataMapper);
        $righi = iterator_to_array((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione));
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['righi'] = $righi;
        $this->vars['utilizzi'] = array_map(fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'B' ? $generatore->getUtilizzo($dichiarazione, $rigo) : null, $righi);
        $this->vars['pageTitle'] = 'Dichiarazione IMU ' . $dichiarazione->anno . ' · ' . $dichiarazione->soggetto->nominativo;
        return $this->render->generateView('dichiarazioneImu/stampa', $this->vars);
    }

    private function autorizza(DichiarazioneImu $dichiarazione): void
    {
        $this->autorizzaSoggetto($dichiarazione->soggetto);
    }

    private function verificaModificabile(DichiarazioneImu $dichiarazione): ?Response
    {
        if ($dichiarazione->isModificabile()) {
            return null;
        }
        $this->aggiungiMessaggio('La dichiarazione è presentata: per modificarla riportala in bozza o crea la sostitutiva.', 'warning');
        return $this->vaiAllaDichiarazione($dichiarazione);
    }

    private function vaiAllaDichiarazione(DichiarazioneImu $dichiarazione): Response
    {
        return $this->router->redirect('dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id);
    }
}
