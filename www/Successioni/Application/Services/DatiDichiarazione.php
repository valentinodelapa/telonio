<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\IdentificativoGraffato;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\IdentificativoGraffatoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Models\AllegatoSuccessioneModel;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;
use Successioni\Application\Models\EstremoVolturaModel;
use Successioni\Application\Models\PassivitaSuccessioneModel;
use Successioni\Application\Models\RipartizionePassivitaModel;

/**
 * Contenuto della dichiarazione letto una volta sola per liquidazione,
 * controlli, stampa e file telematico: beneficiari, beni per quadro con le
 * devoluzioni e i dati catastali alla data del decesso, passività con le
 * ripartizioni, allegati.
 */
class DatiDichiarazione
{

    /** @var BeneficiarioSuccessione[] */
    public array $beneficiari = [];

    /** @var array<string, BeneSuccessione[]> per valore di QuadroBene */
    public array $beni = [];

    /** @var array<int, DevoluzioneSuccessione[]> per id del bene */
    public array $devoluzioni = [];

    /** @var array<int, ?DatiCatastali> classamento alla data del decesso, per id del bene */
    public array $catasto = [];

    /** @var array<int, IdentificativoGraffato[]> identificativi graffati dei fabbricati, per id del bene */
    public array $graffati = [];

    /** @var array<int, \Successioni\Application\Entities\EstremoVoltura[]> estremi del quadro EI, per id del bene */
    public array $estremi = [];

    /** @var PassivitaSuccessione[] */
    public array $passivita = [];

    /** @var array<int, RipartizionePassivita[]> per id della passività */
    public array $ripartizioni = [];

    /** @var AllegatoSuccessione[] */
    public array $allegati = [];

    public function __construct(public readonly DichiarazioneSuccessione $dichiarazione, DataMapper $dataMapper = new DataMapper())
    {
        $this->beneficiari = iterator_to_array((new BeneficiarioSuccessioneModel($dataMapper))->getElenco($dichiarazione), false);
        $this->beni = (new BeneSuccessioneModel($dataMapper))->getPerQuadro($dichiarazione);
        $devoluzioneModel = new DevoluzioneSuccessioneModel($dataMapper);
        $datiCatastaliModel = new DatiCatastaliModel($dataMapper);
        $estremoModel = new EstremoVolturaModel($dataMapper);
        $graffatoModel = new IdentificativoGraffatoModel($dataMapper);
        foreach ($this->getBeni() as $bene) {
            $this->devoluzioni[$bene->id] = iterator_to_array($devoluzioneModel->getElenco($bene), false);
            if ($bene->discordanzaIntestatario) {
                $this->estremi[$bene->id] = iterator_to_array($estremoModel->getElenco($bene), false);
            }
            if ($bene->immobile !== null) {
                $this->catasto[$bene->id] = $datiCatastaliModel->getVigenteAl($bene->immobile, $dichiarazione->getDataApertura());
                if ($bene->quadro->isFabbricato()) {
                    $this->graffati[$bene->id] = iterator_to_array($graffatoModel->getElenco($bene->immobile), false);
                }
            }
        }
        $this->passivita = iterator_to_array((new PassivitaSuccessioneModel($dataMapper))->getElenco($dichiarazione), false);
        $ripartizioneModel = new RipartizionePassivitaModel($dataMapper);
        foreach ($this->passivita as $passivita) {
            $this->ripartizioni[$passivita->id] = iterator_to_array($ripartizioneModel->getElenco($passivita), false);
        }
        $this->allegati = iterator_to_array((new AllegatoSuccessioneModel($dataMapper))->getElenco($dichiarazione), false);
    }

    /**
     * @return BeneSuccessione[] tutti i beni nell'ordine dei quadri
     */
    public function getBeni(): array
    {
        return array_merge(...array_values($this->beni));
    }

    /**
     * @return BeneSuccessione[]
     */
    public function getBeniQuadro(QuadroBene $quadro): array
    {
        return $this->beni[$quadro->value] ?? [];
    }

    /**
     * @return DevoluzioneSuccessione[]
     */
    public function getDevoluzioni(BeneSuccessione $bene): array
    {
        return $this->devoluzioni[$bene->id] ?? [];
    }

    /**
     * @return IdentificativoGraffato[]
     */
    public function getGraffati(BeneSuccessione $bene): array
    {
        return $this->graffati[$bene->id] ?? [];
    }

    public function getCatasto(BeneSuccessione $bene): ?DatiCatastali
    {
        return $this->catasto[$bene->id] ?? null;
    }

    /**
     * @return RipartizionePassivita[]
     */
    public function getRipartizioni(PassivitaSuccessione $passivita): array
    {
        return $this->ripartizioni[$passivita->id] ?? [];
    }

    public function getBeneficiarioDichiarante(): ?BeneficiarioSuccessione
    {
        $presentatore = $this->dichiarazione->presentatore;
        $codiceFiscale = ($this->dichiarazione->rappresentato ?? $presentatore)?->codiceFiscale;
        foreach ($this->beneficiari as $beneficiario) {
            if ($beneficiario->soggetto->codiceFiscale === $codiceFiscale) {
                return $beneficiario;
            }
        }
        return null;
    }

    /**
     * @return \Successioni\Application\Entities\EstremoVoltura[]
     */
    public function getEstremi(BeneSuccessione $bene): array
    {
        return $this->estremi[$bene->id] ?? [];
    }

    /**
     * Immobili aziendali dell'azienda del quadro EN.
     *
     * @return BeneSuccessione[]
     */
    public function getImmobiliAziendali(BeneSuccessione $azienda): array
    {
        return array_values(array_filter($this->getBeni(), fn(BeneSuccessione $bene) => $bene->azienda?->id === $azienda->id));
    }

    /**
     * Numero del bene nel suo quadro (rigo progressivo nella stampa).
     */
    public function getNumero(BeneSuccessione $bene): int
    {
        return array_search($bene, $this->getBeniQuadro($bene->quadro), true) + 1;
    }
}
