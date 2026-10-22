<?php

namespace Successioni\Application\Services;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\EstremoVoltura;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;

/**
 * Dichiarazione sostitutiva di una dichiarazione presentata: copia in
 * bozza di frontespizio, beneficiari, beni con devoluzioni ed estremi del
 * quadro EI, passività con ripartizioni e allegati. La nuova dichiarazione
 * riporta gli estremi della prima (anno di presentazione, volume e numero
 * attribuiti dall'ufficio) e, come già versate, le imposte liquidate con la
 * prima; il tipo di sostitutiva (con o senza nuova trascrizione) si sceglie
 * nel frontespizio.
 */
class CopiaDichiarazione
{

    private const ESCLUSE_DICHIARAZIONE = ['id', 'tipoDichiarazione', 'stato', 'dataPresentazione', 'protocollo', 'registrazioneVolume', 'registrazioneNumero', 'sostituita',
        'voltureApplicate', 'dataInserimento', 'annoPrima', 'volumePrima', 'numeroPrima', 'ipotecariaVersata', 'catastaleVersata', 'successioneVersata'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function crea(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): DichiarazioneSuccessione
    {
        $originale = $dati->dichiarazione;
        $primaTransazione = $this->dataMapper->startTransaction();
        $copia = new DichiarazioneSuccessione($this->dataMapper);
        $this->copiaProprieta($originale, $copia, self::ESCLUSE_DICHIARAZIONE);
        $copia->tipoDichiarazione = TipoDichiarazioneSuccessione::sostitutivaConTrascrizione;
        $copia->stato = StatoDichiarazioneSuccessione::bozza;
        $copia->annoPrima = (int) ($originale->dataPresentazione ?? new SismaDate())->format('Y');
        $copia->volumePrima = $originale->registrazioneVolume;
        $copia->numeroPrima = $originale->registrazioneNumero;
        $copia->ipotecariaVersata = ((int) $originale->ipotecariaVersata + $risultato->ipotecariaDaVersare) ?: null;
        $copia->catastaleVersata = ((int) $originale->catastaleVersata + $risultato->catastaleDaVersare) ?: null;
        $copia->successioneVersata = ((int) $originale->successioneVersata + $risultato->successioneDaVersare) ?: null;
        $copia->sostituita = $originale;
        $copia->dataDichiarazione = new SismaDate();
        $copia->dataInserimento = new SismaDateTime();
        $this->dataMapper->save($copia);
        $beneficiari = [];
        foreach ($dati->beneficiari as $beneficiario) {
            $beneficiari[$beneficiario->id] = $this->copiaFiglio($beneficiario, new BeneficiarioSuccessione($this->dataMapper), ['dichiarazioneSuccessione' => $copia]);
        }
        $beni = [];
        foreach ($dati->getBeni() as $bene) {
            $beni[$bene->id] = $this->copiaFiglio($bene, new BeneSuccessione($this->dataMapper), ['dichiarazioneSuccessione' => $copia, 'azienda' => null]);
        }
        foreach ($dati->getBeni() as $bene) {
            if ($bene->azienda !== null) {
                $beni[$bene->id]->azienda = $beni[$bene->azienda->id];
                $this->dataMapper->save($beni[$bene->id]);
            }
            foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                $this->copiaFiglio($devoluzione, new DevoluzioneSuccessione($this->dataMapper), ['beneSuccessione' => $beni[$bene->id], 'beneficiarioSuccessione' => $beneficiari[$devoluzione->beneficiarioSuccessione->id]]);
            }
            foreach ($dati->getEstremi($bene) as $estremo) {
                $this->copiaFiglio($estremo, new EstremoVoltura($this->dataMapper), ['beneSuccessione' => $beni[$bene->id]]);
            }
        }
        foreach ($dati->passivita as $passivita) {
            $nuova = $this->copiaFiglio($passivita, new PassivitaSuccessione($this->dataMapper), ['dichiarazioneSuccessione' => $copia, 'beneSuccessione' => $passivita->beneSuccessione === null ? null : $beni[$passivita->beneSuccessione->id]]);
            foreach ($dati->getRipartizioni($passivita) as $ripartizione) {
                $this->copiaFiglio($ripartizione, new RipartizionePassivita($this->dataMapper), ['passivitaSuccessione' => $nuova, 'beneficiarioSuccessione' => $beneficiari[$ripartizione->beneficiarioSuccessione->id]]);
            }
        }
        foreach ($dati->allegati as $allegato) {
            $contenuto = new ContenutoAllegato($this->dataMapper);
            $contenuto->contenuto = $allegato->contenutoAllegato->contenuto;
            $this->dataMapper->save($contenuto);
            $this->copiaFiglio($allegato, new AllegatoSuccessione($this->dataMapper), ['dichiarazioneSuccessione' => $copia, 'contenutoAllegato' => $contenuto]);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $copia;
    }

    /**
     * Copia le proprietà dell'entità con i riferimenti indicati e la salva.
     *
     * @param array<string, mixed> $riferimenti
     */
    private function copiaFiglio(BaseEntity $origine, BaseEntity $copia, array $riferimenti): BaseEntity
    {
        $this->copiaProprieta($origine, $copia, array_merge(['id'], array_keys($riferimenti)));
        foreach ($riferimenti as $nome => $valore) {
            $copia->$nome = $valore;
        }
        $this->dataMapper->save($copia);
        return $copia;
    }

    /**
     * @param string[] $escluse
     */
    private function copiaProprieta(BaseEntity $origine, BaseEntity $copia, array $escluse): void
    {
        foreach ((new \ReflectionClass($origine))->getProperties(\ReflectionProperty::IS_PROTECTED) as $proprieta) {
            $nome = $proprieta->getName();
            if (($proprieta->getDeclaringClass()->getName() !== $origine::class) || in_array($nome, $escluse, true) || (($proprieta->isInitialized($origine) === false) && (isset($origine->$nome) === false))) {
                continue;
            }
            $copia->$nome = $origine->$nome;
        }
    }
}
