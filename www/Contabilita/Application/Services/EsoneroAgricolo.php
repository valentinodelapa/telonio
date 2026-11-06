<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Agricoltori esonerati (art. 34, c. 6, D.P.R. 633/1972): volume d'affari
 * dell'anno precedente non oltre 7.000 euro, per almeno due terzi di
 * cessioni di prodotti agricoli della Tabella A, parte I. Sono esonerati da
 * versamenti, liquidazioni e dichiarazione; superato nell'anno il limite di
 * un terzo per le operazioni diverse, la dichiarazione è dovuta (con le
 * percentuali di compensazione per i prodotti agricoli, senza il quadro
 * VH). La suite lo verifica sulle righe IVA registrate: se non ce ne sono
 * vale l'indicazione dei dati fiscali.
 */
class EsoneroAgricolo
{

    public const LIMITE_VOLUME = 7000.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function isEsonerato(Soggetto $soggetto, int $anno): bool
    {
        return (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->regimeDetrazioneIva === RegimeDetrazioneIva::agricoloEsonerato;
    }

    /**
     * Motivo per cui l'esonerato deve presentare la dichiarazione dell'anno,
     * null se resta esonerato.
     */
    public function getMotivoDichiarazione(Soggetto $soggetto, int $anno): ?string
    {
        if ($this->getVolumi($soggetto, $anno - 1)['totale'] > self::LIMITE_VOLUME) {
            return sprintf('Volume d\'affari del %d oltre 7.000 euro: l\'esonero non spetta.', $anno - 1);
        }
        $volumi = $this->getVolumi($soggetto, $anno);
        return self::isLimiteSuperato($volumi['agricole'], $volumi['diverse']) ? 'Operazioni diverse oltre un terzo del volume d\'affari: la dichiarazione è dovuta.' : null;
    }

    public static function isLimiteSuperato(float $agricole, float $diverse): bool
    {
        return $diverse * 3 > $agricole + $diverse;
    }

    /**
     * @return array{agricole: float, diverse: float, totale: float}
     */
    public function getVolumi(Soggetto $soggetto, int $anno): array
    {
        $agricole = $diverse = 0.0;
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                continue;
            }
            if (self::isAgricola($riga)) {
                $agricole += $riga->imponibile;
            } else {
                $diverse += $riga->imponibile;
            }
        }
        return ['agricole' => $agricole, 'diverse' => $diverse, 'totale' => $agricole + $diverse];
    }

    private static function isAgricola(RigaIva $riga): bool
    {
        return ($riga->percentualeCompensazione !== null) || ($riga->qualifica === QualificaOperazione::conferimentoAgricolo);
    }
}
