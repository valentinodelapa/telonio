<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Models\AbbinamentoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Partite delle registrazioni anteriori alle partite aperte: in ordine
 * di giornale, ogni registrazione senza partite né abbinamenti apre le
 * sue partite e chiude quelle aperte dalla più vecchia (FIFO), con
 * l'eccedenza come acconto. Gli abbinamenti sono segnati come automatici,
 * da verificare nello scadenzario.
 */
class MigrazionePartite
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
     * @return int registrazioni con partite create
     */
    public function esegui(Soggetto $soggetto): int
    {
        $partite = new PartiteAperte($this->dataMapper);
        $partitaModel = new PartitaModel($this->dataMapper);
        $abbinamentoModel = new AbbinamentoModel($this->dataMapper);
        $righeModel = new RigaContabileModel($this->dataMapper);
        $create = 0;
        $registrazioni = (new RegistrazioneContabileModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate('1900-01-01'), new SismaDate('2999-12-31'));
        foreach ($registrazioni as $registrazione) {
            if (($partitaModel->countByRegistrazioneContabile($registrazione) > 0) || ($abbinamentoModel->countByRegistrazioneContabile($registrazione) > 0)) {
                continue;
            }
            $righe = iterator_to_array($righeModel->getRighe($registrazione), false);
            $gruppi = $partite->getGruppi($registrazione, $righe);
            if (count($gruppi) === 0) {
                continue;
            }
            $scelte = $partite->proponi($registrazione, $gruppi);
            $errori = $partite->verifica($registrazione, $gruppi, $scelte);
            if (count($errori) > 0) {
                $this->avvisi[] = 'Registrazione n. ' . $registrazione->numero . ' del ' . $registrazione->dataRegistrazione->format('d/m/Y') . ': ' . implode(' ', $errori);
                continue;
            }
            $transazione = $this->dataMapper->startTransaction();
            $partite->registra($registrazione, $gruppi, $scelte, true);
            $this->dataMapper->commitTransaction($transazione);
            $create++;
        }
        return $create;
    }
}
