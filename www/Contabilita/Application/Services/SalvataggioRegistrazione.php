<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Models\CorrispettivoGiornalieroModel;
use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Salvataggio della registrazione con le sue righe, già controllate da
 * ControlloRegistrazione: numero progressivo nell'esercizio (assegnato
 * alla creazione, non riutilizzato), protocollo IVA per registro e anno
 * solare (riassegnato se cambiano registro o anno), righe (e dettaglio
 * giornaliero dei corrispettivi) riscritte con i
 * dati copiati dalla registrazione e collegamento alla fattura
 * elettronica da cui deriva.
 */
class SalvataggioRegistrazione
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @param RigaIva[] $righeIva con il segno del documento
     * @param RigaContabile[] $righe
     * @param string|null $chiaveProtocollo registro e anno del protocollo prima della modifica ("id|anno")
     */
    public function salva(RegistrazioneContabile $registrazione, array $righeIva, array $righe, ?string $chiaveProtocollo = null, ?FatturaElettronica $fattura = null): RegistrazioneContabile
    {
        $registrazioneModel = new RegistrazioneContabileModel($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        if (isset($registrazione->id) === false) {
            $registrazione->numero = $registrazioneModel->getProssimoNumero($registrazione->esercizioContabile);
            $registrazione->dataInserimento = new SismaDateTime();
        } else {
            $registrazione->dataModifica = new SismaDateTime();
        }
        if ($registrazione->causale->isIva()) {
            $anno = (int) $registrazione->dataRegistrazione->format('Y');
            if (($registrazione->protocolloIva === null) || ($chiaveProtocollo !== self::chiaveProtocollo($registrazione))) {
                $registrazione->protocolloIva = $registrazioneModel->getProssimoProtocollo($registrazione->registroIva, $anno);
            }
        } else {
            $registrazione->registroIva = null;
            $registrazione->protocolloIva = null;
            $registrazione->dataCompetenzaIva = null;
            $righeIva = [];
        }
        $this->dataMapper->save($registrazione);
        $this->eliminaRighe($registrazione);
        foreach (array_values($righeIva) as $indice => $rigaIva) {
            $rigaIva->registrazioneContabile = $registrazione;
            $rigaIva->soggetto = $registrazione->soggetto;
            $rigaIva->registroIva = $registrazione->registroIva;
            $rigaIva->dataCompetenzaIva = $registrazione->dataCompetenzaIva;
            $rigaIva->ordine = $indice + 1;
            $this->dataMapper->save($rigaIva);
        }
        foreach (array_values($righe) as $indice => $riga) {
            $riga->registrazioneContabile = $registrazione;
            $riga->soggetto = $registrazione->soggetto;
            $riga->dataRegistrazione = $registrazione->dataRegistrazione;
            $riga->ordine = $indice + 1;
            $this->dataMapper->save($riga);
        }
        if ($fattura !== null) {
            $fattura->registrazioneContabile = $registrazione;
            $fattura->controparte = $registrazione->controparte ?? $fattura->controparte;
            $fattura->stato = StatoFatturaElettronica::registrata;
            $this->dataMapper->save($fattura);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $registrazione;
    }

    /**
     * Registro e anno del protocollo della registrazione ("id|anno"; null
     * se non è un documento IVA).
     */
    public static function chiaveProtocollo(RegistrazioneContabile $registrazione): ?string
    {
        if (($registrazione->registroIva === null) || (isset($registrazione->registroIva->id) === false)) {
            return null;
        }
        return $registrazione->registroIva->id . '|' . $registrazione->dataRegistrazione->format('Y');
    }

    /**
     * Elimina la registrazione: la fattura elettronica collegata torna da
     * registrare, la liquidazione IVA che l'aveva generata perde il
     * collegamento al giroconto.
     */
    public function elimina(RegistrazioneContabile $registrazione): void
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $fattura = (new FatturaElettronicaModel($this->dataMapper))->getPerRegistrazione($registrazione);
        if ($fattura !== null) {
            $fattura->registrazioneContabile = null;
            $fattura->stato = StatoFatturaElettronica::daRegistrare;
            $this->dataMapper->save($fattura);
        }
        foreach ((new LiquidazioneIvaModel($this->dataMapper))->getByRegistrazioneContabile($registrazione) as $liquidazione) {
            $liquidazione->registrazioneContabile = null;
            $this->dataMapper->save($liquidazione);
        }
        $this->eliminaRighe($registrazione);
        $this->dataMapper->delete($registrazione);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    private function eliminaRighe(RegistrazioneContabile $registrazione): void
    {
        foreach ((new CorrispettivoGiornalieroModel($this->dataMapper))->getPerRegistrazione($registrazione) as $corrispettivo) {
            $this->dataMapper->delete($corrispettivo);
        }
        foreach ((new RigaIvaModel($this->dataMapper))->getRighe($registrazione) as $rigaIva) {
            $this->dataMapper->delete($rigaIva);
        }
        foreach ((new RigaContabileModel($this->dataMapper))->getRighe($registrazione) as $riga) {
            $this->dataMapper->delete($riga);
        }
    }
}
