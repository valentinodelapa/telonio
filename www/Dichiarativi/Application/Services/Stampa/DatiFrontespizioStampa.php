<?php

namespace Dichiarativi\Application\Services\Stampa;

use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Isa\ModelliIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Dichiarativi\Application\Services\Telematico\GeneratoreIrap;
use Dichiarativi\Application\Services\Telematico\GeneratoreRedditi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Valori del frontespizio e della scheda delle scelte per la stampa sul
 * modello ufficiale, con i codici "#" delle posizioni manuali
 * (Database/sorgenti/posizioni-<modello>-<anno>.csv): gli stessi dati del
 * record B del file telematico (GeneratoreRedditi).
 */
class DatiFrontespizioStampa
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array<string, string> valore per codice "#"
     */
    public function getValori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        // dati di nascita solo delle persone fisiche
        [$comuneNascita, $provinciaNascita] = $soggetto->isPersonaFisica() ? DatiContribuente::getLuogoNascita($soggetto) : [null, null];
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        $valori = [
            '#codiceFiscale' => strtoupper((string) $soggetto->codiceFiscale),
            '#cognome' => mb_strtoupper((string) $soggetto->cognome),
            '#nome' => mb_strtoupper((string) $soggetto->nome),
            // soggetti diversi dalle persone fisiche: denominazione (nel modello IVA TR nella casella del cognome)
            '#denominazione' => $soggetto->isPersonaFisica() ? '' : mb_strtoupper($soggetto->nominativo),
            '#cognomeODenominazione' => mb_strtoupper($soggetto->isPersonaFisica() ? (string) $soggetto->cognome : $soggetto->nominativo),
            '#naturaGiuridica' => $dichiarazione->naturaGiuridica === null ? '' : sprintf('%02d', $dichiarazione->naturaGiuridica),
            '#codiceAttivita' => str_replace('.', '', (string) $datiFiscali?->codiceAteco?->codice),
            '#anno' => (string) $dichiarazione->anno,
            '#trimestre' => (string) $dichiarazione->trimestre,
            '#numeroModuli' => (string) count($quadri->getModuli()),
            '#sesso' => (string) $soggetto->sesso?->value,
            '#sesso' . $soggetto->sesso?->value => 'X',
            '#comuneNascita' => mb_strtoupper((string) $comuneNascita),
            '#provinciaNascita' => (string) $provinciaNascita,
            '#partitaIva' => (string) $datiFiscali?->partitaIva,
            '#telefono' => (string) $recapiti['telefono'],
            '#email' => (string) $recapiti['email'],
            '#correttiva' => $dichiarazione->tipologia === TipologiaDichiarazione::correttiva ? 'X' : '',
            '#integrativa' => $dichiarazione->tipologia === TipologiaDichiarazione::integrativa ? 'X' : '',
        ];
        $valori += $soggetto->isPersonaFisica() ? self::data('#nascita', $soggetto->dataNascita) : [];
        foreach (['' => $anno, 'Successivo' => $anno + 1] as $suffisso => $annoDomicilio) {
            $comune = VersamentiRedditi::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($annoDomicilio . '-01-01'));
            $valori['#comuneDomicilio' . $suffisso] = mb_strtoupper((string) $comune?->denominazione);
            $valori['#provinciaDomicilio' . $suffisso] = (string) $comune?->siglaProvincia;
            $valori['#codiceComuneDomicilio' . $suffisso] = (string) $comune?->codiceCatastale;
        }
        if ($dichiarazione->dichiarante !== null) {
            $valori['#codiceFiscaleRappresentante'] = strtoupper((string) $dichiarazione->dichiarante->codiceFiscale);
            $valori['#codiceCaricaRappresentante'] = (string) $dichiarazione->codiceCarica?->value;
        }
        if ($dichiarazione->intermediario !== null) {
            $valori['#codiceFiscaleIncaricato'] = strtoupper((string) $dichiarazione->intermediario->soggetto->codiceFiscale);
            $valori['#impegno'] = (string) $dichiarazione->impegno?->value;
            $valori += self::data('#impegno', $dichiarazione->dataImpegno);
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $valori['#codiceFiscaleProfessionista'] = strtoupper((string) $dichiarazione->vistoProfessionista->codiceFiscale);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $valori['#codiceFiscaleCaf'] = (string) $dichiarazione->vistoCaf->codiceFiscale;
            $valori['#codiceFiscaleResponsabileCaf'] = strtoupper((string) $dichiarazione->vistoResponsabileCaf?->codiceFiscale);
        }
        // caselle dei quadri compilati (firma della dichiarazione e tipo di dichiarazione)
        foreach ($quadri->getModuli() as $modulo) {
            foreach ($quadri->getQuadriCompilati($modulo) as $quadro) {
                $valori['#quadro' . $quadro] = 'X';
            }
        }
        // scelte dell'otto, cinque e due per mille: la casella scelta (la firma resta al contribuente)
        if ($dichiarazione->ottoPerMille !== null) {
            $valori['#otto' . $dichiarazione->ottoPerMille->value] = 'X';
            $valori['#finalitaStato'] = (string) $dichiarazione->finalitaStato;
        }
        if ($dichiarazione->cinquePerMille !== null) {
            $valori['#cinque' . $dichiarazione->cinquePerMille->value] = 'X';
            $valori['#beneficiario' . $dichiarazione->cinquePerMille->value] = (string) $dichiarazione->beneficiarioCinquePerMille;
        }
        if ($dichiarazione->duePerMille !== null) {
            $valori['#codicePartito'] = (string) $dichiarazione->duePerMille;
        }
        if ($soggetto->isPersonaFisica() === false) {
            $valori += $this->getValoriSocieta($dichiarazione, $quadri);
        }
        return array_filter($valori, fn(string $valore) => $valore !== '');
    }

    /**
     * Frontespizio delle società e degli enti (Redditi SP e SC, CNM, IRAP):
     * periodo d'imposta (esercizio contabile, altrimenti l'anno solare),
     * stato e situazione ordinari, dati anagrafici del rappresentante,
     * caselle del modello ISA, della comunicazione del concordato, del
     * consolidato e della trasparenza, canone RAI e organo di controllo
     * (soggetto e codice fiscale), come nel record B (GeneratoreRedditi).
     *
     * @return array<string, string>
     */
    private function getValoriSocieta(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $anno = $dichiarazione->anno;
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($dichiarazione->soggetto, $anno);
        $valori = self::data('#periodoDal', $esercizio?->dataInizio ?? new SismaDate($anno . '-01-01'))
                + self::data('#periodoAl', $esercizio?->dataFine ?? new SismaDate($anno . '-12-31'));
        // stato e situazione ordinari (codici 1 e 6); nel modello CNM la situazione è solo del consolidato mondiale e lo stato non c'è
        $consolidato = $dichiarazione->modello === ModelloDichiarativo::consolidato;
        $valori['#stato'] = $consolidato ? '' : '1';
        $valori['#situazione'] = $consolidato ? '' : '6';
        if ($dichiarazione->modello === ModelloDichiarativo::irap) {
            // regione del domicilio fiscale e modello Redditi del quadro compilato (frontespizio del file XML, GeneratoreIrap)
            $regione = VersamentiRedditi::getCodiceRegioneDomicilio($this->dataMapper, $dichiarazione->soggetto, new \DateTimeImmutable($anno . '-12-31'));
            $valori['#codiceRegione'] = $regione === null ? '' : sprintf('%02d', $regione);
            $valori['#regione'] = $regione === null ? '' : GeneratoreIrap::REGIONI[$regione];
            foreach (GeneratoreIrap::MODELLI_REDDITI as $quadro => $codice) {
                foreach ($quadri->getModuli() as $modulo) {
                    if (in_array($quadro, $quadri->getQuadriCompilati($modulo), true)) {
                        $valori['#dichiarazioneRedditi'] = (string) $codice;
                    }
                }
            }
        }
        $rappresentante = $dichiarazione->dichiarante;
        if (($rappresentante !== null) && $rappresentante->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($rappresentante);
            $valori['#cognomeRappresentante'] = mb_strtoupper((string) $rappresentante->cognome);
            $valori['#nomeRappresentante'] = mb_strtoupper((string) $rappresentante->nome);
            $valori['#sessoRappresentante' . $rappresentante->sesso?->value] = 'X';
            $valori['#comuneNascitaRappresentante'] = mb_strtoupper((string) $comune);
            $valori['#provinciaNascitaRappresentante'] = $provincia === 'EE' ? '' : (string) $provincia;
            $valori += self::data('#nascitaRappresentante', $rappresentante->dataNascita);
        }
        $valori['#canoneRai'] = (string) $dichiarazione->canoneRai?->value;
        if (isset($dichiarazione->id)) {
            $valori['#isa'] = count((new ModelliIsa($this->dataMapper))->getDichiarazioni($dichiarazione)) > 0 ? 'X' : '';
            foreach (array_slice(iterator_to_array((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione), false), 0, 5) as $indice => $sottoscrizione) {
                $valori['#organoSoggetto' . ($indice + 1)] = (string) $sottoscrizione->tipoSoggetto;
                $valori['#organoCodiceFiscale' . ($indice + 1)] = strtoupper((string) $sottoscrizione->soggetto->codiceFiscale);
            }
        }
        $valori['#comunicazioneCpb'] = $dichiarazione->revocaCpb ? (string) GeneratoreRedditi::CPB_REVOCA_CON_DICHIARAZIONE : '';
        $compilati = [];
        foreach ($quadri->getModuli() as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        // casella del consolidato: nel modello CNM quella del consolidato nazionale, in Redditi SC il quadro GN compilato
        $valori['#consolidato'] = ($consolidato || in_array('GN', $compilati, true)) ? 'X' : '';
        $valori['#trasparenza'] = in_array('TN', $compilati, true) ? 'X' : '';
        return $valori;
    }

    /**
     * Giorno, mese e anno in caselle separate.
     *
     * @return array<string, string>
     */
    private static function data(string $prefisso, ?\DateTimeInterface $data): array
    {
        return $data === null ? [] : [$prefisso . 'Giorno' => $data->format('d'), $prefisso . 'Mese' => $data->format('m'), $prefisso . 'Anno' => $data->format('Y')];
    }
}
