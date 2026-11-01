<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Apertura della contabilità di un'azienda: copia del piano dei conti
 * modello scelto, registri IVA principali (vendite, acquisti,
 * corrispettivi) e primo esercizio. Gli esercizi successivi si aprono con
 * apriEsercizio.
 */
class AperturaContabilita
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \DomainException se la contabilità è già aperta o le date non sono valide
     */
    public function apri(Soggetto $soggetto, PianoConti $modello, SismaDate $inizio, SismaDate $fine): EsercizioContabile
    {
        if ((new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto) !== null) {
            throw new \DomainException('La contabilità dell\'azienda è già aperta.');
        }
        if ($modello->isModello() === false) {
            throw new \DomainException('Scegliere un piano dei conti modello.');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        (new CopiaPianoConti($this->dataMapper))->copia($modello, $soggetto->studio, $soggetto, 'Piano dei conti di ' . $soggetto->nominativo);
        $registroModel = new RegistroIvaModel($this->dataMapper);
        foreach (TipoRegistroIva::cases() as $tipo) {
            if ($registroModel->getRegistro($soggetto, $tipo) === null) {
                $registro = new RegistroIva($this->dataMapper);
                $registro->studio = $soggetto->studio;
                $registro->soggetto = $soggetto;
                $registro->tipo = $tipo;
                $registro->descrizione = $tipo->getLabel();
                $this->dataMapper->save($registro);
            }
        }
        $esercizio = $this->apriEsercizio($soggetto, $inizio, $fine);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esercizio;
    }

    /**
     * Nuovo esercizio dell'azienda; di norma segue l'ultimo senza
     * interruzioni.
     *
     * @throws \DomainException se le date non sono valide o l'esercizio si sovrappone a un altro
     */
    public function apriEsercizio(Soggetto $soggetto, SismaDate $inizio, SismaDate $fine): EsercizioContabile
    {
        if ($fine->format('Y-m-d') < $inizio->format('Y-m-d')) {
            throw new \DomainException('La fine dell\'esercizio precede l\'inizio.');
        }
        if ((new \DateTimeImmutable($inizio->format('Y-m-d')))->modify('+18 months')->format('Y-m-d') <= $fine->format('Y-m-d')) {
            throw new \DomainException('L\'esercizio non può superare i diciotto mesi.');
        }
        $model = new EsercizioContabileModel($this->dataMapper);
        foreach ($model->getElenco($soggetto) as $esistente) {
            if (($inizio->format('Y-m-d') <= $esistente->dataFine->format('Y-m-d')) && ($fine->format('Y-m-d') >= $esistente->dataInizio->format('Y-m-d'))) {
                throw new \DomainException('L\'esercizio si sovrappone all\'esercizio ' . $esistente->getEtichetta() . '.');
            }
        }
        $esercizio = new EsercizioContabile($this->dataMapper);
        $esercizio->studio = $soggetto->studio;
        $esercizio->soggetto = $soggetto;
        $esercizio->anno = (int) $fine->format('Y');
        $esercizio->dataInizio = $inizio;
        $esercizio->dataFine = $fine;
        if ($model->getPerAnno($soggetto, $esercizio->anno) !== null) {
            throw new \DomainException('Esiste già un esercizio che si chiude nel ' . $esercizio->anno . '.');
        }
        $this->dataMapper->save($esercizio);
        return $esercizio;
    }

    /**
     * Date proposte per l'esercizio successivo all'ultimo (o per l'anno
     * indicato se non ce ne sono).
     *
     * @return array{0: SismaDate, 1: SismaDate}
     */
    public function proponiEsercizio(Soggetto $soggetto, int $anno): array
    {
        $ultimo = (new EsercizioContabileModel($this->dataMapper))->getUltimo($soggetto);
        if ($ultimo === null) {
            return [new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')];
        }
        $inizio = (new \DateTimeImmutable($ultimo->dataFine->format('Y-m-d')))->modify('+1 day');
        return [new SismaDate($inizio->format('Y-m-d')), new SismaDate($inizio->modify('+1 year -1 day')->format('Y-m-d'))];
    }
}
