<?php

namespace Dichiarativi\Application\Services\Stampa;

use Anagrafica\Application\Models\DatiFiscaliModel;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
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
        return array_filter($valori, fn(string $valore) => $valore !== '');
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
