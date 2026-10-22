<?php

namespace Deleghe\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\SoggettoModel;
use CommSuite\Application\HelperClasses\CodiceFiscale;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Models\CausaleContributoModel;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Regole di compilazione del modello F24, dalle tabelle e dalle specifiche
 * tecniche dell'Agenzia delle Entrate:
 * - per rigo: i formalismi del codice tributo (debito o credito ammesso,
 *   formato della rateazione, anno di riferimento e anno massimo, codice
 *   ente della sezione IMU, elementi identificativi), le sedi, le causali
 *   e i formati della matricola degli enti previdenziali;
 * - per delega: testata, codice ufficio e atto, identificativo operazione,
 *   crediti IMU, ripartizione sui modelli, saldo e canale di presentazione.
 *
 * Gli errori impediscono la conferma; gli avvisi segnalano situazioni da
 * valutare.
 */
class ControlloDelega
{

    private const PERIODO = '/^(\d{2})\/(\d{4})$/';
    private const ALTRO_ENTE_SENZA_SEDE = '0002';
    private const ALTRO_ENTE_SENZA_POSIZIONE = '0003';
    private const ALTRO_ENTE_ENPALS = '0001';

    private CodiceTabellaF24Model $tabelle;
    private CausaleContributoModel $causali;
    private ComuneModel $comuni;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {
        $this->tabelle = new CodiceTabellaF24Model($dataMapper);
        $this->causali = new CausaleContributoModel($dataMapper);
        $this->comuni = new ComuneModel($dataMapper);
    }

    /**
     * Errori del rigo per campo (vuoto se il rigo è corretto).
     *
     * @return array<string, string>
     */
    public function verificaRiga(Versamento $riga): array
    {
        return $this->verificaDatiRiga($riga, $riga->sezione, $riga->scadenza);
    }

    /**
     * Come verificaRiga, sui dati di un rigo non ancora risolto (es. i dati
     * validati di un form).
     *
     * @return array<string, string>
     */
    public function verificaDatiRiga(object $riga, SezioneF24 $sezione, \DateTimeInterface $dataVersamento): array
    {
        $errori = $this->verificaImporti($riga);
        if (in_array($sezione, [SezioneF24::elide, SezioneF24::accise], true) && ((float) ($riga->importoCredito ?? 0) > 0)) {
            $errori['importoCredito'] = 'La sezione ' . $sezione->getLabel() . ' non ammette compensazioni.';
        }
        if ($sezione->getSezioneCodici() !== null) {
            $errori += $this->verificaCodiceTributo($riga, $sezione, (int) $dataVersamento->format('Y'));
        }
        $errori += match ($sezione) {
            SezioneF24::erario => $this->verificaCertificazione($riga),
            SezioneF24::regioni => $this->verificaCodice('codiceEnte', $riga->codiceEnte, TabellaF24::regioni, 'Indicare il codice della regione (tabella T0).'),
            SezioneF24::tributiLocali => $this->verificaTributiLocali($riga),
            SezioneF24::inps => $this->verificaInps($riga),
            SezioneF24::inail => $this->verificaInail($riga),
            SezioneF24::altriEnti => $this->verificaAltriEnti($riga),
            SezioneF24::elide => $this->verificaElide($riga),
            SezioneF24::accise => $this->verificaAccise($riga),
        };
        return $errori;
    }

    private function verificaImporti(object $riga): array
    {
        $errori = [];
        $importi = [];
        foreach (['importoDebito', 'importoCredito'] as $campo) {
            $importi[$campo] = (float) ($riga->$campo ?? 0);
            if ($importi[$campo] < 0) {
                $errori[$campo] = 'L\'importo non può essere negativo.';
            } elseif (abs($importi[$campo] * 100 - round($importi[$campo] * 100)) > 0.000001) {
                $errori[$campo] = 'Indicare al massimo due decimali.';
            }
        }
        if (count($errori) === 0) {
            if (($importi['importoDebito'] > 0) && ($importi['importoCredito'] > 0)) {
                $errori['importoCredito'] = 'Un rigo riporta un importo a debito oppure a credito, non entrambi.';
            } elseif (($importi['importoDebito'] == 0) && ($importi['importoCredito'] == 0)) {
                $errori['importoDebito'] = 'Indicare l\'importo a debito o a credito.';
            }
        }
        return $errori;
    }

