<?php

namespace Deleghe\Application\Services\Telematico;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\HelperClasses\CodiceFiscale;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Services\ControlloDelega;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\EsitoControllo;
use Deleghe\Application\Services\ModelloCompilato;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Flusso telematico F24 per Entratel secondo le specifiche tecniche per gli
 * intermediari (TracciatoF24): un record A con i dati dell'intermediario,
 * per ogni delega un record M (contribuente, versante o coobbligato, conto
 * di addebito, saldo e data) seguito da un record V per ogni modello della
 * ripartizione, un record Z di chiusura.
 *
 * Addebito: sul conto di ciascun contribuente (flag "E") o, con addebito
 * unico, sul conto dell'intermediario (flag "Y", provvedimento del 21 giugno
 * 2007 prot. 2007/101553). Il file va poi controllato e autenticato con il
 * software dell'Agenzia e trasmesso con Entratel.
 */
class GeneratoreFileF24
{

    public const MASSIMO_DELEGHE = 999;
    private const TIPO_PERSONA_FISICA = '04';
    private const TIPO_PERSONA_NON_FISICA = '14';
    /**
     * Codici identificativi gestiti dal "tipo versante" del record M (campo
     * 13) per compatibilità con le versioni precedenti.
     */
    private const TIPI_VERSANTE = ['02' => 2, '03' => 3, '07' => 7];

    private ControlloDelega $controllo;
    private DatiContribuente $datiContribuente;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {
        $this->controllo = new ControlloDelega($dataMapper);
        $this->datiContribuente = new DatiContribuente($dataMapper);
    }

    /**
     * Problemi che impediscono di inserire l'intermediario come fornitore.
     *
     * @return string[]
     */
    public function verificaIntermediario(Intermediario $intermediario, bool $addebitoIntermediario): array
    {
        $soggetto = $intermediario->soggetto;
        $errori = [];
        if ((CodiceFiscale::isValidoPersonaFisica($soggetto->codiceFiscale) === false) && (CodiceFiscale::isValidoNumerico($soggetto->codiceFiscale) === false)) {
            $errori[] = 'Codice fiscale dell\'intermediario non valido.';
        }
        foreach (DatiContribuente::getDatiMancanti($soggetto) as $mancante) {
            $errori[] = 'Intermediario: manca ' . $mancante . ' in anagrafica.';
        }
        if ($addebitoIntermediario && (($intermediario->contoAddebito === null) || ($intermediario->contoAddebito->isItaliano() === false))) {
            $errori[] = 'Per l\'addebito sul conto dell\'intermediario indicare un conto italiano nelle impostazioni dell\'intermediario.';
        }
        return $errori;
    }

    /**
     * Problemi che impediscono di inserire la delega nel flusso.
     *
     * @return string[]
     */
    public function verificaDelega(Delega $delega, EsitoControllo $esito, bool $addebitoIntermediario): array
    {
        $errori = [];
        if ($delega->stato !== StatoDelega::confermata) {
            $errori[] = 'La delega non è confermata.';
        }
        if ($esito->isConfermabile() === false) {
            $errori[] = 'La delega contiene errori di compilazione.';
        }
        $soggetto = $delega->soggetto;
        if ((CodiceFiscale::isValidoPersonaFisica($soggetto->codiceFiscale) === false) && (CodiceFiscale::isValidoNumerico($soggetto->codiceFiscale) === false)) {
            $errori[] = 'Codice fiscale del contribuente non valido.';
        }
        foreach (DatiContribuente::getDatiMancanti($soggetto) as $mancante) {
            $errori[] = 'Contribuente: manca ' . $mancante . ' in anagrafica.';
        }
        $tipoVersante = self::TIPI_VERSANTE[$delega->codiceIdentificativo?->value ?? ""] ?? null;
        if ($tipoVersante !== null) {
            $versante = $this->controllo->trovaSoggetto($delega, $delega->codiceFiscaleCoobbligato);
            if (($versante === null) || ($versante->isPersonaFisica() === false)) {
                $errori[] = 'Il versante (' . $delega->codiceIdentificativo->getLabel() . ') deve essere una persona fisica in anagrafica con codice fiscale ' . $delega->codiceFiscaleCoobbligato . '.';
            } else {
                foreach (DatiContribuente::getDatiMancanti($versante) as $mancante) {
                    $errori[] = 'Versante: manca ' . $mancante . ' in anagrafica.';
                }
            }
        }
        if (($esito->getSaldo() > 0) && ($addebitoIntermediario === false) && (($delega->contoAddebito === null) || ($delega->contoAddebito->isItaliano() === false))) {
            $errori[] = 'Indicare nella delega il conto italiano di addebito.';
        }
        return $errori;
    }

