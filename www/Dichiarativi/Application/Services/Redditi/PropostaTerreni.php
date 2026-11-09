<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Models\ContrattoLocazioneModel;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\CanoniLocazione;
use Imu\Application\Models\CaratteristicheImuModel;
use Imu\Application\Models\ComuneEsenzioneTerreniModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Enumerations\ModuloSuite;

/**
 * Righi del quadro RA dai possessi dei terreni del contribuente nell'anno
 * (Anagrafica): redditi dominicale e agrario dai dati catastali, titolo 1
 * (non affittato) o, con un contratto di affitto, 2 (regime vincolistico,
 * con il canone) o 3 (libero mercato), caso particolare 4 per l'affitto a
 * giovani agricoltori, casella CD o IAP dai dati fiscali.
 *
 * IMU non dovuta (col. 9): terreni posseduti e condotti da coltivatori
 * diretti e IAP (art. 1, c. 758, lett. a, L. 160/2019) e, con il modulo
 * IMU, terreni esenti per caratteristiche o nei comuni montani o delle
 * isole minori interamente esenti (circolare 9/1993). Il titolare
 * dell'impresa agricola (codici 7-9), il conduttore (4), la conduzione
 * associata e la perdita di prodotto si indicano a mano.
 */
class PropostaTerreni
{

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * @return RigoTerreno[]
     */
    public function getRighi(Soggetto $soggetto, int $anno): array
    {
        $this->avvisi = [];
        $inizio = new \DateTimeImmutable($anno . '-01-01');
        $fine = new \DateTimeImmutable($anno . '-12-31');
        $perImmobile = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            if (($possesso->immobile->tipoCatasto !== TipoCatasto::terreni) || ($possesso->titolo === TitoloPossesso::nudaProprieta)
                    || ($possesso->validoDal->format('Y-m-d') > $fine->format('Y-m-d')) || (($possesso->validoAl !== null) && ($possesso->validoAl->format('Y-m-d') < $inizio->format('Y-m-d')))) {
                continue;
            }
            if ($possesso->utilizzo === UtilizzoImmobile::strumentale) {
                $this->avvisi[] = $possesso->immobile->getIdentificativo() . ': terreno strumentale all\'attività, non si dichiara nel quadro RA.';
                continue;
            }
            $perImmobile[$possesso->immobile->id]['immobile'] = $possesso->immobile;
            $perImmobile[$possesso->immobile->id]['possessi'][] = $possesso;
        }
        $iap = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($fine->format('Y-m-d')))?->coltivatoreIap ?? false;
        $righi = [];
        foreach ($perImmobile as ['immobile' => $immobile, 'possessi' => $possessi]) {
            $imuNonDovuta = $iap || $this->isEsenteImu($immobile, $inizio);
            $primo = true;
            foreach ($this->getSituazioni($immobile, $possessi, $inizio, $fine) as $situazione) {
                $rigo = new RigoTerreno($immobile->id, (int) round($situazione['dominicale']), $situazione['titolo'], (int) round($situazione['agrario']),
                        $situazione['dal']->diff($situazione['al'])->days + 1, round($situazione['possesso']->quota, 2));
                $rigo->continuazione = !$primo;
                $rigo->imuNonDovuta = $primo && $imuNonDovuta;
                $rigo->iap = $iap;
                $contratto = $situazione['contratto'];
                if ($contratto !== null) {
                    $rigo->casiParticolari = $contratto->giovaneAgricoltore ? 4 : null;
                    if ($contratto->tipo === TipoContrattoLocazione::affittoVincolistico) {
                        $rigo->canone = (int) round((new CanoniLocazione($this->dataMapper))->getCanonePeriodo($contratto, $situazione['dal'], $situazione['al']));
                    }
                }
                $righi[] = $rigo;
                $primo = false;
            }
        }
        return $righi;
    }

    private function isEsenteImu(Immobile $immobile, \DateTimeImmutable $data): bool
    {
        if ((ModuloSuite::imu->isDisponibile() === false) || (class_exists(CaratteristicheImuModel::class) === false)) {
            return false;
        }
        if ((new CaratteristicheImuModel($this->dataMapper))->getVigenteAl($immobile, $data)?->esenzione !== null) {
            return true;
        }
        foreach ((new ComuneEsenzioneTerreniModel($this->dataMapper))->getPerComune($immobile->comune->codiceCatastale) as $esenzione) {
            if ($esenzione->parziale === false) {
                return true;
            }
            $this->avvisi[] = $immobile->getIdentificativo() . ': comune ' . $immobile->comune->denominazione . ' parzialmente montano, verifica se il terreno è esente IMU (casella "IMU non dovuta").';
        }
        return false;
    }

    private function getSituazioni(Immobile $immobile, array $possessi, \DateTimeImmutable $inizio, \DateTimeImmutable $fine): array
    {
        $datiModel = new DatiCatastaliModel($this->dataMapper);
        $contratti = (new ContrattoLocazioneModel($this->dataMapper))->getInCorsoTra($immobile, $inizio, $fine);
        $date = [$inizio->format('Y-m-d') => true];
        $aggiungi = function (?\DateTimeInterface $data, bool $giornoDopo = false) use (&$date, $inizio, $fine): void {
            if ($data === null) {
                return;
            }
            $giorno = new \DateTimeImmutable($data->format('Y-m-d'));
            $giorno = $giornoDopo ? $giorno->modify('+1 day') : $giorno;
            if (($giorno > $inizio) && ($giorno <= $fine)) {
                $date[$giorno->format('Y-m-d')] = true;
            }
        };
        foreach ($possessi as $possesso) {
            $aggiungi($possesso->validoDal);
            $aggiungi($possesso->validoAl, true);
        }
        foreach ($datiModel->getStorico($immobile) as $dati) {
            $aggiungi($dati->validoDal);
            $aggiungi($dati->validoAl, true);
        }
        foreach ($contratti as $contratto) {
            $aggiungi($contratto->dataInizio);
            $aggiungi($contratto->dataFine, true);
        }
        ksort($date);
        $inizi = array_keys($date);
        $situazioni = [];
        foreach ($inizi as $indice => $dal) {
            $al = isset($inizi[$indice + 1]) ? (new \DateTimeImmutable($inizi[$indice + 1]))->modify('-1 day') : $fine;
            $dal = new \DateTimeImmutable($dal);
            $possesso = null;
            foreach ($possessi as $candidato) {
                if ($candidato->isVigenteAl($dal)) {
                    $possesso = $candidato;
                    break;
                }
            }
            if ($possesso === null) {
                continue;
            }
            $dati = $datiModel->getVigenteAl($immobile, $dal);
            if (($dati?->redditoDominicale === null) && ($dati?->redditoAgrario === null)) {
                $this->avvisi[] = $immobile->getIdentificativo() . ': redditi dominicale e agrario assenti dal ' . $dal->format('d/m/Y') . ', da indicare a mano.';
            }
            $contratto = null;
            foreach ($contratti as $candidato) {
                if ($candidato->isInCorsoTra($dal, $dal)) {
                    $contratto = $candidato;
                    break;
                }
            }
            $titolo = match ($contratto?->tipo) {
                TipoContrattoLocazione::affittoVincolistico => 2,
                TipoContrattoLocazione::affittoLibero => 3,
                default => 1,
            };
            $ultima = count($situazioni) - 1;
            if (($ultima >= 0) && ($situazioni[$ultima]['possesso'] === $possesso) && ($situazioni[$ultima]['contratto'] === $contratto)
                    && ($situazioni[$ultima]['dominicale'] === (float) ($dati?->redditoDominicale ?? 0)) && ($situazioni[$ultima]['agrario'] === (float) ($dati?->redditoAgrario ?? 0))) {
                $situazioni[$ultima]['al'] = $al;
                continue;
            }
            $situazioni[] = ['dal' => $dal, 'al' => $al, 'possesso' => $possesso, 'contratto' => $contratto, 'titolo' => $titolo,
                'dominicale' => (float) ($dati?->redditoDominicale ?? 0), 'agrario' => (float) ($dati?->redditoAgrario ?? 0)];
        }
        return $situazioni;
    }
}
