<?php

namespace Dichiarativi\Application\Services\Isa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\IndiceIsa;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\IndiceIsaModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Modelli ISA di una dichiarazione Redditi: uno per categoria di reddito
 * con il quadro contabile compilato (impresa: RG o RF; lavoro autonomo:
 * RE), con l'ISA dell'attività indicata nel quadro (rigo 1, codice
 * attività) secondo la tabella 1 delle istruzioni ISA (indice_isa). La
 * causa di esclusione indicata nel quadro (rigo 1, colonna 2) è riportata:
 * con le cause di esclusione il modello non si presenta, salvo i casi
 * previsti dalle istruzioni (attività d'impresa non comprese nello stesso
 * ISA oltre il 30% dei ricavi, gruppo IVA, professionisti che operano in
 * forma d'impresa).
 */
class ModelliIsa
{

    /** Quadri contabili di Redditi PF e tipologia di reddito degli ISA (1 impresa, 2 lavoro autonomo) */
    public const QUADRI_CONTABILI = ['RG' => 1, 'RF' => 1, 'RE' => 2];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return list<array{quadro: string, tipoReddito: int, codiceAttivita: ?string, causaEsclusione: ?string, indice: ?IndiceIsa, dichiarazione: ?Dichiarazione}>
     */
    public function getModelli(Dichiarazione $redditi, ?QuadriDichiarazione $quadri = null): array
    {
        if ($redditi->modello->isRedditi() === false) {
            return [];
        }
        $quadri ??= new QuadriDichiarazione($redditi, $this->dataMapper);
        $compilati = $quadri->getQuadriCompilati(1);
        $esistenti = $this->getDichiarazioni($redditi);
        $indiceModel = new IndiceIsaModel($this->dataMapper);
        $modelli = [];
        $tipi = [];
        foreach (self::QUADRI_CONTABILI as $quadro => $tipoReddito) {
            // un modello per categoria di reddito: per l'impresa il quadro RG prevale su RF
            if (!in_array($quadro, $compilati, true) || isset($tipi[$tipoReddito])) {
                continue;
            }
            $tipi[$tipoReddito] = true;
            $codiceAttivita = $quadri->get($quadro . '001001', 1);
            $indice = $codiceAttivita === null ? null : $indiceModel->getPerAttivita($redditi->getAnnoModello(), $codiceAttivita);
            $dichiarazione = null;
            foreach ($esistenti as $esistente) {
                if (($indice !== null) && ($esistente->isa === $indice->codice)) {
                    $dichiarazione = $esistente;
                }
            }
            $modelli[] = ['quadro' => $quadro, 'tipoReddito' => $tipoReddito, 'codiceAttivita' => $codiceAttivita, 'causaEsclusione' => $quadri->get($quadro . '001002', 1),
                'indice' => $indice, 'dichiarazione' => $dichiarazione];
        }
        return $modelli;
    }

    /**
     * Modelli ISA già creati per la dichiarazione Redditi.
     *
     * @return Dichiarazione[]
     */
    public function getDichiarazioni(Dichiarazione $redditi): array
    {
        return iterator_to_array((new DichiarazioneModel($this->dataMapper))->getByDichiarazioneRedditi($redditi), false);
    }

    /**
     * Crea il modello ISA del quadro contabile della dichiarazione Redditi
     * (o restituisce quello esistente) e ne propone i quadri.
     *
     * @throws \DomainException se il quadro non è compilato o l'attività non ha un ISA
     */
    public function crea(Dichiarazione $redditi, string $quadro): Dichiarazione
    {
        $modello = current(array_filter($this->getModelli($redditi), fn(array $modello) => $modello['quadro'] === $quadro));
        if ($modello === false) {
            throw new \DomainException('Quadro ' . $quadro . ' non compilato nella dichiarazione Redditi: nessun modello ISA da allegare.');
        }
        if ($modello['indice'] === null) {
            throw new \DomainException(sprintf('Nessun ISA in vigore per l\'attività %s del quadro %s (tabella 1 delle istruzioni ISA %d).', $modello['codiceAttivita'] ?? '-', $quadro, $redditi->getAnnoModello()));
        }
        if ($modello['dichiarazione'] !== null) {
            return $modello['dichiarazione'];
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $isa = new Dichiarazione($this->dataMapper);
        $isa->studio = $redditi->studio;
        $isa->soggetto = $redditi->soggetto;
        $isa->modello = ModelloDichiarativo::isa;
        $isa->anno = $redditi->anno;
        $isa->isa = $modello['indice']->codice;
        $isa->dichiarazioneRedditi = $redditi;
        $this->dataMapper->save($isa);
        (new PropostaIsa($this->dataMapper))->proponi($isa);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $isa;
    }
}
