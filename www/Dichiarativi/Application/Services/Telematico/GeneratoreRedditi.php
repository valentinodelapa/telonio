<?php

namespace Dichiarativi\Application\Services\Telematico;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Contabilita\Application\Models\EsercizioContabileModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Isa\CalcoloIsa;
use Dichiarativi\Application\Services\Isa\ConcordatoIsa;
use Dichiarativi\Application\Services\Isa\ModelliIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\ControlliRedditi;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Fornitura telematica dei modelli Redditi: PF ("RPF26"), SP ("RSP26"),
 * SC ("RSC26") ed ENC ("RNC26"), questi tre con il frontespizio in
 * recordBSocieta. Per Redditi PF: record A, B
 * (frontespizio: dati anagrafici, residenza variata nell'anno, domicilio
 * fiscale al 1° gennaio dell'anno d'imposta e del successivo, caselle dei
 * quadri compilati, impegno alla trasmissione e visto), record C dei quadri
 * per codice (un progressivo per modulo) e Z.
 *
 * Le scelte per la destinazione dell'otto, del cinque e del due per mille
 * non sono gestite: risultano "non effettuate".
 *
 * Comunicazione del concordato preventivo biennale 2026-2027 (casella
 * "Comunicazione CPB", campo 11 del record B; provvedimento del 27
 * febbraio 2026, punti 4 e 5; specifiche Redditi PF, par. 43): con la
 * dichiarazione la revoca delle adesioni già comunicate (codice 3, con
 * l'eventuale nuova adesione nel modello ISA allegato); con invio autonomo
 * il solo frontespizio, senza le sezioni escluse (domicilio fiscale,
 * scelte, caselle dei quadri, visto), e il modello CPB nei record del
 * modello ISA: per l'adesione (codice 1) esclusivamente il quadro P, con la
 * proposta del calcolo aggiornato e l'accettazione, per la revoca (codice
 * 2) i soli campi chiave del quadro P. L'invio autonomo non è una dichiarazione.
 */
class GeneratoreRedditi
{

