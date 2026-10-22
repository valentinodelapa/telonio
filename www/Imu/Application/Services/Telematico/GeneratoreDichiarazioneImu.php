<?php

namespace Imu\Application\Services\Telematico;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\RecapitoModel;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Models\RigoDichiarazioneImuModel;
use Imu\Application\Models\UtilizzoMistoEncModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * File telematico della dichiarazione IMU (TracciatoDichiarazioneImu): una
 * fornitura per dichiarazione, da controllare con il modulo di controllo
 * del Dipartimento delle Finanze in Desktop Telematico e trasmettere con
 * Entratel o Fisconline.
 *
 * Prima della generazione la dichiarazione è verificata (getErrori): righi
 * presenti, dati anagrafici e domicilio fiscale del contribuente, del
 * rappresentante e dell'intermediario.
 */
class GeneratoreDichiarazioneImu
{

    private const CODICE_STATO_ESTERO = 'EE';

    private DatiContribuente $datiContribuente;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {
        $this->datiContribuente = new DatiContribuente($dataMapper);
    }

    /**
     * @return string[]
     */
    public function getErrori(DichiarazioneImu $dichiarazione): array
    {
        $errori = [];
        $righi = iterator_to_array((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione));
        if (count($righi) === 0) {
            $errori[] = 'La dichiarazione non ha immobili.';
        }
        $soggetto = $dichiarazione->soggetto;
        foreach (DatiContribuente::getDatiMancanti($soggetto) as $mancante) {
            $errori[] = 'Contribuente: manca ' . $mancante . '.';
        }
        if ($this->datiContribuente->getDomicilio($soggetto, $this->getDataRiferimento($dichiarazione)) === null) {
            $errori[] = 'Contribuente: manca il domicilio fiscale (o residenza o sede legale) in Anagrafica.';
        }
        if (($soggetto->isPersonaFisica() === false) && ($dichiarazione->dichiarante === null)) {
            $errori[] = 'Indicare il rappresentante che firma la dichiarazione (legale rappresentante in Anagrafica).';
        }
        if ($dichiarazione->dichiarante !== null) {
            if ($dichiarazione->codiceCarica === null) {
                $errori[] = 'Indicare il codice di carica del dichiarante.';
            }
            foreach (DatiContribuente::getDatiMancanti($dichiarazione->dichiarante) as $mancante) {
                $errori[] = 'Dichiarante: manca ' . $mancante . '.';
            }
            if ((($dichiarazione->isEnc() === false)) && ($this->datiContribuente->getDomicilio($dichiarazione->dichiarante, $this->getDataRiferimento($dichiarazione)) === null)) {
                $errori[] = 'Dichiarante: manca il domicilio fiscale in Anagrafica.';
            }
        }
        if (($dichiarazione->intermediario !== null) && ($dichiarazione->dataImpegno === null)) {
            $errori[] = 'Indicare la data dell\'impegno a trasmettere dell\'intermediario.';
        }
        foreach ($righi as $rigo) {
            if ($rigo->altro && ($rigo->altroDescrizione === null)) {
                $errori[] = 'Immobile ' . $rigo->numeroOrdine . ': descrivere la vicenda "altro".';
            }
            if (($rigo->acquisto || $rigo->cessione || $rigo->altro || ($rigo->inizioTermine !== null)) && ($rigo->dataVariazione === null)) {
                $errori[] = 'Immobile ' . $rigo->numeroOrdine . ': manca la data della variazione.';
            }
            if (($rigo->esenzione === '1') && ($dichiarazione->isEnc() === false) && (($rigo->nonUtilizzabileTipo === null) || ($rigo->nonUtilizzabileAutorita === null) || ($rigo->nonUtilizzabileData === null))) {
                $errori[] = 'Immobile ' . $rigo->numeroOrdine . ': per l\'esenzione degli immobili occupati indicare tipo, autorità e data della denuncia.';
            }
            if ($dichiarazione->isEnc() && ($rigo->quadro === 'B') && ($this->getUtilizzo($dichiarazione, $rigo) === null)) {
                $errori[] = 'Immobile ' . $rigo->numeroOrdine . ' del quadro B: indicare l\'utilizzo per le attività non commerciali.';
            }
        }
        return $errori;
    }

