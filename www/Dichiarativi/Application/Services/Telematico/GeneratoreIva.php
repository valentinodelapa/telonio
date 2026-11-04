<?php

namespace Dichiarativi\Application\Services\Telematico;

use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * File telematico della dichiarazione IVA (fornitura "IVA26" per l'anno
 * 2025): record A (fornitore: l'intermediario o, senza, il contribuente o
 * il suo rappresentante), B (frontespizio), D (quadri, un modulo, con il
 * campo V1000001 dei quadri compilati) e Z. Il file è la fornitura da
 * controllare e autenticare con il software dell'Agenzia delle Entrate
 * prima dell'invio.
 */
class GeneratoreIva
{

    /** Ordine dei quadri nella stringa dei quadri compilati (V1000001) */
    public const ORDINE_QUADRI = ['VA', 'VC', 'VD', 'VE', 'VF', 'VG', 'VJ', 'VH', 'VM', 'VN', 'VK', 'VL', 'VP', 'VQ', 'VT', 'VX', 'VO'];

    /** Ordine dei quadri del prospetto IVA 26 PR nella stringa V1000002 */
    public const ORDINE_PROSPETTO = ['VS', 'VV', 'VW', 'VY', 'VZ'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \InvalidArgumentException se la dichiarazione ha errori o
     *         segnalazioni non confermate
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $controlli = new ControlliIva($this->dataMapper);
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
            $compilati = array_values(array_intersect(self::ORDINE_QUADRI, $quadri->getQuadriCompilati($modulo)));
            unset($valori[$modulo]['V1000001'], $valori[$modulo]['V1000002']);
            // quadri compilati solo nei moduli della dichiarazione (con il VA2): i quadri VG e VS successivi al primo
            // hanno lo stesso progressivo modulo ma non sono moduli
            if (($modulo === 1) || isset($valori[$modulo]['VA002001'])) {
                $valori[$modulo]['V1000001'] = implode('', $compilati);
            }
            // quadri compilati del prospetto IVA 26 PR della controllante
            $prospetto = array_values(array_intersect(self::ORDINE_PROSPETTO, $quadri->getQuadriCompilati($modulo)));
            if (($modulo === 1) && (count($prospetto) > 0)) {
                $valori[$modulo]['V1000002'] = implode('', $prospetto);
            }
        }
        $recordD = RecordQuadri::genera('D', $dichiarazione->soggetto->codiceFiscale, $valori, $quadri->getCatalogo());
        return implode('', [
            (string) $this->recordA($dichiarazione),
            (string) $this->recordB($dichiarazione, count(array_filter(array_keys($valori), fn(int $modulo) => ($modulo === 1) || isset($valori[$modulo]['VA002001'])))),
            ...$recordD,
            (string) (new RecordF24(TracciatoIva::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordD', count($recordD)),
        ]);
    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s.txt', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale));
    }

    private function recordA(Dichiarazione $dichiarazione): RecordF24
    {
        $fornitore = $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->dichiarante?->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale;
        return (new RecordF24(TracciatoIva::RECORD_A))
                        ->imposta('tipoRecord', 'A')
                        ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno))
                        ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? 1 : 10)
                        ->imposta('codiceFiscaleFornitore', $fornitore);
    }

    private function recordB(Dichiarazione $dichiarazione, int $moduli): RecordF24
    {
        $soggetto = $dichiarazione->soggetto;
        $frontespizio = new Frontespizio($this->dataMapper);
        $record = (new RecordF24(TracciatoIva::RECORD_B))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoDichiarazione', 'TELONIO' . $dichiarazione->id)
                ->imposta('flagConferma', (int) $dichiarazione->flagConferma)
                ->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->imposta('integrativa', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::integrativa))
                ->imposta('partitaIva', (new ControlliIva($this->dataMapper))->getPartitaIva($dichiarazione))
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
        if ($dichiarazione->vistoProfessionista !== null) {
            $record->imposta('codiceFiscaleProfessionista', $dichiarazione->vistoProfessionista->codiceFiscale)
                    ->imposta('firmaVisto', 1);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $record->imposta('codiceFiscaleResponsabileCaf', $dichiarazione->vistoResponsabileCaf->codiceFiscale)
                    ->imposta('codiceFiscaleCaf', $dichiarazione->vistoCaf->codiceFiscale)
                    ->imposta('firmaVisto', 1);
        }
        $record->imposta('esoneroVisto', (int) $dichiarazione->esoneroVisto);
        if (isset($dichiarazione->id)) {
            foreach ((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione) as $indice => $sottoscrizione) {
                $componente = $indice + 1;
                $record->imposta('organoControllo' . $componente, $sottoscrizione->tipoSoggetto)
                        ->imposta('codiceFiscaleOrganoControllo' . $componente, $sottoscrizione->soggetto->codiceFiscale)
                        ->imposta('firmaOrganoControllo' . $componente, (int) $sottoscrizione->isFirmata());
            }
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