    /**
     * Formalismi del codice tributo: sezione, utilizzo a debito o a
     * credito, anno di riferimento, rateazione.
     */
    private function verificaCodiceTributo(object $riga, SezioneF24 $sezione, int $annoVersamento): array
    {
        $codice = $riga->codiceTributo;
        if ($codice === null) {
            return ['codiceTributo' => 'Indicare il codice tributo.'];
        }
        if ($codice->sezione !== $sezione->getSezioneCodici()) {
            return ['codiceTributo' => 'Il codice ' . $codice->codice . ' non è utilizzabile nella sezione ' . $sezione->getLabel() . '.'];
        }
        $errori = [];
        if (((float) ($riga->importoDebito ?? 0) > 0) && ($codice->modalita->ammetteDebito() === false)) {
            $errori['codiceTributo'] = 'Il codice ' . $codice->codice . ' si usa solo per i crediti in compensazione.';
        } elseif (((float) ($riga->importoCredito ?? 0) > 0) && ($codice->modalita->ammetteCredito() === false)) {
            $errori['codiceTributo'] = 'Il codice ' . $codice->codice . ' non è utilizzabile in compensazione.';
        }
        $anno = $riga->annoRiferimento;
        if ($codice->annoRichiesto && (($anno === null) || ($anno < 1900) || ($anno > $annoVersamento + $codice->annoMassimo))) {
            $errori['annoRiferimento'] = $anno === null ? 'Indicare l\'anno di riferimento.' : 'Anno di riferimento non valido: al massimo ' . ($annoVersamento + $codice->annoMassimo) . '.';
        } elseif (($codice->annoRichiesto === false) && ($anno !== null)) {
            $errori['annoRiferimento'] = 'Per il codice ' . $codice->codice . ' l\'anno non va indicato.';
        }
        if (($messaggio = $this->verificaRateazione($codice, $riga->rateazione)) !== null) {
            $errori['rateazione'] = $messaggio;
        }
        return $errori;
    }

    private function verificaRateazione(CodiceTributo $codice, ?string $rateazione): ?string
    {
        $formato = $codice->formatoRateazione;
        if ($rateazione === null) {
            return in_array($formato, ['0000', 'FFFF'], true) ? null : 'Indicare ' . $codice->getDescrizioneRateazione() . '.';
        }
        $valido = match ($formato) {
            '0000' => false,
            '00MM' => preg_match('/^00(0[1-9]|1[0-2])$/', $rateazione) === 1,
            'NNRR', 'FFFF' => self::isRateazione($rateazione),
            '00T0' => str_starts_with($rateazione, '00') && ($this->tabelle->getVoce(TabellaF24::regioni, substr($rateazione, 2)) !== null),
            '00T1' => str_starts_with($rateazione, '00') && ($this->tabelle->getVoce(TabellaF24::entiLocali, substr($rateazione, 2)) !== null),
            '00T2' => str_starts_with($rateazione, '00') && ($this->tabelle->getVoce(TabellaF24::province, substr($rateazione, 2)) !== null),
            '00T4' => $this->comuni->getByCodice($rateazione) !== null,
            default => preg_match('/^[A-Z0-9]{4}$/', $rateazione) === 1,
        };
        return $valido ? null : ($formato === '0000' ? 'Per il codice ' . $codice->codice . ' la rateazione non va indicata.' : 'Valore non valido: indicare ' . $codice->getDescrizioneRateazione() . '.');
    }

    private static function isRateazione(string $rateazione): bool
    {
        if (preg_match('/^(\d{2})(\d{2})$/', $rateazione, $parti) !== 1) {
            return false;
        }
        return ((int) $parti[1] >= 1) && ((int) $parti[1] <= (int) $parti[2]);
    }

    private function verificaCodice(string $campo, ?string $valore, TabellaF24 $tabella, string $messaggio): array
    {
        return $this->tabelle->getVoce($tabella, $valore) === null ? [$campo => $messaggio] : [];
    }

    /**
     * Il codice ente si verifica sulle tabelle indicate dal codice tributo:
     * T4 comuni (archivio comuni di Anagrafica), T1, T2, T6; T5 (enti
     * convenzionati) non è importata e si controlla solo nel formato.
     */
    private function verificaTributiLocali(object $riga): array
    {
        $errori = [];
        $tabelle = $riga->codiceTributo?->getTabelleEnte() ?: ['T4'];
        $ente = $riga->codiceEnte;
        $valido = false;
        foreach ($tabelle as $tabella) {
            $valido = $valido || match ($tabella) {
                'T4' => ($ente !== null) && (preg_match('/^[A-Z]\d{3}$/', $ente) === 1) && ($this->comuni->getByCodice($ente) !== null),
                'T1', 'T2', 'T6' => $this->tabelle->getVoce(TabellaF24::daTerritoriale($tabella), $ente) !== null,
                default => ($ente !== null) && (preg_match('/^[A-Z0-9]{1,4}$/', $ente) === 1),
            };
        }
        if ($valido === false) {
            $errori['codiceEnte'] = in_array('T4', $tabelle, true) ? 'Indicare il codice catastale del comune (es. H501).' : 'Codice ente non valido per il tributo (tabelle ' . implode(', ', $tabelle) . ').';
        }
        if (($riga->numeroImmobili !== null) && (($riga->numeroImmobili < 1) || ($riga->numeroImmobili > 999))) {
            $errori['numeroImmobili'] = 'Numero di immobili da 1 a 999.';
        }
        return $errori;
    }

