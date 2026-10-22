<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\StoricoService;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\QuadroBene;

/**
 * Volture della successione riportate nell'archivio immobili
 * dell'Anagrafica, proposte dopo la presentazione e applicate solo su
 * conferma:
 * - i possessi del defunto in vigore al decesso si chiudono alla data del
 *   decesso (compresi usufrutto, uso e abitazione, che si estinguono);
 * - ogni beneficiario riceve dal giorno successivo un possesso con il
 *   titolo del diritto devoluto e la quota del defunto per la quota
 *   devoluta; se possiede già l'immobile con lo stesso titolo la quota si
 *   somma in una nuova versione del suo possesso.
 *
 * La riunione dell'usufrutto alla nuda proprietà (art. 979 c.c.) e le
 * volture degli immobili non presenti nell'archivio restano da registrare
 * a mano.
 */
class PropostaVolture
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array<array{tipo: string, possesso: ?Possesso, immobile: \Anagrafica\Application\Entities\Immobile, soggetto: \Anagrafica\Application\Entities\Soggetto, titolo: TitoloPossesso, quotaPrima: float, quotaDopo: float, data: SismaDate}>
     */
    public function proponi(DatiDichiarazione $dati): array
    {
        $dichiarazione = $dati->dichiarazione;
        $decesso = $dichiarazione->getDataApertura();
        $dal = new SismaDate((new \DateTimeImmutable($decesso->format('Y-m-d')))->modify('+1 day')->format('Y-m-d'));
        $possessoModel = new PossessoModel($this->dataMapper);
        $operazioni = [];
        $immobili = [];
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati, QuadroBene::terreniTavolare, QuadroBene::fabbricatiTavolare] as $quadro) {
            foreach ($dati->getBeniQuadro($quadro) as $bene) {
                if ($bene->immobile !== null) {
                    $immobili[$bene->immobile->id] = $bene->immobile;
                }
            }
        }
        foreach ($possessoModel->getStoricoSoggetto($dichiarazione->defunto) as $possesso) {
            if ($possesso->isVigenteAl($decesso) && (($possesso->validoAl === null) || ($possesso->validoAl > $decesso))) {
                $operazioni[] = ['tipo' => 'chiusura', 'possesso' => $possesso, 'immobile' => $possesso->immobile, 'soggetto' => $possesso->soggetto, 'titolo' => $possesso->titolo,
                    'quotaPrima' => $possesso->quota, 'quotaDopo' => 0.0, 'data' => $decesso];
            }
        }
        $acquisti = [];
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati, QuadroBene::terreniTavolare, QuadroBene::fabbricatiTavolare] as $quadro) {
            foreach ($dati->getBeniQuadro($quadro) as $bene) {
                if ($bene->immobile === null) {
                    continue;
                }
                foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                    $titolo = $devoluzione->codiceDiritto->getTitoloPossesso();
                    if ($titolo === null) {
                        continue;
                    }
                    $soggetto = $devoluzione->beneficiarioSuccessione->soggetto;
                    $chiave = $bene->immobile->id . '|' . $soggetto->id . '|' . $titolo->value;
                    $acquisti[$chiave] ??= ['immobile' => $bene->immobile, 'soggetto' => $soggetto, 'titolo' => $titolo, 'quota' => 0.0];
                    $acquisti[$chiave]['quota'] += $bene->getQuota() * $devoluzione->getQuota() * 100;
                }
            }
        }
        $this->arrotonda($acquisti);
        foreach ($acquisti as $acquisto) {
            $esistente = null;
            foreach ($possessoModel->getByImmobileAndSoggettoAndTitolo($acquisto['immobile'], $acquisto['soggetto'], $acquisto['titolo']) as $possesso) {
                if ($possesso->isVigenteAl($decesso)) {
                    $esistente = $possesso;
                }
            }
            $quotaPrima = $esistente?->quota ?? 0.0;
            $operazioni[] = ['tipo' => $esistente === null ? 'nuovo' : 'variazione', 'possesso' => $esistente, 'immobile' => $acquisto['immobile'], 'soggetto' => $acquisto['soggetto'],
                'titolo' => $acquisto['titolo'], 'quotaPrima' => $quotaPrima, 'quotaDopo' => min(100.0, round($quotaPrima + $acquisto['quota'], 2)), 'data' => $dal];
        }
        return $operazioni;
    }

    /**
     * Quote in percentuale con due decimali: per ogni immobile e titolo il
     * resto dell'arrotondamento va all'ultima quota, così la somma coincide
     * con la quota trasferita.
     */
    private function arrotonda(array &$acquisti): void
    {
        $gruppi = [];
        foreach ($acquisti as $chiave => $acquisto) {
            $gruppi[$acquisto['immobile']->id . '|' . $acquisto['titolo']->value][] = $chiave;
        }
        foreach ($gruppi as $chiavi) {
            $totale = round(array_sum(array_map(fn(string $chiave) => $acquisti[$chiave]['quota'], $chiavi)), 2);
            $assegnato = 0.0;
            foreach ($chiavi as $indice => $chiave) {
                $acquisti[$chiave]['quota'] = $indice === array_key_last($chiavi) ? round($totale - $assegnato, 2) : round($acquisti[$chiave]['quota'], 2);
                $assegnato += $acquisti[$chiave]['quota'];
            }
        }
    }

    /**
     * Applica le volture proposte e segna la dichiarazione.
     */
    public function applica(DatiDichiarazione $dati): int
    {
        $operazioni = $this->proponi($dati);
        $possessoModel = new PossessoModel($this->dataMapper);
        $storico = new StoricoService($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($operazioni as $operazione) {
            if ($operazione['tipo'] === 'chiusura') {
                $operazione['possesso']->validoAl = $operazione['data'];
                $this->dataMapper->save($operazione['possesso']);
                continue;
            }
            $possesso = new Possesso($this->dataMapper);
            $possesso->immobile = $operazione['immobile'];
            $possesso->soggetto = $operazione['soggetto'];
            $possesso->titolo = $operazione['titolo'];
            $possesso->quota = $operazione['quotaDopo'];
            $possesso->validoDal = $operazione['data'];
            $possesso->utilizzo = $operazione['possesso']?->utilizzo;
            $possesso->note = 'Successione di ' . $dati->dichiarazione->defunto->nominativo;
            $ambito = $possessoModel->getAmbito($possesso);
            $errore = $storico->verifica($possesso, $ambito);
            if ($errore !== null) {
                throw new \RuntimeException($operazione['immobile']->getIdentificativo() . ', ' . $operazione['soggetto']->nominativo . ': ' . $errore);
            }
            $storico->registra($possesso, $ambito);
        }
        $dati->dichiarazione->voltureApplicate = new SismaDateTime();
        $this->dataMapper->save($dati->dichiarazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return count($operazioni);
    }
}
