<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;

/**
 * Controlli della registrazione prima del salvataggio: esercizio aperto e
 * data compresa, controparte e documento richiesti dalla causale, registro
 * IVA del tipo della causale, coerenza delle righe IVA (codici, imposta,
 * totale del documento) e quadratura delle righe in partita doppia su
 * sottoconti del piano dell'azienda.
 */
class ControlloRegistrazione
{

    /** Differenza ammessa tra imposta indicata e calcolata (arrotondamenti del documento). */
    public const TOLLERANZA_IMPOSTA = 1.00;

    /**
     * @param RigaIva[] $righeIva con il segno del documento
     * @param RigaContabile[] $righe
     * @return array<string, string> errori per campo ("righeIva", "righe" per gli errori delle righe)
     */
    public function verifica(PianoConti $piano, RegistrazioneContabile $registrazione, array $righeIva, array $righe): array
    {
        $errori = [];
        $esercizio = $registrazione->esercizioContabile;
        if ($esercizio->chiuso) {
            $errori['dataRegistrazione'] = 'L\'esercizio ' . $esercizio->getEtichetta() . ' è chiuso.';
        } elseif ($esercizio->comprende($registrazione->dataRegistrazione) === false) {
            $errori['dataRegistrazione'] = 'La data non è compresa nell\'esercizio ' . $esercizio->getEtichetta() . '.';
        }
        $causale = $registrazione->causale;
        if ($causale->isControparteRichiesta() && ($registrazione->controparte === null)) {
            $errori['controparte'] = 'Indicare il ' . ($causale->isControparteCliente() ? 'cliente' : 'fornitore') . '.';
        }
        if ($causale->isDocumentoRichiesto() && (($registrazione->numeroDocumento === null) || ($registrazione->dataDocumento === null))) {
            $errori['numeroDocumento'] = 'Indicare numero e data del documento.';
        }
        if (($registrazione->dataDocumento !== null) && ($registrazione->dataDocumento->format('Y-m-d') > $registrazione->dataRegistrazione->format('Y-m-d'))) {
            $errori['dataDocumento'] = 'Il documento non può essere successivo alla registrazione.';
        }
        if ($causale->isIva()) {
            $errori += $this->verificaIva($registrazione, $righeIva);
        } elseif (in_array($causale->value, ['INC', 'PAG', 'VIV'], true) && (($registrazione->totaleDocumento ?? 0) <= 0) && (count($righe) === 0)) {
            $errori['totaleDocumento'] = 'Indicare l\'importo.';
        }
        $erroreRighe = $this->verificaRighe($piano, $righe);
        if ($erroreRighe !== null) {
            $errori['righe'] = $erroreRighe;
        }
        return $errori;
    }