    private function verificaInps(object $riga): array
    {
        $errori = [];
        if ((preg_match('/^\d{4}$/', (string) $riga->codiceSede) !== 1) || ($this->tabelle->getVoce(TabellaF24::sediInps, $riga->codiceSede) === null)) {
            $errori['codiceSede'] = 'Codice sede INPS non presente nella tabella delle sedi (4 cifre).';
        }
        $causale = $this->causali->getCausale(CausaleContributo::ENTE_INPS, $riga->causale);
        if ($causale === null) {
            $errori['causale'] = 'Causale contributo INPS non presente nella tabella delle causali.';
        } else {
            $errori += $this->verificaUtilizzoCausale($riga, $causale);
            if (($messaggio = $this->verificaMatricola($riga->posizione, $causale)) !== null) {
                $errori['posizione'] = $messaggio;
            }
        }
        return $errori + $this->verificaPeriodo($riga, $causale);
    }

    private function verificaUtilizzoCausale(object $riga, CausaleContributo $causale): array
    {
        if (((float) ($riga->importoDebito ?? 0) > 0) && ($causale->modalita->ammetteDebito() === false)) {
            return ['causale' => 'La causale ' . $causale->causale . ' si usa solo per i crediti.'];
        }
        if (((float) ($riga->importoCredito ?? 0) > 0) && ($causale->modalita->ammetteCredito() === false)) {
            return ['causale' => 'La causale ' . $causale->causale . ' non è utilizzabile in compensazione.'];
        }
        return [];
    }

    /**
     * Formato della matricola INPS ammesso per la causale (tabella dei
     * formati dell'Agenzia).
     */
    private function verificaMatricola(?string $matricola, CausaleContributo $causale): ?string
    {
        $formati = $causale->getFormatiMatricola();
        if (count($formati) === 0) {
            return preg_match('/^[A-Z0-9]{1,17}$/', (string) $matricola) === 1 ? null : 'Matricola / codice INPS: fino a 17 caratteri.';
        }
        $descrizioni = [];
        foreach ($formati as $formato) {
            $voce = $this->tabelle->getVoce(TabellaF24::formatiMatricolaInps, $formato);
            if ($voce === null) {
                continue;
            }
            if (preg_match('/' . $voce->dati . '/', (string) $matricola) === 1) {
                return null;
            }
            $descrizioni[] = $voce->descrizione;
        }
        return count($descrizioni) === 0 ? null : 'Per la causale ' . $causale->causale . ' indicare: ' . implode(' oppure ', array_unique($descrizioni)) . '.';
    }

    private function verificaInail(object $riga): array
    {
        $errori = [];
        if ((preg_match('/^\d{5}$/', (string) $riga->codiceSede) !== 1) || ($this->tabelle->getVoce(TabellaF24::sediInail, $riga->codiceSede) === null)) {
            $errori['codiceSede'] = 'Codice sede INAIL non presente nella tabella delle sedi (5 cifre).';
        }
        if (preg_match('/^\d{1,8}$/', (string) $riga->posizione) !== 1) {
            $errori['posizione'] = 'Codice ditta: fino a 8 cifre.';
        }
        if (preg_match('/^\d{2}$/', (string) $riga->cifraControllo) !== 1) {
            $errori['cifraControllo'] = 'Cifra di controllo: 2 cifre.';
        }
        if (preg_match('/^\d{1,6}$/', (string) $riga->numeroRiferimento) !== 1) {
            $errori['numeroRiferimento'] = 'Numero di riferimento: fino a 6 cifre.';
        }
        if (preg_match('/^[A-Z]$/', (string) $riga->causale) !== 1) {
            $errori['causale'] = 'Causale INAIL: una lettera (es. P per premi).';
        }
        return $errori;
    }

