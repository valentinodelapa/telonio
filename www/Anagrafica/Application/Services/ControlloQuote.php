<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Models\PossessoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Verifica che, alla decorrenza di un possesso, le quote dei possessori
 * dell'immobile non superino il 100% per ciascuna componente del diritto
 * (titolarità e godimento, vedi TitoloPossesso::getComponenti).
 *
 * Il controllo è fatto alla decorrenza della versione: le date successive
 * vanno verificate registrando le relative variazioni. Le altre versioni
 * dello stesso possesso (stesso soggetto e titolo) non si sommano, perché
 * lo storico le chiude alla nuova decorrenza.
 */
class ControlloQuote
{

    private const TOLLERANZA = 0.0001;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function verifica(Possesso $possesso): ?string
    {
        $totali = array_fill_keys($possesso->titolo->getComponenti(), $possesso->quota);
        if (isset($possesso->immobile->id)) {
            foreach ((new PossessoModel($this->dataMapper))->getVigentiAl($possesso->immobile, $possesso->validoDal) as $altro) {
                if (($altro->soggetto->id === $possesso->soggetto->id) && ($altro->titolo === $possesso->titolo)) {
                    continue;
                }
                foreach (array_intersect($altro->titolo->getComponenti(), array_keys($totali)) as $componente) {
                    $totali[$componente] += $altro->quota;
                }
            }
        }
        foreach ($totali as $totale) {
            if ($totale > 100 + self::TOLLERANZA) {
                return sprintf('Alla data del %s le quote dei possessori supererebbero il 100%% (%s%%): chiudi o riduci prima le quote degli altri possessori.',
                        $possesso->validoDal->format('d/m/Y'), number_format($totale, 2, ',', '.'));
            }
        }
        return null;
    }
}
