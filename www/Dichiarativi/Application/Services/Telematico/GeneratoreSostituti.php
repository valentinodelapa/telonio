<?php

namespace Dichiarativi\Application\Services\Telematico;

use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Sostituti\ControlliSostituti;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Forniture telematiche dei sostituti d'imposta (TracciatoSostituti):
 *
 * - Certificazione Unica ("CUR26"): record A, B (frontespizio della
 *   comunicazione), per ogni certificazione un record D con i dati
 *   anagrafici (quadro DA) seguito dal record H con il lavoro autonomo
 *   (quadro AU), con il codice fiscale del percipiente e il progressivo
 *   della certificazione nella parte posizionale, e Z;
 * - modello 770 ("77026"): record A, B (frontespizio con la tipologia di
 *   invio 1, unico flusso, le caselle dei quadri compilati e quella delle
 *   ritenute sul lavoro autonomo), record D con i prospetti ST, SV e SX
 *   per modulo e Z.
 */
class GeneratoreSostituti
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \InvalidArgumentException se la dichiarazione ha errori
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $errori = (new ControlliSostituti($this->dataMapper))->getErrori($dichiarazione, $quadri);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
        $valori = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                $valori[$modulo][$codice] = $dati['valore'];
            }
        }
        $recordA = (new RecordF24($dichiarazione->modello === ModelloDichiarativo::modello770 ? TracciatoSostituti::RECORD_A_770 : TracciatoSostituti::RECORD_A))
                ->imposta('tipoRecord', 'A')
                ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno))
                ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? 1 : 10)
                ->imposta('codiceFiscaleFornitore', $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->dichiarante?->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale);
        return $dichiarazione->modello === ModelloDichiarativo::modello770 ? $this->genera770($dichiarazione, $quadri, $valori, (string) $recordA)
                : $this->generaCu($dichiarazione, $quadri, $valori, (string) $recordA);
    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d.txt', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale), $dichiarazione->anno);
    }

    /**
     * @param array<int, array<string, string>> $valori
     */
    private function generaCu(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, array $valori, string $recordA): string
    {
        $certificazioni = self::certificazioni($dichiarazione->soggetto->codiceFiscale, $valori, $quadri, (int) $dichiarazione->flagConferma);
        $conteggio = fn(string $tipo) => count(array_filter($certificazioni, fn(string $record) => $record[0] === $tipo));
        return implode('', [
            $recordA,
            (string) $this->recordBCu($dichiarazione, count($valori)),
            ...$certificazioni,
            (string) (new RecordF24(TracciatoSostituti::RECORD_Z_CU))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)
                    ->imposta('numeroRecordD', $conteggio('D'))->imposta('numeroRecordH', $conteggio('H')),
        ]);
    }

    /**
     * Record delle certificazioni nell'ordine delle specifiche: per ogni
     * certificazione il record D seguito dai suoi record H; progressivo
     * della certificazione uguale al numero del modulo (crescente, non
     * necessariamente consecutivo).
     *
     * @param array<int, array<string, string>> $valori valori per modulo
     * @return string[]
     */
    public static function certificazioni(string $codiceFiscale, array $valori, QuadriDichiarazione $quadri, int $flagConferma): array
    {
        $record = [];
        ksort($valori);
        foreach ($valori as $progressivo => $campi) {
            $percipiente = $campi['DA002001'];
            $anagrafici = array_filter($campi, fn(string $codice) => str_starts_with($codice, 'DA'), ARRAY_FILTER_USE_KEY);
            $compensi = array_filter($campi, fn(string $codice) => str_starts_with($codice, 'AU'), ARRAY_FILTER_USE_KEY);
            // il codice fiscale del sostituto è quello del record B
            $anagrafici['DA001001'] = $codiceFiscale;
            array_push($record, ...self::certificazione('D', $codiceFiscale, $percipiente, $progressivo, $anagrafici, $quadri, $flagConferma),
                    ...self::certificazione('H', $codiceFiscale, $percipiente, $progressivo, $compensi, $quadri, null));
        }
        return $record;
    }

    /**
     * Record di una certificazione (D o H): record a struttura variabile
     * con la parte posizionale della CU (codice fiscale del percipiente,
     * progressivo della certificazione, modulo 1).
     *
     * @param array<string, string> $campi
     * @return string[]
     */
    public static function certificazione(string $tipo, string $codiceFiscale, string $percipiente, int $progressivo, array $campi, QuadriDichiarazione $quadri, ?int $flagConferma): array
    {
        if (count(array_filter($campi, fn(string $valore) => $valore !== '')) === 0) {
            return [];
        }
        $posizionale = (new RecordF24(TracciatoSostituti::RECORD_CERTIFICAZIONE))
                ->imposta('tipoRecord', $tipo)
                ->imposta('codiceFiscale', $codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('codiceFiscalePercipiente', $percipiente)
                ->imposta('progressivoCertificazione', $progressivo);
        if ($flagConferma !== null) {
            $posizionale->imposta('flagConferma', $flagConferma);
        }
        $parte = substr((string) $posizionale, 0, 89);
        return array_map(fn(string $record) => $parte . substr($record, 89), RecordQuadri::genera($tipo, $codiceFiscale, [1 => $campi], $quadri->getCatalogo()));
    }

    private function recordBCu(Dichiarazione $dichiarazione, int $certificazioni): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $record = (new RecordF24(TracciatoSostituti::RECORD_B_CU))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->impostaTroncato('email', $recapiti['email'])
                ->imposta('telefono', $recapiti['telefono'])
                ->imposta('numeroCertificazioni', $certificazioni)
                ->imposta('firma', 1);
        if ($soggetto->isPersonaFisica()) {
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome);
        } else {
            $record->impostaTroncato('denominazione', $soggetto->nominativo);
        }
        $rappresentante = $dichiarazione->dichiarante;
        if (($rappresentante !== null) && ($soggetto->isPersonaFisica() === false)) {
            $record->imposta('codiceFiscaleRappresentante', $rappresentante->codiceFiscale)
                    ->imposta('codiceCaricaRappresentante', $dichiarazione->codiceCarica?->value)
                    ->impostaTroncato('cognomeRappresentante', $rappresentante->isPersonaFisica() ? $rappresentante->cognome : $rappresentante->nominativo)
                    ->impostaTroncato('nomeRappresentante', $rappresentante->isPersonaFisica() ? $rappresentante->nome : '');
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIntermediario', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno?->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno?->format('dmY'))
                    ->imposta('firmaIntermediario', 1);
        }
        return $record;
    }

    /**
     * @param array<int, array<string, string>> $valori
     */
    private function genera770(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, array $valori, string $recordA): string
    {
        $recordD = RecordQuadri::genera('D', $dichiarazione->soggetto->codiceFiscale, $valori, $quadri->getCatalogo());
        $compilati = [];
        foreach (array_keys($valori) as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        return implode('', [
            $recordA,
            (string) $this->recordB770($dichiarazione, array_unique($compilati)),
            ...$recordD,
            (string) (new RecordF24(TracciatoSostituti::RECORD_Z_770))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordD', count($recordD)),
        ]);
    }

    /**
     * @param string[] $quadri quadri compilati
     */
    private function recordB770(Dichiarazione $dichiarazione, array $quadri): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $ateco = (new Frontespizio($this->dataMapper))->getCodiceAttivita2025($soggetto, $dichiarazione->anno);
        $record = (new RecordF24(TracciatoSostituti::RECORD_B_770))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->imposta('flagConferma', (int) $dichiarazione->flagConferma)
                ->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->imposta('integrativa', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::integrativa))
                ->imposta('codiceAttivita', $ateco ?? '')
                ->impostaTroncato('email', $recapiti['email'])
                ->imposta('telefono', $recapiti['telefono'])
                ->imposta('tipologiaInvio', 1)
                ->imposta('autonomo', 1)
                ->imposta('firmaDichiarante', 1);
        foreach (['ST' => 'quadroSt', 'SV' => 'quadroSv', 'SX' => 'quadroSx', 'SS' => 'quadroSs'] as $quadro => $casella) {
            $record->imposta($casella, (int) in_array($quadro, $quadri, true));
        }
        if ($soggetto->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($soggetto);
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome)
                    ->impostaTroncato('comuneNascita', $comune)
                    ->imposta('provinciaNascita', $provincia === 'EE' ? '' : $provincia)
                    ->imposta('dataNascita', $soggetto->dataNascita?->format('dmY'))
                    ->imposta('sesso', $soggetto->sesso?->value);
        } else {
            // codici statistici: soggetto in normale attività, periodo normale d'imposta (tabelle SA e SC)
            $record->impostaTroncato('denominazione', $soggetto->nominativo)
                    ->imposta('stato', 1)
                    ->imposta('naturaGiuridica', $dichiarazione->naturaGiuridica)
                    ->imposta('situazione', 6);
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
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIncaricato', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno?->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno?->format('dmY'))
                    ->imposta('firmaIncaricato', 1);
        }
        return $record;
    }
}
