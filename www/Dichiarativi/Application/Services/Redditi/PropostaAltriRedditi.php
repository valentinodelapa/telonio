<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Models\CertificazioneAutonomoModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RL dalle Certificazioni Uniche del lavoro autonomo e dei redditi
 * diversi (Services\Redditi\CalcoloQuadroRl), nel primo modulo; i totali
 * RL18 e RL19 e RL30-RL32 comprendono anche i righi inseriti a mano di
 * tutti i moduli (specifiche tecniche, righi RL18 e RL30).
 */
class PropostaAltriRedditi
{

    /** Colonne dei redditi e delle spese sommate nel rigo RL18 (righi da RL5 a RL17) */
    private const REDDITI_RL18 = ['RL005001', 'RL006001', 'RL006003', 'RL007001', 'RL008001', 'RL009001', 'RL011001', 'RL012002', 'RL012003', 'RL013001', 'RL014002', 'RL015002', 'RL016002', 'RL017001'];
    private const SPESE_RL18 = ['RL005002', 'RL006002', 'RL006004', 'RL007002', 'RL008002', 'RL009002', 'RL010002', 'RL010005', 'RL014003', 'RL015003', 'RL016003', 'RL017002'];

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

    public function proponi(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $certificazioni = (new CertificazioneAutonomoModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $calcolo = new CalcoloQuadroRl();
        $natoDopo1990 = ($soggetto->dataNascita !== null) && ($soggetto->dataNascita->format('Y-m-d') > '1990-12-31');
        $quadri->usaModulo(1);
        foreach ($calcolo->calcola($certificazioni, $natoDopo1990) as $codice => $valore) {
            $quadri->proponi($codice, $valore);
        }
        $this->avvisi = $calcolo->getAvvisi();
        // totali di tutti i moduli, compresi i righi inseriti a mano
        $somma = function (array $codici) use ($quadri): int {
            $totale = 0;
            foreach ($quadri->getModuli() as $modulo) {
                foreach ($codici as $codice) {
                    $totale += $quadri->getImporto($codice, $modulo);
                }
                // rigo RL10, col. 4: solo senza la cedolare secca (col. 3)
                if (in_array('RL011001', $codici, true) && ($quadri->get('RL010003', $modulo) === null)) {
                    $totale += $quadri->getImporto('RL010001', $modulo) + $quadri->getImporto('RL010004', $modulo);
                }
            }
            return $totale;
        };
        $redditi = $somma(self::REDDITI_RL18);
        $spese = $somma(self::SPESE_RL18);
        $quadri->proponi('RL018001', $redditi ?: null);
        $quadri->proponi('RL018002', $spese ?: null);
        $quadri->proponi('RL019001', max(0, $redditi - $spese) ?: null);
        $totale = $somma(['RL025002', 'RL026003', 'RL027002']);
        if ($totale > 0) {
            $quadri->proponi('RL030001', $totale);
            $deduzioni = (int) round($somma(['RL025002']) * ($natoDopo1990 ? 0.40 : 0.25));
            foreach ($quadri->getModuli() as $modulo) {
                if ($quadri->getImporto('RL026002', $modulo) === 1) {
                    $deduzioni += (int) round($quadri->getImporto('RL026003', $modulo) * 0.15);
                }
            }
            $quadri->proponi('RL031001', $deduzioni ?: null);
            $quadri->proponi('RL032001', max(0, $totale - $deduzioni) ?: null);
        }
    }
}