    public function genera(DichiarazioneImu $dichiarazione): string
    {
        $errori = $this->getErrori($dichiarazione);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
        $righi = iterator_to_array((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione));
        $fornitura = [(string) $this->recordA($dichiarazione)];
        if ($dichiarazione->isEnc()) {
            $fornitura[] = (string) $this->recordBEnc($dichiarazione, $righi);
            $quadroA = array_values(array_filter($righi, fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'A'));
            $quadroB = array_values(array_filter($righi, fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'B'));
            $recordC = $this->recordImmobili($dichiarazione, $quadroA, 'C', TracciatoDichiarazioneImu::IMMOBILE_C_ENC, TracciatoDichiarazioneImu::SCOSTAMENTO_IMMOBILE_C_ENC, TracciatoDichiarazioneImu::CODA_C_ENC);
            $recordD = [];
            foreach ($quadroB as $indice => $rigo) {
                $recordD[] = $this->recordQuadroBEnc($dichiarazione, $rigo, $indice + 1);
            }
            array_push($fornitura, ...array_map('strval', $recordC), ...array_map('strval', $recordD));
            $fornitura[] = (string) $this->recordEEnc($dichiarazione);
            $fornitura[] = (string) (new RecordF24(TracciatoDichiarazioneImu::RECORD_Z_ENC))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordC', count($recordC))->imposta('numeroRecordD', count($recordD));
        } else {
            $fornitura[] = (string) $this->recordB($dichiarazione);
            $recordD = $this->recordImmobili($dichiarazione, $righi, 'D', TracciatoDichiarazioneImu::IMMOBILE_D, TracciatoDichiarazioneImu::SCOSTAMENTO_IMMOBILE_D, TracciatoDichiarazioneImu::CODA_D);
            array_push($fornitura, ...array_map('strval', $recordD));
            $fornitura[] = (string) (new RecordF24(TracciatoDichiarazioneImu::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordD', count($recordD));
        }
        return implode('', $fornitura);
    }

    public static function getNomeFile(DichiarazioneImu $dichiarazione): string
    {
        return sprintf('IMU%s_%d_%s_%s.txt', $dichiarazione->isEnc() ? 'ENC' : '', $dichiarazione->anno, $dichiarazione->codiceComune, preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale));
    }

    private function getDataRiferimento(DichiarazioneImu $dichiarazione): \DateTimeInterface
    {
        return $dichiarazione->dataDichiarazione ?? new \DateTimeImmutable();
    }

    private function recordA(DichiarazioneImu $dichiarazione): RecordF24
    {
        $fornitore = $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->dichiarante?->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale;
        return (new RecordF24(TracciatoDichiarazioneImu::RECORD_A))
                        ->imposta('tipoRecord', 'A')
                        ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura())
                        ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? '01' : '10')
                        ->imposta('codiceFiscaleFornitore', $fornitore)
                        ->imposta('tipologiaDichiarazione', $dichiarazione->tipologia->value)
                        ->imposta('progressivoInvio', 1)
                        ->imposta('numeroInvii', 1);
    }

    private function recordB(DichiarazioneImu $dichiarazione): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $record = $this->testata(new RecordF24(TracciatoDichiarazioneImu::RECORD_B), 'B', $dichiarazione);
        $record->imposta('codiceFiscaleContribuente', $soggetto->codiceFiscale);
        $this->impostaRecapiti($record, $soggetto, 'telefonoPrefisso', 'telefonoNumero', 'email');
        if ($soggetto->isPersonaFisica()) {
            [$luogo, $provincia] = DatiContribuente::getLuogoNascita($soggetto);
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome)
                    ->imposta('dataNascita', $soggetto->dataNascita->format('dmY'))
                    ->imposta('sesso', $soggetto->sesso?->value)
                    ->impostaTroncato('comuneNascita', $luogo)
                    ->imposta('provinciaNascita', $provincia);
        } else {
            $record->impostaTroncato('cognome', $soggetto->nominativo);
        }
        $this->impostaDomicilio($record, $this->datiContribuente->getDomicilio($soggetto, $this->getDataRiferimento($dichiarazione)), '');
        $dichiarante = $dichiarazione->dichiarante;
        if ($dichiarante !== null) {
            $record->imposta('codiceFiscaleDichiarante', $dichiarante->codiceFiscale)
                    ->imposta('codiceCarica', $dichiarazione->codiceCarica?->value)
                    ->impostaTroncato('cognomeDichiarante', $dichiarante->cognome ?? $dichiarante->nominativo)
                    ->impostaTroncato('nomeDichiarante', $dichiarante->nome);
            $this->impostaRecapiti($record, $dichiarante, 'telefonoPrefissoDichiarante', 'telefonoNumeroDichiarante', 'emailDichiarante');
            $this->impostaDomicilio($record, $this->datiContribuente->getDomicilio($dichiarante, $this->getDataRiferimento($dichiarazione)), 'Dichiarante');
        }
        $this->impostaIntermediario($record, $dichiarazione, true);
        return $record;
    }

    private function recordBEnc(DichiarazioneImu $dichiarazione, array $righi): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $record = $this->testata(new RecordF24(TracciatoDichiarazioneImu::RECORD_B_ENC), 'B', $dichiarazione);
        $record->impostaTroncato('denominazione', $soggetto->nominativo)
                ->imposta('codiceFiscaleContribuente', $soggetto->codiceFiscale);
        $this->impostaRecapiti($record, $soggetto, 'telefonoPrefisso', 'telefonoNumero', 'email');
        $this->impostaDomicilio($record, $this->datiContribuente->getDomicilio($soggetto, $this->getDataRiferimento($dichiarazione)), '');
        $rappresentante = $dichiarazione->dichiarante;
        [$luogo, $provincia] = DatiContribuente::getLuogoNascita($rappresentante);
        $record->imposta('codiceFiscaleRappresentante', $rappresentante->codiceFiscale)
                ->imposta('codiceCarica', $dichiarazione->codiceCarica?->value)
                ->impostaTroncato('cognomeRappresentante', $rappresentante->cognome)
                ->impostaTroncato('nomeRappresentante', $rappresentante->nome)
                ->imposta('sessoRappresentante', $rappresentante->sesso?->value)
                ->imposta('dataNascitaRappresentante', $rappresentante->dataNascita?->format('dmY'))
                ->impostaTroncato('comuneNascitaRappresentante', $luogo)
                ->imposta('provinciaNascitaRappresentante', $provincia)
                ->imposta('residenzaEstero', 0);
        $this->impostaRecapiti($record, $rappresentante, 'telefonoPrefissoRappresentante', 'telefonoNumeroRappresentante', null);
        $quadroA = array_filter($righi, fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'A');
        $quadroB = array_filter($righi, fn(RigoDichiarazioneImu $rigo) => $rigo->quadro === 'B');
        $record->imposta('numeroImmobiliQuadroA', count(array_unique(array_map(fn(RigoDichiarazioneImu $rigo) => $rigo->progressivoImmobile, $quadroA))))
                ->imposta('numeroOrdiniQuadroA', count($quadroA))
                ->imposta('numeroImmobiliQuadroB', count(array_unique(array_map(fn(RigoDichiarazioneImu $rigo) => $rigo->progressivoImmobile, $quadroB))))
                ->imposta('quadroC', 1)
                ->imposta('quadroD', (((float) $dichiarazione->creditoRimborso + (float) $dichiarazione->creditoCompensazione) > 0) ? 1 : 0)
                ->imposta('firma', 1);
        $this->impostaIntermediario($record, $dichiarazione, false);
        return $record;
    }

    private function testata(RecordF24 $record, string $tipo, DichiarazioneImu $dichiarazione): RecordF24
    {
        return $record->imposta('tipoRecord', $tipo)
                        ->imposta('codiceFiscale', $dichiarazione->soggetto->codiceFiscale)
                        ->imposta('progressivoModulo', 1)
                        ->imposta('annoDichiarazione', (int) $this->getDataRiferimento($dichiarazione)->format('Y'))
                        ->imposta('periodoImposta', $dichiarazione->anno)
                        ->imposta('codiceComune', $dichiarazione->codiceComune)
                        ->impostaTroncato('denominazioneComune', $dichiarazione->denominazioneComune);
    }

    private function impostaIntermediario(RecordF24 $record, DichiarazioneImu $dichiarazione, bool $conImpegno): void
    {
        if ($dichiarazione->intermediario === null) {
            return;
        }
        $record->imposta('codiceFiscaleIntermediario', $dichiarazione->intermediario->soggetto->codiceFiscale)
                ->imposta('dataImpegno', $dichiarazione->dataImpegno->format('dmY'))
                ->imposta('firmaIntermediario', 1);
        if ($conImpegno) {
            $record->imposta('impegno', 1);
        }
    }

    private function impostaRecapiti(RecordF24 $record, Soggetto $soggetto, string $prefisso, string $numero, ?string $email): void
    {
        $telefono = null;
        $posta = null;
        foreach ((new RecapitoModel($this->dataMapper))->getElenco($soggetto) as $recapito) {
            if (in_array($recapito->tipoRecapito, [TipoRecapito::telefono, TipoRecapito::cellulare], true) && (($telefono === null) || $recapito->principale)) {
                $telefono = preg_replace('/\D/', '', $recapito->valore);
            }
            if (($recapito->tipoRecapito === TipoRecapito::email) && (($posta === null) || $recapito->principale)) {
                $posta = $recapito->valore;
            }
        }
        if (($telefono !== null) && (strlen($telefono) >= 6)) {
            $telefono = substr($telefono, -12);
            $record->imposta($prefisso, substr($telefono, 0, max(0, strlen($telefono) - 8)) ?: substr($telefono, 0, 4))
                    ->imposta($numero, substr($telefono, -8));
        }
        if (($email !== null) && ($posta !== null)) {
            $record->impostaTroncato($email, mb_strtolower($posta));
        }
    }

    private function impostaDomicilio(RecordF24 $record, ?Indirizzo $indirizzo, string $suffisso): void
    {
        if ($indirizzo === null) {
            return;
        }
        $estero = $indirizzo->nazione->isItalia() === false;
        $record->impostaTroncato('indirizzo' . $suffisso, $indirizzo->indirizzo)
                ->impostaTroncato('numeroCivico' . $suffisso, $indirizzo->numeroCivico ?? 'SNC')
                ->impostaTroncato('cap' . $suffisso, $indirizzo->cap)
                ->impostaTroncato('comune' . $suffisso, $estero ? trim(($indirizzo->localitaEstera ?? '') . ' ' . $indirizzo->nazione->denominazione) : $indirizzo->comune?->denominazione)
                ->imposta('provincia' . $suffisso, $estero ? self::CODICE_STATO_ESTERO : $indirizzo->comune?->siglaProvincia);
        if ($estero && ($indirizzo->nazione->codiceCatastale !== null)) {
            $record->imposta('statoEstero' . $suffisso, (int) substr($indirizzo->nazione->codiceCatastale, 1));
        }
    }

    /**
     * Record con due immobili ciascuno (D ordinario, C del quadro A ENC).
     *
     * @param RigoDichiarazioneImu[] $righi
     * @return RecordF24[]
     */
    private function recordImmobili(DichiarazioneImu $dichiarazione, array $righi, string $tipo, array $campiImmobile, int $scostamento, array $coda): array
    {
        $records = [];
        foreach (array_chunk($righi, 2) as $modulo => $coppia) {
            $campi = ['tipoRecord' => [1, 1, 'AN'], 'codiceFiscale' => [2, 16, 'AN'], 'progressivoModulo' => [18, 8, 'NU']]
                    + TracciatoDichiarazioneImu::sposta($campiImmobile, 0, 'primo')
                    + TracciatoDichiarazioneImu::sposta($campiImmobile, $scostamento, 'secondo') + $coda;
            $record = (new RecordF24($campi))->imposta('tipoRecord', $tipo)->imposta('codiceFiscale', $dichiarazione->soggetto->codiceFiscale)->imposta('progressivoModulo', $modulo + 1);
            foreach ($coppia as $indice => $rigo) {
                $prefisso = $indice === 0 ? 'primo' : 'secondo';
                $this->impostaImmobile($record, fn(string $nome) => $prefisso . ucfirst($nome), $rigo, $dichiarazione->isEnc() ? 'enc' : 'ordinario', $righi);
            }
            if (isset($coda['annotazioni']) && ($modulo === 0)) {
                $record->impostaTroncato('annotazioni', $dichiarazione->annotazioni);
            }
            if (isset($coda['data'])) {
                $record->imposta('data', $this->getDataRiferimento($dichiarazione)->format('dmY'))->imposta('firma', 1);
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Dati dell'immobile nel record.
     *
     * @param callable(string): string $campo nome del campo nel record
     * @param string $modello "ordinario", "enc" (quadro A ENC) o "quadroB"
     * @param RigoDichiarazioneImu[] $tutti righi del quadro, per l'indicatore di continuità
     */
    private function impostaImmobile(RecordF24 $record, callable $campo, RigoDichiarazioneImu $rigo, string $modello, array $tutti): void
    {
        $immobile = $rigo->immobile;
        $continuita = count(array_filter($tutti, fn(RigoDichiarazioneImu $altro) => ($altro->progressivoImmobile === $rigo->progressivoImmobile) && ($altro->numeroOrdine < $rigo->numeroOrdine))) > 0;
        [$intera, $decimale] = self::percentuale($rigo->percentualePossesso);
        $record->imposta($campo('numeroOrdine'), $rigo->numeroOrdine)
                ->imposta($campo('progressivoImmobile'), $rigo->progressivoImmobile)
                ->imposta($campo('continuita'), $continuita ? 1 : 0)
                ->imposta($campo('caratteristiche'), $rigo->caratteristica->value)
                ->impostaTroncato($campo('indirizzo'), self::getUbicazione($immobile))
                ->imposta($campo('valore'), (int) round($rigo->valore))
                ->imposta($campo('percentualeIntera'), $intera)
                ->imposta($campo('percentualeDecimale'), $decimale)
                ->imposta($campo('riduzioni'), $rigo->riduzione)
                ->imposta($campo('acquisto'), $rigo->acquisto ? 1 : 0)
                ->imposta($campo('cessione'), $rigo->cessione ? 1 : 0)
                ->imposta($campo('altro'), $rigo->altro ? 1 : 0)
                ->impostaTroncato($campo('altroDescrizione'), $rigo->altroDescrizione)
                ->imposta($campo('dataVariazione'), $rigo->dataVariazione?->format('dmY'))
                ->imposta($campo('inizioTermine'), $rigo->inizioTermine)
                ->impostaTroncato($campo('agenziaEntrate'), $rigo->agenziaEntrate)
                ->impostaTroncato($campo('estremiTitolo'), $rigo->estremiTitolo);
        $this->impostaCatasto($record, $campo, $rigo);
        if ($modello === 'quadroB') {
            return;
        }
        $record->imposta($campo('esenzione'), $rigo->esenzione);
        if ($rigo->esenzione === '1') {
            $record->imposta($campo('nonUtilizzabileTipo'), $rigo->nonUtilizzabileTipo)
                    ->impostaTroncato($campo('nonUtilizzabileAutorita'), $rigo->nonUtilizzabileAutorita)
                    ->imposta($campo('nonUtilizzabileData'), $rigo->nonUtilizzabileData?->format('dmY'));
        }
        if ($modello === 'ordinario') {
            $record->imposta($campo('detrazione'), $rigo->detrazione === null ? 0 : (int) round($rigo->detrazione))
                    ->imposta($campo('equiparazione'), $rigo->equiparazione);
        }
    }

    private function impostaCatasto(RecordF24 $record, callable $campo, RigoDichiarazioneImu $rigo): void
    {
        if ($rigo->caratteristica->value === '4') {
            return;
        }
        $immobile = $rigo->immobile;
        $datiCatastali = (new DatiCatastaliModel($this->dataMapper))->getVigenteAl($immobile, $rigo->dataVariazione ?? new \DateTimeImmutable($rigo->dichiarazioneImu->anno . '-12-31'));
        $terreno = $immobile->tipoCatasto->value === 'T';
        $record->imposta($campo('catasto'), $terreno ? 'T' : 'U')
                ->impostaTroncato($campo('sezione'), $immobile->sezione)
                ->impostaTroncato($campo('foglio'), $immobile->foglio)
                ->impostaTroncato($campo('particella'), $immobile->particella)
                ->impostaTroncato($campo('subalterno'), $immobile->subalterno)
                ->impostaTroncato($campo('categoria'), $terreno ? $datiCatastali?->qualitaColtura : $datiCatastali?->categoria?->value)
                ->impostaTroncato($campo('classe'), $datiCatastali?->classe);
    }

    private function recordQuadroBEnc(DichiarazioneImu $dichiarazione, RigoDichiarazioneImu $rigo, int $modulo): RecordF24
    {
        $campi = ['tipoRecord' => [1, 1, 'AN'], 'codiceFiscale' => [2, 16, 'AN'], 'progressivoModulo' => [18, 8, 'NU']] + TracciatoDichiarazioneImu::IMMOBILE_D_ENC;
        $record = (new RecordF24($campi))->imposta('tipoRecord', 'D')->imposta('codiceFiscale', $dichiarazione->soggetto->codiceFiscale)->imposta('progressivoModulo', $modulo);
        $this->impostaImmobile($record, fn(string $nome) => $nome, $rigo, 'quadroB', [$rigo]);
        $utilizzo = $this->getUtilizzo($dichiarazione, $rigo);
        foreach ($utilizzo->getAttivita() as $attivita) {
            $record->imposta('attivita' . $attivita->value, 1);
        }
        $record->imposta('esenzione', $utilizzo->esente ? 1 : 0);
        if ($utilizzo->tipoConcessione !== null) {
            $record->imposta('tipoConcessione', $utilizzo->tipoConcessione)->impostaTroncato('comodatario', $utilizzo->comodatario);
        }
        if ($utilizzo->esente === false) {
            $this->impostaUtilizzoMisto($record, $utilizzo, $rigo->valore);
        }
        return $record;
    }

    /**
     * Sezioni "attività didattica" o "altre attività" del quadro B.
     */
    private function impostaUtilizzoMisto(RecordF24 $record, UtilizzoMistoEnc $utilizzo, float $valore): void
    {
        $sezione = $utilizzo->isDidattica() ? 'didattica' : 'altre';
        foreach (UtilizzoMistoEnc::RAPPORTI as $rapporto) {
            [$intera, $decimale] = self::percentuale((float) $utilizzo->{$sezione . $rapporto});
            $record->imposta($sezione . $rapporto . 'Intera', $intera)->imposta($sezione . $rapporto . 'Decimale', $decimale);
        }
        [$intera, $decimale] = self::percentuale($utilizzo->getPercentualeImponibilita($sezione));
        $record->imposta($sezione . 'PercentualeIntera', $intera)->imposta($sezione . 'PercentualeDecimale', $decimale);
        $valori = $utilizzo->getValori($valore);
        if ($sezione === 'didattica') {
            $rapporto = $utilizzo->getRapportoCmsCm();
            $record->imposta('didatticaCm', self::centesimi($utilizzo->didatticaCorrispettivoMedio))
                    ->imposta('didatticaCms', self::centesimi($utilizzo->didatticaCostoMedioStudente))
                    ->imposta('didatticaValoreImponibile', self::centesimi($valori['imponibile']))
                    ->imposta('didatticaValoreEsente', self::centesimi($valori['esente']))
                    ->imposta('didatticaCmMinoreCms', $rapporto === null ? 1 : 0)
                    ->imposta('didatticaCmMaggioreCms', $rapporto === null ? 0 : 1);
            if ($rapporto !== null) {
                [$intera, $decimale] = self::percentuale($rapporto);
                $record->imposta('didatticaRapportoIntera', $intera)->imposta('didatticaRapportoDecimale', $decimale)
                        ->imposta('didatticaValoreParziale', self::centesimi($valori['parziale']))
                        ->imposta('didatticaValoreComplessivo', self::centesimi($valori['imponibile'] + $valori['parziale']));
            }
        } else {
            $record->imposta('altreCenc', self::centesimi($utilizzo->altreCorrispettivoMedio))
                    ->imposta('altreCm', self::centesimi($utilizzo->altreCorrispettivoMercato))
                    ->imposta('altreValoreImponibile', self::centesimi($valori['imponibile']));
        }
    }

    private function recordEEnc(DichiarazioneImu $dichiarazione): RecordF24
    {
        $saldo = $dichiarazione->getSaldoQuadroC();
        $euro = fn(?float $importo) => (int) round((float) $importo);
        return (new RecordF24(TracciatoDichiarazioneImu::RECORD_E_ENC))
                        ->imposta('tipoRecord', 'E')
                        ->imposta('codiceFiscale', $dichiarazione->soggetto->codiceFiscale)
                        ->imposta('progressivoModulo', 1)
                        ->imposta('impostaDovuta', $euro($dichiarazione->impostaDovuta))
                        ->imposta('eccedenzaPrecedente', $euro($dichiarazione->eccedenzaPrecedente))
                        ->imposta('eccedenzaCompensata', $euro($dichiarazione->eccedenzaCompensata))
                        ->imposta('rateVersate', $euro($dichiarazione->rateVersate))
                        ->imposta('impostaDebito', $saldo > 0 ? $euro($saldo) : 0)
                        ->imposta('impostaCredito', $saldo < 0 ? $euro(-$saldo) : 0)
                        ->imposta('creditoDichiarazione', $saldo < 0 ? $euro(-$saldo) : 0)
                        ->imposta('creditoRimborso', $euro($dichiarazione->creditoRimborso))
                        ->imposta('creditoCompensazione', $euro($dichiarazione->creditoCompensazione));
    }

    public function getUtilizzo(DichiarazioneImu $dichiarazione, RigoDichiarazioneImu $rigo): ?UtilizzoMistoEnc
    {
        return (new UtilizzoMistoEncModel($this->dataMapper))->getPerAnno($dichiarazione->soggetto, $rigo->immobile, $dichiarazione->anno);
    }

    /**
     * Percentuale in parte intera e due decimali.
     *
     * @return array{0: int, 1: int}
     */
    private static function percentuale(float $percentuale): array
    {
        $centesimi = (int) round($percentuale * 100);
        return [intdiv($centesimi, 100), $centesimi % 100];
    }

    private static function centesimi(?float $importo): int
    {
        return (int) round((float) $importo * 100);
    }

    /**
     * Località, via, civico, piano e interno.
     */
    private static function getUbicazione(Immobile $immobile): string
    {
        return trim(implode(' ', array_filter([$immobile->indirizzo, $immobile->numeroCivico, $immobile->piano !== null ? 'PIANO ' . $immobile->piano : null, $immobile->interno !== null ? 'INT. ' . $immobile->interno : null]))) ?: $immobile->comune->denominazione;
    }
}
