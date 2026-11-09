<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Models\ContrattoLocazioneModel;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\CanoniLocazione;
use Imu\Application\Models\CaratteristicheImuModel;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Enumerations\ModuloSuite;

/**
 * Righi del quadro RB dai possessi dei fabbricati del contribuente
 * nell'anno (Anagrafica), con i dati catastali, i contratti di locazione e,
 * se il modulo IMU è installato, le caratteristiche IMU dell'immobile.
 *
 * Ogni situazione diversa dell'anno (quota, utilizzo, rendita, contratto,
 * cedolare secca, esenzione IMU) è un rigo con la casella "continuazione"
 * (istruzioni RB, sezione I). Non si dichiarano la nuda proprietà, i
 * fabbricati rurali strumentali e quelli strumentali all'attività, le
 * unità del gruppo F.
 *
 * Utilizzo (col. 2): 1 abitazione principale, 5 pertinenza, 2 a
 * disposizione o in comodato a terzi (abitativi), 9 altri casi, 10
 * comodato a un familiare; con un contratto 3 canone libero e locazioni
 * brevi, 4 equo canone, 8 concordato, 14 Abruzzo, 11 e 12 se l'immobile è
 * anche abitazione principale. Casi particolari IMU (col. 12): 2 per le
 * abitazioni principali A/1, A/8 e A/9 e le loro pertinenze, 1 per i
 * fabbricati esenti, 3 per le abitazioni non locate nel comune
 * dell'abitazione principale (art. 9, c. 9, D.Lgs. 23/2011).
 */
class PropostaFabbricati
{

