<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\CessioneFinanziaria;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\CessioneFinanziariaModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RT dall'archivio delle cessioni del contribuente
 * (Services\Redditi\CalcoloQuadroRt), con le minusvalenze residue e le
 * eccedenze d'imposta della dichiarazione dell'anno prima nella suite
 * (righi RT102 e RT105, colonna 5 dei righi RX20 e RX21: ogni anno scala
 * di una colonna e la più vecchia scade), e righi RX20 e RX21.
 */
class PropostaPlusvalenze
{

    /** @var CessioneFinanziaria[] */
    private array $cessioni = [];

    /** @var array{campi: array<string, int|bool>, righe12: list<array>, righe42: list<array>}|null */
    private ?array $calcolo = null;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function carica(Soggetto $soggetto, int $anno): self
    {
        $this->cessioni = (new CessioneFinanziariaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiPf, $anno - 1);
        $titoli = $cripto = [];
        $eccedenzaTitoli = $eccedenzaCripto = 0;
        if ($precedente !== null) {
            $quadri = new QuadriDichiarazione($precedente, $this->dataMapper);
            for ($colonna = 1; $colonna <= 4; $colonna++) {
                $titoli[$colonna] = $quadri->getImporto(sprintf('RT102%03d', $colonna + 1), 1);
            }
            foreach ([3, 4] as $colonna) {
                $cripto[$colonna] = $quadri->getImporto(sprintf('RT105%03d', $colonna + 1), 1);
            }
            $eccedenzaTitoli = $quadri->getImporto('RX020005', 1);
            $eccedenzaCripto = $quadri->getImporto('RX021005', 1);
        }
        $this->calcolo = (count($this->cessioni) > 0) || (array_sum($titoli) + array_sum($cripto) + $eccedenzaTitoli + $eccedenzaCripto > 0)
                ? CalcoloQuadroRt::calcola($this->cessioni, array_filter($titoli), array_filter($cripto), $eccedenzaTitoli, $eccedenzaCripto) + ['eccedenze' => [$eccedenzaTitoli, $eccedenzaCripto]]
                : null;
        return $this;
    }

    public function getModuliNecessari(): int
    {
        return $this->calcolo === null ? 0 : max(count($this->calcolo['righe12']), count($this->calcolo['righe42']));
    }

    public function proponi(QuadriDichiarazione $quadri): void
    {
        if ($this->calcolo === null) {
            return;
        }
        foreach (['righe12' => 'RT012', 'righe42' => 'RT042'] as $chiave => $rigo) {
            foreach ($this->calcolo[$chiave] as $indice => $colonne) {
                $quadri->usaModulo($indice + 1);
                foreach ($colonne as $colonna => $valore) {
                    $quadri->proponi(sprintf('%s%03d', $rigo, $colonna), $valore ?: null);
                }
            }
        }
        $quadri->usaModulo(1);
        foreach ($this->calcolo['campi'] as $codice => $valore) {
            $quadri->proponi($codice, $valore);
        }
        [$eccedenzaTitoli, $eccedenzaCripto] = $this->calcolo['eccedenze'];
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        // riepilogo dei crediti e quadro RX
        $quadri->proponi('RT111001', $eccedenzaTitoli ?: null);
        $residuo = $q('RT111001') - $q('RT111002') - $q('RT005001') - $q('RT015001');
        $quadri->proponi('RT111003', $residuo > 0 ? $residuo : null);
        $quadri->proponi('RT112001', $eccedenzaCripto ?: null);
        $residuoCripto = $q('RT112001') - $q('RT045001');
        $quadri->proponi('RT112003', $residuoCripto > 0 ? $residuoCripto : null);
        foreach ([20 => $q('RT064001') + $q('RT074001') - $q('RT111003'), 21 => $q('RT090001') - $q('RT112003')] as $rigo => $imposta) {
            $rx = fn(int $colonna) => sprintf('RX%03d%03d', $rigo, $colonna);
            $quadri->proponi($rx(1), $imposta > 0 ? $imposta : null);
            $quadri->proponi($rx(2), $imposta < 0 ? -$imposta : null);
            $quadri->proponi($rx(5), max(0, max(0, -$imposta) + $q($rx(3)) - $q($rx(4))) ?: null);
        }
    }
}
