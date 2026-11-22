<?php

namespace Dichiarativi\Application\Services\Sostituti;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Controlli della Certificazione Unica e del modello 770 (controlli
 * bloccanti delle specifiche tecniche CU 2026 e 770/2026 sui dati
 * proposti): frontespizio (rappresentante dei soggetti diversi dalle
 * persone fisiche, impegno dell'intermediario), per ogni certificazione
 * codice fiscale del percipiente, causale e imponibile coerente con il
 * compenso lordo e le somme non soggette, ritenute entro l'imponibile; per
 * il 770 periodo e codice tributo dei righi del prospetto ST e importo
 * versato con la data del versamento.
 */
class ControlliSostituti
{

    /** Tolleranza delle specifiche nel controllo dell'imponibile (AU001008) */
    public const TOLLERANZA = 2.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getErrori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $errori = [];
        if (($dichiarazione->soggetto->isPersonaFisica() === false) && (($dichiarazione->dichiarante === null) || ($dichiarazione->codiceCarica === null))) {
            $errori[] = 'Indicare nel frontespizio il rappresentante firmatario e il codice di carica.';
        }
        if (($dichiarazione->intermediario !== null) && (($dichiarazione->impegno === null) || ($dichiarazione->dataImpegno === null))) {
            $errori[] = 'Indicare nel frontespizio l\'impegno alla trasmissione dell\'intermediario e la sua data.';
        }
        return array_merge($errori, $dichiarazione->modello === ModelloDichiarativo::modello770 ? self::getErrori770($quadri) : self::getErroriCu($quadri));
    }

    /**
     * @return string[]
     */
    public static function getErroriCu(QuadriDichiarazione $quadri): array
    {
        $errori = [];
        $moduli = $quadri->getModuli();
        if (count($moduli) === 0) {
            return ['Nessuna certificazione: proporre le certificazioni dalle ritenute o compilarle a mano.'];
        }
        $codici = [];
        foreach ($moduli as $modulo) {
            $etichetta = 'Certificazione ' . $modulo;
            $codiceFiscale = (string) self::valore($quadri, 'DA002001', $modulo);
            if ((CodiceFiscale::isValidoPersonaFisica($codiceFiscale) || CodiceFiscale::isValidoNumerico($codiceFiscale)) === false) {
                $errori[] = $etichetta . ': codice fiscale del percipiente (DA2 campo 1) mancante o non valido.';
            } elseif (isset($codici[$codiceFiscale])) {
                $errori[] = sprintf('%s: stesso percipiente della certificazione %d, riunire i compensi in una sola certificazione.', $etichetta, $codici[$codiceFiscale]);
            }
            $codici[$codiceFiscale] ??= $modulo;
            if (self::valore($quadri, 'DA001001', $modulo) === null) {
                $errori[] = $etichetta . ': dati del sostituto (DA1) mancanti, riproporre la dichiarazione.';
            }
            $compilati = array_filter(array_keys($quadri->getValori()[$modulo] ?? []), fn(string $codice) => str_starts_with($codice, 'AU') && (self::valore($quadri, $codice, $modulo) !== null));
            if (count($compilati) === 0) {
                $errori[] = $etichetta . ': nessun compenso (quadro AU).';
                continue;
            }
            if (self::valore($quadri, 'AU001001', $modulo) === null) {
                $errori[] = $etichetta . ': causale del compenso (AU1 punto 1) mancante.';
            }
            $lordo = $quadri->getDecimale('AU001004', $modulo);
            $imponibile = $quadri->getDecimale('AU001008', $modulo);
            $atteso = $lordo - $quadri->getDecimale('AU001005', $modulo) - $quadri->getDecimale('AU001007', $modulo);
            if (abs($imponibile - $atteso) > self::TOLLERANZA) {
                $errori[] = sprintf('%s: imponibile (punto 8) di € %s diverso dal compenso lordo meno le somme non soggette (€ %s).', $etichetta, number_format($imponibile, 2, ',', '.'), number_format($atteso, 2, ',', '.'));
            }
            if ((self::valore($quadri, 'AU001007', $modulo) !== null) && (self::valore($quadri, 'AU001006', $modulo) === null)) {
                $errori[] = $etichetta . ': indicare il codice delle altre somme non soggette a ritenuta (punto 6).';
            }
            if ($quadri->getDecimale('AU001009', $modulo) + $quadri->getDecimale('AU001010', $modulo) > $imponibile + 1) {
                $errori[] = $etichetta . ': ritenute (punti 9 e 10) superiori all\'imponibile.';
            }
        }
        return $errori;
    }

    /**
     * @return string[]
     */
    public static function getErrori770(QuadriDichiarazione $quadri): array
    {
        $errori = [];
        $righi = 0;
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            for ($rigo = 2; $rigo <= 19; $rigo++) {
                $prefisso = sprintf('ST%03d', $rigo);
                $compilato = false;
                foreach (['002', '006', '007', '008', '009', '010', '011', '014'] as $colonna) {
                    $compilato = $compilato || (self::valore($quadri, $prefisso . $colonna, $modulo) !== null);
                }
                if ($compilato === false) {
                    continue;
                }
                $righi++;
                $etichetta = ($modulo > 1 ? 'Modulo ' . $modulo . ', ' : '') . 'rigo ST' . $rigo;
                if (self::valore($quadri, $prefisso . '001', $modulo) === null) {
                    $errori[] = $etichetta . ': periodo di riferimento mancante.';
                }
                if (self::valore($quadri, $prefisso . '011', $modulo) === null) {
                    $errori[] = $etichetta . ': codice tributo mancante.';
                }
                if ((self::valore($quadri, $prefisso . '014', $modulo) !== null) && (self::valore($quadri, $prefisso . '007', $modulo) === null)) {
                    $errori[] = $etichetta . ': data del versamento senza importo versato.';
                }
                if ($quadri->getDecimale($prefisso . '008', $modulo) > $quadri->getDecimale($prefisso . '007', $modulo) + 0.1) {
                    $errori[] = $etichetta . ': interessi superiori all\'importo versato.';
                }
            }
        }
        if ($righi === 0) {
            $errori[] = 'Prospetto ST vuoto: nessuna ritenuta da dichiarare.';
        }
        return $errori;
    }

    /**
     * @return string[]
     */
    public function getSegnalazioni(QuadriDichiarazione $quadri): array
    {
        return [];
    }

    /**
     * Avvertenze che non bloccano la presentazione (controlli di
     * rispondenza segnalati come warning dalle specifiche).
     *
     * @return string[]
     */
    public function getAvvertenze(QuadriDichiarazione $quadri, ?Dichiarazione $dichiarazione = null): array
    {
        $avvertenze = [];
        if ($dichiarazione?->modello === ModelloDichiarativo::modello770) {
            foreach ($quadri->getModuli() as $modulo) {
                for ($rigo = 2; $rigo <= 19; $rigo++) {
                    if ((self::valore($quadri, sprintf('ST%03d002', $rigo), $modulo) !== null) && (self::valore($quadri, sprintf('ST%03d007', $rigo), $modulo) === null)) {
                        $avvertenze[] = sprintf('Rigo ST%d: ritenute non versate.', $rigo);
                    }
                }
            }
            return $avvertenze;
        }
        foreach ($quadri->getModuli() as $modulo) {
            $mancanti = [];
            foreach (['DA002002' => 'cognome', 'DA002003' => 'nome', 'DA002004' => 'sesso', 'DA002005' => 'data di nascita', 'DA002006' => 'luogo di nascita'] as $codice => $descrizione) {
                if (self::valore($quadri, $codice, $modulo) === null) {
                    $mancanti[] = $descrizione;
                }
            }
            // i soggetti diversi dalle persone fisiche hanno solo la denominazione nel cognome
            if ((count($mancanti) > 0) && CodiceFiscale::isValidoPersonaFisica((string) self::valore($quadri, 'DA002001', $modulo))) {
                $avvertenze[] = sprintf('Certificazione %d: dati del percipiente mancanti (%s).', $modulo, implode(', ', $mancanti));
            }
        }
        return $avvertenze;
    }

    /**
     * Valore del campo, null anche se svuotato a mano.
     */
    private static function valore(QuadriDichiarazione $quadri, string $codice, ?int $modulo = null): ?string
    {
        $valore = $quadri->get($codice, $modulo);
        return $valore === '' ? null : $valore;
    }
}