    /**
     * @param array<array{delega: Delega, esito: EsitoControllo}> $voci deleghe verificate
     */
    public function genera(Intermediario $intermediario, array $voci, bool $addebitoIntermediario): string
    {
        if ((count($voci) === 0) || (count($voci) > self::MASSIMO_DELEGHE)) {
            throw new \InvalidArgumentException('Il flusso deve contenere da 1 a ' . self::MASSIMO_DELEGHE . ' deleghe.');
        }
        $file = (string) $this->recordA($intermediario, count($voci), $addebitoIntermediario);
        $recordV = 0;
        foreach (array_values($voci) as $indice => ['delega' => $delega, 'esito' => $esito]) {
            $progressivo = $indice + 1;
            $file .= $this->recordM($delega, $esito, $progressivo, $intermediario, $addebitoIntermediario);
            foreach ($esito->modelli as $modello) {
                $file .= $this->recordV($delega, $modello, $progressivo);
                $recordV++;
            }
        }
        $file .= (new RecordF24(TracciatoF24::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordV', $recordV)->imposta('numeroRecordM', count($voci));
        return $file;
    }

    private function recordA(Intermediario $intermediario, int $deleghe, bool $addebitoIntermediario): RecordF24
    {
        $soggetto = $intermediario->soggetto;
        $record = (new RecordF24(TracciatoF24::RECORD_A))
                ->imposta('tipoRecord', 'A')
                ->imposta('codiceFornitura', 'F24A0')
                ->imposta('tipoFornitore', $soggetto->isPersonaFisica() ? self::TIPO_PERSONA_FISICA : self::TIPO_PERSONA_NON_FISICA)
                ->imposta('codiceFiscaleFornitore', $soggetto->codiceFiscale)
                ->imposta('flagOrigine', $addebitoIntermediario ? 'Y' : 'E')
                ->imposta('telefono', $intermediario->telefono)
                ->impostaTroncato('email', $intermediario->email)
                ->imposta('progressivoInvio', 1)
                ->imposta('numeroInvii', $deleghe)
                ->imposta('flagAccettazione', $addebitoIntermediario ? '1' : '');
        $domicilio = $this->datiContribuente->getDomicilio($soggetto, new \DateTimeImmutable());
        if ($soggetto->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($soggetto);
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome)
                    ->imposta('sesso', $soggetto->sesso->value)
                    ->imposta('dataNascita', $soggetto->dataNascita->format('dmY'))
                    ->impostaTroncato('comuneNascita', $comune)
                    ->imposta('provinciaNascita', $provincia);
            $this->impostaDomicilio($record, $domicilio, 'comuneDomicilioPf', 'provinciaDomicilioPf', 'indirizzoDomicilioPf', 'capDomicilioPf');
        } else {
            $record->impostaTroncato('denominazione', $soggetto->nominativo);
            $this->impostaDomicilio($record, $domicilio, 'comuneDomicilioPnf', 'provinciaDomicilioPnf', 'indirizzoDomicilioPnf', 'capDomicilioPnf');
        }
        return $record;
    }

    private function impostaDomicilio(RecordF24 $record, ?\Anagrafica\Application\Entities\Indirizzo $indirizzo, string $comune, string $provincia, string $via, string $cap): void
    {
        if (($indirizzo === null) || ($indirizzo->comune === null)) {
            return;
        }
        $record->impostaTroncato($comune, $indirizzo->comune->denominazione)
                ->imposta($provincia, $indirizzo->comune->siglaProvincia)
                ->impostaTroncato($via, $indirizzo->getRiga1())
                ->imposta($cap, ctype_digit((string) $indirizzo->cap) ? $indirizzo->cap : 0);
    }

    private function recordM(Delega $delega, EsitoControllo $esito, int $progressivo, Intermediario $intermediario, bool $addebitoIntermediario): RecordF24
    {
        $soggetto = $delega->soggetto;
        $record = (new RecordF24(TracciatoF24::RECORD_M))
                ->imposta('tipoRecord', 'M')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', $progressivo)
                ->imposta('identificazioneModello', 'DELEGA ' . $delega->id)
                ->imposta('valutaDelega', 'E')
                ->imposta('valuta', 'EURO')
                ->imposta('saldoTotale', self::importoTesto($esito->getSaldo()))
                ->imposta('dataVersamento', $delega->scadenza->format('d-m-Y'));
        if ($soggetto->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($soggetto);
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome)
                    ->imposta('dataNascita', $soggetto->dataNascita->format('dmY'))
                    ->imposta('sesso', $soggetto->sesso->value)
                    ->impostaTroncato('comuneNascita', $comune)
                    ->imposta('provinciaNascita', $provincia);
        } else {
            $record->impostaTroncato('denominazione', $soggetto->nominativo);
        }
        $this->impostaDomicilio($record, $this->datiContribuente->getDomicilio($soggetto, $delega->scadenza), 'comuneDomicilio', 'provinciaDomicilio', 'indirizzoDomicilio', 'capDomicilio');
        $this->impostaSecondoSoggetto($record, $delega);
        if ($esito->getSaldo() > 0) {
            $conto = $addebitoIntermediario ? $intermediario->contoAddebito : $delega->contoAddebito;
            $titolare = $conto->soggetto->codiceFiscale;
            $record->imposta('iban', $conto->iban)
                    ->imposta('tipoTitolareConto', strlen($titolare) === 16 ? self::TIPO_PERSONA_FISICA : self::TIPO_PERSONA_NON_FISICA)
                    ->imposta('codiceFiscaleTitolareConto', $addebitoIntermediario ? $intermediario->soggetto->codiceFiscale : $titolare);
        }
        return $record;
    }

