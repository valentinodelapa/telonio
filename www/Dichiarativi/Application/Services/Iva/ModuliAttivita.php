<?php

namespace Dichiarativi\Application\Services\Iva;

use Contabilita\Application\Services\AttivitaSeparate;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Models\ModuloDichiarazioneModel;
use Dichiarativi\Application\Models\ValoreDichiarazioneModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Moduli delle attività con contabilità separata (art. 36; paragrafo 3.2
 * delle istruzioni IVA): la prima attività dell'anno è il modulo 1, le
 * altre i moduli successivi, nell'ordine del numero; gli altri moduli
 * (operazioni straordinarie, prima frazione d'anno, attività separate
 * compilate a mano) seguono. I moduli e i loro valori sono rinumerati nel
 * database prima della proposta.
 */
class ModuliAttivita
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return bool true se i moduli sono stati creati, eliminati o rinumerati
     */
    public function sincronizza(Dichiarazione $dichiarazione): bool
    {
        if (isset($dichiarazione->id) === false) {
            return false;
        }
        $attivita = array_values((new AttivitaSeparate($this->dataMapper))->getAttivita($dichiarazione->soggetto, $dichiarazione->anno));
        $moduli = (new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione);
        $collegati = $altri = [];
        foreach ($moduli as $modulo) {
            if ($modulo->attivitaIva !== null) {
                $collegati[$modulo->attivitaIva->id] = $modulo;
            } else {
                $altri[] = $modulo;
            }
        }
        $nuovi = [];
        foreach (array_slice($attivita, 1) as $indice => $singola) {
            $modulo = $collegati[$singola->id] ?? null;
            unset($collegati[$singola->id]);
            if ($modulo === null) {
                $modulo = new ModuloDichiarazione($this->dataMapper);
                $modulo->dichiarazione = $dichiarazione;
                $modulo->tipo = TipoModulo::attivitaSeparata;
                $modulo->attivitaIva = $singola;
                $modulo->numero = 0;
            }
            $nuovi[$indice + 2] = $modulo;
        }
        $numero = count($attivita) === 0 ? 1 : count($attivita);
        usort($altri, fn(ModuloDichiarazione $a, ModuloDichiarazione $b) => $a->numero <=> $b->numero);
        foreach ($altri as $modulo) {
            $nuovi[++$numero] = $modulo;
        }
        $mappa = [];
        foreach ($nuovi as $nuovoNumero => $modulo) {
            if ($modulo->numero !== $nuovoNumero) {
                $mappa[$modulo->numero] = $nuovoNumero;
            }
        }
        if ((count($mappa) === 0) && (count($collegati) === 0)) {
            return false;
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $valori = iterator_to_array((new ValoreDichiarazioneModel($this->dataMapper))->getValori($dichiarazione), false);
        $eliminati = array_map(fn(ModuloDichiarazione $modulo) => $modulo->numero, $collegati);
        // attività non più separate: moduli e valori eliminati
        foreach ($valori as $valore) {
            if (in_array($valore->modulo, $eliminati, true)) {
                $this->dataMapper->delete($valore);
            }
        }
        foreach ($collegati as $modulo) {
            $this->dataMapper->delete($modulo);
        }
        // rinumerazione in due passi per non violare l'unicità del numero
        foreach ($valori as $valore) {
            if (isset($mappa[$valore->modulo]) && (in_array($valore->modulo, $eliminati, true) === false)) {
                $valore->modulo = 1000 + $mappa[$valore->modulo];
                $this->dataMapper->save($valore);
            }
        }
        foreach ($nuovi as $nuovoNumero => $modulo) {
            if ($modulo->numero !== $nuovoNumero) {
                $modulo->numero = 1000 + $nuovoNumero;
                $this->dataMapper->save($modulo);
            }
        }
        foreach ($valori as $valore) {
            if ($valore->modulo > 1000) {
                $valore->modulo -= 1000;
                $this->dataMapper->save($valore);
            }
        }
        foreach ($nuovi as $modulo) {
            if ($modulo->numero > 1000) {
                $modulo->numero -= 1000;
                $this->dataMapper->save($modulo);
            }
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return true;
    }
}
