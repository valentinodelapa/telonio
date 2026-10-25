<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Models\DatiCatastaliModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Classamento dell'immobile ai fini IMU. Valgono le rendite e i redditi
 * dominicali vigenti al 1° gennaio dell'anno (art. 1, c. 745 e 746, L.
 * 160/2019), salvo:
 * - le variazioni di rendita per interventi edilizi, efficaci dalla data di
 *   ultimazione dei lavori o, se antecedente, di utilizzo (comma 745, ultimo
 *   periodo), cioè dalla decorrenza della versione;
 * - il primo classamento (costituzione), che vale dalla sua decorrenza: per
 *   i mesi di possesso anteriori si presume lo stesso classamento (con
 *   avviso), salvo per i fabbricati a valore contabile, la cui rendita vale
 *   solo dall'attribuzione.
 *
 * Una variazione che non arriva a produrre effetti perché ne segue un'altra
 * efficace prima (es. variazione d'ufficio a maggio e intervento edilizio a
 * settembre) non si applica.
 */
class ClassamentoImu
{

    /** @var array<int, array<int, array{datiCatastali: DatiCatastali, dal: string}>> */
    private array $storici = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Data da cui la versione vale per l'IMU (Y-m-d).
     */
    public static function getDecorrenza(DatiCatastali $datiCatastali): string
    {
        $dal = $datiCatastali->validoDal->format('Y-m-d');
        if (($datiCatastali->causale !== CausaleClassamento::altraVariazione) || str_ends_with($dal, '-01-01')) {
            return $dal;
        }
        return ((int) substr($dal, 0, 4) + 1) . '-01-01';
    }

    /**
     * Versioni che si applicano, in ordine di decorrenza IMU.
     *
     * @return array<int, array{datiCatastali: DatiCatastali, dal: string}>
     */
    public function getStorico(Immobile $immobile): array
    {
        if (isset($this->storici[$immobile->id]) === false) {
            $versioni = iterator_to_array((new DatiCatastaliModel($this->dataMapper))->getStorico($immobile), false);
            usort($versioni, fn(DatiCatastali $prima, DatiCatastali $seconda) => $prima->validoDal->format('Y-m-d') <=> $seconda->validoDal->format('Y-m-d'));
            $storico = [];
            foreach ($versioni as $datiCatastali) {
                $dal = self::getDecorrenza($datiCatastali);
                // le versioni precedenti non ancora efficaci a questa decorrenza non si applicano più
                while ((count($storico) > 0) && ($storico[count($storico) - 1]['dal'] >= $dal)) {
                    array_pop($storico);
                }
                $storico[] = ['datiCatastali' => $datiCatastali, 'dal' => $dal];
            }
            $this->storici[$immobile->id] = $storico;
        }
        return $this->storici[$immobile->id];
    }

    /**
     * Classamento IMU alla data: quello con l'ultima decorrenza IMU non
     * successiva; prima del primo, il primo (presunto) se richiesto.
     *
     * @return array{datiCatastali: DatiCatastali, dal: string, presunto: bool}|null
     */
    public function getVigenteAl(Immobile $immobile, \DateTimeInterface $data, bool $conPresunto = true): ?array
    {
        $giorno = $data->format('Y-m-d');
        $vigente = null;
        foreach ($this->getStorico($immobile) as $versione) {
            if ($versione['dal'] <= $giorno) {
                $vigente = $versione + ['presunto' => false];
            }
        }
        if (($vigente === null) && $conPresunto && (count($this->getStorico($immobile)) > 0)) {
            $vigente = $this->getStorico($immobile)[0] + ['presunto' => true];
        }
        return $vigente;
    }

    /**
     * Date da cui cambia il classamento IMU (per dividere i periodi).
     *
     * @return string[] Y-m-d
     */
    public function getDateVariazione(Immobile $immobile): array
    {
        return array_column($this->getStorico($immobile), 'dal');
    }
}