    /**
     * Genitore/tutore, curatore ed erede (codici 02, 03, 07) sono indicati
     * come versante con i dati anagrafici; gli altri codici come coobbligato.
     */
    private function impostaSecondoSoggetto(RecordF24 $record, Delega $delega): void
    {
        $codice = $delega->codiceIdentificativo;
        if ($codice === null) {
            return;
        }
        $tipoVersante = self::TIPI_VERSANTE[$codice->value] ?? null;
        if ($tipoVersante === null) {
            $record->imposta('codiceIdentificativo', $codice->value)->imposta('codiceFiscaleCoobbligato', $delega->codiceFiscaleCoobbligato);
            return;
        }
        $versante = $this->controllo->trovaSoggetto($delega, $delega->codiceFiscaleCoobbligato);
        [$comune, $provincia] = DatiContribuente::getLuogoNascita($versante);
        $record->imposta('flagVersante', 1)
                ->imposta('codiceFiscaleVersante', $versante->codiceFiscale)
                ->imposta('tipoVersante', $tipoVersante)
                ->impostaTroncato('cognomeVersante', $versante->cognome)
                ->impostaTroncato('nomeVersante', $versante->nome)
                ->imposta('sessoVersante', $versante->sesso->value)
                ->imposta('dataNascitaVersante', $versante->dataNascita->format('dmY'))
                ->impostaTroncato('comuneNascitaVersante', $comune)
                ->imposta('provinciaNascitaVersante', $provincia);
    }