    private const CATEGORIE_LUSSO = [CategoriaCatastale::a1, CategoriaCatastale::a8, CategoriaCatastale::a9];

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
     * @return RigoFabbricato[]
     */
    public function getRighi(Soggetto $soggetto, int $anno): array
    {
        $this->avvisi = [];
        $inizio = new \DateTimeImmutable($anno . '-01-01');
        $fine = new \DateTimeImmutable($anno . '-12-31');
        $perImmobile = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            if (($possesso->immobile->tipoCatasto !== TipoCatasto::fabbricati) || ($possesso->titolo === TitoloPossesso::nudaProprieta)
                    || ($possesso->validoDal->format('Y-m-d') > $fine->format('Y-m-d')) || (($possesso->validoAl !== null) && ($possesso->validoAl->format('Y-m-d') < $inizio->format('Y-m-d')))) {
                continue;
            }
            $perImmobile[$possesso->immobile->id]['immobile'] = $possesso->immobile;
            $perImmobile[$possesso->immobile->id]['possessi'][] = $possesso;
        }
        $situazioni = [];
        foreach ($perImmobile as ['immobile' => $immobile, 'possessi' => $possessi]) {
            array_push($situazioni, ...$this->getSituazioni($immobile, $possessi, $inizio, $fine));
        }
        $this->impostaCasiImu($situazioni);
        $righi = [];
        foreach ($situazioni as $situazione) {
            $righi[] = $this->creaRigo($situazione, $righi);
        }
        // abitazioni principali e pertinenze prima, poi nell'ordine dell'archivio
        return $righi;
    }

    /**
     * Situazioni dell'immobile nell'anno, ciascuna con il periodo, il
     * possesso, i dati catastali, il contratto e le caratteristiche IMU.
     */
    private function getSituazioni(Immobile $immobile, array $possessi, \DateTimeImmutable $inizio, \DateTimeImmutable $fine): array
    {
        $datiModel = new DatiCatastaliModel($this->dataMapper);
        $contratti = (new ContrattoLocazioneModel($this->dataMapper))->getInCorsoTra($immobile, $inizio, $fine);
        $caratteristicheModel = ModuloSuite::imu->isDisponibile() && class_exists(CaratteristicheImuModel::class) ? new CaratteristicheImuModel($this->dataMapper) : null;
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
            $aggiungi($contratto->cedolareRevocataDal);
        }
        foreach ($caratteristicheModel?->getStorico($immobile) ?? [] as $caratteristiche) {
            $aggiungi($caratteristiche->validoDal);
            $aggiungi($caratteristiche->validoAl, true);
        }
        ksort($date);
        $inizi = array_keys($date);
        $situazioni = [];
        foreach ($inizi as $indice => $dal) {
            $al = isset($inizi[$indice + 1]) ? (new \DateTimeImmutable($inizi[$indice + 1]))->modify('-1 day') : $fine;
            $dal = new \DateTimeImmutable($dal);
            $possesso = $this->getPossessoAl($possessi, $dal);
            if ($possesso === null) {
                continue;
            }
            $dati = $datiModel->getVigenteAl($immobile, $dal);
            $caratteristiche = $caratteristicheModel?->getVigenteAl($immobile, $dal);
            $descrizione = $immobile->getIdentificativo() . ' (' . $immobile->comune->denominazione . ')';
            if (($dati?->categoria !== null) && ($dati->categoria->haRendita() === false)) {
                continue;
            }
            if ($possesso->utilizzo === UtilizzoImmobile::strumentale) {
                $this->avvisi[] = $descrizione . ': strumentale all\'attività, non si dichiara nel quadro RB.';
                continue;
            }
            if ($caratteristiche?->ruraleStrumentale === true) {
                continue;
            }
            if (($dati === null) || ($dati->rendita === null)) {
                $this->avvisi[] = $descrizione . ': rendita catastale assente dal ' . $dal->format('d/m/Y') . ', da indicare a mano (anche presunta).';
            }
            $contratto = null;
            foreach ($contratti as $candidato) {
                if ($candidato->isInCorsoTra($dal, $dal)) {
                    $contratto = $candidato;
                    break;
                }
            }
            $situazioni[] = ['immobile' => $immobile, 'dal' => $dal, 'al' => $al, 'possesso' => $possesso, 'categoria' => $dati?->categoria,
                'rendita' => (float) ($dati?->rendita ?? 0.0) * ($caratteristiche?->storicoArtistico === true ? 0.5 : 1.0), 'storico' => $caratteristiche?->storicoArtistico === true,
                'esenteImu' => $caratteristiche?->esenzione !== null, 'contratto' => $contratto, 'casiImu' => null];
        }
        return $this->unisci($situazioni);
    }

    /**
     * Unisce le situazioni consecutive uguali (stessi possesso, rendita e
     * contratto): i periodi in cui nulla cambia sono un solo rigo.
     */
    private function unisci(array $situazioni): array
    {
        $unite = [];
        foreach ($situazioni as $situazione) {
            $ultima = count($unite) - 1;
            if (($ultima >= 0) && ($unite[$ultima]['possesso'] === $situazione['possesso']) && ($unite[$ultima]['rendita'] === $situazione['rendita'])
                    && ($unite[$ultima]['contratto'] === $situazione['contratto']) && ($unite[$ultima]['esenteImu'] === $situazione['esenteImu'])
                    && ($unite[$ultima]['al']->modify('+1 day')->format('Y-m-d') === $situazione['dal']->format('Y-m-d'))
                    && (($situazione['contratto'] === null) || ($situazione['contratto']->isCedolareAl($unite[$ultima]['dal']) === $situazione['contratto']->isCedolareAl($situazione['dal'])))) {
                $unite[$ultima]['al'] = $situazione['al'];
                continue;
            }
            $unite[] = $situazione;
        }
        return $unite;
    }

    private function getPossessoAl(array $possessi, \DateTimeImmutable $data): ?Possesso
    {
        foreach ($possessi as $possesso) {
            if ($possesso->isVigenteAl($data)) {
                return $possesso;
            }
        }
        return null;
    }

    /**
     * Casi particolari IMU, che dipendono dall'abitazione principale del
     * contribuente nello stesso periodo.
     */
    private function impostaCasiImu(array &$situazioni): void
    {
        $principali = array_filter($situazioni, fn(array $situazione) => $situazione['possesso']->utilizzo === UtilizzoImmobile::abitazionePrincipale);
        $principaleAl = function (\DateTimeImmutable $data) use ($principali): ?array {
            foreach ($principali as $principale) {
                if (($principale['dal'] <= $data) && ($principale['al'] >= $data)) {
                    return $principale;
                }
            }
            return null;
        };
        foreach ($situazioni as &$situazione) {
            $utilizzo = $situazione['possesso']->utilizzo;
            $lusso = in_array($situazione['categoria'], self::CATEGORIE_LUSSO, true);
            if ($utilizzo === UtilizzoImmobile::abitazionePrincipale) {
                $situazione['casiImu'] = $lusso ? 2 : null;
            } elseif ($utilizzo === UtilizzoImmobile::pertinenzaAbitazionePrincipale) {
                $situazione['casiImu'] = in_array($principaleAl($situazione['dal'])['categoria'] ?? null, self::CATEGORIE_LUSSO, true) ? 2 : null;
            } elseif ($situazione['esenteImu']) {
                $situazione['casiImu'] = 1;
            } elseif (($situazione['contratto'] === null) && ($situazione['categoria']?->isAbitativa() === true)
                    && (($principaleAl($situazione['dal'])['immobile'] ?? null)?->comune->codiceCatastale === $situazione['immobile']->comune->codiceCatastale)) {
                $situazione['casiImu'] = 3;
            }
        }
    }

    /**
     * @param RigoFabbricato[] $precedenti righi già creati (continuazione)
     */
    private function creaRigo(array $situazione, array $precedenti): RigoFabbricato
    {
        $possesso = $situazione['possesso'];
        $immobile = $situazione['immobile'];
        $contratto = $situazione['contratto'];
        $giorni = $situazione['dal']->diff($situazione['al'])->days + 1;
        $utilizzo = $this->getUtilizzo($possesso, $situazione['categoria'], $contratto, $immobile);
        $rigo = new RigoFabbricato($immobile->id, (int) round($situazione['rendita']), $utilizzo, min(365, $giorni), round($possesso->quota, 2));
        $rigo->continuazione = count(array_filter($precedenti, fn(RigoFabbricato $altro) => $altro->fabbricato === $immobile->id)) > 0;
        $rigo->codiceComune = $rigo->continuazione ? null : $immobile->comune->codiceCatastale;
        $rigo->casiImu = $situazione['casiImu'];
        if (($contratto !== null) && in_array($utilizzo, [3, 4, 8, 11, 12, 14], true)) {
            $cedolare = $contratto->isCedolareAl($situazione['dal']);
            $canoni = new CanoniLocazione($this->dataMapper);
            $quota = $canoni->getRipartizione($contratto, $situazione['dal'])[$immobile->id] ?? 1.0;
            $canone = $canoni->getCanonePeriodo($contratto, $situazione['dal'], $situazione['al']) * $quota;
            [$rigo->codiceCanone, $percentuale] = match (true) {
                $cedolare => [3, 1.0],
                $situazione['storico'] => [4, 0.65],
                $contratto->veneziaCentro => [2, 0.75],
                default => [1, 0.95],
            };
            $rigo->canone = (int) round($canone * $percentuale);
            if ($cedolare) {
                $rigo->cedolare = $contratto->tipo === TipoContrattoLocazione::locazioneBreve ? ($contratto->cedolareRidotta ? 2 : 3) : 1;
            }
            $rigo->altriDati = match (true) {
                $contratto->emergenzaCalamita && $cedolare && in_array($utilizzo, [8, 12], true) => 1,
                $contratto->tipo === TipoContrattoLocazione::locazioneBreve => 2,
                default => null,
            };
            $rigo->contratto = $contratto;
        }
        return $rigo;
    }

    private function getUtilizzo(Possesso $possesso, ?CategoriaCatastale $categoria, ?ContrattoLocazione $contratto, Immobile $immobile): int
    {
        $principale = $possesso->utilizzo === UtilizzoImmobile::abitazionePrincipale;
        if ($contratto !== null) {
            // pertinenza di una locazione breve: il canone va all'abitazione, la pertinenza ha l'utilizzo 9
            if (($contratto->tipo === TipoContrattoLocazione::locazioneBreve) && ($contratto->immobile->id !== $immobile->id)) {
                return 9;
            }
            return match ($contratto->tipo) {
                TipoContrattoLocazione::canoneConcordato => $principale ? 12 : 8,
                TipoContrattoLocazione::equoCanone => 4,
                TipoContrattoLocazione::abruzzo => 14,
                default => $principale ? 11 : 3,
            };
        }
        $abitativa = $categoria?->isAbitativa() ?? true;
        return match ($possesso->utilizzo) {
            UtilizzoImmobile::abitazionePrincipale => 1,
            UtilizzoImmobile::pertinenzaAbitazionePrincipale => 5,
            UtilizzoImmobile::comodatoFamiliare => 10,
            UtilizzoImmobile::comodato, UtilizzoImmobile::aDisposizione, null => $abitativa ? 2 : 9,
            UtilizzoImmobile::locatoLiberoMercato, UtilizzoImmobile::locatoCanoneConcordato => $this->senzaContratto($immobile, $abitativa),
            default => 9,
        };
    }

    private function senzaContratto(Immobile $immobile, bool $abitativa): int
    {
        $this->avvisi[] = $immobile->getIdentificativo() . ' (' . $immobile->comune->denominazione . '): utilizzo "locato" senza un contratto di locazione nel periodo; registra il contratto nell\'Anagrafica per proporre il canone.';
        return $abitativa ? 2 : 9;
    }
}