    private function verificaAltriEnti(object $riga): array
    {
        $errori = $this->verificaCodice('codiceEnte', $riga->codiceEnte, TabellaF24::altriEnti, 'Codice ente non presente nella tabella degli altri enti previdenziali.');
        $ente = $riga->codiceEnte;
        if ($ente === self::ALTRO_ENTE_ENPALS) {
            $errori += $this->verificaCodice('codiceSede', $riga->codiceSede, TabellaF24::sediEnpals, 'Codice sede non presente nella tabella delle sedi ENPALS.');
        } elseif (($ente !== self::ALTRO_ENTE_SENZA_SEDE) && (preg_match('/^[A-Z0-9]{1,5}$/', (string) $riga->codiceSede) !== 1)) {
            $errori['codiceSede'] = 'Codice sede: fino a 5 caratteri.';
        }
        $causale = null;
        if (($ente !== null) && $this->causali->hasCausali($ente)) {
            $causale = $this->causali->getCausale($ente, $riga->causale);
            if ($causale === null) {
                $errori['causale'] = 'Causale non presente nella tabella delle causali dell\'ente.';
            }
        } elseif (preg_match('/^[A-Z0-9]{1,4}$/', (string) $riga->causale) !== 1) {
            $errori['causale'] = 'Causale contributo: fino a 4 caratteri.';
        }
        if ($riga->posizione === null ? $ente !== self::ALTRO_ENTE_SENZA_POSIZIONE : (preg_match('/^\d{1,9}$/', $riga->posizione) !== 1)) {
            $errori['posizione'] = 'Codice posizione: fino a 9 cifre.';
        }
        return $errori + $this->verificaPeriodo($riga, $causale);
    }

    /**
     * Numero di certificazione solo per il credito verso la PA (codice
     * PPAA), dove è obbligatorio.
     */
    private function verificaCertificazione(object $riga): array
    {
        $numero = $riga->numeroCertificazione ?? null;
        if ($riga->codiceTributo?->isCreditoPa() === true) {
            return preg_match('/^[A-Z0-9]{1,16}$/', (string) $numero) === 1 ? [] : ['numeroCertificazione' => 'Indicare il numero della certificazione del credito rilasciato dalla piattaforma (fino a 16 caratteri).'];
        }
        return $numero === null ? [] : ['numeroCertificazione' => 'Il numero di certificazione si indica solo con il codice ' . CodiceTributo::CODICE_CREDITI_PA . '.'];
    }

    /**
     * Sezione accise: provincia, codice identificativo richiesto dall'ente e
     * mese del periodo secondo il codice tributo.
     */
    private function verificaAccise(object $riga): array
    {
        $codice = $riga->codiceTributo;
        if ($codice === null) {
            return [];
        }
        $errori = [];
        if ($codice->provinciaRichiesta && ($this->tabelle->getVoce(TabellaF24::province, $riga->provincia) === null)) {
            $errori['provincia'] = 'Indicare la sigla della provincia.';
        } elseif (($codice->provinciaRichiesta === false) && ($riga->provincia !== null)) {
            $errori['provincia'] = 'Per il codice ' . $codice->codice . ' la provincia non va indicata.';
        }
        $identificativo = $riga->codiceIdentificativoEnte;
        if (($codice->codiceIdentificativoEnte !== null) && (preg_match('/^[A-Z0-9]{1,14}$/', (string) $identificativo) !== 1)) {
            $errori['codiceIdentificativoEnte'] = 'Indicare: ' . $codice->codiceIdentificativoEnte . ' (fino a 14 caratteri).';
        } elseif (($codice->codiceIdentificativoEnte === null) && ($identificativo !== null)) {
            $errori['codiceIdentificativoEnte'] = 'Per il codice ' . $codice->codice . ' il codice identificativo non va indicato.';
        }
        $mese = $riga->meseRiferimento;
        if ($codice->meseRichiesto && (($mese === null) || ($mese < 1) || ($mese > 12))) {
            $errori['meseRiferimento'] = 'Indicare il mese di riferimento (1-12).';
        } elseif (($codice->meseRichiesto === false) && ($mese !== null)) {
            $errori['meseRiferimento'] = 'Per il codice ' . $codice->codice . ' il mese non va indicato.';
        }
        return $errori;
    }

    private function verificaElide(object $riga): array
    {
        $errori = $this->verificaCodice('tipoElide', $riga->tipoElide, TabellaF24::tipiElide, 'Indicare il tipo (tabella dei tipi di versamento ELIDE).');
        $formalismo = $riga->codiceTributo?->elementiIdentificativi;
        $elementi = $riga->elementiIdentificativi;
        if (($elementi !== null) && (preg_match('/^[A-Z0-9]{1,17}$/', $elementi) !== 1)) {
            $errori['elementiIdentificativi'] = 'Elementi identificativi: fino a 17 caratteri alfanumerici.';
        } elseif (($elementi === null) && in_array($formalismo, CodiceTributo::ELEMENTI_OBBLIGATORI, true)) {
            $errori['elementiIdentificativi'] = 'Per il codice ' . $riga->codiceTributo->codice . ' indicare gli elementi identificativi' . ($formalismo === '4' ? ' (identificativo telematico del contratto)' : '') . '.';
        } elseif (($elementi !== null) && ($formalismo === CodiceTributo::ELEMENTI_NON_COMPILARE)) {
            $errori['elementiIdentificativi'] = 'Per il codice ' . $riga->codiceTributo->codice . ' gli elementi identificativi non vanno compilati.';
        }
        return $errori;
    }

