<?php

namespace Dichiarativi\Application\Services\Iva;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Controlli del modello IVA TR (istruzioni e specifiche IVT25): errori che
 * impediscono la presentazione (dati del frontespizio come la dichiarazione
 * IVA, trimestre, credito oltre 2.582,28 euro, presupposto, importi in
 * rimborso e compensazione entro il credito, visto o organo di controllo
 * per le compensazioni oltre 5.000 euro annui) e controlli di rispondenza
 * dei totali dei quadri.
 */
class ControlliTr
{

    /** Compensazioni dei crediti trimestrali dell'anno oltre cui serve il visto di conformità */
    public const LIMITE_VISTO = 5000.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getErrori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $errori = array_values(array_filter((new ControlliIva($this->dataMapper))->getErrori($dichiarazione, $quadri),
                        fn(string $errore) => (str_contains($errore, 'VA2') || str_contains($errore, 'VX4') || str_contains($errore, 'quadri')) === false));
        // non residenti: partita IVA con le cifre 8-10 uguali a 999 o rappresentante fiscale (codice carica 6)
        $partitaIva = (string) (new ControlliIva($this->dataMapper))->getPartitaIva($dichiarazione);
        if (($quadri->get('TD004001', 1) !== null) && (substr($partitaIva, 7, 3) !== '999') && ($dichiarazione->codiceCarica?->value !== '6')) {
            $errori[] = 'Casella dei soggetti non residenti (TD4): serve l\'identificazione diretta (partita IVA con le cifre 8-10 uguali a 999) o il rappresentante fiscale come sottoscrittore (codice carica 6).';
        }
        if (($dichiarazione->trimestre === null) || ($dichiarazione->trimestre < 1) || ($dichiarazione->trimestre > 3)) {
            $errori[] = 'Indicare il trimestre (da 1 a 3): il credito del quarto trimestre si chiede con la dichiarazione annuale.';
        }
        $gruppo = $quadri->get('TE001001', 1) !== null;
        if ($gruppo) {
            $limite = min($quadri->getDecimale('TE042001', 1), $quadri->getDecimale('TE043001', 1));
            $richiesto = $quadri->getDecimale('TE050001', 1) + $quadri->getDecimale('TE051001', 1);
            if ($limite <= PropostaTr::SOGLIA) {
                $errori[] = 'Gruppo IVA: il credito del trimestre (minore tra TE42 e TE43) non supera 2.582,28 euro.';
            }
            if (($richiesto <= 0) || ($richiesto > $limite + 0.005)) {
                $errori[] = 'Gruppo IVA: indicare il rimborso (TE50) e la compensazione (TE51), in tutto non oltre il minore tra TE42 e TE43.';
            }
            $compensazione = $quadri->getDecimale('TE051001', 1);
        } else {
            $credito = $quadri->getDecimale('TC007002');
            $richiesto = $quadri->getDecimale('TD006001') + $quadri->getDecimale('TD007001');
            if ($credito <= PropostaTr::SOGLIA) {
                $errori[] = 'Il credito del trimestre (TC7) deve superare 2.582,28 euro.';
            }
            $presupposti = array_values(array_filter(array_keys(PropostaTr::PRESUPPOSTI), fn(string $codice) => $quadri->get($codice) !== null));
            if (count($presupposti) === 0) {
                $errori[] = 'Barrare il presupposto dell\'art. 30, c. 2 (quadro TD, sezione 1).';
            }
            $limite = $presupposti === ['TD003001'] ? min($credito, $quadri->getDecimale('TB033003')) : $credito;
            if (($richiesto <= 0) || ($richiesto > $limite + 0.005)) {
                $errori[] = $presupposti === ['TD003001'] ? 'Rimborso (TD6) e compensazione (TD7): in tutto non oltre l\'imposta sugli acquisti di beni ammortizzabili (TB33).'
                            : 'Indicare il rimborso (TD6) e la compensazione (TD7), in tutto non oltre il credito del trimestre (TC7).';
            }
            $compensazione = $quadri->getDecimale('TD007001');
        }
        // visto di conformità per le compensazioni dei crediti trimestrali oltre 5.000 euro annui
        $sottoscritta = isset($dichiarazione->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione)) > 0);
        if (($compensazione > 0) && ($this->getCompensazioniAnno($dichiarazione) + $compensazione > self::LIMITE_VISTO)
                && ($dichiarazione->hasVisto() === false) && ($sottoscritta === false) && ($dichiarazione->esoneroVisto === false)) {
            $errori[] = 'Compensazioni dei crediti trimestrali dell\'anno oltre 5.000 euro: serve il visto di conformità o la sottoscrizione dell\'organo di controllo (art. 3, c. 2, D.L. 50/2017).';
        }
        return $errori;
    }

    /**
     * @return string[]
     */
    public function getSegnalazioni(QuadriDichiarazione $quadri): array
    {
        $segnalazioni = [];
        $verifica = function (string $codice, float $atteso, int $modulo) use ($quadri, &$segnalazioni): void {
            $valore = $quadri->getDecimale($codice, $modulo);
            if (abs($valore - round($atteso, 2)) > 0.01) {
                $segnalazioni[] = sprintf('%s%s: indicato %s, risulta %s.', $modulo === 1 ? '' : 'Modulo ' . $modulo . ', ', $codice, number_format($valore, 2, ',', '.'), number_format($atteso, 2, ',', '.'));
            }
        };
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            if (($quadri->get('TC007002', $modulo) === null) && ($quadri->get('TA022001', $modulo) === null) && ($quadri->get('TB020001', $modulo) === null)) {
                continue;
            }
            $imponibile = $imposta = $imponibilePassive = $impostaPassive = 0.0;
            for ($rigo = 1; $rigo <= 19; $rigo++) {
                $imponibile += $quadri->getDecimale(sprintf('TA%03d001', $rigo), $modulo);
                $imposta += $quadri->getDecimale(sprintf('TA%03d003', $rigo), $modulo);
                $imponibilePassive += $quadri->getDecimale(sprintf('TB%03d001', $rigo), $modulo);
                $impostaPassive += $quadri->getDecimale(sprintf('TB%03d003', $rigo), $modulo);
            }
            $verifica('TA022001', $imponibile + $quadri->getDecimale('TA020001', $modulo) + $quadri->getDecimale('TA021001', $modulo), $modulo);
            $verifica('TA022003', $imposta, $modulo);
            $verifica('TA024003', $quadri->getDecimale('TA022003', $modulo) + $quadri->getDecimale('TA023001', $modulo), $modulo);
            $verifica('TA032001', $quadri->getDecimale('TA022001', $modulo) + $quadri->getDecimale('TA030001', $modulo) + $quadri->getDecimale('TA031001', $modulo), $modulo);
            $verifica('TB020001', $imponibilePassive, $modulo);
            $verifica('TB020003', $impostaPassive, $modulo);
            $verifica('TB022003', $quadri->getDecimale('TB020003', $modulo) + $quadri->getDecimale('TB021001', $modulo), $modulo);
            $verifica('TC001001', $quadri->getDecimale('TA024003', $modulo), $modulo);
            $verifica('TC003001', $quadri->getDecimale('TC001001', $modulo) + $quadri->getDecimale('TC002001', $modulo), $modulo);
            $verifica('TC004002', $quadri->getDecimale('TB032001', $modulo), $modulo);
            $verifica('TC006002', $quadri->getDecimale('TC004002', $modulo) + $quadri->getDecimale('TC005002', $modulo), $modulo);
            $verifica('TC007002', max(0.0, $quadri->getDecimale('TC006002', $modulo) - $quadri->getDecimale('TC003001', $modulo)), $modulo);
        }
        if ($quadri->get('TE001001', 1) !== null) {
            $crediti = $debiti = 0.0;
            for ($rigo = 1; $rigo <= 39; $rigo++) {
                $crediti += $quadri->getDecimale(sprintf('TE%03d005', $rigo), 1);
                $debiti += $quadri->getDecimale(sprintf('TE%03d006', $rigo), 1);
            }
            $verifica('TE040001', $crediti, 1);
            $verifica('TE041001', $debiti, 1);
            $verifica('TE042001', max(0.0, $crediti - $debiti), 1);
        }
        return $segnalazioni;
    }

    /**
     * Avvertenze che non bloccano la presentazione.
     *
     * @return string[]
     */
    public function getAvvertenze(QuadriDichiarazione $quadri, ?Dichiarazione $dichiarazione = null): array
    {
        $avvertenze = array_filter([ControlliIva::getAvvisoVisto($dichiarazione)]);
        $rimborso = $quadri->getDecimale('TD006001', 1) + $quadri->getDecimale('TE050001', 1);
        if (($rimborso > 30000) && ($quadri->get('TD008003', 1) === null)) {
            foreach (($dichiarazione === null ? [] : (new RimborsoIva($this->dataMapper))->getRischi($dichiarazione)) as $rischio) {
                $avvertenze[] = 'Rimborso oltre 30.000 euro: garanzia dovuta (art. 38-bis, c. 4) per ' . $rischio . '.';
            }
            if (($dichiarazione !== null) && (new RimborsoIva($this->dataMapper))->isVerificaDaFare($dichiarazione)) {
                $avvertenze[] = RimborsoIva::AVVISO_VERIFICA;
            }
            $avvertenze[] = 'Rimborso oltre 30.000 euro senza codice di esonero dalla garanzia (TD8 campo 3): serve il visto o la sottoscrizione dell\'organo di controllo con l\'attestazione patrimoniale, altrimenti la garanzia nei casi di rischio (art. 38-bis, c. 3 e 4).';
        }
        return $avvertenze;
    }

    /**
     * Crediti trimestrali dell'anno già chiesti in compensazione con gli
     * altri modelli IVA TR del soggetto.
     */
    public function getCompensazioniAnno(Dichiarazione $dichiarazione): float
    {
        $totale = 0.0;
        foreach ((new DichiarazioneModel($this->dataMapper))->getTrimestrali($dichiarazione->soggetto, ModelloDichiarativo::ivaTr, $dichiarazione->anno) as $trimestre => $altra) {
            if (($altra->id ?? null) === ($dichiarazione->id ?? null)) {
                continue;
            }
            $quadri = new QuadriDichiarazione($altra, $this->dataMapper);
            $totale += $quadri->getDecimale('TD007001', 1) + $quadri->getDecimale('TE051001', 1);
        }
        return $totale;
    }
}
