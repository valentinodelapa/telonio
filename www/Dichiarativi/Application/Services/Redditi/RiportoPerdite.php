<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\PerditaPregressaModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Riporto delle perdite fiscali (art. 84 Tuir): residui alla fine
 * dell'anno prima dal prospetto delle perdite non compensate della
 * dichiarazione di quell'anno nella suite (Redditi PF righi RS12 e RS13,
 * col. 2; Redditi SC righi RS44 e RS45, col. 6; modello CNM rigo CS1, col. 2
 * e 4) o, se non c'è, dall'archivio delle perdite pregresse del contribuente
 * (PerditaPregressa).
 */
class RiportoPerdite
{

    /** Campi dei residui (misura limitata, misura piena) del prospetto delle perdite per modello */
    public const RESIDUI = [
        'RPF' => ['RS012002', 'RS013002'],
        'RSC' => ['RS044006', 'RS045006'],
        'ENC' => ['RS010002', 'RS011002'],
        'CNM' => ['CS001002', 'CS001004'],
    ];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Perdite residue alla fine dell'anno prima di quello della
     * dichiarazione; null se né la dichiarazione dell'anno prima né
     * l'archivio le indicano.
     *
     * @return array{limitata: int, piena: int, fonte: string}|null
     */
    public function getResidui(Soggetto $soggetto, int $anno, ModelloDichiarativo $modello): ?array
    {
        $campi = self::RESIDUI[$modello->value] ?? null;
        if ($campi === null) {
            return null;
        }
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, $modello, $anno - 1);
        if ($precedente !== null) {
            $quadri = new QuadriDichiarazione($precedente, $this->dataMapper);
            return ['limitata' => $quadri->getImporto($campi[0], 1), 'piena' => $quadri->getImporto($campi[1], 1), 'fonte' => 'dichiarazione ' . $modello->getLabel() . ' dell\'anno ' . ($anno - 1)];
        }
        $tipo = $modello === ModelloDichiarativo::consolidato ? TipoPerdita::consolidato : TipoPerdita::impresa;
        $archivio = (new PerditaPregressaModel($this->dataMapper))->getPerAnno($soggetto, $anno - 1, $tipo);
        if ($archivio !== null) {
            return ['limitata' => $archivio->limitata, 'piena' => $archivio->piena, 'fonte' => 'archivio delle perdite pregresse (anno ' . ($anno - 1) . ')'];
        }
        return null;
    }
}