    /**
     * Periodo di riferimento secondo la causale (obbligatorio, facoltativo
     * o da non indicare); senza causale nota il periodo è facoltativo.
     */
    private function verificaPeriodo(object $riga, ?CausaleContributo $causale): array
    {
        $errori = [];
        foreach (['periodoDa' => $causale?->periodoDa ?? ObbligoCampo::facoltativo, 'periodoA' => $causale?->periodoA ?? ObbligoCampo::facoltativo] as $campo => $obbligo) {
            $valore = $riga->$campo;
            if (($valore === null) && ($obbligo === ObbligoCampo::obbligatorio)) {
                $errori[$campo] = 'Indicare il periodo (MM/AAAA).';
            } elseif (($valore !== null) && ($obbligo === ObbligoCampo::nonPrevisto)) {
                $errori[$campo] = 'Per la causale ' . $causale->causale . ' il periodo non va indicato.';
            } elseif (($valore !== null) && ((preg_match(self::PERIODO, $valore, $parti) !== 1) || ((int) $parti[1] > 12))) {
                $errori[$campo] = 'Periodo nel formato MM/AAAA.';
            }
        }
        if ((count($errori) === 0) && ($riga->periodoDa !== null) && ($riga->periodoA !== null) && (self::mesi($riga->periodoA) < self::mesi($riga->periodoDa))) {
            $errori['periodoA'] = 'Il periodo finale precede quello iniziale.';
        }
        return $errori;
    }

    private static function mesi(string $periodo): int
    {
        return ((int) substr($periodo, 3, 4)) * 12 + (int) substr($periodo, 0, 2);
    }

    /**
     * Errori dei dati di testata per campo (dati della delega o di un form).
     *
     * @return array<string, string>
     */
    public function verificaTestata(object $delega, string $codiceFiscaleContribuente): array
    {
        $errori = [];
        $codiceFiscale = $delega->codiceFiscaleCoobbligato;
        if (($codiceFiscale === null) !== ($delega->codiceIdentificativo === null)) {
            $campo = $codiceFiscale === null ? 'codiceFiscaleCoobbligato' : 'codiceIdentificativo';
            $errori[$campo] = 'Il secondo codice fiscale va indicato insieme al codice identificativo.';
        } elseif (($codiceFiscale !== null) && (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false) && (CodiceFiscale::isValidoNumerico($codiceFiscale) === false)) {
            $errori['codiceFiscaleCoobbligato'] = 'Codice fiscale non valido.';
        } elseif (($codiceFiscale !== null) && ($codiceFiscale === $codiceFiscaleContribuente)) {
            $errori['codiceFiscaleCoobbligato'] = 'Il secondo codice fiscale deve essere diverso da quello del contribuente.';
        }
        if (($delega->codiceUfficio !== null) && ($this->tabelle->getVoce(TabellaF24::uffici, $delega->codiceUfficio) === null)) {
            $errori['codiceUfficio'] = 'Codice ufficio non presente nella tabella degli uffici.';
        }
        if (($delega->codiceAtto !== null) && (preg_match('/^\d{1,11}$/', $delega->codiceAtto) !== 1)) {
            $errori['codiceAtto'] = 'Codice atto: fino a 11 cifre.';
        }
        if (($delega->identificativoOperazione !== null) && (preg_match('/^[A-Z0-9]{1,18}$/', $delega->identificativoOperazione) !== 1)) {
            $errori['identificativoOperazione'] = 'Identificativo operazione: fino a 18 caratteri.';
        }
        if (($delega->detrazioneImu !== null) && ($delega->detrazioneImu < 0)) {
            $errori['detrazioneImu'] = 'La detrazione non può essere negativa.';
        }
        $conto = $delega->contoAddebito ?? null;
        if (($conto !== null) && ($conto->isItaliano() === false)) {
            $errori['contoAddebito'] = 'Il conto di addebito deve essere un conto italiano.';
        }
        return $errori;
    }

