<?php

namespace Dichiarativi\Application\Services\Telematico;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\ControlliRedditi;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Fornitura telematica del modello Redditi PF ("RPF26"): record A, B
 * (frontespizio: dati anagrafici, residenza variata nell'anno, domicilio
 * fiscale al 1° gennaio dell'anno d'imposta e del successivo, caselle dei
 * quadri compilati, impegno alla trasmissione e visto), record C dei quadri
 * per codice (un progressivo per modulo) e Z.
 *
 * Le scelte per la destinazione dell'otto, del cinque e del due per mille
 * non sono gestite: risultano "non effettuate".
 */
class GeneratoreRedditi
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
        $controlli = new ControlliRedditi($this->dataMapper);
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
            (string) (new RecordF24(TracciatoRedditi::RECORD_A))
                    ->imposta('tipoRecord', 'A')
                    ->imposta('codiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno))
                    ->imposta('tipoFornitore', $dichiarazione->intermediario === null ? 1 : 10)
                    ->imposta('codiceFiscaleFornitore', $dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale),
            (string) $this->recordB($dichiarazione, $quadri),
            ...$recordC,
            (string) (new RecordF24(TracciatoRedditi::RECORD_Z))->imposta('tipoRecord', 'Z')->imposta('numeroRecordB', 1)->imposta('numeroRecordC', count($recordC)),
        ]);
    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d.txt', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale), $dichiarazione->anno);
    }

    private function recordB(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): string
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        [$comuneNascita, $provinciaNascita] = DatiContribuente::getLuogoNascita($soggetto);
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $record = (new RecordF24(TracciatoRedditi::RECORD_B))
                ->imposta('tipoRecord', 'B')
                ->imposta('codiceFiscale', $soggetto->codiceFiscale)
                ->imposta('progressivoModulo', 1)
                ->imposta('identificativoProduttore', 'TELONIO')
                ->imposta('flagConferma', (int) $dichiarazione->flagConferma)
                ->imposta('correttiva', (int) ($dichiarazione->tipologia === TipologiaDichiarazione::correttiva))
                ->impostaTroncato('cognome', $soggetto->cognome)
                ->impostaTroncato('nome', $soggetto->nome)
                ->imposta('partitaIva', (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'))?->partitaIva)
                ->imposta('telefono', $recapiti['telefono'])
                ->impostaTroncato('email', $recapiti['email'])
                ->impostaTroncato('comuneNascita', $comuneNascita)
                ->imposta('provinciaNascita', $provinciaNascita === 'EE' ? '' : $provinciaNascita)
                ->imposta('dataNascita', $soggetto->dataNascita?->format('dmY'))
                ->imposta('sesso', $soggetto->sesso?->value)
                ->imposta('firmaDichiarante', 1);
        $this->impostaScelte($record, $dichiarazione);
        $this->impostaResidenza($record, $dichiarazione);
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
