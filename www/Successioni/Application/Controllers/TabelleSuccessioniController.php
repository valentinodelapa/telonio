<?php

namespace Successioni\Application\Controllers;

use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Models\UfficioRegistrazioneModel;

/**
 * Ricerche per i campi a completamento automatico del modulo.
 */
class TabelleSuccessioniController extends SuccessioniController
{

    private const LIMITE_RISULTATI = 20;

    /**
     * Uffici di registrazione per codice o denominazione (prima quelli
     * attivi).
     */
    public function uffici(string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            $uffici = iterator_to_array((new UfficioRegistrazioneModel($this->dataMapper))->getEntityCollection(trim($testo), ['denominazione' => 'ASC'], 0, 200), false);
            usort($uffici, fn($primo, $secondo) => [$primo->periodo !== null, $primo->denominazione] <=> [$secondo->periodo !== null, $secondo->denominazione]);
            foreach (array_slice($uffici, 0, self::LIMITE_RISULTATI) as $ufficio) {
                $risultati[] = ['id' => $ufficio->id, 'etichetta' => $ufficio->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }
}