    /**
     * Verifica completa della delega con i suoi righi, totali e ripartizione
     * sui modelli.
     */
    public function verifica(Delega $delega): EsitoControllo
    {
        $esito = new EsitoControllo();
        $righePerSezione = (new VersamentoModel($this->dataMapper))->getPerSezione($delega);
        foreach ($this->verificaTestata($delega, $delega->soggetto->codiceFiscale) as $errore) {
            $esito->errori[] = 'Testata: ' . $errore;
        }
        $tutte = [];
        foreach (SezioneF24::cases() as $sezione) {
            $righe = $righePerSezione[$sezione->value];
            if ((count($righe) > 0) && ($delega->modello->ammetteSezione($sezione) === false)) {
                $esito->errori[] = 'La sezione ' . $sezione->getLabel() . ' non è prevista nel ' . $delega->modello->getLabel() . '.';
            }
            foreach ($righe as $numero => $riga) {
                foreach ($this->verificaRiga($riga) as $errore) {
                    $esito->errori[] = $sezione->getLabel() . ', rigo ' . ($numero + 1) . ': ' . $errore;
                }
                if ($delega->isModificabile() && ($riga->codiceTributo !== null) && ($riga->codiceTributo->inUso === false)) {
                    $esito->errori[] = $sezione->getLabel() . ', rigo ' . ($numero + 1) . ': il codice tributo ' . $riga->codiceTributo->codice . ' non è più in uso.';
                }
                if ($riga->hasSecondoSoggettoRichiesto() && (($riga->codiceFiscaleCoobbligato !== $delega->codiceFiscaleCoobbligato) || ($riga->codiceIdentificativo !== $delega->codiceIdentificativo))) {
                    $esito->errori[] = sprintf('%s, rigo %d: il versamento richiede il secondo codice fiscale %s con codice identificativo %s.', $sezione->getLabel(), $numero + 1, $riga->codiceFiscaleCoobbligato, $riga->codiceIdentificativo?->value);
                }
                $esito->totaliSezione[$sezione->value]['debito'] += EsitoControllo::centesimi($riga->importoDebito);
                $esito->totaliSezione[$sezione->value]['credito'] += EsitoControllo::centesimi($riga->importoCredito);
                $tutte[] = $riga;
            }
            $esito->debito += $esito->totaliSezione[$sezione->value]['debito'];
            $esito->credito += $esito->totaliSezione[$sezione->value]['credito'];
        }
        $this->verificaDatiSezioni($delega, $tutte, $esito);
        $this->verificaCreditiImu($tutte, $esito);
        $this->verificaCreditiPa($tutte, $esito);
        $this->verificaCreditiArchivio($delega, $tutte, $esito);
        $this->verificaSaldo($delega, $righePerSezione, $esito);
        if ($esito->getSaldo() >= 0) {
            $ripartizione = new RipartizioneModelli(fn(Versamento $riga) => $this->isCompensabileCreditiPa($riga));
            $esito->modelli = $ripartizione->ripartisci($delega->modello, $tutte);
            array_push($esito->errori, ...$ripartizione->getErrori());
            if (count($esito->modelli) > 1) {
                $esito->avvisi[] = sprintf('La delega occupa %d modelli F24: le sezioni superano i righi disponibili in un modello.', count($esito->modelli));
            }
        }
        $this->verificaAddebito($delega, $esito);
        return $esito;
    }

    /**
     * Codice ufficio e atto (sezione Erario) e identificativo operazione
     * (sezione IMU) richiesti o ammessi dai codici tributo della delega.
     *
     * @param Versamento[] $righe
     */
    private function verificaDatiSezioni(Delega $delega, array $righe, EsitoControllo $esito): void
    {
        foreach (['codiceUfficio' => 'il codice ufficio', 'codiceAtto' => 'il codice atto', 'identificativoOperazione' => 'l\'identificativo operazione'] as $campo => $descrizione) {
            $richiesto = [];
            $ammesso = false;
            foreach ($righe as $riga) {
                $obbligo = $riga->codiceTributo?->$campo ?? ObbligoCampo::nonPrevisto;
                $ammesso = $ammesso || $obbligo->isAmmesso();
                if ($obbligo === ObbligoCampo::obbligatorio) {
                    $richiesto[] = $riga->codiceTributo->codice;
                }
            }
            if (($delega->$campo === null) && (count($richiesto) > 0)) {
                $esito->errori[] = 'Indicare ' . $descrizione . ', richiesto dai codici ' . implode(', ', array_unique($richiesto)) . '.';
            } elseif (($delega->$campo !== null) && ($ammesso === false)) {
                $esito->errori[] = 'Nessun codice tributo della delega prevede ' . $descrizione . '.';
            }
        }
        $sezioniConAtto = [];
        foreach ($righe as $riga) {
            if (($riga->codiceTributo?->codiceUfficio === ObbligoCampo::obbligatorio) || ($riga->codiceTributo?->codiceAtto === ObbligoCampo::obbligatorio)) {
                $sezioniConAtto[$riga->sezione->value] = $riga->sezione->getLabel();
            }
        }
        if (isset($sezioniConAtto[SezioneF24::erario->value], $sezioniConAtto[SezioneF24::accise->value])) {
            $esito->errori[] = 'Codice ufficio e codice atto si indicano una sola volta per modello, nella sezione Erario oppure nella sezione accise: dividere i versamenti in due deleghe.';
        }
    }