    private function recordV(Delega $delega, ModelloCompilato $modello, int $progressivo): RecordF24
    {
        $elide = $delega->modello === ModelloF24::elide;
        $accise = $delega->modello === ModelloF24::accise;
        $sezioneAtto = ControlloDelega::getSezioneCodiceAtto(array_merge(...array_values(array_map(fn($voci) => array_column($voci, 'riga'), $modello->righe))));
        $record = (new RecordF24(TracciatoF24::recordV($delega->modello)))
                ->imposta('tipoRecord', 'V')
                ->imposta('codiceFiscale', $delega->soggetto->codiceFiscale)
                ->imposta('progressivoModulo', $progressivo)
                ->imposta('identificazioneModello', 'DELEGA ' . $delega->id)
                ->imposta('tipoModello', $delega->modello->getTipoTelematico())
                ->imposta($sezioneAtto === SezioneF24::accise ? 'ACC.codiceUfficio' : 'codiceUfficio', $delega->codiceUfficio)
                ->imposta($sezioneAtto === SezioneF24::accise ? 'ACC.codiceAtto' : 'codiceAtto', $delega->codiceAtto ?? 0)
                ->imposta('saldoFinale', $modello->getSaldo())
                ->imposta('dataVersamento', $delega->scadenza->format('dmY'));
        if ($elide) {
            $this->sezioneElide($record, $modello);
            return $record;
        }
        foreach (TracciatoF24::SEZIONI_V as $codiceSezione => $definizione) {
            $sezione = SezioneF24::from($codiceSezione);
            if ($delega->modello->ammetteSezione($sezione) === false) {
                continue;
            }
            foreach ($modello->righe[$codiceSezione] as $indice => $voce) {
                $prefisso = $codiceSezione . '.' . ($indice + 1) . '.';
                foreach ($this->campiRigo($sezione, $voce, $delega, $modello, $indice) as $campo => $valore) {
                    $record->imposta($prefisso . $campo, $valore);
                }
            }
            $this->totaliSezione($record, $codiceSezione, $modello->getTotaliSezione($sezione), count($modello->righe[$codiceSezione]) > 0);
        }
        if ($modello->contaRighe(SezioneF24::tributiLocali) > 0) {
            $record->imposta('LOC.identificativoOperazione', $delega->identificativoOperazione);
        }
        if ($accise) {
            $this->sezioneAccise($record, $modello);
            return $record;
        }
        $record->imposta('PRE.codiceEnte', $modello->getCodiceAltroEnte() ?? 0);
        return $record;
    }

    /**
     * Sezione accise: ente del codice tributo, provincia, codice
     * identificativo, rateazione e periodo (MMAAAA o 00AAAA), solo debito.
     */
    private function sezioneAccise(RecordF24 $record, ModelloCompilato $modello): void
    {
        foreach ($modello->righe[SezioneF24::accise->value] as $indice => $voce) {
            $riga = $voce['riga'];
            $prefisso = 'ACC.' . ($indice + 1) . '.';
            $record->imposta($prefisso . 'ente', $riga->codiceTributo->enteAccise)
                    ->imposta($prefisso . 'provincia', $riga->provincia)
                    ->imposta($prefisso . 'codiceTributo', $riga->codiceTributo->codice)
                    ->imposta($prefisso . 'codiceIdentificativo', $riga->codiceIdentificativoEnte)
                    ->imposta($prefisso . 'rateazione', $riga->rateazione ?? '0000')
                    ->imposta($prefisso . 'periodo', sprintf('%02d%04d', $riga->meseRiferimento ?? 0, $riga->annoRiferimento ?? 0))
                    ->imposta($prefisso . 'debito', $voce['debito']);
        }
        $totale = $modello->getTotaliSezione(SezioneF24::accise)['debito'];
        $usata = count($modello->righe[SezioneF24::accise->value]) > 0;
        $record->imposta('ACC.totaleDebito', $totale)->imposta('ACC.segno', $usata ? 'P' : '')->imposta('ACC.saldo', $totale);
    }

