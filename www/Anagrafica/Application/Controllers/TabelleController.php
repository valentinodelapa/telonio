<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ricerche sulle tabelle di riferimento per l'autocompletamento.
 */
class TabelleController extends AnagraficaController
{

    private const LIMITE_RISULTATI = 15;
    private const LIMITE_VERSIONI_COMUNI = 200;

    /**
     * Una voce per ogni denominazione storica (codice catastale, nome,
     * provincia), rappresentata dalla sua versione più recente: le versioni
     * dovute alle sole variazioni territoriali non vengono ripetute.
     */
    public function comuni(string $testo = ''): Response
    {
        $voci = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new ComuneModel($this->dataMapper))->getEntityCollection(trim($testo), ['denominazione' => 'ASC', 'validoDal' => 'DESC'], 0, self::LIMITE_VERSIONI_COMUNI) as $comune) {
                $voci[$comune->codiceCatastale . '|' . $comune->getEtichetta()] ??= $comune;
            }
        }
        usort($voci, fn(Comune $primo, Comune $secondo) => [$primo->validoAl !== null, $primo->denominazione] <=> [$secondo->validoAl !== null, $secondo->denominazione]);
        $risultati = [];
        foreach (array_slice($voci, 0, self::LIMITE_RISULTATI) as $comune) {
            $risultati[] = ['id' => $comune->id, 'etichetta' => $comune->getEtichetta() . $this->descriviPeriodo($comune)];
        }
        return $this->render->generateJson($risultati);
    }

    public function nazioni(string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new NazioneModel($this->dataMapper))->getEntityCollection(trim($testo), ['validoAl' => 'ASC', 'denominazione' => 'ASC'], 0, self::LIMITE_RISULTATI) as $nazione) {
                $risultati[] = ['id' => $nazione->id, 'etichetta' => $nazione->denominazione . $this->descriviPeriodo($nazione)];
            }
        }
        return $this->render->generateJson($risultati);
    }

    /**
     * Sottocategorie ATECO della classificazione in vigore alla data
     * (decorrenza della versione dei dati fiscali; oggi se non indicata).
     */
    public function ateco(string $testo = '', string $al = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $al) ?: new \DateTimeImmutable();
            foreach ((new CodiceAtecoModel($this->dataMapper))->cercaSottocategorie(ClassificazioneAteco::vigenteAl($data), trim($testo), self::LIMITE_RISULTATI) as $codiceAteco) {
                $risultati[] = ['id' => $codiceAteco->id, 'etichetta' => $codiceAteco->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }

    /**
     * Le versioni non più in vigore (comuni soppressi o rinominati, stati
     * cessati) restano selezionabili, ad esempio per il luogo di nascita.
     */
    private function descriviPeriodo(Comune|Nazione $voce): string
    {
        return $voce->validoAl === null ? '' : ' - fino al ' . $voce->validoAl->format('d/m/Y');
    }
}
