<?php

namespace Dichiarativi\Application\Services\Telematico;

use Contabilita\Application\Models\AttivitaIvaModel;
use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\ControlliTr;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Fornitura telematica del modello IVA TR ("IVT25"): record A, B (dati
 * del frontespizio), un record C (o più) per modulo con i campi dei quadri
 * per codice e Z. Visto di conformità, attestazione patrimoniale e
 * sottoscrizione dell'organo di controllo sono campi del quadro TD.
 */
class GeneratoreTr
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \InvalidArgumentException se il modello ha errori o segnalazioni non confermate
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $controlli = new ControlliTr($this->dataMapper);
        $errori = $controlli->getErrori($dichiarazione, $quadri);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
        $segnalazioni = $controlli->getSegnalazioni($quadri);
        if ((count($segnalazioni) > 0) && ($dichiarazione->flagConferma === false)) {
            throw new \InvalidArgumentException('Dati non coerenti (' . implode(' ', $segnalazioni) . '): correggerli o confermarli nel frontespizio.');
        }
        $valori = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                $valori[$modulo][$codice] = $dati['valore'];
            }
        }
        $recordC = RecordQuadri::genera('C', $dichiarazione->soggetto->codiceFiscale, $valori, $quadri->getCatalogo());
        return implode('', [
            (string) (new RecordF24(TracciatoTr::RECORD_A))
                    ->imposta('tipoRecord', 'A')
                    ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno))
                    ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? 1 : 10)
                    ->imposta('codiceFiscaleFornitore', $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->dichiarante?->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale),
            (string) $this->recordB($dichiarazione, max(1, count($valori))),
            ...$recordC,
            (string) (new RecordF24(TracciatoTr::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordC', count($recordC)),
        ]);
    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d_T%d.txt', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale), $dichiarazione->anno, $dichiarazione->trimestre);
    }

    private function recordB(Dichiarazione $dichiarazione, int $moduli): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $frontespizio = new Frontespizio($this->dataMapper);
        $datiFiscali = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $dichiarazione->anno);
        $record = (new RecordF24(TracciatoTr::RECORD_B))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->imposta('flagConferma', (int) $dichiarazione->flagConferma)
                ->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->imposta('modificaIstanza', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::integrativa))
                ->imposta('partitaIva', (new ControlliIva($this->dataMapper))->getPartitaIva($dichiarazione))
                ->imposta('contabilitaSeparate', (int) (count((new AttivitaIvaModel($this->dataMapper))->getAttiveNel($soggetto, $dichiarazione->anno)) > 0))
                ->imposta('controllante', (int) ((new GruppoIvaModel($this->dataMapper))->getPerControllante($soggetto, $dichiarazione->anno) !== null))
                ->imposta('codiceAttivita', str_replace('.', '', (string) $datiFiscali?->codiceAteco?->codice))
                ->imposta('anno', $dichiarazione->anno)
                ->imposta('trimestre', $dichiarazione->trimestre)
                ->imposta('numeroModuli', $moduli)
                ->imposta('firmaDichiarante', 1);
        if ($soggetto->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($soggetto);
            $record->impostaTroncato('cognome', $soggetto->cognome)
                    ->impostaTroncato('nome', $soggetto->nome)
                    ->impostaTroncato('comuneNascita', $comune)
                    ->imposta('provinciaNascita', $provincia === 'EE' ? '' : $provincia)
                    ->imposta('dataNascita', $soggetto->dataNascita->format('dmY'))
                    ->imposta('sesso', $soggetto->sesso->value);
        } else {
            $record->impostaTroncato('denominazione', $soggetto->nominativo)
                    ->imposta('naturaGiuridica', $dichiarazione->naturaGiuridica);
        }
        $recapiti = $frontespizio->getRecapiti($soggetto);
        $record->impostaTroncato('email', $recapiti['email'])
                ->imposta('telefono', $recapiti['telefono']);
        $sottoscrittore = $dichiarazione->dichiarante;
        if ($sottoscrittore !== null) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($sottoscrittore);
            $record->imposta('codiceFiscaleSottoscrittore', $sottoscrittore->codiceFiscale)
                    ->imposta('codiceCaricaSottoscrittore', $dichiarazione->codiceCarica->value)
                    ->impostaTroncato('cognomeSottoscrittore', $sottoscrittore->cognome)
                    ->impostaTroncato('nomeSottoscrittore', $sottoscrittore->nome)
                    ->imposta('sessoSottoscrittore', $sottoscrittore->sesso->value)
                    ->imposta('dataNascitaSottoscrittore', $sottoscrittore->dataNascita->format('dmY'))
                    ->impostaTroncato('comuneNascitaSottoscrittore', $comune)
                    ->imposta('provinciaNascitaSottoscrittore', $provincia === 'EE' ? '' : $provincia);
        }
        if ($dichiarazione->intermediario !== null) {
            $record->imposta('codiceFiscaleIncaricato', $dichiarazione->intermediario->soggetto->codiceFiscale)
                    ->imposta('impegno', $dichiarazione->impegno->value)
                    ->imposta('dataImpegno', $dichiarazione->dataImpegno->format('dmY'))
                    ->imposta('firmaIncaricato', 1);
        }
        return $record;
    }
}