    /**
     * @param array{riga: \Deleghe\Application\Entities\Versamento, debito: int, credito: int} $voce
     */
    private function campiRigo(SezioneF24 $sezione, array $voce, Delega $delega, ModelloCompilato $modello, int $indice): array
    {
        $riga = $voce['riga'];
        $importi = ['debito' => $voce['debito'], 'credito' => $voce['credito']];
        $rateazione = $riga->rateazione ?? '0000';
        $anno = $riga->annoRiferimento ?? 0;
        return $importi + match ($sezione) {
            SezioneF24::erario => ['codiceTributo' => $riga->codiceTributo->codice, 'numeroCertificazione' => $riga->numeroCertificazione, 'rateazione' => $rateazione, 'anno' => $anno],
            SezioneF24::inps => ['codiceSede' => $riga->codiceSede, 'causale' => $riga->causale, 'matricola' => $riga->posizione,
                'periodoDa' => self::periodo($riga->periodoDa), 'periodoA' => self::periodo($riga->periodoA)],
            SezioneF24::regioni => ['codiceRegione' => $riga->codiceEnte, 'codiceTributo' => $riga->codiceTributo->codice, 'rateazione' => $rateazione, 'anno' => $anno],
            SezioneF24::tributiLocali => ['codiceEnte' => $riga->codiceEnte, 'ravvedimento' => (int) $riga->ravvedimento, 'immobiliVariati' => (int) $riga->immobiliVariati,
                'acconto' => (int) $riga->acconto, 'saldo' => (int) $riga->saldo, 'numeroImmobili' => $riga->numeroImmobili ?? 0,
                'detrazione' => ($modello->conDetrazioneImu && ($indice === 0)) ? EsitoControllo::centesimi((float) $delega->detrazioneImu) : 0,
                'codiceTributo' => $riga->codiceTributo->codice, 'rateazione' => $rateazione, 'anno' => $anno],
            SezioneF24::inail => ['codiceSede' => $riga->codiceSede, 'codiceDitta' => $riga->posizione, 'cifraControllo' => $riga->cifraControllo,
                'numeroRiferimento' => $riga->numeroRiferimento, 'causale' => $riga->causale],
            SezioneF24::altriEnti => ['codiceSede' => $riga->codiceSede, 'causale' => $riga->causale, 'codicePosizione' => $riga->posizione ?? 0,
                'periodoDa' => self::periodo($riga->periodoDa), 'periodoA' => self::periodo($riga->periodoA)],
            SezioneF24::elide, SezioneF24::accise => [],
        };
    }

    private function sezioneElide(RecordF24 $record, ModelloCompilato $modello): void
    {
        foreach ($modello->righe[SezioneF24::elide->value] as $indice => $voce) {
            $riga = $voce['riga'];
            $prefisso = 'ELI.' . ($indice + 1) . '.';
            $record->imposta($prefisso . 'tipo', $riga->tipoElide)
                    ->imposta($prefisso . 'elementiIdentificativi', $riga->elementiIdentificativi)
                    ->imposta($prefisso . 'codiceTributo', $riga->codiceTributo->codice)
                    ->imposta($prefisso . 'anno', $riga->annoRiferimento ?? 0)
                    ->imposta($prefisso . 'debito', $voce['debito']);
        }
        $totale = $modello->getDebito();
        $record->imposta('ELI.totaleDebito', $totale)->imposta('ELI.segno', 'P')->imposta('ELI.saldo', $totale);
    }

    /**
     * Totali e saldo della sezione; segno: spazio se la sezione non è
     * usata, N se il credito supera il debito, P altrimenti.
     */
    private function totaliSezione(RecordF24 $record, string $sezione, array $totali, bool $usata): void
    {
        $record->imposta($sezione . '.totaleDebito', $totali['debito'])
                ->imposta($sezione . '.totaleCredito', $totali['credito'])
                ->imposta($sezione . '.segno', $usata ? ($totali['credito'] > $totali['debito'] ? 'N' : 'P') : '')
                ->imposta($sezione . '.saldo', abs($totali['debito'] - $totali['credito']));
    }

    private static function periodo(?string $periodo): string
    {
        return $periodo === null ? '0' : str_replace('/', '', $periodo);
    }

    /**
     * Saldo del record M: separatori delle migliaia e virgola decimale
     * ("0,00" se zero); oltre 15 caratteri senza separatori delle migliaia.
     */
    private static function importoTesto(int $centesimi): string
    {
        $testo = number_format($centesimi / 100, 2, ',', '.');
        return strlen($testo) > 15 ? number_format($centesimi / 100, 2, ',', '') : $testo;
    }
}