    /**
     * Sezione in cui il modello riporta codice ufficio e codice atto: accise
     * se lo richiedono i codici di quella sezione, altrimenti Erario.
     *
     * @param Versamento[] $righe
     */
    public static function getSezioneCodiceAtto(array $righe): SezioneF24
    {
        foreach ($righe as $riga) {
            if (($riga->sezione === SezioneF24::accise) && (($riga->codiceTributo?->codiceUfficio->isAmmesso() === true) || ($riga->codiceTributo?->codiceAtto->isAmmesso() === true))) {
                return SezioneF24::accise;
            }
        }
        return SezioneF24::erario;
    }

    /**
     * Un credito IMU richiede debiti IMU dello stesso comune almeno pari
     * (nota delle specifiche tecniche).
     *
     * @param Versamento[] $righe
     */
    /**
     * I crediti certificati verso la PA (PPAA) pagano solo i tributi e i
     * contributi dell'elenco del D.M. 14/1/2014, nella stessa delega.
     *
     * @param Versamento[] $righe
     */
    private function verificaCreditiPa(array $righe, EsitoControllo $esito): void
    {
        $crediti = 0;
        $debitiAmmessi = 0;
        foreach ($righe as $riga) {
            if ($riga->codiceTributo?->isCreditoPa() === true) {
                $crediti += EsitoControllo::centesimi($riga->importoCredito);
            } elseif ($this->isCompensabileCreditiPa($riga)) {
                $debitiAmmessi += EsitoControllo::centesimi($riga->importoDebito);
            }
        }
        if ($crediti > $debitiAmmessi) {
            $esito->errori[] = sprintf('I crediti verso la PA (codice %s) di € %s superano i debiti pagabili con essi (€ %s): sono ammessi solo i tributi dell\'elenco del D.M. 14/1/2014.',
                    CodiceTributo::CODICE_CREDITI_PA, EsitoControllo::euro($crediti), EsitoControllo::euro($debitiAmmessi));
        }
    }

    public function isCompensabileCreditiPa(Versamento $riga): bool
    {
        if ($riga->importoDebito <= 0) {
            return false;
        }
        if ($riga->codiceTributo !== null) {
            return $riga->codiceTributo->compensabileCreditiPa;
        }
        return ($riga->sezione === SezioneF24::inps) && ($this->causali->getCausale(CausaleContributo::ENTE_INPS, $riga->causale)?->compensabileCreditiPa === true);
    }

    /**
     * Utilizzi dei crediti dell'archivio: credito del contribuente,
     * utilizzabile alla data di versamento e con residuo non negativo.
     *
     * @param Versamento[] $righe
     */
    private function verificaCreditiArchivio(Delega $delega, array $righe, EsitoControllo $esito): void
    {
        $creditoModel = new CreditoModel($this->dataMapper);
        $verificati = [];
        foreach ($righe as $riga) {
            $credito = $riga->credito;
            if (($credito === null) || isset($verificati[$credito->id])) {
                continue;
            }
            $verificati[$credito->id] = true;
            if ($credito->soggetto->id !== $delega->soggetto->id) {
                $esito->errori[] = 'Il credito ' . $credito->getEtichetta() . ' non è del contribuente della delega.';
            }
            if ($credito->isUtilizzabile($delega->scadenza) === false) {
                $esito->errori[] = 'Il credito ' . $credito->getEtichetta() . ' è utilizzabile dal ' . $credito->utilizzabileDal->format('d/m/Y') . '.';
            }
            $residuo = $creditoModel->getResiduo($credito);
            if ($residuo < 0) {
                $esito->errori[] = sprintf('Gli utilizzi del credito %s superano l\'importo del credito di € %s.', $credito->getEtichetta(), EsitoControllo::euro(-$residuo));
            }
        }
    }

    private function verificaCreditiImu(array $righe, EsitoControllo $esito): void
    {
        $saldi = [];
        foreach ($righe as $riga) {
            if (($riga->sezione === SezioneF24::tributiLocali) && ($riga->codiceTributo?->isImu() === true)) {
                $saldi[$riga->codiceEnte] = ($saldi[$riga->codiceEnte] ?? 0) + EsitoControllo::centesimi($riga->importoDebito) - EsitoControllo::centesimi($riga->importoCredito);
            }
        }
        foreach ($saldi as $comune => $saldo) {
            if ($saldo < 0) {
                $esito->errori[] = 'I crediti IMU del comune ' . $comune . ' superano i debiti IMU dello stesso comune.';
            }
        }
    }