    /** Valori della casella "Comunicazione CPB" */
    public const CPB_ADESIONE = 1;
    public const CPB_REVOCA = 2;
    public const CPB_REVOCA_CON_DICHIARAZIONE = 3;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \InvalidArgumentException se il modello ha errori o segnalazioni non confermate
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $controlli = new ControlliRedditi($this->dataMapper);
        $errori = $controlli->getErrori($dichiarazione, $quadri);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
        $segnalazioni = $controlli->getSegnalazioni($quadri);
        $segnalazioniIsa = $controlli->getSegnalazioniIsa($dichiarazione);
        if ((count($segnalazioni) > 0) && ($dichiarazione->flagConferma === false)) {
            throw new \InvalidArgumentException('Dati non coerenti (' . implode(' ', $segnalazioni) . '): correggerli o confermarli nel frontespizio.');
        }
        $valori = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                // codici attività ATECO senza i punti (come nelle tabelle delle specifiche, es. "282920")
                $valori[$modulo][$codice] = in_array($codice, TracciatoRedditi::CODICI_ATTIVITA, true) ? str_replace('.', '', (string) $dati['valore']) : $dati['valore'];
            }
        }
        $recordC = RecordQuadri::genera('C', $dichiarazione->soggetto->codiceFiscale, $valori, $quadri->getCatalogo());
        $recordIsa = $this->getRecordIsa(array_map(fn(Dichiarazione $isa) => (string) $isa->calcoloIsa, (new ModelliIsa($this->dataMapper))->getDichiarazioni($dichiarazione)));
        $recordB = $this->recordB($dichiarazione, $quadri, count($recordIsa['P']) > 0, count($segnalazioniIsa) > 0, count($segnalazioni) > count($segnalazioniIsa),
                $dichiarazione->revocaCpb ? self::CPB_REVOCA_CON_DICHIARAZIONE : null);
        return $this->fornitura($dichiarazione, $recordB, $recordC, $recordIsa);
    }

    /**
     * Comunicazione del concordato preventivo biennale con invio autonomo
     * (codice 1 adesione o 2 revoca): frontespizio della dichiarazione
     * Redditi collegata al modello ISA e modello CPB.
     *
     * @throws \InvalidArgumentException se il modello non consente la comunicazione
     */
    public function generaComunicazioneCpb(Dichiarazione $isa, bool $revoca = false): string
    {
        $redditi = $isa->dichiarazioneRedditi ?? throw new \InvalidArgumentException('Modello ISA senza la dichiarazione Redditi collegata.');
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper);
        if ($revoca) {
            $chiavi = ConcordatoIsa::getChiavi($isa, $quadri);
            if ($chiavi['P0000002'] === '') {
                throw new \InvalidArgumentException('Codice attività del frontespizio del modello ISA (Y01) non indicato.');
            }
            $recordIsa = $this->getRecordIsa([(new GeneratoreIsa($this->dataMapper))->genera($isa, $quadri, null, $chiavi)]);
            $anomalie = false;
        } else {
            if (ConcordatoIsa::isAccettata($quadri) === false) {
                throw new \InvalidArgumentException('Proposta di concordato non accettata nel quadro P del modello ISA: aderire prima di comunicare l\'adesione.');
            }
            if ((new CalcoloIsa($this->dataMapper))->isAggiornato($isa, $quadri) === false) {
                throw new \InvalidArgumentException('Modello ISA da ricalcolare: la proposta accettata deve essere quella del calcolo aggiornato.');
            }
            // con il codice 1 il modello contiene esclusivamente il quadro P (controllo del software dell'Agenzia): chiavi, requisiti,
            // redditi rilevanti e proposti, accettazione
            $quadroP = ConcordatoIsa::getChiavi($isa, $quadri);
            foreach ($quadri->getValori()[1] ?? [] as $codice => $dati) {
                if (str_starts_with($codice, 'P') && ($dati['valore'] !== null) && ($dati['valore'] !== '')) {
                    $quadroP[$codice] = $dati['valore'];
                }
            }
            $recordIsa = $this->getRecordIsa([(new GeneratoreIsa($this->dataMapper))->genera($isa, $quadri, null, $quadroP)]);
            $anomalie = false;
        }
        $recordB = $this->recordB($redditi, new QuadriDichiarazione($redditi, $this->dataMapper), false, $anomalie, false, $revoca ? self::CPB_REVOCA : self::CPB_ADESIONE);
        return $this->fornitura($redditi, $recordB, [], $recordIsa);
    }

    public static function getNomeFileComunicazioneCpb(Dichiarazione $isa, bool $revoca = false): string
    {
        return substr(self::getNomeFile($isa->dichiarazioneRedditi), 0, -4) . '_CPB_' . ($revoca ? 'revoca' : 'adesione') . '.txt';
    }

    /**
     * Fornitura: record A, B, C, P, Q, R e Z.
     *
     * @param string[] $recordC
     * @param array{P: string[], Q: string[], R: string[]} $recordIsa
     */
    private function fornitura(Dichiarazione $dichiarazione, string $recordB, array $recordC, array $recordIsa): string
    {
        return implode('', [
            (string) (new RecordF24(TracciatoRedditi::RECORD_A))
                    ->imposta('tipoRecord', 'A')
                    ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno))
                    ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? 1 : 10)
                    ->imposta('codiceFiscaleFornitore', $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale),
            $recordB,
            ...$recordC,
            ...$recordIsa['P'],
            ...$recordIsa['Q'],
            ...$recordIsa['R'],
            (string) (new RecordF24(TracciatoRedditi::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordC', count($recordC))
                    ->imposta('numeroRecordP', count($recordIsa['P']))->imposta('numeroRecordQ', count($recordIsa['Q']))->imposta('numeroRecordR', count($recordIsa['R'])),
        ]);
    }

    /**
     * Record P, Q ed R dei modelli ISA, come restituiti dal software di
     * calcolo (CalcoloIsa) o generati (GeneratoreIsa), per tipo di record:
     * dopo i record C, ordinati per tipo record (specifiche Redditi PF, par.
     * 2.2).
     *
     * @param string[] $modelli record di ciascun modello ISA
     * @return array{P: string[], Q: string[], R: string[]}
     */
    private function getRecordIsa(array $modelli): array
    {
        $record = ['P' => [], 'Q' => [], 'R' => []];
        foreach ($modelli as $testo) {
            foreach (explode("\r\n", $testo) as $riga) {
                $tipo = substr($riga, 0, 1);
                if (isset($record[$tipo])) {
                    if (strlen($riga) !== GeneratoreIsa::LUNGHEZZA_RECORD - 2) {
                        throw new \LengthException(sprintf('Modello ISA %s: record %s di %d caratteri invece di %d.', trim(substr($riga, 103, 5)), $tipo, strlen($riga) + 2, GeneratoreIsa::LUNGHEZZA_RECORD));
                    }
                    $record[$tipo][] = $riga . "\r\n";
                }
            }
        }
        return $record;
    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d.txt', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale), $dichiarazione->anno);
    }

    /**
     * @param bool $anomalieIsa segnalazioni confermabili dei modelli ISA allegati
     * @param bool $anomalieRedditi segnalazioni confermabili della dichiarazione
     * @param int|null $comunicazioneCpb casella "Comunicazione CPB": con 1 o 2 (invio autonomo) solo le sezioni ammesse del frontespizio
     */
    private function recordB(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, bool $isa = false, bool $anomalieIsa = false, bool $anomalieRedditi = false, ?int $comunicazioneCpb = null): string
    {
        if ($dichiarazione->modello->isRedditiSocieta()) {
            return $this->recordBSocieta($dichiarazione, $quadri, $isa, $anomalieIsa, $comunicazioneCpb);
        }
        if ($dichiarazione->modello === ModelloDichiarativo::consolidato) {
            return $this->recordBConsolidato($dichiarazione, $quadri);
        }
        $autonoma = in_array($comunicazioneCpb, [self::CPB_ADESIONE, self::CPB_REVOCA], true);
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        [$comuneNascita, $provinciaNascita] = DatiContribuente::getLuogoNascita($soggetto);
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        $campi = TracciatoRedditi::RECORD_B;
        foreach (TracciatoRedditi::NUMERICI_B as $posizione => $lunghezza) {
            $campi['numerico' . $posizione] = [$posizione, $lunghezza, 'NU'];
        }
        $record = (new RecordF24($campi))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                // conferma delle anomalie (campo 8, anche per quelle dei modelli ISA allegati, che sono anomalie della dichiarazione) e,
                // per i modelli ISA, anche il campo 9 "Flag conferma Studi di settore/parametri"
                ->imposta('flagConferma', (int) ($dichiarazione->flagConferma && (($autonoma === false) || $anomalieIsa)))
                ->imposta('flagConfermaIsa', (int) ($dichiarazione->flagConferma && $anomalieIsa))
                ->imposta('comunicazioneCpb', $comunicazioneCpb)
                ->impostaTroncato('cognome', $soggetto->cognome)
                ->impostaTroncato('nome', $soggetto->nome)
                ->imposta('partitaIva', $datiFiscali?->partitaIva)
                ->imposta('telefono', $recapiti['telefono'])
                ->impostaTroncato('email', $recapiti['email'])
                ->impostaTroncato('comuneNascita', $comuneNascita)
                ->imposta('provinciaNascita', $provinciaNascita === 'EE' ? '' : $provinciaNascita)
                ->imposta('dataNascita', $soggetto->dataNascita?->format('dmY'))
                ->imposta('sesso', $soggetto->sesso?->value)
                ->imposta('firmaDichiarante', 1);
        $this->impostaResidenza($record, $dichiarazione);
        if ($dichiarazione->dichiarante !== null) {
            $record->imposta('codiceFiscaleRappresentante', $dichiarazione->dichiarante->codiceFiscale)
                    ->imposta('codiceCaricaRappresentante', $dichiarazione->codiceCarica?->value);
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIncaricato', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno?->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno?->format('dmY'))
                    ->imposta('firmaIncaricato', 1);
        }
        if ($autonoma) {
            // invio autonomo della comunicazione: solo frontespizio, senza le sezioni escluse (specifiche, par. 43.1)
            return (string) $record;
        }
        $record->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva));
        $this->impostaScelte($record, $dichiarazione);
        if ($isa) {
            $record->imposta('isa', 1);
        }
        // omessa presentazione del modello ISA scelta nel frontespizio (modello non allegato): casella del quadro RE (campo
        // 179) o dei quadri RF e RG (campo 180) senza causa di esclusione (controlli dei campi RE001002, RF001002 e RG001002)
        if ($dichiarazione->omessoIsa) {
            foreach (['RE' => 'assenzaIsaQuadroH', 'RF' => 'assenzaIsaQuadroF', 'RG' => 'assenzaIsaQuadroF'] as $quadro => $campo) {
                if (($quadri->get($quadro . '001001', 1) !== null) && in_array($quadri->getImporto($quadro . '001002', 1), [0, 7, 14], true)) {
                    $record->imposta($campo, 1);
                }
            }
        }
        foreach ([[$anno, 'comuneDomicilio', 'provinciaDomicilio', 'codiceComuneDomicilio'], [$anno + 1, 'comuneDomicilioSuccessivo', 'provinciaDomicilioSuccessivo', 'codiceComuneDomicilioSuccessivo']] as [$annoDomicilio, $campoComune, $campoProvincia, $campoCodice]) {
            $comune = VersamentiRedditi::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($annoDomicilio . '-01-01'));
            if ($comune === null) {
                if ($annoDomicilio === $anno) {
                    throw new \InvalidArgumentException('Domicilio fiscale al 1° gennaio ' . $anno . ' assente nell\'Anagrafica (indirizzo di residenza o di domicilio fiscale).');
                }
                continue;
            }
            $record->impostaTroncato($campoComune, $comune->denominazione)
                    ->imposta($campoProvincia, $comune->siglaProvincia)
                    ->imposta($campoCodice, $comune->codiceCatastale);
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $record->imposta('codiceFiscaleProfessionista', $dichiarazione->vistoProfessionista->codiceFiscale)->imposta('firmaVisto', 1);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $record->imposta('codiceFiscaleResponsabileCaf', $dichiarazione->vistoResponsabileCaf?->codiceFiscale)
                    ->imposta('codiceFiscaleCaf', $dichiarazione->vistoCaf->codiceFiscale)
                    ->imposta('firmaVisto', 1);
        }
        $compilati = [];
        foreach ($quadri->getModuli() as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        foreach (array_unique($compilati) as $quadro) {
            if (isset(TracciatoRedditi::CASELLE_TESTA[$quadro])) {
                $record->imposta(TracciatoRedditi::CASELLE_TESTA[$quadro], 1);
            }
        }
        $testo = (string) $record;
        foreach (array_unique($compilati) as $quadro) {
            if (isset(TracciatoRedditi::CASELLE_QUADRI[$quadro])) {
                $testo = substr_replace($testo, '1', TracciatoRedditi::CASELLE_QUADRI[$quadro] - 1, 1);
            }
        }
        return $testo;
    }

    /**
     * Frontespizio dei modelli Redditi SP e SC (TracciatoRedditiSocieta):
     * denominazione, partita IVA e recapiti, periodo d'imposta (esercizio
     * contabile dell'anno, altrimenti l'anno solare), stato e situazione
     * ordinari (codici 1 e 6 delle tabelle A e C delle istruzioni), natura
     * giuridica, rappresentante firmatario con il codice di carica,
     * impegno alla trasmissione, visto, organo di controllo (solo SC, con la
     * firma della dichiarazione), caselle dei quadri compilati e del modello
     * ISA. Con la comunicazione del concordato con invio autonomo (codice 1
     * o 2) solo i campi ammessi (specifiche SC, campo B11): dati della
     * società, periodo, natura giuridica, rappresentante, impegno e firme.
     */
    private function recordBSocieta(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, bool $isa, bool $anomalieIsa, ?int $comunicazioneCpb): string
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $sc = $dichiarazione->modello === ModelloDichiarativo::redditiSc;
        $enc = $dichiarazione->modello === ModelloDichiarativo::redditiEnc;
        $autonoma = in_array($comunicazioneCpb, [self::CPB_ADESIONE, self::CPB_REVOCA], true);
        $campi = TracciatoRedditiSocieta::RECORD_B + match (true) {
            $sc => TracciatoRedditiSocieta::RECORD_B_SC,
            $enc => TracciatoRedditiSocieta::RECORD_B_ENC,
            default => TracciatoRedditiSocieta::RECORD_B_SP,
        };
        $numerici = match (true) {
            $sc => TracciatoRedditiSocieta::NUMERICI_B_SC,
            $enc => TracciatoRedditiSocieta::NUMERICI_B_ENC,
            default => TracciatoRedditiSocieta::NUMERICI_B_SP,
        };
        foreach ($numerici as $posizione => $lunghezza) {
            $campi['numerico' . $posizione] = [$posizione, $lunghezza, 'NU'];
        }
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $record = (new RecordF24($campi))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->imposta('flagConferma', (int) ($dichiarazione->flagConferma && (($autonoma === false) || $anomalieIsa)))
                ->imposta('flagConfermaIsa', (int) ($dichiarazione->flagConferma && $anomalieIsa))
                ->imposta('comunicazioneCpb', $comunicazioneCpb)
                ->impostaTroncato('denominazione', mb_strtoupper($soggetto->nominativo))
                ->imposta('partitaIva', $datiFiscali?->partitaIva)
                ->imposta('telefono', $recapiti['telefono'])
                ->impostaTroncato('email', $recapiti['email'])
                ->imposta('dataInizioPeriodo', ($esercizio?->dataInizio ?? new SismaDate($anno . '-01-01'))->format('dmY'))
                ->imposta('dataFinePeriodo', ($esercizio?->dataFine ?? new SismaDate($anno . '-12-31'))->format('dmY'))
                ->imposta('stato', 1)
                ->imposta('naturaGiuridica', $dichiarazione->naturaGiuridica)
                ->imposta('situazione', 6)
                ->imposta('firmaDichiarante', 1);
        $rappresentante = $dichiarazione->dichiarante;
        if ($rappresentante !== null) {
            $record->imposta('codiceFiscaleRappresentante', $rappresentante->codiceFiscale)
                    ->imposta('codiceCaricaRappresentante', $dichiarazione->codiceCarica?->value);
            if ($rappresentante->isPersonaFisica()) {
                [$comune, $provincia] = DatiContribuente::getLuogoNascita($rappresentante);
                $record->impostaTroncato('cognomeRappresentante', $rappresentante->cognome)
                        ->impostaTroncato('nomeRappresentante', $rappresentante->nome)
                        ->imposta('sessoRappresentante', $rappresentante->sesso?->value)
                        ->imposta('dataNascitaRappresentante', $rappresentante->dataNascita?->format('dmY'))
                        ->impostaTroncato('comuneNascitaRappresentante', $comune)
                        ->imposta('provinciaNascitaRappresentante', $provincia === 'EE' ? '' : $provincia);
            }
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIncaricato', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno?->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno?->format('dmY'))
                    ->imposta('firmaIncaricato', 1);
        }
        if ($autonoma) {
            return (string) $record;
        }
        $record->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->imposta('canoneRai', $dichiarazione->canoneRai?->value);
        if ($isa) {
            $record->imposta('isa', 1);
        }
        if ($comunicazioneCpb === self::CPB_REVOCA_CON_DICHIARAZIONE) {
            $record->imposta('comunicazioneCpb', $comunicazioneCpb);
        }
        // omessa presentazione del modello ISA: casella del quadro RE (solo SP) o dei quadri RF e RG
        if ($dichiarazione->omessoIsa) {
            foreach (['RE' => 'assenzaIsaQuadroH', 'RF' => 'assenzaIsaQuadroF', 'RG' => 'assenzaIsaQuadroF'] as $quadro => $campo) {
                if (isset($campi[$campo]) && ($quadri->get($quadro . '001001', 1) !== null)) {
                    $record->imposta($campo, 1);
                }
            }
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $record->imposta('codiceFiscaleProfessionista', $dichiarazione->vistoProfessionista->codiceFiscale)->imposta('firmaVisto', 1);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $record->imposta('codiceFiscaleResponsabileCaf', $dichiarazione->vistoResponsabileCaf?->codiceFiscale)
                    ->imposta('codiceFiscaleCaf', $dichiarazione->vistoCaf->codiceFiscale)
                    ->imposta('firmaVisto', 1);
        }
        $compilati = [];
        foreach ($quadri->getModuli() as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        foreach (['VO' => 'quadroVo', 'AC' => 'quadroAc', 'RW' => 'quadroRw', 'GN' => 'consolidato', 'TN' => 'trasparenza'] as $quadro => $campo) {
            if (isset($campi[$campo]) && in_array($quadro, $compilati, true)) {
                $record->imposta($campo, 1);
            }
        }
        $testo = (string) $record;
        $caselle = match (true) {
            $sc => TracciatoRedditiSocieta::CASELLE_QUADRI_SC,
            $enc => TracciatoRedditiSocieta::CASELLE_QUADRI_ENC,
            default => TracciatoRedditiSocieta::CASELLE_QUADRI_SP,
        };
        foreach (array_unique($compilati) as $quadro) {
            if (isset($caselle[$quadro])) {
                $testo = substr_replace($testo, '1', $caselle[$quadro] - 1, 1);
            }
        }
        // organo di controllo (Redditi SC ed ENC, campi B86-B105): soggetto, codice fiscale, firma della dichiarazione, firma per attestazione
        if (($sc || $enc) && isset($dichiarazione->id)) {
            foreach (array_slice(iterator_to_array((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione), false), 0, 5) as $indice => $sottoscrizione) {
                $gruppo = (string) $sottoscrizione->tipoSoggetto . str_pad(strtoupper((string) $sottoscrizione->soggetto->codiceFiscale), 16) . (int) $sottoscrizione->isFirmata() . '0';
                $testo = substr_replace($testo, $gruppo, TracciatoRedditiSocieta::ORGANO_CONTROLLO_SC[$indice] - 1, strlen($gruppo));
            }
        }
        return $testo;
    }

    /**
     * Frontespizio del modello CNM (TracciatoRedditiSocieta::RECORD_B_CNM):
     * consolidante con denominazione e recapiti, casella del consolidato
     * nazionale, periodo d'imposta e natura giuridica (la situazione si
     * indica solo per il consolidato mondiale, istruzioni CNM),
     * rappresentante, impegno, visto, organo di controllo e caselle dei
     * quadri compilati.
     */
    private function recordBConsolidato(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): string
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $campi = TracciatoRedditiSocieta::RECORD_B_CNM;
        foreach (TracciatoRedditiSocieta::NUMERICI_B_CNM as $posizione => $lunghezza) {
            $campi['numerico' . $posizione] = [$posizione, $lunghezza, 'NU'];
        }
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $record = (new RecordF24($campi))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->imposta('flagConferma', (int) $dichiarazione->flagConferma)
                ->imposta('consolidatoNazionale', 1)
                ->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->impostaTroncato('denominazione', mb_strtoupper($soggetto->nominativo))
                ->imposta('telefono', $recapiti['telefono'])
                ->impostaTroncato('email', $recapiti['email'])
                ->imposta('dataInizioPeriodo', ($esercizio?->dataInizio ?? new SismaDate($anno . '-01-01'))->format('dmY'))
                ->imposta('dataFinePeriodo', ($esercizio?->dataFine ?? new SismaDate($anno . '-12-31'))->format('dmY'))
                ->imposta('naturaGiuridica', $dichiarazione->naturaGiuridica)
                ->imposta('firmaDichiarante', 1);
        $rappresentante = $dichiarazione->dichiarante;
        if ($rappresentante !== null) {
            $record->imposta('codiceFiscaleRappresentante', $rappresentante->codiceFiscale)
                    ->imposta('codiceCaricaRappresentante', $dichiarazione->codiceCarica?->value);
            if ($rappresentante->isPersonaFisica()) {
                [$comune, $provincia] = DatiContribuente::getLuogoNascita($rappresentante);
                $record->impostaTroncato('cognomeRappresentante', $rappresentante->cognome)
                        ->impostaTroncato('nomeRappresentante', $rappresentante->nome)
                        ->imposta('sessoRappresentante', $rappresentante->sesso?->value)
                        ->imposta('dataNascitaRappresentante', $rappresentante->dataNascita?->format('dmY'))
                        ->impostaTroncato('comuneNascitaRappresentante', $comune)
                        ->imposta('provinciaNascitaRappresentante', $provincia === 'EE' ? '' : $provincia);
            }
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIncaricato', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno?->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno?->format('dmY'))
                    ->imposta('firmaIncaricato', 1);
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $record->imposta('codiceFiscaleProfessionista', $dichiarazione->vistoProfessionista->codiceFiscale)->imposta('firmaVisto', 1);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $record->imposta('codiceFiscaleResponsabileCaf', $dichiarazione->vistoResponsabileCaf?->codiceFiscale)
                    ->imposta('codiceFiscaleCaf', $dichiarazione->vistoCaf->codiceFiscale)
                    ->imposta('firmaVisto', 1);
        }
        $testo = (string) $record;
        $compilati = [];
        foreach ($quadri->getModuli() as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        foreach (array_unique($compilati) as $quadro) {
            if (isset(TracciatoRedditiSocieta::CASELLE_QUADRI_CNM[$quadro])) {
                $testo = substr_replace($testo, '1', TracciatoRedditiSocieta::CASELLE_QUADRI_CNM[$quadro] - 1, 1);
            }
        }
        if (isset($dichiarazione->id)) {
            foreach (array_slice(iterator_to_array((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione), false), 0, 5) as $indice => $sottoscrizione) {
                $gruppo = (string) $sottoscrizione->tipoSoggetto . str_pad(strtoupper((string) $sottoscrizione->soggetto->codiceFiscale), 16) . (int) $sottoscrizione->isFirmata() . '0';
                $testo = substr_replace($testo, $gruppo, TracciatoRedditiSocieta::ORGANO_CONTROLLO_SC[$indice] - 1, strlen($gruppo));
            }
        }
        return $testo;
    }

    /**
     * Residenza anagrafica: si indica solo se variata dal 1° gennaio
     * dell'anno d'imposta (campi 37-45 del record B), con la data della
     * variazione.
     */
    private function impostaResidenza(RecordF24 $record, Dichiarazione $dichiarazione): void
    {
        $residenza = null;
        foreach ((new IndirizzoModel($this->dataMapper))->getVigentiAl($dichiarazione->soggetto, new \DateTimeImmutable()) as $indirizzo) {
            if (($indirizzo->tipoIndirizzo === TipoIndirizzo::residenza) && ($indirizzo->comune instanceof Comune)) {
                $residenza = $indirizzo;
            }
        }
        if (($residenza === null) || ($residenza->validoDal->format('Y-m-d') < $dichiarazione->anno . '-01-01')) {
            return;
        }
        $record->impostaTroncato('comuneResidenza', $residenza->comune->denominazione)
                ->imposta('provinciaResidenza', $residenza->comune->siglaProvincia)
                ->imposta('capResidenza', $residenza->cap)
                ->imposta('codiceComuneResidenza', $residenza->comune->codiceCatastale)
                ->impostaTroncato('indirizzoResidenza', $residenza->indirizzo)
                ->impostaTroncato('civicoResidenza', $residenza->numeroCivico)
                ->impostaTroncato('frazioneResidenza', $residenza->frazione)
                ->imposta('dataVariazioneResidenza', $residenza->validoDal->format('dmY'));
    }

    /**
     * Scelte dell'otto, del cinque e del due per mille (specifiche, par.
     * 4.1-4.3): casella dell'istituzione, della finalità o del partito con
     * la firma, oppure "scelta non effettuata"; nessuna anomalia.
     */
    private function impostaScelte(RecordF24 $record, Dichiarazione $dichiarazione): void
    {
        if ($dichiarazione->ottoPerMille === null) {
            $record->imposta('otto', 1);
        } else {
            $record->imposta('otto' . $dichiarazione->ottoPerMille->value, 1);
            if ($dichiarazione->finalitaStato !== null) {
                $record->imposta('sceltaStato', $dichiarazione->finalitaStato);
            }
        }
        if ($dichiarazione->cinquePerMille === null) {
            $record->imposta('cinque', 1);
        } else {
            $record->imposta('cinque' . $dichiarazione->cinquePerMille->value, 1);
            if (($dichiarazione->beneficiarioCinquePerMille !== null) && ($dichiarazione->cinquePerMille->getPosizioneCodiceFiscale() !== null)) {
                $record->imposta('beneficiario' . $dichiarazione->cinquePerMille->value, $dichiarazione->beneficiarioCinquePerMille);
            }
        }
        if ($dichiarazione->duePerMille === null) {
            $record->imposta('due', 1);
        } else {
            $record->imposta('codicePartito', $dichiarazione->duePerMille)->imposta('firmaPartito', 1);
        }
    }
}