    /**
     * @param RigaIva[] $righeIva
     */
    private function verificaIva(RegistrazioneContabile $registrazione, array $righeIva): array
    {
        $errori = [];
        $tipo = $registrazione->causale->getTipoRegistroIva();
        $registro = $registrazione->registroIva;
        if (($registro === null) || ($registro->tipo !== $tipo) || ($registro->soggetto->id !== $registrazione->soggetto->id)) {
            $errori['registroIva'] = 'Scegliere un registro ' . mb_strtolower($tipo->getLabelBreve()) . ' dell\'azienda.';
        }
        if ($registrazione->dataCompetenzaIva === null) {
            $errori['dataCompetenzaIva'] = 'Indicare la data di competenza IVA.';
        } elseif (($tipo === TipoRegistroIva::acquisti) && ($registrazione->dataDocumento !== null) && ($registrazione->dataCompetenzaIva->format('Y-m-d') < $registrazione->dataDocumento->format('Y-m-d'))) {
            $errori['dataCompetenzaIva'] = 'L\'imposta non si detrae prima della data del documento.';
        } elseif ($registrazione->dataCompetenzaIva->format('Y-m-d') > $registrazione->dataRegistrazione->format('Y-m-d')) {
            $errori['dataCompetenzaIva'] = 'La competenza IVA non può essere successiva alla registrazione.';
        }
        if (count($righeIva) === 0) {
            $errori['righeIva'] = 'Indicare almeno una riga IVA.';
            return $errori;
        }
        $segno = $registrazione->causale->getSegno();
        // data dell'operazione per i codici validi fino a una data: quella del documento o la competenza
        $dataOperazione = $registrazione->dataDocumento ?? $registrazione->dataCompetenzaIva;
        foreach ($righeIva as $indice => $riga) {
            $codice = $riga->codiceIva;
            $numero = 'Riga IVA ' . ($indice + 1) . ': ';
            if (($codice->studio !== null) && ($codice->studio->id !== $registrazione->studio->id)) {
                $errori['righeIva'] = $numero . 'codice IVA non disponibile.';
            } elseif ($codice->ventilazione && ($tipo !== TipoRegistroIva::corrispettivi)) {
                $errori['righeIva'] = $numero . 'il codice ' . $codice->codice . ' (corrispettivi da ventilare) vale solo per i corrispettivi.';
            } elseif ($codice->ventilazione && ($riga->imposta != 0)) {
                $errori['righeIva'] = $numero . 'i corrispettivi da ventilare si indicano al lordo, senza imposta.';
            } elseif ($codice->inversioneContabile && ($tipo !== TipoRegistroIva::acquisti)) {
                $errori['righeIva'] = $numero . 'il codice ' . $codice->codice . ' (integrazione dell\'imposta) vale solo per gli acquisti.';
            } elseif (($codice->natura !== null) && ($riga->imposta != 0)) {
                $errori['righeIva'] = $numero . 'operazione senza imposta (' . $codice->natura->value . '): l\'imposta deve essere zero.';
            } elseif (abs($riga->imposta - $codice->calcolaImposta($riga->imponibile)) > self::TOLLERANZA_IMPOSTA) {
                $errori['righeIva'] = $numero . 'l\'imposta non corrisponde all\'aliquota ' . $codice->aliquota . '% (attesa ' . number_format($codice->calcolaImposta($riga->imponibile), 2, ',', '.') . ').';
            } elseif (($riga->detraibilita < 0) || ($riga->detraibilita > 100)) {
                $errori['righeIva'] = $numero . 'detraibilità tra 0 e 100.';
            } elseif (($dataOperazione !== null) && ($codice->isValidoAl($dataOperazione) === false)) {
                $errori['righeIva'] = $numero . 'il codice ' . $codice->codice . ' vale per le operazioni fino al ' . $codice->validoFino->format('d/m/Y')
                        . ($codice->getNorma($dataOperazione) === null ? '' : ' (' . $codice->getNorma($dataOperazione) . ')') . ': usare il codice dell\'operazione con l\'imposta.';
            }
            if (isset($errori['righeIva'])) {
                return $errori;
            }
        }
        $totale = PropostaRighe::totaleDocumento($righeIva);
        if ($totale * $segno <= 0) {
            $errori['righeIva'] = $segno > 0 ? 'Il totale del documento deve essere positivo.' : 'Indicare gli importi della nota di credito (positivi).';
        } elseif (($registrazione->totaleDocumento !== null) && (abs(abs($totale) - $registrazione->totaleDocumento) > 0.005)) {
            $errori['totaleDocumento'] = 'Il totale (' . number_format(abs($totale), 2, ',', '.') . ') non corrisponde a imponibile e imposta delle righe IVA (esclusa l\'imposta integrata).';
        }
        return $errori;
    }

    /**
     * @param RigaContabile[] $righe
     */
    private function verificaRighe(PianoConti $piano, array $righe): ?string
    {
        if (count($righe) < 2) {
            return 'Indicare almeno due righe in partita doppia (o usare "Proponi le righe").';
        }
        $dare = $avere = 0.0;
        foreach ($righe as $indice => $riga) {
            $numero = 'Riga ' . ($indice + 1) . ': ';
            if (($riga->conto->pianoConti->id !== $piano->id) || ($riga->conto->isMovimentabile() === false)) {
                return $numero . 'scegliere un sottoconto attivo del piano dell\'azienda.';
            }
            if (($riga->dare < 0) || ($riga->avere < 0) || (($riga->dare > 0) === ($riga->avere > 0))) {
                return $numero . 'indicare un importo positivo in dare o in avere.';
            }
            $dare += $riga->dare;
            $avere += $riga->avere;
        }
        if (abs($dare - $avere) > 0.005) {
            return sprintf('La registrazione non quadra: dare %s, avere %s (differenza %s).', number_format($dare, 2, ',', '.'), number_format($avere, 2, ',', '.'), number_format($dare - $avere, 2, ',', '.'));
        }
        return null;
    }
}