    private function verificaSaldo(Delega $delega, array $righePerSezione, EsitoControllo $esito): void
    {
        if (($esito->debito === 0) && ($esito->credito === 0)) {
            $esito->errori[] = 'La delega non contiene importi.';
            return;
        }
        if ($esito->getSaldo() < 0) {
            $esito->errori[] = 'I crediti compensati superano i debiti di € ' . EsitoControllo::euro(-$esito->getSaldo()) . ': il saldo finale non può essere negativo.';
        } elseif ($esito->credito > 0) {
            $esito->avvisi[] = $this->getAvvisoCompensazioni($righePerSezione, $esito);
        }
        if (array_filter($righePerSezione[SezioneF24::tributiLocali->value], fn(Versamento $riga) => ($riga->importoDebito > 0) && ($riga->ravvedimento === false)
                        && ($riga->codiceTributo?->isImu() === true) && ($riga->acconto === false) && ($riga->saldo === false))) {
            $esito->avvisi[] = 'Sezione IMU: barrare acconto e/o saldo per i versamenti dell\'imposta.';
        }
        if ($delega->codiceIdentificativo?->isSoloServiziAgenzia() === true) {
            $esito->avvisi[] = 'Il codice identificativo ' . $delega->codiceIdentificativo->value . ' è ammesso solo con i servizi telematici dell\'Agenzia delle Entrate.';
        }
        if ($delega->isModificabile() && ($delega->scadenza->format('Y-m-d') < (new SismaDate())->format('Y-m-d'))) {
            $esito->avvisi[] = 'Scadenza superata: per il versamento tardivo valutare il ravvedimento operoso.';
        }
    }

    /**
     * Canale di presentazione con compensazioni: i crediti erariali,
     * regionali, INPS e INAIL vanno compensati esclusivamente con i servizi
     * telematici dell'Agenzia (art. 37, c. 49-bis, D.L. 223/2006, dal
     * 1/7/2024 anche INPS e INAIL); a saldo zero sempre (art. 11, c. 2,
     * D.L. 66/2014).
     */
    private function getAvvisoCompensazioni(array $righePerSezione, EsitoControllo $esito): string
    {
        $creditiAgenzia = false;
        foreach ([SezioneF24::erario, SezioneF24::regioni, SezioneF24::inps, SezioneF24::inail] as $sezione) {
            $creditiAgenzia = $creditiAgenzia || ($esito->totaliSezione[$sezione->value]['credito'] > 0);
        }
        return match (true) {
            $esito->getSaldo() === 0 => 'Delega a saldo zero: va presentata con i servizi telematici dell\'Agenzia delle Entrate (Entratel/Fisconline).',
            $creditiAgenzia => 'Delega con compensazioni di crediti erariali, regionali, INPS o INAIL: va presentata con i servizi telematici dell\'Agenzia delle Entrate (art. 37, c. 49-bis, D.L. 223/2006).',
            default => 'Delega con compensazioni: va presentata con i servizi telematici dell\'Agenzia delle Entrate o della banca (home banking).',
        };
    }

    /**
     * Conto di addebito per l'invio telematico: intestato al contribuente o,
     * per i codici identificativi che lo richiedono, al secondo soggetto.
     */
    private function verificaAddebito(Delega $delega, EsitoControllo $esito): void
    {
        if ($esito->getSaldo() <= 0) {
            return;
        }
        $conto = $delega->contoAddebito;
        if ($conto === null) {
            $esito->avvisi[] = 'Nessun conto di addebito: necessario per l\'invio telematico con addebito sul conto del contribuente.';
            return;
        }
        $titolare = $delega->codiceIdentificativo?->isAddebitoSulSecondoSoggetto() === true ? $delega->codiceFiscaleCoobbligato : $delega->soggetto->codiceFiscale;
        if ($conto->soggetto->codiceFiscale !== $titolare) {
            $esito->errori[] = 'Il conto di addebito deve essere intestato a ' . ($titolare === $delega->soggetto->codiceFiscale ? 'il contribuente' : 'il secondo soggetto (' . $titolare . ')') . '.';
        }
    }

    /**
     * Soggetto dello studio con il codice fiscale indicato (es. il versante
     * per i codici identificativi 02, 03 e 07).
     */
    public function trovaSoggetto(Delega $delega, string $codiceFiscale): ?Soggetto
    {
        return (new SoggettoModel($this->dataMapper))->getByStudioAndCodiceFiscale($delega->studio, $codiceFiscale)[0] ?? null;
    }
}
